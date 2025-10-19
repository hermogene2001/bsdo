<?php
require_once 'config.php';

try {
    // Check which columns already exist
    $columns = [];
    $stmt = $pdo->prepare("SHOW COLUMNS FROM products");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $columns[] = $row['Field'];
    }
    
    // Add new columns to products table if they don't exist
    $newColumns = [
        'address' => "ADD COLUMN `address` TEXT DEFAULT NULL",
        'city' => "ADD COLUMN `city` VARCHAR(100) DEFAULT NULL",
        'state' => "ADD COLUMN `state` VARCHAR(100) DEFAULT NULL",
        'country' => "ADD COLUMN `country` VARCHAR(100) DEFAULT NULL",
        'postal_code' => "ADD COLUMN `postal_code` VARCHAR(20) DEFAULT NULL",
        'image_gallery' => "ADD COLUMN `image_gallery` JSON DEFAULT NULL"
    ];
    
    $columnsToAdd = [];
    foreach ($newColumns as $columnName => $columnDefinition) {
        if (!in_array($columnName, $columns)) {
            $columnsToAdd[] = $columnDefinition;
        } else {
            echo "Column '$columnName' already exists\n";
        }
    }
    
    if (!empty($columnsToAdd)) {
        $sql = "ALTER TABLE `products` " . implode(', ', $columnsToAdd);
        $pdo->exec($sql);
        echo "Successfully added new columns to products table\n";
    } else {
        echo "All columns already exist\n";
    }
    
    // Check if indexes exist
    $indexes = [];
    $stmt = $pdo->prepare("SHOW INDEX FROM products");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $indexes[] = $row['Key_name'];
    }
    
    // Add indexes for better performance
    $newIndexes = [
        'idx_address' => "ALTER TABLE `products` ADD INDEX `idx_address` (`address`(255))",
        'idx_city' => "ALTER TABLE `products` ADD INDEX `idx_city` (`city`)",
        'idx_country' => "ALTER TABLE `products` ADD INDEX `idx_country` (`country`)"
    ];
    
    foreach ($newIndexes as $indexName => $indexSql) {
        if (!in_array($indexName, $indexes)) {
            $pdo->exec($indexSql);
            echo "Successfully added index '$indexName'\n";
        } else {
            echo "Index '$indexName' already exists\n";
        }
    }
    
    echo "Database update completed successfully!\n";
    
} catch (Exception $e) {
    echo "Error updating database: " . $e->getMessage() . "\n";
}
?>