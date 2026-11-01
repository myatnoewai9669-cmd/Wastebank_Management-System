<?php
require __DIR__ . 'db.php';
header('Content-Type: application/json');

if (empty($_SESSION['user'])) { http_response_code(401); echo '[]'; exit; }

$range = $_GET['range'] ?? 'week';
$days  = $range === 'month' ? 30 : 7;

$st = $pdo->prepare("SELECT DATE(created_at) AS d, SUM(weight_kg) AS kg
                     FROM transactions
                     WHERE user_id = ? AND created_at >= CURDATE() - INTERVAL " . ($days - 1) . " DAY
                     GROUP BY DATE(created_at)");
$st->execute([$_SESSION['user']['id']]);
$map = array_column($st->fetchAll(), 'kg', 'd');

// Fill in days that have no data with 0
$out = [];
for ($i = $days - 1; $i >= 0; $i--) {
  $date = date('Y-m-d', strtotime("-$i day"));
  $out[] = [
    'label' => $days === 7 ? date('D', strtotime($date)) : date('M j', strtotime($date)),
    'kg'    => (float)($map[$date] ?? 0),
  ];
}
echo json_encode($out);