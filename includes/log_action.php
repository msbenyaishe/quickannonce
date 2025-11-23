<?php 
function log_action(string $action, array $details = []): void { 
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start(); 
    }

    // Get username from session - try multiple possible keys
    $username = $_SESSION['username'] ?? $_SESSION['email'] ?? $_SESSION['user_id'] ?? "visiteur";
    
    // If username is numeric (user_id), prefix it
    if (is_numeric($username)) {
        $username = "user_" . $username;
    }

    $log = [ 
        "user" => $username, 
        "action" => $action, 
        "details" => $details, 
        "timestamp" => date('c') 
    ]; 

    // Use absolute path to avoid issues
    $file = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'logs.json';
    // Normalize path separators for Windows
    $file = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $file);
    
    // Ensure logs is always an array
    $logs = [];
    if (file_exists($file)) {
        $content = @file_get_contents($file);
        if ($content !== false && $content !== '' && trim($content) !== '[]') {
            $decoded = json_decode($content, true);
            if (is_array($decoded)) {
                $logs = $decoded;
            }
        }
    }

    $logs[] = $log; 

    // Write to file with error handling
    $json = json_encode($logs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
    // Try to create directory if it doesn't exist
    $dir = dirname($file);
    if (!is_dir($dir)) {
        if (!@mkdir($dir, 0755, true)) {
            error_log("Failed to create directory: " . $dir);
            return; // Exit early if directory creation fails
        }
    }
    
    // Check if directory is writable
    if (!is_writable($dir)) {
        error_log("Directory is not writable: " . $dir);
        return; // Exit early if directory is not writable
    }
    
    // Write to file (remove @ to see errors in development)
    $result = file_put_contents(
        $file,
        $json,
        LOCK_EX
    );
    
    if ($result === false) {
        // Log error with more details
        $error = error_get_last();
        $errorMsg = $error ? $error['message'] : 'Unknown error';
        error_log("Failed to write to logs.json. File: " . $file . " | Action: " . $action . " | Error: " . $errorMsg);
        error_log("Directory writable: " . (is_writable($dir) ? 'YES' : 'NO'));
        error_log("File exists: " . (file_exists($file) ? 'YES' : 'NO'));
        if (file_exists($file)) {
            error_log("File writable: " . (is_writable($file) ? 'YES' : 'NO'));
        }
        error_log("Log data that failed to write: " . substr($json, 0, 200));
    }
}
?>

