<?php
require_once 'config.php';

// Test if we can generate an invitation link for a stream
try {
    // First, let's see if there are any live streams we can test with
    $stmt = $pdo->query("SELECT id, title, seller_id FROM live_streams WHERE is_live = 1 LIMIT 1");
    $stream = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($stream) {
        echo "Found a live stream to test with:\n";
        echo "- ID: {$stream['id']}\n";
        echo "- Title: {$stream['title']}\n";
        
        // Generate a test invite code
        $invite_code = bin2hex(random_bytes(10));
        $expires_at = date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        // Update the stream with the invite code
        $update = $pdo->prepare("UPDATE live_streams SET invite_code = ?, invite_expires_at = ? WHERE id = ?");
        $update->execute([$invite_code, $expires_at, $stream['id']]);
        
        echo "Generated invitation link:\n";
        echo "Code: $invite_code\n";
        echo "Expires: $expires_at\n";
        echo "URL: http://localhost/bsdo/watch_stream.php?invite=$invite_code\n";
        
        // Test resolving the invite code
        $invite_stmt = $pdo->prepare("SELECT id FROM live_streams WHERE invite_code = ? AND is_live = 1 AND (invite_expires_at IS NULL OR invite_expires_at > UTC_TIMESTAMP()) LIMIT 1");
        $invite_stmt->execute([$invite_code]);
        $row = $invite_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            echo "✓ Successfully resolved invite code to stream ID: {$row['id']}\n";
        } else {
            echo "✗ Failed to resolve invite code\n";
        }
    } else {
        echo "No live streams found. Creating a test stream...\n";
        
        // Create a test stream
        $stmt = $pdo->prepare("INSERT INTO live_streams (seller_id, title, description, stream_key, is_live, status, streaming_method) VALUES (?, ?, ?, ?, 1, 'live', 'rtmp')");
        $stmt->execute([1, 'Test Stream', 'Test stream for invitation link functionality', 'test_stream_key_' . time(), 1]);
        $stream_id = $pdo->lastInsertId();
        
        echo "Created test stream with ID: $stream_id\n";
        
        // Generate a test invite code
        $invite_code = bin2hex(random_bytes(10));
        $expires_at = date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        // Update the stream with the invite code
        $update = $pdo->prepare("UPDATE live_streams SET invite_code = ?, invite_expires_at = ? WHERE id = ?");
        $update->execute([$invite_code, $expires_at, $stream_id]);
        
        echo "Generated invitation link:\n";
        echo "Code: $invite_code\n";
        echo "Expires: $expires_at\n";
        echo "URL: http://localhost/bsdo/watch_stream.php?invite=$invite_code\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>