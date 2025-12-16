<?php
require_once 'config.php';

try {
    echo "Applying invitation link column fixes...\n";
    
    // Read the SQL file
    $sql = file_get_contents('fix_invite_columns.sql');
    
    // Split the SQL into individual statements
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    foreach ($statements as $statement) {
        if (!empty($statement)) {
            try {
                $pdo->exec($statement);
                echo "✓ Executed: " . substr($statement, 0, 50) . "...\n";
            } catch (PDOException $e) {
                // Ignore errors about columns that already exist
                if (strpos($e->getMessage(), 'Duplicate column name') === false && 
                    strpos($e->getMessage(), 'duplicate key name') === false) {
                    echo "⚠ Warning: " . $e->getMessage() . "\n";
                } else {
                    echo "ℹ Info: " . substr($statement, 0, 50) . "... (already exists)\n";
                }
            }
        }
    }
    
    echo "\n✓ All invitation link column fixes applied successfully!\n";
    
} catch (PDOException $e) {
    echo "✗ Database error: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}
?>