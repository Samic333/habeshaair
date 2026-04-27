<?php
require_once __DIR__ . '/includes/bootstrap.php';

$ref = (string)($_GET['ref'] ?? '');
if ($ref === '' || !preg_match('/^HA-[A-Z0-9-]+$/', $ref)) $ref = 'HA-PENDING';

$page = [
    'title'       => 'Request received — HabeshaAir',
    'description' => 'Your charter request has been received. A coordinator will be in touch shortly.',
    'canonical'   => url('/request-success.php'),
    'schema'      => 'organization',
];
include __DIR__ . '/includes/header.php';

$wa = whatsapp_link("Hello HabeshaAir, I just submitted a charter request. Reference: $ref");
?>

<section class="section">
  <div class="container prose text-center">
    <span class="badge">Reference <?= e($ref) ?></span>
    <h1 style="margin-top:1rem">Request received</h1>
    <p class="lede">Thank you. Your charter request has been logged. A coordinator will review it and respond — typically within 60 minutes during business hours, faster for urgent flights.</p>

    <div class="alert alert-info" style="text-align:left">
      <strong>What happens next</strong>
      <ol style="margin:.5rem 0 0; padding-left:1.25rem">
        <li>We review your route, payload, and timing.</li>
        <li>We source aircraft options through certified Part 135 operators (or foreign equivalents).</li>
        <li>You receive a written proposal with pricing and operational notes.</li>
        <li>On confirmation, we coordinate the flight with the operator and stay with you through landing.</li>
      </ol>
    </div>

    <div class="form-actions" style="justify-content:center; margin-top:2rem">
      <a href="<?= e($wa) ?>" class="btn btn-gold btn-lg" target="_blank" rel="noopener">Continue on WhatsApp</a>
      <a href="mailto:<?= e(cfg('app.email')) ?>?subject=<?= rawurlencode('Charter request ' . $ref) ?>" class="btn btn-outline">Email <?= e(cfg('app.email')) ?></a>
    </div>

    <p style="margin-top:2rem"><a href="/">← Back to home</a></p>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
