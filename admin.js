// Line chart: waste collected per month this year
const canvas = document.getElementById('adminChart');
if (canvas && window.Chart) {
  const data = window.adminChart || [];
  const ctx = canvas.getContext('2d');
  const grad = ctx.createLinearGradient(0, 0, 0, 260);
  grad.addColorStop(0, 'rgba(26,138,62,.30)');
  grad.addColorStop(1, 'rgba(26,138,62,0)');

  new Chart(canvas, {
    type: 'line',
    data: {
      labels: data.map(d => d.label),
      datasets: [{
        data: data.map(d => d.kg),
        borderColor: '#1a8a3e', backgroundColor: grad, fill: true,
        tension: 0.4, borderWidth: 3, pointRadius: 0, pointHoverRadius: 5
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

// Animate the "Top Waste Types" bars
document.querySelectorAll('.bar span[data-w]').forEach(el => {
  setTimeout(() => { el.style.width = el.dataset.w + '%'; }, 200);
});