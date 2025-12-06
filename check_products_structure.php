<?php
require_once 'config.php';

try {
    $stmt = $pdo->query('DESCRIBE products');
    echo "Products table structure:\n";
    echo "========================\n";
    while ($row = $stmt->fetch()) {
        echo $row['Field'] . " (" . $row['Type'] . ")\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>