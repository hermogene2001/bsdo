<?php
require_once 'config.php';
require_once 'includes/support_links.php';

// Test the support links functionality
$links = getCustomerSupportLinks($pdo);

echo "<h1>Customer Support Links Test</h1>";
echo "<p>Total links found: " . count($links) . "</p>";

if (!empty($links)) {
    echo "<ul>";
    foreach ($links as $link) {
        echo "<li>";
        echo "<strong>" . htmlspecialchars($link['name']) . "</strong><br>";
        echo "URL: " . htmlspecialchars($link['url']) . "<br>";
        echo "Description: " . htmlspecialchars($link['description']) . "<br>";
        echo "Icon: " . htmlspecialchars($link['icon']) . "<br>";
        echo "Active: " . ($link['is_active'] ? 'Yes' : 'No') . "<br>";
        echo "</li><hr>";
    }
    echo "</ul>";
    
    echo "<h2>Display Test</h2>";
    echo displayCustomerSupportLinks($pdo);
} else {
    echo "<p>No support links found in the database.</p>";
}
?>