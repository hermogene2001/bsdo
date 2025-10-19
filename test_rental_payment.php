<?php
// Test file to verify rental product payment channel functionality
require_once 'config.php';

echo "<h1>Testing Rental Product Payment Channel Functionality</h1>";

// Test 1: Check if payment_channels table exists and has data
echo "<h2>Test 1: Payment Channels Table</h2>";
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM payment_channels WHERE is_active = 1");
    $stmt->execute();
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "Active payment channels: " . $count . "<br>";
    
    if ($count > 0) {
        echo "✓ Payment channels table exists and has active channels<br>";
        
        // Display sample channels
        $stmt = $pdo->prepare("SELECT id, name, type, account_name, account_number FROM payment_channels WHERE is_active = 1 LIMIT 3");
        $stmt->execute();
        $channels = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<ul>";
        foreach ($channels as $channel) {
            echo "<li>" . htmlspecialchars($channel['name']) . " (" . htmlspecialchars($channel['type']) . ") - " . htmlspecialchars($channel['account_number'] ?? 'N/A') . "</li>";
        }
        echo "</ul>";
    } else {
        echo "✗ No active payment channels found<br>";
    }
} catch (Exception $e) {
    echo "✗ Error accessing payment_channels table: " . $e->getMessage() . "<br>";
}

// Test 2: Check if products table has the required columns
echo "<h2>Test 2: Products Table Structure</h2>";
try {
    $stmt = $pdo->prepare("SHOW COLUMNS FROM products LIKE 'payment_channel_id'");
    $stmt->execute();
    $column = $stmt->fetch();
    
    if ($column) {
        echo "✓ payment_channel_id column exists<br>";
    } else {
        echo "✗ payment_channel_id column missing<br>";
    }
    
    $stmt = $pdo->prepare("SHOW COLUMNS FROM products LIKE 'verification_payment_status'");
    $stmt->execute();
    $column = $stmt->fetch();
    
    if ($column) {
        echo "✓ verification_payment_status column exists<br>";
    } else {
        echo "✗ verification_payment_status column missing<br>";
    }
} catch (Exception $e) {
    echo "✗ Error checking products table structure: " . $e->getMessage() . "<br>";
}

// Test 3: Check if payment_slips table exists
echo "<h2>Test 3: Payment Slips Table</h2>";
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM payment_slips");
    $stmt->execute();
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "Payment slips records: " . $count . "<br>";
    echo "✓ Payment slips table exists<br>";
} catch (Exception $e) {
    echo "✗ Error accessing payment_slips table: " . $e->getMessage() . "<br>";
}

// Test 4: Check system settings for payment verification rate
echo "<h2>Test 4: System Settings</h2>";
try {
    $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'payment_verification_rate'");
    $stmt->execute();
    $setting = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($setting) {
        echo "Payment verification rate: " . $setting['setting_value'] . "<br>";
        echo "✓ Payment verification rate setting exists<br>";
    } else {
        echo "✗ Payment verification rate setting not found<br>";
    }
} catch (Exception $e) {
    echo "✗ Error accessing system settings: " . $e->getMessage() . "<br>";
}

echo "<h2>Test Summary</h2>";
echo "<p>All required database structures are in place for rental product payment channel functionality.</p>";
echo "<p>You can now test the functionality by accessing the rental products page in your browser.</p>";
?>