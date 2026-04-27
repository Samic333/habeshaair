<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

// KPIs
$monthStart = date('Y-m-01 00:00:00');
$row = db()->prepare(
    'SELECT
       COALESCE(SUM(status = "New"), 0)                                    AS new_count,
       COALESCE(SUM(is_urgent = 1 AND status NOT IN ("Closed","Cancelled")), 0) AS urgent_open,
       COALESCE(SUM(created_at >= ?), 0)                                   AS month_count,
       COUNT(*)                                                            AS total
     FROM charter_requests'
);
$row->execute([$monthStart]);
$k = $row->fetch();

$mq = db()->query('SELECT COUNT(*) AS c FROM contact_messages WHERE status = "New"')->fetch();
$messagesNew = (int)($mq['c'] ?? 0);

$recent = db()->query(
    'SELECT id, reference_code, service_type, origin, destination, urgency_level, status, full_name, is_urgent, created_at
     FROM charter_requests ORDER BY created_at DESC LIMIT 10'
)->fetchAll();

$adminTitle = 'Dashboard — HabeshaAir admin';
$activeNav  = 'dashboard';
include __DIR__ . '/includes/admin-header.php';
?>

<div class="container">
  <h1 style="margin-top:2rem">Dashboard</h1>

  <div class="kpi-grid">
    <div class="kpi"><span class="kpi-label">New requests</span><span class="kpi-num"><?= (int)$k['new_count'] ?></span></div>
    <div class="kpi kpi-warn"><span class="kpi-label">Urgent open</span><span class="kpi-num"><?= (int)$k['urgent_open'] ?></span></div>
    <div class="kpi"><span class="kpi-label">This month</span><span class="kpi-num"><?= (int)$k['month_count'] ?></span></div>
    <div class="kpi"><span class="kpi-label">All time</span><span class="kpi-num"><?= (int)$k['total'] ?></span></div>
    <div class="kpi"><span class="kpi-label">New messages</span><span class="kpi-num"><?= $messagesNew ?></span></div>
  </div>

  <h2 style="margin-top:2.5rem">Recent requests</h2>
  <?php if (!$recent): ?>
    <p style="color:var(--gray-600)">No requests yet. They will appear here as soon as a charter request is submitted.</p>
  <?php else: ?>
  <div class="table-wrap">
    <table class="admin-table">
      <thead>
        <tr><th>Ref</th><th>Service</th><th>Route</th><th>Urgency</th><th>Status</th><th>Name</th><th>When</th></tr>
      </thead>
      <tbody>
        <?php foreach ($recent as $r): ?>
          <tr>
            <td><a href="/admin/request-view.php?id=<?= (int)$r['id'] ?>"><?= e($r['reference_code']) ?></a><?= $r['is_urgent'] ? ' <span class="badge badge-warn">Urgent</span>' : '' ?></td>
            <td><?= e($r['service_type']) ?></td>
            <td><?= e($r['origin']) ?> → <?= e($r['destination']) ?></td>
            <td><?= e($r['urgency_level']) ?></td>
            <td><span class="status status-<?= e(strtolower($r['status'])) ?>"><?= e($r['status']) ?></span></td>
            <td><?= e($r['full_name']) ?></td>
            <td><?= e($r['created_at']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <p style="margin-top:1rem"><a href="/admin/requests.php">All requests →</a></p>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>
