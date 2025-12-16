<?php
require_once 'config.php';

try {
    // Check for indexes on invite_code and invite_expires_at
    $stmt = $pdo->query("SHOW INDEX FROM live_streams");
    echo "Indexes on live_streams table:\n";
    echo "=============================\n";
    
    $indexes = [];
    while ($row = $stmt->fetch()) {
        $indexes[] = $row;
        echo "Index: " . $row['Key_name'] . " on column " . $row['Column_name'] . "\n";
    }
    
    // Check specifically for indexes on invite_code and invite_expires_at
    $invite_indexes = [];
    foreach ($indexes as $index) {
        if (in_array($index['Column_name'], ['invite_code', 'invite_expires_at'])) {
            $invite_indexes[] = $index['Column_name'];
        }
    }
    
    echo "\nInvite-related indexes found: " . implode(', ', $invite_indexes) . "\n";
    
    // Check if we have the required indexes
    $required_indexed_columns = ['invite_code', 'invite_expires_at'];
    $missing_indexes = array_diff($required_indexed_columns, $invite_indexes);
    
    if (!empty($missing_indexes)) {
        echo "\nMissing indexes for columns: " . implode(', ', $missing_indexes) . "\n";
        echo "SQL to add missing indexes:\n";
        foreach ($missing_indexes as $column) {
            echo "CREATE INDEX idx_$column ON live_streams($column);\n";
        }
    } else {
        echo "\nAll required indexes are present.\n";
    }
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}
?>