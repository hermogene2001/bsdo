<?php
// Monitor and manage live stream connections
session_start();
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$action = $_POST['action'] ?? '';
$stream_id = intval($_POST['stream_id'] ?? 0);
$user_id = $_SESSION['user_id'];

try {
    switch ($action) {
        case 'heartbeat':
            // Update last activity time
            if (isset($_SESSION['webrtc_room'])) {
                $_SESSION['webrtc_room']['last_activity'] = time();
                
                // Update stream connection status
                $stmt = $pdo->prepare("
                    UPDATE live_streams 
                    SET connection_status = 'connected' 
                    WHERE id = ? AND (seller_id = ? OR id IN (
                        SELECT stream_id FROM live_stream_viewers 
                        WHERE user_id = ? AND is_active = 1
                    ))
                ");
                $stmt->execute([$stream_id, $user_id, $user_id]);
            }
            break;
            
        case 'check_connection':
            // Check if stream is still active
            $stmt = $pdo->prepare("
                SELECT is_live, connection_status, seller_id 
                FROM live_streams 
                WHERE id = ?
            ");
            $stmt->execute([$stream_id]);
            $stream = $stmt->fetch();
            
            if (!$stream) {
                echo json_encode(['error' => 'Stream not found']);
                exit;
            }
            
            // Check for inactive streams
            if ($stream['is_live'] && $stream['connection_status'] === 'connected') {
                $threshold = time() - 30; // 30 seconds threshold
                
                if (isset($_SESSION['webrtc_room']) && 
                    $_SESSION['webrtc_room']['last_activity'] < $threshold) {
                    // Mark as reconnecting
                    $stmt = $pdo->prepare("
                        UPDATE live_streams 
                        SET connection_status = 'reconnecting' 
                        WHERE id = ?
                    ");
                    $stmt->execute([$stream_id]);
                    
                    $stream['connection_status'] = 'reconnecting';
                }
            }
            
            echo json_encode([
                'success' => true,
                'is_live' => (bool)$stream['is_live'],
                'connection_status' => $stream['connection_status']
            ]);
            break;
    }
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    error_log("Stream connection error: " . $e->getMessage());
    echo json_encode(['error' => 'Internal server error']);
}