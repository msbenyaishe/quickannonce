<?php
  include 'config.php';
  require_once __DIR__ . '/includes/log_action.php';
  log_action("deconnexion");
  // Destroy server-side session
  $_SESSION = [];
  if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
      $params['path'], $params['domain'],
      $params['secure'], $params['httponly']
    );
  }
  session_destroy();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Logging Out... - QuickAnnonce</title>
  <link rel="stylesheet" href="css/styles.css" />
  <style>
    .logout-container {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      min-height: 70vh;
      text-align: center;
      padding: 2rem;
    }
    .logout-icon {
      font-size: 4rem;
      margin-bottom: 1rem;
    }
    .logout-message {
      font-size: 1.5rem;
      font-weight: 600;
      margin-bottom: 0.5rem;
    }
    .logout-submessage {
      color: #6b7280;
      margin-bottom: 2rem;
    }
  </style>
</head>
<body>
  <header class="header">
    <div class="container header-inner">
      <a class="brand" href="index.php">
        <span class="brand-logo">QA</span>
      </a>
      <nav class="nav">
        <!-- Navigation intentionally empty during logout -->
      </nav>
      <button class="mobile-toggle" aria-label="Toggle navigation">☰</button>
    </div>
  </header>

  <main class="container logout-container">
    <div class="logout-icon">👋</div>
    <div class="logout-message">Logging you out...</div>
    <div class="logout-submessage">You'll be redirected to the home page in a moment.</div>
    <div class="btn ghost" onclick="goHome()">Go to Home Now</div>
  </main>

  <footer class="footer">
    <div class="container footer-inner">
      <div><strong>QuickAnnonce</strong><div class="muted">© <span id="year"></span></div></div>
    </div>
  </footer>

  <script>
    document.getElementById('year').textContent = new Date().getFullYear();
    
    // Clear any stored authentication data
    try {
      localStorage.removeItem('currentUser');
      sessionStorage.clear();
      console.log('✓ Logged out successfully');
    } catch (e) {
      console.error('Error clearing storage:', e);
    }
    
    // Redirect to guest homepage after 1.5 seconds
    setTimeout(() => {
      window.location.replace('index.php');
    }, 1500);
    
    // Manual redirect function
    function goHome() {
      window.location.replace('index.php');
    }
  </script>
  <script src="js/mobile-toggle.js"></script>
</body>
</html>