<?php
/**
 * rfq-view.php — single RFQ dispatch detail. Admin can manually mark
 * Replied/Quoted/Declined and capture quoted_amount + a reply snippet
 * (placeholder until Phase 3's IMAP poll cron does this automatically).
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) redirect('/admin/requests.php');

$STATUSES = ['Sent','Replied','Quoted','Declined','No-Response','Cancelled'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $action = (string)($_POST['action'] ?? 'save');

    if ($action === 'save_as_quote') {
        // One-click conversion: pull operator_price + currency from the dispatch
        // and create a Draft quote. Admin then refines on quote-view.php.
        $disp = db()->prepare(
            'SELECT request_id, airline_id, quoted_amount, quoted_currency
             FROM rfq_dispatches WHERE id = ? LIMIT 1'
        );
        $disp->execute([$id]);
        $row = $disp->fetch();
        if (!$row) redirect('/admin/requests.php');

        $operator = (float)($_POST['operator_price'] ?? $row['quoted_amount'] ?? 0);
        $currency = strtoupper(trim((string)($_POST['currency'] ?? $row['quoted_currency'] ?? 'USD')));
        if (strlen($currency) !== 3) $currency = 'USD';

        if ($operator <= 0) {
            flash_set('rfq_saved', 'Need a positive operator price to create the quote.');
            redirect('/admin/rfq-view.php?id=' . $id);
        }

        $ins = db()->prepare(
            'INSERT INTO quotes
             (request_id, dispatch_id, airline_id, operator_price, currency,
              markup_pct, service_fee, status)
             VALUES (?, ?, ?, ?, ?, 10.00, 0, "Draft")'
        );
        $ins->execute([
            (int)$row['request_id'],
            $id,
            (int)$row['airline_id'],
            $operator,
            $currency,
        ]);
        $newQuoteId = (int)db()->lastInsertId();

        // Bump dispatch to Quoted if not already past it
        db()->prepare('UPDATE rfq_dispatches SET status = "Quoted" WHERE id = ? AND status IN ("Sent","Replied")')
            ->execute([$id]);

        flash_set('quote_msg', 'Quote drafted from this RFQ reply.');
        redirect('/admin/quote-view.php?id=' . $newQuoteId);
    }

    // Default: save dispatch fields
    $newStatus = (string)($_POST['status'] ?? 'Sent');
    if (!in_array($newStatus, $STATUSES, true)) $newStatus = 'Sent';
    $amount   = trim((string)($_POST['quoted_amount'] ?? ''));
    $currency = strtoupper(trim((string)($_POST['quoted_currency'] ?? '')));
    if ($currency !== '' && strlen($currency) !== 3) $currency = '';
    $snippet  = trim((string)($_POST['reply_snippet'] ?? ''));
    if (mb_strlen($snippet) > 500) $snippet = mb_substr($snippet, 0, 500);
    $notes    = trim((string)($_POST['internal_notes'] ?? ''));
    if (mb_strlen($notes) > 5000) $notes = mb_substr($notes, 0, 5000);

    $replyAt = ($newStatus !== 'Sent' && $newStatus !== 'Cancelled') ? date('Y-m-d H:i:s') : null;

    $u = db()->prepare(
        'UPDATE rfq_dispatches
         SET status = ?, quoted_amount = ?, quoted_currency = ?,
             reply_snippet = ?, internal_notes = ?, reply_at = COALESCE(reply_at, ?)
         WHERE id = ?'
    );
    $u->execute([
        $newStatus,
        $amount !== '' ? (float)$amount : null,
        $currency !== '' ? $currency : null,
        $snippet !== '' ? $snippet : null,
        $notes,
        $replyAt,
        $id,
    ]);

    flash_set('rfq_saved', 'RFQ updated.');
    redirect('/admin/rfq-view.php?id=' . $id);
}

$stmt = db()->prepare(
    'SELECT d.*, a.name AS airline_name, a.iata_code, a.icao_code, a.contact_email,
            r.reference_code, r.service_type, r.origin, r.destination, r.travel_date,
            r.urgency_level
     FROM rfq_dispatches d
     JOIN airlines a ON a.id = d.airline_id
     JOIN charter_requests r ON r.id = d.request_id
     WHERE d.id = ? LIMIT 1'
);
$stmt->execute([$id]);
$d = $stmt->fetch();
if (!$d) redirect('/admin/requests.php');

$saved = flash_get('rfq_saved');

// Inbound replies routed by IMAP cron (Phase 3)
$repStmt = db()->prepare(
    'SELECT id, from_email, from_name, subject, body_text, body_html,
            has_attachments, attachments, received_at
     FROM rfq_replies
     WHERE dispatch_id = ?
     ORDER BY received_at ASC'
);
$repStmt->execute([$id]);
$replies = $repStmt->fetchAll();

// Quotes already created from this dispatch
$qStmt = db()->prepare(
    'SELECT id, operator_price, currency, total_to_client, status, created_at
     FROM quotes
     WHERE dispatch_id = ?
     ORDER BY created_at DESC'
);
$qStmt->execute([$id]);
$dispatchQuotes = $qStmt->fetchAll();

// Add the form-style "save as quote" pill ONLY if there's no quote yet AND we
// have a quoted_amount captured (either from the IMAP cron or admin manually).
$canSaveAsQuote = empty($dispatchQuotes) && !empty($d['quoted_amount']);

$adminTitle = 'RFQ to ' . $d['airline_name'] . ' — ' . $d['reference_code'];
$activeNav  = 'requests';
include __DIR__ . '/includes/admin-header.php';
?>

<div class="container">
  <p style="margin-top:1.5rem">
    <a href="/admin/request-view.php?id=<?= (int)$d['request_id'] ?>">← Request <?= e($d['reference_code']) ?></a>
  </p>

  <div class="grid grid-2" style="gap:2rem; margin-top:1rem; align-items:start">
    <div>
      <h1 style="margin-bottom:.25em">RFQ → <?= e($d['airline_name']) ?></h1>
      <p style="color:var(--gray-600)">
        Sent <?= e($d['sent_at']) ?>
        <?php if ($d['reply_at']): ?> · Replied <?= e($d['reply_at']) ?><?php endif; ?>
      </p>

      <h3 style="margin-top:1.5rem">Charter request</h3>
      <dl class="kv">
        <dt>Reference</dt><dd><a href="/admin/request-view.php?id=<?= (int)$d['request_id'] ?>"><?= e($d['reference_code']) ?></a></dd>
        <dt>Service</dt><dd><?= e($d['service_type']) ?></dd>
        <dt>Route</dt><dd><?= e($d['origin']) ?> → <?= e($d['destination']) ?></dd>
        <dt>Date</dt><dd><?= e($d['travel_date'] ?: '—') ?></dd>
        <dt>Urgency</dt><dd><?= e($d['urgency_level']) ?></dd>
      </dl>

      <h3 style="margin-top:1.5rem">Outbound email</h3>
      <dl class="kv">
        <dt>To</dt><dd><?= e($d['to_email']) ?></dd>
        <dt>Reply-to token</dt><dd><code><?= e($d['reply_token']) ?></code></dd>
        <dt>Subject</dt><dd><?= e($d['subject']) ?></dd>
      </dl>
      <details style="margin-top:1rem">
        <summary style="cursor:pointer">View body</summary>
        <pre style="background:var(--gray-100); padding:1rem; border-radius:var(--radius); white-space:pre-wrap; font-family:var(--font-sans); font-size:.92rem; margin-top:.5rem"><?= e($d['body_text']) ?></pre>
      </details>

      <?php if ($dispatchQuotes): ?>
        <h3 style="margin-top:1.5rem">Quotes from this RFQ</h3>
        <ul style="list-style:none; padding:0; margin:0">
          <?php foreach ($dispatchQuotes as $dq): ?>
            <li style="padding:.5rem 0; border-bottom:1px dashed var(--gray-200)">
              <a href="/admin/quote-view.php?id=<?= (int)$dq['id'] ?>"><strong>Quote #<?= (int)$dq['id'] ?></strong></a>
              · <?= e($dq['currency']) ?> <?= number_format((float)$dq['operator_price'], 2) ?>
              → total <?= e($dq['currency']) ?> <?= number_format((float)$dq['total_to_client'], 2) ?>
              · <span class="status status-<?= e(strtolower($dq['status'])) ?>"><?= e($dq['status']) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>

    <div>
      <h3>Update status</h3>
      <?php if ($saved): ?><div class="alert alert-success"><?= e($saved) ?></div><?php endif; ?>

      <form method="post" class="form-card">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <div class="form-grid">
          <div class="form-group">
            <label for="status">Status</label>
            <select id="status" name="status">
              <?php foreach ($STATUSES as $s): ?>
                <option value="<?= e($s) ?>"<?= $d['status'] === $s ? ' selected':'' ?>><?= e($s) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label for="quoted_amount">Quoted amount</label>
            <input id="quoted_amount" name="quoted_amount" type="number" step="0.01" min="0"
                   value="<?= e($d['quoted_amount']) ?>" placeholder="e.g. 4500">
          </div>
          <div class="form-group">
            <label for="quoted_currency">Currency (3-letter)</label>
            <input id="quoted_currency" name="quoted_currency" type="text" maxlength="3"
                   value="<?= e($d['quoted_currency']) ?>" placeholder="USD" style="text-transform:uppercase">
          </div>
          <div class="form-group">
            <label for="reply_snippet">Reply snippet (first 500 chars)</label>
            <textarea id="reply_snippet" name="reply_snippet" maxlength="500" rows="4"><?= e($d['reply_snippet']) ?></textarea>
            <span class="hint">Phase 3's IMAP cron will fill this automatically. Until then, paste the airline's reply text here.</span>
          </div>
          <div class="form-group">
            <label for="internal_notes">Internal notes</label>
            <textarea id="internal_notes" name="internal_notes" maxlength="5000" rows="4"><?= e($d['internal_notes']) ?></textarea>
          </div>
          <button class="btn btn-navy" type="submit">Save</button>
        </div>
      </form>

      <?php if ($canSaveAsQuote): ?>
        <h3 style="margin-top:2rem">Convert reply to a quote</h3>
        <p style="color:var(--gray-600)">
          A price was captured for this RFQ. One click drafts a <strong>quote</strong> with the airline as operator and the standard 10% markup — you'll refine markup, validity, and client wording on the next page.
        </p>
        <form method="post" class="form-card">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="save_as_quote">
          <div class="form-grid">
            <div class="form-group">
              <label for="quote_operator">Operator price</label>
              <input id="quote_operator" name="operator_price" type="number" step="0.01" min="0"
                     value="<?= e($d['quoted_amount']) ?>" required>
            </div>
            <div class="form-group">
              <label for="quote_currency">Currency</label>
              <input id="quote_currency" name="currency" type="text" maxlength="3"
                     value="<?= e($d['quoted_currency'] ?: 'USD') ?>" style="text-transform:uppercase" required>
            </div>
            <button class="btn btn-navy" type="submit">★ Save as quote</button>
          </div>
        </form>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($replies): ?>
    <h2 style="margin-top:3rem">Inbound replies</h2>
    <p style="color:var(--gray-600); margin-bottom:1rem">
      Routed automatically by the IMAP cron (every 2 min) using the
      <code>replies+<?= e($d['reply_token']) ?>@</code> reply-to tag.
    </p>
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
            Open the original mail in <a href="https://habeshair.com:2096" target="_blank" rel="noopener">webmail</a> to download attachments.
          </p>
        <?php endif; ?>
      </details>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>
