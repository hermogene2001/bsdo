<?php
// Update database schema to support both RTMP/HLS and WebRTC streaming methods
require_once 'config.php';

try {
    echo "Updating database schema to support streaming method selection...\n";
    
    // Add streaming_method column to live_streams table
    try {
        $stmt = $pdo->prepare("ALTER TABLE live_streams ADD COLUMN streaming_method ENUM('rtmp', 'webrtc') DEFAULT 'rtmp' AFTER status");
        $stmt->execute();
        echo "✓ Added streaming_method column to live_streams table\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "Note: streaming_method column already exists\n";
        } else {
            throw $e;
        }
    }
    
    // Add connection_status column to live_streams table for WebRTC connection tracking
    try {
        $stmt = $pdo->prepare("ALTER TABLE live_streams ADD COLUMN connection_status VARCHAR(50) DEFAULT 'offline' AFTER streaming_method");
        $stmt->execute();
        echo "✓ Added connection_status column to live_streams table\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "Note: connection_status column already exists\n";
        } else {
            throw $e;
        }
    }
    
    // Update existing streams to set appropriate streaming method
    try {
        $stmt = $pdo->prepare("UPDATE live_streams SET streaming_method = 'rtmp' WHERE streaming_method IS NULL");
        $stmt->execute();
        echo "✓ Updated existing streams to use RTMP as default streaming method\n";
    } catch (PDOException $e) {
        echo "Warning: Could not update existing streams - " . $e->getMessage() . "\n";
    }
    
    echo "Database schema update completed successfully!\n";
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>