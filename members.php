<?php
require_once 'db.php';
require_role(['admin']);
$me = $_SESSION['user']['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $id = (int)($_POST['id'] ?? 0);
  $action = $_POST['action'] ?? '';

  if ($id === $me) {
    $_SESSION['flash'] = ['err', 'You cannot change your own role or status.'];
  } elseif ($action === 'toggle') {
    $pdo->prepare("UPDATE users SET status = IF(status='active','inactive','active') WHERE id=?")->execute([$id]);
    $_SESSION['flash'] = ['ok', 'Member status updated.'];
  } elseif ($action === 'role' && in_array($_POST['role'] ?? '', ['member', 'staff', 'admin'], true)) {
    $pdo->prepare("UPDATE users SET role=? WHERE id=?")->execute([$_POST['role'], $id]);
    $_SESSION['flash'] = ['ok', 'Role updated.'];
  }
  header('Location: members.php?' . http_build_query(array_filter(['q' => $_POST['q'] ?? '', 'page' => $_POST['page'] ?? '']))); exit;
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$q = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;

$where = ''; $params = [];
if ($q !== '') {
  $where = "WHERE name LIKE ? OR email LIKE ? OR member_code LIKE ?";
  $like = '%' . $q . '%';
  $params = [$like, $like, $like];
}

$st = $pdo->prepare("SELECT COUNT(*) FROM users $where");
$st->execute($params);
$total = (int)$st->fetchColumn();
$pages = max(1, (int)ceil($total / $perPage));
$page = min($page, $pages);
$offset = ($page - 1) * $perPage;

$st = $pdo->prepare("SELECT id, member_code, name, email, role, status, points, balance, created_at
                     FROM users $where ORDER BY created_at DESC LIMIT $perPage OFFSET $offset");
$st->execute($params);
$rows = $st->fetchAll();

$pageTitle = 'Members';
require 'header.php';
?>

<h1 class="welcome">Manage Members</h1>
<p class="sub"><?= $total ?> account<?= $total === 1 ? '' : 's' ?> in total.</p>

<?php if ($flash): ?><div class="notice <?= $flash[0] ?>"><?= e($flash[1]) ?></div><?php endif; ?>

<form method="get" class="panel filter-bar" style="grid-template-columns:1fr auto">
  <input class="inp" type="text" name="q" placeholder="Search by name, email or member ID" value="<?= e($q) ?>">
  <div class="fb-btns">
    <button class="btn-sm big" type="submit">Search</button>
    <a href="members.php" class="btn-ghost small">Reset</a>
  </div>
</form>

<div class="panel">
  <div class="tbl-wrap">
    <table class="tbl">
      <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Points</th><th>Status</th><th>Action</th></tr></thead>
      <tbody>
        <?php foreach ($rows as $r): $self = (int)$r['id'] === (int)$me; ?>
          <tr>
            <td><strong><?= e($r['name']) ?></strong><small><?= e($r['member_code']) ?> • joined <?= date('M d, Y', strtotime($r['created_at'])) ?></small></td>
            <td><?= e($r['email']) ?></td>
            <td>
              <?php if ($self): ?>
                <span class="badge <?= e($r['role']) ?>"><?= e(ucfirst($r['role'])) ?></span>
              <?php else: ?>
                <form method="post" class="role-form">
                  <input type="hidden" name="csrf" value="<?= csrf() ?>">
                  <input type="hidden" name="action" value="role">
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <input type="hidden" name="q" value="<?= e($q) ?>">
                  <input type="hidden" name="page" value="<?= $page ?>">
                  <select name="role" class="inp sm" onchange="if(confirm('Change role for <?= e($r['name']) ?>?')) this.form.submit(); else this.value='<?= e($r['role']) ?>';">
                    <?php foreach (['member', 'staff', 'admin'] as $ro): ?>
                      <option value="<?= $ro ?>" <?= $r['role'] === $ro ? 'selected' : '' ?>><?= ucfirst($ro) ?></option>
                    <?php endforeach; ?>
                  </select>
                </form>
              <?php endif; ?>
            </td>
            <td><?= (int)$r['points'] ?> pts<small>Rp <?= number_format($r['balance'], 0, '.', ',') ?></small></td>
            <td><span class="badge <?= e($r['status']) ?>"><?= e(ucfirst($r['status'])) ?></span></td>
            <td>
              <?php if ($self): ?><small>You</small><?php else: ?>
                <form method="post" onsubmit="return confirm('<?= $r['status'] === 'active' ? 'Deactivate' : 'Activate' ?> this account?')">
                  <input type="hidden" name="csrf" value="<?= csrf() ?>">
                  <input type="hidden" name="action" value="toggle">
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <input type="hidden" name="q" value="<?= e($q) ?>">
                  <input type="hidden" name="page" value="<?= $page ?>">
                  <button class="act <?= $r['status'] === 'active' ? 'cancelled' : 'confirmed' ?>">
                    <?= $r['status'] === 'active' ? 'Deactivate' : 'Activate' ?></button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="6" class="empty" style="text-align:center;padding:30px">No members found.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php if ($pages > 1): ?>
    <div class="pager">
      <?php for ($i = 1; $i <= $pages; $i++): ?>
        <a href="members.php?<?= e(http_build_query(array_filter(['q' => $q, 'page' => $i]))) ?>" class="<?= $i === $page ? 'on' : '' ?>"><?= $i ?></a>
      <?php endfor; ?>
    </div>
  <?php endif; ?>
</div>

<?php require 'footer.php'; ?>