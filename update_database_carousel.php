<?php
require_once 'config.php';

try {
    // Update existing carousel items to use image_path instead of image_url
    $stmt = $pdo->prepare("UPDATE carousel_items SET image_path = REPLACE(image_path, 'uploads/carousel/sample', 'uploads/carousel/sample') WHERE image_path LIKE '%uploads/carousel/sample%'");
    $stmt->execute();
    
    echo "Carousel items updated successfully!\n";
    
    // Check if carousel items exist
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM carousel_items");
    $stmt->execute();
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    echo "Found $count carousel items in the database.\n";
    
    if ($count == 0) {
        // Insert sample data if table is empty
        $sample_items = [
            [
                'title' => 'Welcome to BSDO Sale',
                'description' => 'Your trusted e-commerce platform with live streaming, real-time inquiries, and rental products.',
                'image_path' => 'uploads/carousel/sample1.jpg',
                'link_url' => '#products',
                'sort_order' => 1,
                'is_active' => 1
            ],
            [
                'title' => 'Live Shopping Experience',
                'description' => 'Join live streams and interact with sellers in real-time.',
                'image_path' => 'uploads/carousel/sample2.jpg',
                'link_url' => 'live_streams.php',
                'sort_order' => 2,
                'is_active' => 1
            ],
            [
                'title' => 'Rent Products',
                'description' => 'Find amazing products to rent for short-term use.',
                'image_path' => 'uploads/carousel/sample3.jpg',
                'link_url' => 'products.php?type=rental',
                'sort_order' => 3,
                'is_active' => 1
            ]
        ];
        
        foreach ($sample_items as $item) {
            $stmt = $pdo->prepare("INSERT INTO carousel_items (title, description, image_path, link_url, sort_order, is_active) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $item['title'],
                $item['description'],
                $item['image_path'],
                $item['link_url'],
                $item['sort_order'],
                $item['is_active']
            ]);
        }
        
        echo "Sample carousel items inserted successfully!\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>