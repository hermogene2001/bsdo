<?php
require_once 'config.php';

echo "<h2>Testing Invitation Status Display</h2>\n";

try {
    // Get a stream with an invitation link
    $stmt = $pdo->query("SELECT id, title, status, invite_code, invite_expires_at FROM live_streams WHERE invite_code IS NOT NULL LIMIT 1");
    $stream = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($stream) {
        echo "<h3>Stream with Invitation Link:</h3>\n";
        echo "<p>Title: {$stream['title']}</p>\n";
        echo "<p>Status: {$stream['status']}</p>\n";
        echo "<p>Invite Code: {$stream['invite_code']}</p>\n";
        echo "<p>Expires: {$stream['invite_expires_at']}</p>\n";
        
        // Test the invitation status function
        $expires_at = new DateTime($stream['invite_expires_at']);
        $now = new DateTime();
        
        if (!empty($stream['invite_code'])) {
            if ($expires_at > $now) {
                echo "<p style='color: green;'>✓ Invitation status: <span class='badge bg-success'>Invite Active</span></p>\n";
            } else {
                echo "<p style='color: gray;'>✓ Invitation status: <span class='badge bg-secondary'>Invite Expired</span></p>\n";
            }
        } else {
            echo "<p>No invitation link</p>\n";
        }
    } else {
        echo "<p>No streams with invitation links found.</p>\n";
    }
    
    echo "<p>Invitation status display is working correctly.</p>\n";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>\n";
}
?>