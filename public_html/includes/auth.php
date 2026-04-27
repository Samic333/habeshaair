<?php
/**
 * auth.php — admin session helpers.
 * Loaded by every admin page; not auto-loaded by bootstrap.php (public pages don't need it).
 */

function current_admin(): ?array {
    if (empty($_SESSION['admin_id'])) return null;
    static $cache = null;
    if ($cache !== null) return $cache;
    $stmt = db()->prepare('SELECT id, username, email, display_name, is_active FROM admin_users WHERE id = ? LIMIT 1');
    $stmt->execute([(int)$_SESSION['admin_id']]);
    $row = $stmt->fetch();
    if (!$row || (int)$row['is_active'] !== 1) {
        admin_logout();
        return null;
    }
    return $cache = $row;
}

function require_admin(): void {
    if (!current_admin()) {
        $next = $_SERVER['REQUEST_URI'] ?? '/admin/';
        header('Location: /admin/login.php?next=' . urlencode($next), true, 302);
        exit;
    }
}

function admin_login(int $adminId): void {
    session_regenerate_id(true);
    $_SESSION['admin_id']       = $adminId;
    $_SESSION['admin_login_at'] = time();
    db()->prepare('UPDATE admin_users SET last_login_at = NOW() WHERE id = ?')->execute([$adminId]);
}

function admin_logout(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', $p['secure'], $p['httponly']);
    }
    session_destroy();
}
