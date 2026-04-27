<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) redirect('/admin/requests.php');

$STATUSES = ['New','Reviewing','Quoted','Waiting','Confirmed','Cancelled','Closed'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $newStatus = (string)($_POST['status'] ?? 'New');
    if (!in_array($newStatus, $STATUSES, true)) $newStatus = 'New';
    $isUrgent = !empty($_POST['is_urgent']) ? 1 : 0;
    $notes = trim((string)($_POST['internal_notes'] ?? ''));
    if (mb_strlen($notes) > 5000) $notes = mb_substr($notes, 0, 5000);

    $u = db()->prepare('UPDATE charter_requests SET status = ?, is_urgent = ?, internal_notes = ? WHERE id = ?');
    $u->execute([$newStatus, $isUrgent, $notes, $id]);

    flash_set('saved', 'Request updated.');
    redirect('/admin/request-view.php?id=' . $id);
}

$stmt = db()->prepare('SELECT * FROM charter_requests WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$req = $stmt->fetch();
if (!$req) redirect('/admin/requests.php');

$saved = flash_get('saved');

$adminTitle = 'Request ' . $req['reference_code'] . ' — HabeshaAir admin';
$activeNav  = 'requests';
include __DIR__ . '/includes/admin-header.php';

$wa = whatsapp_link("Hello {$req['full_name']}, regarding your charter request {$req['reference_code']}.");
?>

<div class="container">
  <p style="margin-top:1.5rem"><a href="/admin/requests.php">← All requests</a></p>

  <div class="grid grid-2" style="gap:2rem; margin-top:1rem; align-items:start">
    <div>
      <h1 style="margin-bottom:.25em"><?= e($req['reference_code']) ?>
        <?= $req['is_urgent'] ? '<span class="badge badge-warn">Urgent</span>' : '' ?>
      </h1>
      <p style="color:var(--gray-600)">Submitted <?= e($req['created_at']) ?></p>

      <dl class="kv">
        <dt>Service</dt><dd><?= e($req['service_type']) ?></dd>
        <dt>Trip</dt><dd><?= e($req['trip_type']) ?></dd>
        <dt>Route</dt><dd><?= e($req['origin']) ?> → <?= e($req['destination']) ?></dd>
        <dt>Date</dt><dd><?= e($req['travel_date'] ?: '—') ?><?= $req['return_date'] ? ' / return ' . e($req['return_date']) : '' ?></dd>
        <dt>Time pref</dt><dd><?= e($req['time_pref']) ?></dd>
        <?php if ($req['passengers']): ?><dt>Passengers</dt><dd><?= (int)$req['passengers'] ?></dd><?php endif; ?>
        <?php if ($req['approx_weight_kg']): ?><dt>Weight</dt><dd><?= (int)$req['approx_weight_kg'] ?> kg</dd><?php endif; ?>
        <?php if ($req['cargo_type']): ?><dt>Cargo type</dt><dd><?= e($req['cargo_type']) ?></dd><?php endif; ?>
        <dt>Urgency</dt><dd><?= e($req['urgency_level']) ?></dd>
        <?php if ($req['budget_range']): ?><dt>Budget</dt><dd><?= e($req['budget_range']) ?></dd><?php endif; ?>
        <?php if ($req['special_requirements']): ?><dt>Notes</dt><dd><?= nl2br(e($req['special_requirements'])) ?></dd><?php endif; ?>
      </dl>

      <h3 style="margin-top:2rem">Contact</h3>
      <dl class="kv">
        <dt>Name</dt><dd><?= e($req['full_name']) ?></dd>
        <?php if ($req['company']): ?><dt>Company</dt><dd><?= e($req['company']) ?></dd><?php endif; ?>
        <dt>Email</dt><dd><a href="mailto:<?= e($req['email']) ?>?subject=<?= rawurlencode('Charter request ' . $req['reference_code']) ?>"><?= e($req['email']) ?></a></dd>
        <dt>Phone</dt><dd><?= e($req['phone']) ?> <a href="<?= e($wa) ?>" target="_blank" rel="noopener" style="margin-left:.5em">WhatsApp ↗</a></dd>
        <dt>Prefers</dt><dd><?= e($req['contact_method']) ?></dd>
        <dt>Consent</dt><dd><?= $req['consent'] ? 'Yes' : 'No' ?></dd>
      </dl>
    </div>

    <div>
      <h3>Update status</h3>
      <?php if ($saved): ?><div class="alert alert-success"><?= e($saved) ?></div><?php endif; ?>

      <form method="post" class="form-card">
        <?= csrf_field() ?>
        <div class="form-grid">
          <div class="form-group">
            <label for="status">Status</label>
            <select id="status" name="status">
              <?php foreach ($STATUSES as $s): ?>
                <option value="<?= e($s) ?>"<?= $req['status'] === $s ? ' selected':'' ?>><?= e($s) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="checkbox-row">
              <input type="checkbox" name="is_urgent" value="1"<?= $req['is_urgent'] ? ' checked' : '' ?>>
              <span>Flag as urgent</span>
            </label>
          </div>
          <div class="form-group">
            <label for="internal_notes">Internal notes</label>
            <textarea id="internal_notes" name="internal_notes" maxlength="5000"><?= e($req['internal_notes']) ?></textarea>
            <span class="hint">Visible to admins only.</span>
          </div>
          <button class="btn btn-navy" type="submit">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>
