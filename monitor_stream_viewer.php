<?php
header('Content-Type: application/json');
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$action = $_POST['action'] ?? '';
$stream_id = intval($_POST['stream_id'] ?? 0);
$user_id = $_SESSION['user_id'];

try {
    switch ($action) {
        case 'update_viewer':
            // Update last activity time
            $stmt = $pdo->prepare("
                UPDATE live_stream_viewers 
                SET last_activity = NOW() 
                WHERE stream_id = ? AND user_id = ? AND is_active = 1
            ");
            $stmt->execute([$stream_id, $user_id]);

            // Get current viewer count
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as viewer_count 
                FROM live_stream_viewers 
                WHERE stream_id = ? AND is_active = 1 
                AND last_activity >= DATE_SUB(NOW(), INTERVAL 30 SECOND)
            ");
            $stmt->execute([$stream_id]);
            $result = $stmt->fetch();

            echo json_encode([
                'success' => true,
                'viewer_count' => $result['viewer_count']
            ]);
            break;

        case 'check_stream_status':
            // Check if stream is still live
            $stmt = $pdo->prepare("
                SELECT 
                    is_live,
                    connection_status,
                    quality_options
                FROM live_streams 
                WHERE id = ?
            ");
            $stmt->execute([$stream_id]);
            $stream = $stmt->fetch();

            if (!$stream) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Stream not found'
                ]);
                exit;
            }

            // Clean up inactive viewers (no activity in last 30 seconds)
            $stmt = $pdo->prepare("
                UPDATE live_stream_viewers 
                SET is_active = 0, left_at = NOW() 
                WHERE stream_id = ? 
                AND is_active = 1 
                AND last_activity < DATE_SUB(NOW(), INTERVAL 30 SECOND)
            ");
            $stmt->execute([$stream_id]);

            echo json_encode([
                'success' => true,
                'is_live' => (bool)$stream['is_live'],
                'connection_status' => $stream['connection_status'],
                'quality_options' => json_decode($stream['quality_options'] ?? '[]')
            ]);
            break;

        case 'report_issue':
            $issue_type = $_POST['issue_type'] ?? '';
            $details = $_POST['details'] ?? '';

            // Log streaming issues for analysis
            $stmt = $pdo->prepare("
                INSERT INTO stream_issues 
                (stream_id, user_id, issue_type, details) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$stream_id, $user_id, $issue_type, $details]);

            echo json_encode(['success' => true]);
            break;

        default:
            echo json_encode([
                'success' => false,
                'error' => 'Invalid action'
            ]);
    }
} catch (Exception $e) {
    error_log("Stream monitoring error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error'
    ]);
}