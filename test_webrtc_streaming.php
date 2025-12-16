<?php
require_once 'config.php';

try {
    echo "Testing WebRTC streaming method...\n";
    
    // Test with WebRTC streaming method
    $seller_id = 1;
    $title = "WebRTC Test Stream";
    $description = "Test Description";
    $category_id = 1;
    $stream_key = "webrtc_test_stream_key_" . time();
    $invitation_code = bin2hex(random_bytes(8));
    $scheduled_at = null;
    $streaming_method = "webrtc";
    
    $stmt = $pdo->prepare("
        INSERT INTO live_streams (seller_id, title, description, category_id, stream_key, invitation_code, invitation_enabled, invitation_expiry, status, scheduled_at, streaming_method) 
        VALUES (?, ?, ?, ?, ?, ?, true, NULL, 'scheduled', ?, ?)
    ");
    
    $result = $stmt->execute([$seller_id, $title, $description, $category_id, $stream_key, $invitation_code, $scheduled_at, $streaming_method]);
    
    if ($result) {
        echo "✓ WebRTC INSERT successful\n";
        $stream_id = $pdo->lastInsertId();
        echo "Stream ID: $stream_id\n";
        
        // Test the redirect logic
        $stream_stmt = $pdo->prepare("SELECT streaming_method FROM live_streams WHERE id = ?");
        $stream_stmt->execute([$stream_id]);
        $stream_data = $stream_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($stream_data && $stream_data['streaming_method'] === 'webrtc') {
            echo "✓ Correctly identified as WebRTC stream\n";
        } else {
            echo "✗ Failed to identify as WebRTC stream\n";
        }
        
        // Clean up
        $pdo->prepare("DELETE FROM live_streams WHERE id = ?")->execute([$stream_id]);
        echo "✓ Test record cleaned up\n";
    }
    
} catch (PDOException $e) {
    echo "✗ WebRTC streaming error:\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "Code: " . $e->getCode() . "\n";
} catch (Exception $e) {
    echo "✗ General error:\n";
    echo "Message: " . $e->getMessage() . "\n";
}
?>