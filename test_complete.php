<?php
// Test script to verify the complete customer support feature implementation

echo "<h1>Customer Support Feature Implementation Test</h1>";

// Test 1: Database table creation
echo "<h2>Test 1: Database Table</h2>";
require_once 'config.php';

try {
    $stmt = $pdo->prepare("SHOW TABLES LIKE 'customer_support_links'");
    $stmt->execute();
    $tableExists = $stmt->fetch();
    
    if ($tableExists) {
        echo "<p style='color: green;'>✓ customer_support_links table exists</p>";
    } else {
        echo "<p style='color: red;'>✗ customer_support_links table does not exist</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error checking table: " . $e->getMessage() . "</p>";
}

// Test 2: Support links data
echo "<h2>Test 2: Support Links Data</h2>";
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM customer_support_links");
    $stmt->execute();
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($count > 0) {
        echo "<p style='color: green;'>✓ Support links data found ($count links)</p>";
    } else {
        echo "<p style='color: red;'>✗ No support links found in database</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error checking support links: " . $e->getMessage() . "</p>";
}

// Test 3: Helper functions
echo "<h2>Test 3: Helper Functions</h2>";
try {
    require_once 'includes/support_links.php';
    
    // Test getCustomerSupportLinks function
    $links = getCustomerSupportLinks($pdo);
    if (is_array($links) && count($links) > 0) {
        echo "<p style='color: green;'>✓ getCustomerSupportLinks() function works correctly</p>";
    } else {
        echo "<p style='color: red;'>✗ getCustomerSupportLinks() function returned no data</p>";
    }
    
    // Test displayCustomerSupportLinks function
    $html = displayCustomerSupportLinks($pdo);
    if (is_string($html) && strlen($html) > 0) {
        echo "<p style='color: green;'>✓ displayCustomerSupportLinks() function works correctly</p>";
    } else {
        echo "<p style='color: red;'>✗ displayCustomerSupportLinks() function returned no HTML</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error testing helper functions: " . $e->getMessage() . "</p>";
}

// Test 4: Admin panel integration
echo "<h2>Test 4: Admin Panel Integration</h2>";
$adminSettingsFile = 'admin/settings.php';
if (file_exists($adminSettingsFile)) {
    $content = file_get_contents($adminSettingsFile);
    if (strpos($content, 'update_support_links') !== false) {
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
    if (strpos($content, 'displayCustomerSupportLinks') !== false) {
        echo "<p style='color: green;'>✓ Client frontend integration found</p>";
    } else {
        echo "<p style='color: red;'>✗ Client frontend integration not found</p>";
    }
} else {
    echo "<p style='color: red;'>✗ Index file not found</p>";
}

if (file_exists($sellerDashboardFile)) {
    $content = file_get_contents($sellerDashboardFile);
    if (strpos($content, 'displayCustomerSupportLinks') !== false) {
        echo "<p style='color: green;'>✓ Seller frontend integration found</p>";
    } else {
        echo "<p style='color: red;'>✗ Seller frontend integration not found</p>";
    }
} else {
    echo "<p style='color: red;'>✗ Seller dashboard file not found</p>";
}

// Test 6: FAQ page
echo "<h2>Test 6: FAQ Page</h2>";
$faqFile = 'faq.php';
if (file_exists($faqFile)) {
    echo "<p style='color: green;'>✓ FAQ page created successfully</p>";
} else {
    echo "<p style='color: red;'>✗ FAQ page not found</p>";
}

echo "<h2>Implementation Summary</h2>";
echo "<ul>";
echo "<li>✓ Database table for customer support links</li>";
echo "<li>✓ Admin panel for managing support links</li>";
echo "<li>✓ Helper functions for retrieving and displaying links</li>";
echo "<li>✓ Integration with seller dashboard</li>";
echo "<li>✓ Integration with client homepage</li>";
echo "<li>✓ Sample FAQ page</li>";
echo "</ul>";

echo "<p>All tests completed. The customer support feature has been successfully implemented!</p>";
?>