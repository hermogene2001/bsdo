<?php
require_once 'config.php';

try {
    // Get live streams details
    $stmt = $pdo->query('SELECT ls.*, u.store_name, u.first_name, u.last_name FROM live_streams ls JOIN users u ON ls.seller_id = u.id WHERE ls.is_live = 1');
    $streams = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($streams)) {
        echo "Live streams details:\n";
        foreach ($streams as $stream) {
            echo "=====================================\n";
            echo "Stream ID: " . $stream['id'] . "\n";
            echo "Title: " . $stream['title'] . "\n";
            echo "Seller: " . $stream['store_name'] . " (" . $stream['first_name'] . " " . $stream['last_name'] . ")\n";
            echo "Started at: " . $stream['started_at'] . "\n";
            echo "Status: " . $stream['status'] . "\n";
            echo "Is Live: " . $stream['is_live'] . "\n";
            echo "Stream Key: " . $stream['stream_key'] . "\n";
        }
    } else {
        echo "No live streams currently.\n";
    }
    
    // Check if there are viewers
    echo "\nChecking viewers:\n";
    $stmt = $pdo->query('SELECT COUNT(*) as count FROM live_stream_viewers WHERE is_active = 1');
    $result = $stmt->fetch();
    echo "Active viewers: " . $result['count'] . "\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>