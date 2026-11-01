<?php
require_once 'db.php';

$token  = $_GET['token'] ?? $_POST['token'] ?? '';
$errors = [];
$valid  = false;
$row    = null;

// Check the token: right format, not used, not expired
if (preg_match('/^[a-f0-9]{64}$/', $token)) {
  $st = $pdo->prepare("SELECT id, user_id FROM password_resets
                       WHERE token_hash=? AND used_at IS NULL AND expires_at > NOW()");
  $st->execute([hash('sha256', $token)]);
  $row = $st->fetch();
  $valid = (bool)$row;
}

if ($valid && $_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $pass = $_POST['password'] ?? '';
  $conf = $_POST['confirm'] ?? '';

  if (strlen($pass) < 8) $errors[] = 'Password must be at least 8 characters';
  if ($pass !== $conf)   $errors[] = 'Passwords do not match';

  if (!$errors) {
    try {
      $pdo->beginTransaction();

      // Lock the token row so it can only be used once
      $st = $pdo->prepare("SELECT id, user_id FROM password_resets
                           WHERE id=? AND used_at IS NULL AND expires_at > NOW() FOR UPDATE");
      $st->execute([$row['id']]);
      $locked = $st->fetch();
      if (!$locked) throw new Exception('This reset link is no longer valid.');

      $pdo->prepare("UPDATE users SET password_hash=? WHERE id=?")
          ->execute([password_hash($pass, PASSWORD_DEFAULT), $locked['user_id']]);

      $pdo->prepare("UPDATE password_resets SET used_at=NOW() WHERE id=?")->execute([$locked['id']]);
      // Invalidate any other reset links for this account
      $pdo->prepare("DELETE FROM password_resets WHERE user_id=? AND used_at IS NULL")
          ->execute([$locked['user_id']]);

      $pdo->commit();
      header('Location: login.php?reset=1'); exit;
    } catch (Exception $ex) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      $errors[] = 'Could not reset the password. Please request a new link.';
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Reset Password - Waste Bank</title>
<link rel="stylesheet" href="auth.css">
</head>
<body>
<div class="leaf l1"></div><div class="leaf l2"></div>
<div class="leaf l3"></div><div class="leaf l4"></div>

<main class="card <?= $errors ? 'shake' : '' ?>">
  <div class="brand">
    <svg viewBox="0 0 24 24" width="38" height="38" fill="#1a8a3e"><path d="M17 8C8 10 5.9 16.2 3.8 20.2c1.5.5 3 .8 4.5.8 7 0 12.7-5.5 12.7-14-1 .5-2 .8-3 1zM6 18c1-3.5 4-7 9-8.5-4.2 2-6.8 5-8 8.5z"/></svg>
    <div><h1>Waste Bank</h1><small>Management System</small></div>
  </div>

  <?php if (!$valid): ?>
    <h2>Link Expired</h2>
    <div class="alert">This reset link is invalid or has expired.</div>
    <a href="forgot_password.php" class="btn" style="display:block;text-align:center;text-decoration:none;line-height:1.9;margin-top:18px">Request a New Link</a>
  <?php else: ?>
    <h2>Choose a New Password</h2>
    <?php foreach ($errors as $er): ?><div class="alert"><?= e($er) ?></div><?php endforeach; ?>

    <!-- The form id and field ids match assets/js/auth.js (strength meter + confirm check) -->
    <form method="post" id="regForm">
      <input type="hidden" name="csrf" value="<?= csrf() ?>">
      <input type="hidden" name="token" value="<?= e($token) ?>">

      <label>New Password</label>
      <div class="pw">
        <input type="password" name="password" id="pass" placeholder="At least 8 characters" minlength="8" required>
        <button type="button" class="eye" data-toggle>👁</button>
      </div>
      <div class="meter"><span id="bar"></span></div>

      <label>Confirm New Password</label>
      <div class="pw">
        <input type="password" name="confirm" id="conf" placeholder="Confirm your password" minlength="8" required>
        <button type="button" class="eye" data-toggle>👁</button>
      </div>

      <button class="btn" type="submit">Reset Password</button>
    </form>
  <?php endif; ?>

  <p class="foot"><a href="login.php">← Back to Login</a></p>
</main>
<script src="auth.js"></script>
</body>
</html>