<?php
// Setup WebRTC streaming support
require_once 'config.php';

echo "=== BSDO WebRTC Streaming Setup ===\n\n";

try {
    // Check if streaming_method column exists
    $stmt = $pdo->query('DESCRIBE live_streams');
    $columns = [];
    while ($row = $stmt->fetch()) {
        $columns[] = $row['Field'];
    }

    // Add streaming_method column if it doesn't exist
    if (!in_array('streaming_method', $columns)) {
        echo "Adding streaming_method column...\n";
        $pdo->exec("ALTER TABLE live_streams ADD COLUMN streaming_method ENUM('rtmp', 'webrtc') DEFAULT 'rtmp' AFTER status");
        echo "✓ streaming_method column added\n";
    } else {
        echo "✓ streaming_method column exists\n";
    }

    // Add connection_status column if it doesn't exist
    if (!in_array('connection_status', $columns)) {
        echo "Adding connection_status column...\n";
        $pdo->exec("ALTER TABLE live_streams ADD COLUMN connection_status VARCHAR(50) DEFAULT 'offline' AFTER streaming_method");
        echo "✓ connection_status column added\n";
    } else {
        echo "✓ connection_status column exists\n";
    }

    // Create webrtc_messages table if it doesn't exist
    echo "Checking webrtc_messages table...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS webrtc_messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            room_id VARCHAR(100) NOT NULL,
            sender_id INT NOT NULL,
            message_type ENUM('offer', 'answer', 'candidate') NOT NULL,
            message_data TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_room_id (room_id),
            INDEX idx_created_at (created_at),
            INDEX idx_room_sender (room_id, sender_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "✓ webrtc_messages table ready\n";

    // Update any existing streams to use webrtc method for testing
    echo "Updating existing streams for WebRTC testing...\n";
    $update_stmt = $pdo->prepare("UPDATE live_streams SET streaming_method = 'webrtc', connection_status = 'offline' WHERE streaming_method IS NULL OR streaming_method = ''");
    $update_stmt->execute();
    echo "✓ Existing streams updated\n";

    // Create a test stream for WebRTC
    echo "Creating test WebRTC stream...\n";
    $test_stream_key = 'test_webrtc_' . time();
    $insert_stmt = $pdo->prepare("
        INSERT INTO live_streams (seller_id, title, description, stream_key, invitation_code, is_live, streaming_method, status, started_at)
        VALUES (1, 'WebRTC Test Stream', 'Test stream for WebRTC browser streaming', ?, 'test123', 0, 'webrtc', 'scheduled', NOW())
        ON DUPLICATE KEY UPDATE title = VALUES(title)
    ");
    $insert_stmt->execute([$test_stream_key]);
    echo "✓ Test stream created/updated\n";

    echo "\n=== WebRTC Setup Complete ===\n";
    echo "Your system now supports browser-based live streaming!\n\n";

    echo "How to use:\n";
    echo "1. Sellers: Go to seller/live_stream.php → Select 'WebRTC (Browser-based streaming)'\n";
    echo "2. Start stream: seller/live_stream_browser.php\n";
    echo "3. Viewers: Go to live.php → Click 'Join Stream' on WebRTC streams\n";
    echo "4. Watch: watch_stream.php will automatically detect WebRTC streams\n\n";

    echo "No external software needed - everything works in the browser!\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>