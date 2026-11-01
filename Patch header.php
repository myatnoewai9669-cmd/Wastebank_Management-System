// Extra menu items for staff and admins
if (in_array($_SESSION['user']['role'], ['staff', 'admin'], true)) {
  $menu['scan.php']      = ['Scan QR Code', '📷'];
  $menu['add_waste.php'] = ['Add Waste',    '➕'];
}