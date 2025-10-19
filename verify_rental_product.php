<?php
require_once 'config.php';

try {
    // Retrieve the test rental product
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([8]); // ID of our new test rental product
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($product) {
        echo "Rental Product Details:\n";
        echo "Name: " . $product['name'] . "\n";
        echo "Type: " . $product['product_type'] . "\n";
        echo "Is Rental: " . $product['is_rental'] . "\n";
        echo "Price Per Day: $" . $product['rental_price_per_day'] . "\n";
        echo "Price Per Week: $" . $product['rental_price_per_week'] . "\n";
        echo "Price Per Month: $" . $product['rental_price_per_month'] . "\n";
        echo "Min Rental Days: " . $product['min_rental_days'] . "\n";
        echo "Max Rental Days: " . $product['max_rental_days'] . "\n";
        echo "Security Deposit: $" . $product['security_deposit'] . "\n";
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
        echo "Rental product not found\n";
    }
    
} catch (Exception $e) {
    echo "Error retrieving rental product: " . $e->getMessage() . "\n";
}
?>