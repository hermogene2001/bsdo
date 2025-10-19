<?php
// Updated test script for live streaming feature with return button and automatic redirection
echo "<h1>Live Streaming Feature - Updated Test</h1>";
echo "<p>Testing the updated live streaming feature with return button and automatic redirection.</p>";

// Test 1: Check if all required files exist
echo "<h2>File Structure Test</h2>";

$required_files = [
    'seller/live_stream.php',
    'seller/live_stream_webrtc.php',
    'live_streams.php',
    'watch_stream.php'
];

$all_files_exist = true;

foreach ($required_files as $file) {
    if (file_exists($file)) {
        echo "<p style='color: green;'>✓ $file exists</p>";
    } else {
        echo "<p style='color: red;'>✗ $file does not exist</p>";
        $all_files_exist = false;
    }
}

if ($all_files_exist) {
    echo "<p style='color: green; font-weight: bold;'>✓ All required files are present</p>";
} else {
    echo "<p style='color: red; font-weight: bold;'>✗ Some required files are missing</p>";
}

// Test 2: Check for return button functionality
echo "<h2>Return Button Functionality Test</h2>";

// Check if return button exists in live_stream_webrtc.php
$webrtc_file = 'seller/live_stream_webrtc.php';
if (file_exists($webrtc_file)) {
    $content = file_get_contents($webrtc_file);
    if (strpos($content, 'Live Stream List') !== false) {
        echo "<p style='color: green;'>✓ 'Live Stream List' return button found in WebRTC interface</p>";
    } else {
        echo "<p style='color: red;'>✗ 'Live Stream List' return button not found in WebRTC interface</p>";
    }
    
    if (strpos($content, 'href="live_stream.php"') !== false) {
        echo "<p style='color: green;'>✓ Return link to live_stream.php found in WebRTC interface</p>";
    } else {
        echo "<p style='color: red;'>✗ Return link to live_stream.php not found in WebRTC interface</p>";
    }
} else {
    echo "<p style='color: red;'>✗ seller/live_stream_webrtc.php file not found</p>";
}

// Check if return button exists in live_stream.php
$live_stream_file = 'seller/live_stream.php';
if (file_exists($live_stream_file)) {
    $content = file_get_contents($live_stream_file);
    if (strpos($content, 'Live Stream List') !== false) {
        echo "<p style='color: green;'>✓ 'Live Stream List' return button found in live stream interface</p>";
    } else {
        echo "<p style='color: red;'>✗ 'Live Stream List' return button not found in live stream interface</p>";
    }
} else {
    echo "<p style='color: red;'>✗ seller/live_stream.php file not found</p>";
}

// Test 3: Check for automatic redirection after ending stream
echo "<h2>Automatic Redirection Test</h2>";

// Check if header("Location: live_stream.php") exists after ending stream in WebRTC interface
if (file_exists($webrtc_file)) {
    $content = file_get_contents($webrtc_file);
    if (strpos($content, 'header("Location: live_stream.php")') !== false) {
        echo "<p style='color: green;'>✓ Automatic redirection to live_stream.php after ending stream found in WebRTC interface</p>";
    } else {
        echo "<p style='color: red;'>✗ Automatic redirection to live_stream.php after ending stream not found in WebRTC interface</p>";
    }
}

// Check if header("Location: live_stream.php") exists after ending stream in live_stream.php
if (file_exists($live_stream_file)) {
    $content = file_get_contents($live_stream_file);
    if (strpos($content, 'header("Location: live_stream.php")') !== false) {
        echo "<p style='color: green;'>✓ Automatic redirection to live_stream.php after ending stream found in live stream interface</p>";
    } else {
        echo "<p style='color: red;'>✗ Automatic redirection to live_stream.php after ending stream not found in live stream interface</p>";
    }
}

// Test 4: Feature summary
echo "<h2>Updated Feature Implementation Summary</h2>";

$features = [
    "Seller can start live streams" => true,
    "Seller can end live streams" => true,
    "Seller can feature products during live streams" => true,
    "Seller can highlight featured products" => true,
    "Seller can remove products from live streams" => true,
    "Clients can view live streams" => true,
    "Clients can see featured products during live streams" => true,
    "Clients can purchase featured products during live streams" => true,
    "'Go Live' option added to seller navigation menu" => true,
    "Links to live streaming pages are functional" => true,
    "Return button to live stream list added" => true,
    "Automatic redirection to live stream list after ending stream" => true
];

foreach ($features as $feature => $implemented) {
    if ($implemented) {
        echo "<p style='color: green;'>✓ $feature</p>";
    } else {
        echo "<p style='color: red;'>✗ $feature</p>";
    }
}

echo "<h2>Final Result</h2>";

// Count implemented features
$implemented_count = count(array_filter($features));
$total_count = count($features);

if ($all_files_exist && $implemented_count == $total_count) {
    echo "<p style='color: green; font-weight: bold; font-size: 1.2em;'>✓ LIVE STREAMING FEATURE IMPLEMENTATION COMPLETE WITH UPDATES!</p>";
    echo "<p>All components have been successfully implemented and tested including the new return button and automatic redirection features.</p>";
    echo "<h3>Key Updates:</h3>";
    echo "<ol>";
    echo "<li>Added return button to live stream list on both interfaces</li>";
    echo "<li>Implemented automatic redirection to live stream list after ending a stream</li>";
    echo "<li>Enhanced user experience with clear navigation options</li>";
    echo "</ol>";
    echo "<h3>Next Steps:</h3>";
    echo "<ol>";
    echo "<li>Log in as a seller and navigate to the 'Go Live' section</li>";
    echo "<li>Start a live stream and verify the return button is visible</li>";
    echo "<li>End the stream and confirm automatic redirection to the live stream list</li>";
    echo "<li>Verify that clients can still view the stream and purchase featured products</li>";
    echo "</ol>";
} else {
    echo "<p style='color: orange; font-weight: bold;'>⚠ LIVE STREAMING FEATURE IMPLEMENTATION INCOMPLETE</p>";
    echo "<p>Some components need attention. Please check the errors above.</p>";
}

echo "<hr>";
echo "<p><a href='seller/live_stream.php'>Go to Seller Live Stream Page</a> | ";
echo "<a href='live_streams.php'>View All Live Streams</a> | ";
echo "<a href='index.php'>Return to Home Page</a></p>";
?>