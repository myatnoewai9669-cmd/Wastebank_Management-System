// Allow only the given roles; everyone else is redirected
function require_role(array $roles) {
  if (empty($_SESSION['user'])) { header('Location: login.php'); exit; }
  if (!in_array($_SESSION['user']['role'], $roles, true)) { header('Location: dashboard.php'); exit; }
}