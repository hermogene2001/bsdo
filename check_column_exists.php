<?php
require_once 'config.php';

try {
    $stmt = $pdo->query('SHOW COLUMNS FROM live_streams LIKE "streaming_method"');
    if ($stmt->fetch()) {
        echo "Column streaming_method exists\n";
    } else {
        echo "Column streaming_method does NOT exist\n";
    }
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}
?>