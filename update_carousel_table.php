<?php
require_once 'config.php';

try {
    // Check if the table exists and has the old structure
    $stmt = $pdo->prepare("SHOW COLUMNS FROM carousel_items LIKE 'image_url'");
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        // Rename image_url column to image_path
        $pdo->exec("ALTER TABLE carousel_items CHANGE image_url image_path VARCHAR(500)");
        echo "Renamed image_url column to image_path\n";
    }
    
    // Check if image_path column exists
    $stmt = $pdo->prepare("SHOW COLUMNS FROM carousel_items LIKE 'image_path'");
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        // Add image_path column
        $pdo->exec("ALTER TABLE carousel_items ADD image_path VARCHAR(500) NOT NULL AFTER description");
        echo "Added image_path column\n";
    }
    
    echo "Carousel table updated successfully!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>