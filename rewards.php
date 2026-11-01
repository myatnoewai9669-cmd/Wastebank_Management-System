<?php
require_once 'db.php';

if (empty($_SESSION['user'])) { header('Location: login.php'); exit; }
$uid = $_SESSION['user']['id'];

// ---------- Redeem a reward ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $rewardId = (int)($_POST['reward_id'] ?? 0);

  try {
    $pdo->beginTransaction();

    // Lock the user row and the reward row so nothing changes mid-way
    $st = $pdo->prepare("SELECT points FROM users WHERE id=? FOR UPDATE");
    $st->execute([$uid]);
    $points = (int)$st->fetchColumn();

    $st = $pdo->prepare("SELECT * FROM rewards WHERE id=? AND is_active=1 FOR UPDATE");
    $st->execute([$rewardId]);
    $reward = $st->fetch();

    if (!$reward)                          throw new Exception('Reward not found.');
    if ($reward['stock'] <= 0)             throw new Exception('Sorry, this reward is out of stock.');
    if ($points < $reward['points_cost'])  throw new Exception('You do not have enough points for this reward.');

    $cost = (int)$reward['points_cost'];

    $pdo->prepare("INSERT INTO redemptions (user_id, reward_id, points_used) VALUES (?,?,?)")
        ->execute([$uid, $rewardId, $cost]);
    $redemptionId = $pdo->lastInsertId();

    $pdo->prepare("UPDATE users SET points = points - ? WHERE id=?")->execute([$cost, $uid]);
    $pdo->prepare("UPDATE rewards SET stock = stock - 1 WHERE id=?")->execute([$rewardId]);
    $pdo->prepare("INSERT INTO points_ledger (user_id, change_points, reason, ref_id)
                   VALUES (?,?, 'redeem', ?)")->execute([$uid, -$cost, $redemptionId]);

    $pdo->commit();
    $_SESSION['flash'] = ['ok', 'Redeemed "' . $reward['title'] . '" successfully!'];
  } catch (Exception $ex) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $_SESSION['flash'] = ['err', $ex->getMessage()];
  }
  header('Location: rewards.php'); exit;
}

// Flash message (shown once)
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// Current points
$st = $pdo->prepare("SELECT points FROM users WHERE id=?");
$st->execute([$uid]);
$points = (int)$st->fetchColumn();

// Rewards catalog
$rewards = $pdo->query("SELECT * FROM rewards WHERE is_active=1 ORDER BY points_cost ASC")->fetchAll();

// Next reward: the cheapest one the user cannot afford yet
$next = null;
foreach ($rewards as $r) {
  if ($r['points_cost'] > $points) { $next = $r; break; }
}
$progress = $next ? min(100, round($points / $next['points_cost'] * 100)) : 100;

// Redemption history
$st = $pdo->prepare("SELECT rd.*, r.title FROM redemptions rd
                     JOIN rewards r ON r.id = rd.reward_id
                     WHERE rd.user_id=? ORDER BY rd.created_at DESC LIMIT 5");
$st->execute([$uid]);
$history = $st->fetchAll();

function reward_icon($title) {
  $t = strtolower($title);
  if (str_contains($t, 'voucher')) return '🎟';
  if (str_contains($t, 'bag'))     return '👜';
  if (str_contains($t, 'tumbler')) return '🥤';
  if (str_contains($t, 'tree'))    return '🌳';
  return '🎁';
}

$pageTitle = 'Rewards & Points';
require 'header.php';
?>

<h1 class="welcome">Rewards &amp; Points</h1>
<p class="sub">Collect points for your contribution and redeem exciting rewards.</p>

<?php if ($flash): ?><div class="notice <?= $flash[0] ?>"><?= e($flash[1]) ?></div><?php endif; ?>

<div class="rw-top">
  <!-- Points card -->
  <div class="pts-card">
    <div class="pts-star">⭐</div>
    <div>
      <small>Your Points</small>
      <h2><span data-count="<?= $points ?>" data-dec="0">0</span> pts</h2>
      <a href="#catalog" class="btn-light">Redeem Rewards</a>
    </div>
  </div>

  <!-- Next reward -->
  <div class="panel next-card">
    <h3>Next Reward</h3>
    <?php if ($next): ?>
      <div class="next-row">
        <div class="gift"><?= reward_icon($next['title']) ?></div>
        <div>
          <strong><?= e($next['title']) ?></strong>
          <small><?= number_format($next['points_cost']) ?> pts</small>
        </div>
      </div>
      <div class="bar"><span data-w="<?= $progress ?>"></span></div>
      <div class="bar-txt">
        <span><?= $points ?> / <?= (int)$next['points_cost'] ?> pts</span>
        <span><?= (int)$next['points_cost'] - $points ?> pts to go</span>
      </div>
    <?php else: ?>
      <p class="empty">You can already afford every reward. Pick one below!</p>
    <?php endif; ?>
  </div>
</div>

<div class="rw-grid">
  <!-- How to earn -->
  <div class="panel">
    <h3>How to Earn Points</h3>
    <div class="earn">
      <div class="earn-item"><i>♻</i><strong>1 kg</strong><small>= 10 pts</small></div>
      <div class="earn-item"><i>🚚</i><strong>Every Pickup</strong><small>= 50 pts</small></div>
      <div class="earn-item"><i>★</i><strong>Special Event</strong><small>= 100 pts</small></div>
    </div>

    <h3 style="margin-top:24px">My Redemptions</h3>
    <?php if (!$history): ?><p class="empty">No redemptions yet.</p><?php endif; ?>
    <?php foreach ($history as $h): ?>
      <div class="hist">
        <div class="hist-ic"><?= reward_icon($h['title']) ?></div>
        <div class="hist-info">
          <strong><?= e($h['title']) ?></strong>
          <small><?= date('M d, Y', strtotime($h['created_at'])) ?></small>
        </div>
        <div class="hist-right">
          <span class="badge <?= e($h['status']) ?>"><?= e(ucfirst($h['status'])) ?></span>
          <small class="minus">-<?= (int)$h['points_used'] ?> pts</small>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- Catalog -->
  <div class="panel" id="catalog">
    <div class="ph"><h3>Rewards Catalog</h3></div>

    <?php foreach ($rewards as $r):
      $can = $points >= $r['points_cost'] && $r['stock'] > 0; ?>
      <div class="rw-item">
        <div class="gift"><?= reward_icon($r['title']) ?></div>
        <div class="rw-info">
          <strong><?= e($r['title']) ?></strong>
          <small><?= e($r['description']) ?></small>
          <small class="cost"><?= number_format($r['points_cost']) ?> pts
            <?= $r['stock'] <= 0 ? ' • Out of stock' : ($r['stock'] <= 5 ? ' • Only ' . (int)$r['stock'] . ' left' : '') ?></small>
        </div>
        <form method="post" class="redeem-form" data-title="<?= e($r['title']) ?>" data-cost="<?= (int)$r['points_cost'] ?>">
          <input type="hidden" name="csrf" value="<?= csrf() ?>">
          <input type="hidden" name="reward_id" value="<?= (int)$r['id'] ?>">
          <button class="btn-sm" <?= $can ? '' : 'disabled' ?>>Redeem</button>
        </form>
      </div>
    <?php endforeach; ?>
    <?php if (!$rewards): ?><p class="empty">No rewards available right now.</p><?php endif; ?>
  </div>
</div>

<script src="rewards.js"></script>
<?php require 'footer.php'; ?>