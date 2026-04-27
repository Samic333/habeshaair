</main>

<section class="contact-strip">
  <div class="container contact-strip-row">
    <div>
      <p class="eyebrow">Need to fly soon?</p>
      <h2>Charter on demand. Quotes within minutes.</h2>
    </div>
    <div class="contact-strip-actions">
      <a href="/request.php" class="btn btn-gold">Request a Charter</a>
      <a href="<?= e(whatsapp_link('Hello HabeshAir, I would like a charter quote.')) ?>" class="btn btn-outline-light" target="_blank" rel="noopener">
        WhatsApp <?= e(cfg('app.whatsapp_display')) ?>
      </a>
    </div>
  </div>
</section>

<footer class="site-footer">
  <div class="container footer-grid">
    <div>
      <a href="/" class="brand brand-light">
        <span class="brand-mark" aria-hidden="true">
          <svg viewBox="0 0 32 32" width="28" height="28"><path d="M16 3 L20 14 L31 16 L20 18 L16 29 L12 18 L1 16 L12 14 Z" fill="currentColor"/></svg>
        </span>
        <span class="brand-name">Habesh<span class="brand-accent">Air</span></span>
      </a>
      <p class="footer-tagline">Premium air charter coordination across Africa, the Middle East, and beyond.</p>
    </div>
    <div>
      <h4>Services</h4>
      <ul class="footer-links">
        <li><a href="/request.php?type=VIP">VIP charter</a></li>
        <li><a href="/request.php?type=Cargo">Cargo</a></li>
        <li><a href="/request.php?type=Humanitarian">Humanitarian</a></li>
        <li><a href="/request.php?type=Emergency-Medevac">Emergency &amp; medevac</a></li>
        <li><a href="/request.php?type=Group-Event">Group &amp; event</a></li>
      </ul>
    </div>
    <div>
      <h4>Company</h4>
      <ul class="footer-links">
        <li><a href="/about.php">About</a></li>
        <li><a href="/how-it-works.php">How it works</a></li>
        <li><a href="/faq.php">FAQ</a></li>
        <li><a href="/contact.php">Contact</a></li>
      </ul>
    </div>
    <div>
      <h4>Contact</h4>
      <ul class="footer-links">
        <li><a href="mailto:<?= e(cfg('app.email')) ?>"><?= e(cfg('app.email')) ?></a></li>
        <li><a href="<?= e(whatsapp_link()) ?>" target="_blank" rel="noopener">WhatsApp <?= e(cfg('app.whatsapp_display')) ?></a></li>
        <li>24/7 operations</li>
      </ul>
    </div>
  </div>
  <div class="container footer-bottom">
    <p>&copy; <?= date('Y') ?> <?= e(cfg('app.company')) ?>. Charter requests are coordinated through FAA Certified Part 135 air carriers or foreign equivalents.</p>
    <ul class="footer-meta">
      <li><a href="/privacy.php">Privacy</a></li>
      <li><a href="/terms.php">Terms</a></li>
    </ul>
  </div>
</footer>

<script src="/assets/js/main.js" defer></script>
<?php if (!empty($page['scripts'])) foreach ($page['scripts'] as $s): ?>
<script src="<?= e($s) ?>" defer></script>
<?php endforeach; ?>
</body>
</html>
