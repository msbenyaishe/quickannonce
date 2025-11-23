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
$action = $_POST['action'] ?? '';

if ($id <= 0 || !in_array($action, ['approve','reject'], true)) {
  header('Location: ' . $redirect);
  exit;
}

try {
  if ($action === 'approve') {
    $stmt = $pdo->prepare("UPDATE annonces SET moderation_status = 'approved', etat = 'active' WHERE id = ?");
    $stmt->execute([$id]);
  } else {
    $stmt = $pdo->prepare("UPDATE annonces SET moderation_status = 'rejected', etat = 'inactive' WHERE id = ?");
    $stmt->execute([$id]);
  }
} catch (Throwable $e) {
  // swallow error; stay resilient
}

header('Location: ' . $redirect);
exit;
?>
