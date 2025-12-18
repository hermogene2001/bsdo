<?php
require_once 'config.php';

try {
    $stmt = $pdo->query('SELECT title, is_live, rtmp_url, hls_url FROM live_streams WHERE is_live = 1 LIMIT 2');
    while($row = $stmt->fetch()) {
        echo $row['title'] . ' - Live: ' . $row['is_live'] . PHP_EOL;
        echo 'RTMP: ' . $row['rtmp_url'] . PHP_EOL;
        echo 'HLS: ' . $row['hls_url'] . PHP_EOL . PHP_EOL;
    }

    // Test if HLS URLs are accessible
    echo 'Testing HLS URL accessibility:' . PHP_EOL;
    $stmt = $pdo->query('SELECT hls_url FROM live_streams WHERE is_live = 1 LIMIT 1');
    $row = $stmt->fetch();
    if ($row) {
        $hls_url = $row['hls_url'];
        echo 'Testing URL: ' . $hls_url . PHP_EOL;

        $context = stream_context_create(['http' => ['timeout' => 10]]);
        $result = @file_get_contents($hls_url, false, $context);
        if ($result !== false) {
            echo '✓ HLS stream is accessible' . PHP_EOL;
        } else {
            echo '✗ HLS stream is not accessible' . PHP_EOL;
        }
    }

} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
}
?>