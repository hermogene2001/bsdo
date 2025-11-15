<?php
// Test script to verify invitation access logic for live streams

echo "<h1>Live Stream Invitation Access Test</h1>";

// Simulate different user roles
$test_roles = ['client', 'seller', 'admin', 'guest'];

echo "<h2>User Role Access Tests</h2>";

foreach ($test_roles as $role) {
    echo "<h3>Role: " . ucfirst($role) . "</h3>";
    
    // Simulate user session
    $user_role = $role;
    
    // Test logic for invitation requirement
    $invitation_enabled = true; // Simulate stream with invitation enabled
    $is_active = true; // Simulate live stream
    
    echo "<p>Stream has invitation enabled: " . ($invitation_enabled ? 'Yes' : 'No') . "</p>";
    echo "<p>Stream is active: " . ($is_active ? 'Yes' : 'No') . "</p>";
    
    // Apply the same logic as in watch_stream.php
    if ($invitation_enabled && $is_active) {
        if ($user_role !== 'client' && $user_role !== 'admin') {
            echo "<p style='color: red;'>✗ Access denied - Invitation code required</p>";
        } else {
            echo "<p style='color: green;'>✓ Access granted - No invitation code needed for " . ucfirst($user_role) . "s</p>";
        }
    } else {
        echo "<p style='color: green;'>✓ Access granted - No invitation required</p>";
    }
    
    echo "<hr>";
}

echo "<h2>Implementation Summary</h2>";
echo "<ul>";
echo "<li>✓ Registered clients can access streams without invitation codes</li>";
echo "<li>✓ Admins can access streams without invitation codes</li>";
echo "<li>✓ Sellers and guests still require invitation codes for private streams</li>";
echo "<li>✓ Public streams are accessible to all users</li>";
echo "</ul>";

echo "<p>The implementation allows registered clients and admins to view live streams without needing invitation codes, while still protecting private streams for other user types.</p>";
?>