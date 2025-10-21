<?php
require_once 'includes/db.php';

try {
    // Create seller_codes table
    $sql = "
    CREATE TABLE IF NOT EXISTS `seller_codes` (
        `id` INT PRIMARY KEY AUTO_INCREMENT,
        `seller_id` INT NOT NULL,
        `seller_code` VARCHAR(255) NOT NULL UNIQUE,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX `idx_seller_id` (`seller_id`),
        INDEX `idx_seller_code` (`seller_code`),
        CONSTRAINT `fk_seller_codes_user` FOREIGN KEY (`seller_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    
    $pdo->exec($sql);
    echo "Seller codes table created successfully!\n";
    
    // Check if there are existing sellers without codes and create codes for them
    $stmt = $pdo->prepare("SELECT id FROM users WHERE role = 'seller' AND id NOT IN (SELECT seller_id FROM seller_codes)");
    $stmt->execute();
    $sellers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($sellers as $seller) {
        $seller_id = $seller['id'];
        $seller_code = 'SELLER' . str_pad($seller_id, 6, '0', STR_PAD_LEFT);
        
        $insertStmt = $pdo->prepare("INSERT INTO seller_codes (seller_id, seller_code) VALUES (?, ?)");
        $insertStmt->execute([$seller_id, $seller_code]);
        echo "Created seller code $seller_code for seller ID $seller_id\n";
    }
    
    echo "All done!\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>