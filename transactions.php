<?php
$pageTitle = 'Transactions';
require 'header.php';

$uid = $_SESSION['user']['id'];
$perPage = 10;

// ---------- Read and validate filters ----------
$from = $_GET['from'] ?? '';
$to   = $_GET['to'] ?? '';
$type = (int)($_GET['type'] ?? 0);
$page = max(1, (int)($_GET['page'] ?? 1));

$validDate = fn($d) => (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d) !== false;
if ($from !== '' && !$validDate($from)) $from = '';
if ($to   !== '' && !$validDate($to))   $to = '';

$where  = ['t.user_id = ?'];
$params = [$uid];
if ($from !== '') { $where[] = 't.created_at >= ?'; $params[] = $from . ' 00:00:00'; }
if ($to   !== '') { $where[] = 't.created_at <= ?'; $params[] = $to   . ' 23:59:59'; }
if ($type > 0)    { $where[] = 't.waste_type_id = ?'; $params[] = $type; }
$whereSql = implode(' AND ', $where);

// ---------- Totals for the current filter ----------
$st = $pdo->prepare("SELECT COUNT(*) AS cnt,
                            COALESCE(SUM(t.weight_kg),0)     AS kg,
                            COALESCE(SUM(t.amount),0)        AS money,
                            COALESCE(SUM(t.points_earned),0) AS pts
                     FROM transactions t WHERE $whereSql");
$st->execute($params);
$sum = $st->fetch();

$pages  = max(1, (int)ceil($sum['cnt'] / $perPage));
$page   = min($page, $pages);
$offset = ($page - 1) * $perPage;

// ---------- Rows for this page ----------
$st = $pdo->prepare("SELECT t.*, wt.name AS type_name
                     FROM transactions t
                     JOIN waste_types wt ON wt.id = t.waste_type_id
                     WHERE $whereSql
                     ORDER BY t.created_at DESC, t.id DESC
                     LIMIT $perPage OFFSET $offset");
$st->execute($params);
$rows = $st->fetchAll();

$types = $pdo->query("SELECT id, name FROM waste_types WHERE is_active=1 ORDER BY name")->fetchAll();

// Build pagination links that keep the filters
function page_link($n) {
  global $from, $to, $type;
  $q = array_filter(['from' => $from, 'to' => $to, 'type' => $type ?: '', 'page' => $n]);
  return 'transactions.php?' . http_build_query($q);
}
$icons = ['Plastic'=>'🧴','Paper'=>'📦','Glass'=>'🍾','Metal'=>'🥫','Others'=>'♻'];
?>

<h1 class="welcome">Transactions</h1>
<p class="sub">View every waste deposit you have made.</p>

<!-- Filters -->
<form method="get" class="panel filter-bar">
  <div><label class="lbl">From</label>
    <input type="date" name="from" class="inp" value="<?= e($from) ?>"></div>
  <div><label class="lbl">To</label>
    <input type="date" name="to" class="inp" value="<?= e($to) ?>"></div>
  <div><label class="lbl">Waste Type</label>
    <select name="type" class="inp">
      <option value="0">All types</option>
      <?php foreach ($types as $t): ?>
        <option value="<?= (int)$t['id'] ?>" <?= $type === (int)$t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?></option>
      <?php endforeach; ?>
    </select></div>
  <div class="fb-btns">
    <button class="btn-sm big" type="submit">Filter</button>
    <a href="transactions.php" class="btn-ghost small">Reset</a>
  </div>
</form>

<!-- Totals -->
<div class="mini-stats">
  <div class="stat"><small>Deposits</small><h3><?= (int)$sum['cnt'] ?></h3></div>
  <div class="stat"><small>Total Weight</small><h3><?= number_format($sum['kg'], 1) ?> kg</h3></div>
  <div class="stat"><small>Total Earned</small><h3>Rp <?= number_format($sum['money'], 0, '.', ',') ?></h3></div>
  <div class="stat"><small>Points Earned</small><h3><?= (int)$sum['pts'] ?> pts</h3></div>
</div>

<!-- Table -->
<div class="panel">
  <div class="tbl-wrap">
    <table class="tbl">
      <thead>
        <tr><th>Date</th><th>Item</th><th>Type</th><th>Weight</th><th>Amount</th><th>Points</th></tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?= date('M d, Y', strtotime($r['created_at'])) ?><small><?= date('H:i', strtotime($r['created_at'])) ?></small></td>
            <td><span class="ti"><?= $icons[$r['type_name']] ?? '♻' ?></span> <?= e($r['item_name'] ?: $r['type_name']) ?></td>
            <td><span class="tag"><?= e($r['type_name']) ?></span></td>
            <td><?= number_format($r['weight_kg'], 2) ?> kg</td>
            <td class="plus">+Rp <?= number_format($r['amount'], 0, '.', ',') ?></td>
            <td><?= (int)$r['points_earned'] ?> pts</td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?>
          <tr><td colspan="6" class="empty" style="text-align:center;padding:30px">No transactions found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php if ($pages > 1): ?>
    <div class="pager">
      <?php if ($page > 1): ?><a href="<?= e(page_link($page - 1)) ?>">‹</a><?php endif; ?>
      <?php for ($i = max(1, $page - 2); $i <= min($pages, $page + 2); $i++): ?>
        <a href="<?= e(page_link($i)) ?>" class="<?= $i === $page ? 'on' : '' ?>"><?= $i ?></a>
      <?php endfor; ?>
      <?php if ($page < $pages): ?><a href="<?= e(page_link($page + 1)) ?>">›</a><?php endif; ?>
    </div>
  <?php endif; ?>
</div>

<?php require 'footer.php'; ?>