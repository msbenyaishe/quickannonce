<?php
  include 'config.php';
  $pdo = getPDO();
  
  // Fetch all announcements
  $stmt = $pdo->query("
    SELECT a.*, u.nom AS user_name
    FROM annonces a
    LEFT JOIN utilisateurs u ON a.id_utilisateur = u.id
    ORDER BY a.date_publication DESC
  ");

  $announcements = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en" class="grid">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Browse Announcements - QuickAnnonce</title>
  <link rel="stylesheet" href="css/styles.css" />
</head>
<body class="grid">
  <header class="header">
    <div class="container header-inner">
      <a class="brand" href="index-admin.php"><span class="brand-logo">QA</span><span class="brand-name">QuickAnnonce</span></a>
      <!-- ADMIN NAVBAR: Home, Announcements, Admin, Logout -->
      <nav class="nav">
        <a href="index-admin.php">Home</a>
        <a class="active" href="user-consult-admin.php">Announcements</a>
        <a href="admin-console.php">Admin</a>
        <a href="logout.php">Logout</a>
      </nav>
      <div style="display:flex; gap:10px; align-items:center;">
        <a class="cta" href="post-admin.php">Post Your Ad</a>
        <button class="mobile-toggle" aria-label="Toggle navigation">☰</button>
      </div>
    </div>
  </header>

  <main class="container content" data-view-container>
    <div class="section-title">
      <h1>All Announcements</h1>
    </div>

    <div class="search search--inline-filters">
      <form action="filter.php" method="get">
        <input class="input" type="text" name="q" placeholder="Search..." />
        <select name="category"><option value="">Category</option><option>Vehicles</option><option>Real Estate</option><option>Electronics</option><option>Clothing</option></select>
        <select name="city">
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
        <input class="input" type="number" name="min" placeholder="Min" />
        <input class="input" type="number" name="max" placeholder="Max" />
        <button class="btn" type="submit">Filter</button>
      </form>
    </div>

    <div class="cards grid">
      <?php if (empty($announcements)): ?>
        <div class="empty-state">
          <p>No announcements available at the moment.</p>
        </div>
      <?php else: ?>
        <?php foreach ($announcements as $a): ?>
          <article class="card">
            <?php $src = getImagePath($a['image_path'] ?? null); ?>
            <img src="<?php echo h($src); ?>" alt="<?php echo h($a['titre']); ?>" />
            <div class="card-body">
              <div class="card-title"><?php echo h($a['titre']); ?></div>
              <div class="card-meta">
                <?php if (!empty($a['prix']) && $a['prix'] > 0): ?>
                  <span style="color: #059669; font-weight: 600; font-size: 1.1rem;">€<?php echo number_format((float)$a['prix'], 2, '.', ''); ?></span>
                  <span>•</span>
                <?php endif; ?>
                By <?php echo h($a['user_name'] ?? 'Unknown'); ?> • <?php echo (int)max(0, (new DateTime())->diff(new DateTime($a['date_publication']))->days); ?> days ago • <?php echo h($a['etat']); ?>
                <?php if (!empty($a['ville'])): ?>
                  <span>•</span>
                  <span>📍 <?php echo h($a['ville']); ?></span>
                <?php endif; ?>
              </div>
              <div class="card-actions">
                <a class="btn ghost" href="ad-details.php?id=<?php echo (int)$a['id']; ?>">See details</a>
                <a class="btn" href="admin-edit-ad.php?id=<?php echo (int)$a['id']; ?>">Edit</a>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <nav class="pagination">
      <a href="#" class="active">1</a>
      <a href="#">2</a>
      <a href="#">3</a>
      <a href="#">Next</a>
    </nav>
  </main>

  <footer class="footer">
    <div class="container footer-inner">
      <div><strong>QuickAnnonce</strong><div class="muted">© <span id="year"></span></div></div>
      <div style="display:flex; gap:12px;"><a href="admin-console.php">Admin</a></div>
    </div>
  </footer>
  <script>document.getElementById('year').textContent = new Date().getFullYear();</script>
  <script src="js/main.js"></script>
  <script src="js/mobile-toggle.js"></script>
</body>
</html>
