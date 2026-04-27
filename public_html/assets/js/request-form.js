/* request-form.js — show/hide passenger vs cargo fields based on service_type. */
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
})();
