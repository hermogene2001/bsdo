<?php
require_once 'config.php';

try {
    $stmt = $pdo->prepare("INSERT IGNORE INTO system_settings (setting_key, setting_value, description) VALUES ('payment_verification_rate', '0.50', 'Percentage of product price required for verification payment')");
    $stmt->execute();
    
    echo "Payment verification rate setting added successfully!\n";
    
    // Verify the setting was added
    $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'payment_verification_rate'");
    $stmt->execute();
    $setting = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($setting) {
        echo "Verification rate: " . $setting['setting_value'] . "\n";
    } else {
        echo "Setting not found\n";
    }
} catch (Exception $e) {
    echo "Error adding setting: " . $e->getMessage() . "\n";
}
?>