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
ini_set('display_errors', 0); // Don't display errors in production
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/webrtc_error.log');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Ensure webrtc_messages table exists
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
            INDEX idx_created_at (created_at),
            INDEX idx_room_sender (room_id, sender_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
} catch (PDOException $e) {
    error_log("Table creation error: " . $e->getMessage());
}

try {
    switch ($action) {
        case 'create_room':
            $stream_id = intval($_POST['stream_id'] ?? 0);
            
            if (!$stream_id) {
                throw new Exception('Invalid stream ID');
            }
            
            // Verify stream exists and user is the seller
            $stmt = $pdo->prepare("
                SELECT id, seller_id, is_live 
                FROM live_streams 
                WHERE id = ? AND seller_id = ? AND is_live = 1
            ");
            $stmt->execute([$stream_id, $user_id]);
            $stream = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$stream) {
                throw new Exception('Stream not found, not live, or you are not authorized');
            }
            
            // Clean up old messages for this stream (older than 1 hour)
            $stmt = $pdo->prepare("
                DELETE FROM webrtc_messages 
                WHERE room_id LIKE ? 
                AND created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)
            ");
            $stmt->execute(['room_' . $stream_id . '_%']);
            
            // Create new room ID
            $room_id = 'room_' . $stream_id . '_' . time();
            
            // Store room info in session
            $_SESSION['webrtc_room'] = [
                'room_id' => $room_id,
                'stream_id' => $stream_id,
                'created_at' => time(),
                'last_activity' => time()
            ];
            
            // Update stream connection status
            $stmt = $pdo->prepare("
                UPDATE live_streams 
                SET connection_status = 'connected' 
                WHERE id = ?
            ");
            $stmt->execute([$stream_id]);
            
            echo json_encode([
                'success' => true,
                'room_id' => $room_id,
                'stream_id' => $stream_id,
                'ice_servers' => [
                    ['urls' => 'stun:stun1.l.google.com:19302'],
                    ['urls' => 'stun:stun2.l.google.com:19302']
                ]
            ]);
            break;
            
        case 'join_room':
            $room_id = $_POST['room_id'] ?? '';
            
            if (empty($room_id)) {
                throw new Exception('Invalid room ID');
            }
            
            // Extract stream ID from room_id format: room_{stream_id}_{timestamp}
            preg_match('/room_(\d+)_/', $room_id, $matches);
            $stream_id = isset($matches[1]) ? intval($matches[1]) : 0;
            
            if (!$stream_id) {
                throw new Exception('Invalid room format');
            }
            
            // Verify stream exists and is live
            $stmt = $pdo->prepare("
                SELECT id, is_live 
                FROM live_streams 
                WHERE id = ? AND is_live = 1
            ");
            $stmt->execute([$stream_id]);
            $stream = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$stream) {
                throw new Exception('Stream not found or not live');
            }
            
            // Store room info in session
            $_SESSION['webrtc_room'] = [
                'room_id' => $room_id,
                'stream_id' => $stream_id,
                'joined_at' => time()
            ];
            
            echo json_encode([
                'success' => true,
                'room_id' => $room_id,
                'stream_id' => $stream_id
            ]);
            break;
            
        case 'send_offer':
            $room_id = $_POST['room_id'] ?? '';
            $offer = $_POST['offer'] ?? '';
            
            if (empty($room_id) || empty($offer)) {
                throw new Exception('Missing required parameters');
            }
            
            // Validate JSON
            $decoded = json_decode($offer);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid offer format');
            }
            
            // Store offer in database
            $stmt = $pdo->prepare("
                INSERT INTO webrtc_messages 
                (room_id, sender_id, message_type, message_data, created_at) 
                VALUES (?, ?, 'offer', ?, NOW())
            ");
            $result = $stmt->execute([$room_id, $user_id, $offer]);
            
            if (!$result) {
                throw new Exception('Failed to store offer');
            }
            
            echo json_encode([
                'success' => true,
                'message_id' => $pdo->lastInsertId()
            ]);
            break;
            
        case 'send_answer':
            $room_id = $_POST['room_id'] ?? '';
            $answer = $_POST['answer'] ?? '';
            
            if (empty($room_id) || empty($answer)) {
                throw new Exception('Missing required parameters');
            }
            
            // Validate JSON
            $decoded = json_decode($answer);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid answer format');
            }
            
            // Store answer in database
            $stmt = $pdo->prepare("
                INSERT INTO webrtc_messages 
                (room_id, sender_id, message_type, message_data, created_at) 
                VALUES (?, ?, 'answer', ?, NOW())
            ");
            $result = $stmt->execute([$room_id, $user_id, $answer]);
            
            if (!$result) {
                throw new Exception('Failed to store answer');
            }
            
            echo json_encode([
                'success' => true,
                'message_id' => $pdo->lastInsertId()
            ]);
            break;
            
        case 'send_candidate':
            $room_id = $_POST['room_id'] ?? '';
            $candidate = $_POST['candidate'] ?? '';
            
            if (empty($room_id) || empty($candidate)) {
                throw new Exception('Missing required parameters');
            }
            
            // Validate JSON
            $decoded = json_decode($candidate);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid candidate format');
            }
            
            // Store ICE candidate in database
            $stmt = $pdo->prepare("
                INSERT INTO webrtc_messages 
                (room_id, sender_id, message_type, message_data, created_at) 
                VALUES (?, ?, 'candidate', ?, NOW())
            ");
            $result = $stmt->execute([$room_id, $user_id, $candidate]);
            
            if (!$result) {
                throw new Exception('Failed to store candidate');
            }
            
            echo json_encode([
                'success' => true,
                'message_id' => $pdo->lastInsertId()
            ]);
            break;
            
        case 'get_messages':
            $room_id = $_POST['room_id'] ?? $_GET['room_id'] ?? '';
            $last_id = intval($_POST['last_id'] ?? $_GET['last_id'] ?? 0);
            
            if (empty($room_id)) {
                throw new Exception('Missing room ID');
            }
            
            // Get messages for this room that are not from current user
            $stmt = $pdo->prepare("
                SELECT id, sender_id, message_type, message_data, 
                       UNIX_TIMESTAMP(created_at) as created_at 
                FROM webrtc_messages 
                WHERE room_id = ? 
                AND id > ? 
                AND sender_id != ?
                ORDER BY id ASC
                LIMIT 50
            ");
            $stmt->execute([$room_id, $last_id, $user_id]);
            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'messages' => $messages,
                'count' => count($messages)
            ]);
            break;
            
        case 'leave_room':
            $room_id = $_POST['room_id'] ?? '';
            
            // Clean up session
            if (isset($_SESSION['webrtc_room'])) {
                unset($_SESSION['webrtc_room']);
            }
            
            // Optionally clean up old messages
            if (!empty($room_id)) {
                $stmt = $pdo->prepare("
                    DELETE FROM webrtc_messages 
                    WHERE room_id = ? 
                    AND created_at < DATE_SUB(NOW(), INTERVAL 30 MINUTE)
                ");
                $stmt->execute([$room_id]);
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Left room successfully'
            ]);
            break;
            
        case 'cleanup':
            // Admin function to clean up old messages
            // You might want to add admin check here
            $stmt = $pdo->prepare("
                DELETE FROM webrtc_messages 
                WHERE created_at < DATE_SUB(NOW(), INTERVAL 2 HOUR)
            ");
            $deleted = $stmt->execute();
            $count = $stmt->rowCount();
            
            echo json_encode([
                'success' => true,
                'deleted_count' => $count,
                'message' => "Cleaned up $count old messages"
            ]);
            break;
            
        default:
            throw new Exception('Invalid action: ' . htmlspecialchars($action));
    }
    
} catch (PDOException $e) {
    error_log("Database error in webrtc_server.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error occurred',
        'details' => $e->getMessage() // Remove in production
    ]);
    
} catch (Exception $e) {
    error_log("Error in webrtc_server.php: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>