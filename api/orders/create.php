<?php
// API endpoint for creating orders
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
$payment_method = $input['payment_method'] ?? 'paypal';
$shipping_address = $input['shipping_address'] ?? [];

if ($product_id <= 0 || $quantity <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Valid product ID and quantity are required']);
    exit;
}

if (empty($shipping_address)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Shipping address is required']);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    // Get product information
    $stmt = $pdo->prepare("
        SELECT p.*, u.store_name, u.email as seller_email
        FROM products p
        JOIN users u ON p.seller_id = u.id
        WHERE p.id = ? AND p.status = 'active'
    ");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Product not found']);
        exit;
    }

    // Check stock availability
    if ($product['stock'] < $quantity) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Insufficient stock available']);
        exit;
    }

    // Calculate total amount
    $unit_price = $product['product_type'] === 'rental' ? $product['rental_price_per_day'] : $product['price'];
    $total_amount = $unit_price * $quantity;

    // Begin transaction
    $pdo->beginTransaction();

    // Create order
    $stmt = $pdo->prepare("
        INSERT INTO orders (
            user_id, 
            product_id, 
            quantity, 
            unit_price, 
            total_amount, 
            shipping_address, 
            payment_method, 
            status, 
            seller_id
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?)
    ");

    $stmt->execute([
        $user_id,
        $product_id,
        $quantity,
        $unit_price,
        $total_amount,
        json_encode($shipping_address),
        $payment_method,
        $product['seller_id']
    ]);

    $order_id = $pdo->lastInsertId();

    // Update product stock
    $stmt = $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
    $stmt->execute([$quantity, $product_id]);

    // Commit transaction
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Order created successfully',
        'order' => [
            'id' => $order_id,
            'product_id' => $product_id,
            'quantity' => $quantity,
            'unit_price' => $unit_price,
            'total_amount' => $total_amount,
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s')
        ]
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    $pdo->rollback();
    
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to create order']);
}
?>