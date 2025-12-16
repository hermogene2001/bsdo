<?php
echo "Error log location: " . ini_get('error_log') . "\n";
echo "Display errors: " . ini_get('display_errors') . "\n";
echo "Log errors: " . ini_get('log_errors') . "\n";

// Check if error log file exists
$error_log = ini_get('error_log');
if ($error_log && file_exists($error_log)) {
    echo "Error log file exists: $error_log\n";
    // Show last 10 lines of error log
    $lines = file($error_log);
    $last_lines = array_slice($lines, -10);
    echo "Last 10 lines of error log:\n";
    foreach ($last_lines as $line) {
        echo $line;
    }
} else {
    echo "Error log file not found or not configured\n";
}
?>