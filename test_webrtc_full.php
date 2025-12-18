<?php
// Comprehensive WebRTC streaming test
require_once 'config.php';

echo "=== BSDO WebRTC Streaming Full Test ===\n\n";

try {
    // Check users
    echo "1. Checking Users:\n";
    $stmt = $pdo->query('SELECT COUNT(*) as count FROM users');
    $result = $stmt->fetch();
    echo "   Total users: " . $result['count'] . "\n";

    $stmt = $pdo->query('SELECT COUNT(*) as count FROM users WHERE role = "seller"');
    $result = $stmt->fetch();
    echo "   Seller accounts: " . $result['count'] . "\n";

    $stmt = $pdo->query('SELECT COUNT(*) as count FROM users WHERE role = "buyer" OR role IS NULL OR role = ""');
    $result = $stmt->fetch();
    echo "   Buyer accounts: " . $result['count'] . "\n\n";

    // Create test seller if none exists
    $stmt = $pdo->query('SELECT id FROM users WHERE role = "seller" LIMIT 1');
    $seller = $stmt->fetch();

    if (!$seller) {
        echo "2. Creating Test Seller Account:\n";
        $hashed_password = password_hash('test123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("
            INSERT INTO users (first_name, last_name, email, password, role, store_name, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute(['Test', 'Seller', 'seller@test.com', $hashed_password, 'seller', 'Test Store']);
        $seller_id = $pdo->lastInsertId();
        echo "   ✓ Created test seller (ID: $seller_id, Email: seller@test.com, Password: test123)\n\n";
    } else {
        $seller_id = $seller['id'];
        echo "2. Using Existing Seller (ID: $seller_id)\n\n";
    }

    // Create test buyer if none exists
    $stmt = $pdo->query('SELECT id FROM users WHERE role = "buyer" OR role IS NULL LIMIT 1');
    $buyer = $stmt->fetch();

    if (!$buyer) {
        echo "3. Creating Test Buyer Account:\n";
        $hashed_password = password_hash('test123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("
            INSERT INTO users (first_name, last_name, email, password, role, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute(['Test', 'Buyer', 'buyer@test.com', $hashed_password, 'buyer']);
        $buyer_id = $pdo->lastInsertId();
        echo "   ✓ Created test buyer (ID: $buyer_id, Email: buyer@test.com, Password: test123)\n\n";
    } else {
        $buyer_id = $buyer['id'];
        echo "3. Using Existing Buyer (ID: $buyer_id)\n\n";
    }

    // Create test products for seller
    echo "4. Creating Test Products:\n";
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM products WHERE seller_id = $seller_id");
    $result = $stmt->fetch();

    if ($result['count'] == 0) {
        $products = [
            ['Test Laptop', 'High-performance laptop for testing', 999.99, 10],
            ['Test Phone', 'Latest smartphone for testing', 599.99, 20],
            ['Test Headphones', 'Wireless headphones for testing', 149.99, 15]
        ];

        foreach ($products as $product) {
            $stmt = $pdo->prepare("
                INSERT INTO products (seller_id, name, description, price, stock, status, created_at)
                VALUES (?, ?, ?, ?, ?, 'active', NOW())
            ");
            $stmt->execute([$seller_id, $product[0], $product[1], $product[2], $product[3]]);
        }
        echo "   ✓ Created 3 test products for seller\n\n";
    } else {
        echo "   ✓ Seller already has products\n\n";
    }

    // Create a test WebRTC stream
    echo "5. Creating Test WebRTC Stream:\n";
    $stream_key = 'test_webrtc_' . time() . '_' . bin2hex(random_bytes(4));
    $stmt = $pdo->prepare("
        INSERT INTO live_streams (seller_id, title, description, stream_key, invitation_code, is_live, streaming_method, status, created_at)
        VALUES (?, ?, ?, ?, ?, 0, 'webrtc', 'scheduled', NOW())
        ON DUPLICATE KEY UPDATE title = VALUES(title)
    ");
    $stmt->execute([$seller_id, 'WebRTC Test Stream', 'Testing WebRTC browser streaming functionality', $stream_key, 'test123']);
    $stream_id = $pdo->lastInsertId();
    echo "   ✓ Created test WebRTC stream (ID: $stream_id)\n\n";

    // Test WebRTC signaling server
    echo "6. Testing WebRTC Signaling Server:\n";

    // Simulate creating a room (what happens when seller starts streaming)
    $room_data = [
        'action' => 'create_room',
        'stream_id' => $stream_id
    ];

    // Test the signaling endpoint
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'http://localhost/bsdo/webrtc_server.php');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($room_data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded',
        'Cookie: PHPSESSID=test_session_' . time()
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code == 200) {
        $data = json_decode($response, true);
        if ($data && isset($data['success']) && $data['success']) {
            echo "   ✓ WebRTC signaling server responding correctly\n";
            echo "   ✓ Room creation test passed\n";
        } else {
            echo "   ⚠️ Signaling server responded but with unexpected data\n";
        }
    } else {
        echo "   ✗ WebRTC signaling server not responding (HTTP $http_code)\n";
    }

    echo "\n=== Test Setup Complete ===\n\n";

    echo "TEST ACCOUNTS CREATED:\n";
    echo "Seller: seller@test.com / test123\n";
    echo "Buyer:  buyer@test.com / test123\n\n";

    echo "HOW TO TEST WEBRTC STREAMING:\n\n";

    echo "SELLER SIDE:\n";
    echo "1. Open browser: http://localhost/bsdo/login.php\n";
    echo "2. Login: seller@test.com / test123\n";
    echo "3. Go to: seller/live_stream.php\n";
    echo "4. Select: 'WebRTC (Browser-based streaming)'\n";
    echo "5. Click: 'Start Live Stream'\n";
    echo "6. Allow camera/microphone permissions\n";
    echo "7. You should see your camera feed!\n\n";

    echo "BUYER SIDE:\n";
    echo "1. Open another browser/incognito window\n";
    echo "2. Go to: http://localhost/bsdo/login.php\n";
    echo "3. Login: buyer@test.com / test123\n";
    echo "4. Go to: http://localhost/bsdo/live.php\n";
    echo "5. Click 'Join Stream' on the test stream\n";
    echo "6. You should see the seller's video!\n\n";

    echo "TEST FEATURES:\n";
    echo "✓ Camera/microphone selection\n";
    echo "✓ Video/audio toggle controls\n";
    echo "✓ Real-time chat\n";
    echo "✓ Product showcasing\n";
    echo "✓ Viewer count\n\n";

    echo "If you encounter issues:\n";
    echo "1. Make sure you're using HTTPS (localhost might work with HTTP)\n";
    echo "2. Check browser console for errors\n";
    echo "3. Verify camera permissions are granted\n";
    echo "4. Try refreshing the page\n\n";

    echo "🎉 Ready to test WebRTC streaming!\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>