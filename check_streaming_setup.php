<?php
require_once 'config.php';

try {
    echo "Checking streaming setup...\n";
    
    // Check if live_streams table exists
    $stmt = $pdo->query('SHOW TABLES LIKE "live_streams"');
    if ($stmt->fetch()) {
        echo "✓ live_streams table exists\n";
    } else {
        echo "✗ live_streams table missing\n";
        exit(1);
    }
    
    // Check table structure
    $stmt = $pdo->query('DESCRIBE live_streams');
    $columns = [];
    while ($row = $stmt->fetch()) {
        $columns[] = $row['Field'];
    }
    
    // Required columns for streaming
    $required_columns = [
        'id', 'seller_id', 'title', 'description', 'stream_key', 
        'is_live', 'status', 'streaming_method'
    ];
    
    echo "Checking required columns...\n";
    foreach ($required_columns as $column) {
        if (in_array($column, $columns)) {
            echo "✓ Column '$column' exists\n";
        } else {
            echo "✗ Column '$column' missing\n";
        }
    }
    
    // Check if streaming_method column exists specifically
    if (in_array('streaming_method', $columns)) {
        echo "✓ streaming_method column exists\n";
    } else {
        echo "✗ streaming_method column missing\n";
        // Try to add it
        try {
            $pdo->exec("ALTER TABLE live_streams ADD COLUMN streaming_method ENUM('rtmp', 'webrtc') DEFAULT 'rtmp' AFTER status");
            echo "✓ Added streaming_method column\n";
        } catch (PDOException $e) {
            echo "✗ Failed to add streaming_method column: " . $e->getMessage() . "\n";
        }
    }
    
    // Test a simple insert
    echo "\nTesting INSERT operation...\n";
    $stmt = $pdo->prepare("INSERT INTO live_streams (seller_id, title, description, stream_key, is_live, status, streaming_method) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $result = $stmt->execute([1, 'Test Stream', 'Test Description', 'test_key_' . time(), 0, 'scheduled', 'rtmp']);
    
    if ($result) {
        echo "✓ INSERT test successful\n";
        // Clean up
        $stream_id = $pdo->lastInsertId();
        $pdo->prepare("DELETE FROM live_streams WHERE id = ?")->execute([$stream_id]);
        echo "✓ Test record cleaned up\n";
    } else {
        echo "✗ INSERT test failed\n";
    }
    
} catch (PDOException $e) {
    echo "✗ Database error: " . $e->getMessage() . "\n";
    echo "Error code: " . $e->getCode() . "\n";
}
?>