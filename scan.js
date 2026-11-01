const camBtn = document.getElementById('camBtn');
const camMsg = document.getElementById('camMsg');
let scanner = null;
let running = false;

camBtn.addEventListener('click', async () => {
  // Toggle: stop the camera if it is already running
  if (running) {
    await scanner.stop();
    running = false;
    camBtn.textContent = 'Open Camera';
    return;
  }

  camMsg.textContent = '';
  scanner = new Html5Qrcode('reader');

  try {
    await scanner.start(
      { facingMode: 'environment' },
      { fps: 10, qrbox: { width: 220, height: 220 } },
      async decodedText => {
        // Stop scanning, then open the Add Waste form for this member
        await scanner.stop();
        running = false;
        window.location.href = 'add_waste.php?member=' + encodeURIComponent(decodedText.trim());
      },
      () => {}   // ignore frames where no QR is found
    );
    running = true;
    camBtn.textContent = 'Stop Camera';
  } catch (err) {
    camMsg.textContent = 'Could not open the camera. Please allow camera access or use manual input.';
  }
});