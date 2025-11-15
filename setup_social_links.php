<?php
require_once 'config.php';

// Read the SQL file
$sql = file_get_contents('create_social_links_table.sql');

try {
    // Execute the SQL
    $pdo->exec($sql);
    echo "Social links table created successfully!\n";
} catch (Exception $e) {
    echo "Error creating table: " . $e->getMessage() . "\n";
}
?>