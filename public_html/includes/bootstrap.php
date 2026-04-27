<?php
/**
 * bootstrap.php — single entry point loaded by every page.
 * Loads config, sets error mode, starts session, requires helpers.
 */

// TEMP DEBUG: force errors visible from the very first line
error_reporting(E_ALL);
@ini_set('display_errors', '1');
@ini_set('display_startup_errors', '1');

if (defined('HA_BOOTSTRAPPED')) return;
define('HA_BOOTSTRAPPED', true);

// Load config (real on server, sample on first-run for clearer error)
$configPath = __DIR__ . '/config.php';
if (!file_exists($configPath)) {
    http_response_code(500);
    exit('Configuration missing. Copy includes/config.sample.php to includes/config.php and fill in credentials.');
}
$CONFIG = require $configPath;
echo "<!-- HA: config loaded, type=" . gettype($CONFIG) . " env=" . ($CONFIG['app']['env'] ?? 'NOT_SET') . " -->\n";

date_default_timezone_set($CONFIG['app']['timezone'] ?? 'UTC');

// Session — secure cookie params before session_start
if (session_status() === PHP_SESSION_NONE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
           || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name('HASESS');
    session_start();
}

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/validation.php';
require_once __DIR__ . '/rate-limit.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mailer.php';
