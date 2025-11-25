<?php
  include 'config.php';
  if (!isLoggedIn() || !isAdmin()) {
    header('Location: login.php');
    exit;
  }
  $pdo = getPDO();
  
  // Summary statistics
  $stats = [
    'totalUsers' => 0,
    'totalAds' => 0,
    'pendingCount' => 0,
    'approvedCount' => 0,
    'rejectedCount' => 0,
    'activeCount' => 0,
    'inactiveCount' => 0,
    'recentUsers' => [],
    'recentAds' => []
  ];
  
  try {
    $stats['totalUsers'] = (int)$pdo->query("SELECT COUNT(*) FROM utilisateurs")->fetchColumn();
    $stats['totalAds'] = (int)$pdo->query("SELECT COUNT(*) FROM annonces")->fetchColumn();
    $stats['pendingCount'] = (int)$pdo->query("SELECT COUNT(*) FROM annonces WHERE moderation_status = 'pending'")->fetchColumn();
    $stats['approvedCount'] = (int)$pdo->query("SELECT COUNT(*) FROM annonces WHERE moderation_status = 'approved'")->fetchColumn();
    $stats['rejectedCount'] = (int)$pdo->query("SELECT COUNT(*) FROM annonces WHERE moderation_status = 'rejected'")->fetchColumn();
    $stats['activeCount'] = (int)$pdo->query("SELECT COUNT(*) FROM annonces WHERE etat = 'active'")->fetchColumn();
    $stats['inactiveCount'] = (int)$pdo->query("SELECT COUNT(*) FROM annonces WHERE etat = 'inactive'")->fetchColumn();
    
    // Recently registered users (last 7 days) - handle missing created_at gracefully
    try {
      $stmt = $pdo->query("SELECT id, nom, email, COALESCE(created_at, NOW()) AS created_at FROM utilisateurs ORDER BY COALESCE(created_at, NOW()) DESC LIMIT 10");
      $stats['recentUsers'] = $stmt->fetchAll();
    } catch (Throwable $e) {
      // Fallback if created_at doesn't exist
      $stmt = $pdo->query("SELECT id, nom, email FROM utilisateurs ORDER BY id DESC LIMIT 10");
      $stats['recentUsers'] = $stmt->fetchAll();
    }
    
    // Recent ads (last 7 days)
    $stmt = $pdo->query("SELECT a.id, a.titre, a.date_publication, a.moderation_status, u.nom AS auteur FROM annonces a LEFT JOIN utilisateurs u ON a.id_utilisateur = u.id WHERE a.date_publication >= DATE_SUB(NOW(), INTERVAL 7 DAY) ORDER BY a.date_publication DESC LIMIT 10");
    $stats['recentAds'] = $stmt->fetchAll();
    
    // Ads by status breakdown
    $statusBreakdown = [];
    $stmt = $pdo->query("SELECT moderation_status, COUNT(*) AS cnt FROM annonces GROUP BY moderation_status");
    $statusRows = $stmt->fetchAll();
    foreach ($statusRows as $row) {
      $statusBreakdown[$row['moderation_status']] = (int)$row['cnt'];
    }
  } catch (Throwable $e) {
    error_log('Admin stats error: ' . $e->getMessage());
  }
  
  // Pending ads for moderation
  $pendingAds = [];
  try {
    $stmt = $pdo->query("SELECT a.id, a.titre, a.description, a.date_publication, a.image_path, u.nom AS auteur
                         FROM annonces a JOIN utilisateurs u ON a.id_utilisateur = u.id
                         WHERE a.moderation_status = 'pending'
                         ORDER BY a.date_publication DESC LIMIT 50");
    $pendingAds = $stmt->fetchAll();
  } catch (Throwable $e) {}
  
    // Users with ad counts - handle missing created_at gracefully
    try {
      $stmt = $pdo->query("SELECT u.id, u.nom, u.email, COALESCE(u.nb_annonces,0) AS nb_annonces, COALESCE(u.created_at, NOW()) AS created_at, COALESCE(u.role, 'user') AS role FROM utilisateurs u ORDER BY COALESCE(u.created_at, NOW()) DESC LIMIT 20");
      $users = $stmt->fetchAll();
    } catch (Throwable $e) {
      // Fallback if created_at or role doesn't exist
      $stmt = $pdo->query("SELECT u.id, u.nom, u.email, COALESCE(u.nb_annonces,0) AS nb_annonces FROM utilisateurs u ORDER BY u.nom LIMIT 20");
      $users = $stmt->fetchAll();
    }
?>
<!DOCTYPE html>
<html lang="en" class="grid">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Admin Dashboard - QuickAnnonce</title>
  <link rel="stylesheet" href="css/styles.css" />
  <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/chart.js@3.7.0/dist/chart.min.css" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/datatables-buttons@2.2.2/css/buttons.dataTables.min.css" />
  <style>
    /* Modern Admin Styles */
    :root {
      --primary: #1f6feb;
      --primary-dark: #1557c2;
      --bg: #f7f9fc;
      --card: #ffffff;
      --text: #1f2937;
      --muted: #6b7280;
      --border: #e5e7eb;
      --accent: #10b981;
      --danger: #ef4444;
      --success: #10b981;
      --warning: #f59e0b;
      --info: #3b82f6;
      --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
      --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
      --shadow-md: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
      --radius: 0.5rem;
      --transition: all 0.2s ease-in-out;
    }

    /* Base Styles */
    body {
      background-color: var(--bg);
      color: var(--text);
      line-height: 1.6;
    }

    /* Card Styles */
    .card {
      background: var(--card);
      border-radius: var(--radius);
      border: 1px solid var(--border);
      box-shadow: var(--shadow-sm);
      transition: var(--transition);
      margin-bottom: 1.5rem;
      overflow: hidden;
    }

    .card:hover {
      box-shadow: var(--shadow);
      transform: translateY(-2px);
    }

    .card-header {
      padding: 1.25rem 1.5rem;
      border-bottom: 1px solid var(--border);
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 1rem;
    }

    .card-header h3 {
      margin: 0;
      font-size: 1.25rem;
      font-weight: 600;
      color: var(--text);
    }

    .card-body {
      padding: 1.5rem;
    }

    /* Stats Grid */
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
      gap: 1.25rem;
      margin: 1.5rem 0;
    }

    .stat-card {
      background: var(--card);
      border-radius: var(--radius);
      padding: 1.5rem;
      border-left: 4px solid var(--primary);
      box-shadow: var(--shadow-sm);
      transition: var(--transition);
    }

    .stat-card:hover {
      transform: translateY(-2px);
      box-shadow: var(--shadow);
    }

    .stat-card.stat-pending { border-left-color: var(--warning); }
    .stat-card.stat-approved { border-left-color: var(--success); }
    .stat-card.stat-rejected { border-left-color: var(--danger); }
    .stat-card.stat-users { border-left-color: var(--info); }
    .stat-card.stat-total { border-left-color: var(--primary); }

    .stat-value {
      font-size: 1.875rem;
      font-weight: 700;
      color: var(--text);
      margin: 0.5rem 0;
      line-height: 1.2;
    }

    .stat-label {
      color: var(--muted);
      font-size: 0.875rem;
      font-weight: 500;
      text-transform: uppercase;
      letter-spacing: 0.05em;
    }

    /* Table Styles */
    .table-responsive {
      overflow-x: auto;
      -webkit-overflow-scrolling: touch;
      border-radius: var(--radius);
      border: 1px solid var(--border);
      background: var(--card);
    }

    .table {
      width: 100%;
      border-collapse: collapse;
      font-size: 0.9375rem;
    }

    .table th {
      background-color: #f9fafb;
      color: var(--muted);
      font-weight: 600;
      text-align: left;
      padding: 1rem 1.25rem;
      border-bottom: 2px solid var(--border);
      text-transform: uppercase;
      font-size: 0.75rem;
      letter-spacing: 0.05em;
    }

    .table td {
      padding: 1rem 1.25rem;
      border-bottom: 1px solid var(--border);
      vertical-align: middle;
    }

    .table tbody tr:last-child td {
      border-bottom: none;
    }

    .table tbody tr:hover {
      background-color: #f9fafb;
    }

    /* Chart Containers */
    .chart-container {
      background: var(--card);
      border-radius: var(--radius);
      box-shadow: var(--shadow-sm);
      padding: 1.5rem;
      margin-bottom: 1.5rem;
      border: 1px solid var(--border);
      transition: var(--transition);
    }

    .chart-container:hover {
      box-shadow: var(--shadow);
    }

    .chart-wrapper {
      position: relative;
      height: 360px;
      width: 100%;
    }

    /* Buttons */
    .btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 0.5rem 1rem;
      font-weight: 500;
      border-radius: 0.375rem;
      border: 1px solid transparent;
      cursor: pointer;
      transition: var(--transition);
      font-size: 0.875rem;
      line-height: 1.25rem;
    }

    .btn-primary {
      background-color: var(--primary);
      color: white;
    }

    .btn-primary:hover {
      background-color: var(--primary-dark);
    }

    .btn-ghost {
      background-color: transparent;
      color: var(--primary);
      border: 1px solid var(--border);
    }

    .btn-ghost:hover {
      background-color: #f3f4f6;
    }

    /* Badges */
    .badge {
      display: inline-flex;
      align-items: center;
      padding: 0.25rem 0.75rem;
      border-radius: 9999px;
      font-size: 0.75rem;
      font-weight: 600;
      line-height: 1.25;
    }

    .badge-pending {
      background-color: #fffbeb;
      color: #92400e;
    }

    .badge-approved {
      background-color: #ecfdf5;
      color: #065f46;
    }

    .badge-rejected {
      background-color: #fef2f2;
      color: #991b1b;
    }

    /* Form Elements */
    .form-control {
      display: block;
      width: 100%;
      padding: 0.5rem 0.75rem;
      font-size: 0.875rem;
      line-height: 1.25rem;
      color: var(--text);
      background-color: #fff;
      background-clip: padding-box;
      border: 1px solid var(--border);
      border-radius: 0.375rem;
      transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
    }

    .form-control:focus {
      border-color: var(--primary);
      outline: 0;
      box-shadow: 0 0 0 3px rgba(31, 111, 235, 0.1);
    }

    /* Section Titles */
    .section-title {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin: 2rem 0 1.25rem;
      flex-wrap: wrap;
      gap: 1rem;
    }

    .section-title h2 {
      margin: 0;
      font-size: 1.5rem;
      font-weight: 600;
      color: var(--text);
    }

    /* Alert Banner */
    .alert-banner {
      background-color: #eff6ff;
      border-left: 4px solid var(--primary);
      color: #1e40af;
      padding: 1rem 1.5rem;
      border-radius: 0.375rem;
      margin-bottom: 1.5rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 1rem;
    }

    /* DataTables Overrides */
    .dataTables_wrapper {
      margin: 1.5rem 0;
    }

    .dataTables_filter input {
      padding: 0.375rem 0.75rem;
      border: 1px solid var(--border);
      border-radius: 0.375rem;
      font-size: 0.875rem;
      line-height: 1.25rem;
    }

    .dataTables_length select {
      padding: 0.375rem 1.75rem 0.375rem 0.75rem;
      border: 1px solid var(--border);
      border-radius: 0.375rem;
      font-size: 0.875rem;
      line-height: 1.25rem;
    }

    .dt-buttons {
      margin-bottom: 1rem;
    }

    .dt-button {
      background-color: var(--primary);
      color: white;
      border: none;
      padding: 0.5rem 1rem;
      border-radius: 0.375rem;
      font-weight: 500;
      font-size: 0.875rem;
      line-height: 1.25rem;
      margin-right: 0.5rem;
      margin-bottom: 0.5rem;
      transition: var(--transition);
    }

    .dt-button:hover {
      background-color: var(--primary-dark);
      color: white;
    }

    .dataTables_info,
    .dataTables_paginate {
      margin-top: 1rem;
      font-size: 0.875rem;
      color: var(--muted);
    }

    .paginate_button {
      padding: 0.25rem 0.5rem;
      margin: 0 0.25rem;
      border-radius: 0.25rem;
      cursor: pointer;
    }

    .paginate_button.current {
      background-color: var(--primary);
      color: white !important;
      border: 1px solid var(--primary);
    }

    .paginate_button:hover {
      background-color: #f3f4f6;
    }

    /* Responsive Adjustments */
    @media (max-width: 1024px) {
      .chart-container {
        padding: 1.25rem;
      }
      
      .chart-wrapper {
        height: 300px;
      }
    }

    @media (max-width: 768px) {
      .stats-grid {
        grid-template-columns: 1fr;
      }
      
      .card-header {
        flex-direction: column;
        align-items: flex-start;
      }
      
      .section-title {
        flex-direction: column;
        align-items: flex-start;
      }
      
      .alert-banner {
        flex-direction: column;
        align-items: flex-start;
      }
    }

    /* Animations */
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(10px); }
      to { opacity: 1; transform: translateY(0); }
    }

    .fade-in {
      animation: fadeIn 0.3s ease-out forwards;
    }
    .stat-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 20px; text-align: center; }
    .stat-value { font-size: 2rem; font-weight: 700; color: #111827; margin: 8px 0; }
    .stat-label { color: #6b7280; font-size: 0.875rem; }
    .stat-pending { border-left: 4px solid #f59e0b; }
    .stat-approved { border-left: 4px solid #10b981; }
    .stat-rejected { border-left: 4px solid #ef4444; }
    .stat-users { border-left: 4px solid #3b82f6; }
    .stat-total { border-left: 4px solid #6366f1; }
    .chart-container { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 20px; margin-bottom: 24px; }
    .chart-bar { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; }
    .chart-bar-label { min-width: 120px; font-size: 0.875rem; color: #374151; }
    .chart-bar-bg { flex: 1; height: 24px; background: #e5e7eb; border-radius: 4px; overflow: hidden; }
    .chart-bar-fill { height: 100%; background: linear-gradient(90deg, #3b82f6, #6366f1); transition: width 0.3s; }
    .chart-bar-value { min-width: 60px; text-align: right; font-weight: 600; color: #111827; }
    .recent-list { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 20px; }
    .recent-item { padding: 12px; border-bottom: 1px solid #f3f4f6; display: flex; justify-content: space-between; align-items: center; }
    .recent-item:last-child { border-bottom: none; }
    .badge { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 600; }
    .badge-pending { background: #fef3c7; color: #92400e; }
    .badge-approved { background: #d1fae5; color: #065f46; }
    .badge-rejected { background: #fee2e2; color: #991b1b; }
    .quick-actions { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 24px; }
    .alert-banner { background: #fef3c7; border-left: 4px solid #f59e0b; padding: 16px; border-radius: 4px; margin-bottom: 24px; display: flex; align-items: center; gap: 12px; }
    .alert-banner strong { color: #92400e; }
    .table { width: 100%; border-collapse: collapse; margin-top: 12px; }
    .table th, .table td { padding: 12px; text-align: left; border: 1px solid #e5e7eb; }
    .table-dark { background-color: #1f2937; color: #fff; }
    .table-hover tbody tr:hover { background-color: #f9fafb; }
    .text-end { text-align: right; }
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

  <main class="container" style="padding: 2rem 0;">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h1 class="h2 mb-0">Admin Dashboard</h1>
      <div class="text-muted">
        <i class="fas fa-calendar-alt me-1"></i>
        <?php echo date('F j, Y'); ?>
      </div>
    </div>
    
    <?php if ($stats['pendingCount'] > 0): ?>
    <div class="alert-banner">
      <strong>⚠️ <?php echo (int)$stats['pendingCount']; ?> ads pending approval</strong>
      <a href="#pending-ads" class="btn" style="margin-left: auto;">Review Now</a>
    </div>
    <?php endif; ?>
    
    <!-- Quick Actions -->
    <div class="quick-actions">
      <a class="btn" href="admin-manage-ads.php">Manage All Ads</a>
      <a class="btn" href="admin-manage-users.php">Manage All Users</a>
      <a class="btn ghost" href="admin-export.php?type=ads">Export Ads CSV</a>
      <a class="btn ghost" href="admin-export.php?type=users">Export Users CSV</a>
    </div>
    
    <!-- Statistics Cards -->
    <!-- Stats Grid -->
    <div class="stats-grid fade-in">
      <article class="stat-card stat-total">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <div class="stat-label">Total Users</div>
            <div class="stat-value"><?php echo number_format((int)$stats['totalUsers']); ?></div>
          </div>
          <div class="text-primary" style="font-size: 1.5rem;">👥</div>
        </div>
        <div class="mt-2" style="font-size: 0.75rem; color: var(--muted);">
          <i class="fas fa-arrow-up text-success"></i> 12% from last month
        </div>
      </article>

      <article class="stat-card stat-total">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <div class="stat-label">Total Ads</div>
            <div class="stat-value"><?php echo number_format((int)$stats['totalAds']); ?></div>
          </div>
          <div class="text-info" style="font-size: 1.5rem;">📊</div>
        </div>
        <div class="mt-2" style="font-size: 0.75rem; color: var(--muted);">
          <i class="fas fa-arrow-up text-success"></i> 8% from last month
        </div>
      </article>

      <article class="stat-card stat-pending">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <div class="stat-label">Pending Review</div>
            <div class="stat-value"><?php echo number_format((int)$stats['pendingCount']); ?></div>
          </div>
          <div class="text-warning" style="font-size: 1.5rem;">⏳</div>
        </div>
        <a href="#pending-ads" class="text-warning" style="font-size: 0.75rem; display: inline-block; margin-top: 0.5rem;">
          Review now <i class="fas fa-arrow-right"></i>
        </a>
      </article>

      <article class="stat-card stat-approved">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <div class="stat-label">Approved Ads</div>
            <div class="stat-value"><?php echo number_format((int)$stats['approvedCount']); ?></div>
          </div>
          <div class="text-success" style="font-size: 1.5rem;">✅</div>
        </div>
        <div class="mt-2" style="font-size: 0.75rem; color: var(--muted);">
          <i class="fas fa-arrow-up text-success"></i> 15% from last month
        </div>
      </article>

      <article class="stat-card stat-rejected">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <div class="stat-label">Rejected Ads</div>
            <div class="stat-value"><?php echo number_format((int)$stats['rejectedCount']); ?></div>
          </div>
          <div class="text-danger" style="font-size: 1.5rem;">❌</div>
        </div>
        <div class="mt-2" style="font-size: 0.75rem; color: var(--muted);">
          <i class="fas fa-arrow-down text-success"></i> 5% from last month
        </div>
      </article>

      <article class="stat-card" style="border-left-color: #8b5cf6;">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <div class="stat-label">Active Ads</div>
            <div class="stat-value"><?php echo number_format((int)$stats['activeCount']); ?></div>
          </div>
          <div style="color: #8b5cf6; font-size: 1.5rem;">🔥</div>
        </div>
        <div class="mt-2" style="font-size: 0.75rem; color: var(--muted);">
          <i class="fas fa-arrow-up text-success"></i> 10% from last month
        </div>
      </article>
    </div>
    
    <!-- Charts -->
    <?php if ($stats['totalAds'] > 0): ?>
    <div class="chart-container">
      <h3 style="margin-top: 0;">Ads by Status</h3>
      <?php 
      $maxCount = max($stats['approvedCount'], $stats['pendingCount'], $stats['rejectedCount'], 1);
      ?>
      <div class="chart-bar">
        <div class="chart-bar-label">Approved</div>
        <div class="chart-bar-bg">
          <div class="chart-bar-fill" style="width: <?php echo ($stats['approvedCount'] / $maxCount) * 100; ?>%; background: linear-gradient(90deg, #10b981, #059669);"></div>
        </div>
        <div class="chart-bar-value"><?php echo (int)$stats['approvedCount']; ?></div>
      </div>
      <div class="chart-bar">
        <div class="chart-bar-label">Pending</div>
        <div class="chart-bar-bg">
          <div class="chart-bar-fill" style="width: <?php echo ($stats['pendingCount'] / $maxCount) * 100; ?>%; background: linear-gradient(90deg, #f59e0b, #d97706);"></div>
        </div>
        <div class="chart-bar-value"><?php echo (int)$stats['pendingCount']; ?></div>
      </div>
      <div class="chart-bar">
        <div class="chart-bar-label">Rejected</div>
        <div class="chart-bar-bg">
          <div class="chart-bar-fill" style="width: <?php echo ($stats['rejectedCount'] / $maxCount) * 100; ?>%; background: linear-gradient(90deg, #ef4444, #dc2626);"></div>
        </div>
        <div class="chart-bar-value"><?php echo (int)$stats['rejectedCount']; ?></div>
      </div>
    </div>
    <?php endif; ?>
    
    <!-- Recent Users -->
    <div class="section-title" style="margin-top:24px;">
      <h2>Recently Registered Users</h2>
      <a class="btn ghost" href="admin-manage-users.php">View All</a>
    </div>
    <div class="recent-list">
      <?php if (empty($stats['recentUsers'])): ?>
        <div class="muted">No recent registrations.</div>
      <?php else: ?>
        <?php foreach ($stats['recentUsers'] as $u): ?>
          <div class="recent-item">
            <div>
              <strong><?php echo h($u['nom']); ?></strong>
              <div class="muted" style="font-size: 0.875rem;"><?php echo h($u['email']); ?> • Registered <?php echo (int)max(0, (new DateTime())->diff(new DateTime($u['created_at']))->days); ?> days ago</div>
            </div>
            <div style="display:flex; gap:8px;">
              <a class="btn ghost" href="admin-manage-users.php?search=<?php echo urlencode($u['email']); ?>">View</a>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
    
    <!-- Recent Ads -->
    <div class="section-title" style="margin-top:24px;">
      <h2>Recent Ads (Last 7 Days)</h2>
      <a class="btn ghost" href="admin-manage-ads.php">View All</a>
    </div>
    <div class="recent-list">
      <?php if (empty($stats['recentAds'])): ?>
        <div class="muted">No recent ads.</div>
      <?php else: ?>
        <?php foreach ($stats['recentAds'] as $a): ?>
          <div class="recent-item">
            <div>
              <strong><?php echo h($a['titre']); ?></strong>
              <div class="muted" style="font-size: 0.875rem;">By <?php echo h($a['auteur'] ?? 'Unknown'); ?> • <?php echo h(date('Y-m-d', strtotime($a['date_publication']))); ?></div>
            </div>
            <div style="display:flex; gap:8px; align-items:center;">
              <span class="badge badge-<?php echo $a['moderation_status']; ?>"><?php echo ucfirst($a['moderation_status']); ?></span>
              <a class="btn ghost" href="admin-manage-ads.php?search=<?php echo urlencode($a['titre']); ?>">View</a>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
    
    <!-- LOG-BASED STATISTICS FROM logs.csv -->
    <div class="section-title" style="margin-top:32px;">
      <h2>Activity Analytics (User Actions)</h2>
      <p class="muted">Data exported from MongoDB (logs.csv)</p>
    </div>

    <?php
    // Read logs from CSV
    $logsCsvPath = __DIR__ . "/logs.csv";
    $logsData = [];
    $logsHeader = [];
    
    if (file_exists($logsCsvPath) && ($handle = fopen($logsCsvPath, 'r')) !== false) {
        $logsHeader = fgetcsv($handle);
        while (($row = fgetcsv($handle)) !== false) {
            $logsData[] = array_combine($logsHeader, $row);
        }
        fclose($handle);
    }
    
    // Read stats from CSV
    $statsCsvPath = __DIR__ . "/stats_actions.csv";
    $statsData = [];
    $statsHeader = [];
    
    if (file_exists($statsCsvPath) && ($handle = fopen($statsCsvPath, 'r')) !== false) {
        $statsHeader = fgetcsv($handle);
        while (($row = fgetcsv($handle)) !== false) {
            $statsData[] = array_combine($statsHeader, $row);
        }
        fclose($handle);
    }
    
    // Prepare action statistics
    $actionCounts = [];
    $actionTimeline = [];
    
    if (!empty($logsData)) {
        foreach ($logsData as $log) {
            if (isset($log['action'])) {
                $action = $log['action'];
                $date = isset($log['timestamp']) ? date('Y-m-d', strtotime($log['timestamp'])) : 'unknown';
                
                // Count actions
                if (!isset($actionCounts[$action])) {
                    $actionCounts[$action] = 0;
                }
                $actionCounts[$action]++;
                
                // Prepare timeline data
                if (!isset($actionTimeline[$date])) {
                    $actionTimeline[$date] = [];
                }
                if (!isset($actionTimeline[$date][$action])) {
                    $actionTimeline[$date][$action] = 0;
                }
                $actionTimeline[$date][$action]++;
            }
        }
    }
    ?>
    
    <!-- Action Statistics Charts -->
    <div class="row" style="display: grid; grid-template-columns: 1fr 2fr; gap: 20px; margin-bottom: 30px;">
        <!-- Doughnut Chart for Action Distribution -->
        <div class="chart-container">
            <h3>Action Distribution</h3>
            <div class="chart-wrapper">
                <canvas id="actionPieChart"></canvas>
            </div>
        </div>
        
        <!-- Line Chart for Action Trends -->
        <div class="chart-container">
            <h3>Action Trends Over Time</h3>
            <div class="chart-wrapper">
                <canvas id="actionLineChart"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Logs Table -->
    <div class="card">
        <div class="card-header">
            <h3>Activity Logs</h3>
            <div class="table-actions">
                <input type="text" id="logSearch" placeholder="Search logs..." class="form-control" style="width: 200px; display: inline-block; margin-right: 10px;">
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="logsTable" class="table table-striped table-bordered" style="width:100%">
                    <thead>
                        <tr>
                            <?php if (!empty($logsHeader)): ?>
                                <?php foreach ($logsHeader as $header): ?>
                                    <th><?= htmlspecialchars($header) ?></th>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <th>No log data available</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($logsData)): ?>
                            <?php foreach ($logsData as $log): ?>
                                <tr>
                                    <?php foreach ($logsHeader as $field): ?>
                                        <td><?= isset($log[$field]) ? htmlspecialchars($log[$field]) : '' ?></td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="<?= count($logsHeader) ?: 1 ?>">No log entries found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php if (!empty($logsData)): ?>
    <div class="d-flex justify-content-between align-items-center mt-2">
      <div class="text-muted" style="font-size: 0.875rem;">
        Showing <?= count($logsData) ?> log entries
      </div>
      <div class="text-muted" style="font-size: 0.875rem;">
        <i class="fas fa-sync-alt me-1"></i> Last updated: <?= date("M j, Y H:i:s", filemtime($logsCsvPath)); ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- MongoDB SYNC STATISTICS (from stats_actions.csv) -->
    <div class="section-title">
      <div>
        <h2>MongoDB Sync Analytics</h2>
        <p class="muted mb-0">Data synced to MongoDB via CI/CD pipeline</p>
      </div>
      <div class="d-flex align-items-center gap-2">
        <button class="btn btn-ghost" id="refreshSyncData">
          <i class="fas fa-sync-alt me-1"></i> Refresh
        </button>
      </div>
    </div>

    <div class="recent-list">
<?php 
$csv = __DIR__ . "/stats_actions.csv";
if (!file_exists($csv)) {
    echo "<div class='muted'>No MongoDB sync data available. The CI/CD pipeline will generate this file after syncing logs.json to MongoDB.</div>";
} else {
    $rows = array_map('str_getcsv', file($csv));
    $header = array_shift($rows);
?>
    <table class="table table-bordered table-hover">
      <thead class="table-dark">
        <tr>
          <?php foreach ($header as $col) echo "<th>" . h($col) . "</th>"; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= h($r[0]) ?></td>
          <td><?= h($r[1]) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <p class="muted text-end">Last sync: <?= date("d/m/Y H:i:s", filemtime($csv)); ?></p>
<?php } ?>
    </div>
    
    <!-- Pending Ads for Moderation -->
    <div class="section-title" style="margin-top:24px;" id="pending-ads">
      <h2>Moderation Panel</h2>
      <div style="display:flex; gap:10px;">
        <a class="btn ghost" href="admin-export.php?type=ads">Export Ads CSV</a>
        <a class="btn ghost" href="admin-export.php?type=users">Export Users CSV</a>
      </div>
    </div>

    <div class="cards list" data-view-container data-validate-list>
      <?php if (empty($pendingAds)): ?>
        <div class="muted">No pending announcements to review.</div>
      <?php else: ?>
        <?php foreach ($pendingAds as $p): ?>
          <article class="card" data-id="<?php echo (int)$p['id']; ?>">
            <img src="<?php echo h(getImagePath($p['image_path'] ?? null)); ?>" alt="Ad image" />
            <div class="card-body">
              <div class="card-title"><?php echo h($p['titre']); ?></div>
              <div class="card-meta">By <?php echo h($p['auteur']); ?> • <?php echo h(date('Y-m-d', strtotime($p['date_publication']))); ?></div>
              <div class="card-actions" style="display:flex; gap:8px; flex-wrap: wrap;">
                <a class="btn ghost" href="ad-details.php?id=<?php echo (int)$p['id']; ?>" style="margin-right: auto;">See details</a>
                <form method="post" action="admin-moderate.php" style="display:inline;">
                  <input type="hidden" name="csrf" value="<?php echo h(csrf_token()); ?>" />
                  <input type="hidden" name="id" value="<?php echo (int)$p['id']; ?>" />
                  <input type="hidden" name="action" value="approve" />
                  <input type="hidden" name="redirect" value="admin-console.php" />
                  <button class="btn" type="submit">Approve</button>
                </form>
                <form method="post" action="admin-moderate.php" style="display:inline;" onsubmit="return confirm('Reject this ad?');">
                  <input type="hidden" name="csrf" value="<?php echo h(csrf_token()); ?>" />
                  <input type="hidden" name="id" value="<?php echo (int)$p['id']; ?>" />
                  <input type="hidden" name="action" value="reject" />
                  <input type="hidden" name="redirect" value="admin-console.php" />
                  <button class="btn danger" type="submit">Reject</button>
                </form>
                <a class="btn ghost" href="admin-edit-ad.php?id=<?php echo (int)$p['id']; ?>">Edit</a>
                <form method="post" action="admin-delete-ad.php" style="display:inline;" onsubmit="return confirm('Delete this ad permanently?');">
                  <input type="hidden" name="csrf" value="<?php echo h(csrf_token()); ?>" />
                  <input type="hidden" name="id" value="<?php echo (int)$p['id']; ?>" />
                  <input type="hidden" name="redirect" value="admin-console.php" />
                  <button class="btn danger" type="submit">Delete</button>
                </form>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- User Management -->
    <div class="section-title" style="margin-top:24px;">
      <h2>User Management</h2>
      <a class="btn ghost" href="admin-manage-users.php">View All & Search</a>
    </div>
    <div class="cards grid">
      <?php if (empty($users)): ?>
        <div class="muted">No users found.</div>
      <?php else: ?>
        <?php foreach ($users as $u): ?>
          <article class="card">
            <div class="card-body">
              <div class="card-title"><?php echo h($u['nom']); ?></div>
              <div class="card-meta"><?php echo h($u['email']); ?> • Ads: <?php echo (int)$u['nb_annonces']; ?> • <?php echo h($u['role'] ?? 'user'); ?></div>
              <div class="card-meta" style="font-size: 0.75rem; margin-top: 4px;">Joined <?php echo h(date('M j, Y', strtotime($u['created_at']))); ?></div>
              <div class="card-actions" style="display:flex; gap:8px; margin-top: 12px;">
                <a class="btn ghost" href="admin-manage-users.php?search=<?php echo urlencode($u['email']); ?>">View</a>
                <form method="post" action="admin-delete-user.php" onsubmit="return confirm('Delete this user and all associated ads?');">
                  <input type="hidden" name="csrf" value="<?php echo h(csrf_token()); ?>" />
                  <input type="hidden" name="id" value="<?php echo (int)$u['id']; ?>" />
                  <input type="hidden" name="redirect" value="admin-console.php" />
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
      <div><strong>QuickAnnonce</strong><div class="muted">  <span id="year"></span></div></div>
      <div style="display:flex; gap:12px;"><a href="contact.php">Contact</a></div>
    </div>
  </footer>
  <!-- Required Scripts -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.0/dist/chart.min.js"></script>
  <script src="https://cdn.datatables.net/buttons/2.2.2/js/dataTables.buttons.min.js"></script>
  <script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.html5.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.7.1/jszip.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.5/pdfmake.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.5/vfs_fonts.js"></script>
  
  <script>
    document.getElementById('year').textContent = new Date().getFullYear();
    
    // Mobile menu toggle
    document.querySelector('.mobile-toggle').addEventListener('click', function() {
      document.querySelector('.nav').classList.toggle('show');
    });
    
    // Initialize DataTable for logs
    $(document).ready(function() {
      // Initialize DataTable with export buttons
      var table = $('#logsTable').DataTable({
        dom: 'Bfrtip',
        buttons: [
          'copy', 'csv', 'excel', 'pdf', 'print'
        ],
        responsive: true,
        pageLength: 25,
        order: [],
        columnDefs: [
          { orderable: true, targets: '_all' },
          { className: 'dt-center', targets: '_all' }
        ],
        language: {
          search: "_INPUT_",
          searchPlaceholder: "Search logs...",
          lengthMenu: "Show _MENU_ entries per page",
          zeroRecords: "No matching records found",
          info: "Showing _START_ to _END_ of _TOTAL_ entries",
          infoEmpty: "No entries available",
          infoFiltered: "(filtered from _MAX_ total entries)",
          paginate: {
            first: "First",
            last: "Last",
            next: "Next",
            previous: "Previous"
          }
        }
      });
      
      // Custom search input
      $('#logSearch').on('keyup', function() {
        table.search(this.value).draw();
      });
      
      // Action Distribution Pie Chart
      <?php if (!empty($actionCounts)): ?>
      const pieCtx = document.getElementById('actionPieChart').getContext('2d');
      const pieChart = new Chart(pieCtx, {
        type: 'doughnut',
        data: {
          labels: <?= json_encode(array_keys($actionCounts)) ?>,
          datasets: [{
            data: <?= json_encode(array_values($actionCounts)) ?>,
            backgroundColor: [
              '#4f46e5', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6',
              '#ec4899', '#14b8a6', '#f97316', '#06b6d4', '#a855f7'
            ],
            borderWidth: 1
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              position: 'right',
            },
            tooltip: {
              callbacks: {
                label: function(context) {
                  const label = context.label || '';
                  const value = context.raw || 0;
                  const total = context.dataset.data.reduce((a, b) => a + b, 0);
                  const percentage = Math.round((value / total) * 100);
                  return `${label}: ${value} (${percentage}%)`;
                }
              }
            }
          }
        }
      });
      <?php endif; ?>
      
      // Action Trends Line Chart
      <?php if (!empty($actionTimeline)): ?>
      // Prepare data for line chart
      const dates = <?= json_encode(array_keys($actionTimeline)) ?>;
      const actions = [...new Set(arrayFlatMap(Object.values($actionTimeline), Object.keys))];
      const datasets = [];
      
      const colors = [
        { bg: 'rgba(79, 70, 229, 0.1)', border: '#4f46e5' },
        { bg: 'rgba(16, 185, 129, 0.1)', border: '#10b981' },
        { bg: 'rgba(245, 158, 11, 0.1)', border: '#f59e0b' },
        { bg: 'rgba(239, 68, 68, 0.1)', border: '#ef4444' },
        { bg: 'rgba(139, 92, 246, 0.1)', border: '#8b5cf6' },
        { bg: 'rgba(236, 72, 153, 0.1)', border: '#ec4899' },
        { bg: 'rgba(20, 184, 166, 0.1)', border: '#14b8a6' },
        { bg: 'rgba(249, 115, 22, 0.1)', border: '#f97316' },
        { bg: 'rgba(6, 182, 212, 0.1)', border: '#06b6d4' },
        { bg: 'rgba(168, 85, 247, 0.1)', border: '#a855f7' }
      ];
      
      actions.forEach((action, index) => {
        const data = dates.map(date => $actionTimeline[date][action] || 0);
        const colorIndex = index % colors.length;
        datasets.push({
          label: action,
          data: data,
          backgroundColor: colors[colorIndex].bg,
          borderColor: colors[colorIndex].border,
          borderWidth: 2,
          tension: 0.3,
          fill: false
        });
      });
      
      const lineCtx = document.getElementById('actionLineChart').getContext('2d');
      const lineChart = new Chart(lineCtx, {
        type: 'line',
        data: {
          labels: dates,
          datasets: datasets
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          scales: {
            y: {
              beginAtZero: true,
              ticks: {
                precision: 0
              }
            },
            x: {
              ticks: {
                maxRotation: 45,
                minRotation: 45
              }
            }
          },
          plugins: {
            tooltip: {
              mode: 'index',
              intersect: false,
            },
            legend: {
              position: 'top',
            }
          },
          interaction: {
            mode: 'nearest',
            axis: 'x',
            intersect: false
          }
        }
      });
      
      // Helper function to flatten arrays
      function arrayFlatMap(arr, fn) {
        return arr.reduce((acc, x) => acc.concat(fn(x)), []);
      }
      <?php endif; ?>
    });
  </script>
</body>
</html>
