<?php
require_once 'config.php';
require_once 'includes/social_links.php';

// Test the social links functionality
$links = getSocialLinks($pdo);

echo "<h1>Social Links Test</h1>";
echo "<p>Total links found: " . count($links) . "</p>";

if (!empty($links)) {
    echo "<ul>";
    foreach ($links as $link) {
        echo "<li>";
        echo "<strong>" . htmlspecialchars($link['name']) . "</strong><br>";
        echo "URL: " . htmlspecialchars($link['url']) . "<br>";
        echo "Icon: " . htmlspecialchars($link['icon']) . "<br>";
        echo "Active: " . ($link['is_active'] ? 'Yes' : 'No') . "<br>";
        echo "</li><hr>";
    }
    echo "</ul>";
    
    echo "<h2>Display Test</h2>";
    echo displaySocialLinks($pdo);
    
    echo "<h2>Display Test with Custom Classes</h2>";
    echo displaySocialLinks($pdo, 'd-flex gap-2', 'btn btn-primary');
} else {
    echo "<p>No social links found in the database.</p>";
}
?>