<?php
  include 'config.php';
  if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
  }
  if (isAdmin()) {
    header('Location: index-admin.php');
    exit;
  }
  $pdo = getPDO();

  $userId = currentUserId();
  $error = '';
  $success = '';
  $user = null;
  $stats = ['total' => 0, 'approved' => 0, 'pending' => 0, 'rejected' => 0];

  try {
    // Try to get all columns, but handle missing ones gracefully
    $stmt = $pdo->prepare('SELECT id, nom, email, mot_de_passe, nb_annonces, profile_picture, created_at, phone FROM utilisateurs WHERE id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    // Set defaults if columns don't exist
    if ($user && !isset($user['profile_picture'])) $user['profile_picture'] = 'default.png';
    if ($user && !isset($user['created_at'])) $user['created_at'] = date('Y-m-d');
    if ($user && !isset($user['phone'])) $user['phone'] = '';
  } catch (Throwable $e) {
    // Fallback query without optional columns
    try {
      $stmt = $pdo->prepare('SELECT id, nom, email, mot_de_passe, nb_annonces FROM utilisateurs WHERE id = ?');
      $stmt->execute([$userId]);
      $user = $stmt->fetch();
      if ($user) {
        $user['profile_picture'] = 'default.png';
        $user['created_at'] = date('Y-m-d');
        $user['phone'] = '';
      }
    } catch (Throwable $e2) {}
  }

  if (!$user) {
    $error = 'User not found.';
  } else {
    // Compute account statistics
    try {
      $stmt = $pdo->prepare('SELECT moderation_status, COUNT(*) AS c FROM annonces WHERE id_utilisateur = ? GROUP BY moderation_status');
      $stmt->execute([$userId]);
      $rows = $stmt->fetchAll();
      foreach ($rows as $r) {
        $stats['total'] += (int)$r['c'];
        if ($r['moderation_status'] === 'approved') $stats['approved'] = (int)$r['c'];
        if ($r['moderation_status'] === 'pending') $stats['pending'] = (int)$r['c'];
        if ($r['moderation_status'] === 'rejected') $stats['rejected'] = (int)$r['c'];
      }
    } catch (Throwable $e) {}
  }

  // Handle profile update
  if ($user && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf($_POST['csrf'] ?? null)) {
      http_response_code(403);
      exit('Invalid CSRF token');
    }
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $current_password = (string)($_POST['current_password'] ?? '');
    $new_password = (string)($_POST['new_password'] ?? '');
    $confirm_password = (string)($_POST['confirm_password'] ?? '');
    $newPicture = $user['profile_picture'] ?? null;

    // Basic validation
    if ($name === '' || $email === '') {
      $error = 'Name and email are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $error = 'Please enter a valid email address.';
    }

    // Email uniqueness check if changed
    if ($error === '' && strtolower($email) !== strtolower($user['email'])) {
      try {
        $stmt = $pdo->prepare('SELECT id FROM utilisateurs WHERE email = ? AND id <> ? LIMIT 1');
        $stmt->execute([$email, $userId]);
        $exists = $stmt->fetch();
        if ($exists) {
          $error = 'Email is already in use by another account.';
        }
      } catch (Throwable $e) {}
    }

    // Password change, if provided
    $passwordHashToSave = null;
    if ($error === '' && ($new_password !== '' || $confirm_password !== '')) {
      if ($new_password !== $confirm_password) {
        $error = 'New password and confirmation do not match.';
      } elseif (strlen($new_password) < 8 || !preg_match('/[A-Za-z]/', $new_password) || !preg_match('/\d/', $new_password)) {
        $error = 'Password must be at least 8 characters and include letters and numbers.';
      } elseif (!password_verify($current_password, $user['mot_de_passe'])) {
        $error = 'Current password is incorrect.';
      } else {
        $passwordHashToSave = password_hash($new_password, PASSWORD_BCRYPT);
      }
    }

    // Profile picture upload (optional)
    if ($error === '' && isset($_FILES['profile_picture']) && is_uploaded_file($_FILES['profile_picture']['tmp_name'])) {
      $tmp = $_FILES['profile_picture']['tmp_name'];
      $size = (int)($_FILES['profile_picture']['size'] ?? 0);
      $finfo = new finfo(FILEINFO_MIME_TYPE);
      $mime = $finfo->file($tmp);
      if (!in_array($mime, ['image/jpeg','image/png'], true)) {
        $error = 'Profile picture must be JPG or PNG.';
      } elseif ($size > 2 * 1024 * 1024) {
        $error = 'Profile picture must be smaller than 2MB.';
      } else {
        $ext = $mime === 'image/png' ? 'png' : 'jpg';
        $new_filename = 'profile_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        $upload_path = 'uploads/' . $new_filename;
        if (!is_dir('uploads')) { @mkdir('uploads', 0755, true); }
        if (move_uploaded_file($tmp, $upload_path)) {
          $newPicture = $new_filename;
        } else {
          $error = 'Failed to upload profile picture.';
        }
      }
    }

    // Persist changes
    if ($error === '') {
      try {
        // Build dynamic update based on provided fields
        $fields = ['nom' => $name, 'email' => $email];
        $sql = 'UPDATE utilisateurs SET nom = :nom, email = :email';
        if ($newPicture !== $user['profile_picture']) {
          $sql .= ', profile_picture = :profile_picture';
          $fields['profile_picture'] = $newPicture;
        }
        if ($phone !== '') {
          // Attempt to include phone if column exists (will fail gracefully if column doesn't exist)
          $sql .= ', phone = :phone';
          $fields['phone'] = $phone;
        }
        if ($passwordHashToSave) {
          $sql .= ', mot_de_passe = :mot_de_passe';
          $fields['mot_de_passe'] = $passwordHashToSave;
        }
        $sql .= ' WHERE id = :id';
        $fields['id'] = $userId;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($fields);
        $success = 'Profile updated successfully.';

        // Refresh user after update
        try {
          $stmt = $pdo->prepare('SELECT id, nom, email, mot_de_passe, nb_annonces, profile_picture, created_at, phone FROM utilisateurs WHERE id = ?');
          $stmt->execute([$userId]);
          $user = $stmt->fetch();
          if ($user && !isset($user['profile_picture'])) $user['profile_picture'] = 'default.png';
          if ($user && !isset($user['created_at'])) $user['created_at'] = date('Y-m-d');
          if ($user && !isset($user['phone'])) $user['phone'] = '';
        } catch (Throwable $e) {
          // Fallback if columns don't exist
          $stmt = $pdo->prepare('SELECT id, nom, email, mot_de_passe, nb_annonces FROM utilisateurs WHERE id = ?');
          $stmt->execute([$userId]);
          $user = $stmt->fetch();
          if ($user) {
            $user['profile_picture'] = 'default.png';
            $user['created_at'] = date('Y-m-d');
            $user['phone'] = '';
          }
        }
      } catch (Throwable $e) {
        $error = 'Failed to update profile.';
      }
    }
  }
?>
<!DOCTYPE html>
<html lang="en" class="grid">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>My Profile - QuickAnnonce</title>
  <link rel="stylesheet" href="css/styles.css" />
  <style>
    .profile-header { display:flex; align-items:center; gap:16px; }
    .avatar { width:96px; height:96px; border-radius:50%; object-fit:cover; background:#f3f4f6; }
    .container-narrow { max-width: 840px; margin: 0 auto; }
    .grid-two { display:grid; grid-template-columns: 1fr 1fr; gap:16px; }
    @media (max-width: 720px) { .grid-two { grid-template-columns: 1fr; } }
    .modal { position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: none; align-items: center; justify-content: center; }
    .modal .card { max-width: 420px; }
    /* Profile layout: two columns on desktop, stacked on mobile */
    .profile-grid { display: grid; grid-template-columns: 1.6fr .9fr; gap: 16px; align-items: start; }
    .profile-stack { display: grid; gap: 16px; }
    @media (max-width: 768px) {
      .profile-grid { grid-template-columns: 1fr; }
    }
  </style>
  </head>
<body class="grid">
  <header class="header">
    <div class="container header-inner">
      <a class="brand" href="index-user.php"><span class="brand-logo">QA</span><span class="brand-name">QuickAnnonce</span></a>
      <nav class="nav">
        <a href="index-user.php">Home</a>
        <a href="user-consult-user.php">Announcements</a>
        <a class="active" href="profile.php">Profile</a>
        <a href="contact-user.php">Contact</a>
        <a href="logout.php">Logout</a>
      </nav>
      <div style="display:flex; gap:10px; align-items:center;">
        <a class="cta" href="post-user.php">Post Your Ad</a>
        <button class="mobile-toggle" aria-label="Toggle navigation">☰</button>
      </div>
    </div>
  </header>

  <main style="margin-top:15px;" class="container">
    <div class="profile-header">
      <?php
      // Normalize profile picture path - handle both 'uploads/filename' and 'filename' formats
      $picPath = ($user && !empty($user['profile_picture'])) ? $user['profile_picture'] : null;
      if ($picPath && strpos($picPath, 'uploads/') !== 0) {
          $picPath = 'uploads/' . $picPath;
      } elseif (!$picPath) {
          $picPath = 'https://via.placeholder.com/96x96.png?text=User';
      }
      ?>
      <img id="avatar" class="avatar" src="<?php echo h($picPath); ?>" alt="<?php echo h($user['nom'] ?? 'Profile'); ?>" />

      <div>
        <h1 style="margin:0;"><?php echo h($user['nom'] ?? 'My Profile'); ?></h1>
        <?php if ($user): ?>
          <div class="muted">Member since <?php echo h(date('F j, Y', strtotime($user['created_at'] ?? date('Y-m-d')))); ?></div>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($error !== ''): ?>
      <div class="login-message error" style="margin-top:12px;"><?php echo h($error); ?></div>
    <?php elseif ($success !== ''): ?>
      <div class="login-message success" style="margin-top:12px;"><?php echo h($success); ?></div>
    <?php endif; ?>

    <section class="profile-grid" style="margin-top:16px;">
      <article class="card">
        <div class="card-body">
          <div class="card-title">Account Informations</div>
          <div class="card-meta">Full name, email, phone, and profile picture</div>
        </div>
        <form class="simple-form" method="post" enctype="multipart/form-data" style="padding: 0 16px 16px;">
          <input type="hidden" name="csrf" value="<?php echo h(csrf_token()); ?>" />
          <label>Full name</label>
          <input class="input" type="text" name="name" value="<?php echo h($user['nom'] ?? ''); ?>" required />
          <label>Email</label>
          <input class="input" type="email" name="email" value="<?php echo h($user['email'] ?? ''); ?>" required />
          <label>Phone (optional)</label>
          <input class="input" type="tel" name="phone" value="<?php echo h($user['phone'] ?? ''); ?>" placeholder="e.g., +225 01 23 45 67" />
          <label>Profile picture</label>
          <input class="input" type="file" name="profile_picture" accept="image/*" onchange="previewProfilePicture(event)" />
          <div class="muted" style="margin-top:8px;">Accepted: JPG, PNG. Max 2MB.</div>

          <div class="card-title" style="margin-top:16px;">Change Password</div>
          <label>Current password</label>
          <input class="input" type="password" name="current_password" placeholder="••••••••" />
          <label>New password</label>
          <input class="input" type="password" name="new_password" placeholder="At least 8 characters" />
          <label>Confirm new password</label>
          <input class="input" type="password" name="confirm_password" placeholder="Re-enter new password" />

          <button class="btn" type="submit" style="margin-top:12px;">Save Changes</button>
        </form>
      </article>
      <div class="profile-stack">
        <article class="card">
          <div class="card-body">
            <div class="card-title">Account Summary</div>
            <div class="card-meta">Quick stats about your activity</div>
            <ul style="margin-top:8px;">
              <li>Total ads posted: <?php echo (int)$stats['total']; ?></li>
              <li>Approved: <?php echo (int)$stats['approved']; ?> • Pending: <?php echo (int)$stats['pending']; ?> • Rejected: <?php echo (int)$stats['rejected']; ?></li>
            </ul>
          </div>
        </article>

        <article class="card">
          <div class="card-body">
            <div class="card-title">Danger Zone</div>
            <div class="card-meta">Delete your account and all associated data</div>
          </div>
          <div style="padding: 0 16px 16px;">
            <button class="btn danger" onclick="openDeleteModal()">Delete My Account</button>
          </div>
        </article>
      </div>
    </section>
  </main>

  <!-- Confirm Delete Modal -->
  <div id="delete-modal" class="modal" role="dialog" aria-modal="true" aria-labelledby="delete-title">
    <article class="card">
      <div class="card-body">
        <div id="delete-title" class="card-title">Confirm Account Deletion</div>
        <div class="card-meta">This action cannot be undone. All your ads will be deleted.</div>
        <div style="display:flex; gap:8px; margin-top:12px;">
          <button class="btn" onclick="closeDeleteModal()">Cancel</button>
          <a class="btn danger" href="user-delete.php?id=<?php echo (int)$userId; ?>">Yes, delete my account</a>
        </div>
      </div>
    </article>
  </div>

  <footer class="footer">
    <div class="container footer-inner">
      <div><strong>QuickAnnonce</strong><div class="muted">© <span id="year"></span></div></div>
      <div style="display:flex; gap:12px;"><a href="contact-user.php">Contact</a></div>
    </div>
  </footer>
  <script>
    document.getElementById('year').textContent = new Date().getFullYear();
    function previewProfilePicture(e) {
      const file = e.target.files && e.target.files[0];
      if (!file) return;
      const reader = new FileReader();
      reader.onload = function(evt) {
        const img = document.getElementById('avatar');
        img.src = evt.target.result;
      };
      reader.readAsDataURL(file);
    }
    function openDeleteModal(){ document.getElementById('delete-modal').style.display = 'flex'; }
    function closeDeleteModal(){ document.getElementById('delete-modal').style.display = 'none'; }
  </script>
  <script src="js/mobile-toggle.js"></script>
</body>
</html>
