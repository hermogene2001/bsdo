<?php
require_once 'config.php';

try {
    // Check if invitation requirements are disabled for all streams
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM live_streams WHERE invitation_enabled = 1");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['count'] == 0) {
        echo "SUCCESS: All invitation requirements have been removed.\n";
        echo "Clients can now join all live streams directly without invitation codes.\n";
    } else {
        echo "WARNING: There are still " . $result['count'] . " streams with invitation requirements enabled.\n";
        echo "Running update to disable all invitation requirements...\n";
        
        // Disable invitation requirements for all streams
        $stmt = $pdo->prepare("UPDATE live_streams SET invitation_enabled = 0");
        $stmt->execute();
        
        echo "SUCCESS: All invitation requirements have been disabled.\n";
    }
    
    // Check the structure of the live_streams table
    $stmt = $pdo->query('DESCRIBE live_streams');
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\nLive Streams Table Structure:\n";
    echo "============================\n";
    foreach ($columns as $column) {
        echo $column['Field'] . " (" . $column['Type'] . ")\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>