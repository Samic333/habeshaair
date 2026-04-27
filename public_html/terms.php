<?php
require_once __DIR__ . '/includes/bootstrap.php';
$page = [
    'title'       => 'Terms & Disclaimer — HabeshAir',
    'description' => 'Terms of use and disclaimer for the HabeshAir charter coordination platform.',
    'canonical'   => url('/terms.php'),
    'schema'      => 'organization',
];
include __DIR__ . '/includes/header.php';
?>
<section class="section">
  <div class="container prose">
    <h1>Terms &amp; Disclaimer</h1>
    <p><em>Last updated: <?= date('F Y') ?>.</em></p>

    <h2>What HabeshAir is</h2>
    <p>HabeshAir is a charter coordination and brokerage platform. We coordinate charter flight requests on behalf of clients and may work with third-party aircraft operators. HabeshAir does not itself operate aircraft.</p>

    <h2>Operator approval and availability</h2>
    <p>Final aircraft availability, pricing, and operational approval depend on the licensed operator, applicable regulations, route, airport permissions, ground handling, weather, and safety considerations. Quotes are indicative until confirmed in writing by the selected operator and acknowledged by you.</p>

    <h2>No warranty</h2>
    <p>HabeshAir coordinates options and proposes solutions; the operator confirms and performs the flight. While we work with operators we know and trust, we do not warrant operator performance and are not liable for operator acts or omissions.</p>

    <h2>Use of this site</h2>
    <p>Submitting false, misleading, or fraudulent information through any HabeshAir form is prohibited. We may decline service at our discretion, particularly for requests that appear unsafe, illegal, or misrepresented.</p>

    <h2>Pricing and payment</h2>
    <p>Quotes are issued in writing and include operator costs and HabeshAir's coordination fee. Payment terms are stated in the operator agreement and any invoice we issue. Cancellation, rescheduling, and refund terms follow the operator's policy unless otherwise agreed.</p>

    <h2>Limitation of liability</h2>
    <p>To the fullest extent permitted by law, HabeshAir's liability is limited to the coordination fees paid for the specific flight in question. We are not responsible for indirect, incidental, or consequential damages arising from operator-side events including delay, cancellation, weather, regulatory restrictions, or technical issues.</p>

    <h2>Governing law</h2>
    <p>These terms are governed by the laws of the jurisdiction in which HabeshAir is established, without regard to conflict-of-law principles.</p>

    <h2>Contact</h2>
    <p>Questions about these terms: <a href="mailto:<?= e(cfg('app.email')) ?>"><?= e(cfg('app.email')) ?></a>.</p>
  </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
