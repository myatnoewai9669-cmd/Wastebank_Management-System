<?php
require_once 'db.php';
require_role(['admin']);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $id     = (int)($_POST['id'] ?? 0);
  $name   = trim($_POST['name'] ?? '');
  $desc   = trim($_POST['description'] ?? '');
  $price  = (float)($_POST['price_per_kg'] ?? -1);
  $points = (int)($_POST['points_per_kg'] ?? -1);
  $active = isset($_POST['is_active']) ? 1 : 0;

  if ($name === '' || strlen($name) > 50)   $errors[] = 'Please enter a valid name (max 50 characters)';
  if ($price < 0 || $price > 1000000)        $errors[] = 'Price per kg must be between 0 and 1,000,000';
  if ($points < 0 || $points > 1000)         $errors[] = 'Points per kg must be between 0 and 1,000';
  if (strlen($desc) > 255)                   $errors[] = 'Description is too long';

  if (!$errors) {
    if ($id > 0) {
      $pdo->prepare("UPDATE waste_types SET name=?, description=?, price_per_kg=?, points_per_kg=?, is_active=? WHERE id=?")
          ->execute([$name, $desc, $price, $points, $active, $id]);
      $_SESSION['flash'] = ['ok', 'Waste type updated.'];
    } else {
      $pdo->prepare("INSERT INTO waste_types (name, description, price_per_kg, points_per_kg, is_active) VALUES (?,?,?,?,?)")
          ->execute([$name, $desc, $price, $points, $active]);
      $_SESSION['flash'] = ['ok', 'Waste type added.'];
    }
    header('Location: waste_types.php'); exit;
  }
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$types = $pdo->query("SELECT * FROM waste_types ORDER BY is_active DESC, name")->fetchAll();

$pageTitle = 'Waste Types';
require 'header.php';
?>

<h1 class="welcome">Waste Types</h1>
<p class="sub">Set the price and points for each kind of waste.</p>

<?php if ($flash): ?><div class="notice <?= $flash[0] ?>"><?= e($flash[1]) ?></div><?php endif; ?>
<?php foreach ($errors as $er): ?><div class="notice err"><?= e($er) ?></div><?php endforeach; ?>

<div class="panel" style="margin-bottom:16px">
  <h3 style="margin-bottom:6px">Add Waste Type</h3>
  <form method="post" class="wt-form">
    <input type="hidden" name="csrf" value="<?= csrf() ?>">
    <input type="hidden" name="id" value="0">
    <input class="inp" name="name" placeholder="Name (e.g. Cardboard)" maxlength="50" required>
    <input class="inp" name="description" placeholder="Description" maxlength="255">
    <input class="inp" type="number" name="price_per_kg" placeholder="Rp per kg" min="0" step="100" required>
    <input class="inp" type="number" name="points_per_kg" placeholder="Points per kg" min="0" required>
    <label class="check"><input type="checkbox" name="is_active" checked> Active</label>
    <button class="btn-sm big" type="submit">Add</button>
  </form>
</div>

<div class="panel">
  <h3 style="margin-bottom:6px">All Waste Types</h3>
  <?php foreach ($types as $t): ?>
    <form method="post" class="wt-form wt-row <?= $t['is_active'] ? '' : 'off' ?>">
      <input type="hidden" name="csrf" value="<?= csrf() ?>">
      <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
      <input class="inp" name="name" value="<?= e($t['name']) ?>" maxlength="50" required>
      <input class="inp" name="description" value="<?= e($t['description']) ?>" maxlength="255">
      <input class="inp" type="number" name="price_per_kg" value="<?= (float)$t['price_per_kg'] ?>" min="0" step="100" required>
      <input class="inp" type="number" name="points_per_kg" value="<?= (int)$t['points_per_kg'] ?>" min="0" required>
      <label class="check"><input type="checkbox" name="is_active" <?= $t['is_active'] ? 'checked' : '' ?>> Active</label>
      <button class="btn-sm big" type="submit">Save</button>
    </form>
  <?php endforeach; ?>
</div>

<?php require 'footer.php'; ?>