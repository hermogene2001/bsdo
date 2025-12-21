<?php
session_start();
require_once '../config.php';

// Enable error reporting for debugging
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Check if seller_id is provided
if (!isset($_GET['seller_id']) || !is_numeric($_GET['seller_id'])) {
    echo json_encode(['error' => 'Invalid seller ID provided']);
    exit();
}

$seller_id = intval($_GET['seller_id']);

try {
    // Get invited users for this seller
    $stmt = $pdo->prepare("
        SELECT r.*, u.first_name, u.last_name, u.email, u.role, u.created_at as user_created_at
        FROM referrals r
        JOIN users u ON r.invitee_id = u.id
        WHERE r.inviter_id = ?
        ORDER BY r.created_at DESC
    ");
    $stmt->execute([$seller_id]);
    $invited_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format the dates
    foreach ($invited_users as &$user) {
        $user['user_created_at'] = date('M j, Y g:i A', strtotime($user['user_created_at']));
        // Ensure numeric values are properly formatted
        $user['reward_to_inviter'] = floatval($user['reward_to_inviter']);
        $user['reward_to_invitee'] = floatval($user['reward_to_invitee']);
    }
    
    echo json_encode($invited_users);
} catch (Exception $e) {
    // Log the actual error for debugging
    error_log("Error in get_invited_users.php: " . $e->getMessage());
    echo json_encode(['error' => 'Database error occurred. Please check server logs for details.']);
}