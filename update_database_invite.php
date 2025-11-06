<?php
require_once 'config.php';

echo "Updating database to add invitation link fields...\n";

try {
    try {
        // Add invitation_code column
        $stmt = $pdo->prepare("ALTER TABLE live_streams ADD COLUMN invitation_code VARCHAR(32) AFTER stream_key");
        $stmt->execute();
        echo "✓ Added invitation_code column\n";
    } catch (Exception $e) {
        echo "Note: invitation_code column might already exist\n";
    }
    
    try {
        // Add invitation_enabled column
        $stmt = $pdo->prepare("ALTER TABLE live_streams ADD COLUMN invitation_enabled BOOLEAN DEFAULT true AFTER invitation_code");
        $stmt->execute();
        echo "✓ Added invitation_enabled column\n";
    } catch (Exception $e) {
        echo "Note: invitation_enabled column might already exist\n";
    }
    
    try {
        // Add invitation_expiry column
        $stmt = $pdo->prepare("ALTER TABLE live_streams ADD COLUMN invitation_expiry DATETIME DEFAULT NULL AFTER invitation_enabled");
        $stmt->execute();
        echo "✓ Added invitation_expiry column\n";
    } catch (Exception $e) {
        echo "Note: invitation_expiry column might already exist\n";
    }
    
    try {
        // Create index for invitation_code
        $stmt = $pdo->prepare("CREATE INDEX idx_invitation_code ON live_streams(invitation_code)");
        $stmt->execute();
        echo "✓ Created index for invitation_code\n";
    } catch (Exception $e) {
        echo "Note: index for invitation_code might already exist\n";
    }
    
    // Update existing streams with default invitation codes
    $stmt = $pdo->prepare("UPDATE live_streams SET invitation_code = CONCAT('INIT_', SUBSTRING(MD5(RAND()) FROM 1 FOR 8)) WHERE invitation_code IS NULL");
    $stmt->execute();
    echo "✓ Updated existing streams with default invitation codes\n";
    
    echo "✓ Database update completed successfully!\n";
    
} catch (Exception $e) {
    echo "Error updating database: " . $e->getMessage() . "\n";
}
?>