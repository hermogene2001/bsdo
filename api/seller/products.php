<?php
// API endpoint for seller product management
require_once __DIR__ . '/../../config.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'PUT' && $_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Check if user is authenticated as a seller
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'seller') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized - sellers only']);
    exit;
}

$seller_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get seller's products
    try {
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 12;
        $status = $_GET['status'] ?? 'all'; // 'active', 'inactive', 'all'
        
        $sql = "SELECT p.*, c.name as category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.seller_id = ?";
        $params = [$seller_id];
        
        if ($status !== 'all') {
            $sql .= " AND p.status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY p.created_at DESC";
        
        $offset = ($page - 1) * $limit;
        $sql .= " LIMIT :limit OFFSET :offset";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        
        // Bind other params
        for ($i = 0; $i < count($params); $i++) {
            $stmt->bindValue($i + 1, $params[$i]);
        }
        
        $stmt->execute();
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Count total products
        $count_sql = "SELECT COUNT(*) as total FROM products p WHERE p.seller_id = ?";
        $count_params = [$seller_id];
        
        if ($status !== 'all') {
            $count_sql .= " AND p.status = ?";
            $count_params[] = $status;
        }
        
        $count_stmt = $pdo->prepare($count_sql);
        $count_stmt->execute($count_params);
        $total = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        $total_pages = ceil($total / $limit);
        
        echo json_encode([
            'success' => true,
            'products' => $products,
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
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Create new product
    $input = json_decode(file_get_contents('php://input'), true);
    
    $required_fields = ['name', 'description', 'price', 'stock'];
    foreach ($required_fields as $field) {
        if (!isset($input[$field]) || empty($input[$field])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => "$field is required"]);
            exit;
        }
    }
    
    $name = trim($input['name']);
    $description = trim($input['description']);
    $price = floatval($input['price']);
    $stock = intval($input['stock']);
    $product_type = $input['product_type'] ?? 'regular';
    $category_id = intval($input['category_id'] ?? 0);
    $location = $input['location'] ?? '';
    
    // Rental-specific fields
    $rental_price_per_day = $input['rental_price_per_day'] ?? null;
    $rental_price_per_week = $input['rental_price_per_week'] ?? null;
    $min_rental_days = $input['min_rental_days'] ?? null;
    $max_rental_days = $input['max_rental_days'] ?? null;
    $security_deposit = $input['security_deposit'] ?? null;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO products (
                name, description, price, stock, product_type, category_id, 
                location, rental_price_per_day, rental_price_per_week, 
                min_rental_days, max_rental_days, security_deposit, 
                seller_id, status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())
        ");
        
        $result = $stmt->execute([
            $name, $description, $price, $stock, $product_type, $category_id,
            $location, $rental_price_per_day, $rental_price_per_week,
            $min_rental_days, $max_rental_days, $security_deposit,
            $seller_id
        ]);
        
        if ($result) {
            $product_id = $pdo->lastInsertId();
            
            echo json_encode([
                'success' => true,
                'message' => 'Product created successfully',
                'product_id' => $product_id
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to create product']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'An error occurred while creating product']);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    // Update product
    $input = json_decode(file_get_contents('php://input'), true);
    
    $product_id = intval($input['id'] ?? 0);
    if ($product_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Product ID is required']);
        exit;
    }
    
    // Verify product belongs to seller
    $verify_stmt = $pdo->prepare("SELECT id FROM products WHERE id = ? AND seller_id = ?");
    $verify_stmt->execute([$product_id, $seller_id]);
    if ($verify_stmt->rowCount() === 0) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Unauthorized - you do not own this product']);
        exit;
    }
    
    // Prepare update fields
    $updates = [];
    $params = [];
    
    if (isset($input['name'])) {
        $updates[] = "name = ?";
        $params[] = trim($input['name']);
    }
    
    if (isset($input['description'])) {
        $updates[] = "description = ?";
        $params[] = trim($input['description']);
    }
    
    if (isset($input['price'])) {
        $updates[] = "price = ?";
        $params[] = floatval($input['price']);
    }
    
    if (isset($input['stock'])) {
        $updates[] = "stock = ?";
        $params[] = intval($input['stock']);
    }
    
    if (isset($input['status'])) {
        $updates[] = "status = ?";
        $params[] = $input['status'];
    }
    
    if (isset($input['category_id'])) {
        $updates[] = "category_id = ?";
        $params[] = intval($input['category_id']);
    }
    
    if (isset($input['location'])) {
        $updates[] = "location = ?";
        $params[] = $input['location'];
    }
    
    if (isset($input['rental_price_per_day'])) {
        $updates[] = "rental_price_per_day = ?";
        $params[] = floatval($input['rental_price_per_day']);
    }
    
    if (isset($input['rental_price_per_week'])) {
        $updates[] = "rental_price_per_week = ?";
        $params[] = floatval($input['rental_price_per_week']);
    }
    
    if (isset($input['min_rental_days'])) {
        $updates[] = "min_rental_days = ?";
        $params[] = intval($input['min_rental_days']);
    }
    
    if (isset($input['max_rental_days'])) {
        $updates[] = "max_rental_days = ?";
        $params[] = intval($input['max_rental_days']);
    }
    
    if (isset($input['security_deposit'])) {
        $updates[] = "security_deposit = ?";
        $params[] = floatval($input['security_deposit']);
    }
    
    if (empty($updates)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No fields to update']);
        exit;
    }
    
    $params[] = $product_id;
    $params[] = $seller_id;
    
    try {
        $sql = "UPDATE products SET " . implode(", ", $updates) . " WHERE id = ? AND seller_id = ?";
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute($params);
        
        if ($result) {
            echo json_encode([
                'success' => true,
                'message' => 'Product updated successfully'
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to update product']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'An error occurred while updating product']);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    // Delete product
    $product_id = intval($_GET['id'] ?? 0);
    if ($product_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Product ID is required']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ? AND seller_id = ?");
        $result = $stmt->execute([$product_id, $seller_id]);
        
        if ($result && $stmt->rowCount() > 0) {
            echo json_encode([
                'success' => true,
                'message' => 'Product deleted successfully'
            ]);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Product not found or unauthorized']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'An error occurred while deleting product']);
    }
}
?>