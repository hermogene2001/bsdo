<?php
require_once 'config.php';

try {
    // Check for invite-related columns
    $stmt = $pdo->query('SHOW COLUMNS FROM live_streams');
    echo "All columns in live_streams table:\n";
    echo "==================================\n";
    
    $invite_columns = [];
    while ($row = $stmt->fetch()) {
        echo $row['Field'] . " (" . $row['Type'] . ")\n";
        if (strpos($row['Field'], 'invite') !== false) {
            $invite_columns[] = $row['Field'];
        }
    }
    
    echo "\nInvite-related columns found: " . implode(', ', $invite_columns) . "\n";
    
    // Check specifically for the missing columns
    $missing_columns = [];
    $required_columns = ['invite_code', 'invite_expires_at'];
    
    foreach ($required_columns as $column) {
        $check_stmt = $pdo->query("SHOW COLUMNS FROM live_streams LIKE '$column'");
        if (!$check_stmt->fetch()) {
            $missing_columns[] = $column;
        }
    }
    
    if (!empty($missing_columns)) {
        echo "\nMissing columns: " . implode(', ', $missing_columns) . "\n";
    } else {
        echo "\nAll required invite columns are present.\n";
    }
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}
?>