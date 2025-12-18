<?php
require_once 'config.php';

try {
    // Check active live streams
    $stmt = $pdo->query('SELECT COUNT(*) as count FROM live_streams WHERE is_live = 1');
    $result = $stmt->fetch();
    echo 'Active live streams: ' . $result['count'] . PHP_EOL;

    // Check total streams
    $stmt = $pdo->query('SELECT COUNT(*) as count FROM live_streams');
    $result = $stmt->fetch();
    echo 'Total live streams: ' . $result['count'] . PHP_EOL;

    // Check sample streams
    echo PHP_EOL . 'Sample streams:' . PHP_EOL;
    $stmt = $pdo->query('SELECT title, rtmp_url, hls_url, is_live, status FROM live_streams LIMIT 5');
    while ($stream = $stmt->fetch()) {
        echo '  "' . $stream['title'] . '" - Live: ' . $stream['is_live'] . ', Status: ' . $stream['status'] . PHP_EOL;
        echo '    RTMP: ' . substr($stream['rtmp_url'], 0, 50) . '...' . PHP_EOL;
        echo '    HLS: ' . substr($stream['hls_url'], 0, 50) . '...' . PHP_EOL;
    }

    // Check if streaming server is accessible
    echo PHP_EOL . 'Testing streaming server connectivity:' . PHP_EOL;
    $test_url = 'http://localhost:1935'; // Common RTMP port
    $context = stream_context_create(['http' => ['timeout' => 5]]);
    $result = @file_get_contents($test_url, false, $context);
    if ($result !== false) {
        echo '✓ RTMP server appears to be running' . PHP_EOL;
    } else {
        echo '✗ RTMP server not accessible (this may be normal if using external service)' . PHP_EOL;
    }

} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
}
?>