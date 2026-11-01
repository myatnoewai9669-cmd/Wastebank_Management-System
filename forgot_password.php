<?php
require_once 'db.php';

const CODE_LENGTH    = 6;
const CODE_MINUTES   = 10;
const MAX_TRIES      = 5;
const CODES_PER_HOUR = 3;

// True when the mail files exist (no PHPMailer needed any more)
function mail_ready() {
  return is_file(__DIR__ . '/mail.php') && is_file(__DIR__ . '/mail_config.php');
}

function clear_fp() {
  foreach (['fp_email', 'fp_verified', 'fp_dev_code', 'fp_info'] as $k) unset($_SESSION[$k]);
}

// Create a new code for this email (only if the account exists and the rate limit allows)
function issue_code(PDO $pdo, $email) {
  unset($_SESSION['fp_dev_code']);

  $st = $pdo->prepare("SELECT id, name FROM users WHERE email=? AND status='active'");
  $st->execute([$email]);
  $user = $st->fetch();
  if (!$user) return;                       // do nothing, but the page looks the same

  $st = $pdo->prepare("SELECT COUNT(*) FROM password_codes
                       WHERE user_id=? AND created_at >= NOW() - INTERVAL 1 HOUR");
  $st->execute([$user['id']]);
  if ((int)$st->fetchColumn() >= CODES_PER_HOUR) return;

  $pdo->prepare("DELETE FROM password_codes WHERE user_id=? AND used_at IS NULL")->execute([$user['id']]);

  $code = str_pad((string)random_int(0, 10 ** CODE_LENGTH - 1), CODE_LENGTH, '0', STR_PAD_LEFT);
  $pdo->prepare("INSERT INTO password_codes (user_id, code_hash, expires_at)
                 VALUES (?, ?, DATE_ADD(NOW(), INTERVAL " . CODE_MINUTES . " MINUTE))")
      ->execute([$user['id'], password_hash($code, PASSWORD_DEFAULT)]);

  // Email the code straight to the member's inbox
  if (mail_ready()) {
    require_once 'mail.php';
    send_mail($email, 'Your WasteBank reset code: ' . $code,
      "Hello " . $user['name'] . ",\n\nYour WasteBank password reset code is:\n\n    " . $code .
      "\n\nThis code works for " . CODE_MINUTES . " minutes. If you did not request it, ignore this email.\n");
  }

  // Local testing only: show the code on screen
  if (APP_DEBUG) $_SESSION['fp_dev_code'] = $code;
}

// Start over
if (isset($_GET['restart'])) { clear_fp(); header('Location: forgot_password.php'); exit; }

$error  = '';
$done   = false;
$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();

  // ---- Step 1: email ----
  if ($action === 'email') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $error = 'Please enter a valid email address.';
    } else {
      clear_fp();
      $_SESSION['fp_email'] = $email;
      issue_code($pdo, $email);
      header('Location: forgot_password.php'); exit;
    }
  }

  // ---- Resend the code ----
  elseif ($action === 'resend' && !empty($_SESSION['fp_email'])) {
    unset($_SESSION['fp_verified']);
    issue_code($pdo, $_SESSION['fp_email']);
    $_SESSION['fp_info'] = 'If the email is registered, a new code has been sent.';
    header('Location: forgot_password.php'); exit;
  }

  // ---- Step 2: verify the code ----
  elseif ($action === 'otp' && !empty($_SESSION['fp_email'])) {
    $entered = preg_replace('/\D/', '', implode('', (array)($_POST['otp'] ?? [])));

    if (strlen($entered) !== CODE_LENGTH) {
      $error = 'Please enter the ' . CODE_LENGTH . '-digit code.';
    } else {
      try {
        $pdo->beginTransaction();
        $st = $pdo->prepare("SELECT c.id, c.user_id, c.code_hash, c.attempts
                             FROM users u JOIN password_codes c ON c.user_id = u.id
                             WHERE u.email=? AND u.status='active'
                               AND c.used_at IS NULL AND c.expires_at > NOW()
                             ORDER BY c.id DESC LIMIT 1 FOR UPDATE");
        $st->execute([$_SESSION['fp_email']]);
        $row = $st->fetch();

        if (!$row || (int)$row['attempts'] >= MAX_TRIES) {
          $error = 'This code is invalid or has expired. Please resend a new code.';
        } elseif (!password_verify($entered, $row['code_hash'])) {
          $pdo->prepare("UPDATE password_codes SET attempts = attempts + 1 WHERE id=?")->execute([$row['id']]);
          $left = max(0, MAX_TRIES - 1 - (int)$row['attempts']);
          $error = 'Invalid code. ' . $left . ' attempt' . ($left === 1 ? '' : 's') . ' left.';
        } else {
          $_SESSION['fp_verified'] = ['code_id' => (int)$row['id'], 'user_id' => (int)$row['user_id']];
        }
        $pdo->commit();
      } catch (Exception $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = 'Something went wrong. Please try again.';
      }
      if (!$error) { header('Location: forgot_password.php'); exit; }
    }
  }

  // ---- Step 3: new password (only after the code was verified) ----
  elseif ($action === 'password' && !empty($_SESSION['fp_verified'])) {
    $pass    = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';

    if (strlen($pass) < 8)         $error = 'Password must be at least 8 characters.';
    elseif ($pass !== $confirm)    $error = 'Passwords do not match.';
    else {
      try {
        $pdo->beginTransaction();
        $v  = $_SESSION['fp_verified'];
        $st = $pdo->prepare("SELECT id FROM password_codes
                             WHERE id=? AND user_id=? AND used_at IS NULL AND expires_at > NOW() FOR UPDATE");
        $st->execute([$v['code_id'], $v['user_id']]);

        if (!$st->fetch()) {
          $pdo->rollBack();
          clear_fp();
          $error = 'Your session expired. Please start again.';
        } else {
          $pdo->prepare("UPDATE users SET password_hash=? WHERE id=?")
              ->execute([password_hash($pass, PASSWORD_DEFAULT), $v['user_id']]);
          $pdo->prepare("UPDATE password_codes SET used_at=NOW() WHERE id=?")->execute([$v['code_id']]);
          $pdo->prepare("DELETE FROM password_codes WHERE user_id=? AND used_at IS NULL")->execute([$v['user_id']]);
          $pdo->commit();
          clear_fp();
          $done = true;
        }
      } catch (Exception $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = 'Could not change the password. Please try again.';
      }
    }
  }
}

$info = $_SESSION['fp_info'] ?? '';
unset($_SESSION['fp_info']);

// Decide which step to show
if ($done)                                $step = 4;
elseif (!empty($_SESSION['fp_verified'])) $step = 3;
elseif (!empty($_SESSION['fp_email']))    $step = 2;
else                                      $step = 1;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Forgot Password - WasteBank</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --g:#1a8a3e;--g-dark:#0b4d2c;--g-soft:#d9f2dc;--g-mid:#8fd19e;
  --text:#1f2d24;--muted:#6b7a70;--border:#e0e8e2;--white:#fff;
}
body{
  font-family:'Poppins','Segoe UI',Arial,sans-serif;color:var(--text);
  background:linear-gradient(135deg,var(--g-soft),var(--g-mid),#3fa763,var(--g-soft));
  background-size:300% 300%;animation:bgmove 14s ease infinite;
  min-height:100vh;display:flex;align-items:center;justify-content:center;padding:1rem;
}
@keyframes bgmove{0%{background-position:0 50%}50%{background-position:100% 50%}100%{background-position:0 50%}}
.card{
  width:100%;max-width:400px;background:var(--white);border-radius:28px;
  box-shadow:0 20px 60px rgba(11,77,44,.25);overflow:hidden;
  animation:cardUp .6s cubic-bezier(.22,1,.36,1) both;
}
@keyframes cardUp{from{opacity:0;transform:translateY(30px)}to{opacity:1;transform:translateY(0)}}
.head{background:linear-gradient(135deg,var(--g-dark),var(--g));padding:2rem 2rem 3rem;position:relative}
.head::after{content:'';position:absolute;bottom:-1px;left:0;right:0;height:34px;background:var(--white);border-radius:34px 34px 0 0}
.back{display:inline-block;color:rgba(255,255,255,.8);font-size:.8rem;margin-bottom:.8rem;text-decoration:none}
.back:hover{color:#fff}
.brand{color:rgba(255,255,255,.8);font-size:.72rem;font-weight:600;letter-spacing:.06em;margin-bottom:4px}
.title{color:#fff;font-size:1.7rem;font-weight:700}
.dots{display:flex;align-items:center;position:absolute;top:1.2rem;right:1.5rem}
.dot{width:8px;height:8px;border-radius:50%;background:rgba(255,255,255,.35);transition:all .3s}
.dot+.dot{margin-left:5px}
.dot.active{background:#fff;width:20px;border-radius:4px}
.dot.done{background:#8fe3a8}
.body{padding:.5rem 2rem 2rem}
.icon-wrap{text-align:center;padding:1.2rem 0 .8rem}
.icon{
  width:85px;height:85px;border-radius:50%;display:flex;align-items:center;justify-content:center;
  font-size:2.2rem;margin:0 auto .8rem;background:linear-gradient(135deg,#f1fbf3,var(--g-soft));
  border:2px solid var(--g-mid);animation:bounce 2s ease-in-out infinite;
}
@keyframes bounce{0%,100%{transform:translateY(0)}50%{transform:translateY(-8px)}}
.sub{font-size:.8rem;color:var(--muted);line-height:1.5;margin-bottom:1.2rem;text-align:center}
.sub strong{color:var(--g-dark);word-break:break-all}
.err{background:#fdecea;color:#b71c1c;padding:.6rem .9rem;border-radius:10px;font-size:.8rem;margin-bottom:1rem;animation:shake .4s ease}
.okmsg{background:#e0f5e6;color:#1a7a35;padding:.6rem .9rem;border-radius:10px;font-size:.8rem;margin-bottom:1rem}
@keyframes shake{0%,100%{transform:translateX(0)}20%,60%{transform:translateX(-5px)}40%,80%{transform:translateX(5px)}}
.field{margin-bottom:1rem}
.field label{display:block;font-size:.7rem;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:5px}
.in{display:flex;align-items:center;gap:8px;border:1.5px solid var(--border);border-radius:12px;padding:0 .9rem;background:#fafcfb;transition:all .2s}
.in:focus-within{border-color:var(--g);box-shadow:0 0 0 3px rgba(26,138,62,.15);background:#fff}
.in span{font-size:.95rem;color:#9aa8a0}
.in input{flex:1;border:0;outline:0;padding:.75rem 0;font-size:.9rem;font-family:inherit;background:transparent;min-width:0}
.otp{display:flex;justify-content:center;gap:8px;margin-bottom:1.2rem}
.otp input{
  width:44px;height:54px;border:2px solid var(--border);border-radius:12px;text-align:center;
  font-size:1.35rem;font-weight:700;font-family:inherit;color:var(--g-dark);outline:none;background:#fafcfb;transition:all .2s;
}
.otp input:focus{border-color:var(--g);box-shadow:0 0 0 3px rgba(26,138,62,.15);background:#fff}
.demo{text-align:center;font-size:.72rem;color:var(--muted);background:#f6faf7;border:1px dashed var(--border);border-radius:8px;padding:6px 12px;margin-bottom:1rem}
.demo strong{color:var(--g-dark);font-size:1rem;letter-spacing:.2em}
.resend{text-align:center;font-size:.75rem;color:var(--muted);margin-top:.9rem}
.linkbtn{background:none;border:0;color:var(--g);font-weight:600;font-size:.75rem;cursor:pointer;font-family:inherit}
.linkbtn:hover{text-decoration:underline}
.foot{text-align:center;font-size:.78rem;color:var(--muted);margin-top:1.2rem}
.foot a{color:var(--g);font-weight:600;text-decoration:none}
.bar{height:3px;background:var(--border);border-radius:3px;margin-top:6px;overflow:hidden}
.fill{height:100%;width:0;border-radius:3px;transition:width .4s,background .4s}
.hint{font-size:.65rem;color:var(--muted);margin-top:3px}
.btn{
  display:block;width:100%;padding:.85rem;background:linear-gradient(135deg,var(--g),var(--g-dark));color:#fff;border:0;border-radius:14px;
  font-size:.95rem;font-weight:700;font-family:inherit;cursor:pointer;text-align:center;text-decoration:none;
  transition:transform .2s,box-shadow .2s,filter .2s;
}
.btn:hover{transform:translateY(-2px);box-shadow:0 8px 22px rgba(26,138,62,.4);filter:brightness(1.05)}
.btn:active{transform:scale(.98)}
.ok{text-align:center;padding:1.5rem 0}
.ok-circle{
  width:90px;height:90px;border-radius:50%;background:linear-gradient(135deg,var(--g),#4cd37b);color:#fff;
  display:flex;align-items:center;justify-content:center;font-size:2.5rem;margin:0 auto 1.2rem;
  box-shadow:0 0 40px rgba(26,138,62,.4);animation:pop .6s cubic-bezier(.22,1,.36,1) both;
}
@keyframes pop{from{transform:scale(0) rotate(-45deg);opacity:0}70%{transform:scale(1.1)}to{transform:scale(1);opacity:1}}
.ok h3{font-size:1.3rem;color:var(--g-dark);margin-bottom:6px}
.ok p{font-size:.82rem;color:var(--muted);margin-bottom:1.5rem;line-height:1.5}
@media (prefers-reduced-motion:reduce){*{animation:none!important}}
</style>
</head>
<body>
<div class="card">

<?php
function dots($n) {
  echo '<div class="dots">';
  for ($i = 1; $i <= 4; $i++) {
    $c = $i < $n ? 'done' : ($i === $n ? 'active' : '');
    echo "<div class=\"dot $c\"></div>";
  }
  echo '</div>';
}
?>

<?php if ($step === 1): ?>
  <div class="head">
    <?php dots(1); ?>
    <a href="login.php" class="back">&larr; Back</a>
    <div class="brand">WASTEBANK</div>
    <div class="title">Reset Password</div>
  </div>
  <div class="body">
    <div class="icon-wrap"><div class="icon">&#128272;</div></div>
    <div class="sub">Enter your email address to<br>receive a verification code</div>
    <?php if ($error): ?><div class="err"><?= e($error) ?></div><?php endif; ?>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= csrf() ?>">
      <input type="hidden" name="action" value="email">
      <div class="field">
        <label for="email">Email Address</label>
        <div class="in"><span>&#9993;</span>
          <input id="email" type="email" name="email" value="<?= e($_POST['email'] ?? '') ?>" placeholder="Enter your email" autocomplete="email" required>
        </div>
      </div>
      <button class="btn" type="submit">Send Code &rarr;</button>
    </form>
    <div class="foot">Remember your password? <a href="login.php">Sign In</a></div>
  </div>

<?php elseif ($step === 2): ?>
  <div class="head">
    <?php dots(2); ?>
    <a href="forgot_password.php?restart=1" class="back">&larr; Change email</a>
    <div class="brand">VERIFICATION</div>
    <div class="title">Verify Your Email</div>
  </div>
  <div class="body">
    <div class="icon-wrap"><div class="icon">&#128231;</div></div>
    <div class="sub">If <strong><?= e($_SESSION['fp_email'] ?? '') ?></strong> is registered,<br>we sent a <?= CODE_LENGTH ?>-digit code to it.</div>
    <?php if ($info): ?><div class="okmsg"><?= e($info) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="err"><?= e($error) ?></div><?php endif; ?>
    <?php if (APP_DEBUG && !empty($_SESSION['fp_dev_code'])): ?>
      <div class="demo">Demo code: <strong><?= e($_SESSION['fp_dev_code']) ?></strong></div>
    <?php endif; ?>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= csrf() ?>">
      <input type="hidden" name="action" value="otp">
      <div class="otp">
        <?php for ($i = 0; $i < CODE_LENGTH; $i++): ?>
          <input type="text" name="otp[]" maxlength="1" inputmode="numeric" autocomplete="<?= $i === 0 ? 'one-time-code' : 'off' ?>" required <?= $i === 0 ? 'autofocus' : '' ?>>
        <?php endfor; ?>
      </div>
      <button class="btn" type="submit">Verify &rarr;</button>
    </form>
    <form method="post" class="resend">
      <input type="hidden" name="csrf" value="<?= csrf() ?>">
      <input type="hidden" name="action" value="resend">
      Didn't receive the code? <button type="submit" class="linkbtn">Resend</button>
    </form>
  </div>

<?php elseif ($step === 3): ?>
  <div class="head">
    <?php dots(3); ?>
    <div class="brand">ALMOST DONE</div>
    <div class="title">New Password</div>
  </div>
  <div class="body">
    <div class="icon-wrap"><div class="icon">&#128273;</div></div>
    <div class="sub">Your new password must be at least<br>8 characters long.</div>
    <?php if ($error): ?><div class="err"><?= e($error) ?></div><?php endif; ?>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= csrf() ?>">
      <input type="hidden" name="action" value="password">
      <div class="field">
        <label for="pwd">New Password</label>
        <div class="in"><span>&#128274;</span>
          <input id="pwd" type="password" name="password" placeholder="Enter new password" minlength="8" autocomplete="new-password" required>
        </div>
        <div class="bar"><div class="fill" id="fill"></div></div>
        <div class="hint" id="hint">Enter a password</div>
      </div>
      <div class="field">
        <label for="cf">Confirm Password</label>
        <div class="in"><span>&#128274;</span>
          <input id="cf" type="password" name="confirm" placeholder="Confirm new password" minlength="8" autocomplete="new-password" required>
        </div>
      </div>
      <button class="btn" type="submit">Save &rarr;</button>
    </form>
  </div>

<?php else: ?>
  <div class="head" style="padding-bottom:2.5rem">
    <?php dots(4); ?>
    <div class="brand">ALL DONE!</div>
    <div class="title">Password Changed</div>
  </div>
  <div class="body">
    <div class="ok">
      <div class="ok-circle">&#10003;</div>
      <h3>Password Changed!</h3>
      <p>Your password has been changed successfully.<br>You can now sign in.</p>
      <a href="login.php?reset=1" class="btn">Back to Sign In &rarr;</a>
    </div>
  </div>
<?php endif; ?>

</div>

<script>
// Code boxes: auto-advance, backspace, paste
var boxes = document.querySelectorAll('.otp input');
boxes.forEach(function (inp, i) {
  inp.addEventListener('input', function () {
    this.value = this.value.replace(/\D/g, '');
    if (this.value && i < boxes.length - 1) boxes[i + 1].focus();
  });
  inp.addEventListener('keydown', function (e) {
    if (e.key === 'Backspace' && !this.value && i > 0) boxes[i - 1].focus();
  });
  inp.addEventListener('paste', function (e) {
    var t = (e.clipboardData.getData('text') || '').replace(/\D/g, '').slice(0, boxes.length);
    if (!t) return;
    e.preventDefault();
    t.split('').forEach(function (ch, k) { if (boxes[k]) boxes[k].value = ch; });
    boxes[Math.min(t.length, boxes.length - 1)].focus();
  });
});

// Password strength
var pwd = document.getElementById('pwd');
if (pwd) {
  pwd.addEventListener('input', function () {
    var v = this.value, s = 0;
    if (v.length >= 8) s++;
    if (v.length >= 12) s++;
    if (/[A-Z]/.test(v) && /[a-z]/.test(v)) s++;
    if (/[0-9]/.test(v)) s++;
    if (/[^A-Za-z0-9]/.test(v)) s++;
    var lv = [
      { w: '0%',   c: 'transparent', t: 'Enter a password' },
      { w: '25%',  c: '#ef4444',     t: 'Weak' },
      { w: '50%',  c: '#f97316',     t: 'Fair' },
      { w: '75%',  c: '#eab308',     t: 'Good' },
      { w: '100%', c: '#22c55e',     t: 'Strong' }
    ][Math.min(s, 4)];
    var f = document.getElementById('fill'), h = document.getElementById('hint');
    f.style.width = lv.w; f.style.background = lv.c;
    h.textContent = lv.t; h.style.color = lv.c === 'transparent' ? '' : lv.c;
  });
}
</script>
</body>
</html>