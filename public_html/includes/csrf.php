<?php
/**
 * csrf.php — issue and verify session-bound tokens.
 */

function csrf_token(): string {
    if (empty($_SESSION['_csrf']) || empty($_SESSION['_csrf_at']) ||
        (time() - (int)$_SESSION['_csrf_at']) > (int)cfg('security.csrf_ttl', 7200)) {
        $_SESSION['_csrf']    = bin2hex(random_bytes(32));
        $_SESSION['_csrf_at'] = time();
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string {
    return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
}

function csrf_check(?string $tok): bool {
    if (!$tok || empty($_SESSION['_csrf'])) return false;
    return hash_equals($_SESSION['_csrf'], $tok);
}

function csrf_require(): void {
    if (!csrf_check($_POST['_token'] ?? null)) {
        http_response_code(400);
        exit('Invalid request token. Please refresh and try again.');
    }
}
