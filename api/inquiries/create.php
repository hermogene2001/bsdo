<?php
// API endpoint for creating inquiries
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
$message = trim($input['message'] ?? '');

if ($product_id <= 0 || empty($message)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Product ID and message are required']);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    // Get product and seller information
    $stmt = $pdo->prepare("
        SELECT p.id, p.seller_id, u.store_name
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

    // Check if inquiry already exists for this user and product
    $stmt = $pdo->prepare("
        SELECT id FROM inquiries 
        WHERE product_id = ? AND user_id = ? AND seller_id = ?
    ");
    $stmt->execute([$product_id, $user_id, $product['seller_id']]);
    $existing_inquiry = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing_inquiry) {
        // If inquiry exists, just add a message to it
        $inquiry_id = $existing_inquiry['id'];

        // Add message to existing inquiry
        $stmt = $pdo->prepare("
            INSERT INTO inquiry_messages (inquiry_id, sender_id, sender_type, message) 
            VALUES (?, ?, 'user', ?)
        ");
        $stmt->execute([$inquiry_id, $user_id, $message]);

        // Update inquiry status and timestamp
        $stmt = $pdo->prepare("
            UPDATE inquiries SET status = 'pending', updated_at = CURRENT_TIMESTAMP WHERE id = ?
        ");
        $stmt->execute([$inquiry_id]);
    } else {
        // Create new inquiry
        $stmt = $pdo->prepare("
            INSERT INTO inquiries (product_id, user_id, seller_id, message, status) 
            VALUES (?, ?, ?, ?, 'pending')
        ");
        $stmt->execute([$product_id, $user_id, $product['seller_id'], $message]);
        $inquiry_id = $pdo->lastInsertId();

        // Add the initial message
        $stmt = $pdo->prepare("
            INSERT INTO inquiry_messages (inquiry_id, sender_id, sender_type, message) 
            VALUES (?, ?, 'user', ?)
        ");
        $stmt->execute([$inquiry_id, $user_id, $message]);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Inquiry sent successfully',
        'inquiry_id' => $inquiry_id
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to create inquiry']);
}
?>