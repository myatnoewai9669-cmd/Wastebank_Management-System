<?php
$pageTitle = 'Dashboard';
require 'header.php';

$uid = $_SESSION['user']['id'];

// Points + totals
$st = $pdo->prepare("SELECT points FROM users WHERE id=?");
$st->execute([$uid]);
$points = (int)$st->fetchColumn();

$st = $pdo->prepare("SELECT COALESCE(SUM(weight_kg),0) AS kg,
                            COALESCE(SUM(amount),0)    AS money,
                            COUNT(*)                   AS cnt
                     FROM transactions WHERE user_id=?");
$st->execute([$uid]);
$t = $st->fetch();

// Waste collected in the last 7 days
$st = $pdo->prepare("SELECT COALESCE(SUM(weight_kg),0) FROM transactions
                     WHERE user_id=? AND created_at >= CURDATE() - INTERVAL 6 DAY");
$st->execute([$uid]);
$weekKg = (float)$st->fetchColumn();

// Last 4 transactions
$st = $pdo->prepare("SELECT t.*, wt.name AS type_name
                     FROM transactions t
                     JOIN waste_types wt ON wt.id = t.waste_type_id
                     WHERE t.user_id=? ORDER BY t.created_at DESC LIMIT 4");
$st->execute([$uid]);
$recent = $st->fetchAll();

// Impact (simple estimates)
$trees = floor($t['kg'] / 2.5);     // 1 tree per 2.5 kg
$co2   = $t['kg'] * 0.082;          // kg of CO2 reduced

$icons = ['Plastic'=>'🧴','Paper'=>'📦','Glass'=>'🍾','Metal'=>'🥫','Others'=>'♻'];
?>

<h1 class="welcome">Welcome back, <?= e($_SESSION['user']['name']) ?>! 👋</h1>
<p class="sub">Small actions make a big impact. Let's keep our environment clean and green!</p>

<div class="top-grid">
  <!-- Hero banner -->
  <div class="hero">
    <div>
      <h2>Together for a<br>Cleaner Planet</h2>
      <p>Reduce &nbsp;•&nbsp; Reuse &nbsp;•&nbsp; Recycle</p>
      <a href="guide.php" class="btn-light">See How It Works →</a>
    </div>
    <div class="globe">🌍</div>
  </div>

  <!-- Stat cards -->
  <div class="stats">
    <div class="stat">
      <div class="ic g">🌿</div>
      <small>Total Waste Collected</small>
      <h3><span data-count="<?= $t['kg'] ?>" data-dec="1">0</span> kg</h3>
      <em>+<?= number_format($weekKg, 1) ?> kg this week</em>
    </div>
    <div class="stat">
      <div class="ic y">🧾</div>
      <small>Total Transactions</small>
      <h3>Rp <span data-count="<?= $t['money'] ?>" data-dec="0">0</span></h3>
      <em><?= (int)$t['cnt'] ?> deposits</em>
    </div>
    <div class="stat">
      <div class="ic o">🪙</div>
      <small>Your Points</small>
      <h3><span data-count="<?= $points ?>" data-dec="0">0</span> pts</h3>
      <a href="rewards.php" class="btn-sm">Redeem</a>
    </div>
  </div>
</div>

<!-- Quick actions -->
<div class="quick">
  <a href="pickup.php"><i>🚚</i>Book Pickup</a>
  <a href="points.php"><i>📍</i>Recycling Points</a>
  <a href="rewards.php"><i>★</i>Rewards</a>
  <a href="qr.php"><i>▣</i>Scan QR</a>
</div>

<div class="grid3">
  <!-- Line chart -->
  <div class="panel">
    <div class="ph">
      <h3>Waste Collection Overview</h3>
      <select id="range"><option value="week">This Week</option><option value="month">This Month</option></select>
    </div>
    <div class="chart-box"><canvas id="wasteChart"></canvas></div>
  </div>

  <!-- Recent transactions -->
  <div class="panel">
    <div class="ph"><h3>Recent Transactions</h3><a href="transactions.php">View All</a></div>
    <?php if (!$recent): ?><p class="empty">No transactions yet.</p><?php endif; ?>
    <?php foreach ($recent as $r): ?>
      <div class="tx">
        <span class="ti"><?= $icons[$r['type_name']] ?? '♻' ?></span>
        <div class="tn"><strong><?= e($r['item_name'] ?: $r['type_name']) ?></strong><small><?= number_format($r['weight_kg'], 1) ?> kg</small></div>
        <div class="ta"><strong>Rp <?= number_format($r['amount'], 0, '.', ',') ?></strong>
          <small><?= date('M d, Y', strtotime($r['created_at'])) ?></small></div>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- Your impact -->
  <div class="panel impact">
    <h3>Your Impact</h3>
    <div class="imp"><i>🌿</i><div><strong><?= number_format($t['kg'], 1) ?> kg</strong><small>waste collected</small></div></div>
    <div class="imp"><i>🌳</i><div><strong><?= (int)$trees ?></strong><small>trees planted</small></div></div>
    <div class="imp"><i>☁</i><div><strong><?= number_format($co2, 2) ?> kg</strong><small>CO₂ reduced</small></div></div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<?php require 'footer.php'; ?>