<?php
// Enable detailed error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

try {
    echo "Testing with detailed error reporting...\n";
    
    // Enable PDO error mode to exception
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Test the exact scenario that might be failing
    $seller_id = 1;
    $title = str_repeat("A", 300); // This might be too long for the title field
    $description = "Test Description";
    $category_id = 1;
    $stream_key = "test_stream_key_" . time();
    $invitation_code = bin2hex(random_bytes(8));
    $scheduled_at = null;
    $streaming_method = "invalid_method"; // This might cause an error
    
    echo "Attempting INSERT with potentially problematic data...\n";
    
    $stmt = $pdo->prepare("
        INSERT INTO live_streams (seller_id, title, description, category_id, stream_key, invitation_code, invitation_enabled, invitation_expiry, status, scheduled_at, streaming_method) 
        VALUES (?, ?, ?, ?, ?, ?, true, NULL, 'scheduled', ?, ?)
    ");
    
    $result = $stmt->execute([$seller_id, $title, $description, $category_id, $stream_key, $invitation_code, $scheduled_at, $streaming_method]);
    
    if ($result) {
        echo "✓ INSERT successful (unexpected!)\n";
        $stream_id = $pdo->lastInsertId();
        $pdo->prepare("DELETE FROM live_streams WHERE id = ?")->execute([$stream_id]);
    }
    
} catch (PDOException $e) {
    echo "✗ Detailed PDO error:\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "Code: " . $e->getCode() . "\n";
    if (isset($e->errorInfo)) {
        echo "SQL State: " . $e->errorInfo[0] . "\n";
        echo "Driver Error Code: " . $e->errorInfo[1] . "\n";
        echo "Driver Error Message: " . $e->errorInfo[2] . "\n";
    }
} catch (Exception $e) {
    echo "✗ General error:\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "Code: " . $e->getCode() . "\n";
}

// Test with valid data
try {
    echo "\nTesting with valid data...\n";
    
    $seller_id = 1;
    $title = "Valid Test Stream";
    $description = "Test Description";
    $category_id = 1;
    $stream_key = "valid_test_stream_key_" . time();
    $invitation_code = bin2hex(random_bytes(8));
    $scheduled_at = null;
    $streaming_method = "rtmp";
    
    $stmt = $pdo->prepare("
        INSERT INTO live_streams (seller_id, title, description, category_id, stream_key, invitation_code, invitation_enabled, invitation_expiry, status, scheduled_at, streaming_method) 
        VALUES (?, ?, ?, ?, ?, ?, true, NULL, 'scheduled', ?, ?)
    ");
    
    $result = $stmt->execute([$seller_id, $title, $description, $category_id, $stream_key, $invitation_code, $scheduled_at, $streaming_method]);
    
    if ($result) {
        echo "✓ Valid INSERT successful\n";
        $stream_id = $pdo->lastInsertId();
        $pdo->prepare("DELETE FROM live_streams WHERE id = ?")->execute([$stream_id]);
        echo "✓ Test record cleaned up\n";
    }
    
} catch (PDOException $e) {
    echo "✗ Valid data also failed:\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "Code: " . $e->getCode() . "\n";
}
?>