<?php
require_once __DIR__ . '/includes/bootstrap.php';

$page = [
    'title'       => 'HabeshaAir — Premium Air Charter Africa & Beyond',
    'description' => 'HabeshaAir coordinates premium VIP, cargo, humanitarian, and emergency charter flights across Africa, the Middle East, and beyond. Quotes within 60 minutes.',
    'canonical'   => url('/'),
    'schema'      => 'home',
    'active_nav'  => 'home',
];
include __DIR__ . '/includes/header.php';
?>

<section class="hero">
  <div class="container">
    <span class="hero-badge">Premium Air Charter Airline</span>
    <h1>Premium Air Charter <br>Africa &amp; Beyond</h1>
    <p class="lede">Reliable private aviation solutions for VIP, Cargo, and Humanitarian missions. We respond within 60 minutes to get you airborne.</p>
    <div class="hero-cta">
      <a href="/request.php" class="btn btn-gold btn-lg">Request a Charter</a>
      <a href="/services.php" class="btn btn-outline-light btn-lg">View Services</a>
    </div>
    <div class="hero-trust">
      <span>Private</span><span>Cargo</span><span>Humanitarian</span><span>Emergency Support</span>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="text-center prose" style="margin-bottom:2.5rem">
      <p class="eyebrow">What we do</p>
      <h2>Charter solutions for time-critical, complex, and high-value missions</h2>
      <p class="lede">HabeshaAir coordinates flights with FAA Certified Part 135 air carriers and foreign equivalents. Through trusted aviation partners, we offer access to over 5,000 aircraft worldwide.</p>
    </div>

    <div class="grid grid-3">
      <article class="card service-card">
        <div class="icon">
          <svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor"><path d="M2 12l8-2 4-7 1 0 1 7 6 1v2l-6 1-1 7-1 0-4-7-8-2v-1z"/></svg>
        </div>
        <h3>VIP &amp; Private</h3>
        <p>Discreet, comfortable, on-demand private travel for executives, families, and dignitaries.</p>
        <a href="/request.php?type=VIP" class="card-cta">Request VIP charter →</a>
      </article>

      <article class="card service-card">
        <div class="icon">
          <svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor"><path d="M3 7h13l5 4v6h-2a2 2 0 1 1-4 0H10a2 2 0 1 1-4 0H3V7zm15 5h2.6L18 9.5V12z"/></svg>
        </div>
        <h3>Cargo Logistics</h3>
        <p>General, perishable, dangerous goods, and live animal cargo, lifted on the right aircraft for the route.</p>
        <a href="/request.php?type=Cargo" class="card-cta">Request cargo charter →</a>
      </article>

      <article class="card service-card">
        <div class="icon">
          <svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor"><path d="M11 2h2v6h6v2h-6v12h-2V10H5V8h6V2z"/></svg>
        </div>
        <h3>Humanitarian Relief</h3>
        <p>Aid logistics for NGOs and operators flying into remote and austere airfields across Africa.</p>
        <a href="/request.php?type=Humanitarian" class="card-cta">Request humanitarian flight →</a>
      </article>

      <article class="card service-card">
        <div class="icon">
          <svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor"><path d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2zm1 15h-2v-2h2zm0-4h-2V7h2z"/></svg>
        </div>
        <h3>Emergency &amp; Medevac</h3>
        <p>Time-critical medical evacuation and emergency lift coordination, available 24/7/365.</p>
        <a href="/request.php?type=Emergency-Medevac" class="card-cta">Request medevac →</a>
      </article>

      <article class="card service-card">
        <div class="icon">
          <svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor"><path d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4zm0 2c-3 0-9 1.5-9 4.5V21h18v-2.5c0-3-6-4.5-9-4.5z"/></svg>
        </div>
        <h3>Group &amp; Event</h3>
        <p>Sports teams, corporate retreats, weddings, and special-event lift for groups large and small.</p>
        <a href="/request.php?type=Group-Event" class="card-cta">Request group charter →</a>
      </article>

      <article class="card service-card">
        <div class="icon">
          <svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor"><path d="M12 1l3 6 7 1-5 5 1 7-6-3-6 3 1-7-5-5 7-1z"/></svg>
        </div>
        <h3>Tailored Routes</h3>
        <p>Africa, the Middle East, and beyond — multi-leg, repositioning, and connecting service requests welcome.</p>
        <a href="/request.php" class="card-cta">Plan a custom route →</a>
      </article>
    </div>
  </div>
</section>

<section class="section section-band-navy">
  <div class="container">
    <div class="grid grid-2" style="align-items:center">
      <div>
        <p class="eyebrow">Why HabeshaAir</p>
        <h2>Speed and clarity, every step.</h2>
        <p class="lede">From your first message to engines on, we keep the request moving. Our network gives us options on short notice; our process keeps you informed.</p>
        <ul class="list-check">
          <li>Quotes typically within 60 minutes of request</li>
          <li>Launch capability in as little as 2 hours when aircraft is positioned</li>
          <li>Coordinators monitor every leg until completion</li>
          <li>WhatsApp, email, or phone — your choice</li>
        </ul>
      </div>
      <div class="metric-row">
        <div class="metric"><div class="num">5,000+</div><div class="lbl">Aircraft accessible through partners</div></div>
        <div class="metric"><div class="num">60 min</div><div class="lbl">Typical response time</div></div>
        <div class="metric"><div class="num">24/7</div><div class="lbl">Operations center</div></div>
        <div class="metric"><div class="num">Africa</div><div class="lbl">Middle East &amp; beyond</div></div>
      </div>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="text-center prose" style="margin-bottom:2.5rem">
      <p class="eyebrow">How it works</p>
      <h2>From request to wheels-up</h2>
    </div>
    <div class="grid grid-2">
      <div>
        <div class="step"><div class="step-num">1</div><div><h3>Submit a request</h3><p>Tell us route, dates, payload, and preferences. Use the request form, WhatsApp, or email.</p></div></div>
        <div class="step"><div class="step-num">2</div><div><h3>Review &amp; sourcing</h3><p>We assess feasibility, source aircraft options through certified operators, and confirm permissions.</p></div></div>
      </div>
      <div>
        <div class="step"><div class="step-num">3</div><div><h3>Proposal &amp; quote</h3><p>You receive a clear proposal: aircraft, pricing, timing, and any operational considerations.</p></div></div>
        <div class="step"><div class="step-num">4</div><div><h3>Coordination &amp; flight</h3><p>We coordinate with the operator and stay with you through to safe arrival.</p></div></div>
      </div>
    </div>
    <div class="text-center" style="margin-top:2rem">
      <a href="/how-it-works.php" class="btn btn-outline">See full process</a>
    </div>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
