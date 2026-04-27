<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) redirect('/admin/messages.php');

$STATUSES = ['New','Read','Replied','Archived'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $status = (string)($_POST['status'] ?? 'New');
    if (!in_array($status, $STATUSES, true)) $status = 'New';
    db()->prepare('UPDATE contact_messages SET status = ? WHERE id = ?')->execute([$status, $id]);
    flash_set('saved', 'Message updated.');
    redirect('/admin/message-view.php?id=' . $id);
}

$stmt = db()->prepare('SELECT * FROM contact_messages WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$m = $stmt->fetch();
if (!$m) redirect('/admin/messages.php');

// Auto-mark New as Read on first view
if ($m['status'] === 'New') {
    db()->prepare('UPDATE contact_messages SET status = "Read" WHERE id = ?')->execute([$id]);
    $m['status'] = 'Read';
}

$saved = flash_get('saved');
$adminTitle = 'Message — HabeshaAir admin';
$activeNav  = 'messages';
include __DIR__ . '/includes/admin-header.php';
?>
<div class="container">
  <p style="margin-top:1.5rem"><a href="/admin/messages.php">← All messages</a></p>

  <?php if ($saved): ?><div class="alert alert-success"><?= e($saved) ?></div><?php endif; ?>

  <div class="grid grid-2" style="gap:2rem; align-items:start; margin-top:1rem">
    <article class="form-card">
      <h1 style="margin:0 0 .25em"><?= e($m['subject']) ?></h1>
      <p style="margin:0 0 1.25rem; color:var(--gray-600)">
        From <strong><?= e($m['full_name']) ?></strong> &lt;<a href="mailto:<?= e($m['email']) ?>"><?= e($m['email']) ?></a>&gt;
        <?= $m['phone'] ? ' · ' . e($m['phone']) : '' ?>
        · <?= e($m['created_at']) ?>
      </p>
      <div style="white-space:pre-wrap; line-height:1.7"><?= e($m['message']) ?></div>
      <div style="margin-top:1.5rem">
        <a class="btn btn-navy" href="mailto:<?= e($m['email']) ?>?subject=<?= rawurlencode('Re: ' . $m['subject']) ?>">Reply via email</a>
      </div>
    </article>

    <form method="post" class="form-card">
      <?= csrf_field() ?>
      <div class="form-grid">
        <div class="form-group">
          <label for="status">Status</label>
          <select id="status" name="status">
            <?php foreach ($STATUSES as $s): ?>
              <option value="<?= e($s) ?>"<?= $m['status'] === $s ? ' selected':'' ?>><?= e($s) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button class="btn btn-navy" type="submit">Save</button>
      </div>
    </form>
  </div>
</div>
<?php include __DIR__ . '/includes/admin-footer.php'; ?>
