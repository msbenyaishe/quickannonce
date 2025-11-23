<?php
  include 'config.php';
  $pdo = getPDO();
  
  // Get ad ID from URL
  $adId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
  
  if ($adId <= 0) {
    header('Location: index.php');
    exit;
  }
  
  // Fetch ad details with user information
  $ad = null;
  $error = '';
  
  try {
    // Check if user is logged in and their role
    $isAdmin = isLoggedIn() && isAdmin();
    $isOwner = false;
    $userId = isLoggedIn() ? currentUserId() : null;
    
    // Build query based on user permissions
    if ($isAdmin) {
      // Admins can see all ads (pending, approved, rejected)
      $stmt = $pdo->prepare("
        SELECT a.*, u.nom AS user_name, u.email AS user_email, u.phone AS user_phone
        FROM annonces a 
        LEFT JOIN utilisateurs u ON a.id_utilisateur = u.id 
        WHERE a.id = ?
      ");
    } else {
      // Regular users and guests can only see approved ads
      $stmt = $pdo->prepare("
        SELECT a.*, u.nom AS user_name, u.email AS user_email, u.phone AS user_phone
        FROM annonces a 
        LEFT JOIN utilisateurs u ON a.id_utilisateur = u.id 
        WHERE a.id = ? AND a.moderation_status = 'approved'
      ");
    }
    
    $stmt->execute([$adId]);
    $ad = $stmt->fetch();
    
    if ($ad) {
      // Check if current user is the owner
      $isOwner = ($userId && $ad['id_utilisateur'] == $userId);
      
      // If not admin, not owner, and ad is not approved, redirect
      if (!$isAdmin && !$isOwner && $ad['moderation_status'] !== 'approved') {
        header('Location: index.php');
        exit;
      }
    } else {
      $error = 'Ad not found or you do not have permission to view it.';
    }
  } catch (Throwable $e) {
    $error = 'Error loading ad details.';
    error_log('Ad details error: ' . $e->getMessage());
  }
  
  // If ad not found, redirect
  if (!$ad && !$error) {
    header('Location: index.php');
    exit;
  }
  
  // Calculate days since publication
  $daysAgo = $ad ? (int)max(0, (new DateTime())->diff(new DateTime($ad['date_publication']))->days) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?php echo $ad ? h($ad['titre']) : 'Ad Details'; ?> - QuickAnnonce</title>
  <link rel="stylesheet" href="css/styles.css" />
  <style>
    .ad-details {
      max-width: 1200px;
      margin: 0 auto;
      padding: 2.5rem 1.5rem;
    }
    .ad-header {
      margin-bottom: 2.5rem;
      padding-bottom: 0.5rem;
    }
    .ad-title {
      font-size: 2.5rem;
      font-weight: 700;
      margin-bottom: 1.25rem;
      margin-top: 0.5rem;
      color: #111827;
      line-height: 1.2;
    }
    .ad-price {
      font-size: 2rem;
      font-weight: 700;
      color: #059669;
      margin-bottom: 1.5rem;
      margin-top: 0.5rem;
      padding: 0.5rem 0;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }
    .ad-price::before {
      content: '€';
      font-size: 1.5rem;
      color: #059669;
    }
    .ad-meta {
      display: flex;
      flex-wrap: wrap;
      gap: 1rem;
      color: #6b7280;
      font-size: 0.875rem;
      margin-bottom: 2.5rem;
      margin-top: 0.5rem;
      padding: 0.75rem 0;
      align-items: center;
    }
    .ad-content {
      display: grid;
      grid-template-columns: 1fr 420px;
      gap: 2.5rem;
      margin-bottom: 3rem;
      margin-top: 1rem;
    }
    .ad-image {
      width: 100%;
      height: 450px;
      object-fit: cover;
      border-radius: 12px;
      background: #f3f4f6;
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
      transition: transform 0.3s ease;
      margin-bottom: 2rem;
    }
    .ad-image:hover {
      transform: scale(1.02);
    }
    .ad-info {
      background: #fff;
      border: 1px solid #e5e7eb;
      border-radius: 12px;
      padding: 2.25rem;
      box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
      position: sticky;
      top: 2rem;
      height: fit-content;
    }
    .info-item {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 10px;
      margin: 0.25rem 0;
      border-bottom: 1px solid #f3f4f6;
    }
    .info-item:last-child {
      border-bottom: none;
      padding-bottom: 0;
    }
    .info-item:first-child {
      padding-top: 0.5rem;
    }
    .info-label {
      color: #6b7280;
      font-weight: 500;
      display: flex;
      align-items: center;
      gap: 0.5rem;
      padding-right: 1rem;
    }
    .info-value {
      color: #111827;
      font-weight: 600;
      text-align: right;
      padding-left: 1rem;
    }
    .info-value.phone-value {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      color: #059669;
    }
    .ad-description {
      background: #fff;
      border: 1px solid #e5e7eb;
      border-radius: 12px;
      padding: 2.25rem;
      margin-bottom: 2.5rem;
      margin-top: 0.5rem;
      box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
    }
    .ad-description h3 {
      font-size: 1.5rem;
      font-weight: 600;
      margin-bottom: 1.5rem;
      margin-top: 0.25rem;
      color: #111827;
      padding-bottom: 1rem;
      border-bottom: 2px solid #e5e7eb;
    }
    .ad-description p {
      color: #374151;
      line-height: 1.8;
      white-space: pre-wrap;
      font-size: 1rem;
      margin-top: 0.5rem;
      padding-top: 0.5rem;
    }
    .status-badge {
      display: inline-block;
      padding: 6px 14px;
      border-radius: 12px;
      font-size: 0.75rem;
      font-weight: 600;
      margin: 0 0.25rem;
    }
    .status-approved {
      background: #d1fae5;
      color: #065f46;
    }
    .status-pending {
      background: #fef3c7;
      color: #92400e;
    }
    .status-rejected {
      background: #fee2e2;
      color: #991b1b;
    }
    .status-active {
      background: #dbeafe;
      color: #1e40af;
    }
    .status-inactive {
      background: #e5e7eb;
      color: #4b5563;
    }
    .price-section {
      background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
      border: 2px solid #86efac;
      border-radius: 12px;
      padding: 1.75rem;
      margin-bottom: 2rem;
      margin-top: 0.5rem;
      text-align: center;
    }
    .phone-section {
      background: #f0fdfa;
      border: 1px solid #a7f3d0;
      border-radius: 8px;
      padding: 1.25rem;
      margin-top: 1.5rem;
      margin-bottom: 0.5rem;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.75rem;
    }
    @media (max-width: 768px) {
      .ad-details {
        padding: 1.5rem 1rem;
      }
      .ad-header {
        margin-bottom: 1.5rem;
      }
      .ad-content {
        grid-template-columns: 1fr;
        gap: 2rem;
        margin-bottom: 2rem;
      }
      .ad-title {
        font-size: 1.75rem;
        margin-bottom: 1rem;
      }
      .ad-price {
        font-size: 1.5rem;
        margin-bottom: 1.25rem;
      }
      .ad-meta {
        margin-bottom: 2rem;
        padding: 0.5rem 0;
      }
      .ad-info {
        position: relative;
        top: 0;
        padding: 1.75rem;
      }
      .ad-image {
        height: 300px;
        margin-bottom: 1.5rem;
      }
      .ad-description {
        padding: 1.75rem;
        margin-bottom: 2rem;
      }
      .info-item {
        padding: 0.875rem 0;
      }
      .price-section {
        padding: 1.5rem;
        margin-bottom: 1.75rem;
      }
      .phone-section {
        padding: 1rem;
        margin-top: 1.25rem;
      }
    }

    html, body {
      height: 100%;
      margin: 0;
      padding: 0;
    }

    body {
      display: flex;
      flex-direction: column;
      min-height: 100vh;
    }

    main.container.ad-details {
      flex: 1;
    }

    /* Footer styling — clean and proportional */
    .footer {
      margin-top: auto;
      background: #f9fafb;
      border-top: 1px solid #e5e7eb;
      padding: 0.9rem 0; /* Perfect balance: not too big, not too small */
      font-size: 0.95rem;
    }

    .footer .footer-inner {
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 0.75rem;
      max-width: 1200px;
      margin: 0 auto;
      padding: 0 1rem;
    }

    .footer a {
      color: #374151;
      text-decoration: none;
      transition: color 0.2s;
    }

    .footer a:hover {
      color: #111827;
    }

  </style>
</head>
<body>
  <?php if (isLoggedIn() && isAdmin()): ?>
    <!-- ADMIN NAVBAR -->
    <header class="header">
      <div class="container header-inner">
        <a class="brand" href="index-admin.php">
          <span class="brand-logo">QA</span>
          <span class="brand-name">QuickAnnonce</span>
        </a>
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
  <?php elseif (isLoggedIn() && !isAdmin()): ?>
    <!-- USER NAVBAR -->
    <header class="header">
      <div class="container header-inner">
        <a class="brand" href="index-user.php">
          <span class="brand-logo">QA</span>
          <span class="brand-name">QuickAnnonce</span>
        </a>
        <nav class="nav">
          <a href="index-user.php">Home</a>
          <a href="user-consult-user.php">Announcements</a>
          <a href="profile.php">Profile</a>
          <a href="contact-user.php">Contact</a>
          <a href="logout.php">Logout</a>
        </nav>
        <div style="display:flex; gap:10px; align-items:center;">
          <a class="cta" href="post-user.php">Post Your Ad</a>
          <button class="mobile-toggle" aria-label="Toggle navigation">☰</button>
        </div>
      </div>
    </header>
  <?php else: ?>
    <!-- GUEST NAVBAR -->
    <header class="header">
      <div class="container header-inner">
        <a class="brand" href="index.php">
          <span class="brand-logo">QA</span>
          <span class="brand-name">QuickAnnonce</span>
        </a>
        <nav class="nav">
          <a href="index.php">Home</a>
          <a href="user-consult.php">Announcements</a>
          <a href="login.php">Login</a>
          <a href="register.php">Register</a>
          <a href="contact.php">Contact</a>
          <a href="admin-login.php">Admin</a>
        </nav>
        <div style="display:flex; gap:10px; align-items:center;">
          <button class="mobile-toggle" aria-label="Toggle navigation">☰</button>
        </div>
      </div>
    </header>
  <?php endif; ?>

  <main class="container ad-details">
    <?php if ($error): ?>
      <div class="card">
        <div class="card-body">
          <div class="card-title" style="color: #dc2626;"><?php echo h($error); ?></div>
          <a class="btn" href="index.php">Go to Home</a>
        </div>
      </div>
    <?php elseif ($ad): ?>
      <div class="ad-header">
        <a class="btn ghost" href="javascript:history.back()">← Back</a>
      </div>
      
      <h1 class="ad-title"><?php echo h($ad['titre']); ?></h1>
      
      <?php if (!empty($ad['prix']) && $ad['prix'] > 0): ?>
        <div class="ad-price"><?php echo number_format((float)$ad['prix'], 2, '.', ''); ?></div>
      <?php endif; ?>
      
      <div class="ad-meta">
        <span>By <?php echo h($ad['user_name'] ?? 'Unknown'); ?></span>
        <span>•</span>
        <span><?php echo $daysAgo; ?> day<?php echo $daysAgo !== 1 ? 's' : ''; ?> ago</span>
        <?php if (!empty($ad['ville'])): ?>
          <span>•</span>
          <span>📍 <?php echo h($ad['ville']); ?></span>
        <?php endif; ?>
        <?php if (!empty($ad['categorie'])): ?>
          <span>•</span>
          <span>🏷️ <?php echo h($ad['categorie']); ?></span>
        <?php endif; ?>
        <span>•</span>
        <span class="status-badge status-<?php echo h($ad['moderation_status']); ?>">
          <?php echo ucfirst($ad['moderation_status']); ?>
        </span>
        <span class="status-badge status-<?php echo h($ad['etat']); ?>">
          <?php echo ucfirst($ad['etat']); ?>
        </span>
      </div>
      
      <div class="ad-content">
        <div>
          <?php if ($ad['image_path']): ?>
            <img src="<?php echo h(getImagePath($ad['image_path'])); ?>" alt="<?php echo h($ad['titre']); ?>" class="ad-image" />
          <?php else: ?>
            <div class="ad-image" style="display: flex; align-items: center; justify-content: center; color: #9ca3af;">
              No image available
            </div>
          <?php endif; ?>
          
          <div class="ad-description">
            <h3>Description</h3>
            <p><?php echo nl2br(h($ad['description'])); ?></p>
          </div>
        </div>
        
        <div class="ad-info">
          <h3 style="margin-bottom: 2rem; margin-top: 0.25rem; font-size: 1.5rem; font-weight: 600; color: #111827;">Ad Informations</h3>
          
          <?php if (!empty($ad['prix']) && $ad['prix'] > 0): ?>
            <div class="price-section">
              <div style="font-size: 0.875rem; color: #059669; margin-bottom: 0.75rem; font-weight: 600;">Price</div>
              <div style="font-size: 2rem; font-weight: 700; color: #047857;">€<?php echo number_format((float)$ad['prix'], 2, '.', ''); ?></div>
            </div>
          <?php endif; ?>
          
          <div class="info-item">
            <span class="info-label">👤 Posted by</span>
            <span class="info-value"><?php echo h($ad['user_name'] ?? 'Unknown'); ?></span>
          </div>
          
          <?php if (!empty($ad['ville'])): ?>
            <div class="info-item">
              <span class="info-label">📍 City</span>
              <span class="info-value"><?php echo h($ad['ville']); ?></span>
            </div>
          <?php endif; ?>
          
          <?php if (!empty($ad['categorie'])): ?>
            <div class="info-item">
              <span class="info-label">🏷️ Category</span>
              <span class="info-value"><?php echo h($ad['categorie']); ?></span>
            </div>
          <?php endif; ?>
          
          <div class="info-item">
            <span class="info-label">📅 Published</span>
            <span class="info-value"><?php echo date('M j, Y', strtotime($ad['date_publication'])); ?></span>
          </div>
          
          <div class="info-item">
            <span class="info-label">Status</span>
            <span class="info-value">
              <span class="status-badge status-<?php echo h($ad['etat']); ?>">
                <?php echo ucfirst($ad['etat']); ?>
              </span>
            </span>
          </div>
          
          <div class="info-item">
            <span class="info-label">Moderation</span>
            <span class="info-value">
              <span class="status-badge status-<?php echo h($ad['moderation_status']); ?>">
                <?php echo ucfirst($ad['moderation_status']); ?>
              </span>
            </span>
          </div>
          
          <?php if (!empty($ad['phone'])): ?>
            <div class="phone-section">
              <span style="font-size: 1.25rem;">📞</span>
              <a href="tel:<?php echo h($ad['phone']); ?>" style="color: #059669; font-weight: 600; font-size: 1.1rem; text-decoration: none;">
                <?php echo h($ad['phone']); ?>
              </a>
            </div>
          <?php endif; ?>
          
          <?php if ($isAdmin || $isOwner): ?>
            <div style="margin-top: 2rem; padding-top: 2rem; border-top: 1px solid #e5e7eb;">
              <?php if ($isAdmin): ?>
                <a class="btn" href="admin-edit-ad.php?id=<?php echo (int)$ad['id']; ?>" style="width: 100%; margin-bottom: 0.75rem;">
                  Edit Ad (Admin)
                </a>
              <?php endif; ?>
              <?php if ($isOwner && !isAdmin()): ?>
                <div class="muted" style="font-size: 0.875rem; text-align: center; padding: 0.5rem 0;">
                  This is your ad
                </div>
              <?php endif; ?>
            </div>
          <?php endif; ?>
          
          <div style="margin-top: 2rem; padding-top: 2rem; border-top: 1px solid #e5e7eb; display: flex; flex-direction: column; gap: 0.75rem;">
            <a class="btn" href="contact.php" style="width: 100%; margin-bottom: 0.75rem;" style="margin-right: 15px;">
              Contact Seller
            </a>
            <a class="btn ghost" href="javascript:history.back()" style="width: 100%;">
              Back to Listings
            </a>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </main>

  <footer class="footer">
    <div class="container footer-inner">
      <div><strong>QuickAnnonce</strong><div class="muted">© <span id="year"></span> All rights reserved.</div></div>
      <div style="display:flex; gap:12px;">
        <a href="contact.php">Contact</a>
        <a href="#">Privacy</a>
        <a href="#">Terms</a>
      </div>
    </div>
  </footer>

  <script>
    document.getElementById('year').textContent = new Date().getFullYear();
  </script>
  <script src="js/mobile-toggle.js"></script>
</body>
</html>

