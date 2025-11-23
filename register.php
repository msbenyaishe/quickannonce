<?php
  include 'config.php';
  $pdo = getPDO();

  // Create uploads directory if it doesn't exist
  if (!is_dir('uploads')) {
    mkdir('uploads', 0755, true);
  }

  // Create default profile picture if it doesn't exist
  if (!file_exists('uploads/default.png')) {
    copy('img/default-avatar.png', 'uploads/default.png');
  }

  $reg_success = false;
  $reg_error = '';
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf($_POST['csrf'] ?? null)) {
      http_response_code(403);
      exit('Invalid CSRF token');
    }
    $nom = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $password2 = (string)($_POST['password2'] ?? '');

    if ($nom === '' || $email === '' || $password === '' || $password2 === '') {
      $reg_error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $reg_error = 'Invalid email address.';
    } elseif ($password !== $password2) {
      $reg_error = 'Passwords do not match!';
    } else {
      try {
        // Check duplicate
        $exists = $pdo->prepare('SELECT id FROM utilisateurs WHERE email = ? LIMIT 1');
        $exists->execute([$email]);
        if ($exists->fetch()) {
          $reg_error = 'An account with this email already exists.';
        } else {
          // Handle profile picture upload
          $profile_picture = 'default.png';
          
          if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
            $tmp = $_FILES['profile_picture']['tmp_name'];
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($tmp);
            $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png'];
            if (isset($allowed[$mime])) {
              $ext = $allowed[$mime];
              $new_filename = 'profile_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
              $upload_path = 'uploads/' . $new_filename;
              if (move_uploaded_file($tmp, $upload_path)) {
                $profile_picture = $new_filename;
              }
            }
          }
          
          $hash = password_hash($password, PASSWORD_BCRYPT);
          $stmt = $pdo->prepare('INSERT INTO utilisateurs (nom, email, mot_de_passe, nb_annonces, role, profile_picture, created_at) VALUES (?, ?, ?, 0, "user", ?, NOW())');
          $stmt->execute([$nom, $email, $hash, $profile_picture]);
          $reg_success = true;
          session_regenerate_id(true);
          $_SESSION['user_id'] = (int)$pdo->lastInsertId();
          $_SESSION['role'] = 'user';
          header('Location: index-user.php');
          exit;
        }
      } catch (Throwable $e) {
        $reg_error = 'Registration failed. Please try again.';
      }
    }
  }
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Register - QuickAnnonce</title>
  <link rel="stylesheet" href="css/styles.css" />
</head>
<body>
  <header class="header">
    <div class="container header-inner">
      <a class="brand" href="index.php"><span class="brand-logo">QA</span><span class="brand-name">QuickAnnonce</span></a>
      <nav class="nav">
        <a href="index.php">Home</a>
        <a href="user-consult.php">Announcements</a>
        <a href="login.php">Login</a>
        <a class="active" href="register.php">Register</a>
        <a href="contact.php">Contact</a>
      </nav>
      <div style="display:flex; gap:10px; align-items:center;">
        <a class="cta" href="post.php">Post Your Ad</a>
        <button class="mobile-toggle" aria-label="Toggle navigation">☰</button>
      </div>
    </div>
  </header>

  <section class="auth">
    <div class="container auth-grid">
      <aside class="auth-illustration">
        <div class="badge">New</div>
        <h1>Join QuickAnnonce</h1>
        <p class="muted">Sell faster and find great deals across categories and cities. Free for individuals.</p>
        <ul class="benefits">
          <li><span class="check">✓</span><div><strong>Post in minutes</strong><div class="muted">Simple, guided steps to publish.</div></div></li>
          <li><span class="check">✓</span><div><strong>Reach more buyers</strong><div class="muted">Thousands of daily visitors.</div></div></li>
          <li><span class="check">✓</span><div><strong>Secure moderation</strong><div class="muted">Admins validate new ads.</div></div></li>
        </ul>
        <div class="auth-image">
          <img src="https://images.unsplash.com/photo-1491438590914-bc09fcaaf77a?q=80&w=1400&auto=format&fit=crop" alt="Marketplace" />
        </div>
      </aside>

      <section class="auth-card">
        <h2>Create your account</h2>
        <?php if ($reg_error !== ''): ?>
          <div id="register-message" style="margin-bottom: 1rem; padding: 0.75rem; border-radius: 4px; background:#fee2e2; color:#991b1b; display:block;"><?php echo h($reg_error); ?></div>
        <?php endif; ?>
        <form class="simple-form" id="register-form" method="POST" enctype="multipart/form-data">
          <input type="hidden" name="csrf" value="<?php echo h(csrf_token()); ?>" />
          <div class="two">
            <input class="input" id="name" name="name" type="text" placeholder="Full name" required />
            <input class="input" id="email" name="email" type="email" placeholder="Email" required />
          </div>
          <div class="two">
            <input class="input" id="phone" name="phone" type="text" placeholder="Phone (optional)" />
            <input class="input" id="type" name="type" type="text" placeholder="Account type (optional)" />
          </div>
          <div class="two">
            <input class="input" id="password" name="password" type="password" placeholder="Password" required />
            <input class="input" id="password2" name="password2" type="password" placeholder="Confirm password" required />
          </div>
          <div class="profile-upload" style="margin-bottom: 1rem;">
            <label for="profile_picture" style="display: block; margin-bottom: 0.5rem; font-size: 0.9rem; color: #555;">Profile Picture (optional)</label>
            <input type="file" id="profile_picture" name="profile_picture" accept=".jpg,.jpeg,.png" style="border: 1px solid #ddd; padding: 0.5rem; border-radius: 4px; width: 100%;" />
            <small style="display: block; margin-top: 0.25rem; font-size: 0.8rem; color: #666;">Accepted formats: JPG, JPEG, PNG</small>
          </div>
          <label class="agree"><input type="checkbox" id="agree" required /> I agree to the Terms and Privacy Policy</label>
          <div class="actions">
            <button class="btn" type="submit">Create account</button>
            <a class="btn ghost" href="login.php">I have an account</a>
          </div>
        </form>
      </section>
    </div>
  </section>

  <footer class="footer">
    <div class="container footer-inner">
      <div><strong>QuickAnnonce</strong><div class="muted">© <span id="year"></span></div></div>
      <div style="display:flex; gap:12px;"><a href="contact.php">Contact</a></div>
    </div>
  </footer>
  <script src="js/main.js"></script>
  <script src="js/mobile-toggle.js"></script>
  <script>document.getElementById('year').textContent = new Date().getFullYear();</script>
</body>
</html>