<?php
// API endpoint for getting product details
require_once __DIR__ . '/../../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$product_id = $_GET['id'] ?? 0;

if (empty($product_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Product ID is required']);
    exit;
}

try {
    // Get product details
    $stmt = $pdo->prepare("
        SELECT p.*, u.store_name, u.first_name, u.last_name, u.email as seller_email, c.name as category_name 
        FROM products p 
        JOIN users u ON p.seller_id = u.id 
        LEFT JOIN categories c ON p.category_id = c.id 
        WHERE p.id = ? AND p.status = 'active'
    ");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Product not found']);
        exit;
    }

    // Get gallery images
    $gallery_images = [];
    if (!empty($product['image_gallery'])) {
        $gallery_images = json_decode($product['image_gallery'], true);
        if (!is_array($gallery_images)) {
            $gallery_images = [];
        }
    }

    // Get main image
    $imgStmt = $pdo->prepare("SELECT image_path FROM product_images WHERE product_id = ? LIMIT 1");
    $imgStmt->execute([$product['id']]);
    $main_image = $imgStmt->fetch(PDO::FETCH_ASSOC);

    // Format the product data
    $formatted_product = [
        'id' => $product['id'],
        'name' => $product['name'],
        'title' => $product['title'],
        'description' => $product['description'],
        'price' => $product['price'],
        'rental_price_per_day' => $product['rental_price_per_day'] ?? null,
        'rental_price_per_week' => $product['rental_price_per_week'] ?? null,
        'product_type' => $product['product_type'],
        'stock' => $product['stock'],
        'location' => $product['location'],
        'min_rental_days' => $product['min_rental_days'] ?? null,
        'max_rental_days' => $product['max_rental_days'] ?? null,
        'security_deposit' => $product['security_deposit'] ?? null,
        'address' => $product['address'] ?? null,
        'city' => $product['city'] ?? null,
        'state' => $product['state'] ?? null,
        'country' => $product['country'] ?? null,
        'image_url' => $main_image ? $main_image['image_path'] : null,
        'gallery_images' => $gallery_images,
        'seller' => [
            'id' => $product['seller_id'],
            'store_name' => $product['store_name'],
            'first_name' => $product['first_name'],
            'last_name' => $product['last_name'],
            'email' => $product['seller_email']
        ],
        'category' => [
            'id' => $product['category_id'],
            'name' => $product['category_name']
        ],
        'created_at' => $product['created_at'],
        'updated_at' => $product['updated_at']
    ];

    echo json_encode([
        'success' => true,
        'product' => $formatted_product
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred while fetching product details']);
}
?>