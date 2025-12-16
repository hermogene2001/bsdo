<?php
require_once 'config.php';

try {
    $stmt = $pdo->query('DESCRIBE live_streams');
    echo "Live Streams Table Structure:\n";
    echo "============================\n";
    
    while ($row = $stmt->fetch()) {
        echo $row['Field'] . " (" . $row['Type'] . ")\n";
        if ($row['Field'] == 'streaming_method') {
            echo ">>> FOUND streaming_method COLUMN <<<\n";
        }
    }
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}
?>