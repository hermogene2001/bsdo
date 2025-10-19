<?php
/**
 * Sample Order Creation Script
 * Creates one test order to demonstrate the system
 * Run this file once: http://localhost/bsdo/create_sample_order.php
 */

require_once 'config.php';

try {
    // Get a random active client
    $client_stmt = $pdo->prepare("SELECT id, first_name, last_name FROM users WHERE role = 'client' AND status = 'active' LIMIT 1");
    $client_stmt->execute();
    $client = $client_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$client) {
        die('No active clients found. Please register a client first.');
    }
    
    // Get a random active product
    $product_stmt = $pdo->prepare("SELECT id, name, price, seller_id FROM products WHERE status = 'active' AND stock > 0 LIMIT 1");
    $product_stmt->execute();
    $product = $product_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        die('No active products found. Please add products first.');
    }
    
    // Generate order number
    $order_number = 'ORD' . date('Ymd') . str_pad(rand(0, 999), 3, '0', STR_PAD_LEFT);
    
    // Calculate totals
    $quantity = 1;
    $item_total = $product['price'] * $quantity;
    $shipping = 5.00;
    $tax = $item_total * 0.08;
    $total_amount = $item_total + $shipping + $tax;
    
    // Begin transaction
    $pdo->beginTransaction();
    
    // Create order
    $order_stmt = $pdo->prepare("
        INSERT INTO orders (user_id, order_number, total_amount, status, created_at) 
        VALUES (?, ?, ?, 'pending', NOW())
    ");
    $order_stmt->execute([$client['id'], $order_number, $total_amount]);
    $order_id = $pdo->lastInsertId();
    
    // Add order item
    $item_stmt = $pdo->prepare("
        INSERT INTO order_items (order_id, product_id, quantity, price) 
        VALUES (?, ?, ?, ?)
    ");
    $item_stmt->execute([$order_id, $product['id'], $quantity, $product['price']]);
    
    // Update product stock
    $stock_stmt = $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
    $stock_stmt->execute([$quantity, $product['id']]);
    
    // Commit transaction
    $pdo->commit();
    
    echo "<h2>✅ Sample Order Created Successfully!</h2>";
    echo "<div style='font-family: Arial; padding: 20px; background: #f0f0f0; border-radius: 10px; max-width: 600px;'>";
    echo "<h3>Order Details:</h3>";
    echo "<p><strong>Order Number:</strong> $order_number</p>";
    echo "<p><strong>Client:</strong> {$client['first_name']} {$client['last_name']} (ID: {$client['id']})</p>";
    echo "<p><strong>Product:</strong> {$product['name']}</p>";
    echo "<p><strong>Quantity:</strong> $quantity</p>";
    echo "<p><strong>Item Price:</strong> $" . number_format($product['price'], 2) . "</p>";
    echo "<p><strong>Shipping:</strong> $" . number_format($shipping, 2) . "</p>";
    echo "<p><strong>Tax:</strong> $" . number_format($tax, 2) . "</p>";
    echo "<p><strong>Total Amount:</strong> $" . number_format($total_amount, 2) . "</p>";
    echo "<p><strong>Status:</strong> Pending</p>";
    echo "</div>";
    echo "<br><p><a href='orders.php'>View Orders</a> | <a href='seller/orders.php'>Seller Orders</a></p>";
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "<h2>❌ Error Creating Order</h2>";
    echo "<p style='color: red;'>" . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
