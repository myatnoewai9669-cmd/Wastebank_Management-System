<?php
// help-support.php - FAQ, contact form, and the member's own messages
require_once 'db.php';
if (empty($_SESSION['user'])) { header('Location: login.php'); exit; }
$uid = $_SESSION['user']['id'];
$errors = [];

/**
 * Try to email the support team. NEVER throws and NEVER shows technical
 * details to the member. Returns [bool sent, string errorForLog].
 */
function notify_support(PDO $pdo, int $uid, string $subject, string $message): array
{
  try {
    $mailFile = __DIR__ . '/mail.php';
    $confFile = __DIR__ . '/mail_config.php';
    if (!is_file($mailFile) || !is_file($confFile)) {
      return [false, 'mail.php or mail_config.php not found'];
    }

    require_once $mailFile;
    $cfg = require $confFile;
    if (!is_array($cfg) || empty($cfg['support_email'])) {
      return [false, 'support_email missing in mail_config.php'];
    }
    if (!function_exists('send_mail')) {
      return [false, 'send_mail() not defined in mail.php'];
    }

    $st = $pdo->prepare("SELECT name, email, member_code FROM users WHERE id=?");
    $st->execute([$uid]);
    $me = $st->fetch();
    if (!$me) return [false, 'user not found'];

    // prevent header injection
    $cleanSubject = preg_replace('/[\r\n]+/', ' ', $subject);
    $cleanName    = preg_replace('/[\r\n]+/', ' ', $me['name']);
    $cleanEmail   = preg_replace('/[\r\n]+/', '',  $me['email']);

    $body = "New support message from WasteBank\n\n"
          . "From: " . $cleanName . " (" . $me['member_code'] . ")\n"
          . "Email: " . $cleanEmail . "\n"
          . "Subject: " . $cleanSubject . "\n\n"
          . $message . "\n";

    $GLOBALS['mail_error'] = '';
    @set_time_limit(20); // don't let a slow SMTP server hang the page

    $sent = send_mail($cfg['support_email'], '[WasteBank Support] ' . $cleanSubject,
                      $body, $cleanEmail, $cleanName);

    return $sent ? [true, ''] : [false, (string)($GLOBALS['mail_error'] ?? 'Unknown mail error')];
  } catch (Throwable $ex) {
    return [false, $ex->getMessage()];
  }
}

// ---------- Send a support message ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $subject = trim($_POST['subject'] ?? '');
  $message = trim($_POST['message'] ?? '');

  if ($subject === '' || mb_strlen($subject) > 120)          $errors[] = 'Please enter a subject (max 120 characters)';
  if (mb_strlen($message) < 10 || mb_strlen($message) > 2000) $errors[] = 'Message must be between 10 and 2000 characters';

  // Simple spam limit: 5 messages per day
  $st = $pdo->prepare("SELECT COUNT(*) FROM support_messages WHERE user_id=? AND created_at >= NOW() - INTERVAL 1 DAY");
  $st->execute([$uid]);
  if ((int)$st->fetchColumn() >= 5) $errors[] = 'You have reached the daily limit of 5 messages. Please try again tomorrow.';

  if (!$errors) {
    // 1. Save to the database first, so nothing is lost if email fails
    $pdo->prepare("INSERT INTO support_messages (user_id, subject, message) VALUES (?,?,?)")
        ->execute([$uid, $subject, $message]);

    // 2. Email the support team (failure is logged, never shown to the member)
    [$mailSent, $mailErr] = notify_support($pdo, (int)$uid, $subject, $message);

    if (!$mailSent) {
      error_log('[WasteBank] Support email failed: ' . $mailErr);
      // Easy-to-find copy next to this page (open mail_error.log in the Monday folder)
      @file_put_contents(__DIR__ . '/mail_error.log',
        date('Y-m-d H:i:s') . ' | ' . $mailErr . PHP_EOL, FILE_APPEND);
    }

    // The member always sees a friendly message - the message is safely saved.
    // Only in development (APP_DEBUG) do we add a short hint for the developer.
    if ($mailSent) {
      $_SESSION['flash'] = ['ok', 'Your message has been sent. We will get back to you soon.'];
    } else {
      $_SESSION['flash'] = ['ok', 'Your message has been saved. Our team will read it soon.'];
    }
    header('Location: help-support.php'); exit;
  }
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// My previous messages
$st = $pdo->prepare("SELECT * FROM support_messages WHERE user_id=? ORDER BY created_at DESC LIMIT 5");
$st->execute([$uid]);
$mine = $st->fetchAll();

$faq = [
  ['How do I earn points?', 'You earn points for every kg of waste you deposit, plus 50 bonus points for every completed pickup.'],
  ['How do I deposit waste?', 'Open My QR Code and show it to our staff. They scan it, weigh your waste and record the transaction. Your balance and points update immediately.'],
  ['How does a pickup work?', 'Go to Pickup Schedule, choose a date and time slot, and submit. Our team confirms the request, and you get bonus points after it is completed.'],
  ['Can I cancel a pickup?', 'Yes. You can cancel a request from your pickup history while its status is still Pending.'],
  ['How do I redeem rewards?', 'Open Rewards & Points and press Redeem on any reward you have enough points for.'],
  ['How do I change my password?', 'Open My Profile, click Edit Profile, and use the Change Password form.'],
];

$pageTitle = 'Help & Support';
require 'header.php';
?>

<h1 class="welcome">Help &amp; Support</h1>
<p class="sub">Answers to common questions, and a way to reach our team.</p>

<?php if ($flash): ?><div class="notice <?= e($flash[0]) ?>"><?= e($flash[1]) ?></div><?php endif; ?>
<?php foreach ($errors as $er): ?><div class="notice err"><?= e($er) ?></div><?php endforeach; ?>

<div class="adm-grid">
  <!-- FAQ -->
  <div class="panel">
    <h3 style="margin-bottom:12px">Frequently Asked Questions</h3>
    <?php foreach ($faq as $i => [$q, $a]): ?>
      <details class="faq" <?= $i === 0 ? 'open' : '' ?>>
        <summary><?= e($q) ?></summary>
        <p><?= e($a) ?></p>
      </details>
    <?php endforeach; ?>
  </div>

  <div>
    <!-- Contact form -->
    <div class="panel" style="margin-bottom:16px">
      <h3 style="margin-bottom:6px">Contact Support</h3>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= csrf() ?>">
        <label class="lbl">Subject</label>
        <input class="inp" type="text" name="subject" maxlength="120"
               value="<?= e($_POST['subject'] ?? '') ?>" placeholder="What do you need help with?" required>
        <label class="lbl">Message</label>
        <textarea class="inp" name="message" rows="5" maxlength="2000"
                  placeholder="Describe your question or problem..." required><?= e($_POST['message'] ?? '') ?></textarea>
        <button class="btn-main" type="submit">Send Message</button>
      </form>
    </div>

    <!-- My messages -->
    <div class="panel">
      <h3 style="margin-bottom:8px">My Messages</h3>
      <?php if (!$mine): ?><p class="empty">You have not sent any messages yet.</p><?php endif; ?>
      <?php foreach ($mine as $m): ?>
        <div class="tx">
          <div class="tn">
            <strong><?= e($m['subject']) ?></strong>
            <small><?= date('M d, Y H:i', strtotime($m['created_at'])) ?></small>
          </div>
          <span class="badge <?= e($m['status']) ?>"><?= e(ucfirst($m['status'])) ?></span>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Contact details -->
    <div class="panel" style="margin-top:16px">
      <h3 style="margin-bottom:8px">Contact Us</h3>
      <div class="tx"><span class="ti">📍</span><div class="tn"><strong>Address</strong><small>Sukabumi, Indonesia</small></div></div>
      <div class="tx"><span class="ti">✉</span><div class="tn"><strong>Email</strong><small>myatmyatwai97@gmail.com</small></div></div>
      <div class="tx"><span class="ti">🕘</span><div class="tn"><strong>Opening hours</strong><small>Mon - Sat, 08:00 - 17:00</small></div></div>
    </div>
  </div>
</div>

<?php require 'footer.php'; ?>