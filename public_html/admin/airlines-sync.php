<?php
/**
 * airlines-sync.php — handler for the "Sync now" button on /admin/airlines.php
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/airlines-sync.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/admin/airlines.php');
}
csrf_require();

$result = sync_airlines_from_sheet();

if (!empty($result['ok'])) {
    flash_set('airlines_msg', sprintf(
        'Sync complete: %d inserted, %d updated, %d deactivated, %d skipped.',
        (int)$result['inserted'], (int)$result['updated'],
        (int)$result['deactivated'], (int)$result['skipped']
    ));
} else {
    flash_set('airlines_err', 'Sync failed: ' . (string)($result['error'] ?? 'unknown error'));
}

redirect('/admin/airlines.php');
