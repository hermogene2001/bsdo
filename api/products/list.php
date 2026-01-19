<?php
// API endpoint for listing products
require_once __DIR__ . '/../../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get query parameters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 12;
$category = $_GET['category'] ?? '';
$location = $_GET['location'] ?? '';
$min_price = $_GET['min_price'] ?? 0;
$max_price = $_GET['max_price'] ?? 999999999;
$search = $_GET['search'] ?? '';

try {
    // Build query
    $sql = "SELECT p.*, u.store_name, c.name as category_name FROM products p 
            JOIN users u ON p.seller_id = u.id 
            LEFT JOIN categories c ON p.category_id = c.id 
            WHERE p.status = 'active' 
            AND p.price BETWEEN :min_price AND :max_price";
    
    $params = [
        ':min_price' => $min_price,
        ':max_price' => $max_price
    ];

    if (!empty($category)) {
        $sql .= " AND c.name = :category";
        $params[':category'] = $category;
    }
    
    if (!empty($location)) {
        $sql .= " AND p.location LIKE :location";
        $params[':location'] = "%$location%";
    }
    
    if (!empty($search)) {
        $sql .= " AND (p.name LIKE :search OR p.description LIKE :search)";
        $params[':search'] = "%$search%";
    }

    $sql .= " ORDER BY p.created_at DESC";

    // Add pagination
    $offset = ($page - 1) * $limit;
    $sql .= " LIMIT :limit OFFSET :offset";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    // Bind other parameters
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get total count for pagination
    $countSql = "SELECT COUNT(*) as total FROM products p 
                 JOIN users u ON p.seller_id = u.id 
                 LEFT JOIN categories c ON p.category_id = c.id 
                 WHERE p.status = 'active' 
                 AND p.price BETWEEN :min_price AND :max_price";
                 
    if (!empty($category)) {
        $countSql .= " AND c.name = :category";
    }
    
    if (!empty($location)) {
        $countSql .= " AND p.location LIKE :location";
    }
    
    if (!empty($search)) {
        $countSql .= " AND (p.name LIKE :search OR p.description LIKE :search)";
    }
    
    $countStmt = $pdo->prepare($countSql);
    $countStmt->bindValue(':min_price', $min_price);
    $countStmt->bindValue(':max_price', $max_price);
    
    if (!empty($category)) {
        $countStmt->bindValue(':category', $category);
    }
    
    if (!empty($location)) {
        $countStmt->bindValue(':location', "%$location%");
    }
    
    if (!empty($search)) {
        $countStmt->bindValue(':search', "%$search%");
    }
    
    $countStmt->execute();
    $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Get image for each product
    $formatted_products = [];
    foreach ($products as $product) {
        // Get main image
        $imgStmt = $pdo->prepare("SELECT image_path FROM product_images WHERE product_id = ? LIMIT 1");
        $imgStmt->execute([$product['id']]);
        $img = $imgStmt->fetch(PDO::FETCH_ASSOC);
        
        $formatted_products[] = [
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
            'image_url' => $img ? $img['image_path'] : null,
            'seller_store_name' => $product['store_name'],
            'category_name' => $product['category_name'],
            'created_at' => $product['created_at']
        ];
    }

    // Calculate pagination info
    $total_pages = ceil($total / $limit);
    
    echo json_encode([
        'success' => true,
        'products' => $formatted_products,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $total_pages,
            'total_items' => $total,
            'per_page' => $limit
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred while fetching products']);
}
?>