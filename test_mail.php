<?php
// test-mail.php - open http://localhost/Monday/test_mail.php
//   Test 1 (Formspree): test_mail.php
//   Test 2 (Gmail SMTP): test_mail.php?to=someone@example.com
// DELETE this file when everything works.
require_once __DIR__ . '/mail.php';
header('Content-Type: text/plain; charset=utf-8');

$cfg = require __DIR__ . '/mail_config.php';
echo "cURL enabled: " . (function_exists('curl_init') ? 'YES' : 'NO  <-- enable extension=curl in php.ini') . "\n";
echo "PHPMailer found: " . (is_file(__DIR__ . '/PHPMailer/src/PHPMailer.php') ? 'YES' : 'NO') . "\n\n";

$to = trim($_GET['to'] ?? '') ?: $cfg['support_email'];
echo "Sending to: $to  (" . (strcasecmp($to, $cfg['support_email']) === 0 ? 'Formspree' : 'Gmail SMTP') . ")\n";

$ok = send_mail($to, 'Test from WasteBank', "Hello, this is a test.", 'test@example.com', 'Test User');
echo $ok ? "RESULT: SENT OK\n" : "RESULT: FAILED\nReason: " . ($GLOBALS['mail_error'] ?? 'unknown') . "\n";