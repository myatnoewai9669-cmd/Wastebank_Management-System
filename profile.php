<?php
require_once 'db.php';
if (empty($_SESSION['user'])) { header('Location: login.php'); exit; }
$uid = $_SESSION['user']['id'];
$errors = [];

$st = $pdo->prepare("SELECT * FROM users WHERE id=?");
$st->execute([$uid]);
$me = $st->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $action = $_POST['action'] ?? '';

  // ---------- Update profile ----------
  if ($action === 'profile') {
    $name    = trim($_POST['name'] ?? '');
    $phone   = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $avatar  = null;

    if ($name === '' || strlen($name) > 100) $errors[] = 'Please enter a valid name';
    if ($phone !== '' && !preg_match('/^[0-9+\-\s()]{6,20}$/', $phone)) $errors[] = 'Invalid phone number';
    if (strlen($address) > 255) $errors[] = 'Address is too long';

    // Optional avatar upload
    if (!empty($_FILES['avatar']['name']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
      if ($_FILES['avatar']['size'] > 2 * 1024 * 1024) {
        $errors[] = 'Photo must be smaller than 2 MB';
      } else {
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES['avatar']['tmp_name']);
        $ext  = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? null;
        if (!$ext) {
          $errors[] = 'Photo must be a JPG, PNG or WEBP image';
        } else {
          $dir = __DIR__ . '/uploads/avatars';
          if (!is_dir($dir)) mkdir($dir, 0755, true);
          $file = 'u' . $uid . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
          if (move_uploaded_file($_FILES['avatar']['tmp_name'], "$dir/$file")) {
            $avatar = 'uploads/avatars/' . $file;
          } else {
            $errors[] = 'Could not save the photo';
          }
        }
      }
    }

    if (!$errors) {
      $pdo->prepare("UPDATE users SET name=?, phone=?, address=?, avatar=COALESCE(?, avatar) WHERE id=?")
          ->execute([$name, $phone, $address, $avatar, $uid]);
      $_SESSION['user']['name'] = $name;
      $_SESSION['flash'] = ['ok', 'Profile updated successfully.'];
      header('Location: profile.php'); exit;
    }
  }

  // ---------- Change password ----------
  if ($action === 'password') {
    $cur = $_POST['current'] ?? '';
    $new = $_POST['new'] ?? '';
    $con = $_POST['confirm'] ?? '';

    if (!password_verify($cur, $me['password_hash'])) $errors[] = 'Current password is incorrect';
    if (strlen($new) < 8)                              $errors[] = 'New password must be at least 8 characters';
    if ($new !== $con)                                 $errors[] = 'New passwords do not match';

    if (!$errors) {
      $pdo->prepare("UPDATE users SET password_hash=? WHERE id=?")
          ->execute([password_hash($new, PASSWORD_DEFAULT), $uid]);
      session_regenerate_id(true);
      $_SESSION['flash'] = ['ok', 'Password changed successfully.'];
      header('Location: profile.php'); exit;
    }
  }
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// Stats
$st = $pdo->prepare("SELECT COALESCE(SUM(weight_kg),0) FROM transactions WHERE user_id=?");
$st->execute([$uid]);
$kg = (float)$st->fetchColumn();

$st = $pdo->prepare("SELECT COUNT(*) FROM pickup_requests WHERE user_id=? AND status IN ('confirmed','completed')");
$st->execute([$uid]);
$pickups = (int)$st->fetchColumn();
$trees = (int)floor($kg / 2.5);

// Recent transactions
$st = $pdo->prepare("SELECT t.*, wt.name AS type_name FROM transactions t
                     JOIN waste_types wt ON wt.id = t.waste_type_id
                     WHERE t.user_id=? ORDER BY t.created_at DESC LIMIT 6");
$st->execute([$uid]);
$recent = $st->fetchAll();
$icons = ['Plastic'=>'🧴','Paper'=>'📦','Glass'=>'🍾','Metal'=>'🥫','Others'=>'♻'];

$pageTitle = 'My Profile';
require 'header.php';
?>

<h1 class="welcome">Profile &amp; History</h1>
<p class="sub">Manage your personal information and view your transaction history.</p>

<?php if ($flash): ?><div class="notice <?= $flash[0] ?>"><?= e($flash[1]) ?></div><?php endif; ?>
<?php foreach ($errors as $er): ?><div class="notice err"><?= e($er) ?></div><?php endforeach; ?>

<div class="prof-grid">
  <!-- Profile card -->
  <div class="panel prof-card">
    <?php if ($me['avatar']): ?>
      <img class="prof-img" src="<?= e($me['avatar']) ?>" alt="Avatar">
    <?php else: ?>
      <div class="prof-img ph"><?= e(strtoupper(substr($me['name'], 0, 1))) ?></div>
    <?php endif; ?>
    <h2><?= e($me['name']) ?></h2>
    <small class="role"><?= e(ucfirst($me['role'])) ?> • <?= e($me['member_code']) ?></small>

    <div class="p-stats">
      <div><strong><?= number_format($kg, 1) ?></strong><small>kg waste</small></div>
      <div><strong><?= $pickups ?></strong><small>pickups</small></div>
      <div><strong><?= $trees ?></strong><small>trees</small></div>
    </div>

    <button type="button" class="btn-main" id="editBtn">Edit Profile</button>

    <dl class="details">
      <dt>Name</dt><dd><?= e($me['name']) ?></dd>
      <dt>Email</dt><dd><?= e($me['email']) ?></dd>
      <dt>Phone</dt><dd><?= e($me['phone'] ?: '-') ?></dd>
      <dt>Address</dt><dd><?= e($me['address'] ?: '-') ?></dd>
    </dl>
  </div>

  <div>
    <!-- Edit forms (hidden until Edit Profile is clicked) -->
    <div class="panel" id="editPanel" style="display:none;margin-bottom:16px">
      <h3>Edit Profile</h3>
      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= csrf() ?>">
        <input type="hidden" name="action" value="profile">
        <label class="lbl">Full Name</label>
        <input class="inp" type="text" name="name" maxlength="100" value="<?= e($me['name']) ?>" required>
        <label class="lbl">Phone</label>
        <input class="inp" type="tel" name="phone" value="<?= e($me['phone']) ?>">
        <label class="lbl">Address</label>
        <input class="inp" type="text" name="address" maxlength="255" value="<?= e($me['address']) ?>">
        <label class="lbl">Profile Photo <small>(JPG, PNG or WEBP, max 2 MB)</small></label>
        <input class="inp" type="file" name="avatar" accept="image/jpeg,image/png,image/webp">
        <button class="btn-main" type="submit">Save Changes</button>
      </form>

      <h3 style="margin-top:26px">Change Password</h3>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= csrf() ?>">
        <input type="hidden" name="action" value="password">
        <label class="lbl">Current Password</label>
        <input class="inp" type="password" name="current" required>
        <label class="lbl">New Password</label>
        <input class="inp" type="password" name="new" minlength="8" required>
        <label class="lbl">Confirm New Password</label>
        <input class="inp" type="password" name="confirm" minlength="8" required>
        <button class="btn-main" type="submit">Update Password</button>
      </form>
    </div>

    <!-- Transaction history -->
    <div class="panel">
      <div class="ph"><h3>Transaction History</h3><a href="transactions.php">View All</a></div>
      <?php if (!$recent): ?><p class="empty">No transactions yet.</p><?php endif; ?>
      <?php foreach ($recent as $r): ?>
        <div class="tx">
          <span class="ti"><?= $icons[$r['type_name']] ?? '♻' ?></span>
          <div class="tn"><strong><?= e($r['item_name'] ?: $r['type_name']) ?></strong>
            <small><?= number_format($r['weight_kg'], 1) ?> kg</small></div>
          <div class="ta"><strong>Rp <?= number_format($r['amount'], 0, '.', ',') ?></strong>
            <small><?= date('M d, Y', strtotime($r['created_at'])) ?></small></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<script>
  // Show or hide the edit panel. Keep it open if the server returned errors.
  const editPanel = document.getElementById('editPanel');
  document.getElementById('editBtn').addEventListener('click', () => {
    editPanel.style.display = editPanel.style.display === 'none' ? 'block' : 'none';
    editPanel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  });
  <?php if ($errors): ?>editPanel.style.display = 'block';<?php endif; ?>
</script>

<?php require 'footer.php'; ?>