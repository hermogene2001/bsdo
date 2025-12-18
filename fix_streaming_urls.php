<?php
// Fix streaming URLs to use local development setup
require_once 'config.php';

echo "=== Fixing BSDO Live Streaming URLs ===\n\n";

try {
    // Update all streams to use local URLs for development
    $local_rtmp_base = 'rtmp://localhost:1935/live';
    $local_hls_base = 'http://localhost:8080/hls';

    // Get all streams
    $stmt = $pdo->query('SELECT id, title, stream_key FROM live_streams');
    $streams = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Updating " . count($streams) . " streams with local URLs...\n\n";

    $update_stmt = $pdo->prepare("
        UPDATE live_streams
        SET rtmp_url = ?, hls_url = ?
        WHERE id = ?
    ");

    foreach ($streams as $stream) {
        $rtmp_url = $local_rtmp_base . '/' . $stream['stream_key'];
        $hls_url = $local_hls_base . '/' . $stream['stream_key'] . '.m3u8';

        $update_stmt->execute([$rtmp_url, $hls_url, $stream['id']]);

        echo "✓ Updated: " . $stream['title'] . "\n";
        echo "  RTMP: $rtmp_url\n";
        echo "  HLS: $hls_url\n\n";
    }

    echo "=== URL Update Complete ===\n";
    echo "Next steps:\n";
    echo "1. Install Nginx with RTMP module\n";
    echo "2. Configure RTMP server (nginx.conf)\n";
    echo "3. Start Nginx service\n";
    echo "4. Test streaming with OBS Studio\n\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>