<?php
/**
 * analytics-series.php — JSON for the daily-traffic chart on /admin/analytics.php
 *
 * Returns: { labels: [...YYYY-MM-DD...], visits: [...], unique: [...] }
 *
 * Days backfilled with zeros so the chart never has gaps.
 */
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/auth.php';
require_admin();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$days = (int)($_GET['days'] ?? 30);
if ($days < 1)  $days = 1;
if ($days > 365) $days = 365;

$rows = db()->prepare(
    "SELECT DATE(created_at) AS d,
            COUNT(*)                                AS visits,
            COUNT(DISTINCT visitor_hash)            AS uniq
     FROM page_views
     WHERE created_at >= (CURDATE() - INTERVAL :n DAY)
     GROUP BY d"
);
$rows->execute([':n' => $days - 1]);
$by = [];
foreach ($rows->fetchAll() as $r) {
    $by[(string)$r['d']] = ['v' => (int)$r['visits'], 'u' => (int)$r['uniq']];
}

$labels = $visits = $unique = [];
for ($i = $days - 1; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} days"));
    $labels[]  = $d;
    $visits[]  = $by[$d]['v'] ?? 0;
    $unique[]  = $by[$d]['u'] ?? 0;
}

echo json_encode(['labels' => $labels, 'visits' => $visits, 'unique' => $unique]);
