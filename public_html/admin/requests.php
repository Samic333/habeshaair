<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$STATUSES = ['New','Reviewing','Quoted','Waiting','Confirmed','Cancelled','Closed'];
$SERVICES = ['VIP','Cargo','Humanitarian','Emergency-Medevac','Group-Event'];
$URGENCY  = ['Flexible','72h','24h','Emergency'];

// Filters
$f_status  = (string)($_GET['status']  ?? '');
$f_service = (string)($_GET['service'] ?? '');
$f_urg     = (string)($_GET['urgency'] ?? '');
$f_q       = trim((string)($_GET['q']  ?? ''));
$page_n    = max(1, (int)($_GET['page'] ?? 1));
$per       = 25;

$where  = [];
$params = [];
if ($f_status  !== '' && in_array($f_status,  $STATUSES, true)) { $where[] = 'status = ?';        $params[] = $f_status; }
if ($f_service !== '' && in_array($f_service, $SERVICES, true)) { $where[] = 'service_type = ?';  $params[] = $f_service; }
if ($f_urg     !== '' && in_array($f_urg,     $URGENCY,  true)) { $where[] = 'urgency_level = ?'; $params[] = $f_urg; }
if ($f_q !== '') {
    $where[] = '(full_name LIKE ? OR email LIKE ? OR reference_code LIKE ? OR origin LIKE ? OR destination LIKE ?)';
    $like = '%' . $f_q . '%';
    array_push($params, $like, $like, $like, $like, $like);
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total = (int)db()->prepare("SELECT COUNT(*) FROM charter_requests $whereSql")
    ->execute($params) ?: 0;
$cstmt = db()->prepare("SELECT COUNT(*) AS c FROM charter_requests $whereSql");
$cstmt->execute($params);
$total = (int)$cstmt->fetchColumn();

$pages = max(1, (int)ceil($total / $per));
$page_n = min($page_n, $pages);
$offset = ($page_n - 1) * $per;

$stmt = db()->prepare(
    "SELECT id, reference_code, service_type, origin, destination, urgency_level,
            status, full_name, email, is_urgent, created_at
     FROM charter_requests $whereSql
     ORDER BY is_urgent DESC, created_at DESC
     LIMIT $per OFFSET $offset"
);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$adminTitle = 'Charter requests — HabeshAir admin';
$activeNav  = 'requests';
include __DIR__ . '/includes/admin-header.php';

function qstr(array $overrides = []): string {
    $base = $_GET; foreach ($overrides as $k=>$v) $base[$k] = $v;
    foreach ($base as $k=>$v) if ($v === '' || $v === null) unset($base[$k]);
    return http_build_query($base);
}
?>

<div class="container">
  <h1 style="margin-top:2rem">Charter requests</h1>

  <form method="get" class="filter-bar">
    <input type="search" name="q" value="<?= e($f_q) ?>" placeholder="Search name / email / reference / route">
    <select name="status">
      <option value="">All statuses</option>
      <?php foreach ($STATUSES as $s): ?><option value="<?= e($s) ?>"<?= $f_status === $s ? ' selected':'' ?>><?= e($s) ?></option><?php endforeach; ?>
    </select>
    <select name="service">
      <option value="">All services</option>
      <?php foreach ($SERVICES as $s): ?><option value="<?= e($s) ?>"<?= $f_service === $s ? ' selected':'' ?>><?= e($s) ?></option><?php endforeach; ?>
    </select>
    <select name="urgency">
      <option value="">Any urgency</option>
      <?php foreach ($URGENCY as $s): ?><option value="<?= e($s) ?>"<?= $f_urg === $s ? ' selected':'' ?>><?= e($s) ?></option><?php endforeach; ?>
    </select>
    <button class="btn btn-navy" type="submit">Filter</button>
    <a href="/admin/requests.php" class="btn btn-outline">Clear</a>
  </form>

  <p style="color:var(--gray-600); margin:1rem 0">
    <?= $total ?> result<?= $total === 1 ? '' : 's' ?>
    <?= $pages > 1 ? "· page $page_n of $pages" : '' ?>
  </p>

  <div class="table-wrap">
    <table class="admin-table">
      <thead>
        <tr><th>Ref</th><th>Service</th><th>Route</th><th>Urgency</th><th>Status</th><th>Contact</th><th>When</th></tr>
      </thead>
      <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="7" style="text-align:center; padding:2rem; color:var(--gray-600)">No requests match these filters.</td></tr>
      <?php else: foreach ($rows as $r): ?>
        <tr>
          <td><a href="/admin/request-view.php?id=<?= (int)$r['id'] ?>"><?= e($r['reference_code']) ?></a><?= $r['is_urgent'] ? ' <span class="badge badge-warn">Urgent</span>' : '' ?></td>
          <td><?= e($r['service_type']) ?></td>
          <td><?= e($r['origin']) ?> → <?= e($r['destination']) ?></td>
          <td><?= e($r['urgency_level']) ?></td>
          <td><span class="status status-<?= e(strtolower($r['status'])) ?>"><?= e($r['status']) ?></span></td>
          <td><?= e($r['full_name']) ?><br><small><?= e($r['email']) ?></small></td>
          <td><?= e($r['created_at']) ?></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

  <?php if ($pages > 1): ?>
    <nav class="pager">
      <?php if ($page_n > 1): ?><a class="btn btn-outline" href="?<?= e(qstr(['page'=>$page_n-1])) ?>">← Prev</a><?php endif; ?>
      <span>Page <?= $page_n ?> / <?= $pages ?></span>
      <?php if ($page_n < $pages): ?><a class="btn btn-outline" href="?<?= e(qstr(['page'=>$page_n+1])) ?>">Next →</a><?php endif; ?>
    </nav>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>
