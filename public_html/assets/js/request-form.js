/* request-form.js — show/hide fields + client-side validation. */
(function () {
  'use strict';

  const form = document.querySelector('form[data-form="request"]');
  if (!form) return;

  const passengerWrap = form.querySelector('[data-show="passenger"]');
  const cargoWrap     = form.querySelector('[data-show="cargo"]');
  const returnWrap    = form.querySelector('[data-show="return"]');

  function update() {
    const service = form.querySelector('input[name="service_type"]:checked')?.value || '';
    const trip    = form.querySelector('input[name="trip_type"]:checked')?.value || '';

    const isCargoOnly = service === 'Cargo';
    const isPassengerLike = ['VIP', 'Humanitarian', 'Emergency-Medevac', 'Group-Event'].includes(service);

    if (passengerWrap) passengerWrap.hidden = !isPassengerLike;
    if (cargoWrap)     cargoWrap.hidden     = !(isCargoOnly || service === 'Humanitarian');
    if (returnWrap)    returnWrap.hidden    = trip !== 'Round-trip';
  }

  form.addEventListener('change', (e) => {
    if (e.target.matches('input[name="service_type"], input[name="trip_type"]')) update();
  });
  update();

  // Client-side validation
  function isVisible(el) {
    let node = el;
    while (node && node !== form) {
      if (node.hidden || getComputedStyle(node).display === 'none') return false;
      node = node.parentElement;
    }
    return true;
  }

  function showErr(el, msg) {
    const span = document.createElement('span');
    span.className = 'js-err';
    span.style.cssText = 'display:block;color:var(--danger);font-size:.85rem;margin-top:.25rem';
    span.textContent = msg;
    el.parentElement.appendChild(span);
  }

  function clearErrs() {
    form.querySelectorAll('.js-err').forEach(n => n.remove());
    form.querySelectorAll('.has-error').forEach(n => n.classList.remove('has-error'));
  }

  form.addEventListener('submit', function (e) {
    clearErrs();
    let firstInvalid = null;

    // Required text / email / date / select inputs that are visible
    form.querySelectorAll('input[required], textarea[required]').forEach(function (input) {
      if (!isVisible(input)) return;
      const val = input.value.trim();
      if (!val) {
        input.classList.add('has-error');
        showErr(input, 'This field is required');
        if (!firstInvalid) firstInvalid = input;
      }
    });

    // Required radio groups
    ['service_type', 'trip_type', 'urgency_level', 'contact_method'].forEach(function (name) {
      const radios = form.querySelectorAll('input[name="' + name + '"]');
      if (!radios.length) return;
      const firstRadio = radios[0];
      if (!isVisible(firstRadio)) return;
      const checked = form.querySelector('input[name="' + name + '"]:checked');
      if (!checked) {
        const row = firstRadio.closest('.radio-row') || firstRadio.parentElement;
        showErr(row, 'Please select an option');
        if (!firstInvalid) firstInvalid = firstRadio;
      }
    });

    // Consent checkbox
    const consent = form.querySelector('input[name="consent"]');
    if (consent && !consent.checked) {
      showErr(consent, 'You must agree to continue');
      if (!firstInvalid) firstInvalid = consent;
    }

    if (firstInvalid) {
      e.preventDefault();
      firstInvalid.focus();
    }
  });
})();
