<?php
require_once 'config.php';

echo "=== BSDO Live Streaming Fix ===\n\n";

try {
    // Check current streams
    $stmt = $pdo->query('SELECT COUNT(*) as count FROM live_streams WHERE is_live = 1');
    $result = $stmt->fetch();
    echo "Active live streams: " . $result['count'] . "\n";

    $stmt = $pdo->query('SELECT COUNT(*) as count FROM live_streams');
    $result = $stmt->fetch();
    echo "Total streams: " . $result['count'] . "\n\n";

    // Check current URLs
    echo "Current stream URLs (sample):\n";
    $stmt = $pdo->query('SELECT title, rtmp_url, hls_url FROM live_streams LIMIT 3');
    while ($row = $stmt->fetch()) {
        echo "- " . $row['title'] . "\n";
        echo "  RTMP: " . substr($row['rtmp_url'], 0, 60) . "...\n";
        echo "  HLS: " . substr($row['hls_url'], 0, 60) . "...\n\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>