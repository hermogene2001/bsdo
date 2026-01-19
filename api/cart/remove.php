<?php
// API endpoint for removing items from cart
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

if ($product_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Product ID is required']);
    exit;
}

try {
    // Check if cart exists and item is in cart
    if (isset($_SESSION['cart'][$product_id])) {
        // Remove item from cart
        unset($_SESSION['cart'][$product_id]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Item removed from cart successfully',
            'cart_total_items' => count($_SESSION['cart'])
        ]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Item not found in cart']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to remove item from cart']);
}
?>