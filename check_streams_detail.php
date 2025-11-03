<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

// Handle POST requests for adding seller comments
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_seller_comment') {
        // Check if user is logged in and is a seller
        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'seller') {
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit();
        }
        
        $seller_id = $_SESSION['user_id'];
        $stream_id = intval($_POST['stream_id']);
        $comment = trim($_POST['comment']);
        
        if (empty($comment)) {
            echo json_encode(['success' => false, 'error' => 'Comment cannot be empty']);
            exit();
        }
        
        try {
            // Verify that the stream belongs to this seller
            $stmt = $pdo->prepare("SELECT id FROM live_streams WHERE id = ? AND seller_id = ?");
            $stmt->execute([$stream_id, $seller_id]);
            $stream = $stmt->fetch();
            
            if (!$stream) {
                echo json_encode(['success' => false, 'error' => 'Stream not found or access denied']);
                exit();
            }
            
            // Add seller comment (is_seller = 1)
            $stmt = $pdo->prepare("
                INSERT INTO live_stream_comments (stream_id, user_id, comment, is_seller) 
                VALUES (?, ?, ?, 1)
            ");
            $stmt->execute([$stream_id, $seller_id, $comment]);
            
            echo json_encode(['success' => true]);
            exit();
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit();
        }
    }
}

// Handle GET requests for fetching comments
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['stream_id'])) {
    $stream_id = intval($_GET['stream_id']);
    $last_comment_id = intval($_GET['last_comment_id'] ?? 0);
    
    try {
        // Get comments for this stream
        $stmt = $pdo->prepare("
            SELECT lsc.id, lsc.comment, lsc.is_seller, lsc.created_at, 
                   u.first_name, u.last_name
            FROM live_stream_comments lsc
            LEFT JOIN users u ON lsc.user_id = u.id
            WHERE lsc.stream_id = ? AND lsc.id > ?
            ORDER BY lsc.created_at ASC
            LIMIT 50
        ");
        $stmt->execute([$stream_id, $last_comment_id]);
        $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'comments' => $comments]);
        exit();
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit();
    }
}

// Default response for other requests
echo json_encode(['success' => false, 'error' => 'Invalid request']);
?>