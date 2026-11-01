<?php
require_once 'db.php';
if (empty($_SESSION['user'])) { header('Location: login.php'); exit; }
if (!in_array($_SESSION['user']['role'], ['staff', 'admin'], true)) {
  header('Location: dashboard.php'); exit;
}

$pageTitle = 'Scan QR Code';
require 'header.php';
?>
<link rel="stylesheet" href="dashboard.css">

<h1 class="welcome">Scan QR Code</h1>
<p class="sub">Scan a member QR code to record their waste.</p>

<div class="scan-grid">
  <div class="panel scan-card">
    <div class="qr-frame scan-frame">
      <i></i><i></i><i></i><i></i>
      <div id="reader"></div>
    </div>
    <h3>Scan Member QR Code</h3>
    <p class="qr-note">Position the QR code within the frame.</p>
    <button type="button" class="btn-main" id="camBtn">Open Camera</button>
    <p class="hint" id="camMsg"></p>
  </div>

  <div class="panel">
    <h3>Manual Input</h3>
    <form method="get" action="add_waste.php">
      <label class="lbl">Member ID</label>
      <input type="text" name="member" class="inp" placeholder="Enter member ID (e.g. MW001)"
             maxlength="20" required>
      <button class="btn-main" type="submit">Submit</button>
    </form>
  </div>
</div>

<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script src="scan.js"></script>
<?php require 'footer.php'; ?>