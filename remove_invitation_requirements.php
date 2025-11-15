<?php
require_once 'config.php';

try {
    // Disable invitation requirements for all streams
    $stmt = $pdo->prepare("UPDATE live_streams SET invitation_enabled = 0");
    $stmt->execute();
    
    echo "Successfully disabled invitation requirements for all live streams.\n";
    echo "All clients can now join streams directly without invitation codes.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>