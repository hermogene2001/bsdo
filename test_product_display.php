<?php
require_once 'config.php';

try {
    // Retrieve the test product
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([4]); // ID of our test product
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($product) {
        echo "Product Details:\n";
        echo "Name: " . $product['name'] . "\n";
        echo "Price: $" . $product['price'] . "\n";
        echo "Address: " . $product['address'] . "\n";
        echo "City: " . $product['city'] . "\n";
        echo "State: " . $product['state'] . "\n";
        echo "Country: " . $product['country'] . "\n";
        echo "Postal Code: " . $product['postal_code'] . "\n";
        
        if ($product['image_gallery']) {
            $galleryImages = json_decode($product['image_gallery'], true);
            echo "Gallery Images (" . count($galleryImages) . "):\n";
            foreach ($galleryImages as $index => $image) {
                echo "  " . ($index + 1) . ". " . $image . "\n";
            }
        } else {
            echo "No gallery images found\n";
        }
    } else {
        echo "Product not found\n";
    }
    
} catch (Exception $e) {
    echo "Error retrieving product: " . $e->getMessage() . "\n";
}
?>