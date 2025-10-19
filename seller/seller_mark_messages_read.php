<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'seller') {
    echo json_encode(['success' => false]);
    exit;
}

$seller_id = $_SESSION['user_id'];
$inquiry_id = intval($_POST['inquiry_id'] ?? 0);

if ($inquiry_id <= 0) {
    echo json_encode(['success' => false]);
    exit;
}

// Verify inquiry belongs to this seller
$stmt = $pdo->prepare("\n    SELECT i.id\n    FROM inquiries i\n    JOIN products p ON i.product_id = p.id\n    WHERE i.id = ? AND p.seller_id = ?\n    LIMIT 1\n");
$stmt->execute([$inquiry_id, $seller_id]);
if (!$stmt->fetch()) {
    echo json_encode(['success' => false]);
    exit;
}

// Mark user's messages as read for this inquiry
$stmt = $pdo->prepare("\n    UPDATE inquiry_messages\n    SET is_read = 1\n    WHERE inquiry_id = ? AND sender_type = 'user' AND is_read = 0\n");
$stmt->execute([$inquiry_id]);

echo json_encode(['success' => true]);
?>


