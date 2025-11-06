<?php
require_once 'config.php';

try {
    // Check if the invite_code and invite_expires_at columns exist
    $stmt = $pdo->query("SHOW COLUMNS FROM live_streams LIKE 'invite_code'");
    $invite_code_exists = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->query("SHOW COLUMNS FROM live_streams LIKE 'invite_expires_at'");
    $invite_expires_at_exists = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($invite_code_exists && $invite_expires_at_exists) {
        echo "✓ Both invite_code and invite_expires_at columns exist in live_streams table\n";
        
        // Check if there are any streams with invitation links
        $stmt = $pdo->query("SELECT id, title, invite_code, invite_expires_at FROM live_streams WHERE invite_code IS NOT NULL LIMIT 5");
        $streams = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($streams)) {
            echo "Found streams with invitation links:\n";
            foreach ($streams as $stream) {
                echo "- Stream ID: {$stream['id']}, Title: {$stream['title']}, Invite Code: {$stream['invite_code']}\n";
            }
        } else {
            echo "No streams with invitation links found\n";
        }
    } else {
        echo "✗ Missing invitation link columns in live_streams table\n";
        if (!$invite_code_exists) {
            echo "  - invite_code column is missing\n";
        }
        if (!$invite_expires_at_exists) {
            echo "  - invite_expires_at column is missing\n";
        }
    }
    
} catch (Exception $e) {
    echo "Error checking database: " . $e->getMessage() . "\n";
}
?>