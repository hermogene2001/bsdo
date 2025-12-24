<?php
require_once 'config.php';

try {
    $stmt = $pdo->query('DESCRIBE products');
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Products Table Structure:\n";
    foreach ($result as $row) {
        echo "- Field: {$row['Field']}, Type: {$row['Type']}, Null: {$row['Null']}, Key: {$row['Key']}, Default: {$row['Default']}, Extra: {$row['Extra']}\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>