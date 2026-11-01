<?php
require_once 'db.php';
if (empty($_SESSION['user'])) { header('Location: login.php'); exit; }
if (!in_array($_SESSION['user']['role'], ['staff', 'admin'], true)) {
  header('Location: dashboard.php'); exit;
}
$staffId = $_SESSION['user']['id'];

// Load the member from ?member=MW001
$code = trim($_GET['member'] ?? $_POST['member'] ?? '');
$member = null;
if ($code !== '') {
  $st = $pdo->prepare("SELECT id, name, member_code, balance, points FROM users
                       WHERE member_code=? AND role='member' AND status='active'");
  $st->execute([$code]);
  $member = $st->fetch();
}

$wasteTypes = $pdo->query("SELECT * FROM waste_types WHERE is_active=1 ORDER BY name")->fetchAll();
$errors = [];

// ---------- Save the transaction ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $member) {
  csrf_check();
  $typeId = (int)($_POST['waste_type_id'] ?? 0);
  $weight = round((float)($_POST['weight'] ?? 0), 2);
  $note   = trim($_POST['note'] ?? '');

  $type = null;
  foreach ($wasteTypes as $t) if ((int)$t['id'] === $typeId) $type = $t;

  if (!$type)                         $errors[] = 'Please select a waste type';
  if ($weight <= 0 || $weight > 500)  $errors[] = 'Weight must be between 0.01 and 500 kg';
  if (strlen($note) > 255)            $errors[] = 'Note is too long (max 255 characters)';

  if (!$errors) {
    $amount = round($weight * $type['price_per_kg'], 2);
    $points = (int)floor($weight * $type['points_per_kg']);

    try {
      $pdo->beginTransaction();

      $pdo->prepare("INSERT INTO transactions
          (user_id, staff_id, waste_type_id, item_name, weight_kg, amount, points_earned, note)
          VALUES (?,?,?,?,?,?,?,?)")
          ->execute([$member['id'], $staffId, $type['id'], $type['name'],
                     $weight, $amount, $points, $note]);
      $txId = $pdo->lastInsertId();

      $pdo->prepare("UPDATE users SET balance = balance + ?, points = points + ? WHERE id=?")
          ->execute([$amount, $points, $member['id']]);

      $pdo->prepare("INSERT INTO points_ledger (user_id, change_points, reason, ref_id)
                     VALUES (?,?, 'transaction', ?)")
          ->execute([$member['id'], $points, $txId]);

      $pdo->commit();
      $_SESSION['flash'] = ['ok', 'Saved: ' . number_format($weight, 2) . ' kg of ' . $type['name'] .
                            ' for ' . $member['name'] . ' (+Rp ' . number_format($amount, 0, '.', ',') .
                            ', +' . $points . ' pts)'];
      header('Location: scan.php'); exit;
    } catch (Exception $ex) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      $errors[] = 'Could not save the transaction, please try again';
    }
  }
}

// Flash message from a previous save
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$pageTitle = 'Add Waste';
require 'header.php';
?>

<h1 class="welcome">Add Waste / Record Transaction</h1>
<p class="sub">Record the waste a member brought in.</p>

<?php if ($flash): ?><div class="notice <?= $flash[0] ?>"><?= e($flash[1]) ?></div><?php endif; ?>
<?php foreach ($errors as $er): ?><div class="notice err"><?= e($er) ?></div><?php endforeach; ?>

<?php if (!$member): ?>
  <div class="panel">
    <p class="empty"><?= $code !== '' ? 'Member "' . e($code) . '" was not found.' : 'No member selected.' ?></p>
    <a href="scan.php" class="btn-sm" style="display:inline-block;margin-top:12px">Scan a member QR</a>
  </div>
<?php else: ?>
  <div class="panel aw-card">
    <h3>Member Information</h3>
    <div class="qr-user">
      <div class="avatar big"><?= e(strtoupper(substr($member['name'], 0, 1))) ?></div>
      <div>
        <strong><?= e($member['name']) ?></strong>
        <small>Member ID: <?= e($member['member_code']) ?> •
               Balance: Rp <?= number_format($member['balance'], 0, '.', ',') ?> •
               <?= (int)$member['points'] ?> pts</small>
      </div>
    </div>

    <form method="post" id="awForm">
      <input type="hidden" name="csrf" value="<?= csrf() ?>">
      <input type="hidden" name="member" value="<?= e($member['member_code']) ?>">

      <label class="lbl">Waste Type</label>
      <select name="waste_type_id" id="wtype" class="inp" required>
        <option value="">Select waste type</option>
        <?php foreach ($wasteTypes as $t): ?>
          <option value="<?= (int)$t['id'] ?>"
                  data-price="<?= (float)$t['price_per_kg'] ?>"
                  data-points="<?= (int)$t['points_per_kg'] ?>"
                  <?= ((int)($_POST['waste_type_id'] ?? 0) === (int)$t['id']) ? 'selected' : '' ?>>
            <?= e($t['name']) ?> (Rp <?= number_format($t['price_per_kg'], 0, '.', ',') ?>/kg)
          </option>
        <?php endforeach; ?>
      </select>

      <label class="lbl">Weight (kg)</label>
      <input type="number" name="weight" id="weight" class="inp" step="0.01" min="0.01" max="500"
             placeholder="0.00" value="<?= e($_POST['weight'] ?? '') ?>" required>

      <div class="total-box">
        <div><small>Total Value</small><strong id="total">Rp 0</strong></div>
        <div><small>Points Earned</small><strong id="pts">0 pts</strong></div>
      </div>

      <label class="lbl">Notes <small>(optional)</small></label>
      <textarea name="note" class="inp" rows="3" maxlength="255"
                placeholder="e.g. clean and dry bottles"><?= e($_POST['note'] ?? '') ?></textarea>

      <button class="btn-main" type="submit">Add Transaction</button>
    </form>
  </div>
<?php endif; ?>

<script src="addwaste.js"></script>
<?php require 'footer.php'; ?>
