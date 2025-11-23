<?php
  include 'config.php';
  if (!isLoggedIn() || !isAdmin()) {
    header('Location: login.php');
    exit;
  }
  $pdo = getPDO();
  
  // Search and filter parameters
  $search = trim($_GET['search'] ?? '');
  $role = trim($_GET['role'] ?? '');
  $dateFrom = trim($_GET['date_from'] ?? '');
  $dateTo = trim($_GET['date_to'] ?? '');
  $sortBy = trim($_GET['sort'] ?? 'created_at');
  $sortOrder = trim($_GET['order'] ?? 'DESC');
  
  // Build query
  $where = [];
  $params = [];
  
  if ($search !== '') {
    $where[] = '(u.nom LIKE :search OR u.email LIKE :search)';
    $params[':search'] = "%{$search}%";
  }
  
  if ($role !== '') {
    $where[] = 'u.role = :role';
    $params[':role'] = $role;
  }
  
  if ($dateFrom !== '') {
    $where[] = 'DATE(u.created_at) >= :date_from';
    $params[':date_from'] = $dateFrom;
  }
  
  if ($dateTo !== '') {
    $where[] = 'DATE(u.created_at) <= :date_to';
    $params[':date_to'] = $dateTo;
  }
  
  $sql = "SELECT u.*, 
          (SELECT COUNT(*) FROM annonces WHERE id_utilisateur = u.id) AS total_ads,
          (SELECT COUNT(*) FROM annonces WHERE id_utilisateur = u.id AND moderation_status = 'approved') AS approved_ads
          FROM utilisateurs u";
  
  if (!empty($where)) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
  }
  
  // Validate sort column - fallback to 'id' if created_at doesn't exist
  $allowedSorts = ['nom', 'email', 'created_at', 'nb_annonces', 'id'];
  $sortBy = in_array($sortBy, $allowedSorts) ? $sortBy : 'id';
  $sortOrder = strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC';
  
  // Try with created_at first, fallback if column doesn't exist
  try {
    $sql .= " ORDER BY u.{$sortBy} {$sortOrder} LIMIT 100";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll();
  } catch (Throwable $e) {
    // If created_at or role column doesn't exist, use simpler query
    if (strpos($e->getMessage(), 'created_at') !== false || strpos($e->getMessage(), 'role') !== false) {
      $sql = "SELECT u.*, 
              (SELECT COUNT(*) FROM annonces WHERE id_utilisateur = u.id) AS total_ads,
              (SELECT COUNT(*) FROM annonces WHERE id_utilisateur = u.id AND moderation_status = 'approved') AS approved_ads
              FROM utilisateurs u";
      if (!empty($where)) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
      }
      $sql .= " ORDER BY u.id {$sortOrder} LIMIT 100";
      $stmt = $pdo->prepare($sql);
      $stmt->execute($params);
      $users = $stmt->fetchAll();
      // Add defaults for missing columns
      foreach ($users as &$u) {
        if (!isset($u['created_at'])) $u['created_at'] = date('Y-m-d');
        if (!isset($u['role'])) $u['role'] = 'user';
      }
    } else {
      error_log('Admin manage users error: ' . $e->getMessage());
    }
  }
  
  // Get counts - handle missing role column gracefully
  $totalUsers = $adminCount = $userCount = 0;
  try {
    $totalUsers = (int)$pdo->query("SELECT COUNT(*) FROM utilisateurs")->fetchColumn();
    try {
      $adminCount = (int)$pdo->query("SELECT COUNT(*) FROM utilisateurs WHERE role = 'admin'")->fetchColumn();
      $userCount = (int)$pdo->query("SELECT COUNT(*) FROM utilisateurs WHERE role = 'user' OR role IS NULL")->fetchColumn();
    } catch (Throwable $e) {
      // Role column doesn't exist
      $adminCount = 0;
      $userCount = $totalUsers;
    }
  } catch (Throwable $e) {}
?>
<!DOCTYPE html>
<html lang="en" class="grid">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Manage Users - Admin - QuickAnnonce</title>
  <link rel="stylesheet" href="css/styles.css" />
  <style>
    .filter-section { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 20px; margin-bottom: 24px; }
    .filter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; }
    .badge { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 600; }
    .badge-admin { background: #dbeafe; color: #1e40af; }
    .badge-user { background: #e0e7ff; color: #4338ca; }
    .table-container { overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; }
    th, td { padding: 12px; text-align: left; border-bottom: 1px solid #e5e7eb; }
    th { background: #f9fafb; font-weight: 600; color: #374151; }
    tr:hover { background: #f9fafb; }
    .sort-link { color: #3b82f6; text-decoration: none; }
    .sort-link:hover { text-decoration: underline; }
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
      <h1>Manage All Users</h1>
      <a class="btn ghost" href="admin-console.php">← Back to Dashboard</a>
    </div>
    
    <!-- Filter Section -->
    <div class="filter-section">
      <h3 style="margin-top: 0;">Search & Filter</h3>
      <form method="get" action="admin-manage-users.php">
        <div class="filter-grid">
          <div>
            <label style="display: block; margin-bottom: 4px; font-size: 0.875rem; color: #374151;">Search</label>
            <input class="input" type="text" name="search" placeholder="Name or email..." value="<?php echo h($search); ?>" />
          </div>
          <div>
            <label style="display: block; margin-bottom: 4px; font-size: 0.875rem; color: #374151;">Role</label>
            <select class="input" name="role">
              <option value="">All</option>
              <option value="admin" <?php echo $role==='admin'?'selected':''; ?>>Admin (<?php echo $adminCount; ?>)</option>
              <option value="user" <?php echo $role==='user'?'selected':''; ?>>User (<?php echo $userCount; ?>)</option>
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
          <a class="btn ghost" href="admin-manage-users.php">Clear</a>
        </div>
      </form>
    </div>
    
    <!-- Results -->
    <div class="section-title">
      <h2>Results (<?php echo count($users); ?>)</h2>
    </div>
    
    <div class="table-container">
      <table>
        <thead>
          <tr>
            <th><a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'nom', 'order' => $sortBy === 'nom' && $sortOrder === 'ASC' ? 'DESC' : 'ASC'])); ?>" class="sort-link">Name</a></th>
            <th><a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'email', 'order' => $sortBy === 'email' && $sortOrder === 'ASC' ? 'DESC' : 'ASC'])); ?>" class="sort-link">Email</a></th>
            <th>Role</th>
            <th><a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'nb_annonces', 'order' => $sortBy === 'nb_annonces' && $sortOrder === 'ASC' ? 'DESC' : 'ASC'])); ?>" class="sort-link">Total Ads</a></th>
            <th>Approved Ads</th>
            <th><a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'created_at', 'order' => $sortBy === 'created_at' && $sortOrder === 'ASC' ? 'DESC' : 'ASC'])); ?>" class="sort-link">Registered</a></th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($users)): ?>
            <tr>
              <td colspan="7" class="muted">No users found matching your criteria.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($users as $u): ?>
              <tr>
                <td><strong><?php echo h($u['nom']); ?></strong></td>
                <td><?php echo h($u['email']); ?></td>
                <td>
                  <span class="badge badge-<?php echo $u['role'] ?? 'user'; ?>">
                    <?php echo ucfirst($u['role'] ?? 'user'); ?>
                  </span>
                </td>
                <td><?php echo (int)($u['total_ads'] ?? $u['nb_annonces'] ?? 0); ?></td>
                <td><?php echo (int)($u['approved_ads'] ?? 0); ?></td>
                <td><?php echo h(date('M j, Y', strtotime($u['created_at'] ?? date('Y-m-d')))); ?></td>
                <td>
                  <div style="display: flex; gap: 8px;">
                    <a class="btn ghost" href="admin-manage-ads.php?search=<?php echo urlencode($u['email']); ?>" style="font-size: 0.875rem;">View Ads</a>
                    <form method="post" action="admin-delete-user.php" onsubmit="return confirm('Delete this user and all associated ads?');" style="display:inline;">
                      <input type="hidden" name="csrf" value="<?php echo h(csrf_token()); ?>" />
                      <input type="hidden" name="id" value="<?php echo (int)$u['id']; ?>" />
                      <input type="hidden" name="redirect" value="admin-manage-users.php<?php echo $search ? '?search=' . urlencode($search) : ''; ?>" />
                      <button class="btn danger" type="submit" style="font-size: 0.875rem;">Delete</button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
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

