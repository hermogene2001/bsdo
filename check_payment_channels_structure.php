<?php
require_once 'config.php';

try {
    $stmt = $pdo->query('DESCRIBE payment_channels');
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Payment Channels Table Structure:\n";
    foreach ($result as $row) {
        echo "- Field: {$row['Field']}, Type: {$row['Type']}, Null: {$row['Null']}, Key: {$row['Key']}, Default: {$row['Default']}, Extra: {$row['Extra']}\n";
    }
    
    echo "\nSample payment channels data:\n";
    $stmt = $pdo->query('SELECT * FROM payment_channels LIMIT 10');
    $channels = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($channels as $channel) {
        echo "ID: {$channel['id']}, Name: {$channel['name']}, ";
        if (isset($channel['type'])) {
            echo "Type: {$channel['type']}";
        } else {
            echo "Type: [NOT EXISTS]";
        }
        echo "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>