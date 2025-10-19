<?php
require_once 'config.php';

try {
    // Check if account_number column exists
    $stmt = $pdo->prepare("SHOW COLUMNS FROM users LIKE 'account_number'");
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        // Add account_number column
        $pdo->exec("ALTER TABLE users ADD account_number VARCHAR(50) NULL AFTER phone");
        echo "Added account_number column to users table\n";
    } else {
        echo "account_number column already exists\n";
    }
    
    echo "Users table updated successfully!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>