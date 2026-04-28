<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

// Filters
$f_q       = trim((string)($_GET['q'] ?? ''));
$f_country = (string)($_GET['country'] ?? '');
$f_service = (string)($_GET['service'] ?? '');
$f_active  = (string)($_GET['active'] ?? '');
$f_new     = (string)($_GET['new'] ?? '');
$page_n    = max(1, (int)($_GET['page'] ?? 1));
$per       = 25;

$where  = [];
$params = [];

if ($f_q !== '') {
    $where[] = '(name LIKE ? OR iata_code LIKE ? OR icao_code LIKE ? OR base_country LIKE ? OR contact_email LIKE ?)';
    $like = '%' . $f_q . '%';
    array_push($params, $like, $like, $like, $like, $like);
}
if ($f_country !== '') { $where[] = 'base_country = ?'; $params[] = $f_country; }
if ($f_service !== '') { $where[] = 'JSON_CONTAINS(service_types, ?)'; $params[] = json_encode($f_service); }
if ($f_active !== '')  { $where[] = 'active = ?'; $params[] = $f_active === '1' ? 1 : 0; }
if ($f_new === '1')    { $where[] = 'is_new = 1'; }

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$cstmt = db()->prepare("SELECT COUNT(*) FROM airlines $whereSql");
$cstmt->execute($params);
$total = (int)$cstmt->fetchColumn();
$pages = max(1, (int)ceil($total / $per));
$page_n = min($page_n, $pages);
$offset = ($page_n - 1) * $per;

$stmt = db()->prepare(
    "SELECT id, name, iata_code, icao_code, base_country, contact_email,
            fleet_types, regions_served, service_types, capacity_pax_max,
            capacity_kg_max, rating, active, is_new, synced_at
     FROM airlines $whereSql
     ORDER BY is_new DESC, active DESC, rating DESC, name ASC
     LIMIT $per OFFSET $offset"
);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Distinct countries for filter dropdown
$countries = db()->query('SELECT DISTINCT base_country FROM airlines WHERE base_country IS NOT NULL AND base_country != "" ORDER BY base_country')->fetchAll(PDO::FETCH_COLUMN);

$SERVICE_TYPES = ['VIP','Cargo','Humanitarian','Emergency-Medevac','Group-Event'];

// Last sync timestamp
$lastSync = db()->query('SELECT MAX(synced_at) AS s FROM airlines')->fetchColumn();

$adminTitle = 'Airlines directory — HabeshAir admin';
$activeNav  = 'airlines';
include __DIR__ . '/includes/admin-header.php';

function qstr_air(array $overrides = []): string {
    $base = $_GET; foreach ($overrides as $k=>$v) $base[$k] = $v;
    foreach ($base as $k=>$v) if ($v === '' || $v === null) unset($base[$k]);
    return http_build_query($base);
}
?>

<div class="container">
  <div style="display:flex;justify-content:space-between;align-items:baseline;flex-wrap:wrap;gap:1rem;margin-top:2rem">
    <h1 style="margin:0">Airlines directory</h1>
    <form method="post" action="/admin/airlines-sync.php" style="margin:0">
      <?= csrf_field() ?>
      <button class="btn btn-navy" type="submit">⟳ Sync from Google Sheet now</button>
    </form>
  </div>

  <p style="color:var(--gray-600); margin:.5rem 0 1.5rem">
    <?= $total ?> airline<?= $total === 1 ? '' : 's' ?>
    <?php if ($lastSync): ?> · last sync: <?= e($lastSync) ?> UTC<?php endif; ?>
  </p>

  <?php if ($msg = flash_get('airlines_msg')): ?>
    <div class="alert alert-success"><?= e($msg) ?></div>
  <?php endif; ?>
  <?php if ($err = flash_get('airlines_err')): ?>
    <div class="alert alert-error"><?= e($err) ?></div>
  <?php endif; ?>

  <form method="get" class="filter-bar">
    <input type="search" name="q" value="<?= e($f_q) ?>" placeholder="Search name / IATA / ICAO / country / email">
    <select name="country">
      <option value="">All countries</option>
      <?php foreach ($countries as $c): ?>
        <option value="<?= e($c) ?>"<?= $f_country === $c ? ' selected':'' ?>><?= e($c) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="service">
      <option value="">Any service</option>
      <?php foreach ($SERVICE_TYPES as $s): ?>
        <option value="<?= e($s) ?>"<?= $f_service === $s ? ' selected':'' ?>><?= e($s) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="active">
      <option value="">Active + inactive</option>
      <option value="1"<?= $f_active === '1' ? ' selected':'' ?>>Active only</option>
      <option value="0"<?= $f_active === '0' ? ' selected':'' ?>>Inactive only</option>
    </select>
    <label style="display:inline-flex;align-items:center;gap:.4rem">
      <input type="checkbox" name="new" value="1"<?= $f_new === '1' ? ' checked':'' ?>> New only
    </label>
    <button class="btn btn-navy" type="submit">Filter</button>
    <a href="/admin/airlines.php" class="btn btn-outline">Clear</a>
  </form>

  <div class="table-wrap" style="margin-top:1rem">
    <table class="admin-table">
      <thead>
        <tr>
          <th>Airline</th>
          <th>Codes</th>
          <th>Country</th>
          <th>Services</th>
          <th>Capacity</th>
          <th>Rating</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="7" style="text-align:center; padding:2rem; color:var(--gray-600)">
          No airlines match these filters.<br>
          <small>Configure the Google Sheet CSV URL in <code>config.php</code> then click "Sync from Google Sheet now".</small>
        </td></tr>
      <?php else: foreach ($rows as $r):
        $services = $r['service_types'] ? json_decode($r['service_types'], true) : [];
        $fleet    = $r['fleet_types']   ? json_decode($r['fleet_types'], true)   : [];
      ?>
        <tr>
          <td>
            <a href="/admin/airline-view.php?id=<?= (int)$r['id'] ?>"><strong><?= e($r['name']) ?></strong></a>
            <?php if ((int)$r['is_new'] === 1): ?> <span class="badge badge-warn">NEW</span><?php endif; ?>
            <?php if ($r['contact_email']): ?><br><small><?= e($r['contact_email']) ?></small><?php endif; ?>
          </td>
          <td>
            <?php if ($r['iata_code']): ?><code><?= e($r['iata_code']) ?></code><?php endif; ?>
            <?php if ($r['icao_code']): ?> <code><?= e($r['icao_code']) ?></code><?php endif; ?>
          </td>
          <td><?= e($r['base_country']) ?: '—' ?></td>
          <td><?= $services ? e(implode(', ', $services)) : '—' ?></td>
          <td>
            <?php if ($r['capacity_pax_max']): ?><?= (int)$r['capacity_pax_max'] ?> pax<?php endif; ?>
            <?php if ($r['capacity_pax_max'] && $r['capacity_kg_max']): ?> · <?php endif; ?>
            <?php if ($r['capacity_kg_max']): ?><?= number_format((int)$r['capacity_kg_max']) ?> kg<?php endif; ?>
            <?php if (!$r['capacity_pax_max'] && !$r['capacity_kg_max']): ?>—<?php endif; ?>
          </td>
          <td><?= str_repeat('★', (int)$r['rating']) . str_repeat('☆', 5 - (int)$r['rating']) ?></td>
          <td>
            <?php if ((int)$r['active'] === 1): ?>
              <span class="status status-confirmed">Active</span>
            <?php else: ?>
              <span class="status status-archived">Inactive</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

  <?php if ($pages > 1): ?>
    <nav class="pager">
      <?php if ($page_n > 1): ?><a class="btn btn-outline" href="?<?= e(qstr_air(['page'=>$page_n-1])) ?>">← Prev</a><?php endif; ?>
      <span>Page <?= $page_n ?> / <?= $pages ?></span>
      <?php if ($page_n < $pages): ?><a class="btn btn-outline" href="?<?= e(qstr_air(['page'=>$page_n+1])) ?>">Next →</a><?php endif; ?>
    </nav>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>
