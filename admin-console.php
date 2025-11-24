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
  <style>
    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px; }
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

  <main class="container">
    <h1>Admin Dashboard</h1>
    
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
    <div class="stats-grid">
      <article class="stat-card stat-total">
        <div class="stat-label">Total Users</div>
        <div class="stat-value"><?php echo (int)$stats['totalUsers']; ?></div>
      </article>
      <article class="stat-card stat-total">
        <div class="stat-label">Total Ads</div>
        <div class="stat-value"><?php echo (int)$stats['totalAds']; ?></div>
      </article>
      <article class="stat-card stat-pending">
        <div class="stat-label">Pending Ads</div>
        <div class="stat-value"><?php echo (int)$stats['pendingCount']; ?></div>
      </article>
      <article class="stat-card stat-approved">
        <div class="stat-label">Approved Ads</div>
        <div class="stat-value"><?php echo (int)$stats['approvedCount']; ?></div>
      </article>
      <article class="stat-card stat-rejected">
        <div class="stat-label">Rejected Ads</div>
        <div class="stat-value"><?php echo (int)$stats['rejectedCount']; ?></div>
      </article>
      <article class="stat-card stat-approved">
        <div class="stat-label">Active Ads</div>
        <div class="stat-value"><?php echo (int)$stats['activeCount']; ?></div>
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

    <div class="recent-list">
<?php
$csvPath = __DIR__ . "/logs.csv";

if (!file_exists($csvPath)) {
    echo "<div class='muted'>No log data available.</div>";
} else {
    $logsHeader = [];
    $logsRows = [];
    if (($handle = fopen($csvPath, 'r')) !== false) {
        if (($logsHeader = fgetcsv($handle)) !== false) {
            while (($row = fgetcsv($handle)) !== false) {
                $logsRows[] = $row;
            }
        }
        fclose($handle);
    }

    if (empty($logsHeader)) {
        echo "<div class='muted'>No log data available.</div>";
    } else {
?>
    <table class="table table-bordered table-hover">
      <thead class="table-dark">
        <tr>
          <?php foreach ($logsHeader as $col): ?>
            <th><?= h($col); ?></th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($logsRows)): ?>
        <tr>
          <td colspan="<?= count($logsHeader); ?>">No log entries recorded yet.</td>
        </tr>
        <?php else: ?>
          <?php foreach ($logsRows as $row): ?>
          <tr>
            <?php foreach ($logsHeader as $index => $col): ?>
            <td><?= h($row[$index] ?? ''); ?></td>
            <?php endforeach; ?>
          </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
    <p class="muted text-end">Last updated: <?= date("d/m/Y H:i:s", filemtime($csvPath)); ?></p>
<?php
    }
}
?>
    </div>

    <!-- MongoDB SYNC STATISTICS (from stats_actions.csv) -->
    <div class="section-title" style="margin-top:32px;">
      <h2>MongoDB Sync Analytics</h2>
      <p class="muted">Data synced to MongoDB via CI/CD pipeline</p>
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
