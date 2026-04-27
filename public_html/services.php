<?php
require_once __DIR__ . '/includes/bootstrap.php';

$page = [
    'title'       => 'Charter Services — VIP, Cargo, Humanitarian & Medevac | HabeshAir',
    'description' => 'Air charter services across Africa and the Middle East: VIP private travel, cargo, humanitarian relief, emergency medevac, and group flights.',
    'canonical'   => url('/services.php'),
    'schema'      => 'service',
    'active_nav'  => 'services',
];
include __DIR__ . '/includes/header.php';
?>

<section class="hero" style="padding:3.5rem 0 4rem">
  <div class="container">
    <span class="hero-badge">Our services</span>
    <h1>Charter solutions, end to end.</h1>
    <p class="lede">We coordinate the right aircraft for the route, the cargo, and the schedule — through certified operators across Africa, the Middle East, and beyond.</p>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="grid grid-2" style="gap:2rem">
      <article class="card">
        <h2>VIP &amp; Private Charter</h2>
        <p>Discreet, comfortable travel for executives, dignitaries, and families. We coordinate aircraft sized for your route and party — light jets to executive widebodies — with attention to airport access, ground handling, and on-board service.</p>
        <ul class="list-check">
          <li>Light, mid, super-mid, and heavy jets</li>
          <li>Direct routings on schedule</li>
          <li>Discreet handling and ground transport on request</li>
        </ul>
        <a href="/request.php?type=VIP" class="btn btn-navy">Request VIP charter</a>
      </article>

      <article class="card">
        <h2>Cargo Logistics</h2>
        <p>From general freight to perishable, dangerous goods, and live-animal shipments. We help match cargo class to aircraft type, manage AOG and time-critical hauls, and coordinate operator paperwork.</p>
        <ul class="list-check">
          <li>General, perishable, dangerous goods, live animals</li>
          <li>Volume-to-weight assessment and door-size checks</li>
          <li>Routings to remote and unprepared airfields</li>
        </ul>
        <a href="/request.php?type=Cargo" class="btn btn-navy">Request cargo charter</a>
      </article>

      <article class="card">
        <h2>Humanitarian Relief</h2>
        <p>Aid logistics for NGOs, UN agencies, and humanitarian operators. We work into austere airfields across Africa with operators experienced in unpaved strips, short fields, and extended operations.</p>
        <ul class="list-check">
          <li>Caravans, Dash-8, ATR, Twin Otter, and larger lift</li>
          <li>Coordination with field teams and ground handling</li>
          <li>Repeated rotations and resupply windows</li>
        </ul>
        <a href="/request.php?type=Humanitarian" class="btn btn-navy">Request humanitarian flight</a>
      </article>

      <article class="card">
        <h2>Emergency &amp; Medevac</h2>
        <p>Time-critical medical evacuation and emergency lift coordination. We mobilize on short notice with operators capable of patient transport configurations and the right routing for the case.</p>
        <ul class="list-check">
          <li>Stretcher-equipped and ICU-capable aircraft</li>
          <li>Repatriation and inter-hospital transfers</li>
          <li>24/7 operations — fastest route to engines on</li>
        </ul>
        <a href="/request.php?type=Emergency-Medevac" class="btn btn-navy">Request medevac</a>
      </article>

      <article class="card">
        <h2>Group &amp; Event Charter</h2>
        <p>Sports teams, conferences, weddings, and special events. Group lift on a single aircraft beats juggling commercial schedules — and brings everyone in together.</p>
        <ul class="list-check">
          <li>Regional turboprops and narrow-body jets</li>
          <li>One arrival window, one departure window</li>
          <li>Bulk baggage and special cargo on board</li>
        </ul>
        <a href="/request.php?type=Group-Event" class="btn btn-navy">Request group charter</a>
      </article>

      <article class="card">
        <h2>Tailored &amp; Multi-leg</h2>
        <p>Connecting flights, repositioning, multi-stop tours, regional Africa and Middle East solutions. Tell us the destinations and the dates — we'll build the route.</p>
        <ul class="list-check">
          <li>Multi-day and multi-stop routings</li>
          <li>Pricing across legs, not per leg</li>
          <li>One coordinator from request to landing</li>
        </ul>
        <a href="/request.php" class="btn btn-navy">Request a custom route</a>
      </article>
    </div>
  </div>
</section>

<section class="section section-band-light">
  <div class="container prose text-center">
    <p class="eyebrow">A note on availability</p>
    <p>Final aircraft availability, pricing, and operational approval depend on the licensed operator, regulatory permissions, route, airport handling, and safety considerations. HabeshAir coordinates requests and proposes options; the operator confirms the flight.</p>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
