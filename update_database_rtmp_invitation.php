<?php
// Update database schema for RTMP/HLS streaming with invitation code support
require_once 'config.php';

try {
    echo "Updating database schema for RTMP/HLS streaming...\n";
    
    // Add invitation_code column if it doesn't exist
    try {
        $stmt = $pdo->prepare("ALTER TABLE live_streams ADD COLUMN invitation_code VARCHAR(32) AFTER stream_key");
        $stmt->execute();
        echo "✓ Added invitation_code column\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "Note: invitation_code column already exists\n";
        } else {
            throw $e;
        }
    }
    
    // Add invitation_enabled column if it doesn't exist
    try {
        $stmt = $pdo->prepare("ALTER TABLE live_streams ADD COLUMN invitation_enabled BOOLEAN DEFAULT true AFTER invitation_code");
        $stmt->execute();
        echo "✓ Added invitation_enabled column\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "Note: invitation_enabled column already exists\n";
        } else {
            throw $e;
        }
    }
    
    // Add invitation_expiry column if it doesn't exist
    try {
        $stmt = $pdo->prepare("ALTER TABLE live_streams ADD COLUMN invitation_expiry DATETIME DEFAULT NULL AFTER invitation_enabled");
        $stmt->execute();
        echo "✓ Added invitation_expiry column\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "Note: invitation_expiry column already exists\n";
        } else {
            throw $e;
        }
    }
    
    // Add rtmp_url column if it doesn't exist
    try {
        $stmt = $pdo->prepare("ALTER TABLE live_streams ADD COLUMN rtmp_url VARCHAR(500) DEFAULT NULL AFTER invitation_expiry");
        $stmt->execute();
        echo "✓ Added rtmp_url column\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "Note: rtmp_url column already exists\n";
        } else {
            throw $e;
        }
    }
    
    // Add hls_url column if it doesn't exist
    try {
        $stmt = $pdo->prepare("ALTER TABLE live_streams ADD COLUMN hls_url VARCHAR(500) DEFAULT NULL AFTER rtmp_url");
        $stmt->execute();
        echo "✓ Added hls_url column\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "Note: hls_url column already exists\n";
        } else {
            throw $e;
        }
    }
    
    // Create index for invitation_code if it doesn't exist
    try {
        $stmt = $pdo->prepare("CREATE INDEX idx_invitation_code ON live_streams(invitation_code)");
        $stmt->execute();
        echo "✓ Created index for invitation_code\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
            echo "Note: index for invitation_code already exists\n";
        } else {
            throw $e;
        }
    }
    
    // Update existing records to have invitation codes if they don't already
    $stmt = $pdo->prepare("UPDATE live_streams SET invitation_code = CONCAT('INIT_', SUBSTRING(MD5(RAND()) FROM 1 FOR 8)) WHERE invitation_code IS NULL");
    $stmt->execute();
    $updated = $stmt->rowCount();
    if ($updated > 0) {
        echo "✓ Generated invitation codes for $updated existing streams\n";
    } else {
        echo "Note: No existing streams needed invitation codes\n";
    }
    
    echo "Database update completed successfully!\n";
    
} catch (PDOException $e) {
    echo "Error updating database: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>