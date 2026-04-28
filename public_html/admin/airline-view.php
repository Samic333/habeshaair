<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) redirect('/admin/airlines.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $rating = (int)($_POST['rating'] ?? 0);
    if ($rating < 0) $rating = 0;
    if ($rating > 5) $rating = 5;
    $notes = trim((string)($_POST['notes'] ?? ''));
    if (mb_strlen($notes) > 5000) $notes = mb_substr($notes, 0, 5000);
    $active = !empty($_POST['active']) ? 1 : 0;

    $u = db()->prepare('UPDATE airlines SET rating = ?, notes = ?, active = ? WHERE id = ?');
    $u->execute([$rating, $notes, $active, $id]);

    flash_set('saved', 'Airline updated.');
    redirect('/admin/airline-view.php?id=' . $id);
}

$stmt = db()->prepare('SELECT * FROM airlines WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$a = $stmt->fetch();
if (!$a) redirect('/admin/airlines.php');

$saved = flash_get('saved');

$fleet    = $a['fleet_types']    ? json_decode($a['fleet_types'],    true) : [];
$regions  = $a['regions_served'] ? json_decode($a['regions_served'], true) : [];
$services = $a['service_types']  ? json_decode($a['service_types'],  true) : [];

$adminTitle = $a['name'] . ' — HabeshAir admin';
$activeNav  = 'airlines';
include __DIR__ . '/includes/admin-header.php';
?>

<div class="container">
  <p style="margin-top:1.5rem"><a href="/admin/airlines.php">← All airlines</a></p>

  <div class="grid grid-2" style="gap:2rem; margin-top:1rem; align-items:start">
    <div>
      <h1 style="margin-bottom:.25em"><?= e($a['name']) ?>
        <?php if ((int)$a['is_new'] === 1): ?><span class="badge badge-warn">NEW</span><?php endif; ?>
        <?php if ((int)$a['active'] === 0): ?><span class="status status-archived" style="margin-left:.5em">Inactive</span><?php endif; ?>
      </h1>
      <p style="color:var(--gray-600)">
        Last sync: <?= e($a['synced_at'] ?: 'never') ?>
        <?php if ($a['sheet_row_id']): ?> · Sheet row: <code><?= e($a['sheet_row_id']) ?></code><?php endif; ?>
      </p>

      <h3 style="margin-top:1.5rem">Identification</h3>
      <dl class="kv">
        <?php if ($a['iata_code']): ?><dt>IATA</dt><dd><code><?= e($a['iata_code']) ?></code></dd><?php endif; ?>
        <?php if ($a['icao_code']): ?><dt>ICAO</dt><dd><code><?= e($a['icao_code']) ?></code></dd><?php endif; ?>
        <?php if ($a['base_country']): ?><dt>Base country</dt><dd><?= e($a['base_country']) ?></dd><?php endif; ?>
        <?php if ($a['website']): ?><dt>Website</dt><dd><a href="<?= e($a['website']) ?>" target="_blank" rel="noopener"><?= e($a['website']) ?></a></dd><?php endif; ?>
      </dl>

      <h3 style="margin-top:1.5rem">Contact</h3>
      <dl class="kv">
        <?php if ($a['contact_name']): ?><dt>Contact</dt><dd><?= e($a['contact_name']) ?></dd><?php endif; ?>
        <?php if ($a['contact_email']): ?><dt>Email</dt><dd><a href="mailto:<?= e($a['contact_email']) ?>"><?= e($a['contact_email']) ?></a></dd><?php endif; ?>
        <?php if ($a['phone']): ?><dt>Phone</dt><dd><?= e($a['phone']) ?></dd><?php endif; ?>
        <?php if ($a['whatsapp']): ?><dt>WhatsApp</dt><dd><a href="https://wa.me/<?= e(preg_replace('/[^0-9]/', '', $a['whatsapp'])) ?>" target="_blank" rel="noopener"><?= e($a['whatsapp']) ?> ↗</a></dd><?php endif; ?>
      </dl>

      <h3 style="margin-top:1.5rem">Capabilities</h3>
      <dl class="kv">
        <?php if ($services): ?><dt>Services</dt><dd><?= e(implode(', ', $services)) ?></dd><?php endif; ?>
        <?php if ($fleet): ?><dt>Fleet</dt><dd><?= e(implode(', ', $fleet)) ?></dd><?php endif; ?>
        <?php if ($regions): ?><dt>Regions</dt><dd><?= e(implode(', ', $regions)) ?></dd><?php endif; ?>
        <?php if ($a['capacity_pax_max']): ?><dt>Max passengers</dt><dd><?= (int)$a['capacity_pax_max'] ?></dd><?php endif; ?>
        <?php if ($a['capacity_kg_max']): ?><dt>Max payload</dt><dd><?= number_format((int)$a['capacity_kg_max']) ?> kg</dd><?php endif; ?>
      </dl>
    </div>

    <div>
      <h3>Internal</h3>
      <?php if ($saved): ?><div class="alert alert-success"><?= e($saved) ?></div><?php endif; ?>

      <form method="post" class="form-card">
        <?= csrf_field() ?>
        <div class="form-grid">
          <div class="form-group">
            <label for="rating">Rating (0-5)</label>
            <select id="rating" name="rating">
              <?php for ($i = 0; $i <= 5; $i++): ?>
                <option value="<?= $i ?>"<?= (int)$a['rating'] === $i ? ' selected' : '' ?>><?= $i ? str_repeat('★', $i) . ' (' . $i . ')' : '— not rated —' ?></option>
              <?php endfor; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="checkbox-row">
              <input type="checkbox" name="active" value="1"<?= (int)$a['active'] === 1 ? ' checked' : '' ?>>
              <span>Active (eligible for RFQs)</span>
            </label>
          </div>
          <div class="form-group">
            <label for="notes">Internal notes</label>
            <textarea id="notes" name="notes" maxlength="5000" rows="6"><?= e($a['notes']) ?></textarea>
            <span class="hint">Reset on each sheet-sync only if blank in the sheet — otherwise preserved here.</span>
          </div>
          <button class="btn btn-navy" type="submit">Save</button>
        </div>
      </form>

      <p style="color:var(--gray-600); font-size:.85rem; margin-top:1rem">
        IATA/ICAO, contact, capacity and capability columns come from the Google Sheet — edit them there.
      </p>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>
