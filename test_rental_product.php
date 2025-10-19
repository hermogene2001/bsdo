<?php
session_start();
require_once 'config.php';

// Simulate a seller login for testing
$_SESSION['user_id'] = 1; // Assuming user ID 1 is a seller
$_SESSION['user_role'] = 'seller';

// Test rental product data
$productData = [
    'name' => 'Test Rental Product with Multiple Images',
    'description' => 'This is a test rental product to verify multiple image upload and address functionality',
    'category_id' => 1,
    'stock' => 5,
    'is_rental' => 1,
    'product_type' => 'rental',
    'rental_price_per_day' => 15.99,
    'rental_price_per_week' => 99.99,
    'rental_price_per_month' => 399.99,
    'min_rental_days' => 1,
    'max_rental_days' => 30,
    'security_deposit' => 50.00,
    'address' => '456 Rental Street',
    'city' => 'Kigali',
    'state' => 'Kigali City',
    'country' => 'Rwanda',
    'postal_code' => '00200'
];

// Insert test rental product
try {
    $stmt = $pdo->prepare("INSERT INTO products (seller_id, name, description, category_id, stock, is_rental, product_type, rental_price_per_day, rental_price_per_week, rental_price_per_month, min_rental_days, max_rental_days, security_deposit, address, city, state, country, postal_code, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')");
    $stmt->execute([
        $_SESSION['user_id'],
        $productData['name'],
        $productData['description'],
        $productData['category_id'],
        $productData['stock'],
        $productData['is_rental'],
        $productData['product_type'],
        $productData['rental_price_per_day'],
        $productData['rental_price_per_week'],
        $productData['rental_price_per_month'],
        $productData['min_rental_days'],
        $productData['max_rental_days'],
        $productData['security_deposit'],
        $productData['address'],
        $productData['city'],
        $productData['state'],
        $productData['country'],
        $productData['postal_code']
    ]);
    
    $productId = $pdo->lastInsertId();
    echo "Test rental product created successfully with ID: $productId\n";
    
    // Test adding gallery images
    $galleryImages = [
        'uploads/products/rental_test_image_1.jpg',
        'uploads/products/rental_test_image_2.jpg',
        'uploads/products/rental_test_image_3.jpg'
    ];
    
    $galleryJson = json_encode($galleryImages);
    $stmt = $pdo->prepare("UPDATE products SET image_gallery = ? WHERE id = ?");
    $stmt->execute([$galleryJson, $productId]);
    
    echo "Gallery images added successfully\n";
    echo "Test completed!\n";
    
} catch (Exception $e) {
    echo "Error creating test rental product: " . $e->getMessage() . "\n";
}
?>