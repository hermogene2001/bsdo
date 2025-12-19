<?php
require_once 'config.php';

try {
    // Check if the upload_fee column exists
    $stmt = $pdo->prepare("SHOW COLUMNS FROM products LIKE 'upload_fee'");
    $stmt->execute();
    $result = $stmt->fetch();
    
    if ($result) {
        echo "upload_fee column exists\n";
    } else {
        echo "upload_fee column does not exist\n";
    }
    
    // Check if the upload_fee_paid column exists
    $stmt = $pdo->prepare("SHOW COLUMNS FROM products LIKE 'upload_fee_paid'");
    $stmt->execute();
    $result = $stmt->fetch();
    
    if ($result) {
        echo "upload_fee_paid column exists\n";
    } else {
        echo "upload_fee_paid column does not exist\n";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>