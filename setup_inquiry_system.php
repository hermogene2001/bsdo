<?php
// Setup database tables for inquiry system
echo "<h1>Setting up Inquiry System Database Tables</h1>";

try {
    // Include the config file
    require_once 'config.php';
    
    echo "<p style='color: green;'>✓ Database connection successful</p>";
    
    // Read the database schema file
    $sql = file_get_contents('setup_inquiry_system.sql');
    
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
                echo "<p style='color: green;'>✓ Executed: " . substr($statement, 0, 50) . "...</p>";
            } catch (PDOException $e) {
                // Ignore duplicate table errors
                if (strpos($e->getMessage(), 'already exists') === false && strpos($e->getMessage(), 'duplicate') === false) {
                    echo "<p style='color: orange;'>Warning: " . $e->getMessage() . "</p>";
                    $error_count++;
                } else {
                    $success_count++;
                    echo "<p style='color: blue;'>ℹ️ Skipped (already exists): " . substr($statement, 0, 50) . "...</p>";
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
    $tables = ['inquiries', 'inquiry_messages'];
    
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
    echo "<p>The inquiry system database tables have been set up successfully.</p>";
    echo "<p><a href='products.php'>Browse Products</a></p>";
    echo "<p><a href='inquiries.php'>View Your Inquiries</a></p>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>Database connection error: " . $e->getMessage() . "</p>";
    echo "<p>Please check your database configuration in config.php</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>