// Toggle the sidebar on mobile
const menuBtn = document.getElementById('menuBtn');
const sidebar = document.getElementById('sidebar');
if (menuBtn) menuBtn.addEventListener('click', () => sidebar.classList.toggle('open'));

// Count-up animation for stat numbers
document.querySelectorAll('[data-count]').forEach(el => {
  const target = parseFloat(el.dataset.count) || 0;
  const dec = parseInt(el.dataset.dec || 0, 10);
  const start = performance.now(), dur = 1200;
  function tick(now) {
    const p = Math.min((now - start) / dur, 1);
    const v = target * (1 - Math.pow(1 - p, 3));           // ease-out
    el.textContent = v.toLocaleString('en-US', {
      minimumFractionDigits: dec, maximumFractionDigits: dec
    });
    if (p < 1) requestAnimationFrame(tick);
  }
  requestAnimationFrame(tick);
});

// Line chart (weekly / monthly)
const canvas = document.getElementById('wasteChart');
const rangeSel = document.getElementById('range');
let chart;

async function loadChart(range) {
  const res = await fetch('api/chart.php?range=' + range);
  const data = await res.json();

  const ctx = canvas.getContext('2d');
  const grad = ctx.createLinearGradient(0, 0, 0, 260);
  grad.addColorStop(0, 'rgba(26,138,62,.30)');
  grad.addColorStop(1, 'rgba(26,138,62,0)');

  if (chart) chart.destroy();
  chart = new Chart(canvas, {
    type: 'line',
    data: {
      labels: data.map(d => d.label),
      datasets: [{
        data: data.map(d => d.kg),
        borderColor: '#1a8a3e',
        backgroundColor: grad,
        fill: true,
        tension: 0.4,
        borderWidth: 3,
        pointRadius: 0,
        pointHoverRadius: 5
      }]
    },
    options: {
      maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: {
        y: { beginAtZero: true, grid: { color: '#eef3ef' }, ticks: { callback: v => v + 'kg' } },
        x: { grid: { display: false } }
      },
      animation: { duration: 1200, easing: 'easeOutQuart' }
    }
  });
}

if (canvas && window.Chart) {
  loadChart('week');
  rangeSel.addEventListener('change', () => loadChart(rangeSel.value));
}

// ---------- Site search: finds pages by name or keyword ----------
(function () {
  var input = document.getElementById('siteSearch');
  var menu  = document.getElementById('searchMenu');
  if (!input || !menu) return;

  var items = window.searchItems || [];
  var active = -1;

  function close() { menu.classList.remove('open'); active = -1; }

  function render(list) {
    menu.innerHTML = '';
    if (!list.length) {
      var p = document.createElement('p');
      p.className = 'notif-empty';
      p.textContent = 'No pages found.';
      menu.appendChild(p);
    }
    list.forEach(function (it) {
      var a = document.createElement('a');
      a.href = it.url;
      a.textContent = it.icon + '  ' + it.label;   // textContent keeps it XSS-safe
      menu.appendChild(a);
    });
    menu.classList.add('open');
  }

  function search() {
    var q = input.value.trim().toLowerCase();
    if (!q) { close(); return; }
    var words = q.split(/\s+/);
    var found = items.filter(function (it) {
      return words.every(function (w) { return it.words.indexOf(w) !== -1; });
    });
    render(found.slice(0, 6));
    active = -1;
  }

  function highlight(links) {
    links.forEach(function (l, i) { l.classList.toggle('sel', i === active); });
  }

  input.addEventListener('input', search);
  input.addEventListener('focus', search);

  input.addEventListener('keydown', function (e) {
    var links = Array.prototype.slice.call(menu.querySelectorAll('a'));
    if (e.key === 'ArrowDown') { e.preventDefault(); active = Math.min(active + 1, links.length - 1); highlight(links); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); active = Math.max(active - 1, 0); highlight(links); }
    else if (e.key === 'Enter') {
      e.preventDefault();
      var target = links[active >= 0 ? active : 0];
      if (target) window.location.href = target.getAttribute('href');
    }
    else if (e.key === 'Escape') { close(); input.blur(); }
  });

  document.addEventListener('click', function (e) {
    if (!e.target.closest('#searchBox')) close();
  });

  // Press "/" anywhere to jump into the search box
  document.addEventListener('keydown', function (e) {
    var tag = (document.activeElement || {}).tagName;
    if (e.key === '/' && tag !== 'INPUT' && tag !== 'TEXTAREA' && tag !== 'SELECT') {
      e.preventDefault();
      input.focus();
    }
  });
})();