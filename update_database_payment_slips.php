<?php
require_once 'config.php';

try {
    // Read the SQL file
    $sql = file_get_contents('create_payment_slips_table.sql');
    
    // Execute the SQL
    $pdo->exec($sql);
    
    echo "Payment slips table created successfully!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>