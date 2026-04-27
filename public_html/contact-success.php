<?php
require_once __DIR__ . '/includes/bootstrap.php';
$page = [
    'title'       => 'Message sent — HabeshAir',
    'description' => 'Your message has been received. A coordinator will be in touch shortly.',
    'canonical'   => url('/contact-success.php'),
    'schema'      => 'organization',
];
include __DIR__ . '/includes/header.php';
?>
<section class="section">
  <div class="container prose text-center">
    <h1>Message sent</h1>
    <p class="lede">Thank you. A coordinator will be in touch shortly. For urgent charter or medevac requests, reach us directly on WhatsApp <a href="<?= e(whatsapp_link()) ?>"><?= e(cfg('app.whatsapp_display')) ?></a>.</p>
    <p style="margin-top:2rem"><a href="/" class="btn btn-outline">Back to home</a> <a href="/request.php" class="btn btn-gold">Request a charter</a></p>
  </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
