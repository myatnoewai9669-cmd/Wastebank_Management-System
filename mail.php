<?php
// mail.php - one send_mail() for the whole app
//   * to = support_email  -> Formspree (HTTP)         [Help & Support]
//   * to = anyone else    -> Gmail SMTP via PHPMailer [Forgot Password]
// On failure it returns false and puts the reason in $GLOBALS['mail_error'].

function send_mail(string $to, string $subject, string $body, string $replyEmail = '', string $replyName = ''): bool
{
  $GLOBALS['mail_error'] = '';
  $cfg = require __DIR__ . '/mail_config.php';

  if (strcasecmp($to, $cfg['support_email'] ?? '') === 0) {
    return mail_via_formspree($cfg, $subject, $body, $replyEmail, $replyName);
  }
  return mail_via_smtp($cfg, $to, $subject, $body, $replyEmail, $replyName);
}

function mail_is_local(): bool
{
  $h = $_SERVER['HTTP_HOST'] ?? '';
  return strpos($h, 'localhost') === 0 || strpos($h, '127.0.0.1') === 0;
}

// ---------------------------------------------------------------- Formspree
function mail_via_formspree(array $cfg, string $subject, string $body, string $replyEmail, string $replyName): bool
{
  $endpoint = $cfg['formspree_endpoint'] ?? '';
  if ($endpoint === '' || strpos($endpoint, 'PASTE_') !== false) {
    $GLOBALS['mail_error'] = 'formspree_endpoint is not set in mail_config.php';
    return false;
  }
  if (!function_exists('curl_init')) {
    $GLOBALS['mail_error'] = 'PHP cURL extension is disabled (enable extension=curl in php.ini)';
    return false;
  }

  $payload = json_encode([
    'name'     => $replyName,
    'email'    => $replyEmail,
    '_subject' => $subject,
    'message'  => $body,
  ], JSON_UNESCAPED_UNICODE);

  $run = function (bool $verify) use ($endpoint, $payload) {
    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
      CURLOPT_POST           => true,
      CURLOPT_POSTFIELDS     => $payload,
      CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
      CURLOPT_USERAGENT      => 'WasteBank/1.0 (PHP)',
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_CONNECTTIMEOUT => 8,
      CURLOPT_TIMEOUT        => 15,
      CURLOPT_SSL_VERIFYPEER => $verify,
      CURLOPT_SSL_VERIFYHOST => $verify ? 2 : 0,
    ]);
    $res = curl_exec($ch);
    $out = [$res, (int)curl_getinfo($ch, CURLINFO_HTTP_CODE), curl_error($ch), curl_errno($ch)];
    curl_close($ch);
    return $out;
  };

  [$res, $code, $err, $errno] = $run(true);
  // XAMPP often has no CA bundle; retry without the check on localhost only
  if ($res === false && in_array($errno, [35, 60, 77], true) && mail_is_local()) {
    [$res, $code, $err, $errno] = $run(false);
  }

  if ($res === false) { $GLOBALS['mail_error'] = 'Formspree connection failed: ' . $err; return false; }
  if ($code >= 200 && $code < 300) return true;

  $GLOBALS['mail_error'] = 'Formspree HTTP ' . $code . ': ' . substr((string)$res, 0, 300);
  return false;
}

// ---------------------------------------------------------------- Gmail SMTP
function mail_via_smtp(array $cfg, string $to, string $subject, string $body, string $replyEmail, string $replyName): bool
{
  $dir = __DIR__ . '/PHPMailer/src/';
  if (!is_file($dir . 'PHPMailer.php')) {
    $GLOBALS['mail_error'] = 'PHPMailer folder not found (expected PHPMailer/src/PHPMailer.php)';
    return false;
  }
  if (empty($cfg['smtp_pass']) || strpos($cfg['smtp_pass'], 'PASTE_') !== false) {
    $GLOBALS['mail_error'] = 'smtp_pass (Gmail App Password) is not set in mail_config.php';
    return false;
  }

  require_once $dir . 'Exception.php';
  require_once $dir . 'PHPMailer.php';
  require_once $dir . 'SMTP.php';

  try {
    $m = new PHPMailer\PHPMailer\PHPMailer(true);
    $m->isSMTP();
    $m->Host       = $cfg['smtp_host'] ?? 'smtp.gmail.com';
    $m->Port       = (int)($cfg['smtp_port'] ?? 587);
    $m->SMTPAuth   = true;
    $m->Username   = $cfg['smtp_user'];
    $m->Password   = preg_replace('/\s+/', '', $cfg['smtp_pass']);
    $m->SMTPSecure = ($cfg['smtp_secure'] ?? 'tls') === 'ssl'
                     ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
                     : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    $m->Timeout    = 15;
    $m->CharSet    = 'UTF-8';

    if (mail_is_local()) { // XAMPP often lacks a CA bundle
      $m->SMTPOptions = ['ssl' => [
        'verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true,
      ]];
    }

    $m->setFrom($cfg['from_email'] ?? $cfg['smtp_user'], $cfg['from_name'] ?? 'WasteBank');
    $m->addAddress($to);
    if ($replyEmail !== '') $m->addReplyTo($replyEmail, $replyName);
    $m->Subject = $subject;
    $m->Body    = $body;
    $m->isHTML(false);
    $m->send();
    return true;
  } catch (Throwable $ex) {
    $GLOBALS['mail_error'] = 'SMTP failed: ' . $ex->getMessage();
    return false;
  }
}