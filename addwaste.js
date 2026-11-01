// Live preview of the total value and points (the server recalculates on save)
const wtype  = document.getElementById('wtype');
const weight = document.getElementById('weight');
const total  = document.getElementById('total');
const pts    = document.getElementById('pts');

function update() {
  if (!wtype) return;
  const opt = wtype.selectedOptions[0];
  const price  = parseFloat(opt?.dataset.price  || 0);
  const perKg  = parseFloat(opt?.dataset.points || 0);
  const kg     = parseFloat(weight.value || 0);

  total.textContent = 'Rp ' + Math.round(kg * price).toLocaleString('en-US');
  pts.textContent   = Math.floor(kg * perKg) + ' pts';
}

if (wtype && weight) {
  wtype.addEventListener('change', update);
  weight.addEventListener('input', update);
  update();
}