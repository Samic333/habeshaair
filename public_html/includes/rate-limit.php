<?php
/**
 * rate-limit.php — simple file-based rolling-window throttle keyed by IP.
 * Stores JSON timestamp lists under logs/ratelimit/.
 */

function rate_check(string $bucket, int $maxHits, int $windowSeconds, ?string $ip = null): bool {
    $ip = $ip ?? client_ip() ?? 'unknown';
    $dir = __DIR__ . '/../logs/ratelimit';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $key  = preg_replace('/[^a-z0-9_-]/i', '_', $bucket . '_' . $ip);
    $file = $dir . '/' . $key . '.json';

    $now  = time();
    $hits = [];
    if (is_file($file)) {
        $raw = @file_get_contents($file);
        $hits = $raw ? (json_decode($raw, true) ?: []) : [];
    }
    $hits = array_values(array_filter($hits, fn($t) => ($now - (int)$t) < $windowSeconds));
    if (count($hits) >= $maxHits) return false;
    $hits[] = $now;
    @file_put_contents($file, json_encode($hits), LOCK_EX);
    return true;
}
