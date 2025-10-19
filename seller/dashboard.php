<?php
session_start();
require_once '../config.php';

// Check if user is logged in and is seller
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'seller') {
    header('Location: ../login.php');
    exit();
}

$seller_id = $_SESSION['user_id'];
// Get pending inquiries count
$inquiries_stmt = $pdo->prepare("
    SELECT COUNT(*) as pending_inquiries 
    FROM inquiries i 
    JOIN products p ON i.product_id = p.id 
    WHERE p.seller_id = ? AND i.status = 'pending'
");
$inquiries_stmt->execute([$seller_id]);
$pending_inquiries = $inquiries_stmt->fetch(PDO::FETCH_ASSOC)['pending_inquiries'];

// Get today's sales
$today_sales_stmt = $pdo->prepare("
    SELECT COALESCE(SUM(oi.quantity * oi.price), 0) as today_revenue,
           COUNT(DISTINCT o.id) as today_orders
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    JOIN products p ON oi.product_id = p.id
    WHERE p.seller_id = ? AND DATE(o.created_at) = CURDATE()
");
$today_sales_stmt->execute([$seller_id]);
$today_sales = $today_sales_stmt->fetch(PDO::FETCH_ASSOC);
// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_product':
                $name = trim($_POST['name']);
                $description = trim($_POST['description']);
                $price = floatval($_POST['price']);
                $stock = intval($_POST['stock']);
                $category_id = intval($_POST['category_id']);
                $address = trim($_POST['address']);
                $city = trim($_POST['city']);
                $state = trim($_POST['state']);
                $country = trim($_POST['country']);
                $postal_code = trim($_POST['postal_code']);
                
                try {
                    // Handle image upload
                    $image_url = '';
                    $image_gallery = null;
                    
                    // Handle single image upload
                    if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
                        $upload_dir = '../uploads/products/';
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0755, true);
                        }
                        
                        // Validate file type
                        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                        if (in_array($_FILES['image']['type'], $allowed_types)) {
                            // Validate file size (2MB max)
                            if ($_FILES['image']['size'] <= 2 * 1024 * 1024) {
                                $file_extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
                                $filename = 'product_' . time() . '_' . uniqid() . '.' . $file_extension;
                                $target_path = $upload_dir . $filename;
                                
                                if (move_uploaded_file($_FILES['image']['tmp_name'], $target_path)) {
                                    $image_url = 'uploads/products/' . $filename;
                                }
                            }
                        }
                    }
                    
                    // Handle multiple image uploads
                    if (isset($_FILES['gallery_images']) && is_array($_FILES['gallery_images']['name']) && count($_FILES['gallery_images']['name']) > 0) {
                        $gallery_images = [];
                        $upload_dir = '../uploads/products/';
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0755, true);
                        }
                        
                        for ($i = 0; $i < count($_FILES['gallery_images']['name']); $i++) {
                            if ($_FILES['gallery_images']['error'][$i] === 0) {
                                // Validate file type
                                $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                                if (in_array($_FILES['gallery_images']['type'][$i], $allowed_types)) {
                                    // Validate file size (2MB max)
                                    if ($_FILES['gallery_images']['size'][$i] <= 2 * 1024 * 1024) {
                                        $file_extension = pathinfo($_FILES['gallery_images']['name'][$i], PATHINFO_EXTENSION);
                                        $filename = 'gallery_' . time() . '_' . uniqid() . '_' . $i . '.' . $file_extension;
                                        $target_path = $upload_dir . $filename;
                                        
                                        if (move_uploaded_file($_FILES['gallery_images']['tmp_name'][$i], $target_path)) {
                                            $gallery_images[] = 'uploads/products/' . $filename;
                                        }
                                    }
                                }
                            }
                        }
                        
                        if (!empty($gallery_images)) {
                            $image_gallery = json_encode($gallery_images);
                        }
                    }
                    
                    $stmt = $pdo->prepare("INSERT INTO products (seller_id, name, description, price, stock, category_id, image_url, image_gallery, address, city, state, country, postal_code, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
                    $stmt->execute([$seller_id, $name, $description, $price, $stock, $category_id, $image_url, $image_gallery, $address, $city, $state, $country, $postal_code]);
                    $success_message = "Product added successfully! Waiting for admin approval.";
                    logSellerActivity("Added new product: $name");
                } catch (Exception $e) {
                    $error_message = "Failed to add product: " . $e->getMessage();
                }
                break;
                
            case 'update_product':
                $product_id = intval($_POST['product_id']);
                $name = trim($_POST['name']);
                $price = floatval($_POST['price']);
                $category_id = intval($_POST['category_id']);
                $stock = intval($_POST['stock']);
                $description = trim($_POST['description']);
                
                // Address fields
                $address = trim($_POST['address']);
                $city = trim($_POST['city']);
                $state = trim($_POST['state']);
                $country = trim($_POST['country']);
                $postal_code = trim($_POST['postal_code']);
                
                // Verify product belongs to seller
                $stmt = $pdo->prepare("SELECT id, image_url, image_gallery FROM products WHERE id = ? AND seller_id = ?");
                $stmt->execute([$product_id, $seller_id]);
                if ($product = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    // Handle image update
                    $image_url = $product['image_url'];
                    $image_gallery = $product['image_gallery'];
                    
                    // Handle single image update
                    if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
                        // Delete old image if exists
                        if ($image_url && file_exists($_SERVER['DOCUMENT_ROOT'] . '/' . $image_url)) {
                            unlink($_SERVER['DOCUMENT_ROOT'] . '/' . $image_url);
                        }
                        
                        // Upload new image
                        $upload_dir = '../uploads/products/';
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0755, true);
                        }
                        
                        // Validate file type
                        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                        if (in_array($_FILES['image']['type'], $allowed_types)) {
                            // Validate file size (2MB max)
                            if ($_FILES['image']['size'] <= 2 * 1024 * 1024) {
                                $file_extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
                                $filename = 'product_' . time() . '_' . uniqid() . '.' . $file_extension;
                                $target_path = $upload_dir . $filename;
                                
                                if (move_uploaded_file($_FILES['image']['tmp_name'], $target_path)) {
                                    $image_url = 'uploads/products/' . $filename;
                                }
                            }
                        }
                    }
                    
                    // Handle gallery images update
                    if (isset($_FILES['gallery_images']) && is_array($_FILES['gallery_images']['name']) && count($_FILES['gallery_images']['name']) > 0) {
                        // Decode existing gallery images
                        $existing_gallery = !empty($image_gallery) ? json_decode($image_gallery, true) : [];
                        
                        // Handle new gallery image uploads
                        $upload_dir = '../uploads/products/';
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0755, true);
                        }
                        
                        for ($i = 0; $i < count($_FILES['gallery_images']['name']); $i++) {
                            if ($_FILES['gallery_images']['error'][$i] === 0) {
                                // Validate file type
                                $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                                if (in_array($_FILES['gallery_images']['type'][$i], $allowed_types)) {
                                    // Validate file size (2MB max)
                                    if ($_FILES['gallery_images']['size'][$i] <= 2 * 1024 * 1024) {
                                        $file_extension = pathinfo($_FILES['gallery_images']['name'][$i], PATHINFO_EXTENSION);
                                        $filename = 'gallery_' . time() . '_' . uniqid() . '_' . $i . '.' . $file_extension;
                                        $target_path = $upload_dir . $filename;
                                        
                                        if (move_uploaded_file($_FILES['gallery_images']['tmp_name'][$i], $target_path)) {
                                            $existing_gallery[] = 'uploads/products/' . $filename;
                                        }
                                    }
                                }
                            }
                        }
                        
                        if (!empty($existing_gallery)) {
                            $image_gallery_json = json_encode($existing_gallery);
                        }
                    }
                    
                    $stmt = $pdo->prepare("UPDATE products SET name = ?, price = ?, category_id = ?, stock = ?, description = ?, address = ?, city = ?, state = ?, country = ?, postal_code = ?, image_url = ?, image_gallery = ? WHERE id = ?");
                    $stmt->execute([$name, $price, $category_id, $stock, $description, $address, $city, $state, $country, $postal_code, $image_url, $image_gallery, $product_id]);
                    $success_message = "Product updated successfully!";
                    logSellerActivity("Updated product: $name");
                } else {
                    $error_message = "Product not found or access denied.";
                }
                break;
                
            case 'update_stock':
                $product_id = intval($_POST['product_id']);
                $stock = intval($_POST['stock']);
                
                // Verify product belongs to seller
                $stmt = $pdo->prepare("SELECT id, name FROM products WHERE id = ? AND seller_id = ?");
                $stmt->execute([$product_id, $seller_id]);
                if ($product = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $stmt = $pdo->prepare("UPDATE products SET stock = ? WHERE id = ?");
                    $stmt->execute([$stock, $product_id]);
                    $success_message = "Stock updated successfully!";
                    logSellerActivity("Updated stock for product: {$product['name']}");
                } else {
                    $error_message = "Product not found or access denied.";
                }
                break;
            // Add this to your existing POST handling section
case 'add_rental_product':
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $category_id = intval($_POST['category_id']);
    $stock = intval($_POST['stock']);
    $min_rental_days = intval($_POST['min_rental_days']);
    $max_rental_days = intval($_POST['max_rental_days']);
    $rental_price_per_day = floatval($_POST['rental_price_per_day']);
    $rental_price_per_week = floatval($_POST['rental_price_per_week']);
    $rental_price_per_month = floatval($_POST['rental_price_per_month']);
    $security_deposit = floatval($_POST['security_deposit']);
    
    // Address fields
    $address = trim($_POST['address']);
    $city = trim($_POST['city']);
    $state = trim($_POST['state']);
    $country = trim($_POST['country']);
    $postal_code = trim($_POST['postal_code']);
    
    try {
        // Handle image upload
        $image_url = null;
        $image_gallery = null;
        
        // Handle single image upload (main image)
        if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
            $upload_dir = '../uploads/products/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Validate file type
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (in_array($_FILES['image']['type'], $allowed_types)) {
                // Validate file size (2MB max)
                if ($_FILES['image']['size'] <= 2 * 1024 * 1024) {
                    $file_extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
                    $filename = 'product_' . time() . '_' . uniqid() . '.' . $file_extension;
                    $target_path = $upload_dir . $filename;
                    
                    if (move_uploaded_file($_FILES['image']['tmp_name'], $target_path)) {
                        $image_url = 'uploads/products/' . $filename;
                    }
                }
            }
        }
        
        // Handle multiple gallery images
        if (isset($_FILES['gallery_images']) && is_array($_FILES['gallery_images']['name']) && count($_FILES['gallery_images']['name']) > 0) {
            $gallery_images = [];
            $upload_dir = '../uploads/products/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            for ($i = 0; $i < count($_FILES['gallery_images']['name']); $i++) {
                if ($_FILES['gallery_images']['error'][$i] === 0) {
                    // Validate file type
                    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                    if (in_array($_FILES['gallery_images']['type'][$i], $allowed_types)) {
                        // Validate file size (2MB max)
                        if ($_FILES['gallery_images']['size'][$i] <= 2 * 1024 * 1024) {
                            $file_extension = pathinfo($_FILES['gallery_images']['name'][$i], PATHINFO_EXTENSION);
                            $filename = 'gallery_' . time() . '_' . uniqid() . '_' . $i . '.' . $file_extension;
                            $target_path = $upload_dir . $filename;
                            
                            if (move_uploaded_file($_FILES['gallery_images']['tmp_name'][$i], $target_path)) {
                                $gallery_images[] = 'uploads/products/' . $filename;
                            }
                        }
                    }
                }
            }
            
            if (!empty($gallery_images)) {
                $image_gallery = json_encode($gallery_images);
            }
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO products (
                seller_id, name, description, category_id, stock, 
                product_type, min_rental_days, max_rental_days,
                rental_price_per_day, rental_price_per_week, rental_price_per_month,
                security_deposit, image_url, image_gallery, 
                address, city, state, country, postal_code, status
            ) VALUES (?, ?, ?, ?, ?, 'rental', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
        ");
        
        $stmt->execute([
            $seller_id, $name, $description, $category_id, $stock,
            $min_rental_days, $max_rental_days, $rental_price_per_day,
            $rental_price_per_week, $rental_price_per_month, $security_deposit,
            $image_url, $image_gallery,
            $address, $city, $state, $country, $postal_code
        ]);
        
        $success_message = "Rental product added successfully! Waiting for admin approval.";
    } catch (Exception $e) {
        $error_message = "Failed to add rental product: " . $e->getMessage();
    }
    break;
case 'update_rental_product':
    $product_id = intval($_POST['product_id']);
    $name = trim($_POST['name']);
    $category_id = intval($_POST['category_id']);
    $stock = intval($_POST['stock']);
    $description = trim($_POST['description']);
    
    // Rental-specific fields
    $rental_price_per_day = floatval($_POST['rental_price_per_day']);
    $rental_price_per_week = floatval($_POST['rental_price_per_week']);
    $rental_price_per_month = floatval($_POST['rental_price_per_month']);
    $min_rental_days = intval($_POST['min_rental_days']);
    $max_rental_days = intval($_POST['max_rental_days']);
    $security_deposit = floatval($_POST['security_deposit']);
    
    // Address fields
    $address = trim($_POST['address']);
    $city = trim($_POST['city']);
    $state = trim($_POST['state']);
    $country = trim($_POST['country']);
    $postal_code = trim($_POST['postal_code']);
    
    // Verify product belongs to seller
    $stmt = $pdo->prepare("SELECT id, image_url, image_gallery FROM products WHERE id = ? AND seller_id = ?");
    $stmt->execute([$product_id, $seller_id]);
    $existing_product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing_product) {
        // Handle image update
        $image_url = $existing_product['image_url'];
        $image_gallery = $existing_product['image_gallery'];
        
        // Handle single image update
        if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
            // Delete old image if exists
            if ($image_url && file_exists($_SERVER['DOCUMENT_ROOT'] . '/' . $image_url)) {
                unlink($_SERVER['DOCUMENT_ROOT'] . '/' . $image_url);
            }
            
            // Upload new image
            $upload_dir = '../uploads/products/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Validate file type
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (in_array($_FILES['image']['type'], $allowed_types)) {
                // Validate file size (2MB max)
                if ($_FILES['image']['size'] <= 2 * 1024 * 1024) {
                    $file_extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
                    $filename = 'product_' . time() . '_' . uniqid() . '.' . $file_extension;
                    $target_path = $upload_dir . $filename;
                    
                    if (move_uploaded_file($_FILES['image']['tmp_name'], $target_path)) {
                        $image_url = 'uploads/products/' . $filename;
                    }
                }
            }
        }
        
        // Handle gallery images update
        if (isset($_FILES['gallery_images']) && is_array($_FILES['gallery_images']['name']) && count($_FILES['gallery_images']['name']) > 0) {
            // Decode existing gallery images
            $existing_gallery = !empty($image_gallery) ? json_decode($image_gallery, true) : [];
            
            // Handle new gallery image uploads
            $upload_dir = '../uploads/products/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            for ($i = 0; $i < count($_FILES['gallery_images']['name']); $i++) {
                if ($_FILES['gallery_images']['error'][$i] === 0) {
                    // Validate file type
                    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                    if (in_array($_FILES['gallery_images']['type'][$i], $allowed_types)) {
                        // Validate file size (2MB max)
                        if ($_FILES['gallery_images']['size'][$i] <= 2 * 1024 * 1024) {
                            $file_extension = pathinfo($_FILES['gallery_images']['name'][$i], PATHINFO_EXTENSION);
                            $filename = 'gallery_' . time() . '_' . uniqid() . '_' . $i . '.' . $file_extension;
                            $target_path = $upload_dir . $filename;
                            
                            if (move_uploaded_file($_FILES['gallery_images']['tmp_name'][$i], $target_path)) {
                                $existing_gallery[] = 'uploads/products/' . $filename;
                            }
                        }
                    }
                }
            }
            
            if (!empty($existing_gallery)) {
                $image_gallery_json = json_encode($existing_gallery);
            }
        }
        
        $stmt = $pdo->prepare("UPDATE products SET 
            name = ?, category_id = ?, stock = ?, description = ?,
            rental_price_per_day = ?, rental_price_per_week = ?, rental_price_per_month = ?,
            min_rental_days = ?, max_rental_days = ?, security_deposit = ?,
            image_url = ?, image_gallery = ?,
            address = ?, city = ?, state = ?, country = ?, postal_code = ?
            WHERE id = ?");
        $stmt->execute([
            $name, $category_id, $stock, $description,
            $rental_price_per_day, $rental_price_per_week, $rental_price_per_month,
            $min_rental_days, $max_rental_days, $security_deposit,
            $image_url, $image_gallery,
            $address, $city, $state, $country, $postal_code,
            $product_id
        ]);
        $success_message = "Rental product updated successfully!";
    } else {
        $error_message = "Product not found or access denied.";
    }
    break;
        }
    }
}

// Get seller statistics
$stats_stmt = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT p.id) as total_products,
        COUNT(DISTINCT CASE WHEN p.status = 'active' THEN p.id END) as active_products,
        COUNT(DISTINCT CASE WHEN p.status = 'pending' THEN p.id END) as pending_products,
        COUNT(DISTINCT oi.id) as total_sales,
        COALESCE(SUM(CASE WHEN o.status = 'completed' THEN oi.quantity * oi.price ELSE 0 END), 0) as total_revenue,
        COALESCE(SUM(CASE WHEN o.status = 'completed' THEN oi.quantity * oi.price * 0.1 ELSE 0 END), 0) as commission,
        COUNT(DISTINCT CASE WHEN o.status = 'pending' THEN o.id END) as pending_orders
    FROM users u
    LEFT JOIN products p ON u.id = p.seller_id
    LEFT JOIN order_items oi ON p.id = oi.product_id
    LEFT JOIN orders o ON oi.order_id = o.id
    WHERE u.id = ?
");

$stats_stmt->execute([$seller_id]);
$seller_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Get recent orders
$orders_stmt = $pdo->prepare("
    SELECT 
        o.*,
        u.first_name,
        u.last_name,
        u.email,
        SUM(oi.quantity) as item_count,
        GROUP_CONCAT(p.name SEPARATOR ', ') as product_names
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    JOIN products p ON oi.product_id = p.id
    JOIN users u ON o.user_id = u.id
    WHERE p.seller_id = ?
    GROUP BY o.id
    ORDER BY o.created_at DESC
    LIMIT 5
");

$orders_stmt->execute([$seller_id]);
$recent_orders = $orders_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get seller products
$products_stmt = $pdo->prepare("
    SELECT p.*, c.name as category_name,
           COALESCE(SUM(oi.quantity), 0) as units_sold
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN order_items oi ON p.id = oi.product_id
    WHERE p.seller_id = ?
    GROUP BY p.id
    ORDER BY p.created_at DESC
    LIMIT 10
");

$products_stmt->execute([$seller_id]);
$seller_products = $products_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get sales data for chart (last 30 days)
$sales_chart_stmt = $pdo->prepare("
    SELECT 
        DATE(o.created_at) as date,
        COALESCE(SUM(oi.quantity * oi.price), 0) as daily_revenue,
        COUNT(DISTINCT o.id) as daily_orders
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    JOIN products p ON oi.product_id = p.id
    WHERE p.seller_id = ? AND o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY DATE(o.created_at)
    ORDER BY date
");

$sales_chart_stmt->execute([$seller_id]);
$sales_data = $sales_chart_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get top selling products
$top_products_stmt = $pdo->prepare("
    SELECT 
        p.name,
        p.price,
        SUM(oi.quantity) as units_sold,
        COALESCE(SUM(oi.quantity * oi.price), 0) as revenue
    FROM products p
    LEFT JOIN order_items oi ON p.id = oi.product_id
    LEFT JOIN orders o ON oi.order_id = o.id AND o.status = 'completed'
    WHERE p.seller_id = ?
    GROUP BY p.id
    ORDER BY revenue DESC
    LIMIT 5
");

$top_products_stmt->execute([$seller_id]);
$top_products = $top_products_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get categories for product form
$categories_stmt = $pdo->prepare("SELECT id, name FROM categories WHERE status = 'active' ORDER BY name");
$categories_stmt->execute();
$categories = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get seller information
$seller_info_stmt = $pdo->prepare("SELECT first_name, last_name, email, store_name, business_type FROM users WHERE id = ?");
$seller_info_stmt->execute([$seller_id]);
$seller_info = $seller_info_stmt->fetch(PDO::FETCH_ASSOC);

// Log seller activity
logSellerActivity("Accessed seller dashboard");

function logSellerActivity($activity) {
    global $pdo, $seller_id;
    $stmt = $pdo->prepare("INSERT INTO seller_activities (seller_id, activity, ip_address) VALUES (?, ?, ?)");
    $stmt->execute([$seller_id, $activity, $_SERVER['REMOTE_ADDR']]);
}

function getStatusBadge($status) {
    switch ($status) {
        case 'active': return '<span class="badge bg-success">Active</span>';
        case 'pending': return '<span class="badge bg-warning">Pending</span>';
        case 'inactive': return '<span class="badge bg-secondary">Inactive</span>';
        case 'completed': return '<span class="badge bg-success">Completed</span>';
        case 'processing': return '<span class="badge bg-primary">Processing</span>';
        case 'shipped': return '<span class="badge bg-info">Shipped</span>';
        default: return '<span class="badge bg-secondary">' . ucfirst($status) . '</span>';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seller Dashboard - BSDO Sale</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #1cc88a;
            --warning-color: #f6c23e;
            --danger-color: #e74a3b;
            --dark-color: #2e3a59;
            --light-color: #f8f9fc;
        }
        
        body {
            font-family: 'Nunito', sans-serif;
            background-color: #f8f9fc;
        }
        
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .sidebar {
            background-color: var(--dark-color);
            color: white;
            min-height: calc(100vh - 56px);
        }
        
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 12px 20px;
            border-left: 3px solid transparent;
            transition: all 0.3s;
        }
        
        .sidebar .nav-link:hover {
            color: white;
            background-color: rgba(255, 255, 255, 0.1);
            border-left-color: var(--primary-color);
        }
        
        .sidebar .nav-link.active {
            color: white;
            background-color: rgba(255, 255, 255, 0.15);
            border-left-color: var(--primary-color);
        }
        
        .stats-card {
            border-left: 4px solid;
            transition: transform 0.2s;
        }
        
        .stats-card:hover {
            transform: translateY(-2px);
        }
        
        .stats-card.primary { border-left-color: var(--primary-color); }
        .stats-card.success { border-left-color: var(--secondary-color); }
        .stats-card.warning { border-left-color: var(--warning-color); }
        .stats-card.danger { border-left-color: var(--danger-color); }
        
        .chart-container {
            height: 300px;
            position: relative;
        }
        
        .product-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 5px;
        }
        
        .order-status-badge {
            font-size: 0.75em;
        }
        
        .seller-avatar {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            font-weight: bold;
            margin: 0 auto;
        }
        
        .quick-action {
            background: white;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            transition: all 0.3s;
            border: 1px solid #e3e6f0;
        }
        
        .quick-action:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        
        .quick-action i {
            font-size: 2rem;
            margin-bottom: 10px;
            color: var(--primary-color);
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="fas fa-store me-2"></i>
                <strong>BSDO Seller</strong>
            </a>
            
            <div class="d-flex align-items-center">
                <div class="dropdown">
                    <a class="nav-link dropdown-toggle text-white d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown">
                        <div class="bg-white rounded-circle me-2" style="width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;">
                            <span class="text-primary fw-bold"><?php echo strtoupper(substr($seller_info['first_name'], 0, 1)); ?></span>
                        </div>
                        <span><?php echo htmlspecialchars($seller_info['first_name'] . ' ' . $seller_info['last_name']); ?></span>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                        <li><a class="dropdown-item" href="settings.php"><i class="fas fa-cog me-2"></i>Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-lg-2 sidebar d-none d-lg-block">
                <div class="pt-4">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="dashboard.php">
                                <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="products.php">
                                <i class="fas fa-box me-2"></i>Products
                            </a>
                        </li>
                        <li class="nav-item">
    <a class="nav-link" href="rental_products.php">
        <i class="fas fa-calendar-alt me-2"></i>Rental Products
    </a>
</li>
                        <li class="nav-item">
                            <a class="nav-link" href="orders.php">
                                <i class="fas fa-shopping-cart me-2"></i>Orders
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="analytics.php">
                                <i class="fas fa-chart-bar me-2"></i>Analytics
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="live_stream.php">
                                <i class="fas fa-video me-2"></i>Go Live
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="profile.php">
                                <i class="fas fa-user me-2"></i>Profile
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i>Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-lg-10 col-md-12 p-4">
                <!-- Alerts -->
                <?php if (isset($success_message)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Welcome Section -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col-md-8">
                                        <h3 class="card-title">Welcome back, <?php echo htmlspecialchars($seller_info['first_name']); ?>! 👋</h3>
                                        <p class="text-muted">Here's what's happening with your store today.</p>
                                        <?php if (!empty($seller_info['store_name'])): ?>
                                            <span class="badge bg-primary fs-6"><?php echo htmlspecialchars($seller_info['store_name']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-4 text-center">
                                        <div class="seller-avatar">
                                            <?php echo strtoupper(substr($seller_info['first_name'], 0, 1) . substr($seller_info['last_name'], 0, 1)); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="row mb-4">
                    <div class="col-12">
                        <h5 class="mb-3">Quick Actions</h5>
                        <div class="row">
                            <div class="col-xl-3 col-md-6 mb-3">
                                <div class="quick-action">
                                    <i class="fas fa-plus-circle"></i>
                                    <h6>Add New Product</h6>
                                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addProductModal">
                                        Add Product
                                    </button>
                                </div>
                            </div>
                            <div class="col-xl-3 col-md-6 mb-3">
                                <div class="quick-action">
                                    <i class="fas fa-shopping-cart"></i>
                                    <h6>View Orders</h6>
                                    <a href="orders.php" class="btn btn-success btn-sm">View Orders</a>
                                </div>
                            </div>
                            <div class="col-xl-3 col-md-6 mb-3">
                                <div class="quick-action">
                                    <i class="fas fa-chart-line"></i>
                                    <h6>Sales Analytics</h6>
                                    <a href="analytics.php" class="btn btn-info btn-sm">View Analytics</a>
                                </div>
                            </div>
                            <div class="col-xl-3 col-md-6 mb-3">
                                <div class="quick-action">
                                    <i class="fas fa-comments"></i>
                                    <h6>Customer Inquiries</h6>
                                    <a href="inquiries.php" class="btn btn-primary btn-sm">View Inquiries</a>
                                </div>
                            </div>
                            <div class="col-xl-3 col-md-6 mb-3">
                                <div class="quick-action">
                                    <i class="fas fa-video"></i>
                                    <h6>Go Live</h6>
                                    <a href="live_stream.php" class="btn btn-danger btn-sm">Start Live Stream</a>
                                </div>
                            </div>
                            <div class="col-xl-3 col-md-6 mb-3">
                                <div class="quick-action">
                                    <i class="fas fa-box"></i>
                                    <h6>Manage Products</h6>
                                    <a href="products.php" class="btn btn-warning btn-sm">Manage Products</a>
                                </div>
                            </div>
                            <!-- Add this to the Quick Actions section in dashboard.php -->
<div class="col-xl-3 col-md-6 mb-3">
    <div class="quick-action">
        <i class="fas fa-calendar-alt"></i>
        <h6>Add Rental Product</h6>
        <button class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#addRentalProductModal">
            Add Rental
        </button>
    </div>
</div>
                        </div>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stats-card primary h-100">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Revenue</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">$<?php echo number_format($seller_stats['total_revenue'], 2); ?></div>
                                        <div class="mt-2 text-xs text-muted">Commission: $<?php echo number_format($seller_stats['commission'], 2); ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
<!-- Today's Performance Card -->
<div class="col-xl-3 col-md-6 mb-4">
    <div class="card stats-card info h-100">
        <div class="card-body">
            <div class="row no-gutters align-items-center">
                <div class="col mr-2">
                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Today's Performance</div>
                    <div class="h5 mb-0 font-weight-bold text-gray-800">$<?php echo number_format($today_sales['today_revenue'], 2); ?></div>
                    <div class="mt-2 text-xs text-muted">
                        <?php echo number_format($today_sales['today_orders']); ?> orders today
                    </div>
                </div>
                <div class="col-auto">
                    <i class="fas fa-calendar-day fa-2x text-gray-300"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Pending Inquiries Card -->
<div class="col-xl-3 col-md-6 mb-4">
    <div class="card stats-card warning h-100">
        <div class="card-body">
            <div class="row no-gutters align-items-center">
                <div class="col mr-2">
                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Pending Inquiries</div>
                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($pending_inquiries); ?></div>
                    <div class="mt-2 text-xs text-muted">Need your response</div>
                </div>
                <div class="col-auto">
                    <i class="fas fa-comments fa-2x text-gray-300"></i>
                </div>
            </div>
        </div>
    </div>
</div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stats-card success h-100">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Sales</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($seller_stats['total_sales']); ?></div>
                                        <div class="mt-2 text-xs text-muted">
                                            <span class="text-success"><?php echo number_format($seller_stats['active_products']); ?> active</span> | 
                                            <span class="text-warning"><?php echo number_format($seller_stats['pending_products']); ?> pending</span>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-shopping-cart fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stats-card warning h-100">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Products</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($seller_stats['total_products']); ?></div>
                                        <div class="mt-2 text-xs text-muted">
                                            Active: <?php echo number_format($seller_stats['active_products']); ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-box fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stats-card danger h-100">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Pending Orders</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($seller_stats['pending_orders']); ?></div>
                                        <div class="mt-2 text-xs text-muted">Need your attention</div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-clock fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Row -->
                <div class="row mb-4">
                    <div class="col-xl-8 col-lg-7">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Sales Overview (Last 30 Days)</h6>
                            </div>
                            <div class="card-body">
                                <div class="chart-container">
                                    <canvas id="salesChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-4 col-lg-5">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Top Selling Products</h6>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($top_products)): ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($top_products as $product): ?>
                                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                                <div>
                                                    <div class="fw-bold"><?php echo htmlspecialchars($product['name']); ?></div>
                                                    <small class="text-muted">$<?php echo number_format($product['price'], 2); ?></small>
                                                </div>
                                                <div class="text-end">
                                                    <div class="fw-bold text-success">$<?php echo number_format($product['revenue'], 2); ?></div>
                                                    <small class="text-muted"><?php echo number_format($product['units_sold']); ?> sold</small>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted text-center">No sales data available</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Orders & Products -->
                <div class="row">
                    <div class="col-lg-6 mb-4">
                        <div class="card shadow">
                            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                                <h6 class="m-0 font-weight-bold text-primary">Recent Orders</h6>
                                <a href="orders.php" class="btn btn-sm btn-primary">View All</a>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($recent_orders)): ?>
                                    <div class="table-responsive">
                                        <table class="table table-borderless table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Order</th>
                                                    <th>Customer</th>
                                                    <th>Amount</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($recent_orders as $order): ?>
                                                    <tr>
                                                        <td>
                                                            <div class="fw-bold">#<?php echo htmlspecialchars($order['order_number']); ?></div>
                                                            <small class="text-muted"><?php echo date('M j, Y', strtotime($order['created_at'])); ?></small>
                                                        </td>
                                                        <td>
                                                            <div><?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></div>
                                                            <small class="text-muted"><?php echo number_format($order['item_count']); ?> items</small>
                                                        </td>
                                                        <td class="fw-bold text-success">$<?php echo number_format($order['total_amount'], 2); ?></td>
                                                        <td><?php echo getStatusBadge($order['status']); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted text-center">No recent orders</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6 mb-4">
                        <div class="card shadow">
                            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                                <h6 class="m-0 font-weight-bold text-primary">Your Products</h6>
                                <a href="products.php" class="btn btn-sm btn-primary">View All</a>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($seller_products)): ?>
                                    <div class="table-responsive">
                                        <table class="table table-borderless table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Product</th>
                                                    <th>Price</th>
                                                    <th>Stock</th>
                                                    <th>Status</th>
                                                    <th>Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($seller_products as $product): ?>
    <tr>
        <td>
            <div class="fw-bold"><?php echo htmlspecialchars($product['name']); ?></div>
            <small class="text-muted"><?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?></small>
            <?php if ($product['product_type'] === 'rental'): ?>
                <span class="badge bg-info ms-1">Rental</span>
            <?php endif; ?>
        </td>
        <td class="fw-bold">
            <?php if ($product['product_type'] === 'rental'): ?>
                $<?php echo number_format($product['rental_price_per_day'], 2); ?>/day
            <?php else: ?>
                $<?php echo number_format($product['price'], 2); ?>
            <?php endif; ?>
        </td>
        <td>
            <span class="<?php 
                echo $product['stock'] == 0 ? 'text-danger fw-bold' : 
                     ($product['stock'] < 10 ? 'text-warning fw-bold' : 'text-success'); 
            ?>">
                <?php echo number_format($product['stock']); ?>
            </span>
            <?php if ($product['stock'] < 5 && $product['stock'] > 0): ?>
                <span class="badge bg-warning ms-1">Low Stock</span>
            <?php elseif ($product['stock'] == 0): ?>
                <span class="badge bg-danger ms-1">Out of Stock</span>
            <?php endif; ?>
        </td>
        <td><?php echo getStatusBadge($product['status']); ?></td>
        <td>
            <button class="btn btn-sm btn-outline-primary" 
                    data-bs-toggle="modal" 
                    data-bs-target="#editProductModal" 
                    onclick="editProduct(<?php echo htmlspecialchars(json_encode($product)); ?>)">
                <i class="fas fa-edit"></i>
            </button>
        </td>
    </tr>
<?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted text-center">No products found. <a href="#" data-bs-toggle="modal" data-bs-target="#addProductModal">Add your first product</a></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

<!-- Add Rental Product Modal -->
 
<div class="modal fade" id="addRentalProductModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Rental Product</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_rental_product">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Product Name *</label>
                            <input type="text" class="form-control" name="name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Category *</label>
                            <select class="form-control" name="category_id" required>
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="3"></textarea>
                    </div>

                    <!-- Image Upload Section -->
                    <div class="mb-3">
                        <label class="form-label">Main Product Image</label>
                        <input type="file" class="form-control" name="image" accept="image/*">
                        <div class="form-text">Recommended size: 500x500 pixels. Max file size: 2MB</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Gallery Images</label>
                        <input type="file" class="form-control" name="gallery_images[]" accept="image/*" multiple>
                        <div class="form-text">Upload multiple images for your product gallery. Max file size: 2MB each</div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Stock Quantity *</label>
                            <input type="number" class="form-control" name="stock" min="1" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Min Rental Days *</label>
                            <input type="number" class="form-control" name="min_rental_days" min="1" value="1" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Max Rental Days *</label>
                            <input type="number" class="form-control" name="max_rental_days" min="1" value="30" required>
                        </div>
                    </div>
                    
                    <h6 class="mb-3">Rental Pricing</h6>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Price Per Day ($) *</label>
                            <input type="number" class="form-control" name="rental_price_per_day" step="0.01" min="0" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Price Per Week ($)</label>
                            <input type="number" class="form-control" name="rental_price_per_week" step="0.01" min="0">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Price Per Month ($)</label>
                            <input type="number" class="form-control" name="rental_price_per_month" step="0.01" min="0">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Security Deposit ($)</label>
                        <input type="number" class="form-control" name="security_deposit" step="0.01" min="0">
                        <div class="form-text">Refundable security deposit for the rental</div>
                    </div>
                    
                    <hr>
                    <h6>Product Location</h6>
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Address</label>
                            <input type="text" class="form-control" name="address" placeholder="Street address">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">City</label>
                            <input type="text" class="form-control" name="city" placeholder="City">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">State/Province</label>
                            <input type="text" class="form-control" name="state" placeholder="State or Province">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Postal Code</label>
                            <input type="text" class="form-control" name="postal_code" placeholder="Postal Code">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Country</label>
                            <input type="text" class="form-control" name="country" placeholder="Country">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Rental Product</button>
                </div>
            </form>
        </div>
    </div>
</div>
    <!-- Add Product Modal -->
    <div class="modal fade" id="addProductModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Product</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_product">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Product Name *</label>
                                <input type="text" class="form-control" name="name" required maxlength="255">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Price ($) *</label>
                                <input type="number" class="form-control" name="price" step="0.01" min="0" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Category *</label>
                                <select class="form-control" name="category_id" required>
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Stock Quantity *</label>
                                <input type="number" class="form-control" name="stock" min="0" value="0" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Product Image</label>
                            <input type="file" class="form-control" name="image" accept="image/*">
                            <div class="form-text">Recommended size: 500x500 pixels. Max file size: 2MB</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Gallery Images</label>
                            <input type="file" class="form-control" name="gallery_images[]" accept="image/*" multiple>
                            <div class="form-text">Upload multiple images for your product gallery. Max file size: 2MB each</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="4" maxlength="1000" placeholder="Describe your product..."></textarea>
                            <div class="form-text">Max 1000 characters</div>
                        </div>
                        <hr>
                        <h6>Product Location</h6>
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Address</label>
                                <input type="text" class="form-control" name="address" placeholder="Street address">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">City</label>
                                <input type="text" class="form-control" name="city" placeholder="City">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">State/Province</label>
                                <input type="text" class="form-control" name="state" placeholder="State or Province">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Postal Code</label>
                                <input type="text" class="form-control" name="postal_code" placeholder="Postal Code">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Country</label>
                                <input type="text" class="form-control" name="country" placeholder="Country">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Product</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Product Modal -->
    <div class="modal fade" id="editProductModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Product</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="product_id" id="editProductId">
                        <input type="hidden" name="action" value="update_product" id="editProductAction">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Product Name</label>
                                <input type="text" class="form-control" name="name" id="editProductName" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Price ($)</label>
                                <input type="number" class="form-control" name="price" id="editProductPrice" step="0.01" min="0" required>
                                <div id="rentalPricingFields" style="display: none;">
                                    <label class="form-label mt-2">Rental Pricing</label>
                                    <div class="row">
                                        <div class="col-md-4">
                                            <input type="number" class="form-control mb-2" name="rental_price_per_day" id="editRentalPricePerDay" step="0.01" min="0" placeholder="Per Day">
                                        </div>
                                        <div class="col-md-4">
                                            <input type="number" class="form-control mb-2" name="rental_price_per_week" id="editRentalPricePerWeek" step="0.01" min="0" placeholder="Per Week">
                                        </div>
                                        <div class="col-md-4">
                                            <input type="number" class="form-control mb-2" name="rental_price_per_month" id="editRentalPricePerMonth" step="0.01" min="0" placeholder="Per Month">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Category</label>
                                <select class="form-control" name="category_id" id="editProductCategory" required>
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Stock Quantity</label>
                                <input type="number" class="form-control" name="stock" id="editProductStock" min="0" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" id="editProductDescription" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Product Image</label>
                            <input type="file" class="form-control" name="image" accept="image/*">
                            <div class="form-text">Leave empty to keep current image</div>
                            <div id="currentImage" class="mt-2"></div>
                        </div>
                        <div id="rentalFields" style="display: none;">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Min Rental Days</label>
                                    <input type="number" class="form-control" name="min_rental_days" id="editMinRentalDays" min="1">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Max Rental Days</label>
                                    <input type="number" class="form-control" name="max_rental_days" id="editMaxRentalDays" min="1">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Security Deposit ($)</label>
                                <input type="number" class="form-control" name="security_deposit" id="editSecurityDeposit" step="0.01" min="0">
                            </div>
                        </div>
                        <hr>
                        <h6>Product Location</h6>
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Address</label>
                                <input type="text" class="form-control" name="address" id="editProductAddress" placeholder="Street address">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">City</label>
                                <input type="text" class="form-control" name="city" id="editProductCity" placeholder="City">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">State/Province</label>
                                <input type="text" class="form-control" name="state" id="editProductState" placeholder="State or Province">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Postal Code</label>
                                <input type="text" class="form-control" name="postal_code" id="editProductPostalCode" placeholder="Postal Code">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Country</label>
                                <input type="text" class="form-control" name="country" id="editProductCountry" placeholder="Country">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Product</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sales Chart
        // Image preview functionality removed as we're using simpler file inputs
// Real-time dashboard updates
function updateDashboardStats() {
    fetch('get_dashboard_stats.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update pending orders
                if (data.pending_orders > 0) {
                    document.querySelector('.stats-card.danger .h5').textContent = data.pending_orders;
                    // Add notification badge
                    if (!document.getElementById('pendingOrdersBadge')) {
                        const badge = document.createElement('span');
                        badge.id = 'pendingOrdersBadge';
                        badge.className = 'position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger';
                        badge.textContent = data.pending_orders;
                        document.querySelector('.stats-card.danger').style.position = 'relative';
                        document.querySelector('.stats-card.danger').appendChild(badge);
                    }
                }
                
                // Update pending inquiries
                if (data.pending_inquiries > 0) {
                    document.querySelector('.stats-card.warning .h5').textContent = data.pending_inquiries;
                }
            }
        })
        .catch(error => console.error('Error updating stats:', error));
}

// Update every 30 seconds
setInterval(updateDashboardStats, 30000);

// Initial update
updateDashboardStats();
// Enhanced product type handling
function updateProductForm(productType) {
    const regularFields = document.getElementById('regularProductFields');
    const rentalFields = document.getElementById('rentalProductFields');
    
    if (productType === 'regular') {
        regularFields.style.display = 'block';
        rentalFields.style.display = 'none';
    } else {
        regularFields.style.display = 'none';
        rentalFields.style.display = 'block';
    }
}
        <?php if (!empty($sales_data)): ?>
        const salesCtx = document.getElementById('salesChart').getContext('2d');
        const salesChart = new Chart(salesCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($sales_data, 'date')); ?>,
                datasets: [{
                    label: 'Daily Revenue',
                    data: <?php echo json_encode(array_column($sales_data, 'daily_revenue')); ?>,
                    borderColor: '#4e73df',
                    backgroundColor: 'rgba(78, 115, 223, 0.1)',
                    fill: true,
                    tension: 0.4,
                    yAxisID: 'y'
                }, {
                    label: 'Daily Orders',
                    data: <?php echo json_encode(array_column($sales_data, 'daily_orders')); ?>,
                    borderColor: '#1cc88a',
                    backgroundColor: 'rgba(28, 200, 138, 0.1)',
                    fill: true,
                    tension: 0.4,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Revenue ($)'
                        },
                        ticks: {
                            callback: function(value) {
                                return '$' + value;
                            }
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Orders'
                        },
                        grid: {
                            drawOnChartArea: false,
                        },
                    }
                }
            }
        });
        <?php endif; ?>

        // Edit Product Function
        function editProduct(product) {
            document.getElementById('editProductId').value = product.id;
            document.getElementById('editProductName').value = product.name;
            document.getElementById('editProductCategory').value = product.category_id;
            document.getElementById('editProductStock').value = product.stock;
            document.getElementById('editProductDescription').value = product.description || '';
            
            // Address fields
            document.getElementById('editProductAddress').value = product.address || '';
            document.getElementById('editProductCity').value = product.city || '';
            document.getElementById('editProductState').value = product.state || '';
            document.getElementById('editProductPostalCode').value = product.postal_code || '';
            document.getElementById('editProductCountry').value = product.country || '';
            
            // Show current image
            const currentImage = document.getElementById('currentImage');
            if (product.image_url) {
                currentImage.innerHTML = `<img src="../${product.image_url}" class="img-thumbnail" style="max-height: 100px;">`;
            } else {
                currentImage.innerHTML = '<span class="text-muted">No image uploaded</span>';
            }
            
            // Handle product type (regular vs rental)
            if (product.product_type === 'rental') {
                document.getElementById('editProductAction').value = 'update_rental_product';
                document.getElementById('editProductPrice').style.display = 'none';
                document.getElementById('editProductPrice').previousElementSibling.style.display = 'none';
                document.getElementById('rentalPricingFields').style.display = 'block';
                document.getElementById('rentalFields').style.display = 'block';
                
                // Set rental-specific fields
                document.getElementById('editRentalPricePerDay').value = product.rental_price_per_day || '';
                document.getElementById('editRentalPricePerWeek').value = product.rental_price_per_week || '';
                document.getElementById('editRentalPricePerMonth').value = product.rental_price_per_month || '';
                document.getElementById('editMinRentalDays').value = product.min_rental_days || '';
                document.getElementById('editMaxRentalDays').value = product.max_rental_days || '';
                document.getElementById('editSecurityDeposit').value = product.security_deposit || '';
            } else {
                document.getElementById('editProductAction').value = 'update_product';
                document.getElementById('editProductPrice').style.display = 'block';
                document.getElementById('editProductPrice').previousElementSibling.style.display = 'block';
                document.getElementById('editProductPrice').value = product.price || '';
                document.getElementById('rentalPricingFields').style.display = 'none';
                document.getElementById('rentalFields').style.display = 'none';
            }
        }

        // Auto-refresh dashboard every 5 minutes
        setInterval(() => {
            // In a real application, this would refresh specific components
            console.log('Auto-refresh dashboard...');
        }, 300000);
    </script>
</body>
</html>