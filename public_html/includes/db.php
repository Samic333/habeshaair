<?php
/**
 * db.php — PDO singleton with prepared statements only.
 */

function db(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $host = cfg('db.host', 'localhost');
    $name = cfg('db.name');
    $user = cfg('db.user');
    $pass = cfg('db.pass');
    $charset = cfg('db.charset', 'utf8mb4');

    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $host, $name, $charset);
    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+00:00'",
        ]);
    } catch (PDOException $ex) {
        error_log('DB connection failed: ' . $ex->getMessage());
        http_response_code(500);
        exit('Service temporarily unavailable. Please try again shortly.');
    }
    return $pdo;
}
