<?php
session_start();
require_once 'config.php';

// Simulate a seller login for testing
$_SESSION['user_id'] = 1; // Assuming user ID 1 is a seller
$_SESSION['user_role'] = 'seller';

// Test product data
$productData = [
    'name' => 'Test Product with Multiple Images',
    'description' => 'This is a test product to verify multiple image upload and address functionality',
    'price' => 29.99,
    'stock' => 10,
    'category_id' => 1,
    'address' => '123 Main Street',
    'city' => 'Kigali',
    'state' => 'Kigali City',
    'country' => 'Rwanda',
    'postal_code' => '00100'
];

// Insert test product
try {
    $stmt = $pdo->prepare("INSERT INTO products (seller_id, name, description, price, stock, category_id, address, city, state, country, postal_code, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')");
    $stmt->execute([
        $_SESSION['user_id'],
        $productData['name'],
        $productData['description'],
        $productData['price'],
        $productData['stock'],
        $productData['category_id'],
        $productData['address'],
        $productData['city'],
        $productData['state'],
        $productData['country'],
        $productData['postal_code']
    ]);
    
    $productId = $pdo->lastInsertId();
    echo "Test product created successfully with ID: $productId\n";
    
    // Test adding gallery images
    $galleryImages = [
        'uploads/products/test_image_1.jpg',
        'uploads/products/test_image_2.jpg',
        'uploads/products/test_image_3.jpg'
    ];
    
    $galleryJson = json_encode($galleryImages);
    $stmt = $pdo->prepare("UPDATE products SET image_gallery = ? WHERE id = ?");
    $stmt->execute([$galleryJson, $productId]);
    
    echo "Gallery images added successfully\n";
    echo "Test completed!\n";
    
} catch (Exception $e) {
    echo "Error creating test product: " . $e->getMessage() . "\n";
}
?>