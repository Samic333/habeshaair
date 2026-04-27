<?php
/**
 * sheets.php — push form submissions to a Google Apps Script webhook
 * which appends them to the corresponding Google Sheet in the
 * HabeshAir Drive folder. Non-blocking: failures are logged but never
 * surface to the user.
 */

function sheet_log(string $kind, array $row): bool {
    $url    = (string)cfg('sheets.webhook_url', '');
    $secret = (string)cfg('sheets.secret', '');
    if ($url === '' || $secret === '') return false;
    if (!in_array($kind, ['request', 'contact'], true)) return false;

    $body = json_encode([
        'kind'   => $kind,
        'secret' => $secret,
        'row'    => $row,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    $ctx = stream_context_create([
        'http' => [
            'method'        => 'POST',
            'header'        => "Content-Type: application/json\r\n",
            'content'       => $body,
            'timeout'       => 5,
            'ignore_errors' => true,
            'follow_location' => 1,
        ],
        'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
    ]);

    $res = @file_get_contents($url, false, $ctx);
    if ($res === false) {
        @file_put_contents(__DIR__ . '/../logs/sheets.log',
            sprintf("[%s] sheet_log %s failed (transport)\n", date('c'), $kind),
            FILE_APPEND);
        return false;
    }
    $ok = is_string($res) && str_contains($res, '"ok":true');
    if (!$ok) {
        @file_put_contents(__DIR__ . '/../logs/sheets.log',
            sprintf("[%s] sheet_log %s response: %s\n", date('c'), $kind, substr($res, 0, 300)),
            FILE_APPEND);
    }
    return $ok;
}
