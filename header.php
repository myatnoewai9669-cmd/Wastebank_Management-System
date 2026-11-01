<?php
require_once __DIR__ . '/db.php';

// Redirect guests to the login page
if (empty($_SESSION['user'])) { header('Location: login.php'); exit; }

$current = basename($_SERVER['PHP_SELF']);

// Menu for every logged-in user
$menu = [
  'dashboard.php'    => ['Dashboard',         '▦'],
  'profile.php'      => ['My Profile',        '👤'],
  'qr.php'           => ['My QR Code',        '▣'],
  'balance.php'      => ['My Balance',        '💳'],
  'transactions.php' => ['Transactions',      '🧾'],
  'pickup.php'       => ['Pickup Schedule',   '🗓'],
  'guide.php'        => ['Recycling Guide',   '♻'],
  'points.php'       => ['Recycling Points',  '📍'],
  'rewards.php'      => ['Rewards & Points',  '★'],
  'help-support.php'         => ['Help & Support',    '?'],
];

// Extra menu items for staff and admins
if (in_array($_SESSION['user']['role'], ['staff', 'admin'], true)) {
  $menu['scan.php']      = ['Scan QR Code', '📷'];
  $menu['add_waste.php'] = ['Add Waste',    '➕'];
}

// Extra menu items for admins only
if ($_SESSION['user']['role'] === 'admin') {
  $menu['admin_dashboard.php'] = ['Admin Dashboard', '📊'];
  $menu['members.php']         = ['Members',         '👥'];
  $menu['pickup_requests.php'] = ['Pickup Requests', '🚚'];
  $menu['waste_types.php']     = ['Waste Types',     '🗑'];
  $menu['support_inbox.php']   = ['Support Inbox',   '📨'];
}

// ---------- Search index: every menu page plus a few keywords ----------
$searchWords = [
  'dashboard.php'       => 'home overview summary',
  'profile.php'         => 'account name phone address photo password',
  'qr.php'              => 'member id card code download print',
  'balance.php'         => 'money earnings cash wallet',
  'transactions.php'    => 'history deposits waste records',
  'pickup.php'          => 'book collection schedule request cancel',
  'guide.php'           => 'recycle plastic paper glass metal tips',
  'points.php'          => 'map location center drop point nearby',
  'rewards.php'         => 'redeem voucher gift points catalog',
  'help.php'            => 'support faq contact message question',
  'scan.php'            => 'camera member scan staff',
  'add_waste.php'       => 'record weight staff deposit',
  'admin_dashboard.php' => 'admin overview statistics',
  'members.php'         => 'users accounts roles',
  'pickup_requests.php' => 'confirm complete approve',
  'waste_types.php'     => 'price rate category',
  'support_inbox.php'   => 'messages tickets inbox',
];
$searchItems = [];
foreach ($menu as $file => [$label, $icon]) {
  $searchItems[] = ['label' => $label, 'url' => $file, 'icon' => $icon,
                    'words' => strtolower($label . ' ' . ($searchWords[$file] ?? ''))];
}

// ---------- Notifications (built from existing data, no extra table) ----------
$notifs = [];
$uidN = (int)$_SESSION['user']['id'];
$reasonLabels = ['transaction' => 'Waste deposit', 'pickup' => 'Pickup bonus', 'redeem' => 'Reward redeemed',
                 'bonus' => 'Bonus points', 'adjust' => 'Adjustment'];

try {
  $st = $pdo->prepare("SELECT change_points, reason, UNIX_TIMESTAMP(created_at) AS ts
                       FROM points_ledger WHERE user_id=? ORDER BY created_at DESC, id DESC LIMIT 5");
  $st->execute([$uidN]);
  foreach ($st->fetchAll() as $r) {
    $p = (int)$r['change_points'];
    $notifs[] = ['ts' => (int)$r['ts'], 'icon' => $p >= 0 ? '⭐' : '🎁', 'link' => 'rewards.php',
                 'text' => ($p >= 0 ? '+' : '') . $p . ' points: ' . ($reasonLabels[$r['reason']] ?? ucfirst($r['reason']))];
  }
} catch (Exception $ex) {}

try {
  $st = $pdo->prepare("SELECT pickup_date, status, UNIX_TIMESTAMP(updated_at) AS ts
                       FROM pickup_requests WHERE user_id=? AND status <> 'pending'
                       ORDER BY updated_at DESC LIMIT 3");
  $st->execute([$uidN]);
  foreach ($st->fetchAll() as $r) {
    $notifs[] = ['ts' => (int)$r['ts'], 'icon' => '🚚', 'link' => 'pickup.php',
                 'text' => 'Your pickup for ' . date('M d', strtotime($r['pickup_date'])) . ' was ' . $r['status']];
  }
} catch (Exception $ex) {}

try {
  $st = $pdo->prepare("SELECT subject, UNIX_TIMESTAMP(updated_at) AS ts
                       FROM support_messages WHERE user_id=? AND status='resolved'
                       ORDER BY updated_at DESC LIMIT 2");
  $st->execute([$uidN]);
  foreach ($st->fetchAll() as $r) {
    $notifs[] = ['ts' => (int)$r['ts'], 'icon' => '💬', 'link' => 'help.php',
                 'text' => 'Support message resolved: ' . $r['subject']];
  }
} catch (Exception $ex) {}

if ($_SESSION['user']['role'] === 'admin') {
  try {
    $r = $pdo->query("SELECT COUNT(*) AS c, UNIX_TIMESTAMP(MAX(created_at)) AS ts
                      FROM pickup_requests WHERE status='pending'")->fetch();
    if ((int)$r['c'] > 0) {
      $notifs[] = ['ts' => (int)$r['ts'], 'icon' => '⏳', 'link' => 'pickup_requests.php?status=pending',
                   'text' => $r['c'] . ' pickup request(s) waiting for approval'];
    }
    $r = $pdo->query("SELECT COUNT(*) AS c, UNIX_TIMESTAMP(MAX(created_at)) AS ts
                      FROM support_messages WHERE status='open'")->fetch();
    if ((int)$r['c'] > 0) {
      $notifs[] = ['ts' => (int)$r['ts'], 'icon' => '📨', 'link' => 'support_inbox.php',
                   'text' => $r['c'] . ' open support message(s)'];
    }
  } catch (Exception $ex) {}
}

usort($notifs, fn($a, $b) => $b['ts'] <=> $a['ts']);
$notifs = array_slice($notifs, 0, 8);
$latestNotif = $notifs ? $notifs[0]['ts'] : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle ?? 'WasteBank') ?> - WasteBank</title>
<link rel="stylesheet" href="dashboard.css">
<style>
/* Search dropdown (kept here so it works even if dashboard.css was not updated) */
.search{position:relative}
.search-menu{display:none;position:absolute;left:0;right:auto;top:calc(100% + 8px);width:100%;min-width:240px;
  background:#fff;border:1px solid #e3ece6;border-radius:14px;box-shadow:0 16px 36px rgba(11,77,44,.2);
  padding:6px;z-index:80}
.search-menu.open{display:block}
.search-menu a{display:block;padding:10px 12px;border-radius:8px;font-size:13px;color:#1f2d24;text-decoration:none}
.search-menu a:hover,.search-menu a.sel{background:#e3f6e8;color:#116b36}
.search-menu .search-empty{padding:16px;color:#7a8a80;font-size:13px;text-align:center}

/* Notification + user dropdowns */
.dd{position:relative}
.dd-menu{display:none!important;position:absolute!important;right:0;top:calc(100% + 8px);min-width:220px;
  background:#fff;border:1px solid #e3ece6;border-radius:14px;
  box-shadow:0 16px 36px rgba(11,77,44,.2);padding:6px;z-index:80}
.dd-menu.open{display:block!important}
.notif-menu{width:320px;max-height:380px;overflow:auto}
.dd-menu a{display:block;padding:10px 12px;border-radius:8px;font-size:13px;color:#1f2d24;text-decoration:none}
.dd-menu a:hover{background:#e3f6e8;color:#116b36}
.notif-head{padding:8px 12px;font-weight:700;font-size:13px}
.notif-empty{padding:16px;color:#7a8a80;font-size:13px;text-align:center;margin:0}
.nt{display:flex!important;gap:10px}
.nt-tx small{display:block;color:#7a8a80;font-size:11px}

/* Bell button (forced so it always shows) */
.bell{position:relative!important;display:inline-flex!important;align-items:center;justify-content:center;
  width:42px!important;height:42px!important;padding:0!important;border:1px solid #e3ece6!important;
  border-radius:50%!important;background:#fff!important;cursor:pointer;opacity:1!important;visibility:visible!important}
.bell:hover{background:#e3f6e8!important}
.bell .bell-ic{display:block;width:22px;height:22px;fill:#f5a623}
.bell b{position:absolute;top:7px;right:8px;width:9px;height:9px;border-radius:50%;
  background:#e53935;border:2px solid #fff;box-sizing:content-box}
</style>
</head>
<body>
<aside class="sidebar" id="sidebar">
  <a href="index.php" class="logo" style="text-decoration:none;color:inherit">
    <svg viewBox="0 0 24 24" width="34" height="34" fill="#4cd37b"><path d="M17 8C8 10 5.9 16.2 3.8 20.2c1.5.5 3 .8 4.5.8 7 0 12.7-5.5 12.7-14-1 .5-2 .8-3 1zM6 18c1-3.5 4-7 9-8.5-4.2 2-6.8 5-8 8.5z"/></svg>
    <div><strong>WasteBank</strong><small>Smart Waste Management</small></div>
  </a>
  <nav>
    <?php foreach ($menu as $file => [$label, $icon]): ?>
      <a href="<?= $file ?>" class="<?= $current === $file ? 'active' : '' ?>">
        <i><?= $icon ?></i><span><?= $label ?></span>
      </a>
    <?php endforeach; ?>
  </nav>
  <a href="logout.php" class="logout">⏻ Logout</a>
</aside>

<div class="main">
  <header class="topbar">
    <button id="menuBtn" class="menu-btn" aria-label="Menu">☰</button>

    <!-- Page search -->
    <div class="search" id="searchBox">
      <span>🔍</span>
      <input type="text" id="siteSearch" placeholder="Search pages..." autocomplete="off" aria-label="Search pages">
      <div class="search-menu" id="searchMenu"></div>
    </div>
    <script>
    (function () {
      var items = <?= json_encode($searchItems, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
      var input = document.getElementById('siteSearch');
      var menu  = document.getElementById('searchMenu');
      var box   = document.getElementById('searchBox');
      var active = -1;

      function close() { menu.classList.remove('open'); active = -1; }

      function render(list) {
        menu.innerHTML = '';
        if (!list.length) {
          var p = document.createElement('div');
          p.className = 'search-empty';
          p.textContent = 'No pages found.';
          menu.appendChild(p);
        }
        list.forEach(function (it) {
          var a = document.createElement('a');
          a.href = it.url;
          a.textContent = it.icon + '  ' + it.label;   // textContent keeps it XSS-safe
          menu.appendChild(a);
        });
        menu.classList.add('open');
      }

      function search() {
        var q = input.value.trim().toLowerCase();
        if (!q) { close(); return; }
        var words = q.split(/\s+/);
        var found = items.filter(function (it) {
          return words.every(function (w) { return it.words.indexOf(w) !== -1; });
        });
        render(found.slice(0, 6));
        active = -1;
      }

      function highlight(links) {
        links.forEach(function (l, i) { l.classList.toggle('sel', i === active); });
      }

      input.addEventListener('input', search);
      input.addEventListener('focus', search);
      input.addEventListener('keydown', function (e) {
        var links = Array.prototype.slice.call(menu.querySelectorAll('a'));
        if (e.key === 'ArrowDown')      { e.preventDefault(); active = Math.min(active + 1, links.length - 1); highlight(links); }
        else if (e.key === 'ArrowUp')   { e.preventDefault(); active = Math.max(active - 1, 0); highlight(links); }
        else if (e.key === 'Enter')     { e.preventDefault(); var t = links[active >= 0 ? active : 0]; if (t) window.location.href = t.getAttribute('href'); }
        else if (e.key === 'Escape')    { close(); input.blur(); }
      });
      document.addEventListener('click', function (e) { if (!box.contains(e.target)) close(); });

      // Press "/" anywhere to jump into the search box
      document.addEventListener('keydown', function (e) {
        var tag = (document.activeElement || {}).tagName;
        if (e.key === '/' && tag !== 'INPUT' && tag !== 'TEXTAREA' && tag !== 'SELECT') {
          e.preventDefault();
          input.focus();
        }
      });
    })();
    </script>

    <div class="top-right">
      <!-- Notifications -->
      <div class="dd">
        <button type="button" class="bell" id="bellBtn" aria-label="Notifications" aria-expanded="false"
                data-latest="<?= (int)$latestNotif ?>" data-uid="<?= $uidN ?>"><svg class="bell-ic" viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path d="M12 22a2.4 2.4 0 0 0 2.4-2.4H9.6A2.4 2.4 0 0 0 12 22zm7-6.2V11a7 7 0 0 0-5.5-6.8V3.5a1.5 1.5 0 0 0-3 0v.7A7 7 0 0 0 5 11v4.8l-1.7 1.7V19h17.4v-1.5L19 15.8z"/></svg><b></b></button>
        <div class="dd-menu notif-menu" id="notifMenu">
          <div class="notif-head">Notifications</div>
          <?php if (!$notifs): ?>
            <p class="notif-empty">No notifications yet.</p>
          <?php endif; ?>
          <?php foreach ($notifs as $n): ?>
            <a href="<?= e($n['link']) ?>" class="nt">
              <span class="nt-ic"><?= $n['icon'] ?></span>
              <span class="nt-tx"><?= e($n['text']) ?><small><?= date('M d, H:i', $n['ts']) ?></small></span>
            </a>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- User menu -->
      <div class="dd">
        <button type="button" class="user" id="userBtn" aria-expanded="false">
          <div class="avatar"><?= e(strtoupper(substr($_SESSION['user']['name'], 0, 1))) ?></div>
          <div><strong><?= e($_SESSION['user']['name']) ?></strong><small><?= e(ucfirst($_SESSION['user']['role'])) ?></small></div>
        </button>
        <div class="dd-menu" id="userMenu">
          <a href="profile.php">👤 My Profile</a>
          <a href="qr.php">▣ My QR Code</a>
          <a href="help.php">? Help &amp; Support</a>
          <a href="logout.php" class="dd-out">⏻ Logout</a>
        </div>
      </div>
    </div>

    <!-- Dropdown toggle (click to open/close) -->
    <script>
    (function () {
      var pairs = [['bellBtn','notifMenu'], ['userBtn','userMenu']];
      function closeAll() {
        pairs.forEach(function (p) {
          document.getElementById(p[1]).classList.remove('open');
          document.getElementById(p[0]).setAttribute('aria-expanded', 'false');
        });
      }
      pairs.forEach(function (p) {
        var btn = document.getElementById(p[0]), menu = document.getElementById(p[1]);
        btn.addEventListener('click', function (e) {
          e.stopPropagation();
          var wasOpen = menu.classList.contains('open');
          closeAll();
          if (!wasOpen) { menu.classList.add('open'); btn.setAttribute('aria-expanded', 'true'); }
        });
        menu.addEventListener('click', function (e) { e.stopPropagation(); });
      });
      document.addEventListener('click', closeAll);
    })();
    </script>
  </header>
  <section class="content">