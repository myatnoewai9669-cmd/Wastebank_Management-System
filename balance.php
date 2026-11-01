<?php
$pageTitle = 'My Balance';
require 'header.php';

$uid = $_SESSION['user']['id'];

// Balance and points
$st = $pdo->prepare("SELECT balance, points FROM users WHERE id=?");
$st->execute([$uid]);
$me = $st->fetch();

// Lifetime and this-month totals
$st = $pdo->prepare("SELECT COALESCE(SUM(amount),0) AS earned,
                            COALESCE(SUM(weight_kg),0) AS kg,
                            COALESCE(SUM(CASE WHEN created_at >= DATE_FORMAT(CURDATE(),'%Y-%m-01')
                                              THEN amount END),0) AS month_earned
                     FROM transactions WHERE user_id=?");
$st->execute([$uid]);
$tot = $st->fetch();

// Earnings per month for the last 6 months
$st = $pdo->prepare("SELECT DATE_FORMAT(created_at,'%Y-%m') AS ym, SUM(amount) AS money
                     FROM transactions
                     WHERE user_id=? AND created_at >= DATE_FORMAT(CURDATE() - INTERVAL 5 MONTH,'%Y-%m-01')
                     GROUP BY ym");
$st->execute([$uid]);
$byMonth = array_column($st->fetchAll(), 'money', 'ym');

$chart = [];
for ($i = 5; $i >= 0; $i--) {
  $ts = strtotime("first day of -$i month");
  $chart[] = ['label' => date('M', $ts), 'money' => (float)($byMonth[date('Y-m', $ts)] ?? 0)];
}

// Recent deposits
$st = $pdo->prepare("SELECT t.*, wt.name AS type_name FROM transactions t
                     JOIN waste_types wt ON wt.id = t.waste_type_id
                     WHERE t.user_id=? ORDER BY t.created_at DESC LIMIT 5");
$st->execute([$uid]);
$recent = $st->fetchAll();

// Recent points activity (earned and redeemed)
$st = $pdo->prepare("SELECT * FROM points_ledger WHERE user_id=? ORDER BY created_at DESC, id DESC LIMIT 5");
$st->execute([$uid]);
$ledger = $st->fetchAll();
$reasons = ['transaction'=>'Waste deposit','pickup'=>'Pickup bonus','redeem'=>'Reward redeemed',
            'bonus'=>'Bonus points','adjust'=>'Adjustment'];
$icons = ['Plastic'=>'🧴','Paper'=>'📦','Glass'=>'🍾','Metal'=>'🥫','Others'=>'♻'];
?>

<h1 class="welcome">My Balance</h1>
<p class="sub">Track your earnings and points from recycling.</p>

<div class="bal-top">
  <div class="pts-card">
    <div class="pts-star">💳</div>
    <div>
      <small>Current Balance</small>
      <h2>Rp <span data-count="<?= $me['balance'] ?>" data-dec="0">0</span></h2>
      <a href="transactions.php" class="btn-light">View Transactions</a>
    </div>
  </div>

  <div class="mini-stats two">
    <div class="stat"><small>Total Earned</small><h3>Rp <?= number_format($tot['earned'], 0, '.', ',') ?></h3></div>
    <div class="stat"><small>This Month</small><h3>Rp <?= number_format($tot['month_earned'], 0, '.', ',') ?></h3></div>
    <div class="stat"><small>Waste Deposited</small><h3><?= number_format($tot['kg'], 1) ?> kg</h3></div>
    <div class="stat"><small>Your Points</small><h3><?= (int)$me['points'] ?> pts</h3>
      <a href="rewards.php" class="btn-sm">Redeem</a></div>
  </div>
</div>

<div class="bal-grid">
  <div class="panel">
    <div class="ph"><h3>Monthly Earnings</h3></div>
    <div class="chart-box"><canvas id="earnChart"></canvas></div>
  </div>

  <div class="panel">
    <div class="ph"><h3>Recent Deposits</h3><a href="transactions.php">View All</a></div>
    <?php if (!$recent): ?><p class="empty">No deposits yet.</p><?php endif; ?>
    <?php foreach ($recent as $r): ?>
      <div class="tx">
        <span class="ti"><?= $icons[$r['type_name']] ?? '♻' ?></span>
        <div class="tn"><strong><?= e($r['item_name'] ?: $r['type_name']) ?></strong>
          <small><?= number_format($r['weight_kg'], 1) ?> kg • <?= date('M d', strtotime($r['created_at'])) ?></small></div>
        <div class="ta"><strong class="plus">+Rp <?= number_format($r['amount'], 0, '.', ',') ?></strong></div>
      </div>
    <?php endforeach; ?>

    <h3 style="margin:22px 0 8px;font-size:14.5px">Points Activity</h3>
    <?php if (!$ledger): ?><p class="empty">No points activity yet.</p><?php endif; ?>
    <?php foreach ($ledger as $l): ?>
      <div class="tx">
        <div class="tn"><strong><?= e($reasons[$l['reason']] ?? ucfirst($l['reason'])) ?></strong>
          <small><?= date('M d, Y', strtotime($l['created_at'])) ?></small></div>
        <div class="ta"><strong class="<?= $l['change_points'] >= 0 ? 'plus' : 'minus-t' ?>">
          <?= $l['change_points'] >= 0 ? '+' : '' ?><?= (int)$l['change_points'] ?> pts</strong></div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<script>
  // Data passed from PHP to JavaScript
  window.earnings = <?= json_encode($chart) ?>;
</script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="balance.js"></script>
<?php require 'footer.php'; ?>