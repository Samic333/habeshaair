<?php
require_once __DIR__ . '/includes/bootstrap.php';

$page = [
    'title'       => 'How It Works — Charter Request to Wheels-Up | HabeshAir',
    'description' => 'How HabeshAir coordinates a charter from your first message to engines on. Four clear steps, with timelines.',
    'canonical'   => url('/how-it-works.php'),
    'schema'      => 'organization',
    'active_nav'  => 'how',
];
include __DIR__ . '/includes/header.php';
?>

<section class="hero" style="padding:3.5rem 0 4rem">
  <div class="container">
    <span class="hero-badge">How it works</span>
    <h1>From request to wheels-up.</h1>
    <p class="lede">A clear four-step path. We respond fast, source thoroughly, and stay with you until your flight is complete.</p>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="prose">
      <div class="step"><div class="step-num">1</div><div>
        <h3>You submit a request</h3>
        <p>Use the request form, WhatsApp, or email. The more detail (route, dates, payload, urgency), the faster we move. For emergency or medevac flights, mark urgency as <em>Emergency</em>; that routes straight to the duty coordinator.</p>
      </div></div>

      <div class="step"><div class="step-num">2</div><div>
        <h3>We review &amp; source</h3>
        <p>A coordinator validates feasibility — airport permissions, aircraft type for the payload, route legality — and sources options through our vetted global network of licensed charter operators and airlines. Together our partners give us access to over 5,000 aircraft worldwide.</p>
      </div></div>

      <div class="step"><div class="step-num">3</div><div>
        <h3>You receive a proposal</h3>
        <p>A written quote: aircraft type, pricing, departure window, operational notes, and any special considerations (overflight permissions, ground handling, etc.). Quotes are typically returned within 60 minutes during business hours.</p>
      </div></div>

      <div class="step"><div class="step-num">4</div><div>
        <h3>Confirmation &amp; flight</h3>
        <p>On your go-ahead, we contract with the operator on your behalf, share final timings, and stay on the line until safe arrival. Multi-leg and rotational flights get the same level of coordination at every stop.</p>
      </div></div>
    </div>

    <div style="margin-top:3rem; text-align:center">
      <a href="/request.php" class="btn btn-gold btn-lg">Request a charter</a>
    </div>
  </div>
</section>

<section class="section section-band-light">
  <div class="container prose">
    <h2>Timing — what to expect</h2>
    <ul class="list-check">
      <li><strong>Quote:</strong> typically within 60 minutes of request during business hours.</li>
      <li><strong>Launch capability:</strong> as little as 2 hours when an aircraft is well-positioned and permissions are in place.</li>
      <li><strong>Emergency / medevac:</strong> 24/7 operations; we mobilize immediately and chase the fastest viable path to engines on.</li>
      <li><strong>Complex routes / approvals:</strong> some destinations require overflight or landing permits with longer lead times — we'll tell you up front.</li>
    </ul>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
