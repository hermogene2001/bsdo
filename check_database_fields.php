<?php
require_once 'config.php';

try {
    // Retrieve the test rental product
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([7]); // ID of our test rental product
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($product) {
        echo "All Product Fields:\n";
        foreach ($product as $key => $value) {
            echo "$key: " . ($value === null ? "NULL" : $value) . "\n";
        }
    } else {
        echo "Product not found\n";
    }
    
} catch (Exception $e) {
    echo "Error retrieving product: " . $e->getMessage() . "\n";
}
?>