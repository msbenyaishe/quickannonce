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
  // Delete user's ads first, then the user
  $pdo->beginTransaction();
  $pdo->prepare('DELETE FROM annonces WHERE id_utilisateur = ?')->execute([$id]);
  $pdo->prepare('DELETE FROM utilisateurs WHERE id = ?')->execute([$id]);
  $pdo->commit();
} catch (Throwable $e) {
  if ($pdo->inTransaction()) {
    $pdo->rollBack();
  }
  error_log('Delete user error: ' . $e->getMessage());
}
header('Location: ' . $redirect);
exit;
?>
