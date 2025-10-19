<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'client') {
    echo json_encode(['new_messages' => 0]);
    exit;
}

$user_id = $_SESSION['user_id'];

// Count total unread messages across all inquiries
$stmt = $pdo->prepare("
    SELECT COUNT(*) as new_messages 
    FROM inquiry_messages im 
    JOIN inquiries i ON im.inquiry_id = i.id 
    WHERE i.user_id = ? AND im.sender_type = 'seller' AND im.is_read = 0
");
$stmt->execute([$user_id]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);

echo json_encode(['new_messages' => $result['new_messages']]);
?>