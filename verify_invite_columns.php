<?php
require_once 'config.php';

try {
    echo "Verifying invitation link columns...\n";
    
    // Check if the required columns exist
    $required_columns = ['invite_code', 'invite_expires_at'];
    
    foreach ($required_columns as $column) {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM live_streams LIKE ?");
        $stmt->execute([$column]);
        
        if ($stmt->fetch()) {
            echo "✓ Column '$column' exists\n";
        } else {
            echo "✗ Column '$column' is missing\n";
        }
    }
    
    // Check if the required indexes exist
    $required_indexes = ['invite_code', 'invite_expires_at'];
    
    foreach ($required_indexes as $column) {
        $stmt = $pdo->prepare("SHOW INDEX FROM live_streams WHERE Column_name = ?");
        $stmt->execute([$column]);
        
        if ($stmt->fetch()) {
            echo "✓ Index on column '$column' exists\n";
        } else {
            echo "✗ Index on column '$column' is missing\n";
        }
    }
    
    echo "\n✓ Verification complete!\n";
    
} catch (PDOException $e) {
    echo "✗ Database error: " . $e->getMessage() . "\n";
}
?>