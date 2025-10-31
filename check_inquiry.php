<?php
require_once 'config.php';

try {
    $stmt = $pdo->query('SELECT * FROM inquiries WHERE id = 2');
    $inquiry = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($inquiry) {
        echo "Inquiry found:\n";
        print_r($inquiry);
    } else {
        echo "Inquiry not found\n";
    }
    
    // Also check inquiry messages
    $stmt = $pdo->query('SELECT * FROM inquiry_messages WHERE inquiry_id = 2');
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($messages)) {
        echo "\nMessages found:\n";
        print_r($messages);
    } else {
        echo "\nNo messages found\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>