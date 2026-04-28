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
    <p>&copy; <?= date('Y') ?> <?= e(cfg('app.company')) ?>. Charter requests are coordinated through a vetted global network of licensed charter operators and airlines.</p>
    <ul class="footer-meta">
      <li><a href="/privacy.php">Privacy</a></li>
      <li><a href="/terms.php">Terms</a></li>
    </ul>
  </div>
</footer>

<a href="<?= e(whatsapp_link('Hello HabeshAir, I\'d like a charter quote.')) ?>"
   class="wa-float" target="_blank" rel="noopener"
   aria-label="Chat on WhatsApp">
  <svg viewBox="0 0 24 24" width="28" height="28" fill="currentColor" aria-hidden="true">
    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/>
    <path d="M12 0C5.373 0 0 5.373 0 12c0 2.125.558 4.122 1.534 5.856L0 24l6.335-1.524A11.945 11.945 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 21.818a9.818 9.818 0 01-5.007-1.373l-.36-.214-3.727.977.994-3.634-.234-.374A9.817 9.817 0 012.182 12C2.182 6.58 6.58 2.182 12 2.182S21.818 6.58 21.818 12 17.42 21.818 12 21.818z"/>
  </svg>
</a>

<script src="/assets/js/main.js" defer></script>
<?php if (!empty($page['scripts'])) foreach ($page['scripts'] as $s): ?>
<script src="<?= e($s) ?>" defer></script>
<?php endforeach; ?>
</body>
</html>
