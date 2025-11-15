<?php
// Check what's missing from the index.php file

// Read both files
$current = file_get_contents('index.php');
$backup = file_get_contents('index_backup.php');

// Check if modals exist in backup
if (strpos($backup, 'loginModal') !== false) {
    echo "Login modal found in backup\n";
} else {
    echo "Login modal NOT found in backup\n";
}

if (strpos($backup, 'registerModal') !== false) {
    echo "Register modal found in backup\n";
} else {
    echo "Register modal NOT FOUND in backup\n";
}

// Check if modals exist in current file
if (strpos($current, 'loginModal') !== false) {
    echo "Login modal found in current file\n";
} else {
    echo "Login modal NOT found in current file\n";
}

if (strpos($current, 'registerModal') !== false) {
    echo "Register modal found in current file\n";
} else {
    echo "Register modal NOT found in current file\n";
}

// Find the position of the closing </body> tag in current file
$bodyPos = strrpos($current, '</body>');
if ($bodyPos !== false) {
    echo "Found closing body tag at position: $bodyPos\n";
    
    // Extract the end of the backup file to see what's missing
    $backupEnd = substr($backup, -2000); // Get last 2000 characters
    echo "Last 1000 characters of backup:\n";
    echo substr($backupEnd, -1000);
} else {
    echo "Could not find closing body tag\n";
}
?>