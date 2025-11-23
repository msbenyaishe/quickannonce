<?php
  include 'config.php';
  $pdo = getPDO();

  $error = '';
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    try {
      $stmt = $pdo->prepare('SELECT id, nom, email, mot_de_passe, actif FROM admins WHERE email = ? LIMIT 1');
      $stmt->execute([$email]);
      $admin = $stmt->fetch();
      if ($admin && (int)$admin['actif'] === 1 && password_verify($password, $admin['mot_de_passe'])) {
        session_regenerate_id(true);
        $_SESSION['admin_id'] = (int)$admin['id'];
        $_SESSION['is_admin'] = true;
        $_SESSION['username'] = $admin['email']; // Store email as username for logging
        $_SESSION['role'] = 'admin';
        
        // Log admin login
        require_once __DIR__ . '/includes/log_action.php';
        log_action("connexion", ["user" => $admin['email'], "role" => "admin"]);
        
        header('Location: index-admin.php');
        exit;
      }
      $error = 'Access denied — invalid credentials.';
    } catch (Throwable $e) {
      $error = 'Access denied — login failed. Please try again later.';
    }
  }
?>
<!DOCTYPE html>
<html lang="en" class="grid">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Admin Login - QuickAnnonce</title>
  <link rel="stylesheet" href="css/styles.css" />
  <style>
    /* Center login card vertically and horizontally and keep footer at bottom */
    body.grid {
      display: flex;
      flex-direction: column;
      min-height: 100vh;
    }

    main.container {
      flex: 1;
      display: flex;
      justify-content: center;
      align-items: center;
    }

    .login-card {
      max-width: 420px;
      width: 100%;
    }

    .footer {
      margin-top: auto;
    }
    
    /* Enhanced error message styling */
    @keyframes slideIn {
      from {
        opacity: 0;
        transform: translateY(-10px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
    
    .admin-login-message.error {
      display: block !important;
    }
  </style>
</head>
<body class="grid">
  <header class="header">
    <div class="container header-inner">
      <a class="brand" href="index.php"><span class="brand-logo">QA</span><span class="brand-name">QuickAnnonce</span></a>
      <nav class="nav">
        <a href="index.php">Home</a>
        <a href="user-consult.php">Announcements</a>
        <a href="login.php">Login</a>
        <a href="register.php">Register</a>
        <a href="contact.php">Contact</a>
        <a class="active" href="admin-login.php">Admin</a>
      </nav>
      <div style="display:flex; gap:10px; align-items:center;">
        <button class="mobile-toggle" aria-label="Toggle navigation">☰</button>
      </div>
    </div>
  </header>

  <main class="container">
    <div class="card login-card">
      <div class="card-body">
        <div class="card-title">Admin Login</div>
        <div class="card-meta">Use your admin credentials to access the dashboard.</div>
      </div>
      <?php if (!empty($error)): ?>
        <div id="admin-login-message" class="admin-login-message error" style="display: block; margin: 0 16px 16px; padding: 12px 16px; border-radius: 8px; background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; font-weight: 600; font-size: 14px; animation: slideIn 0.3s ease-out;">
          ⚠️ <?php echo h($error); ?>
        </div>
      <?php endif; ?>
      <form action="admin-login.php" method="post" style="padding: 0 16px 16px;">
        <label>Email</label>
        <input class="input" type="email" name="email" placeholder="admin@example.com" required />
        <label>Password</label>
        <input class="input" type="password" name="password" placeholder="••••••••" required />
        <button class="btn" type="submit" style="margin-top:12px;">Login</button>
      </form>
    </div>
  </main>

  <footer class="footer">
    <div class="container footer-inner">
      <div><strong>QuickAnnonce</strong><div class="muted">© <span id="year"></span></div></div>
      <div style="display:flex; gap:12px;"><a href="contact.php">Contact</a></div>
    </div>
  </footer>
  <script>
    document.getElementById('year').textContent = new Date().getFullYear();
    
    // Auto-dismiss error message when user starts typing
    const errorMessage = document.getElementById('admin-login-message');
    const formInputs = document.querySelectorAll('form[action="admin-login.php"] input');
    
    if (errorMessage) {
      formInputs.forEach(input => {
        input.addEventListener('focus', function() {
          errorMessage.style.opacity = '0.7';
        });
        input.addEventListener('input', function() {
          errorMessage.style.opacity = '0.5';
        });
      });
    }
  </script>
  <script src="js/mobile-toggle.js"></script>
</body>
</html>
