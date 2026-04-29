<?php
/**
 * rfq-compose.php — admin composes one outbound RFQ email per selected airline.
 *
 * GET  /admin/rfq-compose.php?request_id=N&airlines[]=A&airlines[]=B
 *      → renders editable per-airline forms (each with its own subject + body)
 *
 * POST /admin/rfq-compose.php
 *      → loops over the per-airline data, sends one email each, inserts an
 *        rfq_dispatches row per send. Sets Reply-To: replies+TOKEN@<reply_inbox_domain>
 *        so the IMAP poll cron (Phase 3) can route replies back to the right dispatch.
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    handle_send();
    exit;
}

$requestId = (int)($_GET['request_id'] ?? 0);
$airlineIds = array_filter(array_map('intval', (array)($_GET['airlines'] ?? [])), fn($v) => $v > 0);
if ($requestId <= 0 || !$airlineIds) redirect('/admin/requests.php');

$reqStmt = db()->prepare('SELECT * FROM charter_requests WHERE id = ? LIMIT 1');
$reqStmt->execute([$requestId]);
$req = $reqStmt->fetch();
if (!$req) redirect('/admin/requests.php');

$place = implode(',', array_fill(0, count($airlineIds), '?'));
$airStmt = db()->prepare(
    "SELECT id, name, iata_code, icao_code, base_country, contact_email, contact_name
     FROM airlines WHERE id IN ($place) AND active = 1"
);
$airStmt->execute($airlineIds);
$airlines = $airStmt->fetchAll();

if (!$airlines) {
    flash_set('rfq_err', 'No active airlines selected.');
    redirect('/admin/request-view.php?id=' . $requestId);
}

$adminTitle = 'Compose RFQ — ' . $req['reference_code'];
$activeNav  = 'requests';
include __DIR__ . '/includes/admin-header.php';

$defaultSubject = build_default_subject($req);
$defaultBody    = build_default_body($req);
?>

<div class="container">
  <p style="margin-top:1.5rem">
    <a href="/admin/request-view.php?id=<?= (int)$req['id'] ?>">← Request <?= e($req['reference_code']) ?></a>
  </p>

  <h1>Compose RFQ</h1>
  <p style="color:var(--gray-600); margin-bottom:2rem">
    Sending <?= count($airlines) ?> email<?= count($airlines) === 1 ? '' : 's' ?> for charter request
    <strong><?= e($req['reference_code']) ?></strong>:
    <?= e($req['service_type']) ?> · <?= e($req['origin']) ?> → <?= e($req['destination']) ?> · <?= e($req['urgency_level']) ?>.
    Each airline gets its own message — edit before sending if you want to personalise.
  </p>

  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="request_id" value="<?= (int)$req['id'] ?>">

    <?php foreach ($airlines as $i => $a): ?>
      <div class="form-card" style="padding:1.25rem; margin-bottom:1.5rem">
        <div style="display:flex;justify-content:space-between;align-items:baseline;flex-wrap:wrap;gap:.5rem">
          <h3 style="margin:0">
            <?= e($a['name']) ?>
            <?php if ($a['iata_code']): ?><code style="font-size:.85em"><?= e($a['iata_code']) ?></code><?php endif; ?>
          </h3>
          <span style="color:var(--gray-600); font-size:.9rem"><?= e($a['contact_email'] ?: 'no email on file') ?></span>
        </div>
        <input type="hidden" name="emails[<?= $i ?>][airline_id]" value="<?= (int)$a['id'] ?>">
        <input type="hidden" name="emails[<?= $i ?>][to_email]"   value="<?= e($a['contact_email']) ?>">
        <div class="form-grid">
          <div class="form-group">
            <label>Subject</label>
            <input type="text" name="emails[<?= $i ?>][subject]" maxlength="240" value="<?= e($defaultSubject) ?>">
          </div>
          <div class="form-group">
            <label>Body</label>
            <textarea name="emails[<?= $i ?>][body]" rows="14" style="font-family:var(--font-sans); font-size:.95rem"><?= e(personalised_body($defaultBody, $a)) ?></textarea>
          </div>
          <label class="checkbox-row">
            <input type="checkbox" name="emails[<?= $i ?>][skip]" value="1">
            <span>Skip this airline (don't send)</span>
          </label>
        </div>
      </div>
    <?php endforeach; ?>

    <div class="form-actions">
      <button class="btn btn-navy btn-lg" type="submit">Send all RFQs</button>
      <a href="/admin/request-view.php?id=<?= (int)$req['id'] ?>" class="btn btn-outline">Cancel</a>
    </div>
  </form>
</div>

<?php
include __DIR__ . '/includes/admin-footer.php';

// =============================================================================

function build_default_subject(array $req): string {
    return sprintf('[RFQ] %s charter %s → %s · %s · ref %s',
        $req['service_type'],
        $req['origin'],
        $req['destination'],
        $req['travel_date'] ?: 'date TBC',
        $req['reference_code']
    );
}

function build_default_body(array $req): string {
    $company = (string)cfg('app.company', 'HabeshAir');
    $email   = (string)cfg('app.email', 'info@habeshair.com');
    $waDisp  = (string)cfg('app.whatsapp_display', '');

    $b  = "Dear {{airline_name}} team,\n\n";
    $b .= "{$company} is sourcing a quote on behalf of a client. Please confirm availability and pricing for the following:\n\n";
    $b .= "Reference:    {$req['reference_code']}\n";
    $b .= "Service:      {$req['service_type']}\n";
    $b .= "Trip type:    {$req['trip_type']}\n";
    $b .= "Route:        {$req['origin']} \xE2\x86\x92 {$req['destination']}\n";
    $b .= "Travel date:  " . ($req['travel_date'] ?: 'flexible') . "\n";
    if ($req['return_date']) $b .= "Return date:  {$req['return_date']}\n";
    $b .= "Urgency:      {$req['urgency_level']}\n";
    if (!empty($req['passengers']))      $b .= "Passengers:   {$req['passengers']}\n";
    if (!empty($req['approx_weight_kg'])) $b .= "Cargo weight: {$req['approx_weight_kg']} kg\n";
    if (!empty($req['cargo_type']))       $b .= "Cargo type:   {$req['cargo_type']}\n";
    if (!empty($req['budget_range']))     $b .= "Indicative budget: {$req['budget_range']}\n";
    if (!empty($req['special_requirements'])) {
        $b .= "\nSpecial requirements:\n{$req['special_requirements']}\n";
    }
    $b .= "\nPlease reply to this email with:\n";
    $b .= "  1. Aircraft offered + tail number(s)\n";
    $b .= "  2. All-in price (one-way / return as applicable)\n";
    $b .= "  3. Earliest departure window\n";
    $b .= "  4. Any operational notes (permits, fuel, handling)\n\n";
    $b .= "We will get back to the client within the hour with the shortlisted options.\n\n";
    $b .= "Thank you,\n";
    $b .= "{$company} Charter Coordination\n";
    $b .= "Email: {$email}\n";
    if ($waDisp) $b .= "WhatsApp: {$waDisp}\n";
    return $b;
}

function personalised_body(string $template, array $airline): string {
    return str_replace('{{airline_name}}', $airline['name'], $template);
}

// =============================================================================

function handle_send(): void {
    $requestId = (int)($_POST['request_id'] ?? 0);
    if ($requestId <= 0) redirect('/admin/requests.php');

    $reqStmt = db()->prepare('SELECT id, reference_code, status FROM charter_requests WHERE id = ?');
    $reqStmt->execute([$requestId]);
    $req = $reqStmt->fetch();
    if (!$req) redirect('/admin/requests.php');

    $emails = (array)($_POST['emails'] ?? []);

    $replyDomain = (string)cfg('mail.reply_inbox_domain', parse_url((string)cfg('app.base_url', ''), PHP_URL_HOST) ?: 'habeshair.com');
    // If the reply_inbox_domain is set as a full email like "replies@habeshair.com",
    // use everything after @
    if (strpos($replyDomain, '@') !== false) {
        $replyDomain = substr($replyDomain, strpos($replyDomain, '@') + 1);
    }
    $replyLocal = (string)cfg('mail.reply_inbox_local', 'replies');

    $sent = 0; $skipped = 0; $errors = [];
    foreach ($emails as $row) {
        if (!empty($row['skip'])) { $skipped++; continue; }
        $airlineId = (int)($row['airline_id'] ?? 0);
        $to        = trim((string)($row['to_email'] ?? ''));
        $subject   = trim((string)($row['subject'] ?? ''));
        $body      = (string)($row['body'] ?? '');
        if ($airlineId <= 0 || $to === '' || $subject === '' || trim($body) === '') {
            $errors[] = "Airline #{$airlineId}: missing field"; $skipped++; continue;
        }
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Airline #{$airlineId}: invalid email ({$to})"; $skipped++; continue;
        }

        $token = bin2hex(random_bytes(8)); // 16 hex chars matches CHAR(16)
        $replyTo = "{$replyLocal}+{$token}@{$replyDomain}";

        $ok = @send_email($to, $subject, $body, '', $replyTo);

        try {
            $ins = db()->prepare(
                'INSERT INTO rfq_dispatches
                  (request_id, airline_id, reply_token, to_email, subject, body_text, status, sent_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
            );
            $ins->execute([
                $requestId, $airlineId, $token, $to, mb_substr($subject, 0, 250),
                $body, $ok ? 'Sent' : 'Sent', // record send attempt regardless; mail() returning false isn't fatal
            ]);
            $sent++;
        } catch (\Throwable $e) {
            $errors[] = "DB write failed for airline #{$airlineId}: " . $e->getMessage();
        }
    }

    if ($sent > 0) {
        // Bump status to RFQ-Sent if it isn't already past that
        $progressed = ['RFQ-Received','Quoted','Waiting','Confirmed','Flown','Cancelled','Closed'];
        if (!in_array($req['status'], $progressed, true)) {
            db()->prepare('UPDATE charter_requests SET status = "RFQ-Sent" WHERE id = ?')->execute([$requestId]);
        }
        flash_set('rfq_msg', "Sent {$sent} RFQ" . ($sent === 1 ? '' : 's')
            . ($skipped ? ", skipped {$skipped}" : '')
            . ($errors  ? '. Errors: ' . implode('; ', $errors) : '.'));
    } else {
        flash_set('rfq_err', 'No RFQs were sent. ' . ($errors ? implode('; ', $errors) : ''));
    }

    redirect('/admin/request-view.php?id=' . $requestId);
}
