<?php
// WebRTC signaling server implementation
session_start();
require_once 'config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/webrtc_error.log');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'create_room':
            $stream_id = intval($_POST['stream_id'] ?? 0);
            
            // Verify stream exists and is live
            $stmt = $pdo->prepare("SELECT id, seller_id FROM live_streams WHERE id = ? AND is_live = 1");
            $stmt->execute([$stream_id]);
            $stream = $stmt->fetch();
            
            if (!$stream) {
                echo json_encode(['error' => 'Stream not found or not live']);
                exit;
            }
            
            // Clean up any existing room for this stream
            $stmt = $pdo->prepare("DELETE FROM webrtc_messages WHERE room_id LIKE ?");
            $stmt->execute(['room_' . $stream_id . '_%']);
            
            // Create new room
            $room_id = 'room_' . $stream_id . '_' . time();
            
            // Store room info in session for this user
            $_SESSION['webrtc_room'] = [
                'room_id' => $room_id,
                'stream_id' => $stream_id,
                'created_at' => time(),
                'last_activity' => time()
            ];
            
            // Update stream connection status
            $stmt = $pdo->prepare("UPDATE live_streams SET connection_status = 'connected' WHERE id = ?");
            $stmt->execute([$stream_id]);
            
            echo json_encode([
                'success' => true,
                'room_id' => $room_id,
                'stream_id' => $stream_id,
                'stun_servers' => [
                    'urls' => [
                        'stun:stun1.l.google.com:19302',
                        'stun:stun2.l.google.com:19302'
                    ]
                ]
            ]);
            break;
            
        case 'join_room':
            $room_id = $_POST['room_id'] ?? '';
            
            // In a real implementation, we would verify the room exists
            // For this demo, we'll just store it in session
            $_SESSION['webrtc_room'] = [
                'room_id' => $room_id,
                'stream_id' => intval(substr($room_id, 5, strpos($room_id, '_', 5) - 5)),
                'joined_at' => time()
            ];
            
            echo json_encode([
                'success' => true,
                'room_id' => $room_id
            ]);
            break;
            
        case 'send_offer':
            $room_id = $_POST['room_id'] ?? '';
            $offer = $_POST['offer'] ?? '';
            
            // Store offer in database
            $stmt = $pdo->prepare("
                INSERT INTO webrtc_messages (room_id, sender_id, message_type, message_data, created_at) 
                VALUES (?, ?, 'offer', ?, NOW())
            ");
            $stmt->execute([$room_id, $user_id, $offer]);
            
            echo json_encode(['success' => true]);
            break;
            
        case 'send_answer':
            $room_id = $_POST['room_id'] ?? '';
            $answer = $_POST['answer'] ?? '';
            
            // Store answer in database
            $stmt = $pdo->prepare("
                INSERT INTO webrtc_messages (room_id, sender_id, message_type, message_data, created_at) 
                VALUES (?, ?, 'answer', ?, NOW())
            ");
            $stmt->execute([$room_id, $user_id, $answer]);
            
            echo json_encode(['success' => true]);
            break;
            
        case 'send_candidate':
            $room_id = $_POST['room_id'] ?? '';
            $candidate = $_POST['candidate'] ?? '';
            
            // Store ICE candidate in database
            $stmt = $pdo->prepare("
                INSERT INTO webrtc_messages (room_id, sender_id, message_type, message_data, created_at) 
                VALUES (?, ?, 'candidate', ?, NOW())
            ");
            $stmt->execute([$room_id, $user_id, $candidate]);
            
            echo json_encode(['success' => true]);
            break;
            
        case 'get_messages':
            $room_id = $_POST['room_id'] ?? '';
            $last_id = intval($_POST['last_id'] ?? 0);
            
            // Get messages for this room
            $stmt = $pdo->prepare("
                SELECT id, sender_id, message_type, message_data, created_at 
                FROM webrtc_messages 
                WHERE room_id = ? AND id > ? 
                ORDER BY id ASC
            ");
            $stmt->execute([$room_id, $last_id]);
            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'messages' => $messages
            ]);
            break;
            
        case 'leave_room':
            unset($_SESSION['webrtc_room']);
            echo json_encode(['success' => true]);
            break;
            
        default:
            echo json_encode(['error' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}

// Create table for WebRTC messages if it doesn't exist
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS webrtc_messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            room_id VARCHAR(100) NOT NULL,
            sender_id INT NOT NULL,
            message_type ENUM('offer', 'answer', 'candidate') NOT NULL,
            message_data TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_room_id (room_id),
            INDEX idx_created_at (created_at)
        )
    ");
} catch (Exception $e) {
    // Table creation might fail if already exists, that's okay
}
?>