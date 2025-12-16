<?php
require_once 'config.php';

try {
    // Test inserting a record with streaming_method
    $stmt = $pdo->prepare("
        INSERT INTO live_streams (seller_id, title, description, category_id, stream_key, invitation_code, invitation_enabled, invitation_expiry, status, scheduled_at, streaming_method) 
        VALUES (?, ?, ?, ?, ?, ?, true, NULL, 'scheduled', ?, ?)
    ");
    
    $result = $stmt->execute([1, 'Test Stream', 'Test Description', 1, 'test_key_123', 'invite_123', date('Y-m-d H:i:s'), 'rtmp']);
    
    if ($result) {
        echo "Insert successful!\n";
        // Get the inserted ID
        $stream_id = $pdo->lastInsertId();
        echo "Inserted stream ID: " . $stream_id . "\n";
        
        // Delete the test record
        $delete_stmt = $pdo->prepare("DELETE FROM live_streams WHERE id = ?");
        $delete_stmt->execute([$stream_id]);
        echo "Test record cleaned up.\n";
    }
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
    echo "Error code: " . $e->getCode() . "\n";
}
?>