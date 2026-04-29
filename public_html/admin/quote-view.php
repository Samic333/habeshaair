<?php
/**
 * quote-view.php — single quote detail. Shows the originating RFQ replies
 * (when any), lets admin edit pricing/markup/notes, mark this quote as the
 * winner (sets siblings to Lost + bumps request to 'Quoted'), and stages
 * a Phase-4 client send.
 *
 * Computed columns (markup_amount, client_price, total_to_client) live in
 * MySQL — we never write them; we read them after each save.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) redirect('/admin/requests.php');

$STATUSES = ['Draft','Sent','Accepted','Rejected','Expired','Won','Lost'];

// ---- POST handlers --------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $action = (string)($_POST['action'] ?? 'save');

    if ($action === 'save') {
        $operator    = (float)($_POST['operator_price'] ?? 0);
        $currency    = strtoupper(trim((string)($_POST['currency'] ?? 'USD')));
        if (strlen($currency) !== 3) $currency = 'USD';
        $markup      = (float)($_POST['markup_pct'] ?? 10);
        if ($markup < 0)   $markup = 0;
        if ($markup > 100) $markup = 100;
        $serviceFee  = (float)($_POST['service_fee'] ?? 0);
        if ($serviceFee < 0) $serviceFee = 0;
        $validity    = trim((string)($_POST['validity_until'] ?? ''));
        if ($validity !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $validity)) $validity = '';
        $clientText  = trim((string)($_POST['client_facing_text'] ?? ''));
        if (mb_strlen($clientText) > 10000) $clientText = mb_substr($clientText, 0, 10000);
        $status      = (string)($_POST['status'] ?? 'Draft');
        if (!in_array($status, $STATUSES, true)) $status = 'Draft';
        $notes       = trim((string)($_POST['notes'] ?? ''));
        if (mb_strlen($notes) > 5000) $notes = mb_substr($notes, 0, 5000);

        $u = db()->prepare(
            'UPDATE quotes
             SET operator_price = ?, currency = ?, markup_pct = ?,
                 service_fee = ?, validity_until = ?, client_facing_text = ?,
                 status = ?, notes = ?
             WHERE id = ?'
        );
        $u->execute([
            $operator, $currency, $markup, $serviceFee,
            $validity !== '' ? $validity : null,
            $clientText !== '' ? $clientText : null,
            $status,
            $notes !== '' ? $notes : null,
            $id,
        ]);
        flash_set('quote_saved', 'Quote saved.');
        redirect('/admin/quote-view.php?id=' . $id);
    }

    if ($action === 'use_winner') {
        // Mark THIS quote as Won. Sibling quotes for the same request go Lost.
        // Bump request status to 'Quoted' if it's still upstream of that.
        $row = db()->prepare('SELECT request_id FROM quotes WHERE id = ?');
        $row->execute([$id]);
        $reqId = (int)$row->fetchColumn();
        if ($reqId > 0) {
            db()->beginTransaction();
            try {
                db()->prepare('UPDATE quotes SET status = "Lost" WHERE request_id = ? AND id <> ? AND status NOT IN ("Won","Accepted","Rejected","Expired")')
                    ->execute([$reqId, $id]);
                db()->prepare('UPDATE quotes SET status = "Won" WHERE id = ?')
                    ->execute([$id]);
                $progressed = ['Confirmed','Flown','Cancelled','Closed'];
                $cur = db()->prepare('SELECT status FROM charter_requests WHERE id = ?');
                $cur->execute([$reqId]);
                $st = (string)$cur->fetchColumn();
                if (!in_array($st, $progressed, true)) {
                    db()->prepare('UPDATE charter_requests SET status = "Quoted" WHERE id = ?')
                        ->execute([$reqId]);
                }
                db()->commit();
                flash_set('quote_saved', 'Marked as winner. Other quotes set to Lost; request → Quoted.');
            } catch (\Throwable $e) {
                db()->rollBack();
                flash_set('quote_saved', 'Could not mark winner: ' . $e->getMessage());
            }
        }
        redirect('/admin/quote-view.php?id=' . $id);
    }
}

// ---- Load -----------------------------------------------------------------
$stmt = db()->prepare(
    'SELECT q.*, a.name AS airline_name, a.iata_code, a.icao_code, a.contact_email,
            r.reference_code, r.full_name AS client_name, r.email AS client_email,
            r.service_type, r.origin, r.destination, r.travel_date, r.urgency_level,
            d.reply_token, d.subject AS dispatch_subject, d.status AS dispatch_status,
            d.sent_at AS dispatch_sent_at
     FROM quotes q
     JOIN airlines a ON a.id = q.airline_id
     JOIN charter_requests r ON r.id = q.request_id
     LEFT JOIN rfq_dispatches d ON d.id = q.dispatch_id
     WHERE q.id = ? LIMIT 1'
);
$stmt->execute([$id]);
$q = $stmt->fetch();
if (!$q) redirect('/admin/requests.php');

// Reply trail (only when this quote came from an RFQ dispatch)
$replies = [];
if (!empty($q['dispatch_id'])) {
    $rs = db()->prepare(
        'SELECT id, from_email, from_name, subject, body_text, body_html,
                has_attachments, attachments, received_at
         FROM rfq_replies
         WHERE dispatch_id = ?
         ORDER BY received_at ASC'
    );
    $rs->execute([(int)$q['dispatch_id']]);
    $replies = $rs->fetchAll();
}

$saved = flash_get('quote_saved');

$adminTitle = 'Quote — ' . $q['reference_code'] . ' / ' . $q['airline_name'];
$activeNav  = 'requests';
include __DIR__ . '/includes/admin-header.php';
?>

<div class="container">
  <p style="margin-top:1.5rem">
    <a href="/admin/request-view.php?id=<?= (int)$q['request_id'] ?>">← Request <?= e($q['reference_code']) ?></a>
  </p>

  <h1 style="margin-bottom:.25em">
    Quote — <?= e($q['airline_name']) ?>
    <span class="status status-<?= e(strtolower($q['status'])) ?>" style="vertical-align:middle"><?= e($q['status']) ?></span>
  </h1>
  <p style="color:var(--gray-600)">
    Created <?= e($q['created_at']) ?>
    <?php if ($q['sent_at']): ?> · Sent <?= e($q['sent_at']) ?><?php endif; ?>
    <?php if ($q['accepted_at']): ?> · Accepted <?= e($q['accepted_at']) ?><?php endif; ?>
  </p>

  <?php if ($saved): ?><div class="alert alert-success" style="margin-top:1rem"><?= e($saved) ?></div><?php endif; ?>

  <div class="grid grid-2" style="gap:2rem; margin-top:1.5rem; align-items:start">

    <!-- Left column: pricing form + actions -->
    <div>
      <h3>Pricing</h3>
      <form method="post" class="form-card">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">

        <div class="form-grid">
          <div class="form-group">
            <label for="operator_price">Operator price (what airline charges us)</label>
            <input id="operator_price" name="operator_price" type="number" step="0.01" min="0"
                   value="<?= e($q['operator_price']) ?>" required>
          </div>

          <div class="form-group">
            <label for="currency">Currency (3-letter)</label>
            <input id="currency" name="currency" type="text" maxlength="3"
                   value="<?= e($q['currency']) ?>" style="text-transform:uppercase" required>
          </div>

          <div class="form-group">
            <label for="markup_pct">Markup (%)</label>
            <input id="markup_pct" name="markup_pct" type="number" step="0.01" min="0" max="100"
                   value="<?= e($q['markup_pct']) ?>" required>
            <span class="hint">Standard 10%. VIP/Medevac higher; cargo/long-haul lower.</span>
          </div>

          <div class="form-group">
            <label for="service_fee">Service fee (flat)</label>
            <input id="service_fee" name="service_fee" type="number" step="0.01" min="0"
                   value="<?= e($q['service_fee']) ?>">
          </div>

          <div class="form-group">
            <label for="validity_until">Validity until</label>
            <input id="validity_until" name="validity_until" type="date"
                   value="<?= e($q['validity_until']) ?>">
          </div>

          <div class="form-group">
            <label for="status">Status</label>
            <select id="status" name="status">
              <?php foreach ($STATUSES as $s): ?>
                <option value="<?= e($s) ?>"<?= $q['status'] === $s ? ' selected':'' ?>><?= e($s) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group" style="grid-column:1/-1">
            <label for="client_facing_text">Client-facing message (shown in the quote email)</label>
            <textarea id="client_facing_text" name="client_facing_text" rows="5"
                      placeholder="Optional. Will appear in the email body sent to the client."><?= e($q['client_facing_text']) ?></textarea>
          </div>

          <div class="form-group" style="grid-column:1/-1">
            <label for="notes">Internal notes</label>
            <textarea id="notes" name="notes" rows="3"><?= e($q['notes']) ?></textarea>
          </div>

          <button class="btn btn-navy" type="submit">Save quote</button>
        </div>
      </form>

      <?php
        $client    = (float)$q['client_price'];
        $total     = (float)$q['total_to_client'];
        $markupAmt = (float)$q['markup_amount'];
      ?>
      <h3 style="margin-top:1.5rem">Computed totals</h3>
      <dl class="kv">
        <dt>Operator price</dt><dd><?= e($q['currency']) ?> <?= number_format((float)$q['operator_price'], 2) ?></dd>
        <dt>+ Markup (<?= e($q['markup_pct']) ?>%)</dt><dd><?= e($q['currency']) ?> <?= number_format($markupAmt, 2) ?></dd>
        <dt>Client price</dt><dd><strong><?= e($q['currency']) ?> <?= number_format($client, 2) ?></strong></dd>
        <dt>+ Service fee</dt><dd><?= e($q['currency']) ?> <?= number_format((float)$q['service_fee'], 2) ?></dd>
        <dt>Total to client</dt><dd><strong style="color:var(--navy-700, #1e3a8a)"><?= e($q['currency']) ?> <?= number_format($total, 2) ?></strong></dd>
      </dl>

      <h3 style="margin-top:1.5rem">Actions</h3>
      <div style="display:flex; gap:.75rem; flex-wrap:wrap">
        <?php if ($q['status'] !== 'Won'): ?>
          <form method="post" onsubmit="return confirm('Mark this as the winning quote? Other quotes for this request will be set to Lost and the request will move to status Quoted.');">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="use_winner">
            <button class="btn btn-navy" type="submit">★ Use this quote (mark winner)</button>
          </form>
        <?php endif; ?>

        <button type="button" class="btn btn-outline" disabled
                title="Phase 4 — sends a branded HTML email + PDF to the client">
          Send to client (Phase 4)
        </button>
      </div>
    </div>

    <!-- Right column: context (request + airline + reply trail) -->
    <div>
      <h3>Charter request</h3>
      <dl class="kv">
        <dt>Reference</dt><dd><a href="/admin/request-view.php?id=<?= (int)$q['request_id'] ?>"><?= e($q['reference_code']) ?></a></dd>
        <dt>Service</dt><dd><?= e($q['service_type']) ?></dd>
        <dt>Route</dt><dd><?= e($q['origin']) ?> → <?= e($q['destination']) ?></dd>
        <dt>Date</dt><dd><?= e($q['travel_date'] ?: '—') ?></dd>
        <dt>Urgency</dt><dd><?= e($q['urgency_level']) ?></dd>
        <dt>Client</dt><dd><?= e($q['client_name']) ?> — <a href="mailto:<?= e($q['client_email']) ?>"><?= e($q['client_email']) ?></a></dd>
      </dl>

      <h3 style="margin-top:1.5rem">Airline</h3>
      <dl class="kv">
        <dt>Name</dt><dd>
          <a href="/admin/airline-view.php?id=<?= (int)$q['airline_id'] ?>"><?= e($q['airline_name']) ?></a>
          <?php if ($q['iata_code']): ?> <code><?= e($q['iata_code']) ?></code><?php endif; ?>
          <?php if ($q['icao_code']): ?> <code><?= e($q['icao_code']) ?></code><?php endif; ?>
        </dd>
        <?php if ($q['contact_email']): ?><dt>Contact</dt><dd><a href="mailto:<?= e($q['contact_email']) ?>"><?= e($q['contact_email']) ?></a></dd><?php endif; ?>
      </dl>

      <?php if (!empty($q['dispatch_id'])): ?>
        <h3 style="margin-top:1.5rem">Source RFQ</h3>
        <dl class="kv">
          <dt>Dispatch</dt><dd><a href="/admin/rfq-view.php?id=<?= (int)$q['dispatch_id'] ?>">View RFQ #<?= (int)$q['dispatch_id'] ?></a></dd>
          <dt>Sent</dt><dd><?= e($q['dispatch_sent_at']) ?></dd>
          <dt>Reply token</dt><dd><code><?= e($q['reply_token']) ?></code></dd>
          <dt>Subject</dt><dd><?= e($q['dispatch_subject']) ?></dd>
        </dl>
      <?php endif; ?>

      <?php if ($replies): ?>
        <h3 style="margin-top:1.5rem">Airline replies</h3>
        <?php foreach ($replies as $r):
          $atts = $r['attachments'] ? json_decode($r['attachments'], true) : [];
        ?>
          <details style="margin-bottom:1rem; border:1px solid var(--gray-200); border-radius:var(--radius); padding:.75rem 1rem" open>
            <summary style="cursor:pointer">
              <strong><?= e($r['from_name'] ?: $r['from_email']) ?></strong>
              <span style="color:var(--gray-600)"> &lt;<?= e($r['from_email']) ?>&gt; · <?= e($r['received_at']) ?></span>
            </summary>
            <?php if ($r['subject']): ?>
              <p style="margin:.5rem 0; color:var(--gray-700)"><strong>Subject:</strong> <?= e($r['subject']) ?></p>
            <?php endif; ?>
            <?php if ($r['body_text']): ?>
              <pre style="background:var(--gray-100); padding:.75rem; border-radius:var(--radius); white-space:pre-wrap; font-family:var(--font-sans); font-size:.92rem; max-height:400px; overflow:auto"><?= e($r['body_text']) ?></pre>
            <?php elseif ($r['body_html']): ?>
              <div style="background:var(--gray-100); padding:.75rem; border-radius:var(--radius); max-height:400px; overflow:auto">
                <?= strip_tags($r['body_html'], '<p><br><strong><em><ul><ol><li><a><h1><h2><h3><h4><h5><h6><blockquote><pre><code><table><thead><tbody><tr><td><th>') ?>
              </div>
            <?php else: ?>
              <p style="color:var(--gray-600); font-style:italic">(empty body)</p>
            <?php endif; ?>
            <?php if ($atts): ?>
              <p style="margin:.75rem 0 0; color:var(--gray-700)"><strong>Attachments (<?= count($atts) ?>):</strong></p>
              <ul style="margin:.25rem 0 0">
                <?php foreach ($atts as $att): ?>
                  <li><?= e($att['filename'] ?? 'unnamed') ?>
                    <?php if (!empty($att['size'])): ?>
                      <small style="color:var(--gray-600)">(<?= number_format((int)$att['size'] / 1024, 1) ?> KB)</small>
                    <?php endif; ?>
                  </li>
                <?php endforeach; ?>
              </ul>
              <p style="font-size:.85rem; color:var(--gray-600); margin-top:.5rem">
                Open the original mail in your inbox to download attachments.
              </p>
            <?php endif; ?>
          </details>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>
