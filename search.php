<?php
  include 'config.php';
  $pdo = getPDO();
  $q = trim($_GET['q'] ?? '');
  $etat = trim($_GET['etat'] ?? '');
  $date = trim($_GET['date'] ?? '');

  $where = [];
  $params = [];
  if ($q !== '') {
    $where[] = '(a.titre LIKE :q OR a.description LIKE :q)';
    $params[':q'] = "%{$q}%";
  }
  if ($etat !== '') {
    $where[] = 'a.etat = :etat';
    $params[':etat'] = $etat;
  }
  if ($date !== '') {
    $where[] = 'DATE(a.date_publication) = :d';
    $params[':d'] = $date;
  }
  $sql = 'SELECT a.id, a.titre, a.description, a.date_publication, a.etat, a.image_path, a.prix, a.ville, a.categorie, u.nom AS auteur
          FROM annonces a JOIN utilisateurs u ON a.id_utilisateur = u.id
          WHERE a.moderation_status = \'approved\'';
  if (!empty($where)) {
    $sql .= ' AND ' . implode(' AND ', $where);
  }
  $sql .= ' ORDER BY a.date_publication DESC LIMIT 50';
  $results = [];
  try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll();
  } catch (Throwable $e) {
    $results = [];
  }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Advanced Search - QuickAnnonce</title>
  <link rel="stylesheet" href="css/styles.css" />
  <style>
    @media (max-width: 1024px) {
      .search form {
        grid-template-columns: 1fr 1fr !important;
      }
      .search form > div:last-child {
        grid-column: 1 / -1;
        justify-content: flex-start;
      }
    }
    @media (max-width: 768px) {
      .search form {
        grid-template-columns: 1fr !important;
      }
      .search form > div:last-child {
        grid-column: 1;
      }
      .cards.grid {
        grid-template-columns: repeat(2, 1fr) !important;
      }
    }
    @media (max-width: 480px) {
      .cards.grid {
        grid-template-columns: 1fr !important;
      }
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
        <a href="login.php">Login</a>
        <a href="register.php">Register</a>
        <a href="contact.php">Contact</a>
      </nav>
      <div style="display:flex; gap:10px; align-items:center;">
        <a class="cta" href="post.php">Post Your Ad</a>
        <button class="mobile-toggle" aria-label="Toggle navigation">☰</button>
      </div>
    </div>
  </header>

  <main class="container content" style="max-width: 1400px; margin: 2rem auto; padding: 0 1rem;">
    <div class="section-title" style="margin-bottom: 2rem;">
      <h1 style="font-size: 2rem; font-weight: 700; color: #111827; margin-bottom: 0.5rem;">Advanced Search</h1>
      <p class="muted" style="font-size: 1rem;">Find exactly what you're looking for with our powerful search filters</p>
    </div>

    <div class="search" style="background: #fff; border: 1px solid #e5e7eb; border-radius: 16px; padding: 2rem; box-shadow: 0 2px 8px rgba(0,0,0,0.04); margin-bottom: 2.5rem;">
      <form action="search.php" method="get" style="display: grid; grid-template-columns: 2fr 1fr 1fr 1fr auto; gap: 1rem; align-items: end;">
        <div>
          <label style="display: block; font-weight: 600; color: #374151; margin-bottom: 0.5rem; font-size: 0.875rem;">🔍 Keyword Search</label>
          <input class="input" type="text" name="q" placeholder="e.g., Toyota, iPhone, Apartment..." value="<?php echo h($q); ?>" style="width: 100%; padding: 0.75rem 1rem; border: 1px solid #d1d5db; border-radius: 8px; font-size: 1rem;" />
        </div>
        <div>
          <label style="display: block; font-weight: 600; color: #374151; margin-bottom: 0.5rem; font-size: 0.875rem;">📊 Status</label>
          <select name="etat" style="width: 100%; padding: 0.75rem 1rem; border: 1px solid #d1d5db; border-radius: 8px; font-size: 1rem; background: #fff;">
            <option value="">Any Status</option>
            <option value="active" <?php echo $etat==='active'?'selected':''; ?>>Active</option>
            <option value="inactive" <?php echo $etat==='inactive'?'selected':''; ?>>Inactive</option>
          </select>
        </div>
        <div>
          <label style="display: block; font-weight: 600; color: #374151; margin-bottom: 0.5rem; font-size: 0.875rem;">📅 Date</label>
          <input type="date" name="date" value="<?php echo h($date); ?>" style="width: 100%; padding: 0.75rem 1rem; border: 1px solid #d1d5db; border-radius: 8px; font-size: 1rem;" />
        </div>
        <div style="display: flex; gap: 0.5rem; align-items: end;">
          <button class="btn" type="submit" style="padding: 0.75rem 2rem; font-weight: 600; white-space: nowrap;">Search</button>
          <button class="btn ghost" type="reset" style="padding: 0.75rem 1.5rem; white-space: nowrap;">Clear</button>
        </div>
      </form>
    </div>

    <?php if (!empty($results)): ?>
      <div style="margin-bottom: 1.5rem; padding: 1rem; background: #f0fdf4; border: 1px solid #86efac; border-radius: 8px;">
        <strong style="color: #065f46;">Found <?php echo count($results); ?> result<?php echo count($results) !== 1 ? 's' : ''; ?></strong>
      </div>
    <?php endif; ?>

    <div class="cards grid" style="margin-top: 2rem;">
      <?php if (empty($results)): ?>
        <div style="grid-column: 1 / -1; text-align: center; padding: 4rem 2rem; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 12px;">
          <div style="font-size: 3rem; margin-bottom: 1rem;">🔍</div>
          <div class="muted" style="font-size: 1.125rem; color: #6b7280;">No results found.</div>
          <p class="muted" style="margin-top: 0.5rem; color: #9ca3af;">Try adjusting your search criteria or browse all listings.</p>
          <a href="user-consult.php" class="btn" style="margin-top: 1.5rem; display: inline-block;">Browse All Listings</a>
        </div>
      <?php else: ?>
        <?php foreach ($results as $r): ?>
          <article class="card" style="transition: transform 0.2s ease, box-shadow 0.2s ease;">
            <?php $src = getImagePath($r['image_path'] ?? null); ?>
            <img src="<?php echo h($src); ?>" alt="<?php echo h($r['titre']); ?>" style="transition: transform 0.2s ease;" />
            <div class="card-body" style="padding: 1.25rem;">
              <div class="card-title" style="font-size: 1.125rem; margin-bottom: 0.75rem;"><?php echo h($r['titre']); ?></div>
              <div class="card-meta" style="margin-bottom: 1rem;">
                <?php if (!empty($r['prix']) && $r['prix'] > 0): ?>
                  <span style="color: #059669; font-weight: 600; font-size: 1.1rem;">€<?php echo number_format((float)$r['prix'], 2, '.', ''); ?></span>
                  <span>•</span>
                <?php endif; ?>
                By <?php echo h($r['auteur']); ?> • <?php echo (int)max(0, (new DateTime())->diff(new DateTime($r['date_publication']))->days); ?> days ago • <?php echo h($r['etat']); ?>
                <?php if (!empty($r['ville'])): ?>
                  <span>•</span>
                  <span>📍 <?php echo h($r['ville']); ?></span>
                <?php endif; ?>
              </div>
              <div class="card-actions" style="padding: 0; margin-top: auto;">
                <a class="btn ghost" href="ad-details.php?id=<?php echo (int)$r['id']; ?>" style="width: 100%; text-align: center;">See details</a>
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
  <script src="js/main.js"></script>
  <script src="js/mobile-toggle.js"></script>
  <script>document.getElementById('year').textContent = new Date().getFullYear();</script>
</body>
</html>
