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
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>
