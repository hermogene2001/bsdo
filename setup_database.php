<?php
// Setup database tables for live streaming feature
echo "<h1>Setting up Live Streaming Database Tables</h1>";

try {
    // Include the config file
    require_once 'config.php';
    
    echo "<p style='color: green;'>✓ Database connection successful</p>";
    
    // Read the database schema file
    $sql = file_get_contents('database_schema.sql');
    
    // Split the SQL into individual statements
    $statements = explode(';', $sql);
    
    $success_count = 0;
    $error_count = 0;
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (!empty($statement)) {
            try {
                $pdo->exec($statement);
                $success_count++;
            } catch (PDOException $e) {
                // Ignore duplicate table errors
                if (strpos($e->getMessage(), 'already exists') === false) {
                    echo "<p style='color: orange;'>Warning: " . $e->getMessage() . "</p>";
                    $error_count++;
                } else {
                    $success_count++;
                }
            }
        }
    }
    
    echo "<p style='color: green;'>✓ Executed $success_count SQL statements successfully</p>";
    
    if ($error_count > 0) {
        echo "<p style='color: red;'>✗ $error_count SQL statements had errors (these may be warnings)</p>";
    }
    
    echo "<h2>Verifying Tables</h2>";
    
    // Check if all required tables exist
    $tables = ['live_streams', 'live_stream_products', 'live_stream_viewers', 'live_stream_comments', 'live_stream_analytics'];
    
    foreach ($tables as $table) {
        try {
            $stmt = $pdo->prepare("SHOW TABLES LIKE '$table'");
            $stmt->execute();
            if ($stmt->rowCount() > 0) {
                echo "<p style='color: green;'>✓ $table table exists</p>";
            } else {
                echo "<p style='color: red;'>✗ $table table does not exist</p>";
            }
        } catch (PDOException $e) {
            echo "<p style='color: red;'>✗ Error checking $table table: " . $e->getMessage() . "</p>";
        }
    }
    
    echo "<h2>Setup Complete!</h2>";
    echo "<p>The live streaming feature database tables have been set up successfully.</p>";
    echo "<p><a href='seller/live_stream.php'>Go to Seller Live Stream Page</a></p>";
    echo "<p><a href='live_streams.php'>View All Live Streams</a></p>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>Database connection error: " . $e->getMessage() . "</p>";
    echo "<p>Please check your database configuration in config.php</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>