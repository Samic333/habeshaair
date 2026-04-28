<?php
require_once __DIR__ . '/includes/bootstrap.php';

const SERVICE_TYPES = ['VIP','Cargo','Humanitarian','Emergency-Medevac','Group-Event'];
const TRIP_TYPES    = ['One-way','Round-trip','Multi-leg'];
const TIME_PREFS    = ['Any','Morning','Afternoon','Evening'];
const CARGO_TYPES   = ['General','Dangerous Goods','Live Animals','Perishable'];
const URGENCY       = ['Flexible','72h','24h','Emergency'];
const CONTACT_METH  = ['WhatsApp','Email','Phone'];

$errors = flash_get('request_errors', []);
$old    = flash_get('request_old', []);

// Preselect service type from ?type=
$preselect = $_GET['type'] ?? null;
if ($preselect && !in_array($preselect, SERVICE_TYPES, true)) $preselect = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();

    if (honeypot_caught()) {
        // Silently treat as success to deter bots
        redirect('/request-success.php?ref=HA-OK');
    }

    if (!rate_check('request', (int)cfg('security.rate_form_per_hour', 5), 3600)) {
        flash_set('request_errors', ['_form' => 'Too many requests from this IP. Please try again in an hour or contact us directly.']);
        flash_set('request_old', $_POST);
        redirect('/request.php');
    }

    $v = new V($_POST);
    $v->required('service_type','Service type')->in('service_type', SERVICE_TYPES,'Service type');
    $v->required('trip_type','Trip type')->in('trip_type', TRIP_TYPES,'Trip type');
    $v->required('origin','Departure')->maxLen('origin',160,'Departure');
    $v->required('destination','Destination')->maxLen('destination',160,'Destination');
    $v->required('travel_date','Preferred date')->date('travel_date','Preferred date');
    $v->optional('return_date')->date('return_date','Return date');
    $v->optional('time_pref')->in('time_pref', TIME_PREFS,'Time preference');
    $v->optional('passengers')->intRange('passengers',1,300,'Passengers');
    $v->optional('approx_weight_kg')->intRange('approx_weight_kg',0,50000,'Approx weight (kg)');
    $v->optional('cargo_type')->in('cargo_type', CARGO_TYPES,'Cargo type');
    $v->required('urgency_level','Urgency')->in('urgency_level', URGENCY,'Urgency');
    $v->optional('budget_range')->maxLen('budget_range',80,'Budget range');
    $v->optional('special_requirements')->maxLen('special_requirements',2000,'Special requirements');
    $v->required('full_name','Full name')->maxLen('full_name',160,'Full name');
    $v->required('email','Email')->email('email','Email')->maxLen('email',190,'Email');
    $v->required('phone','Phone')->phone('phone','Phone')->maxLen('phone',40,'Phone');
    $v->optional('company')->maxLen('company',160,'Company');
    $v->required('contact_method','Preferred contact method')->in('contact_method', CONTACT_METH,'Contact method');
    if (empty($_POST['consent'])) $v->errors['consent'] = 'Please confirm you agree to the privacy policy.';
    $v->bool('consent');

    if (!$v->ok()) {
        flash_set('request_errors', $v->errors);
        flash_set('request_old', $_POST);
        redirect('/request.php');
    }

    $c = $v->clean;
    $ref = reference_code();
    $isUrgent = in_array($c['urgency_level'] ?? '', ['24h','Emergency'], true) ? 1 : 0;

    try {
        $stmt = db()->prepare(
            'INSERT INTO charter_requests
              (reference_code, service_type, trip_type, origin, destination,
               travel_date, return_date, time_pref, passengers, approx_weight_kg,
               cargo_type, urgency_level, budget_range, special_requirements,
               full_name, email, phone, company, contact_method, consent,
               status, is_urgent, ip_address, user_agent)
             VALUES
              (:ref, :svc, :trip, :org, :dst,
               :tdate, :rdate, :tp, :pax, :wt,
               :ctype, :urg, :budget, :notes,
               :name, :email, :phone, :company, :cm, :consent,
               :status, :urgent, :ip, :ua)'
        );
        $stmt->execute([
            ':ref'    => $ref,
            ':svc'    => $c['service_type'],
            ':trip'   => $c['trip_type'],
            ':org'    => $c['origin'],
            ':dst'    => $c['destination'],
            ':tdate'  => $c['travel_date'] ?? null,
            ':rdate'  => $c['return_date'] ?? null,
            ':tp'     => $c['time_pref']   ?? 'Any',
            ':pax'    => $c['passengers']  ?? null,
            ':wt'     => $c['approx_weight_kg'] ?? null,
            ':ctype'  => $c['cargo_type']  ?? null,
            ':urg'    => $c['urgency_level'],
            ':budget' => $c['budget_range'] ?? null,
            ':notes'  => $c['special_requirements'] ?? null,
            ':name'   => $c['full_name'],
            ':email'  => $c['email'],
            ':phone'  => $c['phone'],
            ':company'=> $c['company'] ?? null,
            ':cm'     => $c['contact_method'],
            ':consent'=> $c['consent'] ?? 0,
            ':status' => 'New',
            ':urgent' => $isUrgent,
            ':ip'     => ip_to_binary(client_ip()),
            ':ua'     => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
        ]);
    } catch (Throwable $ex) {
        error_log('Charter request insert failed: ' . $ex->getMessage());
        flash_set('request_errors', ['_form' => 'We could not save your request. Please try again or contact us directly.']);
        flash_set('request_old', $_POST);
        redirect('/request.php');
    }

    // Email notification (non-blocking)
    $subject = sprintf('New Charter Request: %s — %s to %s%s',
        $c['service_type'], $c['origin'], $c['destination'], $isUrgent ? ' — URGENT' : '');
    $body  = "New charter request received.\n\n";
    $body .= "Reference: $ref\n";
    $body .= "Service: {$c['service_type']}  |  Trip: {$c['trip_type']}  |  Urgency: {$c['urgency_level']}\n";
    $body .= "Route: {$c['origin']} → {$c['destination']}\n";
    $body .= "Date: " . ($c['travel_date'] ?? '-') . "  Return: " . ($c['return_date'] ?? '-') . "  Time: " . ($c['time_pref'] ?? 'Any') . "\n";
    if (isset($c['passengers']))       $body .= "Passengers: {$c['passengers']}\n";
    if (isset($c['approx_weight_kg'])) $body .= "Approx weight (kg): {$c['approx_weight_kg']}\n";
    if (isset($c['cargo_type']))       $body .= "Cargo type: {$c['cargo_type']}\n";
    if (!empty($c['budget_range']))    $body .= "Budget: {$c['budget_range']}\n";
    if (!empty($c['special_requirements'])) $body .= "Notes: {$c['special_requirements']}\n";
    $body .= "\nContact:\n  {$c['full_name']}";
    if (!empty($c['company'])) $body .= " ({$c['company']})";
    $body .= "\n  {$c['email']}\n  {$c['phone']}\n  Prefers: {$c['contact_method']}\n";
    $body .= "\nView: " . url('/admin/request-view.php?id=' . db()->lastInsertId()) . "\n";

    @send_admin_notification($subject, $body);

    // Confirmation email to requester
    $confSubject = "Charter request {$ref} received — HabeshAir";
    $confBody  = "Dear {$c['full_name']},\n\n";
    $confBody .= "Your charter request has been received.\n\n";
    $confBody .= "Reference: {$ref}\n";
    $confBody .= "Route:     {$c['origin']} \xE2\x86\x92 {$c['destination']}\n";
    $confBody .= "Service:   {$c['service_type']}\n";
    $confBody .= "Date:      " . ($c['travel_date'] ?? '-') . "\n";
    $confBody .= "Urgency:   {$c['urgency_level']}\n\n";
    $confBody .= "Our team will be in touch within 60 minutes.\n";
    $confBody .= "For urgent requests, reach us directly:\n";
    $confBody .= "  Email:    " . cfg('app.email', 'info@habeshair.com') . "\n";
    $confBody .= "  WhatsApp: " . cfg('app.whatsapp_display', '') . "\n\n";
    $confBody .= "Thank you for choosing HabeshAir.\n";
    @send_email($c['email'], $confSubject, $confBody);

    // Mirror to Google Sheets (non-blocking)
    @sheet_log('request', [
        'Timestamp'             => date('Y-m-d H:i:s'),
        'Reference'             => $ref,
        'Service'               => $c['service_type'],
        'Trip'                  => $c['trip_type'],
        'Origin'                => $c['origin'],
        'Destination'           => $c['destination'],
        'Travel Date'           => $c['travel_date'] ?? '',
        'Return Date'           => $c['return_date'] ?? '',
        'Time Pref'             => $c['time_pref'] ?? 'Any',
        'Passengers'            => $c['passengers'] ?? '',
        'Weight (kg)'           => $c['approx_weight_kg'] ?? '',
        'Cargo Type'            => $c['cargo_type'] ?? '',
        'Urgency'               => $c['urgency_level'],
        'Budget'                => $c['budget_range'] ?? '',
        'Special Requirements'  => $c['special_requirements'] ?? '',
        'Full Name'             => $c['full_name'],
        'Email'                 => $c['email'],
        'Phone'                 => $c['phone'],
        'Company'               => $c['company'] ?? '',
        'Contact Method'        => $c['contact_method'],
        'IP'                    => client_ip() ?? '',
        'User Agent'            => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
    ]);

    redirect('/request-success.php?ref=' . urlencode($ref));
}

$page = [
    'title'       => 'Request a Charter — HabeshAir',
    'description' => 'Request a VIP, cargo, humanitarian, medevac, or group charter flight. Quotes typically returned within 60 minutes.',
    'canonical'   => url('/request.php'),
    'schema'      => 'service',
    'active_nav'  => 'request',
    'scripts'     => ['/assets/js/request-form.js'],
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
function checkedIf($cond): string { return $cond ? ' checked' : ''; }
?>

<section class="hero" style="padding:3rem 0 3.5rem">
  <div class="container">
    <span class="hero-badge">Charter request</span>
    <h1>Request a charter</h1>
    <p class="lede">Tell us where you want to go and what you need to move. Quotes typically returned within 60 minutes; emergency requests handled around the clock.</p>
  </div>
</section>

<section class="section">
  <div class="container" style="max-width:880px">

    <?php if (!empty($errors['_form'])): ?>
      <div class="alert alert-error"><?= e($errors['_form']) ?></div>
    <?php endif; ?>

    <form method="post" action="/request.php" class="form-card" data-form="request" novalidate>
      <?= csrf_field() ?>
      <div class="honeypot" aria-hidden="true">
        <label>Leave this empty <input type="text" name="_hp" tabindex="-1" autocomplete="off"></label>
      </div>

      <div class="form-grid">

        <div class="form-group">
          <span class="label">Service type *</span>
          <div class="radio-row" role="radiogroup">
            <?php foreach (SERVICE_TYPES as $opt):
              $isChecked = ($old['service_type'] ?? $preselect) === $opt;
            ?>
              <label class="radio-pill"><input type="radio" name="service_type" value="<?= e($opt) ?>"<?= checkedIf($isChecked) ?>> <?= e(str_replace('-', ' / ', $opt)) ?></label>
            <?php endforeach; ?>
          </div>
          <?= err('service_type', $errors) ?>
        </div>

        <div class="form-group">
          <span class="label">Trip type *</span>
          <div class="radio-row" role="radiogroup">
            <?php foreach (TRIP_TYPES as $opt):
              $isChecked = ($old['trip_type'] ?? 'One-way') === $opt;
            ?>
              <label class="radio-pill"><input type="radio" name="trip_type" value="<?= e($opt) ?>"<?= checkedIf($isChecked) ?>> <?= e($opt) ?></label>
            <?php endforeach; ?>
          </div>
          <?= err('trip_type', $errors) ?>
        </div>

        <div class="form-grid cols-2">
          <div class="form-group">
            <label for="origin">Departure (airport / city) *</label>
            <input id="origin" type="text" name="origin" value="<?= old('origin', $old) ?>" class="<?= cls('origin', $errors) ?>" maxlength="160" required>
            <?= err('origin', $errors) ?>
          </div>
          <div class="form-group">
            <label for="destination">Destination (airport / city) *</label>
            <input id="destination" type="text" name="destination" value="<?= old('destination', $old) ?>" class="<?= cls('destination', $errors) ?>" maxlength="160" required>
            <?= err('destination', $errors) ?>
          </div>
        </div>

        <div class="form-grid cols-2">
          <div class="form-group">
            <label for="travel_date">Preferred date *</label>
            <input id="travel_date" type="date" name="travel_date" value="<?= old('travel_date', $old) ?>" class="<?= cls('travel_date', $errors) ?>" required>
            <?= err('travel_date', $errors) ?>
          </div>
          <div class="form-group" data-show="return">
            <label for="return_date">Return date</label>
            <input id="return_date" type="date" name="return_date" value="<?= old('return_date', $old) ?>" class="<?= cls('return_date', $errors) ?>">
            <span class="hint">Only for round trips.</span>
            <?= err('return_date', $errors) ?>
          </div>
        </div>

        <div class="form-group">
          <label for="time_pref">Time preference</label>
          <select id="time_pref" name="time_pref">
            <?php foreach (TIME_PREFS as $opt): ?>
              <option value="<?= e($opt) ?>"<?= ($old['time_pref'] ?? 'Any') === $opt ? ' selected' : '' ?>><?= e($opt) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-grid cols-2" data-show="passenger">
          <div class="form-group">
            <label for="passengers">Number of passengers</label>
            <input id="passengers" type="number" min="1" max="300" name="passengers" value="<?= old('passengers', $old) ?>" class="<?= cls('passengers', $errors) ?>">
            <?= err('passengers', $errors) ?>
          </div>
          <div class="form-group">
            <label for="company">Company / organization</label>
            <input id="company" type="text" name="company" value="<?= old('company', $old) ?>" maxlength="160">
          </div>
        </div>

        <div class="form-grid cols-2" data-show="cargo">
          <div class="form-group">
            <label for="approx_weight_kg">Approx weight (kg)</label>
            <input id="approx_weight_kg" type="number" min="0" max="50000" name="approx_weight_kg" value="<?= old('approx_weight_kg', $old) ?>" class="<?= cls('approx_weight_kg', $errors) ?>">
            <?= err('approx_weight_kg', $errors) ?>
          </div>
          <div class="form-group">
            <label for="cargo_type">Cargo type</label>
            <select id="cargo_type" name="cargo_type">
              <option value="">— Select —</option>
              <?php foreach (CARGO_TYPES as $opt): ?>
                <option value="<?= e($opt) ?>"<?= ($old['cargo_type'] ?? '') === $opt ? ' selected' : '' ?>><?= e($opt) ?></option>
              <?php endforeach; ?>
            </select>
            <?= err('cargo_type', $errors) ?>
          </div>
        </div>

        <div class="form-group">
          <span class="label">Urgency *</span>
          <div class="radio-row" role="radiogroup">
            <?php foreach (URGENCY as $opt):
              $isChecked = ($old['urgency_level'] ?? 'Flexible') === $opt;
            ?>
              <label class="radio-pill"><input type="radio" name="urgency_level" value="<?= e($opt) ?>"<?= checkedIf($isChecked) ?>> <?= e($opt) ?></label>
            <?php endforeach; ?>
          </div>
          <span class="hint">"24h" or "Emergency" routes straight to the duty coordinator.</span>
          <?= err('urgency_level', $errors) ?>
        </div>

        <div class="form-group">
          <label for="budget_range">Budget range (optional)</label>
          <input id="budget_range" type="text" name="budget_range" value="<?= old('budget_range', $old) ?>" maxlength="80" placeholder="e.g. USD 30k–50k">
        </div>

        <div class="form-group">
          <label for="special_requirements">Special requirements / notes</label>
          <textarea id="special_requirements" name="special_requirements" maxlength="2000"><?= old('special_requirements', $old) ?></textarea>
        </div>

        <div class="form-grid cols-2">
          <div class="form-group">
            <label for="full_name">Full name *</label>
            <input id="full_name" type="text" name="full_name" value="<?= old('full_name', $old) ?>" class="<?= cls('full_name', $errors) ?>" maxlength="160" required>
            <?= err('full_name', $errors) ?>
          </div>
          <div class="form-group">
            <label for="email">Email *</label>
            <input id="email" type="email" name="email" value="<?= old('email', $old) ?>" class="<?= cls('email', $errors) ?>" maxlength="190" required>
            <?= err('email', $errors) ?>
          </div>
        </div>

        <div class="form-group">
          <label for="phone">Phone (with country code) *</label>
          <input id="phone" type="tel" name="phone" value="<?= old('phone', $old) ?>" class="<?= cls('phone', $errors) ?>" maxlength="40" required placeholder="+254 7XX XXX XXX">
          <?= err('phone', $errors) ?>
        </div>

        <div class="form-group">
          <span class="label">Preferred contact method *</span>
          <div class="radio-row" role="radiogroup">
            <?php foreach (CONTACT_METH as $opt):
              $isChecked = ($old['contact_method'] ?? 'WhatsApp') === $opt;
            ?>
              <label class="radio-pill"><input type="radio" name="contact_method" value="<?= e($opt) ?>"<?= checkedIf($isChecked) ?>> <?= e($opt) ?></label>
            <?php endforeach; ?>
          </div>
          <?= err('contact_method', $errors) ?>
        </div>

        <div class="form-group">
          <label class="checkbox-row">
            <input type="checkbox" name="consent" value="1"<?= checkedIf(!empty($old['consent'])) ?>>
            <span>I agree to the <a href="/privacy.php">Privacy Policy</a> and authorize HabeshAir to contact me about this request.</span>
          </label>
          <?= err('consent', $errors) ?>
        </div>

        <div class="form-actions">
          <button type="submit" class="btn btn-gold btn-lg">Submit request</button>
          <span class="hint">Quotes typically returned within 60 minutes.</span>
        </div>
      </div>
    </form>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
