<?php
  include 'config.php';
  $pdo = getPDO();
  
  // Get filter parameters
  $q = trim($_GET['q'] ?? '');
  $etat = trim($_GET['etat'] ?? '');
  $category = trim($_GET['category'] ?? '');
  $city = trim($_GET['city'] ?? '');
  $minPrice = isset($_GET['min']) && $_GET['min'] !== '' ? (float)$_GET['min'] : null;
  $maxPrice = isset($_GET['max']) && $_GET['max'] !== '' ? (float)$_GET['max'] : null;
  $sort = trim($_GET['sort'] ?? 'date_desc');
  $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
  $perPage = 20;
  $offset = ($page - 1) * $perPage;

  // Build WHERE conditions
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
  if ($category !== '') {
    $where[] = 'a.categorie = :category';
    $params[':category'] = $category;
  }
  if ($city !== '') {
    $where[] = 'a.ville = :city';
    $params[':city'] = $city;
  }
  if ($minPrice !== null && $minPrice > 0) {
    $where[] = 'a.prix >= :min_price';
    $params[':min_price'] = $minPrice;
  }
  if ($maxPrice !== null && $maxPrice > 0) {
    $where[] = 'a.prix <= :max_price';
    $params[':max_price'] = $maxPrice;
  }

  // Build ORDER BY clause
  $orderBy = 'a.date_publication DESC';
  switch ($sort) {
    case 'date_asc':
      $orderBy = 'a.date_publication ASC';
      break;
    case 'price_asc':
      $orderBy = 'a.prix ASC, a.date_publication DESC';
      break;
    case 'price_desc':
      $orderBy = 'a.prix DESC, a.date_publication DESC';
      break;
    case 'title_asc':
      $orderBy = 'a.titre ASC';
      break;
    default:
      $orderBy = 'a.date_publication DESC';
  }

  // Build SQL query
  $sql = 'SELECT a.id, a.titre, a.description, a.date_publication, a.etat, a.image_path, a.prix, a.ville, a.categorie, u.nom AS auteur
          FROM annonces a JOIN utilisateurs u ON a.id_utilisateur = u.id
          WHERE a.moderation_status = \'approved\'';
  if (!empty($where)) {
    $sql .= ' AND ' . implode(' AND ', $where);
  }
  
  // Get total count for pagination
  $countSql = 'SELECT COUNT(*) as total FROM annonces a WHERE a.moderation_status = \'approved\'';
  if (!empty($where)) {
    $countSql .= ' AND ' . implode(' AND ', $where);
  }
  
  $totalResults = 0;
  $results = [];
  
  try {
    // Get total count
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $totalResults = (int)$countStmt->fetch()['total'];
    
    // Get paginated results
    $sql .= ' ORDER BY ' . $orderBy . ' LIMIT :limit OFFSET :offset';
    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
      $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $results = $stmt->fetchAll();
    
    $totalPages = ceil($totalResults / $perPage);
  } catch (Throwable $e) {
    error_log('Filter error: ' . $e->getMessage());
    $results = [];
    $totalResults = 0;
    $totalPages = 0;
  }
  
  // Get active filters count
  $activeFilters = 0;
  if ($q !== '') $activeFilters++;
  if ($etat !== '') $activeFilters++;
  if ($category !== '') $activeFilters++;
  if ($city !== '') $activeFilters++;
  if ($minPrice !== null && $minPrice > 0) $activeFilters++;
  if ($maxPrice !== null && $maxPrice > 0) $activeFilters++;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Filter Results - QuickAnnonce</title>
  <link rel="stylesheet" href="css/styles.css" />
  <style>
    /* Responsive filter form */
    @media (max-width: 1024px) {
      .search form {
        grid-template-columns: 1fr 1fr 1fr !important;
      }
      .search form > div:last-child {
        grid-column: 1 / -1;
        justify-content: flex-start;
      }
    }
    @media (max-width: 768px) {
      .search form {
        grid-template-columns: 1fr 1fr !important;
      }
      .search form > div:last-child {
        grid-column: 1 / -1;
      }
      .cards.grid {
        grid-template-columns: repeat(2, 1fr) !important;
      }
    }
    @media (max-width: 480px) {
      .search form {
        grid-template-columns: 1fr !important;
      }
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
        <a class="active" href="user-consult.php">Announcements</a>
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
    <div class="section-title" style="margin-bottom: 1.5rem;">
      <h1 style="font-size: 2rem; font-weight: 700; color: #111827; margin-bottom: 0.5rem;">Advanced Filters</h1>
      <p class="muted" style="font-size: 1rem;">Refine your search with multiple filters to find exactly what you're looking for</p>
    </div>

    <!-- Active Filters Display -->
    <?php if ($activeFilters > 0): ?>
      <div style="margin-bottom: 1.5rem; padding: 1rem; background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 8px; display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center;">
        <span style="font-weight: 600; color: #0369a1; font-size: 0.875rem;">Active Filters (<?php echo $activeFilters; ?>):</span>
        <?php if ($q !== ''): ?>
          <span class="filter-chip" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.375rem 0.75rem; background: #fff; border: 1px solid #93c5fd; border-radius: 6px; font-size: 0.875rem;">
            🔍 <?php echo h($q); ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['q' => ''])); ?>" style="color: #dc2626; text-decoration: none; margin-left: 0.25rem;">×</a>
          </span>
        <?php endif; ?>
        <?php if ($category !== ''): ?>
          <span class="filter-chip" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.375rem 0.75rem; background: #fff; border: 1px solid #93c5fd; border-radius: 6px; font-size: 0.875rem;">
            🏷️ <?php echo h($category); ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['category' => ''])); ?>" style="color: #dc2626; text-decoration: none; margin-left: 0.25rem;">×</a>
          </span>
        <?php endif; ?>
        <?php if ($city !== ''): ?>
          <span class="filter-chip" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.375rem 0.75rem; background: #fff; border: 1px solid #93c5fd; border-radius: 6px; font-size: 0.875rem;">
            📍 <?php echo h($city); ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['city' => ''])); ?>" style="color: #dc2626; text-decoration: none; margin-left: 0.25rem;">×</a>
          </span>
        <?php endif; ?>
        <?php if ($etat !== ''): ?>
          <span class="filter-chip" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.375rem 0.75rem; background: #fff; border: 1px solid #93c5fd; border-radius: 6px; font-size: 0.875rem;">
            📊 <?php echo ucfirst($etat); ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['etat' => ''])); ?>" style="color: #dc2626; text-decoration: none; margin-left: 0.25rem;">×</a>
          </span>
        <?php endif; ?>
        <?php if ($minPrice !== null && $minPrice > 0): ?>
          <span class="filter-chip" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.375rem 0.75rem; background: #fff; border: 1px solid #93c5fd; border-radius: 6px; font-size: 0.875rem;">
            💰 Min: €<?php echo number_format($minPrice, 2); ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['min' => ''])); ?>" style="color: #dc2626; text-decoration: none; margin-left: 0.25rem;">×</a>
          </span>
        <?php endif; ?>
        <?php if ($maxPrice !== null && $maxPrice > 0): ?>
          <span class="filter-chip" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.375rem 0.75rem; background: #fff; border: 1px solid #93c5fd; border-radius: 6px; font-size: 0.875rem;">
            💰 Max: €<?php echo number_format($maxPrice, 2); ?>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['max' => ''])); ?>" style="color: #dc2626; text-decoration: none; margin-left: 0.25rem;">×</a>
          </span>
        <?php endif; ?>
        <a href="filter.php" class="btn ghost" style="padding: 0.375rem 0.75rem; font-size: 0.875rem; margin-left: auto;">Clear All</a>
      </div>
    <?php endif; ?>

    <!-- Advanced Filter Form -->
    <div class="search" style="background: #fff; border: 1px solid #e5e7eb; border-radius: 16px; padding: 2rem; box-shadow: 0 2px 8px rgba(0,0,0,0.04); margin-bottom: 2rem;">
      <form action="filter.php" method="get" style="display: grid; grid-template-columns: 2fr 1fr 1fr 1fr 1fr 1fr auto; gap: 1rem; align-items: end;">
        <div>
          <label style="display: block; font-weight: 600; color: #374151; margin-bottom: 0.5rem; font-size: 0.875rem;">🔍 Search</label>
          <input class="input" type="text" name="q" placeholder="Keywords, title, description..." value="<?php echo h($q); ?>" style="width: 100%; padding: 0.75rem 1rem; border: 1px solid #d1d5db; border-radius: 8px; font-size: 1rem;" />
        </div>
        <div>
          <label style="display: block; font-weight: 600; color: #374151; margin-bottom: 0.5rem; font-size: 0.875rem;">🏷️ Category</label>
          <select name="category" style="width: 100%; padding: 0.75rem 1rem; border: 1px solid #d1d5db; border-radius: 8px; font-size: 1rem; background: #fff;">
            <option value="">All Categories</option>
            <option value="Vehicles" <?php echo $category==='Vehicles'?'selected':''; ?>>🚗 Vehicles</option>
            <option value="Real Estate" <?php echo $category==='Real Estate'?'selected':''; ?>>🏠 Real Estate</option>
            <option value="Electronics" <?php echo $category==='Electronics'?'selected':''; ?>>💻 Electronics</option>
            <option value="Clothing" <?php echo $category==='Clothing'?'selected':''; ?>>👗 Clothing</option>
            <option value="Jobs" <?php echo $category==='Jobs'?'selected':''; ?>>🧑‍💼 Jobs</option>
          </select>
        </div>
        <div>
          <label style="display: block; font-weight: 600; color: #374151; margin-bottom: 0.5rem; font-size: 0.875rem;">📍 City</label>
          <select name="city" style="width: 100%; padding: 0.75rem 1rem; border: 1px solid #d1d5db; border-radius: 8px; font-size: 1rem; background: #fff;">
  <option value="">All Cities</option>
  <option value="Agadir" <?php echo $city==='Agadir'?'selected':''; ?>>Agadir</option>
  <option value="Ahfir" <?php echo $city==='Ahfir'?'selected':''; ?>>Ahfir</option>
  <option value="Al Hoceima" <?php echo $city==='Al Hoceima'?'selected':''; ?>>Al Hoceima</option>
  <option value="Arfoud" <?php echo $city==='Arfoud'?'selected':''; ?>>Arfoud</option>
  <option value="Asilah" <?php echo $city==='Asilah'?'selected':''; ?>>Asilah</option>
  <option value="Azemmour" <?php echo $city==='Azemmour'?'selected':''; ?>>Azemmour</option>
  <option value="Azilal" <?php echo $city==='Azilal'?'selected':''; ?>>Azilal</option>
  <option value="Beni Mellal" <?php echo $city==='Beni Mellal'?'selected':''; ?>>Beni Mellal</option>
  <option value="Berkane" <?php echo $city==='Berkane'?'selected':''; ?>>Berkane</option>
  <option value="Berrechid" <?php echo $city==='Berrechid'?'selected':''; ?>>Berrechid</option>
  <option value="Bouarfa" <?php echo $city==='Bouarfa'?'selected':''; ?>>Bouarfa</option>
  <option value="Boujdour" <?php echo $city==='Boujdour'?'selected':''; ?>>Boujdour</option>
  <option value="Boulemane" <?php echo $city==='Boulemane'?'selected':''; ?>>Boulemane</option>
  <option value="Bouskoura" <?php echo $city==='Bouskoura'?'selected':''; ?>>Bouskoura</option>
  <option value="Casablanca" <?php echo $city==='Casablanca'?'selected':''; ?>>Casablanca</option>
  <option value="Chefchaouen" <?php echo $city==='Chefchaouen'?'selected':''; ?>>Chefchaouen</option>
  <option value="Chichaoua" <?php echo $city==='Chichaoua'?'selected':''; ?>>Chichaoua</option>
  <option value="Dakhla" <?php echo $city==='Dakhla'?'selected':''; ?>>Dakhla</option>
  <option value="Dar Bouazza" <?php echo $city==='Dar Bouazza'?'selected':''; ?>>Dar Bouazza</option>
  <option value="Demnate" <?php echo $city==='Demnate'?'selected':''; ?>>Demnate</option>
  <option value="El Hajeb" <?php echo $city==='El Hajeb'?'selected':''; ?>>El Hajeb</option>
  <option value="El Jadida" <?php echo $city==='El Jadida'?'selected':''; ?>>El Jadida</option>
  <option value="El Kelaa des Sraghna" <?php echo $city==='El Kelaa des Sraghna'?'selected':''; ?>>El Kelaa des Sraghna</option>
  <option value="Errachidia" <?php echo $city==='Errachidia'?'selected':''; ?>>Errachidia</option>
  <option value="Essaouira" <?php echo $city==='Essaouira'?'selected':''; ?>>Essaouira</option>
  <option value="Fès" <?php echo $city==='Fès'?'selected':''; ?>>Fès</option>
  <option value="Fnideq" <?php echo $city==='Fnideq'?'selected':''; ?>>Fnideq</option>
  <option value="Fquih Ben Salah" <?php echo $city==='Fquih Ben Salah'?'selected':''; ?>>Fquih Ben Salah</option>
  <option value="Guelmim" <?php echo $city==='Guelmim'?'selected':''; ?>>Guelmim</option>
  <option value="Guercif" <?php echo $city==='Guercif'?'selected':''; ?>>Guercif</option>
  <option value="Ifrane" <?php echo $city==='Ifrane'?'selected':''; ?>>Ifrane</option>
  <option value="Imzouren" <?php echo $city==='Imzouren'?'selected':''; ?>>Imzouren</option>
  <option value="Inzegane" <?php echo $city==='Inzegane'?'selected':''; ?>>Inzegane</option>
  <option value="Jerada" <?php echo $city==='Jerada'?'selected':''; ?>>Jerada</option>
  <option value="Kalaat Mgouna" <?php echo $city==='Kalaat Mgouna'?'selected':''; ?>>Kalaat Mgouna</option>
  <option value="Kenitra" <?php echo $city==='Kenitra'?'selected':''; ?>>Kenitra</option>
  <option value="Khemisset" <?php echo $city==='Khemisset'?'selected':''; ?>>Khemisset</option>
  <option value="Khenifra" <?php echo $city==='Khenifra'?'selected':''; ?>>Khenifra</option>
  <option value="Khouribga" <?php echo $city==='Khouribga'?'selected':''; ?>>Khouribga</option>
  <option value="Ksar El Kebir" <?php echo $city==='Ksar El Kebir'?'selected':''; ?>>Ksar El Kebir</option>
  <option value="Larache" <?php echo $city==='Larache'?'selected':''; ?>>Larache</option>
  <option value="Laayoune" <?php echo $city==='Laayoune'?'selected':''; ?>>Laayoune</option>
  <option value="Marrakech" <?php echo $city==='Marrakech'?'selected':''; ?>>Marrakech</option>
  <option value="Martil" <?php echo $city==='Martil'?'selected':''; ?>>Martil</option>
  <option value="Mediouna" <?php echo $city==='Mediouna'?'selected':''; ?>>Mediouna</option>
  <option value="Mechra Bel Ksiri" <?php echo $city==='Mechra Bel Ksiri'?'selected':''; ?>>Mechra Bel Ksiri</option>
  <option value="Meknès" <?php echo $city==='Meknès'?'selected':''; ?>>Meknès</option>
  <option value="Midelt" <?php echo $city==='Midelt'?'selected':''; ?>>Midelt</option>
  <option value="Mohammedia" <?php echo $city==='Mohammedia'?'selected':''; ?>>Mohammedia</option>
  <option value="Nador" <?php echo $city==='Nador'?'selected':''; ?>>Nador</option>
  <option value="Ouarzazate" <?php echo $city==='Ouarzazate'?'selected':''; ?>>Ouarzazate</option>
  <option value="Ouezzane" <?php echo $city==='Ouezzane'?'selected':''; ?>>Ouezzane</option>
  <option value="Oujda" <?php echo $city==='Oujda'?'selected':''; ?>>Oujda</option>
  <option value="Oulad Teima" <?php echo $city==='Oulad Teima'?'selected':''; ?>>Oulad Teima</option>
  <option value="Rabat" <?php echo $city==='Rabat'?'selected':''; ?>>Rabat</option>
  <option value="Safi" <?php echo $city==='Safi'?'selected':''; ?>>Safi</option>
  <option value="Salé" <?php echo $city==='Salé'?'selected':''; ?>>Salé</option>
  <option value="Sefrou" <?php echo $city==='Sefrou'?'selected':''; ?>>Sefrou</option>
  <option value="Settat" <?php echo $city==='Settat'?'selected':''; ?>>Settat</option>
  <option value="Sidi Bennour" <?php echo $city==='Sidi Bennour'?'selected':''; ?>>Sidi Bennour</option>
  <option value="Sidi Ifni" <?php echo $city==='Sidi Ifni'?'selected':''; ?>>Sidi Ifni</option>
  <option value="Sidi Kacem" <?php echo $city==='Sidi Kacem'?'selected':''; ?>>Sidi Kacem</option>
  <option value="Sidi Slimane" <?php echo $city==='Sidi Slimane'?'selected':''; ?>>Sidi Slimane</option>
  <option value="Skhirat" <?php echo $city==='Skhirat'?'selected':''; ?>>Skhirat</option>
  <option value="Smara" <?php echo $city==='Smara'?'selected':''; ?>>Smara</option>
  <option value="Souk El Arbaa" <?php echo $city==='Souk El Arbaa'?'selected':''; ?>>Souk El Arbaa</option>
  <option value="Tafraout" <?php echo $city==='Tafraout'?'selected':''; ?>>Tafraout</option>
  <option value="Taliouine" <?php echo $city==='Taliouine'?'selected':''; ?>>Taliouine</option>
  <option value="Tan-Tan" <?php echo $city==='Tan-Tan'?'selected':''; ?>>Tan-Tan</option>
  <option value="Tanger" <?php echo $city==='Tanger'?'selected':''; ?>>Tanger</option>
  <option value="Taounate" <?php echo $city==='Taounate'?'selected':''; ?>>Taounate</option>
  <option value="Tarfaya" <?php echo $city==='Tarfaya'?'selected':''; ?>>Tarfaya</option>
  <option value="Taroudant" <?php echo $city==='Taroudant'?'selected':''; ?>>Taroudant</option>
  <option value="Tata" <?php echo $city==='Tata'?'selected':''; ?>>Tata</option>
  <option value="Taza" <?php echo $city==='Taza'?'selected':''; ?>>Taza</option>
  <option value="Témara" <?php echo $city==='Témara'?'selected':''; ?>>Témara</option>
  <option value="Tétouan" <?php echo $city==='Tétouan'?'selected':''; ?>>Tétouan</option>
  <option value="Tinghir" <?php echo $city==='Tinghir'?'selected':''; ?>>Tinghir</option>
  <option value="Tiznit" <?php echo $city==='Tiznit'?'selected':''; ?>>Tiznit</option>
  <option value="Youssoufia" <?php echo $city==='Youssoufia'?'selected':''; ?>>Youssoufia</option>
  <option value="Zagora" <?php echo $city==='Zagora'?'selected':''; ?>>Zagora</option>
</select>

        </div>
        <div>
          <label style="display: block; font-weight: 600; color: #374151; margin-bottom: 0.5rem; font-size: 0.875rem;">💰 Min Price</label>
          <input class="input" type="number" name="min" placeholder="0" value="<?php echo $minPrice !== null ? h($minPrice) : ''; ?>" min="0" step="0.01" style="width: 100%; padding: 0.75rem 1rem; border: 1px solid #d1d5db; border-radius: 8px; font-size: 1rem;" />
        </div>
        <div>
          <label style="display: block; font-weight: 600; color: #374151; margin-bottom: 0.5rem; font-size: 0.875rem;">💰 Max Price</label>
          <input class="input" type="number" name="max" placeholder="No limit" value="<?php echo $maxPrice !== null ? h($maxPrice) : ''; ?>" min="0" step="0.01" style="width: 100%; padding: 0.75rem 1rem; border: 1px solid #d1d5db; border-radius: 8px; font-size: 1rem;" />
        </div>
        <div>
          <label style="display: block; font-weight: 600; color: #374151; margin-bottom: 0.5rem; font-size: 0.875rem;">📊 Status</label>
          <select name="etat" style="width: 100%; padding: 0.75rem 1rem; border: 1px solid #d1d5db; border-radius: 8px; font-size: 1rem; background: #fff;">
            <option value="">All Status</option>
            <option value="active" <?php echo $etat==='active'?'selected':''; ?>>Active</option>
            <option value="inactive" <?php echo $etat==='inactive'?'selected':''; ?>>Inactive</option>
          </select>
        </div>
        <div style="display: flex; gap: 0.5rem; align-items: end;">
          <button class="btn" type="submit" style="padding: 0.75rem 2rem; font-weight: 600; white-space: nowrap;">Apply Filters</button>
        </div>
      </form>
    </div>

    <!-- Results Header with Sort and Count -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
      <div>
        <?php if ($totalResults > 0): ?>
          <div style="font-size: 1.125rem; font-weight: 600; color: #111827;">
            Found <span style="color: var(--primary);"><?php echo number_format($totalResults); ?></span> result<?php echo $totalResults !== 1 ? 's' : ''; ?>
          </div>
          <div class="muted" style="font-size: 0.875rem; margin-top: 0.25rem;">
            Showing <?php echo $offset + 1; ?>-<?php echo min($offset + $perPage, $totalResults); ?> of <?php echo number_format($totalResults); ?>
          </div>
        <?php else: ?>
          <div style="font-size: 1.125rem; font-weight: 600; color: #6b7280;">No results found</div>
        <?php endif; ?>
      </div>
      <?php if ($totalResults > 0): ?>
        <div style="display: flex; align-items: center; gap: 0.75rem;">
          <label style="font-weight: 600; color: #374151; font-size: 0.875rem;">Sort by:</label>
          <select name="sort" id="sort-select" style="padding: 0.5rem 1rem; border: 1px solid #d1d5db; border-radius: 8px; font-size: 0.875rem; background: #fff; cursor: pointer;">
            <option value="date_desc" <?php echo $sort==='date_desc'?'selected':''; ?>>Newest First</option>
            <option value="date_asc" <?php echo $sort==='date_asc'?'selected':''; ?>>Oldest First</option>
            <option value="price_asc" <?php echo $sort==='price_asc'?'selected':''; ?>>Price: Low to High</option>
            <option value="price_desc" <?php echo $sort==='price_desc'?'selected':''; ?>>Price: High to Low</option>
            <option value="title_asc" <?php echo $sort==='title_asc'?'selected':''; ?>>Title: A-Z</option>
          </select>
        </div>
      <?php endif; ?>
    </div>

    <!-- Results Grid -->
    <div class="cards grid">
      <?php if (empty($results)): ?>
        <div style="grid-column: 1 / -1; text-align: center; padding: 4rem 2rem; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 12px;">
          <div style="font-size: 3rem; margin-bottom: 1rem;">🔍</div>
          <div style="font-size: 1.125rem; color: #6b7280; font-weight: 600; margin-bottom: 0.5rem;">No results found</div>
          <p class="muted" style="margin-top: 0.5rem; color: #9ca3af; margin-bottom: 1.5rem;">
            <?php if ($activeFilters > 0): ?>
              Try adjusting your filters or <a href="filter.php" style="color: var(--primary); text-decoration: underline;">clear all filters</a> to see more results.
            <?php else: ?>
              No listings match your criteria. <a href="user-consult.php" style="color: var(--primary); text-decoration: underline;">Browse all listings</a> instead.
            <?php endif; ?>
          </p>
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
                <?php if (!empty($r['categorie'])): ?>
                  <span>•</span>
                  <span>🏷️ <?php echo h($r['categorie']); ?></span>
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

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
      <nav class="pagination" style="margin-top: 2rem; display: flex; justify-content: center; gap: 0.5rem; flex-wrap: wrap;">
        <?php if ($page > 1): ?>
          <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="btn ghost" style="padding: 0.5rem 1rem;">← Previous</a>
        <?php endif; ?>
        
        <?php
        $startPage = max(1, $page - 2);
        $endPage = min($totalPages, $page + 2);
        
        if ($startPage > 1): ?>
          <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>" class="btn ghost" style="padding: 0.5rem 1rem;">1</a>
          <?php if ($startPage > 2): ?>
            <span style="padding: 0.5rem;">...</span>
          <?php endif; ?>
        <?php endif; ?>
        
        <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
          <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
             class="btn <?php echo $i === $page ? '' : 'ghost'; ?>" 
             style="padding: 0.5rem 1rem; <?php echo $i === $page ? 'background: var(--primary); color: #fff;' : ''; ?>">
            <?php echo $i; ?>
          </a>
        <?php endfor; ?>
        
        <?php if ($endPage < $totalPages): ?>
          <?php if ($endPage < $totalPages - 1): ?>
            <span style="padding: 0.5rem;">...</span>
          <?php endif; ?>
          <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $totalPages])); ?>" class="btn ghost" style="padding: 0.5rem 1rem;"><?php echo $totalPages; ?></a>
        <?php endif; ?>
        
        <?php if ($page < $totalPages): ?>
          <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="btn ghost" style="padding: 0.5rem 1rem;">Next →</a>
        <?php endif; ?>
      </nav>
    <?php endif; ?>
  </main>
  <footer class="footer">
    <div class="container footer-inner">
      <div><strong>QuickAnnonce</strong><div class="muted">© <span id="year"></span></div></div>
      <div style="display:flex; gap:12px;"><a href="contact.php">Contact</a></div>
    </div>
  </footer>
  <script>
    document.getElementById('year').textContent = new Date().getFullYear();
    
    // Auto-submit sort select
    const sortSelect = document.getElementById('sort-select');
    if (sortSelect) {
      sortSelect.addEventListener('change', function() {
        const url = new URL(window.location.href);
        url.searchParams.set('sort', this.value);
        url.searchParams.set('page', '1'); // Reset to first page when sorting changes
        window.location.href = url.toString();
      });
    }
  </script>
  <script src="js/main.js"></script>
  <script src="js/mobile-toggle.js"></script>
</body>
</html>