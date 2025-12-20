<?php
require_once 'config.php';

try {
    // Check if WhatsApp already exists
    $stmt = $pdo->prepare("SELECT id FROM social_links WHERE name = ?");
    $stmt->execute(['WhatsApp']);
    $existing = $stmt->fetch();
    
    if ($existing) {
        echo "WhatsApp link already exists in database.\n";
    } else {
        // Add WhatsApp link
        $stmt = $pdo->prepare("INSERT INTO social_links (name, url, icon, is_active, sort_order) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute(['WhatsApp', 'https://wa.me/1234567890', 'fab fa-whatsapp', 1, 6]);
        echo "WhatsApp link added to database successfully!\n";
    }
    
    // Verify the addition
    $stmt = $pdo->prepare("SELECT * FROM social_links WHERE name = ?");
    $stmt->execute(['WhatsApp']);
    $whatsappLink = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($whatsappLink) {
        echo "Current WhatsApp link details:\n";
        echo "- Name: " . $whatsappLink['name'] . "\n";
        echo "- URL: " . $whatsappLink['url'] . "\n";
        echo "- Icon: " . $whatsappLink['icon'] . "\n";
        echo "- Active: " . ($whatsappLink['is_active'] ? 'Yes' : 'No') . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>