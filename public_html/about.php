<?php
require_once __DIR__ . '/includes/bootstrap.php';

$page = [
    'title'       => 'About HabeshaAir — Premium Air Charter Coordination',
    'description' => 'HabeshaAir is a premium air charter coordination platform connecting Africa to the world. VIP, cargo, humanitarian, and emergency missions through certified operators.',
    'canonical'   => url('/about.php'),
    'schema'      => 'organization',
    'active_nav'  => 'about',
];
include __DIR__ . '/includes/header.php';
?>

<section class="hero" style="padding:3.5rem 0 4rem">
  <div class="container">
    <span class="hero-badge">About</span>
    <h1>Premium air charter, coordinated with care.</h1>
    <p class="lede">HabeshaAir connects Africa to the world. We specialize in time-critical, complex, and high-value aviation missions — coordinated through certified operators we know and trust.</p>
  </div>
</section>

<section class="section">
  <div class="container prose">
    <h2>What we do</h2>
    <p>HabeshaAir is a charter coordination and brokerage platform. Through aviation partners, we offer access to over 5,000 aircraft worldwide. Our role is to understand your mission, source the right aircraft for the route and payload, and coordinate with the operator from the first call to safe landing.</p>

    <h2>Who we work with</h2>
    <p>HabeshaAir arranges flights on behalf of its clients with FAA Certified Part 135 air carriers or foreign equivalents. We work with operators experienced in unpaved strips, austere airfields, and time-critical missions across Africa, the Middle East, and beyond. Aircraft selection and operational approval rest with the licensed operator.</p>

    <h2>Why clients choose us</h2>
    <ul class="list-check">
      <li><strong>Speed.</strong> Quotes typically within 60 minutes; launch capability in as little as two hours when aircraft is positioned.</li>
      <li><strong>Reach.</strong> Routes across Africa and the Middle East — and connecting service further afield through partner networks.</li>
      <li><strong>Clarity.</strong> One coordinator, one set of timings, one written proposal. No confusing handoffs.</li>
      <li><strong>Care.</strong> Humanitarian and medevac coordination is core to what we do, not an afterthought.</li>
    </ul>

    <h2>How we are paid</h2>
    <p>HabeshaAir earns a coordination fee included in the operator quote. We do not charge clients to submit a request, receive a quote, or talk to a coordinator. If we cannot find a suitable option for your mission, we tell you — and we tell you why.</p>

    <h2>Get in touch</h2>
    <p>Email <a href="mailto:<?= e(cfg('app.email')) ?>"><?= e(cfg('app.email')) ?></a>, WhatsApp <a href="<?= e(whatsapp_link()) ?>"><?= e(cfg('app.whatsapp_display')) ?></a>, or use the <a href="/request.php">request form</a>. Operations are 24/7.</p>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
