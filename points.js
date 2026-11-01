// Default center: Sukabumi (used if location access is denied)
let userPos = { lat: -6.9277, lng: 106.93 };
let activeType = 'all';
const markers = {};

const map = L.map('map').setView([userPos.lat, userPos.lng], 13);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
  attribution: '&copy; OpenStreetMap contributors',
  maxZoom: 19
}).addTo(map);

// Green pin icon for recycling points
const greenIcon = L.divIcon({
  className: '',
  html: '<div class="pin">♻</div>',
  iconSize: [34, 34],
  iconAnchor: [17, 34]
});
// Blue pulsing dot for "You are here"
const meIcon = L.divIcon({
  className: '',
  html: '<div class="me"></div>',
  iconSize: [18, 18]
});
let meMarker = L.marker([userPos.lat, userPos.lng], { icon: meIcon })
  .addTo(map).bindTooltip('You are here', { permanent: true, direction: 'top' });

// Convert DB strings to numbers
const points = window.points.map(p => ({
  ...p,
  lat: parseFloat(p.latitude),
  lng: parseFloat(p.longitude)
}));

// Distance between two coordinates in km (Haversine formula)
function distanceKm(a, b) {
  const R = 6371, rad = x => x * Math.PI / 180;
  const dLat = rad(b.lat - a.lat), dLng = rad(b.lng - a.lng);
  const h = Math.sin(dLat / 2) ** 2 +
            Math.cos(rad(a.lat)) * Math.cos(rad(b.lat)) * Math.sin(dLng / 2) ** 2;
  return 2 * R * Math.asin(Math.sqrt(h));
}

// Escape text before putting it into HTML
function esc(s) {
  const d = document.createElement('div');
  d.textContent = s ?? '';
  return d.innerHTML;
}

// Add all markers once
points.forEach(p => {
  markers[p.id] = L.marker([p.lat, p.lng], { icon: greenIcon })
    .addTo(map)
    .bindPopup('<strong>' + esc(p.name) + '</strong><br>' + esc(p.address));
});

function render() {
  const sort = document.getElementById('sort').value;
  const list = document.getElementById('list');

  let items = points
    .map(p => ({ ...p, km: distanceKm(userPos, p) }))
    .filter(p => activeType === 'all' || p.type === activeType);

  items.sort((a, b) => sort === 'name' ? a.name.localeCompare(b.name) : a.km - b.km);

  // Show or hide markers based on the filter
  points.forEach(p => {
    const visible = items.some(i => i.id === p.id);
    if (visible && !map.hasLayer(markers[p.id])) markers[p.id].addTo(map);
    if (!visible && map.hasLayer(markers[p.id])) map.removeLayer(markers[p.id]);
  });

  if (!items.length) {
    list.innerHTML = '<p class="empty">No recycling points found.</p>';
    return;
  }

  list.innerHTML = items.map(p => {
    const minutes = Math.max(1, Math.round(p.km / 0.4));     // ~24 km/h average
    const tags = (p.accepted || '').split(',').filter(Boolean)
      .map(t => '<span class="tag">' + esc(t.trim()) + '</span>').join('');
    const dir = 'https://www.google.com/maps/dir/?api=1&destination=' + p.lat + ',' + p.lng;
    return `
      <div class="pt" data-id="${p.id}">
        <div class="pt-ic">♻</div>
        <div class="pt-info">
          <strong>${esc(p.name)}</strong>
          <small>${p.km.toFixed(1)} km • ${minutes} min</small>
          <div class="tags">${tags}</div>
        </div>
        <a class="btn-sm" href="${dir}" target="_blank" rel="noopener">Get Direction</a>
      </div>`;
  }).join('');

  // Click a list item to focus on its marker
  list.querySelectorAll('.pt').forEach(el => {
    el.addEventListener('click', e => {
      if (e.target.closest('a')) return;
      const m = markers[el.dataset.id];
      map.flyTo(m.getLatLng(), 16);
      m.openPopup();
    });
  });
}

// Filter chips
document.getElementById('filters').addEventListener('click', e => {
  const chip = e.target.closest('.chip');
  if (!chip) return;
  document.querySelectorAll('.chip').forEach(c => c.classList.remove('active'));
  chip.classList.add('active');
  activeType = chip.dataset.type;
  render();
});
document.getElementById('sort').addEventListener('change', render);

// Use the browser location if the user allows it
if (navigator.geolocation) {
  navigator.geolocation.getCurrentPosition(pos => {
    userPos = { lat: pos.coords.latitude, lng: pos.coords.longitude };
    meMarker.setLatLng([userPos.lat, userPos.lng]);
    map.setView([userPos.lat, userPos.lng], 13);
    render();
  }, () => {}, { timeout: 8000 });
}

render();