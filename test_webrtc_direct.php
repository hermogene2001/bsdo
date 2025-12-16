<?php
require_once 'config.php';

try {
    echo "Testing direct WebRTC streaming (like live_stream_webrtc.php)...\n";
    
    // Test with the exact parameters from live_stream_webrtc.php
    $seller_id = 1;
    $title = "Direct WebRTC Test Stream";
    $description = "Test Description";
    $category_id = 1;
    $stream_key = "direct_webrtc_test_stream_key_" . time();
    $invitation_code = bin2hex(random_bytes(8));
    $rtmp_url = "rtmp://test.server.com/app/stream";
    $hls_url = "http://test.server.com/hls/stream.m3u8";
    
    $stmt = $pdo->prepare("
        INSERT INTO live_streams (seller_id, title, description, category_id, stream_key, invitation_code, rtmp_url, hls_url, is_live, streaming_method, status, started_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, 'webrtc', 'live', NOW())
    ");
    
    $result = $stmt->execute([$seller_id, $title, $description, $category_id, $stream_key, $invitation_code, $rtmp_url, $hls_url]);
    
    if ($result) {
        echo "✓ Direct WebRTC INSERT successful\n";
        $stream_id = $pdo->lastInsertId();
        echo "Stream ID: $stream_id\n";
        
        // Clean up
        $pdo->prepare("DELETE FROM live_streams WHERE id = ?")->execute([$stream_id]);
        echo "✓ Test record cleaned up\n";
    }
    
} catch (PDOException $e) {
    echo "✗ Direct WebRTC streaming error:\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "Code: " . $e->getCode() . "\n";
    if (isset($e->errorInfo)) {
        echo "SQL State: " . $e->errorInfo[0] . "\n";
        echo "Driver Error Code: " . $e->errorInfo[1] . "\n";
        echo "Driver Error Message: " . $e->errorInfo[2] . "\n";
    }
} catch (Exception $e) {
    echo "✗ General error:\n";
    echo "Message: " . $e->getMessage() . "\n";
}
?>