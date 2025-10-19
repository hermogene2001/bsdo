<?php
require_once 'config.php';

try {
    // Check if payment_channels table exists
    $stmt = $pdo->prepare("SHOW TABLES LIKE 'payment_channels'");
    $stmt->execute();
    $payment_channels_exists = $stmt->rowCount() > 0;
    
    echo "Payment channels table exists: " . ($payment_channels_exists ? "YES" : "NO") . "\n";
    
    // Check products table structure
    $stmt = $pdo->prepare("DESCRIBE products");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Products table columns:\n";
    foreach ($columns as $column) {
        echo "- " . $column['Field'] . " (" . $column['Type'] . ")\n";
        if ($column['Field'] == 'payment_channel_id') {
            echo "  -> Payment channel ID column found\n";
        }
        if ($column['Field'] == 'verification_payment_status') {
            echo "  -> Verification payment status column found\n";
        }
    }
    
    // Check if there are any payment channels
    if ($payment_channels_exists) {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM payment_channels");
        $stmt->execute();
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        echo "Number of payment channels: " . $count . "\n";
        
        if ($count > 0) {
            $stmt = $pdo->prepare("SELECT * FROM payment_channels LIMIT 3");
            $stmt->execute();
            $channels = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo "Sample payment channels:\n";
            foreach ($channels as $channel) {
                echo "- " . $channel['name'] . " (" . $channel['type'] . ")\n";
            }
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>