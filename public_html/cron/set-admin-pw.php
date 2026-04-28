<?php
/**
 * set-admin-pw.php — one-shot endpoint to update an admin user's bcrypt hash.
 * Auth: ?secret=XXX matches cfg('cron.secret').
 * Usage: ?secret=XXX&user=admin&hash=$2y$12$...
 *
 * DELETE THIS FILE after successful run.
 */
require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$secret = (string)cfg('cron.secret', '');
if ($secret === '' || ($_GET['secret'] ?? '') !== $secret) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

$user = trim((string)($_GET['user'] ?? ''));
$hash = trim((string)($_GET['hash'] ?? ''));
if ($user === '' || strlen($hash) < 30 || strpos($hash, '$2y$') !== 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Bad input']);
    exit;
}

try {
    $stmt = db()->prepare('UPDATE admin_users SET password_hash = ? WHERE username = ?');
    $stmt->execute([$hash, $user]);
    $rows = $stmt->rowCount();
    echo json_encode(['ok' => true, 'rows_affected' => $rows]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
