<?php
  include 'config.php';
  if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
  }
  $pdo = getPDO();

  $message = '';
  $error = '';
  $targetUserId = isset($_GET['id']) ? (int)$_GET['id'] : currentUserId();

  // Only admins can delete arbitrary users; a normal user can delete only themselves
  if (!isAdmin() && $targetUserId !== currentUserId()) {
    $error = 'Unauthorized.';
  } else {
    try {
      if (!SIMULATE_STORED_OBJECTS) {
        // Attempt stored procedure (local dev)
        $stmt = $pdo->prepare('CALL supprimer_utilisateur_complet(?)');
        $stmt->execute([$targetUserId]);
      } else {
        // Simulate: delete annonces then user
        $pdo->beginTransaction();
        $pdo->prepare('DELETE FROM annonces WHERE id_utilisateur = ?')->execute([$targetUserId]);
        $pdo->prepare('DELETE FROM utilisateurs WHERE id = ?')->execute([$targetUserId]);
        $pdo->commit();
      }

      // If a user deleted themselves, log them out
      if ($targetUserId === currentUserId()) {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
          $params = session_get_cookie_params();
          setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
          );
        }
        session_destroy();
        header('Location: index.php');
        exit;
      }

      $message = 'User and their announcements were deleted.';
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) { $pdo->rollBack(); }
      $error = 'Deletion failed.';
    }
  }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Delete User - QuickAnnonce</title>
  <link rel="stylesheet" href="css/styles.css" />
  <style>.container-narrow{max-width:720px;margin:40px auto;}</style>
  </head>
<body>
  <header class="header">
    <div class="container header-inner">
      <a class="brand" href="index.php"><span class="brand-logo">QA</span><span class="brand-name">QuickAnnonce</span></a>
      <nav class="nav">
        <a href="index.php">Home</a>
        <?php if (isAdmin()): ?><a class="active" href="index-admin.php">Admin</a><?php endif; ?>
      </nav>
      <div style="display:flex; gap:10px; align-items:center;">
        <a class="cta" href="post-user.php">Post Your Ad</a>
        <button class="mobile-toggle" aria-label="Toggle navigation">☰</button>
      </div>
    </div>
  </header>

  <main class="container container-narrow">
    <h1>Delete User</h1>
    <?php if ($error !== ''): ?>
      <div class="login-message error"><?php echo h($error); ?></div>
    <?php elseif ($message !== ''): ?>
      <div class="login-message success"><?php echo h($message); ?></div>
    <?php else: ?>
      <div class="muted">Nothing to do.</div>
    <?php endif; ?>
  </main>

  <footer class="footer">
    <div class="container footer-inner">
      <div><strong>QuickAnnonce</strong><div class="muted">© <span id="year"></span></div></div>
    </div>
  </footer>
  <script>document.getElementById('year').textContent = new Date().getFullYear();</script>
  <script src="js/mobile-toggle.js"></script>
</body>
</html>