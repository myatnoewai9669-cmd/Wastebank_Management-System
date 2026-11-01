<?php
require_once 'db.php';
require_role(['admin', 'staff']);

$PICKUP_BONUS = 50;
$tabs = ['all', 'pending', 'confirmed', 'completed', 'cancelled'];

// Allowed status changes
$allowed = [
  'pending'   => ['confirmed', 'cancelled'],
  'confirmed' => ['completed', 'cancelled'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $id   = (int)($_POST['id'] ?? 0);
  $to   = $_POST['to'] ?? '';
  $back = in_array($_POST['back'] ?? '', $tabs, true) ? $_POST['back'] : 'all';

  try {
    $pdo->beginTransaction();

    // Lock the request so two admins cannot change it at the same time
    $st = $pdo->prepare("SELECT id, user_id, status FROM pickup_requests WHERE id=? FOR UPDATE");
    $st->execute([$id]);
    $req = $st->fetch();

    if (!$req) throw new Exception('Pickup request not found.');
    if (!in_array($to, $allowed[$req['status']] ?? [], true)) {
      throw new Exception('That status change is not allowed.');
    }

    $pdo->prepare("UPDATE pickup_requests SET status=? WHERE id=?")->execute([$to, $id]);

    // Completed pickup = bonus points for the member
    if ($to === 'completed') {
      $pdo->prepare("UPDATE users SET points = points + ? WHERE id=?")->execute([$PICKUP_BONUS, $req['user_id']]);
      $pdo->prepare("INSERT INTO points_ledger (user_id, change_points, reason, ref_id)
                     VALUES (?,?, 'pickup', ?)")->execute([$req['user_id'], $PICKUP_BONUS, $id]);
    }

    $pdo->commit();
    $_SESSION['flash'] = ['ok', 'Pickup #' . $id . ' marked as ' . $to . '.'];
  } catch (Exception $ex) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $_SESSION['flash'] = ['err', $ex->getMessage()];
  }
  header('Location: pickup_requests.php?status=' . $back); exit;
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$status = in_array($_GET['status'] ?? 'all', $tabs, true) ? ($_GET['status'] ?? 'all') : 'all';

// Count per status for the tabs
$counts = ['all' => 0];
foreach ($pdo->query("SELECT status, COUNT(*) c FROM pickup_requests GROUP BY status") as $c) {
  $counts[$c['status']] = (int)$c['c'];
  $counts['all'] += (int)$c['c'];
}

$sql = "SELECT p.*, u.name, u.member_code, u.address
        FROM pickup_requests p JOIN users u ON u.id = p.user_id";
$params = [];
if ($status !== 'all') { $sql .= " WHERE p.status = ?"; $params[] = $status; }
$sql .= " ORDER BY p.pickup_date DESC, p.id DESC LIMIT 50";
$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();

$pageTitle = 'Pickup Requests';
require 'header.php';
?>

<h1 class="welcome">Pickup Schedule</h1>
<p class="sub">Review, confirm and complete member pickup requests.</p>

<?php if ($flash): ?><div class="notice <?= $flash[0] ?>"><?= e($flash[1]) ?></div><?php endif; ?>

<div class="tabs">
  <?php foreach ($tabs as $t): ?>
    <a href="pickup_requests.php?status=<?= $t ?>" class="tab <?= $status === $t ? 'on' : '' ?>">
      <?= ucfirst($t) ?> <b><?= (int)($counts[$t] ?? 0) ?></b>
    </a>
  <?php endforeach; ?>
</div>

<div class="panel">
  <div class="tbl-wrap">
    <table class="tbl">
      <thead><tr><th>Date &amp; Time</th><th>Member</th><th>Waste</th><th>Status</th><th>Action</th></tr></thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><strong><?= date('M d, Y', strtotime($r['pickup_date'])) ?></strong><small><?= e($r['time_slot']) ?></small></td>
            <td><?= e($r['name']) ?><small><?= e($r['member_code']) ?><?= $r['address'] ? ' • ' . e($r['address']) : '' ?></small></td>
            <td><?= $r['waste_types'] ? e(str_replace(',', ', ', $r['waste_types'])) : '-' ?>
              <?php if ($r['note']): ?><small>📝 <?= e($r['note']) ?></small><?php endif; ?></td>
            <td><span class="badge <?= e($r['status']) ?>"><?= e(ucfirst($r['status'])) ?></span></td>
            <td>
              <div class="acts">
                <?php foreach ($allowed[$r['status']] ?? [] as $to):
                  $label = ['confirmed'=>'Confirm','completed'=>'Complete','cancelled'=>'Cancel'][$to]; ?>
                  <form method="post" onsubmit="return confirm('<?= $label ?> this pickup request?')">
                    <input type="hidden" name="csrf" value="<?= csrf() ?>">
                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                    <input type="hidden" name="to" value="<?= $to ?>">
                    <input type="hidden" name="back" value="<?= e($status) ?>">
                    <button class="act <?= $to ?>"><?= $label ?></button>
                  </form>
                <?php endforeach; ?>
                <?php if (empty($allowed[$r['status']])): ?><small>-</small><?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="5" class="empty" style="text-align:center;padding:30px">No pickup requests.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require 'footer.php'; ?>