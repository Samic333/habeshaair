<?php
require_once __DIR__ . '/includes/bootstrap.php';

$errors = flash_get('contact_errors', []);
$old    = flash_get('contact_old', []);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();

    if (honeypot_caught()) {
        redirect('/contact-success.php');
    }

    if (!rate_check('contact', (int)cfg('security.rate_form_per_hour', 5), 3600)) {
        flash_set('contact_errors', ['_form' => 'Too many submissions from this IP. Please try again in an hour.']);
        flash_set('contact_old', $_POST);
        redirect('/contact.php');
    }

    $v = new V($_POST);
    $v->required('full_name','Name')->maxLen('full_name',160,'Name');
    $v->required('email','Email')->email('email','Email')->maxLen('email',190,'Email');
    $v->optional('phone')->phone('phone','Phone')->maxLen('phone',40,'Phone');
    $v->required('subject','Subject')->maxLen('subject',200,'Subject');
    $v->required('message','Message')->maxLen('message',5000,'Message');
    if (empty($_POST['consent'])) $v->errors['consent'] = 'Please confirm you agree to the privacy policy.';

    if (!$v->ok()) {
        flash_set('contact_errors', $v->errors);
        flash_set('contact_old', $_POST);
        redirect('/contact.php');
    }

    $c = $v->clean;
    try {
        $stmt = db()->prepare(
            'INSERT INTO contact_messages (full_name, email, phone, subject, message, status, ip_address)
             VALUES (:name, :email, :phone, :subject, :message, "New", :ip)'
        );
        $stmt->execute([
            ':name'    => $c['full_name'],
            ':email'   => $c['email'],
            ':phone'   => $c['phone']   ?? null,
            ':subject' => $c['subject'],
            ':message' => $c['message'],
            ':ip'      => ip_to_binary(client_ip()),
        ]);
    } catch (Throwable $ex) {
        error_log('Contact insert failed: ' . $ex->getMessage());
        flash_set('contact_errors', ['_form' => 'We could not save your message. Please email us directly at ' . cfg('app.email') . '.']);
        flash_set('contact_old', $_POST);
        redirect('/contact.php');
    }

    $subject = 'New contact message: ' . $c['subject'];
    $body  = "From: {$c['full_name']} <{$c['email']}>\n";
    if (!empty($c['phone'])) $body .= "Phone: {$c['phone']}\n";
    $body .= "Subject: {$c['subject']}\n\n{$c['message']}\n";
    @send_admin_notification($subject, $body);

    redirect('/contact-success.php');
}

$page = [
    'title'       => 'Contact HabeshaAir — Charter Coordination 24/7',
    'description' => 'Reach HabeshaAir 24/7 for charter coordination. Email, WhatsApp, or send a message via the contact form.',
    'canonical'   => url('/contact.php'),
    'schema'      => 'contactpage',
    'active_nav'  => 'contact',
];
include __DIR__ . '/includes/header.php';

function err(string $f, array $errors): string {
    return isset($errors[$f]) ? '<p class="error-msg">' . e($errors[$f]) . '</p>' : '';
}
function cls(string $f, array $errors): string {
    return isset($errors[$f]) ? ' has-error' : '';
}
function old(string $f, array $old, $default = '') {
    return e($old[$f] ?? $default);
}
?>

<section class="hero" style="padding:3.5rem 0 4rem">
  <div class="container">
    <span class="hero-badge">Contact</span>
    <h1>Talk to a coordinator.</h1>
    <p class="lede">Email, WhatsApp, or message us below. Operations are 24/7 — emergency requests are answered around the clock.</p>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="grid grid-2" style="gap:2.5rem">
      <div>
        <h2>Direct contact</h2>
        <p><strong>Email</strong><br><a href="mailto:<?= e(cfg('app.email')) ?>"><?= e(cfg('app.email')) ?></a></p>
        <p><strong>WhatsApp</strong><br><a href="<?= e(whatsapp_link()) ?>" target="_blank" rel="noopener"><?= e(cfg('app.whatsapp_display')) ?></a></p>
        <p><strong>Hours</strong><br>24/7 operations center</p>
        <p><strong>Coverage</strong><br>Africa, the Middle East, and beyond — through certified operators worldwide.</p>

        <div class="alert alert-info" style="margin-top:1.5rem">
          <strong>Need a charter quote?</strong>
          <p style="margin:.35rem 0 0">Use the <a href="/request.php">charter request form</a> for the fastest response. Quotes typically returned within 60 minutes.</p>
        </div>
      </div>

      <div>
        <h2>Send a message</h2>

        <?php if (!empty($errors['_form'])): ?>
          <div class="alert alert-error"><?= e($errors['_form']) ?></div>
        <?php endif; ?>

        <form method="post" action="/contact.php" class="form-card" novalidate>
          <?= csrf_field() ?>
          <div class="honeypot" aria-hidden="true">
            <label>Leave blank <input type="text" name="_hp" tabindex="-1" autocomplete="off"></label>
          </div>

          <div class="form-grid">
            <div class="form-group">
              <label for="c_name">Full name *</label>
              <input id="c_name" type="text" name="full_name" value="<?= old('full_name', $old) ?>" class="<?= cls('full_name', $errors) ?>" maxlength="160" required>
              <?= err('full_name', $errors) ?>
            </div>
            <div class="form-group">
              <label for="c_email">Email *</label>
              <input id="c_email" type="email" name="email" value="<?= old('email', $old) ?>" class="<?= cls('email', $errors) ?>" maxlength="190" required>
              <?= err('email', $errors) ?>
            </div>
            <div class="form-group">
              <label for="c_phone">Phone (optional)</label>
              <input id="c_phone" type="tel" name="phone" value="<?= old('phone', $old) ?>" class="<?= cls('phone', $errors) ?>" maxlength="40" placeholder="+254 7XX XXX XXX">
              <?= err('phone', $errors) ?>
            </div>
            <div class="form-group">
              <label for="c_subject">Subject *</label>
              <input id="c_subject" type="text" name="subject" value="<?= old('subject', $old) ?>" class="<?= cls('subject', $errors) ?>" maxlength="200" required>
              <?= err('subject', $errors) ?>
            </div>
            <div class="form-group">
              <label for="c_message">Message *</label>
              <textarea id="c_message" name="message" class="<?= cls('message', $errors) ?>" maxlength="5000" required><?= old('message', $old) ?></textarea>
              <?= err('message', $errors) ?>
            </div>
            <div class="form-group">
              <label class="checkbox-row">
                <input type="checkbox" name="consent" value="1"<?= !empty($old['consent']) ? ' checked' : '' ?>>
                <span>I agree to the <a href="/privacy.php">Privacy Policy</a>.</span>
              </label>
              <?= err('consent', $errors) ?>
            </div>
            <div class="form-actions">
              <button type="submit" class="btn btn-gold">Send message</button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
