<?php
// db.php - database connection, session, and helper functions

// Start the session only once
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// ---------- App settings ----------
define('APP_URL', 'http://localhost/Monday');   // change to your real site URL when you deploy
define('APP_DEBUG', true);                      // true = show the reset link on screen (localhost only). Set false on a real server.

// ---------- Database connection ----------
try {
  $pdo = new PDO(
    "mysql:host=localhost;dbname=wastebank;charset=utf8mb4",
    "root",
    "",
    [
      PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES   => false,
    ]
  );
} catch (PDOException $ex) {
  http_response_code(500);
  die(APP_DEBUG ? 'Database connection failed: ' . $ex->getMessage() : 'Database connection failed.');
}

// ---------- Helper functions ----------

// Escape text before printing it in HTML
function e($s) {
  return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

// Get (or create) the CSRF token for this session
function csrf() {
  if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
  }
  return $_SESSION['csrf'];
}

// Stop the request if the CSRF token is missing or wrong
function csrf_check() {
  if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
    http_response_code(403);
    die('Invalid request');
  }
}

// Allow only the given roles; everyone else is redirected
function require_role(array $roles) {
  if (empty($_SESSION['user'])) {
    header('Location: login.php');
    exit;
  }
  if (!in_array($_SESSION['user']['role'], $roles, true)) {
    header('Location: dashboard.php');
    exit;
  }
}