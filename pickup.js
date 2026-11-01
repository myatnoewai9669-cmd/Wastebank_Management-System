const form  = document.getElementById('pickupForm');
const panes = form.querySelectorAll('.pane');
const steps = document.querySelectorAll('#steps .step');
const err1  = document.getElementById('err1');
let current = 1;

function show(n) {
  current = n;
  panes.forEach(p => p.classList.toggle('active', +p.dataset.step === n));
  steps.forEach((s, i) => {
    s.classList.toggle('active', i + 1 === n);
    s.classList.toggle('done', i + 1 < n);
  });
}

function validateStep1() {
  const date = document.getElementById('pdate').value;
  const slot = form.querySelector('input[name="time_slot"]:checked');
  const today = new Date().toISOString().slice(0, 10);
  if (!date)            return 'Please choose a pickup date.';
  if (date < today)     return 'Pickup date cannot be in the past.';
  if (!slot)            return 'Please select a time slot.';
  return '';
}

function fillSummary() {
  const date = document.getElementById('pdate').value;
  const slot = form.querySelector('input[name="time_slot"]:checked');
  const types = [...form.querySelectorAll('input[name="waste_types[]"]:checked')].map(i => i.value);
  const note = document.getElementById('pnote').value.trim();

  document.getElementById('sDate').textContent  =
    new Date(date + 'T00:00:00').toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
  document.getElementById('sSlot').textContent  = slot ? slot.value : '-';
  document.getElementById('sTypes').textContent = types.length ? types.join(', ') : 'Not specified';
  document.getElementById('sNote').textContent  = note || 'None';   // textContent keeps it XSS-safe
}

form.querySelectorAll('[data-next]').forEach(btn => btn.addEventListener('click', () => {
  if (current === 1) {
    const msg = validateStep1();
    err1.textContent = msg;
    if (msg) return;
  }
  if (current === 2) fillSummary();
  show(current + 1);
}));

form.querySelectorAll('[data-prev]').forEach(btn =>
  btn.addEventListener('click', () => show(current - 1)));

// If the server returned validation errors, go back to step 1
if (document.querySelector('.notice.err')) show(1);