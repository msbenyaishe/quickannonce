<?php
/**
 * Diagnostic script to test logging functionality
 * Run this file directly to check if logging works
 */

// Start session
session_start();

// Set a test username
$_SESSION['username'] = 'test_user_' . time();

// Include the log_action function
require_once __DIR__ . '/includes/log_action.php';

echo "<h1>Logging Diagnostic Test</h1>";
echo "<pre>";

// Test 1: Check if function exists
echo "Test 1: Function exists? " . (function_exists('log_action') ? 'YES ✓' : 'NO ✗') . "\n";

// Test 2: Check file path
$file = __DIR__ . '/logs.json';
echo "Test 2: Target file path: " . $file . "\n";
echo "Test 2: File exists? " . (file_exists($file) ? 'YES ✓' : 'NO ✗') . "\n";
echo "Test 2: File writable? " . (is_writable($file) || is_writable(dirname($file)) ? 'YES ✓' : 'NO ✗') . "\n";

// Test 3: Check directory permissions
$dir = dirname($file);
echo "Test 3: Directory: " . $dir . "\n";
echo "Test 3: Directory exists? " . (is_dir($dir) ? 'YES ✓' : 'NO ✗') . "\n";
echo "Test 3: Directory writable? " . (is_writable($dir) ? 'YES ✓' : 'NO ✗') . "\n";

// Test 4: Check session
echo "Test 4: Session username: " . ($_SESSION['username'] ?? 'NOT SET') . "\n";

// Test 5: Try to write a test log
echo "\nTest 5: Attempting to write log...\n";
try {
    log_action("test_action", ["test" => "diagnostic"]);
    echo "Test 5: log_action() called without errors\n";
} catch (Exception $e) {
    echo "Test 5: ERROR - " . $e->getMessage() . "\n";
}

// Test 6: Check if file was written
echo "\nTest 6: Checking logs.json content...\n";
if (file_exists($file)) {
    $content = file_get_contents($file);
    echo "Test 6: File size: " . strlen($content) . " bytes\n";
    if ($content && $content !== '[]' && $content !== '') {
        $logs = json_decode($content, true);
        echo "Test 6: Number of logs: " . (is_array($logs) ? count($logs) : 0) . "\n";
        if (is_array($logs) && count($logs) > 0) {
            echo "Test 6: Last log entry:\n";
            print_r(end($logs));
        }
    } else {
        echo "Test 6: File is empty or contains only '[]'\n";
    }
} else {
    echo "Test 6: File does not exist\n";
}

// Test 7: Check PHP error log location
echo "\nTest 7: PHP error log location: " . ini_get('error_log') . "\n";
echo "Test 7: Last PHP error: " . (error_get_last() ? print_r(error_get_last(), true) : 'None') . "\n";

// Test 8: Check file permissions
if (file_exists($file)) {
    echo "\nTest 8: File permissions: " . substr(sprintf('%o', fileperms($file)), -4) . "\n";
    echo "Test 8: File owner: " . (function_exists('posix_getpwuid') ? posix_getpwuid(fileowner($file))['name'] : 'Unknown') . "\n";
}

echo "\n</pre>";
echo "<p><strong>If all tests pass but logs.json is still empty, check:</strong></p>";
echo "<ul>";
echo "<li>PHP error log: " . ini_get('error_log') . "</li>";
echo "<li>Web server error log</li>";
echo "<li>File permissions on logs.json</li>";
echo "<li>Disk space</li>";
echo "</ul>";
?>

