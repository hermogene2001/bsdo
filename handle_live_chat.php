<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';
$stream_id = intval($_POST['stream_id'] ?? 0);

try {
    // Check if user is muted or banned
    $stmt = $pdo->prepare("
        SELECT action, expires_at 
        FROM live_stream_chat_moderation 
        WHERE stream_id = ? AND user_id = ? 
        AND (expires_at IS NULL OR expires_at > NOW())
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    $stmt->execute([$stream_id, $user_id]);
    $moderation = $stmt->fetch();

    if ($moderation && $action !== 'get_messages') {
        echo json_encode([
            'error' => 'You are currently ' . $moderation['action'] . 'ed from this chat',
            'expires_at' => $moderation['expires_at']
        ]);
        exit;
    }

    switch ($action) {
        case 'send_message':
            $message = trim($_POST['message'] ?? '');
            $message_type = $_POST['message_type'] ?? 'text';
            
            if (empty($message)) {
                echo json_encode(['error' => 'Message cannot be empty']);
                exit;
            }

            // Check if user is a seller for this stream
            $stmt = $pdo->prepare("
                SELECT seller_id FROM live_streams 
                WHERE id = ? AND is_live = 1
            ");
            $stmt->execute([$stream_id]);
            $stream = $stmt->fetch();
            
            if (!$stream) {
                echo json_encode(['error' => 'Stream not found or not live']);
                exit;
            }

            $is_seller = ($stream['seller_id'] == $user_id) ? 1 : 0;
            
            // Only sellers can send highlighted messages or announcements
            if (!$is_seller && $message_type !== 'text') {
                $message_type = 'text';
            }

            $stmt = $pdo->prepare("
                INSERT INTO live_stream_chat 
                (stream_id, user_id, message, message_type, is_seller) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$stream_id, $user_id, $message, $message_type, $is_seller]);

            echo json_encode(['success' => true, 'message_id' => $pdo->lastInsertId()]);
            break;

        case 'get_messages':
            $last_id = intval($_POST['last_id'] ?? 0);
            
            $stmt = $pdo->prepare("
                SELECT 
                    c.id,
                    c.message,
                    c.message_type,
                    c.is_seller,
                    c.is_pinned,
                    c.created_at,
                    u.username,
                    u.profile_image
                FROM live_stream_chat c
                JOIN users u ON c.user_id = u.id
                WHERE c.stream_id = ? AND c.id > ?
                ORDER BY c.created_at ASC
                LIMIT 100
            ");
            $stmt->execute([$stream_id, $last_id]);
            
            echo json_encode([
                'success' => true,
                'messages' => $stmt->fetchAll(PDO::FETCH_ASSOC)
            ]);
            break;

        case 'pin_message':
            if ($_SESSION['user_role'] !== 'seller') {
                echo json_encode(['error' => 'Unauthorized']);
                exit;
            }

            $message_id = intval($_POST['message_id'] ?? 0);
            
            $stmt = $pdo->prepare("
                UPDATE live_stream_chat 
                SET is_pinned = 1 
                WHERE id = ? AND stream_id = ?
            ");
            $stmt->execute([$message_id, $stream_id]);
            
            echo json_encode(['success' => true]);
            break;

        case 'moderate_user':
            if ($_SESSION['user_role'] !== 'seller') {
                echo json_encode(['error' => 'Unauthorized']);
                exit;
            }

            $target_user_id = intval($_POST['target_user_id'] ?? 0);
            $action_type = $_POST['action_type'] ?? 'mute';
            $duration = intval($_POST['duration'] ?? 0); // minutes
            $reason = trim($_POST['reason'] ?? '');

            $expires_at = $duration > 0 ? date('Y-m-d H:i:s', time() + ($duration * 60)) : null;

            $stmt = $pdo->prepare("
                INSERT INTO live_stream_chat_moderation 
                (stream_id, user_id, action, duration, reason, expires_at) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $stream_id, 
                $target_user_id, 
                $action_type, 
                $duration,
                $reason,
                $expires_at
            ]);

            echo json_encode(['success' => true]);
            break;
    }
} catch (Exception $e) {
    error_log("Live chat error: " . $e->getMessage());
    echo json_encode(['error' => 'Internal server error']);
}