<?php
  include 'config.php';
  if (!isLoggedIn() || !isAdmin()) {
    header('Location: login.php');
    exit;
  }
  $pdo = getPDO();
  
  // Search and filter parameters
  $search = trim($_GET['search'] ?? '');
  $status = trim($_GET['status'] ?? '');
  $etat = trim($_GET['etat'] ?? '');
  $dateFrom = trim($_GET['date_from'] ?? '');
  $dateTo = trim($_GET['date_to'] ?? '');
  
  // Build query
  $where = [];
  $params = [];
  
  if ($search !== '') {
    $where[] = '(a.titre LIKE :search OR a.description LIKE :search OR u.nom LIKE :search OR u.email LIKE :search)';
    $params[':search'] = "%{$search}%";
  }
  
  if ($status !== '') {
    $where[] = 'a.moderation_status = :status';
    $params[':status'] = $status;
  }
  
  if ($etat !== '') {
    $where[] = 'a.etat = :etat';
    $params[':etat'] = $etat;
  }
  
  if ($dateFrom !== '') {
    $where[] = 'DATE(a.date_publication) >= :date_from';
    $params[':date_from'] = $dateFrom;
  }
  
  if ($dateTo !== '') {
    $where[] = 'DATE(a.date_publication) <= :date_to';
    $params[':date_to'] = $dateTo;
  }
  
  $sql = "SELECT a.*, u.nom AS user_name, u.email AS user_email 
          FROM annonces a 
          LEFT JOIN utilisateurs u ON a.id_utilisateur = u.id";
  
  if (!empty($where)) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
  }
  
  $sql .= ' ORDER BY a.date_publication DESC LIMIT 100';
  
  $ads = [];
  try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $ads = $stmt->fetchAll();
  } catch (Throwable $e) {
    error_log('Admin manage ads error: ' . $e->getMessage());
  }
  
  // Get counts for filter display
  $totalAds = $pendingCount = $approvedCount = $rejectedCount = 0;
  try {
    $totalAds = (int)$pdo->query("SELECT COUNT(*) FROM annonces")->fetchColumn();
    $pendingCount = (int)$pdo->query("SELECT COUNT(*) FROM annonces WHERE moderation_status = 'pending'")->fetchColumn();
    $approvedCount = (int)$pdo->query("SELECT COUNT(*) FROM annonces WHERE moderation_status = 'approved'")->fetchColumn();
    $rejectedCount = (int)$pdo->query("SELECT COUNT(*) FROM annonces WHERE moderation_status = 'rejected'")->fetchColumn();
  } catch (Throwable $e) {}
?>
<!DOCTYPE html>
<html lang="en" class="grid">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Manage Ads - Admin - QuickAnnonce</title>
  <link rel="stylesheet" href="css/styles.css" />
  <style>
    .filter-section { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 20px; margin-bottom: 24px; }
    .filter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; }
    .badge { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 600; }
    .badge-pending { background: #fef3c7; color: #92400e; }
    .badge-approved { background: #d1fae5; color: #065f46; }
    .badge-rejected { background: #fee2e2; color: #991b1b; }
    .badge-active { background: #dbeafe; color: #1e40af; }
    .badge-inactive { background: #f3f4f6; color: #374151; }
    .ad-actions { display: flex; gap: 8px; flex-wrap: wrap; }
    /* Keep footer at bottom but compact */
    html, body {
      height: 100%;
      margin: 0;
      display: flex;
      flex-direction: column;
    }

    main.container {
      flex: 1;
    }

    /* Compact footer */
    .footer {
      margin-top: auto;
      background: #fff;
      border-top: 1px solid #e5e7eb;
      padding: 8px 0; /* reduced padding */
      font-size: 1rem; /* smaller text */
    }

    .footer .container {
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

  </style>
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

  <main class="container">
    <div class="section-title">
      <h1>Manage All Ads</h1>
      <a class="btn ghost" href="admin-console.php">← Back to Dashboard</a>
    </div>
    
    <!-- Filter Section -->
    <div class="filter-section">
      <h3 style="margin-top: 0;">Search & Filter</h3>
      <form method="get" action="admin-manage-ads.php">
        <div class="filter-grid">
          <div>
            <label style="display: block; margin-bottom: 4px; font-size: 0.875rem; color: #374151;">Search</label>
            <input class="input" type="text" name="search" placeholder="Title, description, user..." value="<?php echo h($search); ?>" />
          </div>
          <div>
            <label style="display: block; margin-bottom: 4px; font-size: 0.875rem; color: #374151;">Moderation Status</label>
            <select class="input" name="status">
              <option value="">All</option>
              <option value="pending" <?php echo $status==='pending'?'selected':''; ?>>Pending (<?php echo $pendingCount; ?>)</option>
              <option value="approved" <?php echo $status==='approved'?'selected':''; ?>>Approved (<?php echo $approvedCount; ?>)</option>
              <option value="rejected" <?php echo $status==='rejected'?'selected':''; ?>>Rejected (<?php echo $rejectedCount; ?>)</option>
            </select>
          </div>
          <div>
            <label style="display: block; margin-bottom: 4px; font-size: 0.875rem; color: #374151;">Ad Status</label>
            <select class="input" name="etat">
              <option value="">All</option>
              <option value="active" <?php echo $etat==='active'?'selected':''; ?>>Active</option>
              <option value="inactive" <?php echo $etat==='inactive'?'selected':''; ?>>Inactive</option>
            </select>
          </div>
          <div>
            <label style="display: block; margin-bottom: 4px; font-size: 0.875rem; color: #374151;">From Date</label>
            <input class="input" type="date" name="date_from" value="<?php echo h($dateFrom); ?>" />
          </div>
          <div>
            <label style="display: block; margin-bottom: 4px; font-size: 0.875rem; color: #374151;">To Date</label>
            <input class="input" type="date" name="date_to" value="<?php echo h($dateTo); ?>" />
          </div>
        </div>
        <div style="display: flex; gap: 12px; margin-top: 16px;">
          <button class="btn" type="submit">Apply Filters</button>
          <a class="btn ghost" href="admin-manage-ads.php">Clear</a>
        </div>
      </form>
    </div>
    
    <!-- Results -->
    <div class="section-title">
      <h2>Results (<?php echo count($ads); ?>)</h2>
    </div>
    
    <div class="cards list">
      <?php if (empty($ads)): ?>
        <div class="muted">No ads found matching your criteria.</div>
      <?php else: ?>
        <?php foreach ($ads as $ad): ?>
          <article class="card">
            <img src="<?php echo h(getImagePath($ad['image_path'] ?? null)); ?>" alt="Ad image" style="width: 150px; height: 100px; object-fit: cover; border-radius: 4px;" />
            <div class="card-body" style="flex: 1;">
              <div class="card-title"><?php echo h($ad['titre']); ?></div>
              <div class="card-meta">
                By <?php echo h($ad['user_name'] ?? 'Unknown'); ?> (<?php echo h($ad['user_email'] ?? 'N/A'); ?>) • 
                <?php echo h(date('Y-m-d H:i', strtotime($ad['date_publication']))); ?>
              </div>
              <div style="margin-top: 8px; display: flex; gap: 8px; flex-wrap: wrap;">
                <span class="badge badge-<?php echo $ad['moderation_status']; ?>"><?php echo ucfirst($ad['moderation_status']); ?></span>
                <span class="badge badge-<?php echo $ad['etat']; ?>"><?php echo ucfirst($ad['etat']); ?></span>
              </div>
              <div style="margin-top: 8px; font-size: 0.875rem; color: #6b7280;">
                <?php echo h(substr($ad['description'] ?? '', 0, 150)); ?><?php echo strlen($ad['description'] ?? '') > 150 ? '...' : ''; ?>
              </div>
              <div class="ad-actions" style="margin-top: 12px;">
                <a class="btn ghost" href="ad-details.php?id=<?php echo (int)$ad['id']; ?>">See details</a>
                <?php if ($ad['moderation_status'] !== 'approved'): ?>
                  <form method="post" action="admin-moderate.php" style="display:inline;">
                    <input type="hidden" name="csrf" value="<?php echo h(csrf_token()); ?>" />
                    <input type="hidden" name="id" value="<?php echo (int)$ad['id']; ?>" />
                    <input type="hidden" name="action" value="approve" />
                    <input type="hidden" name="redirect" value="admin-manage-ads.php<?php echo $search ? '?search=' . urlencode($search) : ''; ?>" />
                    <button class="btn" type="submit">Approve</button>
                  </form>
                <?php endif; ?>
                <?php if ($ad['moderation_status'] !== 'rejected'): ?>
                  <form method="post" action="admin-moderate.php" style="display:inline;" onsubmit="return confirm('Reject this ad?');">
                    <input type="hidden" name="csrf" value="<?php echo h(csrf_token()); ?>" />
                    <input type="hidden" name="id" value="<?php echo (int)$ad['id']; ?>" />
                    <input type="hidden" name="action" value="reject" />
                    <input type="hidden" name="redirect" value="admin-manage-ads.php<?php echo $search ? '?search=' . urlencode($search) : ''; ?>" />
                    <button class="btn danger" type="submit">Reject</button>
                  </form>
                <?php endif; ?>
                <a class="btn ghost" href="admin-edit-ad.php?id=<?php echo (int)$ad['id']; ?>">Edit</a>
                <form method="post" action="admin-delete-ad.php" style="display:inline;" onsubmit="return confirm('Delete this ad permanently?');">
                  <input type="hidden" name="csrf" value="<?php echo h(csrf_token()); ?>" />
                  <input type="hidden" name="id" value="<?php echo (int)$ad['id']; ?>" />
                  <input type="hidden" name="redirect" value="admin-manage-ads.php<?php echo $search ? '?search=' . urlencode($search) : ''; ?>" />
                  <button class="btn danger" type="submit">Delete</button>
                </form>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      <?php endif; ?>
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
  </script>
  <script src="js/mobile-toggle.js"></script>
</body>
</html>

