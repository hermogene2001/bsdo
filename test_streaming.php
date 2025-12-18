<?php
// Test streaming setup
require_once 'config.php';

echo "=== BSDO Streaming Test ===\n\n";

try {
    // Check database
    $stmt = $pdo->query('SELECT COUNT(*) as count FROM live_streams');
    $result = $stmt->fetch();
    echo "✓ Database: " . $result['count'] . " streams found\n";

    // Check URLs
    $stmt = $pdo->query('SELECT title, rtmp_url, hls_url FROM live_streams LIMIT 1');
    $stream = $stmt->fetch();
    if ($stream) {
        echo "✓ URLs configured for: " . $stream['title'] . "\n";
        echo "  RTMP: " . $stream['rtmp_url'] . "\n";
        echo "  HLS: " . $stream['hls_url'] . "\n\n";
    }

    // Test RTMP server
    echo "Testing RTMP server (port 1935)...\n";
    $rtmp_test = @fsockopen('localhost', 1935, $errno, $errstr, 5);
    if ($rtmp_test) {
        echo "✓ RTMP server is running\n";
        fclose($rtmp_test);
    } else {
        echo "✗ RTMP server not accessible\n";
    }

    // Test HLS server
    echo "Testing HLS server (port 8080)...\n";
    $hls_test = @fsockopen('localhost', 8080, $errno, $errstr, 5);
    if ($hls_test) {
        echo "✓ HLS server is running\n";
        fclose($hls_test);

        // Test health endpoint
        $health_url = 'http://localhost:8080/health';
        $context = stream_context_create(['http' => ['timeout' => 5]]);
        $health_response = @file_get_contents($health_url, false, $context);
        if ($health_response) {
            $health_data = json_decode($health_response, true);
            echo "✓ Health check passed: " . ($health_data['status'] ?? 'unknown') . "\n";
        }
    } else {
        echo "✗ HLS server not accessible\n";
    }

    echo "\n=== Test Results ===\n";
    echo "If all checks pass, your streaming system is ready!\n";
    echo "\nNext steps:\n";
    echo "1. Open OBS Studio\n";
    echo "2. Settings → Stream\n";
    echo "3. Server: rtmp://localhost:1935/live\n";
    echo "4. Stream Key: [get from seller dashboard]\n";
    echo "5. Start Streaming!\n";
    echo "\nTest playback: http://localhost:8080/hls/[stream_key]/index.m3u8\n";

} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}
?>