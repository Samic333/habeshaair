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
    $action = (string)($_POST['action'] ?? 'update_status');

    if ($action === 'create_quote') {
        // Manual quote — admin clicked "Add quote" without going through an RFQ reply.
        $airlineId = (int)($_POST['airline_id'] ?? 0);
        $operator  = (float)($_POST['operator_price'] ?? 0);
        $currency  = strtoupper(trim((string)($_POST['currency'] ?? 'USD')));
        if (strlen($currency) !== 3) $currency = 'USD';
        $markup    = (float)($_POST['markup_pct'] ?? 10);
        if ($markup < 0)   $markup = 0;
        if ($markup > 100) $markup = 100;
        $serviceFee = (float)($_POST['service_fee'] ?? 0);
        if ($serviceFee < 0) $serviceFee = 0;
        $validity  = trim((string)($_POST['validity_until'] ?? ''));
        if ($validity !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $validity)) $validity = '';

        if ($airlineId <= 0 || $operator <= 0) {
            flash_set('quote_err', 'Pick an airline and enter the operator price.');
        } else {
            $exists = db()->prepare('SELECT 1 FROM airlines WHERE id = ?');
            $exists->execute([$airlineId]);
            if (!$exists->fetchColumn()) {
                flash_set('quote_err', 'Selected airline not found.');
            } else {
                $ins = db()->prepare(
                    'INSERT INTO quotes
                     (request_id, dispatch_id, airline_id, operator_price, currency,
                      markup_pct, service_fee, validity_until, status)
                     VALUES (?, NULL, ?, ?, ?, ?, ?, ?, "Draft")'
                );
                $ins->execute([
                    $id, $airlineId, $operator, $currency, $markup, $serviceFee,
                    $validity !== '' ? $validity : null,
                ]);
                $newQuoteId = (int)db()->lastInsertId();
                flash_set('quote_msg', 'Quote drafted.');
                redirect('/admin/quote-view.php?id=' . $newQuoteId);
            }
        }
        redirect('/admin/request-view.php?id=' . $id . '#quotes');
    }

    // Default: update_status
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
$quoteMsg = flash_get('quote_msg');
$quoteErr = flash_get('quote_err');

// Quotes for this request (for the comparison panel)
$quotesStmt = db()->prepare(
    'SELECT q.id, q.airline_id, q.operator_price, q.currency, q.markup_pct,
            q.markup_amount, q.client_price, q.service_fee, q.total_to_client,
            q.validity_until, q.status, q.sent_at, q.created_at,
            a.name AS airline_name
     FROM quotes q
     JOIN airlines a ON a.id = q.airline_id
     WHERE q.request_id = ?
     ORDER BY q.status = "Won" DESC, q.total_to_client ASC, q.created_at DESC'
);
$quotesStmt->execute([$id]);
$quotes = $quotesStmt->fetchAll();

// Active airlines for the manual-quote form
$airlinesStmt = db()->query('SELECT id, name, iata_code, icao_code FROM airlines WHERE active = 1 ORDER BY name ASC');
$activeAirlines = $airlinesStmt->fetchAll();

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
        <input type="hidden" name="action" value="update_status">
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

  <h2 id="quotes" style="margin-top:3rem">Quotes</h2>
  <p style="color:var(--gray-600); margin-bottom:1rem">
    Compare priced quotes side by side. Replies that arrive via the IMAP cron land here automatically as drafts.
    You can also add a manual quote — phone calls, WhatsApp, scribbled-on-a-napkin numbers — without going through an RFQ email.
  </p>

  <?php if ($quoteMsg): ?><div class="alert alert-success"><?= e($quoteMsg) ?></div><?php endif; ?>
  <?php if ($quoteErr): ?><div class="alert alert-error"><?= e($quoteErr) ?></div><?php endif; ?>

  <?php if ($quotes): ?>
    <div class="table-wrap">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Airline</th>
            <th>Operator</th>
            <th>Markup</th>
            <th>Service fee</th>
            <th>Total to client</th>
            <th>Validity</th>
            <th>Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($quotes as $qq): ?>
          <tr<?= $qq['status'] === 'Won' ? ' style="background:#f0fdf4"' : '' ?>>
            <td><strong><?= e($qq['airline_name']) ?></strong></td>
            <td><?= e($qq['currency']) ?> <?= number_format((float)$qq['operator_price'], 2) ?></td>
            <td><?= e($qq['markup_pct']) ?>% <small style="color:var(--gray-600)">(<?= e($qq['currency']) ?> <?= number_format((float)$qq['markup_amount'], 2) ?>)</small></td>
            <td><?= e($qq['currency']) ?> <?= number_format((float)$qq['service_fee'], 2) ?></td>
            <td><strong><?= e($qq['currency']) ?> <?= number_format((float)$qq['total_to_client'], 2) ?></strong></td>
            <td><?= e($qq['validity_until'] ?: '—') ?></td>
            <td><span class="status status-<?= e(strtolower($qq['status'])) ?>"><?= e($qq['status']) ?></span></td>
            <td><a href="/admin/quote-view.php?id=<?= (int)$qq['id'] ?>" class="btn btn-outline">View</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <?php if ($activeAirlines): ?>
    <details style="margin-top:1.5rem">
      <summary style="cursor:pointer; font-weight:600">+ Add manual quote</summary>
      <form method="post" class="form-card" style="margin-top:1rem">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create_quote">
        <div class="form-grid">
          <div class="form-group">
            <label for="airline_id">Airline</label>
            <select id="airline_id" name="airline_id" required>
              <option value="">— pick an airline —</option>
              <?php foreach ($activeAirlines as $a): ?>
                <option value="<?= (int)$a['id'] ?>"><?= e($a['name']) ?>
                  <?php if ($a['iata_code']): ?> (<?= e($a['iata_code']) ?>)<?php endif; ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label for="operator_price">Operator price</label>
            <input id="operator_price" name="operator_price" type="number" step="0.01" min="0" required placeholder="e.g. 4500">
          </div>
          <div class="form-group">
            <label for="currency">Currency</label>
            <input id="currency" name="currency" type="text" maxlength="3" value="USD" style="text-transform:uppercase" required>
          </div>
          <div class="form-group">
            <label for="markup_pct">Markup %</label>
            <input id="markup_pct" name="markup_pct" type="number" step="0.01" min="0" max="100" value="10" required>
          </div>
          <div class="form-group">
            <label for="service_fee">Service fee</label>
            <input id="service_fee" name="service_fee" type="number" step="0.01" min="0" value="0">
          </div>
          <div class="form-group">
            <label for="validity_until">Valid until</label>
            <input id="validity_until" name="validity_until" type="date">
          </div>
          <button class="btn btn-navy" type="submit">Create quote</button>
        </div>
      </form>
    </details>
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
