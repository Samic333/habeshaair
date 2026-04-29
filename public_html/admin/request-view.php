<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/airline-matcher.php';
require_admin();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) redirect('/admin/requests.php');

$STATUSES = ['New','Reviewing','RFQ-Sent','RFQ-Received','Quoted','Waiting','Confirmed','Flown','Cancelled','Closed'];

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

// Match airlines (top 10)
$matches = match_airlines_for_request($req, 10);

// Existing RFQ dispatches for this request
$dispStmt = db()->prepare(
    'SELECT d.id, d.reply_token, d.subject, d.status, d.sent_at, d.reply_at,
            d.reply_snippet, d.quoted_amount, d.quoted_currency,
            a.name AS airline_name, a.iata_code, a.icao_code, a.contact_email
     FROM rfq_dispatches d
     JOIN airlines a ON a.id = d.airline_id
     WHERE d.request_id = ?
     ORDER BY d.sent_at DESC'
);
$dispStmt->execute([$id]);
$dispatches = $dispStmt->fetchAll();

$rfqMsg = flash_get('rfq_msg');
$rfqErr = flash_get('rfq_err');

$adminTitle = 'Request ' . $req['reference_code'] . ' — HabeshAir admin';
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

  <?php if ($rfqMsg): ?><div class="alert alert-success" style="margin-top:2rem"><?= e($rfqMsg) ?></div><?php endif; ?>
  <?php if ($rfqErr): ?><div class="alert alert-error" style="margin-top:2rem"><?= e($rfqErr) ?></div><?php endif; ?>

  <h2 style="margin-top:3rem">Source quotes</h2>
  <p style="color:var(--gray-600); margin-bottom:1rem">
    Top airlines matched against this request's service type, route, and capacity. Pick the operators to send an RFQ to.
  </p>

  <?php if (!$matches): ?>
    <div class="alert alert-info">
      No matching airlines yet. Add operators to the
      <a href="/admin/airlines.php">Airlines directory</a> (or fill the
      Google Sheet and click "Sync now").
    </div>
  <?php else: ?>
    <form method="get" action="/admin/rfq-compose.php" class="form-card" style="padding:1rem">
      <input type="hidden" name="request_id" value="<?= (int)$req['id'] ?>">
      <div class="table-wrap">
        <table class="admin-table">
          <thead>
            <tr>
              <th style="width:40px"><input type="checkbox" id="select-all-airlines"></th>
              <th>Airline</th>
              <th>Codes</th>
              <th>Country</th>
              <th>Capacity</th>
              <th>Score</th>
              <th>Why</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($matches as $m):
            $services = $m['service_types'] ? json_decode($m['service_types'], true) : [];
            $regions  = $m['regions_served'] ? json_decode($m['regions_served'], true) : [];
          ?>
            <tr>
              <td><input type="checkbox" name="airlines[]" value="<?= (int)$m['id'] ?>" class="airline-check"></td>
              <td>
                <a href="/admin/airline-view.php?id=<?= (int)$m['id'] ?>"><strong><?= e($m['name']) ?></strong></a>
                <?php if ($m['contact_email']): ?><br><small><?= e($m['contact_email']) ?></small><?php endif; ?>
              </td>
              <td>
                <?php if ($m['iata_code']): ?><code><?= e($m['iata_code']) ?></code><?php endif; ?>
                <?php if ($m['icao_code']): ?> <code><?= e($m['icao_code']) ?></code><?php endif; ?>
              </td>
              <td><?= e($m['base_country']) ?: '—' ?></td>
              <td>
                <?php if ($m['capacity_pax_max']): ?><?= (int)$m['capacity_pax_max'] ?> pax<?php endif; ?>
                <?php if ($m['capacity_pax_max'] && $m['capacity_kg_max']): ?> · <?php endif; ?>
                <?php if ($m['capacity_kg_max']): ?><?= number_format((int)$m['capacity_kg_max']) ?> kg<?php endif; ?>
                <?php if (!$m['capacity_pax_max'] && !$m['capacity_kg_max']): ?>—<?php endif; ?>
              </td>
              <td><strong><?= (int)$m['score'] ?></strong></td>
              <td><small><?= $m['why'] ? e(implode(', ', $m['why'])) : '—' ?></small></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="form-actions" style="margin-top:1rem">
        <button class="btn btn-navy" type="submit">Compose RFQ to selected →</button>
      </div>
    </form>
    <script>
      document.getElementById('select-all-airlines').addEventListener('change', function(e){
        document.querySelectorAll('.airline-check').forEach(c => c.checked = e.target.checked);
      });
    </script>
  <?php endif; ?>

  <?php if ($dispatches): ?>
    <h2 style="margin-top:3rem">RFQs sent</h2>
    <div class="table-wrap">
      <table class="admin-table">
        <thead>
          <tr><th>Airline</th><th>Sent</th><th>Status</th><th>Reply / quote</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($dispatches as $d): ?>
          <tr>
            <td>
              <strong><?= e($d['airline_name']) ?></strong>
              <?php if ($d['contact_email']): ?><br><small><?= e($d['contact_email']) ?></small><?php endif; ?>
            </td>
            <td><?= e($d['sent_at']) ?></td>
            <td><span class="status status-<?= e(strtolower($d['status'])) ?>"><?= e($d['status']) ?></span></td>
            <td>
              <?php if ($d['quoted_amount']): ?>
                <strong><?= e($d['quoted_currency'] ?: 'USD') ?> <?= number_format((float)$d['quoted_amount'], 2) ?></strong><br>
              <?php endif; ?>
              <?php if ($d['reply_snippet']): ?>
                <small><?= e(mb_substr($d['reply_snippet'], 0, 140)) ?><?= mb_strlen($d['reply_snippet']) > 140 ? '…' : '' ?></small>
              <?php elseif ($d['status'] === 'Sent'): ?>
                <small style="color:var(--gray-600)">Awaiting reply…</small>
              <?php endif; ?>
            </td>
            <td><a href="/admin/rfq-view.php?id=<?= (int)$d['id'] ?>" class="btn btn-outline">View</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>
