<?php
include 'config.php';
$pdo = getPDO();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!validate_csrf($_POST['csrf'] ?? null)) {
    http_response_code(403);
    exit('Invalid CSRF token');
  }
  $email = trim($_POST['email'] ?? '');
  $password = (string)($_POST['password'] ?? '');

  if ($email === '' || $password === '') {
    $error = "⚠️ Please fill in all fields.";
  } else {
    // Clear any existing session data
    $_SESSION['user_id'] = null;
    $_SESSION['role'] = null;
    
    // Check user login (both regular users and admins in the same table)
    $stmt = $pdo->prepare("SELECT id, nom, email, mot_de_passe, role FROM utilisateurs WHERE email = :email LIMIT 1");
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['mot_de_passe'])) {
      session_regenerate_id(true);
      $_SESSION['user_id'] = (int)$user['id'];
      $_SESSION['role'] = $user['role'];
      $_SESSION['username'] = $user['email']; // Store email as username for logging
      
      // Debug information
      error_log("User logged in: ID=" . $user['id'] . ", Role=" . $user['role']);
      
      // Redirect based on role
      require_once __DIR__ . '/includes/log_action.php';
      log_action("connexion", ["user" => $email]);
      
      if ($user['role'] == 'admin') {
        $_SESSION['is_admin'] = true; // Add this for backward compatibility
        header("Location: index-admin.php");
      } else {
        $_SESSION['is_admin'] = false; // Add this for backward compatibility
        header("Location: index-user.php");
      }
      exit;
    } else {
      $error = "⚠️ Incorrect email or password. Please try again.";
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Login - QuickAnnonce</title>
  <link rel="stylesheet" href="css/styles.css" />
  <style>
    /* Enhanced error message styling */
    .login-message.error {
      display: block !important;
      animation: slideIn 0.3s ease-out;
    }
    
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
    
    /* Auto-dismiss on form interaction */
    .login-form input:focus ~ #login-message,
    .login-form input:focus + * ~ #login-message {
      opacity: 0.7;
      transition: opacity 0.3s ease;
    }
  </style>
</head>
<body>
  <header class="header">
    <div class="container header-inner">
      <a class="brand" href="index.php"><span class="brand-logo">QA</span><span class="brand-name">QuickAnnonce</span></a>
      <nav class="nav">
        <a href="index.php">Home</a>
        <a href="user-consult.php">Announcements</a>
        <a class="active" href="login.php">Login</a>
        <a href="register.php">Register</a>
        <a href="contact.php">Contact</a>
      </nav>
      <div style="display:flex; gap:10px; align-items:center;">
        <button class="mobile-toggle" aria-label="Toggle navigation">☰</button>
      </div>
    </div>
  </header>

  <main class="main-content">
    <div class="login-container">
      <div class="login-card">
        <div class="login-header">
          <h1>Welcome back</h1>
          <p>Please sign in to your account</p>
        </div>

        <?php if (!empty($error)): ?>
          <div id="login-message" class="login-message error" style="display: block;"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form class="login-form" id="login-form" method="POST">
          <input type="hidden" name="csrf" value="<?php echo h(csrf_token()); ?>" />
          <div class="form-group">
            <input class="input" type="email" id="email" name="email" placeholder="Email address" required />
          </div>
          <div class="form-group">
            <input class="input" type="password" id="password" name="password" placeholder="Password" required />
          </div>
          <button class="btn" type="submit">Sign in</button>
        </form>
      </div>
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
    const errorMessage = document.getElementById('login-message');
    const formInputs = document.querySelectorAll('#login-form input');
    
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
