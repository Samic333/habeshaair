<?php
/**
 * helpers.php — small utilities used across the site.
 */

function cfg(string $path, $default = null) {
    global $CONFIG;
    $parts = explode('.', $path);
    $node  = $CONFIG;
    foreach ($parts as $p) {
        if (!is_array($node) || !array_key_exists($p, $node)) return $default;
        $node = $node[$p];
    }
    return $node;
}

function e($s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = '/'): string {
    $base = rtrim((string)cfg('app.base_url', ''), '/');
    if ($path !== '' && $path[0] !== '/') $path = '/' . $path;
    return $base . $path;
}

function redirect(string $path): void {
    header('Location: ' . $path, true, 303);
    exit;
}

function flash_set(string $key, $value): void {
    $_SESSION['_flash'][$key] = $value;
}

function flash_get(string $key, $default = null) {
    if (!isset($_SESSION['_flash'][$key])) return $default;
    $v = $_SESSION['_flash'][$key];
    unset($_SESSION['_flash'][$key]);
    return $v;
}

function flash_peek(string $key, $default = null) {
    return $_SESSION['_flash'][$key] ?? $default;
}

function reference_code(string $prefix = 'HA'): string {
    $time = strtoupper(base_convert((string)time(), 10, 36));
    $rand = strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
    return sprintf('%s-%s-%s', $prefix, $time, $rand);
}

function client_ip(): ?string {
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = trim(explode(',', $_SERVER[$key])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
        }
    }
    return null;
}

function ip_to_binary(?string $ip): ?string {
    if (!$ip) return null;
    $packed = @inet_pton($ip);
    return $packed !== false ? $packed : null;
}

function whatsapp_link(string $message = ''): string {
    $num = (string)cfg('app.whatsapp', '');
    $base = 'https://wa.me/' . $num;
    return $message !== '' ? $base . '?text=' . rawurlencode($message) : $base;
}

function active_nav(string $key): string {
    global $page;
    return (($page['active_nav'] ?? '') === $key) ? ' class="active"' : '';
}

function page_title(): string {
    global $page;
    return $page['title'] ?? cfg('app.company') . ' — Premium Air Charter Africa & Beyond';
}

function asset(string $path): string {
    if ($path !== '' && $path[0] !== '/') $path = '/' . $path;
    return '/assets' . $path;
}
