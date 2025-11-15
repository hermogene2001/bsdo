<?php
require_once 'config.php';

try {
    $stmt = $pdo->query('DESCRIBE live_streams');
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Live Streams Table Structure:\n";
    echo "============================\n";
    foreach ($columns as $column) {
        echo $column['Field'] . " (" . $column['Type'] . ")\n";
    }
    
    // Check if invitation columns exist
    $has_invitation_code = false;
    $has_invitation_enabled = false;
    $has_invitation_expiry = false;
    
    foreach ($columns as $column) {
        if ($column['Field'] === 'invitation_code') {
            $has_invitation_code = true;
        }
        if ($column['Field'] === 'invitation_enabled') {
            $has_invitation_enabled = true;
        }
        if ($column['Field'] === 'invitation_expiry') {
            $has_invitation_expiry = true;
        }
    }
    
    echo "\nInvitation Columns Check:\n";
    echo "========================\n";
    echo "invitation_code: " . ($has_invitation_code ? "YES" : "NO") . "\n";
    echo "invitation_enabled: " . ($has_invitation_enabled ? "YES" : "NO") . "\n";
    echo "invitation_expiry: " . ($has_invitation_expiry ? "YES" : "NO") . "\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>