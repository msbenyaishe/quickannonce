<?php
  include 'config.php';
  if (!isLoggedIn() || isAdmin()) {
    // Redirect if not logged in OR if admin (admins should use index-admin.php)
    if (!isLoggedIn()) {
      header('Location: login.php');
    } else {
      header('Location: index-admin.php');
    }
    exit;
  }
  $pdo = getPDO();
  runArchiver($pdo);
  $cards = [];
  try {
    $stmt = $pdo->prepare("SELECT id, titre, description, date_publication, etat, image_path, prix, ville, categorie FROM annonces WHERE id_utilisateur = ? ORDER BY date_publication DESC LIMIT 8");
    $stmt->execute([currentUserId()]);
    $cards = $stmt->fetchAll();
  } catch (Throwable $e) {
    $cards = [];
  }
?>

<!DOCTYPE html>
<html lang="en" class="grid">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>QuickAnnonce - User Dashboard</title>
  <link rel="stylesheet" href="css/styles.css" />
</head>
<body class="grid">

  <!-- HEADER -->
  <header class="header">
    <div class="container header-inner">
      <a class="brand" href="index-user.php">
        <span class="brand-logo">QA</span>
        <span class="brand-name">QuickAnnonce</span>
      </a>

      <!-- USER NAVIGATION -->
      <nav class="nav">
        <a class="active" href="index-user.php">Home</a>
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

  <!-- BANNER -->
  <section class="banner">
    <div class="container banner-inner">
      <div>
        <div class="overline">Your trusted classifieds</div>
        <h1>Find anything. Sell anything. Fast.</h1>
        <p>Browse the latest listings across categories and cities. Post your ad in seconds.</p>
        <div style="margin-top:12px; display:flex; gap:10px;">
          <a class="btn" href="user-consult-user.php">Browse Listings</a>
          <a class="btn secondary" href="post-user.php">Post Your Ad</a>
        </div>

        <!-- SEARCH FORM -->
        <div class="search">
          <form action="search.php" method="get">
            <input class="input" type="text" name="q" placeholder="What are you looking for?" />
            <select name="category">
              <option value="">All Categories</option>
              <option>Vehicles</option>
              <option>Real Estate</option>
              <option>Electronics</option>
              <option>Clothing</option>
              <option>Jobs</option>
            </select>
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
            <input class="input" type="number" name="min" placeholder="Min price" />
            <input class="input" type="number" name="max" placeholder="Max price" />
            <button class="btn" type="submit">Search</button>
          </form>
        </div>

        <!-- POPULAR CATEGORIES -->
        <div class="chips" aria-label="Popular categories">
          <a class="chip" href="filter.php?category=Vehicles">🚗 Vehicles</a>
          <a class="chip" href="filter.php?category=Real%20Estate">🏠 Real Estate</a>
          <a class="chip" href="filter.php?category=Electronics">💻 Electronics</a>
          <a class="chip" href="filter.php?category=Jobs">🧑‍💼 Jobs</a>
        </div>
      </div>

      <div>
        <img src="https://images.unsplash.com/photo-1512295767273-ac109ac3acfa?q=80&w=1200&auto=format&fit=crop" alt="Shopping" />
      </div>
    </div>
  </section>

  <!-- MAIN CONTENT -->
  <main class="container">
    <div class="section-title">
      <h2>Latest Announcements</h2>
      <a class="btn ghost" href="user-consult-user.php">See all</a>
    </div>

    <div class="cards grid" data-view-container>
      <?php if (empty($cards)): ?>
        <div class="muted">You have not posted any announcements yet.</div>
      <?php else: ?>
        <?php foreach ($cards as $c): ?>
          <article class="card">
            <?php $src = getImagePath($c['image_path'] ?? null); ?>
            <img src="<?php echo h($src); ?>" alt="<?php echo h($c['titre']); ?>" />
            <div class="card-body">
              <div class="card-title"><?php echo h($c['titre']); ?></div>
              <div class="card-meta">
                <?php if (!empty($c['prix']) && $c['prix'] > 0): ?>
                  <span style="color: #059669; font-weight: 600; font-size: 1.1rem;">€<?php echo number_format((float)$c['prix'], 2, '.', ''); ?></span>
                  <span>•</span>
                <?php endif; ?>
                <?php echo (int)max(0, (new DateTime())->diff(new DateTime($c['date_publication']))->days); ?> days ago • <?php echo h($c['etat']); ?>
                <?php if (!empty($c['ville'])): ?>
                  <span>•</span>
                  <span>📍 <?php echo h($c['ville']); ?></span>
                <?php endif; ?>
              </div>
              <div class="card-actions">
                <a class="btn ghost" href="ad-details.php?id=<?php echo (int)$c['id']; ?>">See details</a>
                <a class="btn" href="post-user.php">Post new</a>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </main>

  <!-- FEATURES -->
  <section class="container features">
    <div class="feature">
      <div class="feature-icon">⚡</div>
      <div class="feature-title">Post in minutes</div>
      <div class="feature-text">Simple forms, quick approvals, and instant visibility.</div>
    </div>
    <div class="feature">
      <div class="feature-icon">🔎</div>
      <div class="feature-title">Powerful search</div>
      <div class="feature-text">Filter by category, city, price and date to find deals faster.</div>
    </div>
    <div class="feature">
      <div class="feature-icon">🛡️</div>
      <div class="feature-title">Safe & moderated</div>
      <div class="feature-text">Admins validate posts and keep the marketplace clean.</div>
    </div>
  </section>

  <!-- FOOTER -->
  <footer class="footer">
    <div class="container footer-inner">
      <div>
        <strong>QuickAnnonce</strong>
        <div class="muted">© <span id="year"></span> All rights reserved.</div>
      </div>
      <div style="display:flex; gap:12px;">
        <a href="contact-user.php">Contact</a>
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
