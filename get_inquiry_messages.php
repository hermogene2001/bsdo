<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'client') {
    exit('Unauthorized');
}

$inquiry_id = intval($_GET['inquiry_id']);
$user_id = $_SESSION['user_id'];

// Verify the inquiry belongs to the user
$stmt = $pdo->prepare("SELECT id FROM inquiries WHERE id = ? AND user_id = ?");
$stmt->execute([$inquiry_id, $user_id]);
$inquiry = $stmt->fetch();

if (!$inquiry) {
    exit('Inquiry not found');
}

// Get messages
$stmt = $pdo->prepare("
    SELECT im.*, u.store_name as sender_name, 
           CASE WHEN im.sender_type = 'user' THEN 'user' ELSE 'seller' END as message_type
    FROM inquiry_messages im 
    LEFT JOIN users u ON im.sender_id = u.id 
    WHERE im.inquiry_id = ? 
    ORDER BY im.created_at ASC
");
$stmt->execute([$inquiry_id]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($messages as $message) {
    $time = date('M j, g:i A', strtotime($message['created_at']));
    $message_class = $message['message_type'] === 'user' ? 'user' : 'seller';
    $sender_name = $message['message_type'] === 'user' ? 'You' : htmlspecialchars($message['sender_name']);
    
    echo "
    <div class='message {$message_class}'>
        <div class='message-content'>{$message['message']}</div>
        <div class='message-time'>
            {$sender_name} • {$time}
        </div>
    </div>";
}
?>