<?php
require_once 'config.php';

try {
    // Add payment_channel_id column if it doesn't exist
    $stmt = $pdo->prepare("SHOW COLUMNS FROM `products` LIKE 'payment_channel_id'");
    $stmt->execute();
    $columnExists = $stmt->fetch();
    
    if (!$columnExists) {
        $pdo->exec("ALTER TABLE `products` ADD COLUMN `payment_channel_id` INT(11) DEFAULT NULL AFTER `status`");
        echo "Added payment_channel_id column to products table\n";
    } else {
        echo "payment_channel_id column already exists\n";
    }
    
    // Add verification_payment_status column if it doesn't exist
    $stmt = $pdo->prepare("SHOW COLUMNS FROM `products` LIKE 'verification_payment_status'");
    $stmt->execute();
    $columnExists = $stmt->fetch();
    
    if (!$columnExists) {
        $pdo->exec("ALTER TABLE `products` ADD COLUMN `verification_payment_status` ENUM('pending', 'paid', 'rejected') DEFAULT 'pending' AFTER `payment_channel_id`");
        echo "Added verification_payment_status column to products table\n";
    } else {
        echo "verification_payment_status column already exists\n";
    }
    
    // Check if foreign key constraint exists
    $stmt = $pdo->prepare("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = 'bsdo_sale' AND TABLE_NAME = 'products' AND CONSTRAINT_NAME = 'fk_products_payment_channel'");
    $stmt->execute();
    $fkExists = $stmt->fetch();
    
    if (!$fkExists) {
        $pdo->exec("ALTER TABLE `products` ADD CONSTRAINT `fk_products_payment_channel` FOREIGN KEY (`payment_channel_id`) REFERENCES `payment_channels` (`id`) ON DELETE SET NULL");
        echo "Added foreign key constraint for payment_channel_id\n";
    } else {
        echo "Foreign key constraint for payment_channel_id already exists\n";
    }
    
    // Add indexes if they don't exist
    $stmt = $pdo->prepare("SHOW INDEX FROM `products` WHERE Key_name = 'idx_payment_channel_id'");
    $stmt->execute();
    $indexExists = $stmt->fetch();
    
    if (!$indexExists) {
        $pdo->exec("ALTER TABLE `products` ADD INDEX `idx_payment_channel_id` (`payment_channel_id`)");
        echo "Added index idx_payment_channel_id\n";
    } else {
        echo "Index idx_payment_channel_id already exists\n";
    }
    
    $stmt = $pdo->prepare("SHOW INDEX FROM `products` WHERE Key_name = 'idx_verification_payment_status'");
    $stmt->execute();
    $indexExists = $stmt->fetch();
    
    if (!$indexExists) {
        $pdo->exec("ALTER TABLE `products` ADD INDEX `idx_verification_payment_status` (`verification_payment_status`)");
        echo "Added index idx_verification_payment_status\n";
    } else {
        echo "Index idx_verification_payment_status already exists\n";
    }
    
    echo "Database update completed successfully!\n";
    
} catch (Exception $e) {
    echo "Error updating database: " . $e->getMessage() . "\n";
}
?>