<?php
$pageTitle = 'Recycling Points';
require 'header.php';

// Load all active recycling points
$rows = $pdo->query("SELECT id, name, type, accepted, address, latitude, longitude
                     FROM recycling_points WHERE is_active = 1")->fetchAll();
?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">

<h1 class="welcome">Recycling Points</h1>
<p class="sub">Find nearby recycling centers and waste collection points.</p>

<div class="filters" id="filters">
  <button class="chip active" data-type="all">All</button>
  <button class="chip" data-type="recycling_center">♻ Recycling Center</button>
  <button class="chip" data-type="waste_bank">🏦 Waste Bank</button>
  <button class="chip" data-type="drop_point">📍 Drop Point</button>
</div>

<div class="points-grid">
  <div class="panel map-panel">
    <div id="map"></div>
  </div>

  <div class="panel">
    <div class="ph">
      <h3>Nearby Recycling Points</h3>
      <select id="sort">
        <option value="nearest">Nearest</option>
        <option value="name">Name (A-Z)</option>
      </select>
    </div>
    <div id="list"></div>
  </div>
</div>

<script>
  // Data passed from PHP to JavaScript
  window.points = <?= json_encode($rows, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="points.js"></script>

<?php require 'footer.php'; ?>