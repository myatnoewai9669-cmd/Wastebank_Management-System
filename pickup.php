<?php
require_once 'db.php';

// Guests go to login (header.php also checks this, but we need it before processing POST)
if (empty($_SESSION['user'])) { header('Location: login.php'); exit; }
$uid = $_SESSION['user']['id'];

$slots = ['08:00 - 10:00', '10:00 - 12:00', '14:00 - 16:00', '16:00 - 18:00'];
$types = ['Plastic', 'Paper', 'Glass', 'Metal', 'Others'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();

  // ----- Cancel a pending request -----
  if (($_POST['action'] ?? '') === 'cancel') {
    $st = $pdo->prepare("UPDATE pickup_requests SET status='cancelled'
                         WHERE id=? AND user_id=? AND status='pending'");
    $st->execute([(int)($_POST['id'] ?? 0), $uid]);
    $_SESSION['flash'] = 'Pickup request cancelled.';
    header('Location: pickup.php'); exit;
  }

  // ----- Book a new pickup -----
  $date = $_POST['pickup_date'] ?? '';
  $slot = $_POST['time_slot'] ?? '';
  $note = trim($_POST['note'] ?? '');
  $chosen = array_values(array_intersect($types, $_POST['waste_types'] ?? []));

  $d = DateTime::createFromFormat('Y-m-d', $date);
  if (!$d || $d->format('Y-m-d') !== $date) $errors[] = 'Please choose a valid pickup date';
  elseif ($date < date('Y-m-d'))            $errors[] = 'Pickup date cannot be in the past';
  if (!in_array($slot, $slots, true))       $errors[] = 'Please select a time slot';
  if (strlen($note) > 500)                  $errors[] = 'Note is too long (max 500 characters)';

  if (!$errors) {
    $st = $pdo->prepare("INSERT INTO pickup_requests (user_id, pickup_date, time_slot, waste_types, note)
                         VALUES (?,?,?,?,?)");
    $st->execute([$uid, $date, $slot, implode(',', $chosen), $note]);
    $_SESSION['flash'] = 'Pickup booked successfully! We will confirm it soon.';
    header('Location: pickup.php'); exit;
  }
}

// Flash message (shown once)
$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

// Pickup history
$st = $pdo->prepare("SELECT * FROM pickup_requests WHERE user_id=?
                     ORDER BY pickup_date DESC, id DESC LIMIT 8");
$st->execute([$uid]);
$history = $st->fetchAll();

$pageTitle = 'Book a Pickup';
require 'header.php';
?>

<h1 class="welcome">Book a Pickup</h1>
<p class="sub">Schedule a waste collection and let us handle the rest.</p>

<?php if ($flash): ?><div class="notice ok"><?= e($flash) ?></div><?php endif; ?>
<?php foreach ($errors as $er): ?><div class="notice err"><?= e($er) ?></div><?php endforeach; ?>

<div class="pickup-grid">
  <!-- ========== Booking form ========== -->
  <div class="panel">
    <div class="steps" id="steps">
      <div class="step active"><b>1</b><span>Choose Date &amp; Time</span></div>
      <div class="line"></div>
      <div class="step"><b>2</b><span>Select Waste Type</span></div>
      <div class="line"></div>
      <div class="step"><b>3</b><span>Confirm</span></div>
    </div>

    <form method="post" id="pickupForm">
      <input type="hidden" name="csrf" value="<?= csrf() ?>">

      <!-- Step 1 -->
      <div class="pane active" data-step="1">
        <label class="lbl">Pickup Date</label>
        <input type="date" name="pickup_date" id="pdate" min="<?= date('Y-m-d') ?>"
               value="<?= e($_POST['pickup_date'] ?? '') ?>">

        <label class="lbl">Available Time</label>
        <div class="slots">
          <?php foreach ($slots as $s): ?>
            <label class="slot">
              <input type="radio" name="time_slot" value="<?= e($s) ?>"
                     <?= (($_POST['time_slot'] ?? '') === $s) ? 'checked' : '' ?>>
              <span><?= e($s) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
        <p class="hint" id="err1"></p>
        <button type="button" class="btn-main" data-next>Next</button>
      </div>

      <!-- Step 2 -->
      <div class="pane" data-step="2">
        <label class="lbl">Waste Type <small>(optional)</small></label>
        <div class="types">
          <?php foreach ($types as $t): ?>
            <label class="type">
              <input type="checkbox" name="waste_types[]" value="<?= e($t) ?>">
              <span><?= e($t) ?></span>
            </label>
          <?php endforeach; ?>
        </div>

        <label class="lbl">Additional Note</label>
        <textarea name="note" id="pnote" rows="4" maxlength="500"
                  placeholder="e.g. Large amount, special instructions..."><?= e($_POST['note'] ?? '') ?></textarea>

        <div class="btn-row">
          <button type="button" class="btn-ghost" data-prev>Back</button>
          <button type="button" class="btn-main" data-next>Next</button>
        </div>
      </div>

      <!-- Step 3 -->
      <div class="pane" data-step="3">
        <div class="summary">
          <div><small>Pickup Date</small><strong id="sDate">-</strong></div>
          <div><small>Time</small><strong id="sSlot">-</strong></div>
          <div><small>Waste Types</small><strong id="sTypes">-</strong></div>
          <div><small>Note</small><strong id="sNote">-</strong></div>
        </div>
        <div class="btn-row">
          <button type="button" class="btn-ghost" data-prev>Back</button>
          <button type="submit" class="btn-main">Confirm Booking</button>
        </div>
      </div>
    </form>
  </div>

  <!-- ========== Pickup history ========== -->
  <div class="panel">
    <div class="ph"><h3>My Pickup History</h3></div>

    <?php if (!$history): ?><p class="empty">No pickup requests yet.</p><?php endif; ?>

    <?php foreach ($history as $h): ?>
      <div class="hist">
        <div class="hist-ic">🚚</div>
        <div class="hist-info">
          <strong><?= date('M d, Y', strtotime($h['pickup_date'])) ?></strong>
          <small><?= e($h['time_slot']) ?><?= $h['waste_types'] ? ' • ' . e(str_replace(',', ', ', $h['waste_types'])) : '' ?></small>
        </div>
        <div class="hist-right">
          <span class="badge <?= e($h['status']) ?>"><?= e(ucfirst($h['status'])) ?></span>
          <?php if ($h['status'] === 'pending'): ?>
            <form method="post" onsubmit="return confirm('Cancel this pickup request?')">
              <input type="hidden" name="csrf" value="<?= csrf() ?>">
              <input type="hidden" name="action" value="cancel">
              <input type="hidden" name="id" value="<?= (int)$h['id'] ?>">
              <button class="link-del">Cancel</button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<script src="pickup.js"></script>
<?php require 'footer.php'; ?>