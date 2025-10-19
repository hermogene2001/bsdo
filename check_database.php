<?php
// Check database connection and required tables
echo "<h1>Database Connection and Schema Check</h1>";

try {
    // Include the config file
    require_once 'config.php';
    
    echo "<p style='color: green;'>✓ Database connection successful</p>";
    
    // Check if live_streams table exists
    $stmt = $pdo->prepare("SHOW TABLES LIKE 'live_streams'");
    $stmt->execute();
    if ($stmt->rowCount() > 0) {
        echo "<p style='color: green;'>✓ live_streams table exists</p>";
    } else {
        echo "<p style='color: red;'>✗ live_streams table does not exist</p>";
        echo "<p>Please run the database schema script to create required tables</p>";
    }
    
    // Check if live_stream_products table exists
    $stmt = $pdo->prepare("SHOW TABLES LIKE 'live_stream_products'");
    $stmt->execute();
    if ($stmt->rowCount() > 0) {
        echo "<p style='color: green;'>✓ live_stream_products table exists</p>";
    } else {
        echo "<p style='color: red;'>✗ live_stream_products table does not exist</p>";
        echo "<p>Please run the database schema script to create required tables</p>";
    }
    
    // Check if live_stream_viewers table exists
    $stmt = $pdo->prepare("SHOW TABLES LIKE 'live_stream_viewers'");
    $stmt->execute();
    if ($stmt->rowCount() > 0) {
        echo "<p style='color: green;'>✓ live_stream_viewers table exists</p>";
    } else {
        echo "<p style='color: red;'>✗ live_stream_viewers table does not exist</p>";
    }
    
    // Check if live_stream_comments table exists
    $stmt = $pdo->prepare("SHOW TABLES LIKE 'live_stream_comments'");
    $stmt->execute();
    if ($stmt->rowCount() > 0) {
        echo "<p style='color: green;'>✓ live_stream_comments table exists</p>";
    } else {
        echo "<p style='color: red;'>✗ live_stream_comments table does not exist</p>";
    }
    
    // Check if live_stream_analytics table exists
    $stmt = $pdo->prepare("SHOW TABLES LIKE 'live_stream_analytics'");
    $stmt->execute();
    if ($stmt->rowCount() > 0) {
        echo "<p style='color: green;'>✓ live_stream_analytics table exists</p>";
    } else {
        echo "<p style='color: red;'>✗ live_stream_analytics table does not exist</p>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>Database connection error: " . $e->getMessage() . "</p>";
    echo "<p>Please check your database configuration in config.php</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>