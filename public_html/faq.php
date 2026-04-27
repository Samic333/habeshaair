<?php
require_once __DIR__ . '/includes/bootstrap.php';

$faq = [
    ['q' => 'Does HabeshaAir own aircraft?',
     'a' => 'No. HabeshaAir is a charter coordination and brokerage platform. We arrange flights on behalf of clients with FAA Certified Part 135 air carriers or foreign equivalents. Aircraft selection and operational approval rest with the licensed operator.'],
    ['q' => 'How fast can I get a quote?',
     'a' => 'Quotes are typically returned within 60 minutes during business hours. Emergency and medevac requests are handled around the clock and prioritized.'],
    ['q' => 'Can I request cargo flights?',
     'a' => 'Yes. We coordinate general, perishable, dangerous-goods, and live-animal cargo. We help match cargo class to aircraft type and route, and we coordinate operator paperwork and ground handling.'],
    ['q' => 'Can I request emergency or humanitarian flights?',
     'a' => 'Yes. Emergency, medevac, and humanitarian missions are core to what we do. Mark urgency as Emergency on the request form, or message WhatsApp directly for immediate handling.'],
    ['q' => 'Which regions do you serve?',
     'a' => 'East Africa, the wider African continent, and the Middle East primarily — with connecting service to Europe and beyond through partner networks. Through aviation partners, we have access to over 5,000 aircraft worldwide.'],
    ['q' => 'What information is needed for a charter quote?',
     'a' => 'At minimum: route (departure / destination), preferred date and time, payload (passengers or cargo description), and your contact details. The more you can share — return date, special requirements, urgency — the faster we can move.'],
    ['q' => 'Is the request binding?',
     'a' => 'No. Submitting a request does not commit you to anything. You receive a written proposal first; flight only proceeds on your written confirmation.'],
    ['q' => 'How are operators selected?',
     'a' => 'We match operators by route capability, aircraft type, payload, regulatory permissions, safety record, and pricing. We work with operators we know and trust, particularly for African and humanitarian routes.'],
    ['q' => 'How do you handle pricing?',
     'a' => 'Quotes are all-inclusive of operator costs and our coordination fee. There is no charge to submit a request, receive a quote, or talk to a coordinator.'],
    ['q' => 'What if my flight is cancelled or delayed?',
     'a' => 'Operator-side cancellations and delays are managed by the operator under their terms. We help coordinate alternatives and stay with you until the situation is resolved. Force majeure (weather, regulatory, technical) can affect any aviation operation; we work to minimize impact.'],
];

$page = [
    'title'       => 'Charter FAQ — Common Questions | HabeshaAir',
    'description' => 'Frequently asked questions about HabeshaAir charter coordination: quotes, regions, aircraft, emergency flights, and pricing.',
    'canonical'   => url('/faq.php'),
    'schema'      => 'faqpage',
    'active_nav'  => 'faq',
    'faq'         => $faq,
];
include __DIR__ . '/includes/header.php';
?>

<section class="hero" style="padding:3.5rem 0 4rem">
  <div class="container">
    <span class="hero-badge">FAQ</span>
    <h1>Frequently asked questions</h1>
    <p class="lede">Common questions about HabeshaAir, our charter coordination process, and what to expect.</p>
  </div>
</section>

<section class="section">
  <div class="container prose">
    <dl class="faq">
      <?php foreach ($faq as $qa): ?>
        <dt><?= e($qa['q']) ?></dt>
        <dd><?= e($qa['a']) ?></dd>
      <?php endforeach; ?>
    </dl>

    <div class="alert alert-info" style="margin-top:2rem">
      Don't see your question? Email <a href="mailto:<?= e(cfg('app.email')) ?>"><?= e(cfg('app.email')) ?></a> or message us on <a href="<?= e(whatsapp_link()) ?>">WhatsApp</a>.
    </div>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
