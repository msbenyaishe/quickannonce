<?php
include 'config.php';
if (!isLoggedIn() || !isAdmin()) {
  header('Location: login.php');
  exit;
}
$pdo = getPDO();
$redirect = $_POST['redirect'] ?? 'admin-console.php';
if (!validate_csrf($_POST['csrf'] ?? null)) {
  http_response_code(403);
  header('Location: ' . $redirect);
  exit;
}
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0) {
  header('Location: ' . $redirect);
  exit;
}
try {
  $pdo->prepare('DELETE FROM annonces WHERE id = ?')->execute([$id]);
  require_once __DIR__ . '/includes/log_action.php';
  log_action("suppression_annonce", ["id" => $id]);
} catch (Throwable $e) {
}
header('Location: ' . $redirect);
exit;
?>
