<?php
/**
 * airlines-sync.php — shared library: pull the airlines Google Sheet (CSV
 * publish URL) and upsert into the `airlines` table.
 *
 * Called from BOTH:
 *   - /cron/sync-airlines.php   (hourly cron, ?secret=XXX auth)
 *   - /admin/airlines-sync.php  (manual button on dashboard)
 *
 * Sheet column order (must match exactly):
 *   sheet_row_id | name | iata_code | icao_code | base_country |
 *   contact_email | contact_name | phone | whatsapp | website |
 *   fleet_types | regions_served | service_types |
 *   capacity_pax_max | capacity_kg_max | rating | notes | active
 *
 * List columns are pipe-delimited inside the cell: "DHC-8|King Air"
 *
 * Behaviour:
 *   - rows in sheet but not in DB → INSERT (is_new=1)
 *   - rows in both              → UPDATE (preserves rating + notes if blank in sheet)
 *   - rows in DB but not in sheet → soft-delete (active=0)
 */

function sync_airlines_from_sheet(): array {
    $url = (string)cfg('sheets_sync.airlines_csv_url', '');
    if ($url === '') {
        return ['ok' => false, 'error' => 'cfg(sheets_sync.airlines_csv_url) not set'];
    }

    // Fetch CSV
    $ctx = stream_context_create(['http' => [
        'timeout' => 30,
        'header'  => "User-Agent: HabeshAir-Sync/1.0\r\n",
        'follow_location' => 1,
    ]]);
    $csv = @file_get_contents($url, false, $ctx);
    if ($csv === false || $csv === '') {
        return ['ok' => false, 'error' => 'Failed to fetch sheet CSV'];
    }

    // Parse CSV
    $lines = preg_split('/\r\n|\n|\r/', trim($csv));
    if (count($lines) < 2) {
        return ['ok' => false, 'error' => 'Sheet has no data rows'];
    }
    array_shift($lines); // drop header

    $sheetRowIds  = [];
    $inserted     = 0;
    $updated      = 0;
    $skipped      = 0;
    $errors       = [];

    $insertSql = 'INSERT INTO airlines
        (sheet_row_id, name, iata_code, icao_code, base_country, contact_email,
         contact_name, phone, whatsapp, website, fleet_types, regions_served,
         service_types, capacity_pax_max, capacity_kg_max, rating, notes, active,
         is_new, synced_at, created_at)
        VALUES
        (:sid, :name, :iata, :icao, :country, :email, :cname, :phone, :wa, :site,
         :fleet, :regions, :stypes, :pax, :kg, :rating, :notes, :active, 1, NOW(), NOW())
        ON DUPLICATE KEY UPDATE
          name           = VALUES(name),
          iata_code      = VALUES(iata_code),
          icao_code      = VALUES(icao_code),
          base_country   = VALUES(base_country),
          contact_email  = VALUES(contact_email),
          contact_name   = VALUES(contact_name),
          phone          = VALUES(phone),
          whatsapp       = VALUES(whatsapp),
          website        = VALUES(website),
          fleet_types    = VALUES(fleet_types),
          regions_served = VALUES(regions_served),
          service_types  = VALUES(service_types),
          capacity_pax_max = VALUES(capacity_pax_max),
          capacity_kg_max  = VALUES(capacity_kg_max),
          rating         = VALUES(rating),
          notes          = COALESCE(NULLIF(VALUES(notes), ""), notes),
          active         = VALUES(active),
          synced_at      = NOW()';

    $stmt = db()->prepare($insertSql);

    foreach ($lines as $i => $line) {
        if (trim($line) === '') continue;
        $cols = str_getcsv($line);
        if (count($cols) < 18) {
            $errors[] = ['row' => $i + 2, 'msg' => 'Less than 18 columns', 'data' => substr($line, 0, 80)];
            $skipped++;
            continue;
        }

        $sid     = trim($cols[0]);
        $name    = trim($cols[1]);
        $iata    = strtoupper(trim($cols[2])) ?: null;
        $icao    = strtoupper(trim($cols[3])) ?: null;
        $country = trim($cols[4]) ?: null;
        $email   = trim($cols[5]) ?: null;
        $cname   = trim($cols[6]) ?: null;
        $phone   = trim($cols[7]) ?: null;
        $wa      = trim($cols[8]) ?: null;
        $site    = trim($cols[9]) ?: null;
        $fleet   = _split_pipe($cols[10]);
        $regions = _split_pipe($cols[11]);
        $stypes  = _split_pipe($cols[12]);
        $pax     = ctype_digit(trim($cols[13])) ? (int)$cols[13] : null;
        $kg      = ctype_digit(trim($cols[14])) ? (int)$cols[14] : null;
        $rating  = ctype_digit(trim($cols[15])) ? min(5, (int)$cols[15]) : 0;
        $notes   = trim($cols[16]) ?: null;
        $active  = _truthy($cols[17]) ? 1 : 0;

        if ($sid === '' || $name === '') {
            $errors[] = ['row' => $i + 2, 'msg' => 'Missing sheet_row_id or name'];
            $skipped++;
            continue;
        }

        $check = db()->prepare('SELECT id FROM airlines WHERE sheet_row_id = ?');
        $check->execute([$sid]);
        $existed = (bool)$check->fetchColumn();

        try {
            $stmt->execute([
                ':sid'     => $sid,
                ':name'    => $name,
                ':iata'    => $iata,
                ':icao'    => $icao,
                ':country' => $country,
                ':email'   => $email,
                ':cname'   => $cname,
                ':phone'   => $phone,
                ':wa'      => $wa,
                ':site'    => $site,
                ':fleet'   => $fleet ? json_encode($fleet) : null,
                ':regions' => $regions ? json_encode($regions) : null,
                ':stypes'  => $stypes ? json_encode($stypes) : null,
                ':pax'     => $pax,
                ':kg'      => $kg,
                ':rating'  => $rating,
                ':notes'   => $notes,
                ':active'  => $active,
            ]);
            $sheetRowIds[] = $sid;
            if ($existed) $updated++; else $inserted++;
        } catch (\Throwable $e) {
            $errors[] = ['row' => $i + 2, 'msg' => $e->getMessage()];
            $skipped++;
        }
    }

    // Soft-delete rows missing from sheet
    $deactivated = 0;
    if ($sheetRowIds) {
        $place = implode(',', array_fill(0, count($sheetRowIds), '?'));
        $upd = db()->prepare("UPDATE airlines SET active = 0
                              WHERE sheet_row_id IS NOT NULL
                              AND sheet_row_id NOT IN ($place)
                              AND active = 1");
        $upd->execute($sheetRowIds);
        $deactivated = $upd->rowCount();
    }

    // Clear is_new flag for rows older than 24h (so the highlight only lasts a day)
    db()->exec('UPDATE airlines SET is_new = 0
                WHERE is_new = 1 AND synced_at < (NOW() - INTERVAL 24 HOUR)');

    if ($inserted > 0) {
        $subject = "🆕 {$inserted} new airline(s) synced — HabeshAir";
        $body  = "{$inserted} new airline(s) added from the Google Sheet.\n";
        $body .= "{$updated} updated, {$deactivated} deactivated.\n\n";
        $body .= "Review them: " . url('/admin/airlines.php?new=1') . "\n";
        @send_admin_notification($subject, $body);
    }

    return [
        'ok'          => true,
        'inserted'    => $inserted,
        'updated'     => $updated,
        'deactivated' => $deactivated,
        'skipped'     => $skipped,
        'errors'      => $errors,
        'total_rows'  => count($sheetRowIds),
        'at'          => date('c'),
    ];
}

function _split_pipe(?string $cell): array {
    $cell = trim((string)$cell);
    if ($cell === '') return [];
    return array_values(array_filter(array_map('trim', explode('|', $cell)), fn($v) => $v !== ''));
}

function _truthy($v): bool {
    $v = strtolower(trim((string)$v));
    return in_array($v, ['1','true','yes','y','active','on'], true);
}
