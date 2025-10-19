<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'client') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$inquiry_id = intval($_POST['inquiry_id']);
$message = trim($_POST['message']);
$user_id = $_SESSION['user_id'];

if (empty($message)) {
    echo json_encode(['success' => false, 'message' => 'Message cannot be empty']);
    exit;
}

// Verify the inquiry belongs to the user
$stmt = $pdo->prepare("SELECT id FROM inquiries WHERE id = ? AND user_id = ?");
$stmt->execute([$inquiry_id, $user_id]);
$inquiry = $stmt->fetch();

if (!$inquiry) {
    echo json_encode(['success' => false, 'message' => 'Inquiry not found']);
    exit;
}

// Insert message
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

echo json_encode(['success' => true]);
?>