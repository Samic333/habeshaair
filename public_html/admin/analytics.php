<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/analytics.php';
require_admin();

// Date range filter — default last 30 days
$range = (string)($_GET['range'] ?? '30d');
$validRanges = ['24h','7d','30d','90d','all'];
if (!in_array($range, $validRanges, true)) $range = '30d';

$rangeSql = match ($range) {
    '24h'  => "created_at >= NOW() - INTERVAL 1 DAY",
    '7d'   => "created_at >= NOW() - INTERVAL 7 DAY",
    '30d'  => "created_at >= NOW() - INTERVAL 30 DAY",
    '90d'  => "created_at >= NOW() - INTERVAL 90 DAY",
    'all'  => "1=1",
};

// KPIs
$kpis = db()->query("
    SELECT
      SUM(created_at >= NOW() - INTERVAL 1 DAY)  AS visits_24h,
      SUM(created_at >= NOW() - INTERVAL 7 DAY)  AS visits_7d,
      SUM(created_at >= NOW() - INTERVAL 30 DAY) AS visits_30d,
      COUNT(*)                                   AS visits_total
    FROM page_views
")->fetch() ?: [];

$uniq = db()->query("
    SELECT
      COUNT(DISTINCT visitor_hash) AS unique_today
    FROM page_views
    WHERE DATE(created_at) = CURDATE() AND visitor_hash IS NOT NULL
")->fetch() ?: [];

$uniq7 = db()->query("
    SELECT COUNT(DISTINCT visitor_hash) AS u
    FROM page_views
    WHERE created_at >= NOW() - INTERVAL 7 DAY AND visitor_hash IS NOT NULL
")->fetch() ?: [];

// Top countries
$countries = db()->query("
    SELECT country_code, country_name, COUNT(*) AS visits, COUNT(DISTINCT visitor_hash) AS visitors
    FROM page_views
    WHERE $rangeSql AND country_code IS NOT NULL
    GROUP BY country_code, country_name
    ORDER BY visits DESC
    LIMIT 10
")->fetchAll();

// Top referrers
$referrers = db()->query("
    SELECT
      CASE WHEN referrer IS NULL OR referrer = '' THEN '(direct)'
           ELSE SUBSTRING_INDEX(SUBSTRING_INDEX(referrer, '/', 3), '://', -1) END AS source,
      COUNT(*) AS visits
    FROM page_views
    WHERE $rangeSql
    GROUP BY source
    ORDER BY visits DESC
    LIMIT 10
")->fetchAll();

// Top pages
$pages = db()->query("
    SELECT path, COUNT(*) AS visits, COUNT(DISTINCT visitor_hash) AS visitors
    FROM page_views
    WHERE $rangeSql
    GROUP BY path
    ORDER BY visits DESC
    LIMIT 10
")->fetchAll();

// Device breakdown
$devices = db()->query("
    SELECT device_type, COUNT(*) AS visits
    FROM page_views
    WHERE $rangeSql
    GROUP BY device_type
    ORDER BY visits DESC
")->fetchAll();

$adminTitle = 'Analytics — HabeshAir admin';
$activeNav  = 'analytics';
include __DIR__ . '/includes/admin-header.php';
?>

<div class="container">
  <div style="display:flex;justify-content:space-between;align-items:baseline;flex-wrap:wrap;gap:1rem;margin-top:2rem">
    <h1 style="margin:0">Analytics</h1>
    <form method="get" class="filter-bar" style="margin:0">
      <select name="range" onchange="this.form.submit()">
        <option value="24h"<?= $range==='24h'?' selected':'' ?>>Last 24 hours</option>
        <option value="7d"<?= $range==='7d'?' selected':'' ?>>Last 7 days</option>
        <option value="30d"<?= $range==='30d'?' selected':'' ?>>Last 30 days</option>
        <option value="90d"<?= $range==='90d'?' selected':'' ?>>Last 90 days</option>
        <option value="all"<?= $range==='all'?' selected':'' ?>>All time</option>
      </select>
    </form>
  </div>

  <div class="kpi-grid" style="margin-top:1.5rem">
    <div class="kpi"><span class="kpi-label">Visits (24h)</span><span class="kpi-num"><?= (int)($kpis['visits_24h'] ?? 0) ?></span></div>
    <div class="kpi"><span class="kpi-label">Visits (7d)</span><span class="kpi-num"><?= (int)($kpis['visits_7d'] ?? 0) ?></span></div>
    <div class="kpi"><span class="kpi-label">Visits (30d)</span><span class="kpi-num"><?= (int)($kpis['visits_30d'] ?? 0) ?></span></div>
    <div class="kpi"><span class="kpi-label">Unique today</span><span class="kpi-num"><?= (int)($uniq['unique_today'] ?? 0) ?></span></div>
    <div class="kpi"><span class="kpi-label">Unique 7d</span><span class="kpi-num"><?= (int)($uniq7['u'] ?? 0) ?></span></div>
  </div>

  <h2 style="margin-top:2.5rem">Daily traffic (last 30 days)</h2>
  <div class="form-card" style="padding:1.25rem">
    <canvas id="trafficChart" height="80"></canvas>
  </div>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;margin-top:2rem">
    <div>
      <h2>Top countries</h2>
      <?php if (!$countries): ?>
        <p style="color:var(--gray-600)">No country data yet. Visitors will appear here once geo-resolved.</p>
      <?php else: ?>
        <div class="table-wrap">
          <table class="admin-table">
            <thead><tr><th>Country</th><th style="text-align:right">Visits</th><th style="text-align:right">Visitors</th></tr></thead>
            <tbody>
              <?php foreach ($countries as $c): ?>
                <tr>
                  <td><span style="font-size:1.25em"><?= country_flag($c['country_code']) ?></span> <?= e($c['country_name'] ?: $c['country_code']) ?></td>
                  <td style="text-align:right"><?= (int)$c['visits'] ?></td>
                  <td style="text-align:right"><?= (int)$c['visitors'] ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <div>
      <h2>Top referrers</h2>
      <?php if (!$referrers): ?>
        <p style="color:var(--gray-600)">No data yet.</p>
      <?php else: ?>
        <div class="table-wrap">
          <table class="admin-table">
            <thead><tr><th>Source</th><th style="text-align:right">Visits</th></tr></thead>
            <tbody>
              <?php foreach ($referrers as $r): ?>
                <tr><td><?= e($r['source']) ?></td><td style="text-align:right"><?= (int)$r['visits'] ?></td></tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div style="display:grid;grid-template-columns:2fr 1fr;gap:1.5rem;margin-top:2rem">
    <div>
      <h2>Top pages</h2>
      <?php if (!$pages): ?>
        <p style="color:var(--gray-600)">No data yet.</p>
      <?php else: ?>
        <div class="table-wrap">
          <table class="admin-table">
            <thead><tr><th>Path</th><th style="text-align:right">Visits</th><th style="text-align:right">Visitors</th></tr></thead>
            <tbody>
              <?php foreach ($pages as $p): ?>
                <tr>
                  <td><a href="<?= e($p['path']) ?>" target="_blank" rel="noopener"><?= e($p['path']) ?></a></td>
                  <td style="text-align:right"><?= (int)$p['visits'] ?></td>
                  <td style="text-align:right"><?= (int)$p['visitors'] ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>

    <div>
      <h2>Device</h2>
      <?php if (!$devices): ?>
        <p style="color:var(--gray-600)">No data yet.</p>
      <?php else: ?>
        <div class="table-wrap">
          <table class="admin-table">
            <thead><tr><th>Type</th><th style="text-align:right">Visits</th></tr></thead>
            <tbody>
              <?php foreach ($devices as $d): ?>
                <tr><td><?= e(ucfirst($d['device_type'])) ?></td><td style="text-align:right"><?= (int)$d['visits'] ?></td></tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
  fetch('/admin/api/analytics-series.php?days=30')
    .then(r => r.json())
    .then(d => {
      const ctx = document.getElementById('trafficChart').getContext('2d');
      new Chart(ctx, {
        type: 'line',
        data: {
          labels: d.labels,
          datasets: [{
            label: 'Visits',
            data: d.visits,
            borderColor: '#d4a447',
            backgroundColor: 'rgba(212,164,71,0.15)',
            fill: true,
            tension: 0.25,
            pointRadius: 3,
          }, {
            label: 'Unique visitors',
            data: d.unique,
            borderColor: '#0f2540',
            backgroundColor: 'rgba(15,37,64,0.08)',
            fill: false,
            tension: 0.25,
            pointRadius: 3,
          }]
        },
        options: {
          responsive: true,
          plugins: { legend: { position: 'top' } },
          scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
        }
      });
    })
    .catch(e => console.error('analytics chart load failed', e));
</script>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>
