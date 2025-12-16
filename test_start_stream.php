<?php
require_once 'config.php';

try {
    echo "Testing exact start_stream functionality...\n";
    
    // Simulate the exact parameters from live_stream.php
    $seller_id = 1;
    $title = "Test Stream";
    $description = "Test Description";
    $category_id = 1;
    $stream_key = "test_stream_key_" . time();
    $invitation_code = bin2hex(random_bytes(8));
    $scheduled_at = null; // Not scheduled, start immediately
    $streaming_method = "rtmp"; // Default method
    
    echo "Preparing INSERT statement...\n";
    $stmt = $pdo->prepare("
        INSERT INTO live_streams (seller_id, title, description, category_id, stream_key, invitation_code, invitation_enabled, invitation_expiry, status, scheduled_at, streaming_method) 
        VALUES (?, ?, ?, ?, ?, ?, true, NULL, 'scheduled', ?, ?)
    ");
    
    echo "Executing with parameters:\n";
    echo "- seller_id: $seller_id\n";
    echo "- title: $title\n";
    echo "- description: $description\n";
    echo "- category_id: $category_id\n";
    echo "- stream_key: $stream_key\n";
    echo "- invitation_code: $invitation_code\n";
    echo "- scheduled_at: " . ($scheduled_at ? $scheduled_at : "null") . "\n";
    echo "- streaming_method: $streaming_method\n";
    
    $result = $stmt->execute([$seller_id, $title, $description, $category_id, $stream_key, $invitation_code, $scheduled_at, $streaming_method]);
    
    if ($result) {
        echo "✓ INSERT successful!\n";
        $stream_id = $pdo->lastInsertId();
        echo "Stream ID: $stream_id\n";
        
        // Clean up
        $pdo->prepare("DELETE FROM live_streams WHERE id = ?")->execute([$stream_id]);
        echo "✓ Test record cleaned up\n";
    } else {
        echo "✗ INSERT failed\n";
    }
    
} catch (PDOException $e) {
    echo "✗ Database error: " . $e->getMessage() . "\n";
    echo "Error code: " . $e->getCode() . "\n";
    echo "SQL State: " . $e->errorInfo[0] . "\n";
    echo "Driver Error Code: " . $e->errorInfo[1] . "\n";
    echo "Driver Error Message: " . $e->errorInfo[2] . "\n";
} catch (Exception $e) {
    echo "✗ General error: " . $e->getMessage() . "\n";
}
?>