<?php
session_start();
require_once '../config.php';

// Check if user is logged in and is seller
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'seller') {
    header('Location: ../login.php');
    exit();
}

// Initialize messages
$success_message = '';
$error_message = '';

// Get seller ID
$seller_id = $_SESSION['user_id'];

// Get payment verification rate setting
$stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'payment_verification_rate'");
$stmt->execute();
$payment_verification_rate = floatval($stmt->fetch(PDO::FETCH_ASSOC)['setting_value'] ?? 0.50);

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
                $payment_channel_id = intval($_POST['payment_channel_id']);
                $address = trim($_POST['address']);
                $city = trim($_POST['city']);
                $state = trim($_POST['state']);
                $country = trim($_POST['country']);
                $postal_code = trim($_POST['postal_code']);
                
                // Validate payment channel
                $channel_stmt = $pdo->prepare("SELECT id FROM payment_channels WHERE id = ? AND is_active = 1");
                $channel_stmt->execute([$payment_channel_id]);
                if (!$channel_stmt->fetch()) {
                    $error_message = "Invalid or inactive payment channel selected.";
                    break;
                }
                
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
                        
                        $file_extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
                        $filename = 'product_' . time() . '_' . uniqid() . '.' . $file_extension;
                        $target_path = $upload_dir . $filename;
                        
                        if (move_uploaded_file($_FILES['image']['tmp_name'], $target_path)) {
                            $image_url = 'uploads/products/' . $filename;
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
                                $file_extension = pathinfo($_FILES['gallery_images']['name'][$i], PATHINFO_EXTENSION);
                                $filename = 'gallery_' . time() . '_' . uniqid() . '_' . $i . '.' . $file_extension;
                                $target_path = $upload_dir . $filename;
                                
                                if (move_uploaded_file($_FILES['gallery_images']['tmp_name'][$i], $target_path)) {
                                    $gallery_images[] = 'uploads/products/' . $filename;
                                }
                            }
                        }
                        
                        if (!empty($gallery_images)) {
                            $image_gallery = json_encode($gallery_images);
                        }
                    }
                    
                    $stmt = $pdo->prepare("INSERT INTO products (seller_id, name, description, price, stock, category_id, payment_channel_id, image_url, image_gallery, address, city, state, country, postal_code, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
                    $stmt->execute([$seller_id, $name, $description, $price, $stock, $category_id, $payment_channel_id, $image_url, $image_gallery, $address, $city, $state, $country, $postal_code]);
                    $success_message = "Product added successfully! Please make payment for verification.";
                    logSellerActivity("Added new product: $name");
                } catch (Exception $e) {
                    $error_message = "Failed to add product: " . $e->getMessage();
                }
                break;
                
            case 'edit_product':
                $product_id = intval($_POST['product_id']);
                $name = trim($_POST['name']);
                $description = trim($_POST['description']);
                $price = floatval($_POST['price']);
                $stock = intval($_POST['stock']);
                $category_id = intval($_POST['category_id']);
                $payment_channel_id = intval($_POST['payment_channel_id']);
                $address = trim($_POST['address']);
                $city = trim($_POST['city']);
                $state = trim($_POST['state']);
                $country = trim($_POST['country']);
                $postal_code = trim($_POST['postal_code']);
                
                // Validate payment channel
                $channel_stmt = $pdo->prepare("SELECT id FROM payment_channels WHERE id = ? AND is_active = 1");
                $channel_stmt->execute([$payment_channel_id]);
                if (!$channel_stmt->fetch()) {
                    $error_message = "Invalid or inactive payment channel selected.";
                    break;
                }
                
                // Verify product belongs to seller
                $stmt = $pdo->prepare("SELECT id, image_url, image_gallery FROM products WHERE id = ? AND seller_id = ?");
                $stmt->execute([$product_id, $seller_id]);
                if ($product = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    // Handle image update
                    $image_sql = '';
                    $params = [$name, $description, $price, $stock, $category_id, $payment_channel_id, $address, $city, $state, $country, $postal_code];
                    
                    // Handle single image update
                    if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
                        $upload_dir = '../uploads/products/';
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0755, true);
                        }
                        
                        $file_extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
                        $filename = 'product_' . time() . '_' . uniqid() . '.' . $file_extension;
                        $target_path = $upload_dir . $filename;
                        
                        if (move_uploaded_file($_FILES['image']['tmp_name'], $target_path)) {
                            $image_url = 'uploads/products/' . $filename;
                            $image_sql .= ', image_url = ?';
                            $params[] = $image_url;
                        }
                    }
                    
                    // Handle gallery images update
                    if (isset($_FILES['gallery_images']) && is_array($_FILES['gallery_images']['name']) && count($_FILES['gallery_images']['name']) > 0) {
                        // Decode existing gallery images
                        $existing_gallery = !empty($product['image_gallery']) ? json_decode($product['image_gallery'], true) : [];
                        
                        // Handle new gallery image uploads
                        $upload_dir = '../uploads/products/';
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0755, true);
                        }
                        
                        for ($i = 0; $i < count($_FILES['gallery_images']['name']); $i++) {
                            if ($_FILES['gallery_images']['error'][$i] === 0) {
                                $file_extension = pathinfo($_FILES['gallery_images']['name'][$i], PATHINFO_EXTENSION);
                                $filename = 'gallery_' . time() . '_' . uniqid() . '_' . $i . '.' . $file_extension;
                                $target_path = $upload_dir . $filename;
                                
                                if (move_uploaded_file($_FILES['gallery_images']['tmp_name'][$i], $target_path)) {
                                    $existing_gallery[] = 'uploads/products/' . $filename;
                                }
                            }
                        }
                        
                        if (!empty($existing_gallery)) {
                            $image_gallery_json = json_encode($existing_gallery);
                            $image_sql .= ', image_gallery = ?';
                            $params[] = $image_gallery_json;
                        }
                    }
                    
                    // Handle gallery image removal
                    if (isset($_POST['remove_gallery_images']) && is_array($_POST['remove_gallery_images'])) {
                        $existing_gallery = !empty($product['image_gallery']) ? json_decode($product['image_gallery'], true) : [];
                        
                        // Remove specified images
                        foreach ($_POST['remove_gallery_images'] as $image_to_remove) {
                            if (($key = array_search($image_to_remove, $existing_gallery)) !== false) {
                                unset($existing_gallery[$key]);
                                // Delete the file from server
                                if (file_exists('../' . $image_to_remove)) {
                                    unlink('../' . $image_to_remove);
                                }
                            }
                        }
                        
                        // Re-index array
                        $existing_gallery = array_values($existing_gallery);
                        
                        if (empty($existing_gallery)) {
                            $image_gallery_json = null;
                            $image_sql .= ', image_gallery = NULL';
                        } else {
                            $image_gallery_json = json_encode($existing_gallery);
                            $image_sql .= ', image_gallery = ?';
                            $params[] = $image_gallery_json;
                        }
                    }
                    
                    $params[] = $product_id;
                    $stmt = $pdo->prepare("UPDATE products SET name = ?, description = ?, price = ?, stock = ?, category_id = ?, payment_channel_id = ?, address = ?, city = ?, state = ?, country = ?, postal_code = ? $image_sql WHERE id = ?");
                    $stmt->execute($params);
                    $success_message = "Product updated successfully!";
                    logSellerActivity("Updated product: $name");
                } else {
                    $error_message = "Product not found or access denied.";
                }
                break;
                
            case 'delete_product':
                $product_id = intval($_POST['product_id']);
                
                // Verify product belongs to seller and has no orders
                $stmt = $pdo->prepare("SELECT p.id, p.name, COUNT(oi.id) as order_count 
                                      FROM products p 
                                      LEFT JOIN order_items oi ON p.id = oi.product_id 
                                      WHERE p.id = ? AND p.seller_id = ? 
                                      GROUP BY p.id");
                $stmt->execute([$product_id, $seller_id]);
                if ($product = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    if ($product['order_count'] == 0) {
                        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
                        $stmt->execute([$product_id]);
                        $success_message = "Product deleted successfully!";
                        logSellerActivity("Deleted product: {$product['name']}");
                    } else {
                        $error_message = "Cannot delete product with existing orders.";
                    }
                } else {
                    $error_message = "Product not found or access denied.";
                }
                break;
                
            case 'upload_payment_slip':
                try {
                    $product_id = intval($_POST['product_id']);
                    $amount = floatval($_POST['amount']);
                    
                    // Verify the product belongs to this seller
                    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND seller_id = ?");
                    $stmt->execute([$product_id, $_SESSION['user_id']]);
                    $product = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$product) {
                        $error_message = "Invalid product!";
                        break;
                    }
                    
                    // Handle file upload
                    if (isset($_FILES['payment_slip']) && $_FILES['payment_slip']['error'] === UPLOAD_ERR_OK) {
                        $upload_dir = '../uploads/payment_slips/';
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0755, true);
                        }
                        
                        $file_name = uniqid() . '_' . basename($_FILES['payment_slip']['name']);
                        $target_file = $upload_dir . $file_name;
                        
                        // Check file type
                        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
                        $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];
                        
                        if (!in_array($imageFileType, $allowed_types)) {
                            $error_message = "Only JPG, JPEG, PNG, GIF & PDF files are allowed!";
                            break;
                        }
                        
                        // Check file size (5MB max)
                        if ($_FILES['payment_slip']['size'] > 5000000) {
                            $error_message = "File is too large. Maximum 5MB allowed!";
                            break;
                        }
                        
                        // Upload file
                        if (move_uploaded_file($_FILES['payment_slip']['tmp_name'], $target_file)) {
                            $slip_path = 'uploads/payment_slips/' . $file_name;
                            
                            // Insert payment slip record
                            $stmt = $pdo->prepare("INSERT INTO payment_slips (product_id, seller_id, slip_path, amount, verification_rate) VALUES (?, ?, ?, ?, ?)");
                            $stmt->execute([$product_id, $_SESSION['user_id'], $slip_path, $amount, $payment_verification_rate]);
                            
                            $success_message = "Payment slip uploaded successfully!";
                            logSellerActivity("Uploaded payment slip for product ID: $product_id");
                        } else {
                            $error_message = "Sorry, there was an error uploading your file.";
                        }
                    } else {
                        $error_message = "Please select a file to upload!";
                    }
                } catch (Exception $e) {
                    $error_message = "Failed to upload payment slip: " . $e->getMessage();
                }
                break;
        }
    }
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'created_at';
$sort_order = isset($_GET['order']) ? $_GET['order'] : 'desc';

// Build WHERE clause for filtering
$where_conditions = [];
$params = [$_SESSION['user_id']];

if (!empty($status_filter) && in_array($status_filter, ['active', 'inactive', 'pending'])) {
    $where_conditions[] = "p.status = ?";
    $params[] = $status_filter;
}

if (!empty($category_filter)) {
    $where_conditions[] = "p.category_id = ?";
    $params[] = $category_filter;
}

if (!empty($search_query)) {
    $where_conditions[] = "(p.name LIKE ? OR p.description LIKE ?)";
    $search_param = "%$search_query%";
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = 'WHERE p.seller_id = ?';
if (!empty($where_conditions)) {
    $where_clause .= " AND " . implode(" AND ", $where_conditions);
}

// Validate sort parameters
$allowed_sorts = ['name', 'price', 'stock', 'created_at'];
$allowed_orders = ['asc', 'desc'];

if (!in_array($sort_by, $allowed_sorts)) {
    $sort_by = 'created_at';
}
if (!in_array($sort_order, $allowed_orders)) {
    $sort_order = 'desc';
}

// Get all products with pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM products p $where_clause";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_products = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_products / $limit);

// Get products with additional info
$sql = "
    SELECT p.*, 
           c.name as category_name,
           COALESCE(SUM(oi.quantity), 0) as total_sold,
           COALESCE(SUM(oi.quantity * oi.price), 0) as total_revenue
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN order_items oi ON p.id = oi.product_id 
    LEFT JOIN orders o ON oi.order_id = o.id AND o.status = 'completed'
    $where_clause
    GROUP BY p.id 
    ORDER BY $sort_by $sort_order 
    LIMIT ? OFFSET ?
";

$stmt = $pdo->prepare($sql);

// Bind parameters separately for filters
foreach ($params as $key => $value) {
    $stmt->bindValue(($key + 1), $value);
}

// Bind LIMIT and OFFSET as integers (at the end of the parameter list)
$stmt->bindValue((count($params) + 1), $limit, PDO::PARAM_INT);
$stmt->bindValue((count($params) + 2), $offset, PDO::PARAM_INT);

$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get categories for filter dropdown
$categories_stmt = $pdo->prepare("SELECT id, name FROM categories WHERE status = 'active' ORDER BY name");
$categories_stmt->execute();
$categories = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get product counts by status for filter badges
$status_counts_stmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM products WHERE seller_id = ? GROUP BY status");
$status_counts_stmt->execute([$_SESSION['user_id']]);
$status_counts = $status_counts_stmt->fetchAll(PDO::FETCH_ASSOC);

// If no status counts, initialize with zeros
if (empty($status_counts)) {
    $status_counts = [
        ['status' => 'active', 'count' => 0],
        ['status' => 'inactive', 'count' => 0],
        ['status' => 'pending', 'count' => 0]
    ];
}

// Get payment slips for seller's products
$stmt = $pdo->prepare("
    SELECT ps.*, p.name as product_name 
    FROM payment_slips ps 
    JOIN products p ON ps.product_id = p.id 
    WHERE ps.seller_id = ? 
    ORDER BY ps.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$payment_slips = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get seller profile for account info
$stmt = $pdo->prepare("SELECT first_name, last_name, phone, account_number FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$seller_info = $stmt->fetch(PDO::FETCH_ASSOC);

// Initialize seller info with default values if not found
if (!$seller_info) {
    $seller_info = [
        'first_name' => 'Seller',
        'last_name' => '',
        'phone' => '',
        'account_number' => ''
    ];
}

// Log seller activity
logSellerActivity("Accessed products page");

function logSellerActivity($activity) {
    global $pdo;
    if (isset($_SESSION['user_id'])) {
        $stmt = $pdo->prepare("INSERT INTO seller_activities (seller_id, activity, ip_address) VALUES (?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], $activity, $_SERVER['REMOTE_ADDR']]);
    }
}

// Helper function to build pagination query string
function buildQueryString($page, $exclude = []) {
    $params = $_GET;
    $params['page'] = $page;
    foreach ($exclude as $key) {
        unset($params[$key]);
    }
    return http_build_query($params);
}

// Helper function to get sort icon
function getSortIcon($column, $current_sort, $current_order) {
    if ($column === $current_sort) {
        return $current_order === 'asc' ? 'fa-sort-up' : 'fa-sort-down';
    }
    return 'fa-sort';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products Management - BSDO Seller</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #1cc88a;
            --warning-color: #f6c23e;
            --danger-color: #e74a3b;
            --dark-color: #2e3a59;
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
        
        .product-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 5px;
        }
        
        .table th {
            cursor: pointer;
            user-select: none;
        }
        
        .table th:hover {
            background-color: #f8f9fa;
        }
        
        .filter-badge {
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .filter-badge.active {
            transform: scale(1.1);
            box-shadow: 0 0 10px rgba(0,0,0,0.2);
        }
        
        .bulk-actions {
            background: #f8f9fa;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
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
        
        .seller-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }
        
        .product-card {
            background: rgba(255, 255, 255, 0.1);
            padding: 20px;
            border-radius: 10px;
            transition: all 0.2s;
            margin-bottom: 20px;
        }
        
        .product-card:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }
        
        /* Remove top margin on mobile nav */
        .offcanvas-header {
            margin-top: 0 !important;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-store me-2"></i>
                <strong>BSDO Seller</strong>
            </a>
            
            <!-- Mobile menu button -->
            <button class="navbar-toggler" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar" aria-controls="mobileSidebar">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="d-flex align-items-center">
                <div class="dropdown">
                    <a class="nav-link dropdown-toggle text-white d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown">
                        <div class="seller-avatar me-2">
                            <?php echo strtoupper(substr($seller_info['first_name'] ?? 'S', 0, 1)); ?>
                        </div>
                        <span><?php echo htmlspecialchars(($seller_info['first_name'] ?? '') . ' ' . ($seller_info['last_name'] ?? '')); ?></span>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="dashboard.php"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a></li>
                        <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Desktop Sidebar (hidden on mobile) -->
            <div class="col-lg-2 sidebar d-none d-lg-block">
                <div class="pt-4">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">
                                <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="products.php">
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
                            <a class="nav-link" href="live_stream.php">
                                <i class="fas fa-video me-2"></i>Go Live
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="analytics.php">
                                <i class="fas fa-chart-bar me-2"></i>Analytics
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

            <!-- Mobile Sidebar (Offcanvas) -->
            <div class="offcanvas offcanvas-start" tabindex="-1" id="mobileSidebar" aria-labelledby="mobileSidebarLabel">
                <div class="offcanvas-header sidebar">
                    <h5 class="offcanvas-title text-white" id="mobileSidebarLabel">Seller Menu</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
                </div>
                <div class="offcanvas-body sidebar">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">
                                <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="products.php">
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
                            <a class="nav-link" href="live_stream.php">
                                <i class="fas fa-video me-2"></i>Go Live
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="analytics.php">
                                <i class="fas fa-chart-bar me-2"></i>Analytics
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

                <!-- Page Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h1 class="h3 mb-0 text-gray-800">Products Management</h1>
                        <p class="text-muted">Manage your product catalog and inventory</p>
                    </div>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProductModal">
                        <i class="fas fa-plus me-2"></i>Add New Product
                    </button>
                </div>

                <!-- Quick Stats -->
                <div class="row mb-4">
                    <div class="col-xl-4 col-md-6 mb-4">
                        <div class="card stats-card primary h-100">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Products</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($total_products); ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-box fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-4 col-md-6 mb-4">
                        <div class="card stats-card success h-100">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Active Products</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php 
                                                $active_count = 0;
                                                foreach ($status_counts as $status) {
                                                    if ($status['status'] === 'active') {
                                                        $active_count = $status['count'];
                                                        break;
                                                    }
                                                }
                                                echo number_format($active_count);
                                            ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-4 col-md-6 mb-4">
                        <div class="card stats-card warning h-100">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Pending Review</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php 
                                                $pending_count = 0;
                                                foreach ($status_counts as $status) {
                                                    if ($status['status'] === 'pending') {
                                                        $pending_count = $status['count'];
                                                        break;
                                                    }
                                                }
                                                echo number_format($pending_count);
                                            ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-clock fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters Card -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Filters & Search</h6>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Status Filter</label>
                                <div class="d-flex flex-wrap gap-2">
                                    <span class="badge filter-badge <?php echo empty($status_filter) ? 'bg-primary active' : 'bg-secondary'; ?>" onclick="clearFilter('status')">All</span>
                                    <?php foreach ($status_counts as $status_count): ?>
                                        <span class="badge filter-badge <?php echo ($status_filter === $status_count['status']) ? 'bg-primary active' : 'bg-light text-dark'; ?>" 
                                              onclick="setFilter('status', '<?php echo $status_count['status']; ?>')">
                                            <?php echo ucfirst($status_count['status']); ?> 
                                            <span class="badge bg-dark"><?php echo $status_count['count']; ?></span>
                                        </span>
                                    <?php endforeach; ?>
                                    <input type="hidden" name="status" id="statusFilter" value="<?php echo htmlspecialchars($status_filter); ?>">
                                </div>
                            </div>
                            
                            <div class="col-md-3">
                                <label for="category" class="form-label">Category</label>
                                <select class="form-control" id="category" name="category">
                                    <option value="">All Categories</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>" <?php echo $category_filter == $category['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($category['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-3">
                                <label for="search" class="form-label">Search Products</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="search" name="search" placeholder="Search by name or description..." value="<?php echo htmlspecialchars($search_query); ?>">
                                    <button class="btn btn-primary" type="submit">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary me-2">Apply Filters</button>
                                <a href="products.php" class="btn btn-outline-secondary">Clear</a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Bulk Actions -->
                <div class="bulk-actions" id="bulkActions" style="display: none;">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <span id="selectedCount">0</span> products selected
                        </div>
                        <div class="col-md-6 text-end">
                            <form method="POST" id="bulkActionForm" class="d-inline">
                                <input type="hidden" name="product_ids" id="bulkProductIds">
                                <select class="form-select d-inline-block w-auto me-2" name="bulk_action_type" id="bulkActionType">
                                    <option value="">Bulk Actions</option>
                                    <option value="activate">Activate</option>
                                    <option value="deactivate">Deactivate</option>
                                    <option value="delete">Delete</option>
                                </select>
                                <button type="submit" name="action" value="bulk_action" class="btn btn-primary btn-sm" onclick="return confirmBulkAction()">Apply</button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="clearSelection()">Clear Selection</button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Products Table -->
                <div class="card shadow">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-primary">Your Products (<?php echo number_format($total_products); ?>)</h6>
                        <div class="text-muted small">
                            Page <?php echo $page; ?> of <?php echo max(1, $total_pages); ?>
                        </div>
                    </div>
                    <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Image</th>
                                            <th>Product</th>
                                            <th>Price</th>
                                            <th>Stock</th>
                                            <th>Status</th>
                                            <th>Verification</th>
                                            <th>Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($products)): ?>
                                            <?php foreach ($products as $product): ?>
                                                <tr>
                                                    <td>
                                                        <?php if (!empty($product['image_url'])): ?>
                                                            <img src="<?php echo htmlspecialchars($product['image_url']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" style="width: 50px; height: 50px; object-fit: cover;">
                                                        <?php else: ?>
                                                            <div class="bg-light d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                                                <i class="fas fa-image text-muted"></i>
                                                            </div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <div><?php echo htmlspecialchars($product['name']); ?></div>
                                                        <small class="text-muted"><?php echo htmlspecialchars(substr($product['description'], 0, 50)) . (strlen($product['description']) > 50 ? '...' : ''); ?></small>
                                                    </td>
                                                    <td>$<?php echo number_format($product['price'], 2); ?></td>
                                                    <td><?php echo $product['stock']; ?></td>
                                                    <td>
                                                        <span class="badge <?php echo $product['status'] === 'active' ? 'bg-success' : 'bg-secondary'; ?>">
                                                            <?php echo ucfirst($product['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php if (!empty($product['payment_channel_id'])): ?>
                                                            <span class="badge <?php echo $product['verification_payment_status'] === 'paid' ? 'bg-success' : ($product['verification_payment_status'] === 'pending' ? 'bg-warning' : 'bg-danger'); ?>">
                                                                <?php echo ucfirst($product['verification_payment_status']); ?>
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary">No Channel</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo date('M j, Y', strtotime($product['created_at'])); ?></td>
                                                    <td>
                                                        <div class="btn-group">
                                                            <button class="btn btn-sm btn-outline-primary" onclick='editProduct(<?php echo json_encode($product); ?>)'>
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                            <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this product?');">
                                                                <input type="hidden" name="action" value="delete_product">
                                                                <input type="hidden" name="id" value="<?php echo $product['id']; ?>">
                                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            </form>
                                                            <?php if ($product['verification_payment_status'] !== 'paid'): ?>
                                                                <button class="btn btn-sm btn-outline-success" onclick="uploadPaymentSlip(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars($product['name'], ENT_QUOTES); ?>', <?php echo $product['price']; ?>, <?php echo ($product['price'] * $payment_verification_rate / 100); ?>)">
                                                                    <i class="fas fa-money-check"></i>
                                                                </button>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="7" class="text-center">No products found. <a href="#" data-bs-toggle="modal" data-bs-target="#addProductModal">Add your first product</a></td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination -->
                            <?php if ($total_pages > 1): ?>
                                <nav aria-label="Page navigation">
                                    <ul class="pagination justify-content-center">
                                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?<?php echo buildQueryString($page - 1); ?>">Previous</a>
                                        </li>
                                        
                                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                                <a class="page-link" href="?<?php echo buildQueryString($i); ?>"><?php echo $i; ?></a>
                                            </li>
                                        <?php endfor; ?>
                                        
                                        <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?<?php echo buildQueryString($page + 1); ?>">Next</a>
                                        </li>
                                    </ul>
                                </nav>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
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
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Category *</label>
                                <select class="form-control" name="category_id" required>
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Stock Quantity *</label>
                                <input type="number" class="form-control" name="stock" min="0" value="0" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Payment Channel *</label>
                                <select class="form-control" name="payment_channel_id" required>
                                    <option value="">Select Payment Channel</option>
                                    <?php 
                                    // Get active payment channels
                                    $channels_stmt = $pdo->prepare("SELECT id, name, type, account_name, account_number FROM payment_channels WHERE is_active = 1 ORDER BY name");
                                    $channels_stmt->execute();
                                    $payment_channels = $channels_stmt->fetchAll(PDO::FETCH_ASSOC);
                                    foreach ($payment_channels as $channel): ?>
                                        <option value="<?php echo $channel['id']; ?>">
                                            <?php echo htmlspecialchars($channel['name']); ?>
                                            (<?php 
                                            $type_labels = [
                                                'bank' => 'Bank',
                                                'mobile_money' => 'Mobile Money',
                                                'paypal' => 'PayPal',
                                                'cryptocurrency' => 'Crypto',
                                                'other' => 'Other'
                                            ];
                                            echo $type_labels[$channel['type']] ?? $channel['type'];
                                            if (!empty($channel['account_number'])) {
                                                echo ' - ' . htmlspecialchars($channel['account_number']);
                                            }
                                            ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">Select where to pay the verification fee</div>
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
                        <button type="submit" name="action" value="add_product" class="btn btn-primary">Add Product</button>
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
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Product Name *</label>
                                <input type="text" class="form-control" name="name" id="editProductName" required maxlength="255">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Price ($) *</label>
                                <input type="number" class="form-control" name="price" id="editProductPrice" step="0.01" min="0" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Category *</label>
                                <select class="form-control" name="category_id" id="editProductCategory" required>
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Stock Quantity *</label>
                                <input type="number" class="form-control" name="stock" id="editProductStock" min="0" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Payment Channel *</label>
                                <select class="form-control" name="payment_channel_id" id="editProductPaymentChannel" required>
                                    <option value="">Select Payment Channel</option>
                                    <?php 
                                    // Get active payment channels
                                    $channels_stmt = $pdo->prepare("SELECT id, name, type, account_name, account_number FROM payment_channels WHERE is_active = 1 ORDER BY name");
                                    $channels_stmt->execute();
                                    $payment_channels = $channels_stmt->fetchAll(PDO::FETCH_ASSOC);
                                    foreach ($payment_channels as $channel): ?>
                                        <option value="<?php echo $channel['id']; ?>">
                                            <?php echo htmlspecialchars($channel['name']); ?>
                                            (<?php 
                                            $type_labels = [
                                                'bank' => 'Bank',
                                                'mobile_money' => 'Mobile Money',
                                                'paypal' => 'PayPal',
                                                'cryptocurrency' => 'Crypto',
                                                'other' => 'Other'
                                            ];
                                            echo $type_labels[$channel['type']] ?? $channel['type'];
                                            if (!empty($channel['account_number'])) {
                                                echo ' - ' . htmlspecialchars($channel['account_number']);
                                            }
                                            ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Product Image</label>
                            <input type="file" class="form-control" name="image" accept="image/*">
                            <div class="form-text">Leave empty to keep current image</div>
                            <div id="currentImage" class="mt-2"></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Gallery Images</label>
                            <input type="file" class="form-control" name="gallery_images[]" accept="image/*" multiple>
                            <div class="form-text">Upload additional images for your product gallery</div>
                            <div id="currentGallery" class="mt-2"></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" id="editProductDescription" rows="4" maxlength="1000"></textarea>
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
                        <button type="submit" name="action" value="edit_product" class="btn btn-primary">Update Product</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Stock Update Modal -->
    <div class="modal fade" id="stockModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Update Stock</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="product_id" id="stockProductId">
                        <div class="mb-3">
                            <label class="form-label">Product</label>
                            <div id="stockProductName" class="fw-bold"></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Current Stock</label>
                            <div id="currentStock" class="fw-bold"></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">New Stock Quantity *</label>
                            <input type="number" class="form-control" name="stock" id="newStock" min="0" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="action" value="update_stock" class="btn btn-primary">Update Stock</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Edit Product Function
        function editProduct(product) {
            document.getElementById('editProductId').value = product.id;
            document.getElementById('editProductName').value = product.name;
            document.getElementById('editProductPrice').value = product.price;
            document.getElementById('editProductStock').value = product.stock;
            document.getElementById('editProductCategory').value = product.category_id;
            document.getElementById('editProductDescription').value = product.description || '';
            document.getElementById('editProductAddress').value = product.address || '';
            document.getElementById('editProductCity').value = product.city || '';
            document.getElementById('editProductState').value = product.state || '';
            document.getElementById('editProductPostalCode').value = product.postal_code || '';
            document.getElementById('editProductCountry').value = product.country || '';
            if (product.payment_channel_id) {
                document.getElementById('editProductPaymentChannel').value = product.payment_channel_id;
            }
            
            // Show current image
            const currentImage = document.getElementById('currentImage');
            if (product.image_url) {
                currentImage.innerHTML = `<img src="../${product.image_url}" class="product-image" alt="Current Image">`;
            } else {
                currentImage.innerHTML = '<span class="text-muted">No image uploaded</span>';
            }
            
            // Show current gallery images
            const currentGallery = document.getElementById('currentGallery');
            if (product.image_gallery) {
                try {
                    const galleryImages = JSON.parse(product.image_gallery);
                    if (Array.isArray(galleryImages) && galleryImages.length > 0) {
                        let galleryHtml = '<div class="mt-2"><strong>Current Gallery Images:</strong></div>';
                        galleryImages.forEach((img, index) => {
                            galleryHtml += `
                                <div class="d-inline-block position-relative me-2">
                                    <img src="../${img}" class="product-image" style="width: 60px; height: 60px;" alt="Gallery Image ${index + 1}">
                                    <button type="button" class="btn btn-danger btn-sm position-absolute top-0 end-0" 
                                            onclick="removeGalleryImage('${img}', ${product.id})" 
                                            style="padding: 2px 5px; font-size: 0.7rem;">
                                        ×
                                    </button>
                                </div>
                            `;
                        });
                        currentGallery.innerHTML = galleryHtml;
                    } else {
                        currentGallery.innerHTML = '<span class="text-muted">No gallery images uploaded</span>';
                    }
                } catch (e) {
                    currentGallery.innerHTML = '<span class="text-muted">No gallery images uploaded</span>';
                }
            } else {
                currentGallery.innerHTML = '<span class="text-muted">No gallery images uploaded</span>';
            }
            
            // Show the modal
            var editModal = new bootstrap.Modal(document.getElementById('editProductModal'));
            editModal.show();
        }

        // Function to remove gallery images
        function removeGalleryImage(imagePath, productId) {
            if (confirm('Are you sure you want to remove this gallery image?')) {
                // Add hidden input to form for removal
                const form = document.querySelector('#editProductModal form');
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'remove_gallery_images[]';
                hiddenInput.value = imagePath;
                form.appendChild(hiddenInput);
                
                // Remove the image preview
                const imageElement = event.target.closest('.d-inline-block');
                if (imageElement) {
                    imageElement.remove();
                }
            }
        }
        
        // Upload Payment Slip Function
        function uploadPaymentSlip(productId, productName, productPrice, verificationAmount) {
            document.getElementById('slip_product_id').value = productId;
            document.getElementById('slip_product_name').value = productName;
            document.getElementById('slip_product_price').value = '$' + productPrice.toFixed(2);
            document.getElementById('slip_verification_amount').value = '$' + verificationAmount.toFixed(2);
            document.getElementById('slip_amount').value = verificationAmount.toFixed(2);
            
            var slipModal = new bootstrap.Modal(document.getElementById('uploadPaymentSlipModal'));
            slipModal.show();
        }
    </script>
    
    <!-- Upload Payment Slip Modal -->
    <div class="modal fade" id="uploadPaymentSlipModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Upload Payment Slip</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="upload_payment_slip">
                        <input type="hidden" name="product_id" id="slip_product_id">
                        
                        <div class="mb-3">
                            <label class="form-label">Product</label>
                            <input type="text" class="form-control" id="slip_product_name" readonly>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Product Price</label>
                                <input type="text" class="form-control" id="slip_product_price" readonly>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Verification Amount (<?php echo $payment_verification_rate; ?>%)</label>
                                <input type="text" class="form-control" id="slip_verification_amount" readonly>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Amount Paid *</label>
                            <input type="number" class="form-control" name="amount" id="slip_amount" step="0.01" min="0" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Upload Payment Slip *</label>
                            <input type="file" class="form-control" name="payment_slip" accept="image/*,application/pdf" required>
                            <div class="form-text">Upload JPG, PNG, GIF or PDF files. Max file size: 5MB</div>
                        </div>
                        
                        <div class="alert alert-info">
                            <h6 class="alert-heading">Payment Information</h6>
                            <p>Please make a payment of the verification amount to one of the following accounts:</p>
                            <ul class="mb-0">
                                <li><strong>Phone Number:</strong> <?php echo htmlspecialchars($seller_info['phone'] ?? 'Not set'); ?></li>
                                <li><strong>Account Number:</strong> <?php echo htmlspecialchars($seller_info['account_number'] ?? 'Not set'); ?></li>
                            </ul>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Upload Payment Slip</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
