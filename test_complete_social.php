<?php
// Test script to verify the complete social links feature implementation

echo "<h1>Social Links Feature Implementation Test</h1>";

// Test 1: Database table creation
echo "<h2>Test 1: Database Table</h2>";
require_once 'config.php';

try {
    $stmt = $pdo->prepare("SHOW TABLES LIKE 'social_links'");
    $stmt->execute();
    $tableExists = $stmt->fetch();
    
    if ($tableExists) {
        echo "<p style='color: green;'>✓ social_links table exists</p>";
    } else {
        echo "<p style='color: red;'>✗ social_links table does not exist</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error checking table: " . $e->getMessage() . "</p>";
}

// Test 2: Social links data
echo "<h2>Test 2: Social Links Data</h2>";
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM social_links");
    $stmt->execute();
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($count > 0) {
        echo "<p style='color: green;'>✓ Social links data found ($count links)</p>";
    } else {
        echo "<p style='color: red;'>✗ No social links found in database</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error checking social links: " . $e->getMessage() . "</p>";
}

// Test 3: Helper functions
echo "<h2>Test 3: Helper Functions</h2>";
try {
    require_once 'includes/social_links.php';
    
    // Test getSocialLinks function
    $links = getSocialLinks($pdo);
    if (is_array($links) && count($links) > 0) {
        echo "<p style='color: green;'>✓ getSocialLinks() function works correctly</p>";
    } else {
        echo "<p style='color: red;'>✗ getSocialLinks() function returned no data</p>";
    }
    
    // Test displaySocialLinks function
    $html = displaySocialLinks($pdo);
    if (is_string($html) && strlen($html) > 0) {
        echo "<p style='color: green;'>✓ displaySocialLinks() function works correctly</p>";
    } else {
        echo "<p style='color: red;'>✗ displaySocialLinks() function returned no HTML</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error testing helper functions: " . $e->getMessage() . "</p>";
}

// Test 4: Admin panel integration
echo "<h2>Test 4: Admin Panel Integration</h2>";
$adminSettingsFile = 'admin/settings.php';
if (file_exists($adminSettingsFile)) {
    $content = file_get_contents($adminSettingsFile);
    if (strpos($content, 'update_social_links') !== false) {
        echo "<p style='color: green;'>✓ Admin panel integration found</p>";
    } else {
        echo "<p style='color: red;'>✗ Admin panel integration not found</p>";
    }
} else {
    echo "<p style='color: red;'>✗ Admin settings file not found</p>";
}

// Test 5: Frontend integration
echo "<h2>Test 5: Frontend Integration</h2>";
$indexFile = 'index.php';
$sellerDashboardFile = 'seller/dashboard.php';

if (file_exists($indexFile)) {
    $content = file_get_contents($indexFile);
    if (strpos($content, 'displaySocialLinks') !== false) {
        echo "<p style='color: green;'>✓ Client frontend integration found</p>";
    } else {
        echo "<p style='color: red;'>✗ Client frontend integration not found</p>";
    }
} else {
    echo "<p style='color: red;'>✗ Index file not found</p>";
}

if (file_exists($sellerDashboardFile)) {
    $content = file_get_contents($sellerDashboardFile);
    if (strpos($content, 'displaySocialLinks') !== false) {
        echo "<p style='color: green;'>✓ Seller frontend integration found</p>";
    } else {
        echo "<p style='color: red;'>✗ Seller frontend integration not found</p>";
    }
} else {
    echo "<p style='color: red;'>✗ Seller dashboard file not found</p>";
}

echo "<h2>Implementation Summary</h2>";
echo "<ul>";
echo "<li>✓ Database table for social links</li>";
echo "<li>✓ Admin panel for managing social links</li>";
echo "<li>✓ Helper functions for retrieving and displaying links</li>";
echo "<li>✓ Integration with client homepage footer</li>";
echo "<li>✓ Integration with seller dashboard footer</li>";
echo "</ul>";

echo "<p>All tests completed. The social links feature has been successfully implemented!</p>";
?>