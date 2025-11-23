<?php
// JSON endpoint returning ads filtered by category ID (or other optional filters)
// Uses existing conventions: moderation_status = 'approved', same card fields as filter.php/search.php

include 'config.php';

header('Content-Type: application/json; charset=utf-8');

// Only GET is supported
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    $pdo = getPDO();
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database unavailable']);
    exit;
}

// Input parameters
// Note: current database uses a.categorie as a text field (see filter.php),
// so we primarily filter by category name while keeping the API extensible.
$category   = trim($_GET['category'] ?? '');
$page       = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage    = 20;
$offset     = ($page - 1) * $perPage;

// Optional additional filters, reusing naming from filter.php for future compatibility
$city       = trim($_GET['city'] ?? '');
$minPrice   = isset($_GET['min']) && $_GET['min'] !== '' ? (float)$_GET['min'] : null;
$maxPrice   = isset($_GET['max']) && $_GET['max'] !== '' ? (float)$_GET['max'] : null;
$q          = trim($_GET['q'] ?? '');

// Build WHERE conditions
$where  = ["a.moderation_status = 'approved'"];
$params = [];

// Category by name (aligned with existing filter.php usage)
if ($category !== '') {
    $where[]           = 'a.categorie = :category';
    $params[':category'] = $category;
}

// Optional additional filters (kept minimal to preserve existing logic)
if ($city !== '') {
    $where[]        = 'a.ville = :city';
    $params[':city'] = $city;
}
if ($minPrice !== null && $minPrice > 0) {
    $where[]               = 'a.prix >= :min_price';
    $params[':min_price'] = $minPrice;
}
if ($maxPrice !== null && $maxPrice > 0) {
    $where[]               = 'a.prix <= :max_price';
    $params[':max_price'] = $maxPrice;
}
if ($q !== '') {
    $where[]        = '(a.titre LIKE :q OR a.description LIKE :q)';
    $params[':q'] = "%{$q}%";
}

$whereSql = implode(' AND ', $where);

// Base SELECT (kept aligned with filter.php)
$baseSelect = "
    FROM annonces a
    JOIN utilisateurs u ON a.id_utilisateur = u.id
    WHERE {$whereSql}
";

// Count total results
$countSql = "SELECT COUNT(*) AS total {$baseSelect}";

// Paginated query
$dataSql = "
    SELECT
        a.id,
        a.titre,
        a.description,
        a.date_publication,
        a.etat,
        a.image_path,
        a.prix,
        a.ville,
        a.categorie,
        u.nom AS auteur
    {$baseSelect}
    ORDER BY a.date_publication DESC
    LIMIT :limit OFFSET :offset
";

try {
    // Count
    $countStmt = $pdo->prepare($countSql);
    foreach ($params as $key => $value) {
        $countStmt->bindValue($key, $value);
    }
    $countStmt->execute();
    $totalResults = (int)$countStmt->fetchColumn();

    // Data
    $dataStmt = $pdo->prepare($dataSql);
    foreach ($params as $key => $value) {
        $dataStmt->bindValue($key, $value);
    }
    $dataStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $dataStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $dataStmt->execute();
    $ads = $dataStmt->fetchAll();

    // Normalize data shape for frontend (small, additive)
    $items = [];
    foreach ($ads as $row) {
        // Reuse existing image path helper so cards keep same behavior
        $imageUrl = getImagePath($row['image_path'] ?? null);
        $items[] = [
            'id'          => (int)$row['id'],
            'title'       => $row['titre'],
            'description' => $row['description'],
            'date'        => $row['date_publication'],
            'etat'        => $row['etat'],
            'image_path'  => $row['image_path'],
            'image_url'   => $imageUrl,
            'prix'        => $row['prix'],
            'ville'       => $row['ville'],
            'categorie'   => $row['categorie'],
            'auteur'      => $row['auteur'],
        ];
    }

    echo json_encode([
        'success'      => true,
        'total'        => $totalResults,
        'page'         => $page,
        'per_page'     => $perPage,
        'items'        => $items,
    ]);
} catch (Throwable $e) {
    error_log('api-category-ads error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Unable to fetch ads']);
}


