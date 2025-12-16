<?php
require_once 'config.php';

try {
    echo "Testing INSERT with streaming_method column...\n";
    
    // Test the exact INSERT statement from seller\live_stream_webrtc.php
    $stmt = $pdo->prepare("
        INSERT INTO live_streams (seller_id, title, description, category_id, stream_key, invitation_code, rtmp_url, hls_url, is_live, streaming_method, status, started_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, 'webrtc', 'live', NOW())
    ");
    
    $result = $stmt->execute([1, 'Final Test Stream', 'Final test description', 1, 'final_test_key', 'final_invite_123', 'rtmp://test.com/app/stream', 'http://test.com/hls/stream.m3u8']);
    
    if ($result) {
        echo "✓ INSERT successful!\n";
        $stream_id = $pdo->lastInsertId();
        echo "Stream ID: " . $stream_id . "\n";
        
        // Clean up
        $delete_stmt = $pdo->prepare("DELETE FROM live_streams WHERE id = ?");
        $delete_stmt->execute([$stream_id]);
        echo "✓ Test record cleaned up.\n";
    }
} catch (PDOException $e) {
    echo "✗ Database error: " . $e->getMessage() . "\n";
    echo "Error code: " . $e->getCode() . "\n";
}

try {
    echo "\nTesting INSERT with streaming_method column from seller\live_stream.php...\n";
    
    // Test the exact INSERT statement from seller\live_stream.php
    $stmt = $pdo->prepare("
        INSERT INTO live_streams (seller_id, title, description, category_id, stream_key, invitation_code, invitation_enabled, invitation_expiry, status, scheduled_at, streaming_method) 
        VALUES (?, ?, ?, ?, ?, ?, true, NULL, 'scheduled', ?, ?)
    ");
    
    $result = $stmt->execute([1, 'Second Test Stream', 'Second test description', 1, 'second_test_key', 'second_invite_123', date('Y-m-d H:i:s'), 'rtmp']);
    
    if ($result) {
        echo "✓ INSERT successful!\n";
        $stream_id = $pdo->lastInsertId();
        echo "Stream ID: " . $stream_id . "\n";
        
        // Clean up
        $delete_stmt = $pdo->prepare("DELETE FROM live_streams WHERE id = ?");
        $delete_stmt->execute([$stream_id]);
        echo "✓ Test record cleaned up.\n";
    }
} catch (PDOException $e) {
    echo "✗ Database error: " . $e->getMessage() . "\n";
    echo "Error code: " . $e->getCode() . "\n";
}
?>