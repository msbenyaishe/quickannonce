<?php
// Database configuration and helpers (PDO)
// Works with quickannonces_db on InfinityFree (UTF-8 and exception error mode)

// Harden session cookies and start session
ini_set('session.cookie_httponly', '1');
ini_set(
    'session.cookie_secure',
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? '1' : '0'
);
ini_set('session.cookie_samesite', 'Lax');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Simulate stored procedures/triggers if needed
define('SIMULATE_STORED_OBJECTS', true);

function getPDO(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    // Database connection: prefer environment variables
    $host = getenv('DB_HOST') ?: '';
    $dbname = getenv('DB_NAME') ?: '';
    $user = getenv('DB_USER') ?: '';
    $pass = getenv('DB_PASS') ?: '';

    // Fallback values
    if ($host === '' || $dbname === '' || $user === '' || $pass === '') {
        $host   = $host   ?: 'sql304.infinityfree.com';
        $dbname = $dbname ?: 'if0_40259019_quickannoncedynamic';
        $user   = $user   ?: 'if0_40259019';
        $pass   = $pass   ?: 'SE38GKEM1D8';
    }
    $dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4;port=3306";

    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        return $pdo;
    } catch (PDOException $e) {
        // Clean any accidental output to avoid corrupting JSON/API responses
        if (function_exists('ob_get_level') && ob_get_level()) {
            ob_clean();
        }

        error_log('DB connection failed: ' . $e->getMessage());
        http_response_code(500);
        exit('Service temporarily unavailable');
    }
}

// HTML escaping helper
function h(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// Authentication helpers
function isLoggedIn(): bool {
    return !empty($_SESSION['user_id']) || !empty($_SESSION['admin_id']);
}

function isAdmin(): bool {
    return !empty($_SESSION['is_admin']) &&
           $_SESSION['is_admin'] === true &&
           !empty($_SESSION['admin_id']);
}

function currentUserId(): ?int {
    if (!empty($_SESSION['admin_id'])) {
        return (int)$_SESSION['admin_id'];
    }

    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

// CSRF helpers
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function validate_csrf(?string $token): bool {
    return is_string($token)
        && isset($_SESSION['csrf'])
        && hash_equals($_SESSION['csrf'], $token);
}

// Image path helper
function getImagePath(
    ?string $imagePath,
    string $default = 'https://via.placeholder.com/300x200?text=No+Image'
): string {
    if (empty($imagePath)) {
        return $default;
    }

    // If already "uploads/...":
    if (strpos($imagePath, 'uploads/') === 0) {
        return $imagePath;
    }

    return 'uploads/' . $imagePath;
}

// Archiver (safe to leave unchanged)
function runArchiver(PDO $pdo): void {
    try {
        $pdo->beginTransaction();

        $ids = $pdo->query("
            SELECT id FROM annonces
            WHERE date_publication < (CURRENT_DATE - INTERVAL 30 DAY)
        ")->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));

            $pdo->prepare("
                INSERT INTO annonces_archive
                (id, titre, description, date_publication, etat, image_path, id_utilisateur)
                SELECT id, titre, description, date_publication, etat, image_path, id_utilisateur
                FROM annonces WHERE id IN ($placeholders)
            ")->execute($ids);

            $pdo->prepare("
                DELETE FROM annonces
                WHERE id IN ($placeholders)
            ")->execute($ids);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
    }
}

// Trigger simulation (unchanged)
function simulateAfterInsertAnnonce(PDO $pdo, int $userId): void {
    if (!SIMULATE_STORED_OBJECTS) return;

    $pdo->prepare("
        UPDATE utilisateurs
        SET nb_annonces = COALESCE(nb_annonces, 0) + 1
        WHERE id = ?
    ")->execute([$userId]);
}
?>
