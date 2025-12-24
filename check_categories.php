<?php
require_once 'config.php';

try {
    // Check if categories table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'categories'");
    $tableExists = $stmt->rowCount() > 0;
    
    if (!$tableExists) {
        echo "Categories table does not exist.\n";
    } else {
        echo "Categories table exists.\n";
        
        // Count categories
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM categories");
        $result = $stmt->fetch();
        $totalCategories = $result['count'];
        echo "Total categories: " . $totalCategories . "\n";
        
        // Get active categories (as used by the RentalProductModel)
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM categories WHERE status = 'active'");
        $result = $stmt->fetch();
        $activeCategories = $result['count'];
        echo "Active categories: " . $activeCategories . "\n";
        
        // Get all categories to see what's available
        $stmt = $pdo->query("SELECT id, name, status FROM categories");
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($categories) > 0) {
            echo "\nAll categories:\n";
            foreach ($categories as $category) {
                echo "- ID: {$category['id']}, Name: {$category['name']}, Status: {$category['status']}\n";
            }
        } else {
            echo "\nNo categories found in the database.\n";
        }
    }
} catch (Exception $e) {
    echo "Error checking categories: " . $e->getMessage() . "\n";
}
?>