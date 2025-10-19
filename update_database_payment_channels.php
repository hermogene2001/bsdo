<?php
// Database update script for payment channels feature
require_once 'config.php';

try {
    // 1. Create payment_channels table
    $sql1 = "
    CREATE TABLE IF NOT EXISTS `payment_channels` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `name` varchar(100) NOT NULL,
      `type` enum('bank','mobile_money','paypal','cryptocurrency','other') NOT NULL,
      `details` text NOT NULL,
      `account_name` varchar(255) DEFAULT NULL,
      `account_number` varchar(100) DEFAULT NULL,
      `bank_name` varchar(255) DEFAULT NULL,
      `branch_name` varchar(255) DEFAULT NULL,
      `country` varchar(100) DEFAULT NULL,
      `is_active` tinyint(1) DEFAULT 1,
      `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
      `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `is_active` (`is_active`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    
    $pdo->exec($sql1);
    echo "1. Created payment_channels table\n";
    
    // 2. Insert sample payment channels
    $sql2 = "
    INSERT IGNORE INTO `payment_channels` (`id`, `name`, `type`, `details`, `account_name`, `account_number`, `bank_name`, `is_active`) VALUES
    (1, 'Bank Transfer - Main Account', 'bank', 'Primary bank account for payments', 'BSDO Sale Ltd', '1234567890', 'Global Bank', 1),
    (2, 'Mobile Money - M-Pesa', 'mobile_money', 'Kenya Mobile Money', 'BSDO Sale Ltd', '0700123456', NULL, 1),
    (3, 'PayPal Account', 'paypal', 'International PayPal account', 'BSDO Sale Ltd', 'payments@bsdosale.com', NULL, 1);
    ";
    
    $pdo->exec($sql2);
    echo "2. Added sample payment channels\n";
    
    // 3. Update products table to add payment channel reference
    try {
        $sql3 = "
        ALTER TABLE `products` 
        ADD COLUMN `payment_channel_id` INT(11) DEFAULT NULL AFTER `status`,
        ADD COLUMN `verification_payment_status` ENUM('pending', 'paid', 'rejected') DEFAULT 'pending' AFTER `payment_channel_id`;
        ";
        
        $pdo->exec($sql3);
        echo "3. Updated products table with payment channel fields\n";
    } catch (Exception $e) {
        echo "3. Payment channel columns may already exist\n";
    }
    
    // 4. Add foreign key constraint
    try {
        $sql4 = "
        ALTER TABLE `products`
        ADD CONSTRAINT `fk_products_payment_channel` 
        FOREIGN KEY (`payment_channel_id`) REFERENCES `payment_channels` (`id`) ON DELETE SET NULL;
        ";
        $pdo->exec($sql4);
        echo "4. Added foreign key constraints\n";
    } catch (Exception $e) {
        echo "4. Foreign key constraint may already exist\n";
    }
    
    // 5. Add indexes for better performance
    try {
        $sql5 = "
        ALTER TABLE `products` 
        ADD INDEX `idx_payment_channel_id` (`payment_channel_id`),
        ADD INDEX `idx_verification_payment_status` (`verification_payment_status`);
        ";
        $pdo->exec($sql5);
        echo "5. Added performance indexes\n";
    } catch (Exception $e) {
        echo "5. Indexes may already exist\n";
    }
    
    echo "Database updated successfully!\n";
    
} catch (Exception $e) {
    echo "Error updating database: " . $e->getMessage() . "\n";
    exit(1);
}
?>