<?php
// Returns distinct categories from annonces.categorie as JSON.
// This keeps category lists (including the sidebar) in sync with the database.

include 'config.php';

header('Content-Type: application/json; charset=utf-8');

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

try {
    $sql = "
        SELECT TRIM(categorie) AS name, COUNT(*) AS count
        FROM annonces
        WHERE categorie IS NOT NULL AND categorie <> ''
        GROUP BY TRIM(categorie)
        ORDER BY name ASC
    ";
    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll();

    $categories = [];
    foreach ($rows as $row) {
        if ($row['name'] === null || $row['name'] === '') {
            continue;
        }
        $categories[] = [
            'name'  => $row['name'],
            'count' => (int)$row['count'],
        ];
    }

    echo json_encode([
        'success'    => true,
        'categories' => $categories,
    ]);
} catch (Throwable $e) {
    error_log('api-categories error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Unable to fetch categories']);
}


