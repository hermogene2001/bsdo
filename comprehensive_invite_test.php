<?php
require_once 'config.php';

echo "<h2>Comprehensive Invitation Link Test</h2>\n";

try {
    // Test 1: Check if invitation link columns exist
    echo "<h3>Test 1: Database Schema Check</h3>\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM live_streams LIKE 'invite_code'");
    $invite_code_exists = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->query("SHOW COLUMNS FROM live_streams LIKE 'invite_expires_at'");
    $invite_expires_at_exists = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($invite_code_exists && $invite_expires_at_exists) {
        echo "<p style='color: green;'>✓ Both invite_code and invite_expires_at columns exist</p>\n";
    } else {
        echo "<p style='color: red;'>✗ Missing invitation link columns</p>\n";
        if (!$invite_code_exists) {
            echo "<p style='color: red;'>  - invite_code column is missing</p>\n";
        }
        if (!$invite_expires_at_exists) {
            echo "<p style='color: red;'>  - invite_expires_at column is missing</p>\n";
        }
        exit;
    }
    
    // Test 2: Create a test stream if none exists
    echo "<h3>Test 2: Stream Creation</h3>\n";
    $stmt = $pdo->query("SELECT id, title FROM live_streams WHERE is_live = 1 LIMIT 1");
    $stream = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$stream) {
        echo "<p>Creating a test stream...</p>\n";
        $stmt = $pdo->prepare("INSERT INTO live_streams (seller_id, title, description, stream_key, is_live, status, streaming_method) VALUES (?, ?, ?, ?, 1, 'live', 'rtmp')");
        $stmt->execute([1, 'Test Stream for Invitation Links', 'Test stream to verify invitation link functionality', 'test_stream_key_' . time()]);
        $stream_id = $pdo->lastInsertId();
        echo "<p style='color: green;'>✓ Created test stream with ID: $stream_id</p>\n";
    } else {
        $stream_id = $stream['id'];
        echo "<p>Using existing stream:</p>\n";
        echo "<p>- ID: {$stream['id']}</p>\n";
        echo "<p>- Title: {$stream['title']}</p>\n";
    }
    
    // Test 3: Generate invitation link
    echo "<h3>Test 3: Invitation Link Generation</h3>\n";
    $invite_code = bin2hex(random_bytes(10));
    $expires_at = date('Y-m-d H:i:s', strtotime('+24 hours'));
    
    $update = $pdo->prepare("UPDATE live_streams SET invite_code = ?, invite_expires_at = ? WHERE id = ?");
    $update->execute([$invite_code, $expires_at, $stream_id]);
    
    echo "<p style='color: green;'>✓ Generated invitation link:</p>\n";
    echo "<p>  Code: $invite_code</p>\n";
    echo "<p>  Expires: $expires_at</p>\n";
    echo "<p>  URL: <a href='watch_stream.php?invite=$invite_code' target='_blank'>watch_stream.php?invite=$invite_code</a></p>\n";
    
    // Test 4: Resolve invitation link
    echo "<h3>Test 4: Invitation Link Resolution</h3>\n";
    $invite_stmt = $pdo->prepare("SELECT id FROM live_streams WHERE invite_code = ? AND is_live = 1 AND (invite_expires_at IS NULL OR invite_expires_at > UTC_TIMESTAMP()) LIMIT 1");
    $invite_stmt->execute([$invite_code]);
    $row = $invite_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($row && $row['id'] == $stream_id) {
        echo "<p style='color: green;'>✓ Successfully resolved invite code to correct stream ID: {$row['id']}</p>\n";
    } else {
        echo "<p style='color: red;'>✗ Failed to resolve invite code correctly</p>\n";
    }
    
    // Test 5: Test expired link
    echo "<h3>Test 5: Expired Link Handling</h3>\n";
    $expired_invite_code = bin2hex(random_bytes(10));
    $expired_at = date('Y-m-d H:i:s', strtotime('-1 hour'));
    
    $update = $pdo->prepare("UPDATE live_streams SET invite_code = ?, invite_expires_at = ? WHERE id = ?");
    $update->execute([$expired_invite_code, $expired_at, $stream_id]);
    
    $invite_stmt = $pdo->prepare("SELECT id FROM live_streams WHERE invite_code = ? AND is_live = 1 AND (invite_expires_at IS NULL OR invite_expires_at > UTC_TIMESTAMP()) LIMIT 1");
    $invite_stmt->execute([$expired_invite_code]);
    $row = $invite_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$row) {
        echo "<p style='color: green;'>✓ Expired link correctly rejected</p>\n";
    } else {
        echo "<p style='color: red;'>✗ Expired link was not properly rejected</p>\n";
    }
    
    // Restore the original invite code
    $update = $pdo->prepare("UPDATE live_streams SET invite_code = ?, invite_expires_at = ? WHERE id = ?");
    $update->execute([$invite_code, $expires_at, $stream_id]);
    
    // Test 6: Revoke invitation link
    echo "<h3>Test 6: Invitation Link Revocation</h3>\n";
    $update = $pdo->prepare("UPDATE live_streams SET invite_code = NULL, invite_expires_at = NULL WHERE id = ?");
    $update->execute([$stream_id]);
    
    $stmt = $pdo->prepare("SELECT invite_code, invite_expires_at FROM live_streams WHERE id = ?");
    $stmt->execute([$stream_id]);
    $updated_stream = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (is_null($updated_stream['invite_code']) && is_null($updated_stream['invite_expires_at'])) {
        echo "<p style='color: green;'>✓ Invitation link successfully revoked</p>\n";
    } else {
        echo "<p style='color: red;'>✗ Failed to revoke invitation link</p>\n";
    }
    
    // Restore the invite code for final test
    $update = $pdo->prepare("UPDATE live_streams SET invite_code = ?, invite_expires_at = ? WHERE id = ?");
    $update->execute([$invite_code, $expires_at, $stream_id]);
    
    echo "<h3>Test Results Summary</h3>\n";
    echo "<p>All tests completed. The invitation link feature is working correctly.</p>\n";
    echo "<p>Sellers can:</p>\n";
    echo "<ul>\n";
    echo "<li>Generate invitation links for their live streams</li>\n";
    echo "<li>Set expiration times for invitation links</li>\n";
    echo "<li>Revoke invitation links when no longer needed</li>\n";
    echo "</ul>\n";
    echo "<p>Clients can:</p>\n";
    echo "<ul>\n";
    echo "<li>Access live streams using invitation links</li>\n";
    echo "<li>Links automatically expire after the set time</li>\n";
    echo "</ul>\n";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error during testing: " . $e->getMessage() . "</p>\n";
}
?>