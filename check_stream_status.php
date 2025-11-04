<?php
require_once 'config.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$stream_id = isset($_GET['stream_id']) ? intval($_GET['stream_id']) : 0;

if (!$stream_id) {
    echo json_encode(['error' => 'Invalid stream ID']);
    exit;
}

try {
    // Check if stream is live and get connection details
    $stmt = $pdo->prepare("
        SELECT ls.*, u.id as seller_id 
        FROM live_streams ls
        JOIN users u ON ls.seller_id = u.id
        WHERE ls.id = ? AND ls.is_live = 1
    ");
    $stmt->execute([$stream_id]);
    $stream = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$stream) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Stream is not live',
            'is_live' => false
        ]);
        exit;
    }

    // Check if seller is connected
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as active_connections
        FROM webrtc_messages
        WHERE room_id LIKE ? 
        AND sender_id = ?
        AND message_type = 'offer'
        AND created_at >= DATE_SUB(NOW(), INTERVAL 30 SECOND)
    ");
    $stmt->execute([
        'room_' . $stream_id . '%',
        $stream['seller_id']
    ]);
    $result = $stmt->fetch();

    echo json_encode([
        'status' => 'success',
        'is_live' => true,
        'has_seller' => $result['active_connections'] > 0,
        'connection_status' => $stream['connection_status'],
        'viewer_count' => $stream['current_viewers']
    ]);

} catch (Exception $e) {
    error_log("Stream check error: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'Internal server error'
    ]);
}