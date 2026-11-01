<?php
$pageTitle = 'My QR Code';
require 'header.php';

$st = $pdo->prepare("SELECT name, member_code FROM users WHERE id=?");
$st->execute([$_SESSION['user']['id']]);
$me = $st->fetch();
?>

<h1 class="welcome">My QR Code</h1>
<p class="sub">Show this QR code to our staff when depositing waste.</p>

<div class="qr-wrap">
  <div class="panel qr-card">
    <div class="qr-user">
      <div class="avatar big"><?= e(strtoupper(substr($me['name'], 0, 1))) ?></div>
      <div>
        <strong><?= e($me['name']) ?></strong>
        <small>Member ID: <?= e($me['member_code']) ?></small>
      </div>
    </div>

    <div class="qr-frame">
      <i></i><i></i><i></i><i></i>
      <div id="qrcode"></div>
    </div>

    <p class="qr-note">Scan this QR code when depositing waste or requesting a pickup.</p>

    <div class="btn-row">
      <button type="button" class="btn-ghost" id="dl">⬇ Download QR</button>
      <button type="button" class="btn-ghost" onclick="window.print()">🖨 Print</button>
    </div>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
  // The QR code contains only the member code, nothing sensitive
  const memberCode = <?= json_encode($me['member_code']) ?>;
  new QRCode(document.getElementById('qrcode'), {
    text: memberCode, width: 220, height: 220,
    colorDark: '#0b4d2c', colorLight: '#ffffff', correctLevel: QRCode.CorrectLevel.H
  });

  // Download the QR as a PNG image
  document.getElementById('dl').addEventListener('click', () => {
    const canvas = document.querySelector('#qrcode canvas');
    const a = document.createElement('a');
    a.href = canvas.toDataURL('image/png');
    a.download = 'wastebank-' + memberCode + '.png';
    a.click();
  });
</script>

<?php require 'footer.php'; ?>