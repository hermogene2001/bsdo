<?php
require_once 'config.php';

try {
    $stmt = $pdo->query('SHOW DATABASES');
    echo "Available databases:\n";
    echo "==================\n";
    while ($row = $stmt->fetch()) {
        echo $row[0] . "\n";
    }
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}
?>