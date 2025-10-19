<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'seller') {
    exit('Unauthorized');
}

$seller_id = $_SESSION['user_id'];
$inquiry_id = intval($_GET['inquiry_id']);

// Ensure inquiry belongs to this seller (via product ownership)
$ownStmt = $pdo->prepare("\n    SELECT i.id\n    FROM inquiries i\n    JOIN products p ON i.product_id = p.id\n    WHERE i.id = ? AND p.seller_id = ?\n    LIMIT 1\n");
$ownStmt->execute([$inquiry_id, $seller_id]);
if (!$ownStmt->fetch()) {
    exit('Inquiry not found');
}

// Fetch messages
$stmt = $pdo->prepare("\n    SELECT im.*, \n           CASE WHEN im.sender_type = 'user' THEN 'user' ELSE 'seller' END as message_type\n    FROM inquiry_messages im\n    WHERE im.inquiry_id = ?\n    ORDER BY im.created_at ASC\n");
$stmt->execute([$inquiry_id]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($messages as $message) {
    $time = date('M j, g:i A', strtotime($message['created_at']));
    $message_class = $message['message_type'] === 'user' ? 'user' : 'seller';
    $sender_name = $message['message_type'] === 'user' ? 'Customer' : 'You';
    echo "\n    <div class='message {$message_class}'>\n        <div class='message-content'>" . htmlspecialchars($message['message']) . "</div>\n        <div class='message-time'>\n            {$sender_name} • {$time}\n        </div>\n    </div>";
}
?>


