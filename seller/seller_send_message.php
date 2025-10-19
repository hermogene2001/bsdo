<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'seller') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$seller_id = $_SESSION['user_id'];
$inquiry_id = intval($_POST['inquiry_id'] ?? 0);
$message = trim($_POST['message'] ?? '');

if ($inquiry_id <= 0 || $message === '') {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

// Verify inquiry belongs to this seller via product ownership
$stmt = $pdo->prepare("\n    SELECT i.id\n    FROM inquiries i\n    JOIN products p ON i.product_id = p.id\n    WHERE i.id = ? AND p.seller_id = ?\n    LIMIT 1\n");
$stmt->execute([$inquiry_id, $seller_id]);
if (!$stmt->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Inquiry not found']);
    exit;
}

// Insert message as seller
$stmt = $pdo->prepare("\n    INSERT INTO inquiry_messages (inquiry_id, sender_id, sender_type, message, is_read)\n    VALUES (?, ?, 'seller', ?, 0)\n");
$stmt->execute([$inquiry_id, $seller_id, $message]);

// Update inquiry status and timestamp
$stmt = $pdo->prepare("UPDATE inquiries SET status = 'replied', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
$stmt->execute([$inquiry_id]);

echo json_encode(['success' => true]);
?>


