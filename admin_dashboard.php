<?php
require_once 'db.php';
require_role(['admin']);

$pageTitle = 'Admin Dashboard';
require 'header.php';

// Members
$members = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='member'")->fetchColumn();
$newMembers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='member'
                                AND created_at >= DATE_FORMAT(CURDATE(),'%Y-%m-01')")->fetchColumn();

// Totals
$tot = $pdo->query("SELECT COALESCE(SUM(weight_kg),0) AS kg,
                           COALESCE(SUM(amount),0)    AS money,
                           COUNT(*)                   AS cnt FROM transactions")->fetch();
$co2 = $tot['kg'] * 0.082;   // simple estimate: kg of CO2 reduced

// Waste collected per month this year
$rows = $pdo->query("SELECT MONTH(created_at) AS m, SUM(weight_kg) AS kg
                     FROM transactions WHERE YEAR(created_at)=YEAR(CURDATE())
                     GROUP BY m")->fetchAll();
$byMonth = array_column($rows, 'kg', 'm');
$chart = [];
foreach (['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'] as $i => $label) {
  $chart[] = ['label' => $label, 'kg' => (float)($byMonth[$i + 1] ?? 0)];
}

// Top waste types
$top = $pdo->query("SELECT wt.name, SUM(t.weight_kg) AS kg
                    FROM transactions t JOIN waste_types wt ON wt.id = t.waste_type_id
                    GROUP BY wt.id, wt.name ORDER BY kg DESC")->fetchAll();

// Recent transactions
$recent = $pdo->query("SELECT t.*, u.name AS member_name, wt.name AS type_name
                       FROM transactions t
                       JOIN users u ON u.id = t.user_id
                       JOIN waste_types wt ON wt.id = t.waste_type_id
                       ORDER BY t.created_at DESC, t.id DESC LIMIT 5")->fetchAll();

$pending = (int)$pdo->query("SELECT COUNT(*) FROM pickup_requests WHERE status='pending'")->fetchColumn();
$icons = ['Plastic'=>'🧴','Paper'=>'📦','Glass'=>'🍾','Metal'=>'🥫','Others'=>'♻'];
?>

<h1 class="welcome">Dashboard</h1>
<p class="sub">Overview of the whole waste bank.</p>

<?php if ($pending > 0): ?>
  <div class="notice err" style="background:#fff8e6;color:#8a5a00;border-color:#fbc02d">
    ⏳ <?= $pending ?> pickup request<?= $pending > 1 ? 's are' : ' is' ?> waiting for approval.
    <a href="pickup_requests.php?status=pending"><strong>Review now →</strong></a>
  </div>
<?php endif; ?>

<div class="mini-stats">
  <div class="stat"><div class="ic g">👥</div><small>Total Members</small>
    <h3><span data-count="<?= $members ?>" data-dec="0">0</span></h3><em>+<?= $newMembers ?> this month</em></div>
  <div class="stat"><div class="ic g">♻</div><small>Total Waste Collected</small>
    <h3><span data-count="<?= $tot['kg'] ?>" data-dec="0">0</span> kg</h3><em><?= (int)$tot['cnt'] ?> deposits</em></div>
  <div class="stat"><div class="ic y">💰</div><small>Total Transactions</small>
    <h3>Rp <span data-count="<?= $tot['money'] ?>" data-dec="0">0</span></h3></div>
  <div class="stat"><div class="ic g">🌿</div><small>CO₂ Reduced</small>
    <h3><span data-count="<?= $co2 ?>" data-dec="0">0</span> kg</h3></div>
</div>

<div class="adm-grid">
  <div class="panel">
    <div class="ph"><h3>Waste Collection Overview</h3><span class="tag">This Year</span></div>
    <div class="chart-box"><canvas id="adminChart"></canvas></div>
  </div>

  <div class="panel">
    <h3 style="margin-bottom:14px">Top Waste Types</h3>
    <?php if (!$top): ?><p class="empty">No data yet.</p><?php endif; ?>
    <?php $all = array_sum(array_column($top, 'kg')) ?: 1;
          foreach ($top as $t): $pct = round($t['kg'] / $all * 100); ?>
      <div class="tw">
        <span class="ti"><?= $icons[$t['name']] ?? '♻' ?></span>
        <div class="tw-main">
          <div class="tw-row"><strong><?= e($t['name']) ?></strong>
            <small><?= number_format($t['kg'], 0) ?> kg • <?= $pct ?>%</small></div>
          <div class="bar"><span data-w="<?= $pct ?>"></span></div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<div class="panel" style="margin-top:16px">
  <div class="ph"><h3>Recent Transactions</h3><a href="transactions.php">View All</a></div>
  <div class="tbl-wrap">
    <table class="tbl">
      <thead><tr><th>Member</th><th>Item</th><th>Weight</th><th>Amount</th><th>Date</th></tr></thead>
      <tbody>
        <?php foreach ($recent as $r): ?>
          <tr>
            <td><?= e($r['member_name']) ?></td>
            <td><span class="ti"><?= $icons[$r['type_name']] ?? '♻' ?></span> <?= e($r['item_name'] ?: $r['type_name']) ?></td>
            <td><?= number_format($r['weight_kg'], 1) ?> kg</td>
            <td class="plus">Rp <?= number_format($r['amount'], 0, '.', ',') ?></td>
            <td><?= date('M d, Y', strtotime($r['created_at'])) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$recent): ?><tr><td colspan="5" class="empty" style="text-align:center;padding:24px">No transactions yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
  // Data passed from PHP to JavaScript
  window.adminChart = <?= json_encode($chart) ?>;
</script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="admin.js"></script>
<?php require 'footer.php'; ?>