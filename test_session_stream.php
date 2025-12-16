<?php
session_start();

// Simulate session data like a logged-in seller
$_SESSION['user_id'] = 1;
$_SESSION['user_role'] = 'seller';

require_once 'config.php';

try {
    echo "Testing stream start with session data...\n";
    
    // Simulate POST data like the form would send
    $_POST['action'] = 'start_stream';
    $_POST['title'] = 'Session Test Stream';
    $_POST['description'] = 'Test stream with session data';
    $_POST['category_id'] = 1;
    $_POST['streaming_method'] = 'rtmp';
    $_POST['scheduled_datetime'] = '';
    
    // Extract data like live_stream.php does
    $seller_id = $_SESSION['user_id'];
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $category_id = intval($_POST['category_id']);
    $streaming_method = $_POST['streaming_method'] ?? 'rtmp';
    $scheduled_datetime = trim($_POST['scheduled_datetime']);
    
    // Generate stream key and invitation code
    $stream_key = 'stream_' . $seller_id . '_' . time() . '_' . bin2hex(random_bytes(8));
    $invitation_code = bin2hex(random_bytes(8));
    $scheduled_at = $scheduled_datetime ? date('Y-m-d H:i:s', strtotime($scheduled_datetime)) : null;
    
    echo "Data to insert:\n";
    echo "- seller_id: $seller_id\n";
    echo "- title: $title\n";
    echo "- description: $description\n";
    echo "- category_id: $category_id\n";
    echo "- stream_key: $stream_key\n";
    echo "- invitation_code: $invitation_code\n";
    echo "- scheduled_at: " . ($scheduled_at ? $scheduled_at : "null") . "\n";
    echo "- streaming_method: $streaming_method\n";
    
    // Execute the exact same INSERT statement as in live_stream.php
    $stmt = $pdo->prepare("
        INSERT INTO live_streams (seller_id, title, description, category_id, stream_key, invitation_code, invitation_enabled, invitation_expiry, status, scheduled_at, streaming_method) 
        VALUES (?, ?, ?, ?, ?, ?, true, NULL, 'scheduled', ?, ?)
    ");
    
    $result = $stmt->execute([$seller_id, $title, $description, $category_id, $stream_key, $invitation_code, $scheduled_at, $streaming_method]);
    
    if ($result) {
        echo "✓ Session-based INSERT successful\n";
        $stream_id = $pdo->lastInsertId();
        echo "Stream ID: $stream_id\n";
        
        // Clean up
        $pdo->prepare("DELETE FROM live_streams WHERE id = ?")->execute([$stream_id]);
        echo "✓ Test record cleaned up\n";
    }
    
} catch (PDOException $e) {
    echo "✗ Session-based streaming error:\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "Code: " . $e->getCode() . "\n";
} catch (Exception $e) {
    echo "✗ General error:\n";
    echo "Message: " . $e->getMessage() . "\n";
}
?>