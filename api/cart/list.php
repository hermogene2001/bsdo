<?php
// API endpoint for listing cart items
require_once __DIR__ . '/../../config.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
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

try {
    // Get cart items
    $cart_items = isset($_SESSION['cart']) ? array_values($_SESSION['cart']) : [];
    
    // Calculate total
    $total = 0;
    foreach ($cart_items as $item) {
        $total += $item['price'] * $item['quantity'];
    }
    
    echo json_encode([
        'success' => true,
        'cart_items' => $cart_items,
        'total_items' => count($cart_items),
        'total_amount' => $total
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to retrieve cart items']);
}
?>