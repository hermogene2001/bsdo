<?php
// Real-time WebRTC stream monitor
require_once 'config.php';

echo "=== BSDO WebRTC Stream Monitor ===\n\n";

while (true) {
    // Clear screen and show current status
    echo "\033[2J\033[H"; // Clear screen
    echo "=== BSDO WebRTC Stream Monitor ===\n";
    echo "Time: " . date('H:i:s') . "\n\n";

    try {
        // Check active WebRTC streams
        $stmt = $pdo->query('SELECT COUNT(*) as count FROM live_streams WHERE streaming_method = "webrtc" AND is_live = 1');
        $result = $stmt->fetch();
        $active_streams = $result['count'];

        echo "Active WebRTC Streams: $active_streams\n";

        if ($active_streams > 0) {
            // Show stream details
            $stmt = $pdo->query('
                SELECT ls.id, ls.title, ls.stream_key, u.first_name, u.last_name,
                       COUNT(lsv.id) as viewers
                FROM live_streams ls
                JOIN users u ON ls.seller_id = u.id
                LEFT JOIN live_stream_viewers lsv ON ls.id = lsv.stream_id AND lsv.is_active = 1
                WHERE ls.streaming_method = "webrtc" AND ls.is_live = 1
                GROUP BY ls.id
            ');

            while ($stream = $stmt->fetch()) {
                echo "\n🎥 Stream: {$stream['title']}\n";
                echo "   Seller: {$stream['first_name']} {$stream['last_name']}\n";
                echo "   Stream Key: {$stream['stream_key']}\n";
                echo "   Viewers: {$stream['viewers']}\n";
                echo "   Status: ✅ LIVE\n";
            }

            // Check recent chat messages
            $stmt = $pdo->query('
                SELECT COUNT(*) as count
                FROM live_stream_comments lsc
                JOIN live_streams ls ON lsc.stream_id = ls.id
                WHERE ls.streaming_method = "webrtc" AND ls.is_live = 1
                AND lsc.created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
            ');
            $result = $stmt->fetch();
            echo "\n💬 Recent Chat Messages (5 min): {$result['count']}\n";

        } else {
            echo "\n❌ No active WebRTC streams\n";
            echo "\n💡 To start testing:\n";
            echo "1. Login as seller@test.com / test123\n";
            echo "2. Go to seller/live_stream.php\n";
            echo "3. Select 'WebRTC (Browser-based streaming)'\n";
            echo "4. Click 'Start Live Stream'\n";
        }

        // Check WebRTC messages table
        $stmt = $pdo->query('SELECT COUNT(*) as count FROM webrtc_messages');
        $result = $stmt->fetch();
        echo "\n📡 WebRTC Signaling Messages: {$result['count']}\n";

        echo "\n" . str_repeat("=", 40) . "\n";
        echo "Press Ctrl+C to stop monitoring\n";
        echo "Refresh rate: 5 seconds\n";

    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }

    // Wait 5 seconds before next update
    sleep(5);
}
?>