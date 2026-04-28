<?php
/**
 * sync-airlines.php — cron entry point. Authenticates via ?secret=XXX and
 * runs sync_airlines_from_sheet() (defined in includes/airlines-sync.php).
 *
 * cPanel cron line:
 *   0 * * * * /usr/bin/curl -fsS "https://habeshair.com/cron/sync-airlines.php?secret=XXX"
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/airlines-sync.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$secret = (string)cfg('cron.secret', '');
if ($secret === '' || ($_GET['secret'] ?? '') !== $secret) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

echo json_encode(sync_airlines_from_sheet());
