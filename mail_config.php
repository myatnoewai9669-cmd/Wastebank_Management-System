<?php
// mail_config.php
return [
  // ---- Support messages (help-support.php) go through Formspree ----
  'support_email'      => 'myatmyatwai97@gmail.com',
  
  'formspree_endpoint' => 'https://formspree.io/f/mqppylal',

  // ---- Emails to members (forgot_password.php) go through Gmail SMTP ----
  // 1) Turn on 2-Step Verification in the Google account
  // 2) Create an App Password: myaccount.google.com/apppasswords
  // 3) Paste the 16 letters below WITHOUT spaces
  'smtp_host'   => 'smtp.gmail.com',
  'smtp_port'   => 587,
  'smtp_secure' => 'tls',                         // 'tls' for 587, 'ssl' for 465
  'smtp_user'   => 'myatmyatwai97@gmail.com',     // the Gmail that owns the App Password
  'smtp_pass'   => 'ypwn abjo kqhp frwt',
  'from_email'  => 'myatmyatwai97@gmail.com',     // must be the same Gmail as smtp_user
  'from_name'   => 'WasteBank',
];