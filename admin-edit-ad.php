<?php
  include 'config.php';
  if (!isLoggedIn() || !isAdmin()) {
    header('Location: login.php');
    exit;
  }
  $pdo = getPDO();
  
  $adId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
  $error = '';
  $success = '';
  $ad = null;
  
  // Fetch ad
  if ($adId > 0) {
    try {
      $stmt = $pdo->prepare("SELECT a.*, u.nom AS user_name, u.email AS user_email 
                            FROM annonces a 
                            LEFT JOIN utilisateurs u ON a.id_utilisateur = u.id 
                            WHERE a.id = ?");
      $stmt->execute([$adId]);
      $ad = $stmt->fetch();
    } catch (Throwable $e) {
      $error = 'Ad not found.';
    }
  }
  
  if (!$ad) {
    header('Location: admin-console.php');
    exit;
  }
  
  // Handle form submission
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf($_POST['csrf'] ?? null)) {
      http_response_code(403);
      exit('Invalid CSRF token');
    }
    
    $titre = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $etat = trim($_POST['etat'] ?? '');
    $moderation_status = trim($_POST['moderation_status'] ?? '');
    
    if ($titre === '' || $description === '') {
      $error = 'Title and description are required.';
    } elseif (!in_array($etat, ['active', 'inactive'], true)) {
      $error = 'Invalid status.';
    } elseif (!in_array($moderation_status, ['pending', 'approved', 'rejected'], true)) {
      $error = 'Invalid moderation status.';
    } else {
      // Handle image upload if provided
      $imagePath = $ad['image_path'];
      if (!empty($_FILES['photo']['name']) && is_uploaded_file($_FILES['photo']['tmp_name'])) {
        $tmp = $_FILES['photo']['tmp_name'];
        $size = (int)($_FILES['photo']['size'] ?? 0);
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmp);
        if (!in_array($mime, ['image/jpeg','image/png'], true)) {
          $error = 'Image must be JPG or PNG.';
        } elseif ($size > 2 * 1024 * 1024) {
          $error = 'Image must be smaller than 2MB.';
        } else {
          $ext = $mime === 'image/png' ? 'png' : 'jpg';
          $newName = 'ad_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
          $uploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads';
          if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0755, true);
          }
          $destFs = $uploadDir . DIRECTORY_SEPARATOR . $newName;
          if (move_uploaded_file($tmp, $destFs)) {
            // Delete old image if different
            if ($imagePath && strpos($imagePath, 'uploads/') === 0 && file_exists($imagePath)) {
              @unlink($imagePath);
            }
            $imagePath = 'uploads/' . $newName;
          }
        }
      }
      
      if ($error === '') {
        try {
          $stmt = $pdo->prepare("UPDATE annonces SET titre = ?, description = ?, etat = ?, moderation_status = ?, image_path = ? WHERE id = ?");
          $stmt->execute([$titre, $description, $etat, $moderation_status, $imagePath, $adId]);
          $success = 'Ad updated successfully.';
          
          // Refresh ad data
          $stmt = $pdo->prepare("SELECT a.*, u.nom AS user_name, u.email AS user_email 
                                FROM annonces a 
                                LEFT JOIN utilisateurs u ON a.id_utilisateur = u.id 
                                WHERE a.id = ?");
          $stmt->execute([$adId]);
          $ad = $stmt->fetch();
        } catch (Throwable $e) {
          $error = 'Failed to update ad.';
          error_log('Edit ad error: ' . $e->getMessage());
        }
      }
    }
  }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Edit Ad - Admin - QuickAnnonce</title>
  <link rel="stylesheet" href="css/styles.css" />
</head>
<body>
  <header class="header">
    <div class="container header-inner">
      <a class="brand" href="index-admin.php"><span class="brand-logo">QA</span><span class="brand-name">QuickAnnonce</span></a>
      <nav class="nav">
        <a href="index-admin.php">Home</a>
        <a href="user-consult-admin.php">Announcements</a>
        <a class="active" href="admin-console.php">Admin</a>
        <a href="logout.php">Logout</a>
      </nav>
      <div style="display:flex; gap:10px; align-items:center;">
        <a class="cta" href="post-admin.php">Post Your Ad</a>
        <button class="mobile-toggle" aria-label="Toggle navigation">☰</button>
      </div>
    </div>
  </header>

  <main class="container" style="max-width: 800px; margin: 40px auto;">
    <div class="section-title">
      <h1>Edit Ad</h1>
      <a class="btn ghost" href="admin-console.php">← Back to Dashboard</a>
    </div>
    
    <?php if ($error !== ''): ?>
      <div class="login-message error" style="margin-bottom:12px;"><?php echo h($error); ?></div>
    <?php elseif ($success !== ''): ?>
      <div class="login-message success" style="margin-bottom:12px;"><?php echo h($success); ?></div>
    <?php endif; ?>
    
    <article class="card">
      <div class="card-body">
        <div class="card-meta">By <?php echo h($ad['user_name'] ?? 'Unknown'); ?> (<?php echo h($ad['user_email'] ?? 'N/A'); ?>) • Created: <?php echo h(date('Y-m-d H:i', strtotime($ad['date_publication']))); ?></div>
      </div>
      
      <form method="post" enctype="multipart/form-data" style="padding: 0 16px 16px;">
        <input type="hidden" name="csrf" value="<?php echo h(csrf_token()); ?>" />
        
        <label style="display: block; margin-bottom: 4px; font-weight: 600;">Title</label>
        <input class="input" type="text" name="title" value="<?php echo h($ad['titre']); ?>" required />
        
        <label style="display: block; margin-bottom: 4px; margin-top: 16px; font-weight: 600;">Description</label>
        <textarea class="input" name="description" rows="6" required><?php echo h($ad['description']); ?></textarea>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-top: 16px;">
          <div>
            <label style="display: block; margin-bottom: 4px; font-weight: 600;">Ad Status</label>
            <select class="input" name="etat" required>
              <option value="active" <?php echo $ad['etat']==='active'?'selected':''; ?>>Active</option>
              <option value="inactive" <?php echo $ad['etat']==='inactive'?'selected':''; ?>>Inactive</option>
            </select>
          </div>
          
          <div>
            <label style="display: block; margin-bottom: 4px; font-weight: 600;">Moderation Status</label>
            <select class="input" name="moderation_status" required>
              <option value="pending" <?php echo $ad['moderation_status']==='pending'?'selected':''; ?>>Pending</option>
              <option value="approved" <?php echo $ad['moderation_status']==='approved'?'selected':''; ?>>Approved</option>
              <option value="rejected" <?php echo $ad['moderation_status']==='rejected'?'selected':''; ?>>Rejected</option>
            </select>
          </div>
        </div>
        
        <label style="display: block; margin-bottom: 4px; margin-top: 16px; font-weight: 600;">Current Image</label>
        <?php if (!empty($ad['image_path'])): ?>
          <img src="<?php echo h(getImagePath($ad['image_path'])); ?>" alt="Current image" style="max-width: 300px; border-radius: 4px; margin-bottom: 12px;" />
        <?php else: ?>
          <div class="muted">No image</div>
        <?php endif; ?>
        
        <label style="display: block; margin-bottom: 4px; margin-top: 16px; font-weight: 600;">Replace Image (optional)</label>
        <input class="input" type="file" name="photo" accept="image/jpeg,image/png" />
        <div class="muted" style="margin-top: 4px;">JPG/PNG, max 2MB</div>
        
        <div style="display: flex; gap: 12px; margin-top: 24px;">
          <button class="btn" type="submit">Save Changes</button>
          <a class="btn ghost" href="admin-console.php">Cancel</a>
        </div>
      </form>
    </article>
  </main>

  <footer class="footer">
    <div class="container footer-inner">
      <div><strong>QuickAnnonce</strong><div class="muted">© <span id="year"></span></div></div>
      <div style="display:flex; gap:12px;"><a href="contact.php">Contact</a></div>
    </div>
  </footer>
  <script>
    document.getElementById('year').textContent = new Date().getFullYear();
  </script>
  <script src="js/mobile-toggle.js"></script>
</body>
</html>

