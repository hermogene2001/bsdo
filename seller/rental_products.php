<?php
session_start();
require_once '../config.php';

// Check if user is logged in and is seller
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'seller') {
    header('Location: ../login.php');
    exit();
}

$seller_id = $_SESSION['user_id'];
$error_message = '';
$success_message = '';

// Get payment verification rate setting
$stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'payment_verification_rate'");
$stmt->execute();
$payment_verification_rate = floatval($stmt->fetch(PDO::FETCH_ASSOC)['setting_value'] ?? 0.50);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_rental_product':
                $name = trim($_POST['name']);
                $description = trim($_POST['description']);
                $category_id = intval($_POST['category_id']);
                $stock = intval($_POST['stock']);
                $payment_channel_id = intval($_POST['payment_channel_id']);
                
                // Validate payment channel
                $channel_stmt = $pdo->prepare("SELECT id FROM payment_channels WHERE id = ? AND is_active = 1");
                $channel_stmt->execute([$payment_channel_id]);
                if (!$channel_stmt->fetch()) {
                    $error_message = "Invalid or inactive payment channel selected.";
                    break;
                }
                
                // Address fields
                $address = trim($_POST['address']);
                $city = trim($_POST['city']);
                $state = trim($_POST['state']);
                $country = trim($_POST['country']);
                $postal_code = trim($_POST['postal_code']);
                
                // Rental-specific fields
                $is_rental = 1;
                $rental_price_per_day = floatval($_POST['rental_price_per_day']);
                $rental_price_per_week = floatval($_POST['rental_price_per_week']);
                $rental_price_per_month = floatval($_POST['rental_price_per_month']);
                $min_rental_days = intval($_POST['min_rental_days']);
                $max_rental_days = intval($_POST['max_rental_days']);
                $security_deposit = floatval($_POST['security_deposit']);
                
                // Handle image uploads
                $image_url = null;
                $image_gallery = null;
                
                // Handle single image upload (main image)
                if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] == 0) {
                    $image_url = uploadProductImage($_FILES['product_image']);
                }
                
                // Handle multiple gallery images
                if (isset($_FILES['gallery_images']) && is_array($_FILES['gallery_images']['name']) && count($_FILES['gallery_images']['name']) > 0) {
                    $gallery_images = [];
                    $upload_dir = "../uploads/products/";
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    
                    for ($i = 0; $i < count($_FILES['gallery_images']['name']); $i++) {
                        if ($_FILES['gallery_images']['error'][$i] === 0) {
                            // Validate file type
                            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                            if (in_array($_FILES['gallery_images']['type'][$i], $allowed_types)) {
                                // Validate file size (5MB max)
                                if ($_FILES['gallery_images']['size'][$i] <= 5 * 1024 * 1024) {
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
                
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO products 
                        (seller_id, name, description, category_id, stock, is_rental, 
                         rental_price_per_day, rental_price_per_week, rental_price_per_month,
                         min_rental_days, max_rental_days, security_deposit, 
                         image_url, image_gallery, address, city, state, country, postal_code, 
                         payment_channel_id, status) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
                    ");
                    $stmt->execute([
                        $seller_id, $name, $description, $category_id, $stock, $is_rental,
                        $rental_price_per_day, $rental_price_per_week, $rental_price_per_month,
                        $min_rental_days, $max_rental_days, $security_deposit, 
                        $image_url, $image_gallery, $address, $city, $state, $country, $postal_code,
                        $payment_channel_id
                    ]);
                    $success_message = "Rental product added successfully! Waiting for admin approval.";
                } catch (Exception $e) {
                    $error_message = "Failed to add rental product: " . $e->getMessage();
                }
                break;
                
            case 'update_rental_product':
                $product_id = intval($_POST['product_id']);
                $name = trim($_POST['name']);
                $description = trim($_POST['description']);
                $category_id = intval($_POST['category_id']);
                $stock = intval($_POST['stock']);
                $payment_channel_id = intval($_POST['payment_channel_id']);
                $rental_price_per_day = floatval($_POST['rental_price_per_day']);
                $rental_price_per_week = floatval($_POST['rental_price_per_week']);
                $rental_price_per_month = floatval($_POST['rental_price_per_month']);
                $min_rental_days = intval($_POST['min_rental_days']);
                $max_rental_days = intval($_POST['max_rental_days']);
                $security_deposit = floatval($_POST['security_deposit']);
                
                // Validate payment channel
                $channel_stmt = $pdo->prepare("SELECT id FROM payment_channels WHERE id = ? AND is_active = 1");
                $channel_stmt->execute([$payment_channel_id]);
                if (!$channel_stmt->fetch()) {
                    $error_message = "Invalid or inactive payment channel selected.";
                    break;
                }
                
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
                    
                    // Check if user wants to remove current image
                    if (isset($_POST['remove_current_image']) && $_POST['remove_current_image'] == 1) {
                        // Delete the old image file
                        if ($image_url && file_exists($_SERVER['DOCUMENT_ROOT'] . '/' . $image_url)) {
                            unlink($_SERVER['DOCUMENT_ROOT'] . '/' . $image_url);
                        }
                        $image_url = null;
                    }
                    
                    // Check if new main image is uploaded
                    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] == 0) {
                        // Delete old image if exists
                        if ($image_url && file_exists($_SERVER['DOCUMENT_ROOT'] . '/' . $image_url)) {
                            unlink($_SERVER['DOCUMENT_ROOT'] . '/' . $image_url);
                        }
                        // Upload new image
                        $image_url = uploadProductImage($_FILES['product_image']);
                    }
                    
                    // Handle gallery images update
                    $existing_gallery = !empty($image_gallery) ? json_decode($image_gallery, true) : [];
                    
                    // Handle new gallery image uploads
                    if (isset($_FILES['gallery_images']) && is_array($_FILES['gallery_images']['name']) && count($_FILES['gallery_images']['name']) > 0) {
                        $upload_dir = "../uploads/products/";
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0777, true);
                        }
                        
                        for ($i = 0; $i < count($_FILES['gallery_images']['name']); $i++) {
                            if ($_FILES['gallery_images']['error'][$i] === 0) {
                                // Validate file type
                                $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                                if (in_array($_FILES['gallery_images']['type'][$i], $allowed_types)) {
                                    // Validate file size (5MB max)
                                    if ($_FILES['gallery_images']['size'][$i] <= 5 * 1024 * 1024) {
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
                    }
                    
                    // Handle gallery image removal
                    if (isset($_POST['remove_gallery_images']) && is_array($_POST['remove_gallery_images'])) {
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
                    }
                    
                    // Update gallery JSON
                    if (empty($existing_gallery)) {
                        $image_gallery_json = null;
                    } else {
                        $image_gallery_json = json_encode($existing_gallery);
                    }
                    
                    $stmt = $pdo->prepare("
                        UPDATE products SET 
                        name = ?, description = ?, category_id = ?, stock = ?,
                        rental_price_per_day = ?, rental_price_per_week = ?, rental_price_per_month = ?,
                        min_rental_days = ?, max_rental_days = ?, security_deposit = ?, 
                        image_url = ?, image_gallery = ?, address = ?, city = ?, state = ?, country = ?, postal_code = ?,
                        payment_channel_id = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $name, $description, $category_id, $stock,
                        $rental_price_per_day, $rental_price_per_week, $rental_price_per_month,
                        $min_rental_days, $max_rental_days, $security_deposit, 
                        $image_url, $image_gallery_json, $address, $city, $state, $country, $postal_code,
                        $payment_channel_id, $product_id
                    ]);
                    $success_message = "Rental product updated successfully!";
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
                            logSellerActivity("Uploaded payment slip for rental product ID: $product_id");
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

// Image upload function
function uploadProductImage($file) {
    $upload_dir = "../uploads/products/";
    
    // Create uploads directory if it doesn't exist
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    // Handle single file or array of files
    if (is_array($file['name'])) {
        // Multiple files - for now, we'll just use the first one
        $file = [
            'name' => $file['name'][0],
            'type' => $file['type'][0],
            'tmp_name' => $file['tmp_name'][0],
            'error' => $file['error'][0],
            'size' => $file['size'][0]
        ];
    }
    
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("File upload error: " . $file['error']);
    }
    
    // Validate file type
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($file['type'], $allowed_types)) {
        throw new Exception("Only JPG, PNG, GIF, and WebP images are allowed.");
    }
    
    // Validate file size (2MB max)
    if ($file['size'] > 5 * 1024 * 1024) {
        throw new Exception("File size must be less than 5MB.");
    }
    
    // Generate unique filename
    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '_' . time() . '.' . $file_extension;
    $filepath = $upload_dir . $filename;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        throw new Exception("Failed to move uploaded file.");
    }
    
    return "uploads/products/" . $filename;
}

// Get seller's rental products
$rental_products_stmt = $pdo->prepare("
    SELECT p.*, c.name as category_name,
           COUNT(DISTINCT ro.id) as total_rentals,
           COALESCE(SUM(CASE WHEN ro.status = 'completed' THEN ro.total_rental_amount ELSE 0 END), 0) as total_rental_revenue
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN rental_orders ro ON p.id = ro.product_id
    WHERE p.seller_id = ? AND p.is_rental = 1
    GROUP BY p.id
    ORDER BY p.created_at DESC
");

$rental_products_stmt->execute([$seller_id]);
$rental_products = $rental_products_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get rental statistics
$rental_stats_stmt = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT p.id) as total_rental_products,
        COUNT(DISTINCT ro.id) as total_rental_orders,
        COUNT(DISTINCT CASE WHEN ro.status = 'active' THEN ro.id END) as active_rentals,
        COUNT(DISTINCT CASE WHEN ro.status = 'pending' THEN ro.id END) as pending_rentals,
        COALESCE(SUM(ro.total_rental_amount), 0) as total_rental_revenue,
        COALESCE(SUM(CASE WHEN ro.status = 'active' THEN ro.total_rental_amount ELSE 0 END), 0) as active_rental_revenue
    FROM products p
    LEFT JOIN rental_orders ro ON p.id = ro.product_id
    WHERE p.seller_id = ? AND p.is_rental = 1
");

$rental_stats_stmt->execute([$seller_id]);
$rental_stats = $rental_stats_stmt->fetch(PDO::FETCH_ASSOC);

// Get categories for forms
$categories_stmt = $pdo->prepare("SELECT id, name FROM categories WHERE status = 'active' ORDER BY name");
$categories_stmt->execute();
$categories = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get payment channels for forms
$channels_stmt = $pdo->prepare("SELECT id, name, type, account_name, account_number FROM payment_channels WHERE is_active = 1 ORDER BY name");
$channels_stmt->execute();
$payment_channels = $channels_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get seller information
$seller_info_stmt = $pdo->prepare("SELECT first_name, last_name, store_name FROM users WHERE id = ?");
$seller_info_stmt->execute([$seller_id]);
$seller_info = $seller_info_stmt->fetch(PDO::FETCH_ASSOC);

// Log seller activity
logSellerActivity("Accessed rental products management");

function logSellerActivity($activity) {
    global $pdo, $seller_id;
    $stmt = $pdo->prepare("INSERT INTO seller_activities (seller_id, activity, ip_address) VALUES (?, ?, ?)");
    $stmt->execute([$seller_id, $activity, $_SERVER['REMOTE_ADDR']]);
}

function formatCurrency($amount) {
    return '$' . number_format($amount, 2);
}

function getRentalStatusBadge($status) {
    switch ($status) {
        case 'active': return '<span class="badge bg-success">Active</span>';
        case 'pending': return '<span class="badge bg-warning">Pending</span>';
        case 'inactive': return '<span class="badge bg-secondary">Inactive</span>';
        case 'completed': return '<span class="badge bg-info">Completed</span>';
        case 'overdue': return '<span class="badge bg-danger">Overdue</span>';
        default: return '<span class="badge bg-secondary">' . ucfirst($status) . '</span>';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rental Products - BSDO Sale</title>
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
    <style>
        .rental-badge {
            background: linear-gradient(135deg, #36b9cc, #2c9faf);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .rental-card {
            border-left: 4px solid #36b9cc;
        }
        
        .price-option {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
    </style>

</head>
<body>
    <!-- Navigation (same as other seller pages) -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
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

    <div class="container-fluid ">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-lg-2 sidebar d-none d-lg-block ">
                <div class="pt-4">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">
                                <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="products.php">
                                <i class="fas fa-box me-2"></i>Products
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="rental_products.php">
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
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h2 class="mb-1"><i class="fas fa-calendar-alt me-2"></i>Rental Products</h2>
                                <p class="text-muted mb-0">Manage your rental products and bookings</p>
                            </div>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRentalProductModal">
                                <i class="fas fa-plus me-2"></i>Add Rental Product
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Rental Statistics -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stats-card primary h-100">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Rental Products</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $rental_stats['total_rental_products']; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-calendar-alt fa-2x text-gray-300"></i>
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
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Rentals</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $rental_stats['total_rental_orders']; ?></div>
                                        <div class="mt-2 text-xs text-muted">
                                            <span class="text-success"><?php echo $rental_stats['active_rentals']; ?> active</span> | 
                                            <span class="text-warning"><?php echo $rental_stats['pending_rentals']; ?> pending</span>
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
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Rental Revenue</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">$<?php echo number_format($rental_stats['total_rental_revenue'], 2); ?></div>
                                        <div class="mt-2 text-xs text-muted">
                                            Active: $<?php echo number_format($rental_stats['active_rental_revenue'], 2); ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stats-card info h-100">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Avg. per Rental</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $rental_stats['total_rental_orders'] > 0 ? 
                                                '$' . number_format($rental_stats['total_rental_revenue'] / $rental_stats['total_rental_orders'], 2) : '$0.00'; ?>
                                        </div>
                                        <div class="mt-2 text-xs text-muted">Per rental average</div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-chart-line fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Rental Products List -->
                <div class="card shadow">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-primary">Your Rental Products</h6>
                        <span class="badge bg-primary"><?php echo count($rental_products); ?> products</span>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($rental_products)): ?>
                            <div class="table-responsive">
                                <table class="table table-borderless table-hover">
                                    <thead>
                                        <tr>
                                            <th>Product</th>
                                            <th>Rental Prices</th>
                                            <th>Rental Period</th>
                                            <th>Stock</th>
                                            <th>Total Rentals</th>
                                            <th>Revenue</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($rental_products as $product): ?>
                                            <tr>
                                                <td>
                                                    <div class="fw-bold"><?php echo htmlspecialchars($product['name']); ?></div>
                                                    <small class="text-muted"><?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?></small>
                                                    <div><span class="rental-badge">Rental</span></div>
                                                </td>
                                                
                                                <td>
                                                    <div class="small">
                                                        <div>Day: $<?php echo number_format($product['rental_price_per_day'], 2); ?></div>
                                                        <div>Week: $<?php echo number_format($product['rental_price_per_week'], 2); ?></div>
                                                        <div>Month: $<?php echo number_format($product['rental_price_per_month'], 2); ?></div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="small">
                                                        <div>Min: <?php echo $product['min_rental_days']; ?> days</div>
                                                        <div>Max: <?php echo $product['max_rental_days']; ?> days</div>
                                                        <div>Deposit: $<?php echo number_format($product['security_deposit'], 2); ?></div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="<?php echo $product['stock'] < 3 ? 'text-danger fw-bold' : 'text-success'; ?>">
                                                        <?php echo number_format($product['stock']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo number_format($product['total_rentals']); ?></td>
                                                <td class="fw-bold text-success">$<?php echo number_format($product['total_rental_revenue'], 2); ?></td>
                                                <td>
                                                    <?php 
                                                    // Display verification status
                                                    if (!empty($product['payment_channel_id'])): ?>
                                                        <span class="badge <?php echo !empty($product['verification_payment_status']) && $product['verification_payment_status'] === 'paid' ? 'bg-success' : ($product['verification_payment_status'] === 'pending' ? 'bg-warning' : 'bg-danger'); ?>">
                                                            <?php echo !empty($product['verification_payment_status']) ? ucfirst($product['verification_payment_status']) : 'Pending'; ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">No Channel</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#editRentalProductModal" 
                                                            onclick="editRentalProduct(<?php echo htmlspecialchars(json_encode($product)); ?>)">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <?php if (empty($product['verification_payment_status']) || $product['verification_payment_status'] !== 'paid'): ?>
                                                        <button class="btn btn-sm btn-outline-success" 
                                                                onclick="uploadPaymentSlip(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars($product['name'], ENT_QUOTES); ?>', <?php echo $product['rental_price_per_day']; ?>, <?php echo ($product['rental_price_per_day'] * $payment_verification_rate / 100); ?>)">
                                                            <i class="fas fa-money-check"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-calendar-alt fa-3x text-muted mb-3"></i>
                                <h4 class="text-muted">No Rental Products Yet</h4>
                                <p class="text-muted">Start by adding your first rental product to begin accepting rental bookings.</p>
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRentalProductModal">
                                    <i class="fas fa-plus me-2"></i>Add Your First Rental Product
                                </button>
                            </div>
                        <?php endif; ?>
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
                        <input type="file" class="form-control" name="product_image" accept="image/*">
                        <div class="form-text">Primary image for your product. Supported formats: JPG, PNG, GIF. Max size: 5MB.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Gallery Images</label>
                        <input type="file" class="form-control" name="gallery_images[]" multiple accept="image/*">
                        <div class="form-text">You can upload multiple images for your product gallery. Supported formats: JPG, PNG, GIF. Max size: 5MB per image.</div>
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
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Payment Channel *</label>
                            <select class="form-control" name="payment_channel_id" required>
                                <option value="">Select Payment Channel</option>
                                <?php foreach ($payment_channels as $channel): ?>
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

    <!-- Edit Rental Product Modal -->
<div class="modal fade" id="editRentalProductModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Rental Product</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_rental_product">
                    <input type="hidden" name="product_id" id="editRentalProductId">
                    
                    <!-- Current Image Display -->
                    <div class="mb-3" id="currentImageSection" style="display: none;">
                        <label class="form-label">Current Main Image</label>
                        <div class="current-image-container">
                            <img id="currentProductImage" src="" alt="Current product image" class="img-thumbnail" style="max-height: 200px;">
                            <div class="mt-2">
                                <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeCurrentImage()">
                                    <i class="fas fa-trash me-1"></i>Remove Current Image
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Product Name *</label>
                            <input type="text" class="form-control" name="name" id="editRentalProductName" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Category *</label>
                            <select class="form-control" name="category_id" id="editRentalProductCategory" required>
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" id="editRentalProductDescription" rows="3"></textarea>
                    </div>

                    <!-- Image Upload for Edit -->
                    <div class="mb-3">
                        <label class="form-label">Update Main Product Image</label>
                        <input type="file" class="form-control" name="product_image" accept="image/*">
                        <div class="form-text">Leave empty to keep current image. Supported formats: JPG, PNG, GIF. Max size: 5MB.</div>
                        <input type="hidden" name="remove_current_image" id="removeCurrentImageFlag" value="0">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Add More Gallery Images</label>
                        <input type="file" class="form-control" name="gallery_images[]" multiple accept="image/*">
                        <div class="form-text">Upload additional images for your product gallery</div>
                        <div id="currentGallery" class="mt-2"></div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Stock Quantity *</label>
                            <input type="number" class="form-control" name="stock" id="editRentalProductStock" min="1" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Min Rental Days *</label>
                            <input type="number" class="form-control" name="min_rental_days" id="editRentalProductMinDays" min="1" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Max Rental Days *</label>
                            <input type="number" class="form-control" name="max_rental_days" id="editRentalProductMaxDays" min="1" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Payment Channel *</label>
                            <select class="form-control" name="payment_channel_id" id="editRentalProductPaymentChannel" required>
                                <option value="">Select Payment Channel</option>
                                <?php foreach ($payment_channels as $channel): ?>
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
                    
                    <h6 class="mb-3">Rental Pricing</h6>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Price Per Day ($) *</label>
                            <input type="number" class="form-control" name="rental_price_per_day" id="editRentalProductPricePerDay" step="0.01" min="0" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Price Per Week ($)</label>
                            <input type="number" class="form-control" name="rental_price_per_week" id="editRentalProductPricePerWeek" step="0.01" min="0">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Price Per Month ($)</label>
                            <input type="number" class="form-control" name="rental_price_per_month" id="editRentalProductPricePerMonth" step="0.01" min="0">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Security Deposit ($)</label>
                        <input type="number" class="form-control" name="security_deposit" id="editRentalProductSecurityDeposit" step="0.01" min="0">
                        <div class="form-text">Refundable security deposit for the rental</div>
                    </div>
                    
                    <hr>
                    <h6>Product Location</h6>
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Address</label>
                            <input type="text" class="form-control" name="address" id="editRentalProductAddress" placeholder="Street address">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">City</label>
                            <input type="text" class="form-control" name="city" id="editRentalProductCity" placeholder="City">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">State/Province</label>
                            <input type="text" class="form-control" name="state" id="editRentalProductState" placeholder="State or Province">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Postal Code</label>
                            <input type="text" class="form-control" name="postal_code" id="editRentalProductPostalCode" placeholder="Postal Code">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Country</label>
                            <input type="text" class="form-control" name="country" id="editRentalProductCountry" placeholder="Country">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Rental Product</button>
                </div>
            </form>
        </div>
    </div>
</div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Edit Rental Product Function
        function editRentalProduct(product) {
            document.getElementById('editRentalProductId').value = product.id;
            document.getElementById('editRentalProductName').value = product.name;
            document.getElementById('editRentalProductDescription').value = product.description || '';
            document.getElementById('editRentalProductCategory').value = product.category_id;
            document.getElementById('editRentalProductStock').value = product.stock;
            document.getElementById('editRentalProductMinDays').value = product.min_rental_days;
            document.getElementById('editRentalProductMaxDays').value = product.max_rental_days;
            document.getElementById('editRentalProductPricePerDay').value = product.rental_price_per_day;
            document.getElementById('editRentalProductPricePerWeek').value = product.rental_price_per_week || '';
            document.getElementById('editRentalProductPricePerMonth').value = product.rental_price_per_month || '';
            document.getElementById('editRentalProductSecurityDeposit').value = product.security_deposit || '';
            document.getElementById('editRentalProductPaymentChannel').value = product.payment_channel_id || '';
            
            // Address fields
            document.getElementById('editRentalProductAddress').value = product.address || '';
            document.getElementById('editRentalProductCity').value = product.city || '';
            document.getElementById('editRentalProductState').value = product.state || '';
            document.getElementById('editRentalProductPostalCode').value = product.postal_code || '';
            document.getElementById('editRentalProductCountry').value = product.country || '';
            
            // Show current image
            const currentImageSection = document.getElementById('currentImageSection');
            const currentProductImage = document.getElementById('currentProductImage');
            if (product.image_url) {
                currentProductImage.src = '../' + product.image_url;
                currentImageSection.style.display = 'block';
            } else {
                currentImageSection.style.display = 'none';
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
                                    <img src="../${img}" class="img-thumbnail" style="width: 60px; height: 60px;" alt="Gallery Image ${index + 1}">
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
        }

        // Remove current image function
        function removeCurrentImage() {
            if (confirm('Are you sure you want to remove the current image?')) {
                document.getElementById('removeCurrentImageFlag').value = '1';
                document.getElementById('currentImageSection').style.display = 'none';
            }
        }

        // Function to remove gallery images
        function removeGalleryImage(imagePath, productId) {
            if (confirm('Are you sure you want to remove this gallery image?')) {
                // Add hidden input to form for removal
                const form = document.querySelector('#editRentalProductModal form');
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

        // Upload payment slip function
        function uploadPaymentSlip(productId, productName, productPrice, verificationAmount) {
            // Create modal for payment slip upload
            const modalHtml = `
                <div class="modal fade" id="paymentSlipModal" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Upload Payment Slip</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <form method="POST" enctype="multipart/form-data">
                                <div class="modal-body">
                                    <input type="hidden" name="action" value="upload_payment_slip">
                                    <input type="hidden" name="product_id" value="${productId}">
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Product</label>
                                        <div class="form-control-plaintext fw-bold">${productName}</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Product Price</label>
                                        <div class="form-control-plaintext">$${productPrice.toFixed(2)}</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Verification Amount (<?php echo ($payment_verification_rate * 100); ?>%)</label>
                                        <input type="number" class="form-control" name="amount" step="0.01" min="0" value="${verificationAmount.toFixed(2)}" required>
                                        <div class="form-text">Enter the amount you paid for verification</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Payment Slip *</label>
                                        <input type="file" class="form-control" name="payment_slip" accept="image/*,application/pdf" required>
                                        <div class="form-text">Upload your payment slip or receipt. Supported formats: JPG, PNG, GIF, PDF. Max size: 5MB.</div>
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
            `;
            
            // Remove existing modal if any
            const existingModal = document.getElementById('paymentSlipModal');
            if (existingModal) {
                existingModal.remove();
            }
            
            // Add new modal to body
            document.body.insertAdjacentHTML('beforeend', modalHtml);
            
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('paymentSlipModal'));
            modal.show();
            
            // Remove modal from DOM when hidden
            document.getElementById('paymentSlipModal').addEventListener('hidden.bs.modal', function () {
                this.remove();
            });
        }

        // Auto-submit category change
        document.getElementById('categoryFilter')?.addEventListener('change', function() {
            this.form.submit();
        });
    </script>
</body>
</html>