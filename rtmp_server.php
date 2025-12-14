<?php
// RTMP streaming server implementation
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
ini_set('error_log', __DIR__ . '/rtmp_error.log');

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

try {
    switch ($action) {
        case 'get_stream_info':
            $stream_id = intval($_POST['stream_id'] ?? 0);
            
            if (!$stream_id) {
                throw new Exception('Invalid stream ID');
            }
            
            // Verify stream exists and user has access
            $stmt = $pdo->prepare("
                SELECT id, seller_id, stream_key, rtmp_url, hls_url, is_live 
                FROM live_streams 
                WHERE id = ?
            ");
            $stmt->execute([$stream_id]);
            $stream = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$stream) {
                throw new Exception('Stream not found');
            }
            
            // Check if user is authorized to access this stream
            $authorized = false;
            if ($stream['seller_id'] == $user_id) {
                // Seller can always access their own stream
                $authorized = true;
            } else {
                // For viewers, check if stream is live
                if ($stream['is_live'] == 1) {
                    $authorized = true;
                }
            }
            
            if (!$authorized) {
                throw new Exception('Access denied');
            }
            
            echo json_encode([
                'success' => true,
                'stream' => [
                    'id' => $stream['id'],
                    'stream_key' => $stream['stream_key'],
                    'rtmp_url' => $stream['rtmp_url'],
                    'hls_url' => $stream['hls_url'],
                    'is_live' => $stream['is_live']
                ]
            ]);
            break;
            
        case 'update_stream_status':
            $stream_id = intval($_POST['stream_id'] ?? 0);
            $is_live = intval($_POST['is_live'] ?? 0);
            
            if (!$stream_id) {
                throw new Exception('Invalid stream ID');
            }
            
            // Verify stream exists and user is the seller
            $stmt = $pdo->prepare("
                SELECT id, seller_id 
                FROM live_streams 
                WHERE id = ? AND seller_id = ?
            ");
            $stmt->execute([$stream_id, $user_id]);
            $stream = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$stream) {
                throw new Exception('Stream not found or you are not authorized');
            }
            
            // Update stream status
            $stmt = $pdo->prepare("
                UPDATE live_streams 
                SET is_live = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $result = $stmt->execute([$is_live, $stream_id]);
            
            if (!$result) {
                throw new Exception('Failed to update stream status');
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Stream status updated',
                'is_live' => $is_live
            ]);
            break;
            
        case 'get_viewer_count':
            $stream_id = intval($_POST['stream_id'] ?? 0);
            
            if (!$stream_id) {
                throw new Exception('Invalid stream ID');
            }
            
            // Get current viewer count
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as viewer_count
                FROM live_stream_viewers 
                WHERE stream_id = ? AND is_active = 1
            ");
            $stmt->execute([$stream_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'count' => $result['viewer_count'] ?? 0
            ]);
            break;
            
        default:
            throw new Exception('Invalid action: ' . htmlspecialchars($action));
    }
    
} catch (PDOException $e) {
    error_log("Database error in rtmp_server.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error occurred',
        'details' => $e->getMessage() // Remove in production
    ]);
    
} catch (Exception $e) {
    error_log("Error in rtmp_server.php: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>