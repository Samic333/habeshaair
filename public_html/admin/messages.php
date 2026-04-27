<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$STATUSES = ['New','Read','Replied','Archived'];
$f_status = (string)($_GET['status'] ?? '');
$f_q      = trim((string)($_GET['q'] ?? ''));

$where = []; $params = [];
if ($f_status !== '' && in_array($f_status, $STATUSES, true)) { $where[] = 'status = ?'; $params[] = $f_status; }
if ($f_q !== '') {
    $where[] = '(full_name LIKE ? OR email LIKE ? OR subject LIKE ?)';
    $like = '%' . $f_q . '%';
    array_push($params, $like, $like, $like);
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = db()->prepare("SELECT id, full_name, email, subject, status, created_at FROM contact_messages $whereSql ORDER BY created_at DESC LIMIT 100");
$stmt->execute($params);
$rows = $stmt->fetchAll();

$adminTitle = 'Messages — HabeshaAir admin';
$activeNav  = 'messages';
include __DIR__ . '/includes/admin-header.php';
?>
<div class="container">
  <h1 style="margin-top:2rem">Contact messages</h1>

  <form method="get" class="filter-bar">
    <input type="search" name="q" value="<?= e($f_q) ?>" placeholder="Search name / email / subject">
    <select name="status">
      <option value="">All</option>
      <?php foreach ($STATUSES as $s): ?><option value="<?= e($s) ?>"<?= $f_status === $s ? ' selected':'' ?>><?= e($s) ?></option><?php endforeach; ?>
    </select>
    <button class="btn btn-navy" type="submit">Filter</button>
    <a href="/admin/messages.php" class="btn btn-outline">Clear</a>
  </form>

  <div class="table-wrap">
    <table class="admin-table">
      <thead><tr><th>From</th><th>Subject</th><th>Status</th><th>When</th></tr></thead>
      <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="4" style="text-align:center; padding:2rem; color:var(--gray-600)">No messages.</td></tr>
        <?php else: foreach ($rows as $r): ?>
          <tr>
            <td><a href="/admin/message-view.php?id=<?= (int)$r['id'] ?>"><?= e($r['full_name']) ?></a><br><small><?= e($r['email']) ?></small></td>
            <td><?= e($r['subject']) ?></td>
            <td><span class="status status-<?= e(strtolower($r['status'])) ?>"><?= e($r['status']) ?></span></td>
            <td><?= e($r['created_at']) ?></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php include __DIR__ . '/includes/admin-footer.php'; ?>
