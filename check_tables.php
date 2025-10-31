<?php
require_once 'config.php';

try {
    // Get all tables
    $stmt = $pdo->query("SHOW TABLES");
    echo "Database Tables:\n";
    while ($row = $stmt->fetch()) {
        echo "- " . $row[0] . "\n";
    }
    
    // Check if inquiries table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'inquiries'");
    if ($stmt->rowCount() > 0) {
        echo "\nInquiries table structure:\n";
        $stmt = $pdo->query("DESCRIBE inquiries");
        while ($row = $stmt->fetch()) {
            echo "- " . $row['Field'] . " (" . $row['Type'] . ")\n";
        }
    } else {
        echo "\nInquiries table not found.\n";
    }
    
    // Check if inquiry_messages table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'inquiry_messages'");
    if ($stmt->rowCount() > 0) {
        echo "\nInquiry Messages table structure:\n";
        $stmt = $pdo->query("DESCRIBE inquiry_messages");
        while ($row = $stmt->fetch()) {
            echo "- " . $row['Field'] . " (" . $row['Type'] . ")\n";
        }
    } else {
        echo "\nInquiry Messages table not found.\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>