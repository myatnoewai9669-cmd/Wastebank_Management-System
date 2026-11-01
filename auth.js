// Show / hide password
document.querySelectorAll('[data-toggle]').forEach(btn => {
  btn.addEventListener('click', () => {
    const input = btn.parentElement.querySelector('input');
    input.type = input.type === 'password' ? 'text' : 'password';
    btn.textContent = input.type === 'password' ? '👁' : '🙈';
  });
});

// Button ripple effect
document.querySelectorAll('.btn').forEach(btn => {
  btn.addEventListener('click', e => {
    const r = btn.getBoundingClientRect();
    const s = document.createElement('span');
    s.className = 'ripple';
    s.style.width = s.style.height = Math.max(r.width, r.height) + 'px';
    s.style.left = (e.clientX - r.left - r.width / 2) + 'px';
    s.style.top  = (e.clientY - r.top  - r.height / 2) + 'px';
    btn.appendChild(s);
    setTimeout(() => s.remove(), 600);
  });
});

// Register: password strength meter + confirm password check
const pass = document.getElementById('pass');
const conf = document.getElementById('conf');
const bar  = document.getElementById('bar');
if (pass && bar) {
  pass.addEventListener('input', () => {
    const v = pass.value;
    let s = 0;
    if (v.length >= 8) s++;
    if (/[A-Z]/.test(v) && /[a-z]/.test(v)) s++;
    if (/\d/.test(v)) s++;
    if (/[^A-Za-z0-9]/.test(v)) s++;
    bar.style.width = (s * 25) + '%';
    bar.style.background = ['#e53935','#e53935','#fbc02d','#7cb342','#1a8a3e'][s];
  });
  document.getElementById('regForm').addEventListener('submit', e => {
    if (pass.value !== conf.value) {
      e.preventDefault();
      conf.style.borderColor = '#e53935';
      conf.focus();
      alert('Passwords do not match');
    }
  });
}