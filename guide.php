<?php
$pageTitle = 'Recycling Guide';
require 'header.php';

// Prices and points come from the database (same names as waste_types)
$prices = [];
foreach ($pdo->query("SELECT name, price_per_kg, points_per_kg FROM waste_types WHERE is_active=1") as $w) {
  $prices[$w['name']] = $w;
}

$guide = [
  'Plastic' => ['🧴', 'Bottles, bags, packaging',
    ['PET water and drink bottles', 'Shampoo and detergent bottles', 'Clean plastic bags and wrap', 'Food containers and tubs'],
    ['Greasy or food-soiled plastic', 'Styrofoam', 'Plastic mixed with metal parts'],
    'Rinse bottles, remove caps and flatten them to save space.'],
  'Paper' => ['📦', 'Newspaper, boxes, paper',
    ['Newspapers and magazines', 'Cardboard boxes', 'Office paper and books', 'Paper bags'],
    ['Wet or greasy paper', 'Used tissues', 'Waxed or laminated paper'],
    'Keep paper dry and fold boxes flat.'],
  'Glass' => ['🍾', 'Bottles, jars, glass containers',
    ['Glass bottles', 'Jars without lids', 'Clear and coloured glass containers'],
    ['Broken window glass', 'Mirrors', 'Light bulbs', 'Ceramics'],
    'Rinse out glass and handle broken pieces with care.'],
  'Metal' => ['🥫', 'Cans, foil, metal packaging',
    ['Aluminium drink cans', 'Food tins', 'Clean foil', 'Metal lids'],
    ['Aerosol cans that are not empty', 'Paint cans', 'Batteries'],
    'Crush cans to save space and rinse out leftover food.'],
  'Others' => ['♻', 'E-waste, batteries, textiles',
    ['Old phones and chargers', 'Small electronics', 'Used clothes and textiles'],
    ['Medical waste', 'Chemicals', 'Food waste'],
    'Ask our staff about the best drop-off for electronics and batteries.'],
];
?>

<h1 class="welcome">Recycling Guide</h1>
<p class="sub">Learn what can be recycled and how to do it.</p>

<!-- Category cards -->
<div class="g-cards">
  <?php foreach ($guide as $name => $g): $key = strtolower($name); ?>
    <button type="button" class="g-card" data-key="<?= $key ?>">
      <i><?= $g[0] ?></i>
      <strong><?= e($name) ?></strong>
      <small><?= e($g[1]) ?></small>
    </button>
  <?php endforeach; ?>
</div>

<!-- Detail panels (one is shown at a time) -->
<?php foreach ($guide as $name => $g): $key = strtolower($name); ?>
  <div class="panel g-panel" data-panel="<?= $key ?>">
    <div class="ph">
      <h3><?= $g[0] ?> <?= e($name) ?></h3>
      <?php if (isset($prices[$name])): ?>
        <span class="tag">Rp <?= number_format($prices[$name]['price_per_kg'], 0, '.', ',') ?>/kg •
          <?= (int)$prices[$name]['points_per_kg'] ?> pts/kg</span>
      <?php endif; ?>
    </div>
    <div class="yn">
      <div>
        <h4 class="y">✔ Accepted</h4>
        <ul><?php foreach ($g[2] as $item): ?><li><?= e($item) ?></li><?php endforeach; ?></ul>
      </div>
      <div>
        <h4 class="n">✘ Not accepted</h4>
        <ul><?php foreach ($g[3] as $item): ?><li><?= e($item) ?></li><?php endforeach; ?></ul>
      </div>
    </div>
    <div class="g-tip">💡 <?= e($g[4]) ?></div>
  </div>
<?php endforeach; ?>

<!-- General tips -->
<div class="panel">
  <h3 style="margin-bottom:14px">Tips for Better Recycling</h3>
  <div class="earn four">
    <div class="earn-item"><i>🧼</i><strong>Clean</strong><small>Rinse food and liquid residues</small></div>
    <div class="earn-item"><i>🗂</i><strong>Sort</strong><small>Keep each type separate</small></div>
    <div class="earn-item"><i>📉</i><strong>Reduce</strong><small>Use less plastic if possible</small></div>
    <div class="earn-item"><i>🔁</i><strong>Reuse</strong><small>Give items a longer life</small></div>
  </div>
</div>

<script src="guide.js"></script>
<?php require 'footer.php'; ?>