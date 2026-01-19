<?php
// API endpoint for adding items to cart
require_once __DIR__ . '/../../config.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Check if user is authenticated as a client
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'client') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized - clients only']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$product_id = intval($input['product_id'] ?? 0);
$quantity = intval($input['quantity'] ?? 1);

if ($product_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Product ID is required']);
    exit;
}

if ($quantity <= 0) {
    $quantity = 1; // Default to 1 if invalid quantity provided
}

try {
    // Get product information
    $stmt = $pdo->prepare("SELECT id, name, price, stock FROM products WHERE id = ? AND status = 'active'");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Product not found']);
        exit;
    }

    // Check if there's enough stock
    if ($product['stock'] < $quantity) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Not enough stock available']);
        exit;
    }

    // Initialize cart if it doesn't exist
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    // Add or update item in cart
    if (isset($_SESSION['cart'][$product_id])) {
        $new_quantity = $_SESSION['cart'][$product_id]['quantity'] + $quantity;
        
        // Check if new quantity exceeds stock
        if ($new_quantity > $product['stock']) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Cannot add more items than available in stock']);
            exit;
        }
        
        $_SESSION['cart'][$product_id]['quantity'] = $new_quantity;
    } else {
        $_SESSION['cart'][$product_id] = [
            'id' => $product['id'],
            'name' => $product['name'],
            'price' => $product['price'],
            'quantity' => $quantity
        ];
    }

    echo json_encode([
        'success' => true,
        'message' => 'Item added to cart successfully',
        'cart_item' => $_SESSION['cart'][$product_id],
        'cart_total_items' => count($_SESSION['cart'])
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to add item to cart']);
}
?>