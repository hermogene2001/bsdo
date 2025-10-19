<?php
// Setup script for live streaming functionality
// Run this script to create the necessary database tables

require_once 'includes/db.php';

echo "<h2>Setting up Live Streaming Database Tables...</h2>";

try {
    // Read and execute the SQL schema
    $sql = file_get_contents('database_schema.sql');
    
    // Split by semicolon and execute each statement
    $statements = explode(';', $sql);
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (!empty($statement)) {
            $pdo->exec($statement);
            echo "<p>✓ Executed: " . substr($statement, 0, 50) . "...</p>";
        }
    }
    
    echo "<h3 style='color: green;'>✓ Live streaming database setup completed successfully!</h3>";
    echo "<p>You can now:</p>";
    echo "<ul>";
    echo "<li>Access seller live streaming at: <a href='seller/live_stream.php'>seller/live_stream.php</a></li>";
    echo "<li>View live streams at: <a href='live_streams.php'>live_streams.php</a></li>";
    echo "<li>Watch individual streams at: <a href='watch_stream.php'>watch_stream.php</a></li>";
    echo "</ul>";
    
} catch (Exception $e) {
    echo "<h3 style='color: red;'>✗ Error setting up database:</h3>";
    echo "<p>" . $e->getMessage() . "</p>";
}
?>


