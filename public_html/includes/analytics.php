<?php
/**
 * analytics.php — lightweight in-house pageview tracker.
 *
 * Called once per public page from header.php (single function call,
 * fire-and-forget). Skips bots, admin, cron, asset, and well-known crawler
 * paths. Geo resolution via free ipapi.co (cached in visitor_geo_cache to
 * avoid hammering the free tier).
 *
 * No tracking JS. No cookies. Uses a daily visitor_hash = md5(ip + ua + day)[:16]
 * so unique-visitor counts work without storing PII beyond the IP itself.
 */

function track_pageview(): void {
    try {
        $path = (string)($_SERVER['REQUEST_URI'] ?? '/');
        $path = strtok($path, '?');                  // strip query string
        if (!is_string($path) || $path === '') $path = '/';

        // Skip noisy / non-public paths
        $skipPrefixes = ['/admin', '/cron', '/assets', '/favicon', '/robots.txt', '/sitemap'];
        foreach ($skipPrefixes as $p) {
            if (strncmp($path, $p, strlen($p)) === 0) return;
        }
        // Skip explicit list from config (e.g. ['/healthz'])
        $skipPaths = (array)cfg('analytics.skip_paths', []);
        if (in_array($path, $skipPaths, true)) return;

        $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
        $device = _at_classify_device($ua);
        if ($device === 'bot') return;               // do not record bots

        $ip = client_ip();
        $skipIps = (array)cfg('analytics.skip_ips', ['127.0.0.1', '::1']);
        if ($ip && in_array($ip, $skipIps, true)) return;

        $referrer = (string)($_SERVER['HTTP_REFERER'] ?? '');
        if (strlen($referrer) > 500) $referrer = substr($referrer, 0, 500);
        // Drop referrers that point back to ourselves
        if ($referrer !== '') {
            $host = parse_url($referrer, PHP_URL_HOST) ?: '';
            $self = parse_url((string)cfg('app.base_url', ''), PHP_URL_HOST) ?: '';
            if ($self && $host && stripos($host, $self) !== false) $referrer = '';
        }

        $visitorHash = $ip ? substr(md5($ip . '|' . $ua . '|' . date('Y-m-d')), 0, 16) : null;

        // Geo resolve (cached)
        $geo = $ip ? _at_geo_resolve($ip) : ['country_code'=>null, 'country_name'=>null, 'city'=>null];

        $stmt = db()->prepare(
            'INSERT INTO page_views
             (path, referrer, ip_address, visitor_hash, country_code, country_name, city,
              user_agent, device_type, created_at)
             VALUES (:path, :ref, :ip, :vh, :cc, :cn, :ci, :ua, :dev, NOW())'
        );
        $stmt->execute([
            ':path' => substr($path, 0, 255),
            ':ref'  => $referrer !== '' ? $referrer : null,
            ':ip'   => ip_to_binary($ip),
            ':vh'   => $visitorHash,
            ':cc'   => $geo['country_code'] ?? null,
            ':cn'   => $geo['country_name'] ?? null,
            ':ci'   => $geo['city'] ?? null,
            ':ua'   => substr($ua, 0, 255),
            ':dev'  => $device,
        ]);
    } catch (\Throwable $e) {
        // Never break a page render because analytics failed
        @file_put_contents(__DIR__ . '/../logs/analytics.log',
            sprintf("[%s] track_pageview: %s\n", date('c'), $e->getMessage()),
            FILE_APPEND);
    }
}

function _at_classify_device(string $ua): string {
    if ($ua === '') return 'other';
    $u = strtolower($ua);
    // Bots — broad regex covering common crawlers
    if (preg_match('~bot|crawler|spider|slurp|curl|wget|python-requests|axios|httpclient|facebookexternalhit|telegrambot|whatsapp|preview|monitor|uptimerobot|pingdom~i', $ua)) {
        return 'bot';
    }
    if (preg_match('~ipad|tablet|kindle|playbook~i', $ua)) return 'tablet';
    if (preg_match('~mobi|iphone|ipod|android.*mobile|windows phone~i', $ua)) return 'mobile';
    if (preg_match('~mozilla|chrome|safari|edge|firefox|opera~i', $ua)) return 'desktop';
    return 'other';
}

/**
 * Resolve IP -> country/city. Cached in visitor_geo_cache for 30 days.
 * Falls back gracefully if no network or API error.
 */
function _at_geo_resolve(string $ip): array {
    static $memo = [];
    if (isset($memo[$ip])) return $memo[$ip];

    $bin = ip_to_binary($ip);
    if (!$bin) return $memo[$ip] = ['country_code'=>null,'country_name'=>null,'city'=>null];

    // Cache check (30 day TTL)
    try {
        $stmt = db()->prepare(
            'SELECT country_code, country_name, city FROM visitor_geo_cache
             WHERE ip_address = :ip AND cached_at > (NOW() - INTERVAL 30 DAY) LIMIT 1'
        );
        $stmt->execute([':ip' => $bin]);
        $row = $stmt->fetch();
        if ($row) return $memo[$ip] = [
            'country_code' => $row['country_code'],
            'country_name' => $row['country_name'],
            'city'         => $row['city'],
        ];
    } catch (\Throwable $e) { /* cache miss; proceed */ }

    // Lookup via ipapi.co (free, 1000/day, no auth). 1.5s timeout — cheap enough.
    $geo = ['country_code'=>null,'country_name'=>null,'city'=>null];
    $url = 'https://ipapi.co/' . rawurlencode($ip) . '/json/';
    $ctx = stream_context_create(['http' => [
        'timeout' => 1.5,
        'header'  => "User-Agent: HabeshAir/1.0\r\n",
        'ignore_errors' => true,
    ]]);
    $resp = @file_get_contents($url, false, $ctx);
    if ($resp !== false) {
        $j = json_decode($resp, true);
        if (is_array($j) && empty($j['error'])) {
            $geo['country_code'] = isset($j['country_code']) ? substr((string)$j['country_code'], 0, 2) : null;
            $geo['country_name'] = isset($j['country_name']) ? substr((string)$j['country_name'], 0, 80) : null;
            $geo['city']         = isset($j['city']) ? substr((string)$j['city'], 0, 120) : null;
        }
    }

    // Save to cache (always, even if NULLs — avoids retry storms on dead IPs)
    try {
        $stmt = db()->prepare(
            'INSERT INTO visitor_geo_cache (ip_address, country_code, country_name, city, cached_at)
             VALUES (:ip, :cc, :cn, :ci, NOW())
             ON DUPLICATE KEY UPDATE country_code=VALUES(country_code),
                                     country_name=VALUES(country_name),
                                     city=VALUES(city),
                                     cached_at=VALUES(cached_at)'
        );
        $stmt->execute([
            ':ip' => $bin,
            ':cc' => $geo['country_code'],
            ':cn' => $geo['country_name'],
            ':ci' => $geo['city'],
        ]);
    } catch (\Throwable $e) { /* swallow */ }

    return $memo[$ip] = $geo;
}

/**
 * Render an emoji flag for a 2-letter country code. Pure ASCII fallback
 * for unknown codes.
 */
function country_flag(?string $cc): string {
    if (!$cc || strlen($cc) !== 2) return '🌐';
    $cc = strtoupper($cc);
    $a = ord($cc[0]); $b = ord($cc[1]);
    if ($a < 65 || $a > 90 || $b < 65 || $b > 90) return '🌐';
    return mb_chr(0x1F1E6 + ($a - 65), 'UTF-8') . mb_chr(0x1F1E6 + ($b - 65), 'UTF-8');
}
