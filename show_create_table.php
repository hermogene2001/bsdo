<?php
require_once 'config.php';

try {
    $stmt = $pdo->query('SHOW CREATE TABLE live_streams');
    $row = $stmt->fetch();
    echo "CREATE TABLE statement for live_streams:\n";
    echo "========================================\n";
    echo $row['Create Table'] . ";\n";
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}
?>