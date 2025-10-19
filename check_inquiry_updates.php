<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'client') {
    echo json_encode(['has_new_messages' => false]);
    exit;
}

$inquiry_id = intval($_GET['inquiry_id']);
$user_id = $_SESSION['user_id'];

// Verify the inquiry belongs to the user
$stmt = $pdo->prepare("SELECT id FROM inquiries WHERE id = ? AND user_id = ?");
$stmt->execute([$inquiry_id, $user_id]);
$inquiry = $stmt->fetch();

if (!$inquiry) {
    echo json_encode(['has_new_messages' => false]);
    exit;
}

// Check for new seller messages
$stmt = $pdo->prepare("
    SELECT COUNT(*) as new_count 
    FROM inquiry_messages 
    WHERE inquiry_id = ? AND sender_type = 'seller' AND is_read = 0
");
$stmt->execute([$inquiry_id]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);

echo json_encode(['has_new_messages' => $result['new_count'] > 0]);
?>