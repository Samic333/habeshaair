<?php
require_once __DIR__ . '/includes/bootstrap.php';
$page = [
    'title'       => 'Privacy Policy — HabeshAir',
    'description' => 'How HabeshAir collects, uses, and protects information you provide when requesting a charter quote.',
    'canonical'   => url('/privacy.php'),
    'schema'      => 'organization',
];
include __DIR__ . '/includes/header.php';
?>
<section class="section">
  <div class="container prose">
    <h1>Privacy Policy</h1>
    <p><em>Last updated: <?= date('F Y') ?>.</em></p>

    <h2>What we collect</h2>
    <p>When you request a charter, contact us, or use this site, we collect: your name, email, phone, organization (if provided), trip details (route, dates, payload, special requirements), preferred contact method, message content, and basic technical data (IP address, user agent, timestamps) for security and rate-limiting.</p>

    <h2>How we use it</h2>
    <ul class="list-check">
      <li>To respond to your charter request and source aircraft options.</li>
      <li>To coordinate the flight with the selected operator on your behalf.</li>
      <li>To communicate with you about your request via your preferred contact method.</li>
      <li>To prevent abuse and fraud (rate-limiting, anti-spam).</li>
    </ul>

    <h2>Sharing</h2>
    <p>To deliver a charter, we share necessary details (route, dates, passenger/cargo information, contact details) with the licensed charter operator or airline selected for your flight. We do not sell or share your personal information for marketing purposes.</p>

    <h2>Retention</h2>
    <p>Charter request records are retained for operational, regulatory, and accounting purposes. You may request deletion of your records at any time by emailing <a href="mailto:<?= e(cfg('app.email')) ?>"><?= e(cfg('app.email')) ?></a>; we will action requests subject to legal retention requirements.</p>

    <h2>Security</h2>
    <p>Data is transmitted over HTTPS and stored on access-controlled servers. We use industry-standard practices including hashed administrative credentials, prepared database statements, and CSRF protections.</p>

    <h2>Your rights</h2>
    <p>You may request access to, correction of, or deletion of your personal information. Contact <a href="mailto:<?= e(cfg('app.email')) ?>"><?= e(cfg('app.email')) ?></a>.</p>

    <h2>Cookies</h2>
    <p>We use a single session cookie to keep your form submission state and protect against cross-site request forgery. We do not use third-party advertising or tracking cookies.</p>

    <h2>Contact</h2>
    <p>Questions about this policy: <a href="mailto:<?= e(cfg('app.email')) ?>"><?= e(cfg('app.email')) ?></a>.</p>
  </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
