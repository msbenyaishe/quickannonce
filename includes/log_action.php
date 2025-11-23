<?php 
function log_action(string $action, array $details = []): void { 
    if (session_status() === PHP_SESSION_NONE) session_start(); 

    $log = [ 
        "user" => $_SESSION['username'] ?? "visiteur", 
        "action" => $action, 
        "details" => $details, 
        "timestamp" => date('c') 
    ]; 

    $file = __DIR__ . '/../logs.json'; 
    
    // Ensure logs is always an array
    $logs = [];
    if (file_exists($file)) {
        $content = file_get_contents($file);
        if ($content !== false && $content !== '') {
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
        @mkdir($dir, 0755, true);
    }
    
    // Write to file
    $result = @file_put_contents(
        $file,
        $json,
        LOCK_EX
    );
    
    if ($result === false) {
        // Log error with more details
        $error = error_get_last();
        error_log("Failed to write to logs.json. File: " . $file . " | Action: " . $action . " | Error: " . ($error ? $error['message'] : 'Unknown'));
        
        // Try alternative: write to error log for debugging
        error_log("Log data that failed to write: " . substr($json, 0, 200));
    } else {
        // Success - optionally log for debugging
        // error_log("Successfully wrote log: " . $action);
    }
}
?>

