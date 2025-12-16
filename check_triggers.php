<?php
require_once 'config.php';

try {
    $stmt = $pdo->query('SHOW TRIGGERS LIKE "live_streams"');
    echo "Triggers on live_streams table:\n";
    echo "===============================\n";
    while ($row = $stmt->fetch()) {
        print_r($row);
    }
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}
?>