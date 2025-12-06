<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

$stream_id = intval($_GET['stream_id'] ?? 0);

if (!$stream_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Stream ID is required']);
    exit;
}

try {
    // Get active viewer count
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT user_id) as count 
        FROM live_stream_viewers 
        WHERE stream_id = ? AND is_active = 1
    
    ");
    $stmt->execute([$stream_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'count' => intval($result['count'])
    ]);
    
} catch (Exception $e) {
    error_log("Error in get_viewer_count.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error'
    ]);
}
