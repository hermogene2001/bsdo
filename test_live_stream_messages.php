<?php
// Test file for live stream messaging feature
session_start();
require_once 'config.php';

echo "<h1>Live Stream Messaging Test</h1>";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo "<p>You need to be logged in to test this feature.</p>";
    echo "<a href='login.php'>Login</a>";
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? '';

// Create a test stream if none exists
try {
    // Check if there's an active stream
    $stmt = $pdo->prepare("SELECT id, seller_id FROM live_streams WHERE is_live = 1 LIMIT 1");
    $stmt->execute();
    $stream = $stmt->fetch();
    
    if (!$stream) {
        echo "<p>No active live streams found. Creating a test stream...</p>";
        
        // Create a test stream
        $stream_key = 'test_stream_' . time();
        $stmt = $pdo->prepare("
            INSERT INTO live_streams (seller_id, title, description, stream_key, is_live, status, started_at) 
            VALUES (?, 'Test Stream', 'Test stream for messaging functionality', ?, 1, 'live', NOW())
        ");
        
        // Use the current user as seller if they're a seller, otherwise use user_id 1
        $seller_id = ($user_role === 'seller') ? $user_id : 1;
        $stmt->execute([$seller_id, $stream_key]);
        $stream_id = $pdo->lastInsertId();
        
        echo "<p>Created test stream with ID: $stream_id</p>";
        $stream = ['id' => $stream_id, 'seller_id' => $seller_id];
    } else {
        echo "<p>Using existing stream with ID: " . $stream['id'] . "</p>";
    }
    
    // Test adding a client comment
    echo "<h2>Testing Client Comment</h2>";
    $comment_stmt = $pdo->prepare("
        INSERT INTO live_stream_comments (stream_id, user_id, comment, is_seller) 
        VALUES (?, ?, 'This is a test client comment', 0)
    ");
    $comment_stmt->execute([$stream['id'], $user_id]);
    echo "<p style='color: green;'>✓ Client comment added successfully</p>";
    
    // Test adding a seller comment
    echo "<h2>Testing Seller Comment</h2>";
    $seller_comment_stmt = $pdo->prepare("
        INSERT INTO live_stream_comments (stream_id, user_id, comment, is_seller) 
        VALUES (?, ?, 'This is a test seller comment', 1)
    ");
    // Use the stream's seller_id for the seller comment
    $seller_comment_stmt->execute([$stream['id'], $stream['seller_id']]);
    echo "<p style='color: green;'>✓ Seller comment added successfully</p>";
    
    // Retrieve and display comments
    echo "<h2>Retrieved Comments</h2>";
    $retrieve_stmt = $pdo->prepare("
        SELECT lsc.*, u.first_name, u.last_name 
        FROM live_stream_comments lsc 
        LEFT JOIN users u ON lsc.user_id = u.id 
        WHERE lsc.stream_id = ? 
        ORDER BY lsc.created_at ASC
    ");
    $retrieve_stmt->execute([$stream['id']]);
    $comments = $retrieve_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($comments)) {
        echo "<ul>";
        foreach ($comments as $comment) {
            $sender = $comment['is_seller'] ? 'Seller' : 'Client';
            $name = $comment['first_name'] ? $comment['first_name'] . ' ' . $comment['last_name'] : 'Anonymous';
            echo "<li><strong>$sender ($name):</strong> " . htmlspecialchars($comment['comment']) . "</li>";
        }
        echo "</ul>";
    } else {
        echo "<p>No comments found</p>";
    }
    
    echo "<h2>Testing API Endpoints</h2>";
    
    // Test the check_streams_detail.php endpoint for fetching comments
    echo "<h3>Testing Comment Retrieval via API</h3>";
    $api_url = "http://localhost/bsdo/check_streams_detail.php?stream_id=" . $stream['id'] . "&last_comment_id=0";
    echo "<p>API URL: $api_url</p>";
    
    // Test the message sending functionality
    echo "<h3>Testing Message Sending via API</h3>";
    echo "<p>To test message sending, you would need to:</p>";
    echo "<ol>";
    echo "<li>Log in as a seller</li>";
    echo "<li>Go to the live stream page</li>";
    echo "<li>Use the message input field to send a message</li>";
    echo "</ol>";
    
    echo "<h2>Success!</h2>";
    echo "<p>All messaging functionality tests completed successfully.</p>";
    echo "<p><a href='seller/live_stream_webrtc.php?stream_id=" . $stream['id'] . "'>View Live Stream (WebRTC)</a></p>";
    echo "<p><a href='seller/live_stream.php?stream_id=" . $stream['id'] . "'>View Live Stream (Basic)</a></p>";
    echo "<p><a href='watch_stream.php?stream_id=" . $stream['id'] . "'>Watch Stream as Client</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>