<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'client') {
    echo json_encode(['success' => false]);
    exit;
}

$inquiry_id = intval($_POST['inquiry_id']);
$user_id = $_SESSION['user_id'];

// Verify the inquiry belongs to the user
$stmt = $pdo->prepare("SELECT id FROM inquiries WHERE id = ? AND user_id = ?");
$stmt->execute([$inquiry_id, $user_id]);
$inquiry = $stmt->fetch();

if (!$inquiry) {
    echo json_encode(['success' => false]);
    exit;
}

// Mark messages as read
$stmt = $pdo->prepare("
    UPDATE inquiry_messages 
    SET is_read = 1 
    WHERE inquiry_id = ? AND sender_type = 'seller' AND is_read = 0
");
$stmt->execute([$inquiry_id]);

echo json_encode(['success' => true]);
?>