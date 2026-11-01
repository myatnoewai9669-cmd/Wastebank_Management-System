// Animate the progress bar after the page loads
const fill = document.querySelector('.bar span');
if (fill) {
  setTimeout(() => { fill.style.width = fill.dataset.w + '%'; }, 200);
}

// Ask for confirmation before redeeming
document.querySelectorAll('.redeem-form').forEach(form => {
  form.addEventListener('submit', e => {
    const title = form.dataset.title;
    const cost  = form.dataset.cost;
    if (!confirm('Redeem "' + title + '" for ' + cost + ' points?')) {
      e.preventDefault();
    }
  });
});