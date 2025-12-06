<?php
session_start();
require_once 'config.php';

// Get JSON input
$data = json_decode(file_get_contents('php://input'), true);

// If not JSON, try POST data
if (json_last_error() !== JSON_ERROR_NONE) {
    $data = $_POST;
}

if (!isset($data['action'], $data['stream_id'], $data['user_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing parameters']);
    exit;
}

$action = $data['action'];
$stream_id = intval($data['stream_id']);
$user_id = intval($data['user_id']);
$session_id = session_id();
$ip_address = $_SERVER['REMOTE_ADDR'];

try {
    if ($action === 'join') {
        // Mark any existing sessions as inactive
        $stmt = $pdo->prepare("
            UPDATE live_stream_viewers 
            SET is_active = 0, left_at = NOW() 
            WHERE (user_id = ? OR session_id = ?) 
            AND stream_id = ? 
            AND is_active = 1
        ");
        $stmt->execute([$user_id, $session_id, $stream_id]);
        
        // Add new viewer
        $stmt = $pdo->prepare("
            INSERT INTO live_stream_viewers 
            (stream_id, user_id, session_id, ip_address, joined_at, is_active) 
            VALUES (?, ?, ?, ?, NOW(), 1)
            ON DUPLICATE KEY UPDATE 
                is_active = 1,
                joined_at = NOW(),
                left_at = NULL
        ");
        $stmt->execute([$stream_id, $user_id, $session_id, $ip_address]);
        
    } elseif ($action === 'leave') {
        // Mark as inactive
        $stmt = $pdo->prepare("
            UPDATE live_stream_viewers 
            SET is_active = 0, left_at = NOW() 
            WHERE (user_id = ? OR session_id = ?) 
            AND stream_id = ? 
            AND is_active = 1
        ");
        $stmt->execute([$user_id, $session_id, $stream_id]);
    }
    
    // Update viewer count in the live_streams table
    $stmt = $pdo->prepare("
        UPDATE live_streams 
        SET viewer_count = (
            SELECT COUNT(DISTINCT user_id) 
            FROM live_stream_viewers 
            WHERE stream_id = ? AND is_active = 1
        ) 
        WHERE id = ?
    ");
    $stmt->execute([$stream_id, $stream_id]);
    
    // Get updated viewer count
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT user_id) as count 
        FROM live_stream_viewers 
        WHERE stream_id = ? AND is_active = 1
    ");
    $stmt->execute([$stream_id]);
    $viewer_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    echo json_encode(['success' => true, 'count' => $viewer_count]);
    
} catch (Exception $e) {
    error_log("Error in update_viewer_status.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error']);
}
