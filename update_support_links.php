<?php
require_once 'config.php';

// Check if we have support links in the database
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM customer_support_links");
$stmt->execute();
$count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

if ($count == 0) {
    // Insert default support links
    $links = [
        ['Live Chat Support', '/handle_live_chat.php', 'Get instant help through our live chat system', 'fa-comments', 1, 1],
        ['Email Support', 'mailto:support@bsdosale.com', 'Send us an email for assistance', 'fa-envelope', 1, 2],
        ['Phone Support', 'tel:+1234567890', 'Call our support team directly', 'fa-phone', 1, 3],
        ['FAQ Section', '/faq.php', 'Browse our frequently asked questions', 'fa-question-circle', 1, 4]
    ];
    
    $stmt = $pdo->prepare("INSERT INTO customer_support_links (name, url, description, icon, is_active, sort_order) VALUES (?, ?, ?, ?, ?, ?)");
    
    foreach ($links as $link) {
        $stmt->execute($link);
    }
    
    echo "Default support links added successfully!\n";
} else {
    echo "Support links already exist in the database.\n";
}

// Display current links
echo "\nCurrent support links:\n";
$stmt = $pdo->prepare("SELECT * FROM customer_support_links ORDER BY sort_order ASC");
$stmt->execute();
$links = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($links as $link) {
    echo "- " . $link['name'] . " (" . $link['url'] . ")\n";
}
?>