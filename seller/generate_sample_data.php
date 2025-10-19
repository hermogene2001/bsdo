<?php
session_start();
require_once '../config.php';

// Check if user is logged in and is seller
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'seller') {
    header('Location: ../login.php');
    exit();
}

$seller_id = $_SESSION['user_id'];

// Generate sample products if none exist
$check_products = $pdo->prepare("SELECT COUNT(*) FROM products WHERE seller_id = ?");
$check_products->execute([$seller_id]);
$product_count = $check_products->fetchColumn();

if ($product_count == 0) {
    $sample_products = [
        ['Wireless Bluetooth Headphones', 'High-quality wireless headphones with noise cancellation', 79.99, 25, 1],
        ['Smart Fitness Watch', 'Track your fitness with this advanced smartwatch', 129.99, 15, 1],
        ['Organic Cotton T-Shirt', 'Comfortable and eco-friendly cotton t-shirt', 24.99, 50, 2],
        ['Yoga Mat Premium', 'Non-slip yoga mat for your exercises', 39.99, 30, 4],
        ['Programming Book: PHP Guide', 'Complete guide to PHP programming', 29.99, 20, 5]
    ];
    
    foreach ($sample_products as $product) {
        $stmt = $pdo->prepare("INSERT INTO products (seller_id, name, description, price, stock, category_id, status) VALUES (?, ?, ?, ?, ?, ?, 'active')");
        $stmt->execute([$seller_id, $product[0], $product[1], $product[2], $product[3], $product[4]]);
    }
    echo "Generated 5 sample products.<br>";
}

// Generate sample orders
$check_orders = $pdo->prepare("SELECT COUNT(*) FROM orders");
$check_orders->execute();
$order_count = $check_orders->fetchColumn();

if ($order_count == 0) {
    // Get product IDs
    $products_stmt = $pdo->prepare("SELECT id, price FROM products WHERE seller_id = ?");
    $products_stmt->execute([$seller_id]);
    $products = $products_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($products)) {
        // Create a sample customer
        $customer_stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, email, password, role, status) VALUES (?, ?, ?, ?, 'client', 'active')");
        $customer_stmt->execute(['John', 'Doe', 'john.doe@example.com', password_hash('password123', PASSWORD_DEFAULT)]);
        $customer_id = $pdo->lastInsertId();
        
        // Generate orders for the last 30 days
        $statuses = ['completed', 'completed', 'completed', 'pending', 'processing'];
        
        for ($i = 0; $i < 15; $i++) {
            $order_number = 'ORD' . date('Ymd') . str_pad($i, 3, '0', STR_PAD_LEFT);
            $total_amount = 0;
            $order_date = date('Y-m-d H:i:s', strtotime('-' . rand(0, 29) . ' days'));
            $status = $statuses[array_rand($statuses)];
            
            // Create order
            $order_stmt = $pdo->prepare("INSERT INTO orders (user_id, order_number, total_amount, status, created_at) VALUES (?, ?, ?, ?, ?)");
            $order_stmt->execute([$customer_id, $order_number, 0, $status, $order_date]);
            $order_id = $pdo->lastInsertId();
            
            // Add order items (1-3 random products per order)
            $num_items = rand(1, 3);
            $selected_products = array_rand($products, min($num_items, count($products)));
            if (!is_array($selected_products)) {
                $selected_products = [$selected_products];
            }
            
            foreach ($selected_products as $product_index) {
                $product = $products[$product_index];
                $quantity = rand(1, 3);
                $item_total = $product['price'] * $quantity;
                $total_amount += $item_total;
                
                $item_stmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
                $item_stmt->execute([$order_id, $product['id'], $quantity, $product['price']]);
            }
            
            // Update order total
            $update_stmt = $pdo->prepare("UPDATE orders SET total_amount = ? WHERE id = ?");
            $update_stmt->execute([$total_amount, $order_id]);
        }
        echo "Generated 15 sample orders with order items.<br>";
    }
}

echo "Sample data generation complete! <a href='analytics.php'>View Analytics</a>";
?>