<?php
  include 'config.php';
  if (!isLoggedIn() || !isAdmin()) {
    header('Location: login.php');
    exit;
  }
  $pdo = getPDO();
  $post_error = '';
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf($_POST['csrf'] ?? null)) {
      http_response_code(403);
      exit('Invalid CSRF token');
    }
    $titre = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $prix = !empty($_POST['price']) ? (float)$_POST['price'] : null;
    $phone = trim($_POST['phone'] ?? '');
    $ville = trim($_POST['city'] ?? '');
    $categorie = trim($_POST['category'] ?? '');
    
    // Validate phone format if provided
    if ($phone !== '' && !preg_match('/^[\d\s\-\+\(\)]+$/', $phone)) {
      $post_error = 'Please enter a valid phone number.';
    } elseif ($titre === '' || $description === '') {
      $post_error = 'Please provide a title and description.';
    } else {
      try {
        $imagePath = null;
        if (!empty($_FILES['photo']['name'])) {
          $uploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads';
          if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0755, true); }
          $tmp = $_FILES['photo']['tmp_name'];
          $finfo = new finfo(FILEINFO_MIME_TYPE);
          $mime = $finfo->file($tmp);
          if (!in_array($mime, ['image/jpeg','image/png'], true)) { throw new RuntimeException('Only JPG/PNG files are allowed.'); }
          if ($_FILES['photo']['size'] > 2 * 1024 * 1024) { throw new RuntimeException('Image too large (max 2MB).'); }
          $ext = $mime === 'image/png' ? 'png' : 'jpg';
          $newName = 'ad_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
          $destFs = $uploadDir . DIRECTORY_SEPARATOR . $newName;
          if (!@move_uploaded_file($tmp, $destFs)) { throw new RuntimeException('Failed to save image.'); }
          $imagePath = 'uploads/' . $newName;
        }
        // Admin posts: use admin_id to create a system entry or use default user
        $userId = currentUserId() ?? 1; // Attribute to admin id when available
        // Admin posts are immediately approved and active
        $stmt = $pdo->prepare('INSERT INTO annonces (titre, description, date_publication, etat, moderation_status, image_path, id_utilisateur, prix, phone, ville, categorie) VALUES (?, ?, NOW(), "active", "approved", ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$titre, $description, $imagePath, $userId, $prix, $phone !== '' ? $phone : null, $ville !== '' ? $ville : null, $categorie !== '' ? $categorie : null]);
        simulateAfterInsertAnnonce($pdo, $userId);
        require_once __DIR__ . '/includes/log_action.php';
        log_action("creation_annonce", ["titre" => $titre]);
        header('Location: index-admin.php');
        exit;
      } catch (Throwable $e) {
        $post_error = 'Could not publish announcement.';
      }
    }
  }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Post Announcement - QuickAnnonce</title>
  <link rel="stylesheet" href="css/styles.css" />
</head>
<body>
  <header class="header">
    <div class="container header-inner">
      <a class="brand" href="index-admin.php"><span class="brand-logo">QA</span><span class="brand-name">QuickAnnonce</span></a>
      <!-- ADMIN NAVBAR: Home, Announcements, Admin, Logout -->
      <nav class="nav">
        <a href="index-admin.php">Home</a>
        <a href="user-consult-admin.php">Announcements</a>
        <a href="admin-console.php">Admin</a>
        <a href="logout.php">Logout</a>
      </nav>
      <div style="display:flex; gap:10px; align-items:center;">
        <a class="cta" href="post-admin.php">Post Your Ad</a>
        <button class="mobile-toggle" aria-label="Toggle navigation">☰</button>
      </div>
    </div>
  </header>

  <section class="post-head">
    <div class="container">
      <h1>Post your announcement</h1>
      <p class="muted">Create a clear ad to sell faster. Photos must be JPG and ≤ 100KB each.</p>
    </div>
  </section>

  <section class="container post-grid">
    <!-- Left: Form card -->
    <div class="post-card">
      <form id="post-form" class="simple-form" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?php echo h(csrf_token()); ?>" />
        <div class="two">
          <input class="input" id="title" name="title" type="text" placeholder="Title (e.g., iPhone 12 like new)" required />
          <select class="input" id="category" name="category" required>
            <option value="" disabled selected>Select category</option>
            <option>Vehicles</option>
            <option>Real Estate</option>
            <option>Electronics</option>
            <option>Clothing</option>
            <option>Jobs</option>
          </select>
        </div>
        <div class="two">
          <input class="input" id="price" name="price" type="number" step="0.01" min="0" placeholder="Price" required />
          <select class="input" id="city" name="city" required>
            <option value="" disabled selected>Select city</option>
            <option>Agadir</option>
            <option>Ahfir</option>
            <option>Al Hoceima</option>
            <option>Arfoud</option>
            <option>Asilah</option>
            <option>Azemmour</option>
            <option>Azilal</option>
            <option>Beni Mellal</option>
            <option>Berkane</option>
            <option>Berrechid</option>
            <option>Bouarfa</option>
            <option>Boujdour</option>
            <option>Boulemane</option>
            <option>Bouskoura</option>
            <option>Casablanca</option>
            <option>Chefchaouen</option>
            <option>Chichaoua</option>
            <option>Dakhla</option>
            <option>Dar Bouazza</option>
            <option>Demnate</option>
            <option>El Hajeb</option>
            <option>El Jadida</option>
            <option>El Kelaa des Sraghna</option>
            <option>Errachidia</option>
            <option>Essaouira</option>
            <option>Fès</option>
            <option>Fnideq</option>
            <option>Fquih Ben Salah</option>
            <option>Guelmim</option>
            <option>Guercif</option>
            <option>Ifrane</option>
            <option>Imzouren</option>
            <option>Inzegane</option>
            <option>Jerada</option>
            <option>Kalaat Mgouna</option>
            <option>Kenitra</option>
            <option>Khemisset</option>
            <option>Khenifra</option>
            <option>Khouribga</option>
            <option>Ksar El Kebir</option>
            <option>Larache</option>
            <option>Laayoune</option>
            <option>Marrakech</option>
            <option>Martil</option>
            <option>Mediouna</option>
            <option>Mechra Bel Ksiri</option>
            <option>Meknès</option>
            <option>Midelt</option>
            <option>Mohammedia</option>
            <option>Nador</option>
            <option>Ouarzazate</option>
            <option>Ouezzane</option>
            <option>Oujda</option>
            <option>Oulad Teima</option>
            <option>Rabat</option>
            <option>Safi</option>
            <option>Salé</option>
            <option>Sefrou</option>
            <option>Settat</option>
            <option>Sidi Bennour</option>
            <option>Sidi Ifni</option>
            <option>Sidi Kacem</option>
            <option>Sidi Slimane</option>
            <option>Skhirat</option>
            <option>Smara</option>
            <option>Souk El Arbaa</option>
            <option>Tafraout</option>
            <option>Taliouine</option>
            <option>Tan-Tan</option>
            <option>Tanger</option>
            <option>Taounate</option>
            <option>Tarfaya</option>
            <option>Taroudant</option>
            <option>Tata</option>
            <option>Taza</option>
            <option>Témara</option>
            <option>Tétouan</option>
            <option>Tinghir</option>
            <option>Tiznit</option>
            <option>Youssoufia</option>
            <option>Zagora</option>
          </select>
        </div>
        <div class="two">
          <input class="input" id="phone" name="phone" type="tel" placeholder="Phone number (optional)" pattern="[\d\s\-\+\(\)]+" />
        </div>
        <textarea class="input" id="description" name="description" placeholder="Describe your item..." required></textarea>

        <?php if ($post_error !== ''): ?>
          <div class="login-message error" style="margin-bottom:12px;"><?php echo h($post_error); ?></div>
        <?php endif; ?>
        <div class="uploader">
          <div class="uploader-area">
            <div class="uploader-icon">📷</div>
            <div>
              <div class="card-title">Upload a photo</div>
              <div class="card-meta">JPG/PNG, up to 2MB</div>
            </div>
          </div>
          <input id="photo" name="photo" type="file" accept="image/jpeg,image/png" />
        </div>

        <div class="actions">
          <button class="btn" type="submit">Publish</button>
          <a class="btn ghost" href="user-consult-admin.php">Cancel</a>
        </div>
      </form>
    </div>

    <!-- Right: Tips and preview -->
    <aside class="tips">
      <div class="tips-card">
        <div class="card-title">Posting tips</div>
        <ul>
          <li>Use a clear, specific title.</li>
          <li>Go Pro to upload more than one picture.</li>
          <li>Be honest and include key details.</li>
          <li>Set a fair, competitive price.</li>
        </ul>
      </div>
      <div class="preview-card">
        <div class="preview-image">Image preview</div>
        <div class="card-body">
          <div class="card-title">Your title will appear here</div>
          <div class="card-meta">City • Price</div>
        </div>
      </div>
    </aside>
  </section>

  <footer class="footer">
    <div class="container footer-inner">
      <div><strong>QuickAnnonce</strong><div class="muted">© <span id="year"></span></div></div>
      <div style="display:flex; gap:12px;"><a href="admin-console.php">Admin</a></div>
    </div>
  </footer>
  <script>
    document.getElementById('year').textContent = new Date().getFullYear();
  </script>
  <script src="js/post-preview.js"></script>
  <script src="js/mobile-toggle.js"></script>
</body>
</html>
