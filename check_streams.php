<?php
require_once 'config.php';

try {
    // Check live streams
    $stmt = $pdo->query('SELECT COUNT(*) as count FROM live_streams WHERE is_live = 1');
    $result = $stmt->fetch();
    echo "Live streams: " . $result['count'] . "\n";
    
    // Check total streams
    $stmt = $pdo->query('SELECT COUNT(*) as count FROM live_streams');
    $result = $stmt->fetch();
    echo "Total streams: " . $result['count'] . "\n";
    
    // Get live streams details
    $stmt = $pdo->query('SELECT ls.*, u.store_name FROM live_streams ls JOIN users u ON ls.seller_id = u.id WHERE ls.is_live = 1');
    $streams = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($streams)) {
        echo "\nLive streams details:\n";
        foreach ($streams as $stream) {
            echo "- Stream ID: " . $stream['id'] . " | Title: " . $stream['title'] . " | Seller: " . $stream['store_name'] . "\n";
        }
    } else {
        echo "\nNo live streams currently.\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>