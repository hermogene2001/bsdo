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
                    
                    // Check if this seller was referred by another seller and award 0.5% referral bonus
                    try {
                        $pdo->beginTransaction();
                        
                        // Check if this seller was referred by another seller
                        $referral_stmt = $pdo->prepare("SELECT inviter_id FROM referrals WHERE invitee_id = ? AND invitee_role = 'seller' LIMIT 1");
                        $referral_stmt->execute([$seller_id]);
                        $referral_result = $referral_stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($referral_result) {
                            $inviter_id = $referral_result['inviter_id'];
                            $referral_bonus = $price * 0.005; // 0.5% of product price
                
                            // Award the referral bonus to the inviter
                            $bonus_stmt = $pdo->prepare("INSERT INTO user_wallets (user_id, balance) VALUES (?, ?) ON DUPLICATE KEY UPDATE balance = balance + VALUES(balance)");
                            $bonus_stmt->execute([$inviter_id, $referral_bonus]);
                            
                            // Update the referral record with the bonus amount
                            $update_referral_stmt = $pdo->prepare("UPDATE referrals SET reward_to_inviter = reward_to_inviter + ? WHERE invitee_id = ? AND invitee_role = 'seller'");
                            $update_referral_stmt->execute([$referral_bonus, $seller_id]);
                        }
                        
                        $pdo->commit();
                    } catch (Exception $e) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        error_log("Referral bonus error: " . $e->getMessage());
                    }
                    
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
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <!-- Mobile menu button -->
            <button class="btn btn-link text-white d-lg-none me-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar">
                <i class="fas fa-bars"></i>
            </button>

            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-store me-2"></i>
                <strong>BSDO Seller</strong>
            </a>
            
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

    <!-- Mobile Sidebar -->
    <div class="offcanvas offcanvas-start" tabindex="-1" id="mobileSidebar">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title">Menu</h5>
            <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="offcanvas-body">
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
                        <i class="fas fa-video me-2"></i>Live Stream
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="analytics.php">
                        <i class="fas fa-chart-bar me-2"></i>Analytics
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="settings.php">
                        <i class="fas fa-cog me-2"></i>Settings
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
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
                                <i class="fas fa-video me-2"></i>Live Stream
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
                                    <span class="badge filter-badge <?php echo empty($status_filter) ? 'bg-primary active' : 'bg-secondary'; ?>" onclick="clear