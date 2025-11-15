<?php
require_once 'config.php';

// Check if we have social links in the database
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM social_links");
$stmt->execute();
$count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

if ($count == 0) {
    // Insert default social links
    $links = [
        ['Facebook', 'https://facebook.com/bsdosale', 'fab fa-facebook-f', 1, 1],
        ['Twitter', 'https://twitter.com/bsdosale', 'fab fa-twitter', 1, 2],
        ['Instagram', 'https://instagram.com/bsdosale', 'fab fa-instagram', 1, 3],
        ['LinkedIn', 'https://linkedin.com/company/bsdosale', 'fab fa-linkedin-in', 1, 4],
        ['YouTube', 'https://youtube.com/bsdosale', 'fab fa-youtube', 1, 5]
    ];
    
    $stmt = $pdo->prepare("INSERT INTO social_links (name, url, icon, is_active, sort_order) VALUES (?, ?, ?, ?, ?)");
    
    foreach ($links as $link) {
        $stmt->execute($link);
    }
    
    echo "Default social links added successfully!\n";
} else {
    echo "Social links already exist in the database.\n";
}

// Display current links
echo "\nCurrent social links:\n";
$stmt = $pdo->prepare("SELECT * FROM social_links ORDER BY sort_order ASC");
$stmt->execute();
$links = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($links as $link) {
    echo "- " . $link['name'] . " (" . $link['url'] . ")\n";
}
?>