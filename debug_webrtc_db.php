<?php
// Debug database structure and stream creation
require_once 'config.php';

echo "=== Database Debug for WebRTC Streaming ===\n\n";

try {
    // Check table structure
    echo "1. Live Streams Table Structure:\n";
    $stmt = $pdo->query('DESCRIBE live_streams');
    while ($row = $stmt->fetch()) {
        echo "  {$row['Field']}: {$row['Type']} " . ($row['Null'] == 'YES' ? 'NULL' : 'NOT NULL');
        if ($row['Default']) echo " DEFAULT '{$row['Default']}'";
        echo "\n";
    }

    echo "\n2. Required Fields for WebRTC Stream Creation:\n";
    $required_fields = [
        'seller_id', 'title', 'description', 'category_id', 'stream_key',
        'invitation_code', 'rtmp_url', 'hls_url', 'is_live', 'streaming_method',
        'status', 'started_at'
    ];

    $stmt = $pdo->query('DESCRIBE live_streams');
    $existing_fields = [];
    while ($row = $stmt->fetch()) {
        $existing_fields[] = $row['Field'];
    }

    foreach ($required_fields as $field) {
        if (in_array($field, $existing_fields)) {
            echo "  ✓ $field exists\n";
        } else {
            echo "  ✗ $field MISSING\n";
        }
    }

    echo "\n3. Testing Stream Creation Query:\n";

    // Test data
    $test_data = [
        'seller_id' => 3,
        'title' => 'Test WebRTC Stream',
        'description' => 'Testing WebRTC stream creation',
        'category_id' => 1,
        'stream_key' => 'test_' . time(),
        'invitation_code' => 'test123',
        'rtmp_url' => 'rtmp://localhost:1935/live/test',
        'hls_url' => 'http://localhost:8080/hls/test/index.m3u8',
        'is_live' => 1,
        'streaming_method' => 'webrtc',
        'status' => 'live',
        'started_at' => date('Y-m-d H:i:s')
    ];

    echo "Test data prepared:\n";
    foreach ($test_data as $key => $value) {
        echo "  $key: $value\n";
    }

    // Test the exact query from live_stream_browser.php
    echo "\n4. Testing INSERT Query:\n";
    $query = "
        INSERT INTO live_streams (seller_id, title, description, category_id, stream_key, invitation_code, rtmp_url, hls_url, is_live, streaming_method, status, started_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ";

    echo "Query: $query\n";

    $stmt = $pdo->prepare($query);
    $result = $stmt->execute(array_values($test_data));

    if ($result) {
        $stream_id = $pdo->lastInsertId();
        echo "✓ INSERT successful! Stream ID: $stream_id\n";

        // Clean up test record
        $pdo->prepare("DELETE FROM live_streams WHERE id = ?")->execute([$stream_id]);
        echo "✓ Test record cleaned up\n";
    } else {
        echo "✗ INSERT failed\n";
    }

    echo "\n5. Current Stream Count:\n";
    $stmt = $pdo->query('SELECT COUNT(*) as count FROM live_streams');
    $result = $stmt->fetch();
    echo "  Total streams: {$result['count']}\n";

    $stmt = $pdo->query('SELECT COUNT(*) as count FROM live_streams WHERE streaming_method = "webrtc"');
    $result = $stmt->fetch();
    echo "  WebRTC streams: {$result['count']}\n";

} catch (PDOException $e) {
    echo "Database Error: " . $e->getMessage() . "\n";
    echo "Error Code: " . $e->getCode() . "\n";
} catch (Exception $e) {
    echo "General Error: " . $e->getMessage() . "\n";
}
?>