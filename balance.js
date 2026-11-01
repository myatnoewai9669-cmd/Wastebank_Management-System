// Bar chart of earnings for the last 6 months
const canvas = document.getElementById('earnChart');

if (canvas && window.Chart) {
  const data = window.earnings || [];
  new Chart(canvas, {
    type: 'bar',
    data: {
      labels: data.map(d => d.label),
      datasets: [{
        data: data.map(d => d.money),
        backgroundColor: '#1a8a3e',
        hoverBackgroundColor: '#4cd37b',
        borderRadius: 8,
        maxBarThickness: 38
      }]
    },
    options: {
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: { callbacks: { label: ctx => 'Rp ' + ctx.parsed.y.toLocaleString('en-US') } }
      },
      scales: {
        y: { beginAtZero: true, grid: { color: '#eef3ef' },
             ticks: { callback: v => v >= 1000 ? (v / 1000) + 'k' : v } },
        x: { grid: { display: false } }
      },
      animation: { duration: 1200, easing: 'easeOutQuart' }
    }
  });
}