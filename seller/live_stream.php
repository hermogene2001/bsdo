<?php
session_start();
require_once '../config.php';

// Check if user is logged in and is seller
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'seller') {
    header('Location: ../login.php');
    exit();
}

$seller_id = $_SESSION['user_id'];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'start_stream':
                $title = trim($_POST['title']);
                $description = trim($_POST['description']);
                $category_id = intval($_POST['category_id']);
                $scheduled_at = !empty($_POST['scheduled_at']) ? $_POST['scheduled_at'] : null;
                
                // Generate unique stream key
                $stream_key = 'stream_' . $seller_id . '_' . time() . '_' . bin2hex(random_bytes(8));
                
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO live_streams (seller_id, title, description, category_id, stream_key, status, scheduled_at) 
                        VALUES (?, ?, ?, ?, ?, 'scheduled', ?)
                    ");
                    $stmt->execute([$seller_id, $title, $description, $category_id, $stream_key, $scheduled_at]);
                    $stream_id = $pdo->lastInsertId();
                    
                    // If no scheduled time, start immediately
                    if (!$scheduled_at) {
                        $update_stmt = $pdo->prepare("
                            UPDATE live_streams 
                            SET is_live = 1, status = 'live', started_at = NOW() 
                            WHERE id = ?
                        ");
                        $update_stmt->execute([$stream_id]);
                    }
                    
                    $success_message = $scheduled_at ? "Stream scheduled successfully!" : "Stream started successfully!";
                    header("Location: live_stream.php?stream_id=" . $stream_id);
                    exit();
                } catch (Exception $e) {
                    $error_message = "Failed to start stream: " . $e->getMessage();
                }
                break;
                
            case 'end_stream':
                $stream_id = intval($_POST['stream_id']);
                
                // Verify stream belongs to seller
                $stmt = $pdo->prepare("SELECT id FROM live_streams WHERE id = ? AND seller_id = ?");
                $stmt->execute([$stream_id, $seller_id]);
                if ($stmt->rowCount() > 0) {
                    $update_stmt = $pdo->prepare("
                        UPDATE live_streams 
                        SET is_live = 0, status = 'ended', ended_at = NOW() 
                        WHERE id = ?
                    ");
                    $update_stmt->execute([$stream_id]);
                    $success_message = "Stream ended successfully!";
                    // Redirect to live stream list after ending
                    header("Location: live_stream.php");
                    exit();
                } else {
                    $error_message = "Stream not found or access denied.";
                }
                break;
                
            case 'feature_product':
                $stream_id = intval($_POST['stream_id']);
                $product_id = intval($_POST['product_id']);
                $special_price = !empty($_POST['special_price']) ? floatval($_POST['special_price']) : null;
                $discount_percentage = !empty($_POST['discount_percentage']) ? floatval($_POST['discount_percentage']) : null;
                
                // Verify stream belongs to seller and product belongs to seller
                $stmt = $pdo->prepare("
                    SELECT ls.id 
                    FROM live_streams ls 
                    JOIN products p ON p.seller_id = ls.seller_id 
                    WHERE ls.id = ? AND ls.seller_id = ? AND p.id = ?
                ");
                $stmt->execute([$stream_id, $seller_id, $product_id]);
                if ($stmt->rowCount() > 0) {
                    // Check if product is already featured
                    $check_stmt = $pdo->prepare("SELECT id FROM live_stream_products WHERE stream_id = ? AND product_id = ?");
                    $check_stmt->execute([$stream_id, $product_id]);
                    
                    if ($check_stmt->rowCount() > 0) {
                        // Update existing featured product
                        $update_stmt = $pdo->prepare("
                            UPDATE live_stream_products 
                            SET special_price = ?, discount_percentage = ?, featured_at = NOW()
                            WHERE stream_id = ? AND product_id = ?
                        ");
                        $update_stmt->execute([$special_price, $discount_percentage, $stream_id, $product_id]);
                    } else {
                        // Add new featured product
                        $insert_stmt = $pdo->prepare("
                            INSERT INTO live_stream_products (stream_id, product_id, special_price, discount_percentage) 
                            VALUES (?, ?, ?, ?)
                        ");
                        $insert_stmt->execute([$stream_id, $product_id, $special_price, $discount_percentage]);
                    }
                    
                    $success_message = "Product featured successfully!";
                } else {
                    $error_message = "Invalid stream or product.";
                }
                break;
                
            case 'remove_featured_product':
                $stream_id = intval($_POST['stream_id']);
                $product_id = intval($_POST['product_id']);
                
                // Verify stream belongs to seller
                $stmt = $pdo->prepare("SELECT id FROM live_streams WHERE id = ? AND seller_id = ?");
                $stmt->execute([$stream_id, $seller_id]);
                if ($stmt->rowCount() > 0) {
                    $delete_stmt = $pdo->prepare("DELETE FROM live_stream_products WHERE stream_id = ? AND product_id = ?");
                    $delete_stmt->execute([$stream_id, $product_id]);
                    $success_message = "Product removed from stream!";
                } else {
                    $error_message = "Stream not found or access denied.";
                }
                break;
                
            case 'highlight_product':
                $stream_id = intval($_POST['stream_id']);
                $product_id = intval($_POST['product_id']);
                
                // Verify stream belongs to seller
                $stmt = $pdo->prepare("SELECT id FROM live_streams WHERE id = ? AND seller_id = ?");
                $stmt->execute([$stream_id, $seller_id]);
                if ($stmt->rowCount() > 0) {
                    $update_stmt = $pdo->prepare("UPDATE live_stream_products SET is_highlighted = 1 WHERE stream_id = ? AND product_id = ?");
                    $update_stmt->execute([$stream_id, $product_id]);
                    $success_message = "Product highlighted!";
                } else {
                    $error_message = "Stream not found or access denied.";
                }
                break;
                
            case 'unhighlight_product':
                $stream_id = intval($_POST['stream_id']);
                $product_id = intval($_POST['product_id']);
                
                // Verify stream belongs to seller
                $stmt = $pdo->prepare("SELECT id FROM live_streams WHERE id = ? AND seller_id = ?");
                $stmt->execute([$stream_id, $seller_id]);
                if ($stmt->rowCount() > 0) {
                    $update_stmt = $pdo->prepare("UPDATE live_stream_products SET is_highlighted = 0 WHERE stream_id = ? AND product_id = ?");
                    $update_stmt->execute([$stream_id, $product_id]);
                    $success_message = "Product unhighlighted!";
                } else {
                    $error_message = "Stream not found or access denied.";
                }
                break;
        }
    }
}

// Get current stream if any
$current_stream = null;
$stream_id = intval($_GET['stream_id'] ?? 0);

if ($stream_id) {
    $current_stream_stmt = $pdo->prepare("
        SELECT ls.*, c.name as category_name, COUNT(lsv.id) as current_viewers
        FROM live_streams ls
        LEFT JOIN categories c ON ls.category_id = c.id
        LEFT JOIN live_stream_viewers lsv ON ls.id = lsv.stream_id AND lsv.is_active = 1
        WHERE ls.id = ? AND ls.seller_id = ?
        GROUP BY ls.id
    ");
    $current_stream_stmt->execute([$stream_id, $seller_id]);
    $current_stream = $current_stream_stmt->fetch(PDO::FETCH_ASSOC);
}

// Get seller's products (only products created by this seller)
$products_stmt = $pdo->prepare("
    SELECT id, name, price, image_url, description 
    FROM products 
    WHERE seller_id = ? AND status = 'active' 
    ORDER BY created_at DESC
");
$products_stmt->execute([$seller_id]);
$seller_products = $products_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get featured products for current stream (only products from this seller)
$featured_products = [];
if ($current_stream) {
    $featured_stmt = $pdo->prepare("
        SELECT lsp.*, p.name, p.price, p.image_url, p.description
        FROM live_stream_products lsp
        JOIN products p ON lsp.product_id = p.id
        WHERE lsp.stream_id = ? AND p.seller_id = ?
        ORDER BY lsp.featured_at DESC
    ");
    $featured_stmt->execute([$stream_id, $seller_id]);
    $featured_products = $featured_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get seller's recent streams
$recent_streams_stmt = $pdo->prepare("
    SELECT ls.*, c.name as category_name, COUNT(lsv.id) as total_viewers
    FROM live_streams ls
    LEFT JOIN categories c ON ls.category_id = c.id
    LEFT JOIN live_stream_viewers lsv ON ls.id = lsv.stream_id
    WHERE ls.seller_id = ?
    GROUP BY ls.id
    ORDER BY ls.created_at DESC
    LIMIT 10
");
$recent_streams_stmt->execute([$seller_id]);
$recent_streams = $recent_streams_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get categories for stream form
$categories_stmt = $pdo->prepare("SELECT id, name FROM categories WHERE status = 'active' ORDER BY name");
$categories_stmt->execute();
$categories = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get seller information
$seller_info_stmt = $pdo->prepare("SELECT first_name, last_name, email, store_name FROM users WHERE id = ?");
$seller_info_stmt->execute([$seller_id]);
$seller_info = $seller_info_stmt->fetch(PDO::FETCH_ASSOC);

function getStatusBadge($status) {
    switch ($status) {
        case 'live': return '<span class="badge bg-danger">LIVE</span>';
        case 'scheduled': return '<span class="badge bg-warning">Scheduled</span>';
        case 'ended': return '<span class="badge bg-secondary">Ended</span>';
        case 'cancelled': return '<span class="badge bg-dark">Cancelled</span>';
        default: return '<span class="badge bg-secondary">' . ucfirst($status) . '</span>';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Streaming - BSDO Seller</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #1cc88a;
            --live-color: #e74a3b;
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
        
        .live-stream-card {
            border: none;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }
        
        .live-stream-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
        }
        
        .stream-preview {
            height: 300px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 4rem;
            position: relative;
        }
        
        .live-indicator {
            position: absolute;
            top: 15px;
            left: 15px;
            background: var(--live-color);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: bold;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.7; }
            100% { opacity: 1; }
        }
        
        .viewer-count {
            position: absolute;
            bottom: 15px;
            right: 15px;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: bold;
        }
        
        .stream-controls {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            margin-bottom: 30px;
        }
        
        .btn-live {
            background: var(--live-color);
            border: none;
            color: white;
            padding: 12px 30px;
            border-radius: 25px;
            font-weight: bold;
            transition: all 0.3s;
        }
        
        .btn-live:hover {
            background: #c0392b;
            transform: translateY(-2px);
        }
        
        .btn-end {
            background: #6c757d;
            border: none;
            color: white;
            padding: 12px 30px;
            border-radius: 25px;
            font-weight: bold;
        }
        
        .btn-return {
            background: #4e73df;
            border: none;
            color: white;
            padding: 12px 30px;
            border-radius: 25px;
            font-weight: bold;
        }
        
        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            border: none;
        }
        
        .stats-number {
            font-size: 2rem;
            font-weight: bold;
            color: var(--primary-color);
        }
        
        .stream-history-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            margin-bottom: 20px;
        }
        
        .stream-item {
            border-bottom: 1px solid #eee;
            padding: 15px 0;
        }
        
        .stream-item:last-child {
            border-bottom: none;
        }
        
        .stream-thumbnail {
            width: 80px;
            height: 60px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
        }
        
        .product-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            transition: all 0.3s;
        }
        
        .product-card:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }
        
        .product-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
        }
        
        .highlighted {
            border: 2px solid #ffd700;
            box-shadow: 0 0 20px rgba(255, 215, 0, 0.3);
        }
        
        .featured-products-container {
            max-height: 400px;
            overflow-y: auto;
        }
        
        .product-list-container {
            max-height: 400px;
            overflow-y: auto;
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

            <a class="navbar-brand" href="#">
                <i class="fas fa-video me-2"></i>
                <strong>BSDO Live Streaming</strong>
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
                    <a class="nav-link active" href="live_stream.php">
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
                            <a class="nav-link" href="products.php">
                                <i class="fas fa-box me-2"></i>Products
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
                            <a class="nav-link active" href="live_stream.php">
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

                <!-- Current Stream Section -->
                <?php if ($current_stream): ?>
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="live-stream-card">
                                <div class="stream-preview">
                                    <div class="live-indicator">
                                        <i class="fas fa-circle me-1"></i>LIVE
                                    </div>
                                    <i class="fas fa-video"></i>
                                    <div class="viewer-count">
                                        <i class="fas fa-eye me-1"></i><?php echo $current_stream['current_viewers']; ?> viewers
                                    </div>
                                </div>
                                <div class="card-body">
                                    <h4 class="card-title"><?php echo htmlspecialchars($current_stream['title']); ?></h4>
                                    <p class="card-text"><?php echo htmlspecialchars($current_stream['description']); ?></p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <span class="badge bg-primary me-2"><?php echo htmlspecialchars($current_stream['category_name'] ?? 'General'); ?></span>
                                            <small class="text-muted">Started: <?php echo date('M j, Y g:i A', strtotime($current_stream['started_at'])); ?></small>
                                        </div>
                                        <div>
                                            <a href="live_stream.php" class="btn btn-return me-2">
                                                <i class="fas fa-list me-2"></i>Live Stream List
                                            </a>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="action" value="end_stream">
                                                <input type="hidden" name="stream_id" value="<?php echo $current_stream['id']; ?>">
                                                <button type="submit" class="btn btn-end" onclick="return confirm('Are you sure you want to end this stream? This will close the live interaction with clients.')">
                                                    <i class="fas fa-stop me-2"></i>End Stream
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Product Showcase Section -->
                    <div class="row mb-4">
                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0">Featured Products</h5>
                                </div>
                                <div class="card-body">
                                    <div class="featured-products-container">
                                        <?php if (!empty($featured_products)): ?>
                                            <?php foreach ($featured_products as $product): ?>
                                                <div class="product-card <?php echo $product['is_highlighted'] ? 'highlighted' : ''; ?>">
                                                    <div class="d-flex">
                                                        <?php if (!empty($product['image_url'])): ?>
                                                            <img src="../<?php echo htmlspecialchars($product['image_url']); ?>" 
                                                                 alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                                                 class="product-image me-3">
                                                        <?php else: ?>
                                                            <div class="product-image me-3 bg-secondary d-flex align-items-center justify-content-center">
                                                                <i class="fas fa-image text-white"></i>
                                                            </div>
                                                        <?php endif; ?>
                                                        <div class="flex-grow-1">
                                                            <h6 class="mb-1"><?php echo htmlspecialchars($product['name']); ?></h6>
                                                            <p class="text-muted small mb-2"><?php echo htmlspecialchars(substr($product['description'], 0, 60)); ?>...</p>
                                                            <div class="d-flex justify-content-between align-items-center">
                                                                <div>
                                                                    <?php if ($product['special_price']): ?>
                                                                        <span class="text-success fw-bold">$<?php echo number_format($product['special_price'], 2); ?></span>
                                                                        <small class="text-muted text-decoration-line-through ms-1">$<?php echo number_format($product['price'], 2); ?></small>
                                                                    <?php else: ?>
                                                                        <span class="text-success fw-bold">$<?php echo number_format($product['price'], 2); ?></span>
                                                                    <?php endif; ?>
                                                                </div>
                                                                <div>
                                                                    <?php if ($product['is_highlighted']): ?>
                                                                        <form method="POST" class="d-inline">
                                                                            <input type="hidden" name="action" value="unhighlight_product">
                                                                            <input type="hidden" name="stream_id" value="<?php echo $current_stream['id']; ?>">
                                                                            <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                                                                            <button type="submit" class="btn btn-sm btn-warning" title="Unhighlight Product">
                                                                                <i class="fas fa-star"></i>
                                                                            </button>
                                                                        </form>
                                                                    <?php else: ?>
                                                                        <form method="POST" class="d-inline">
                                                                            <input type="hidden" name="action" value="highlight_product">
                                                                            <input type="hidden" name="stream_id" value="<?php echo $current_stream['id']; ?>">
                                                                            <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                                                                            <button type="submit" class="btn btn-sm btn-outline-warning" title="Highlight Product">
                                                                                <i class="far fa-star"></i>
                                                                            </button>
                                                                        </form>
                                                                    <?php endif; ?>
                                                                    <form method="POST" class="d-inline">
                                                                        <input type="hidden" name="action" value="remove_featured_product">
                                                                        <input type="hidden" name="stream_id" value="<?php echo $current_stream['id']; ?>">
                                                                        <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                                                                        <button type="submit" class="btn btn-sm btn-outline-danger ms-1" title="Remove from Stream">
                                                                            <i class="fas fa-times"></i>
                                                                        </button>
                                                                    </form>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <div class="text-center py-4">
                                                <i class="fas fa-box-open fa-2x text-muted mb-3"></i>
                                                <p class="text-muted">No products featured yet. Add products from your catalog below.</p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0">Add Products to Stream</h5>
                                </div>
                                <div class="card-body">
                                    <div class="product-list-container">
                                        <?php if (!empty($seller_products)): ?>
                                            <?php foreach ($seller_products as $product): ?>
                                                <?php 
                                                // Check if product is already featured
                                                $is_featured = false;
                                                $featured_data = null;
                                                foreach ($featured_products as $fp) {
                                                    if ($fp['product_id'] == $product['id']) {
                                                        $is_featured = true;
                                                        $featured_data = $fp;
                                                        break;
                                                    }
                                                }
                                                ?>
                                                <div class="product-card">
                                                    <div class="d-flex">
                                                        <?php if (!empty($product['image_url'])): ?>
                                                            <img src="../<?php echo htmlspecialchars($product['image_url']); ?>" 
                                                                 alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                                                 class="product-image me-3">
                                                        <?php else: ?>
                                                            <div class="product-image me-3 bg-secondary d-flex align-items-center justify-content-center">
                                                                <i class="fas fa-image text-white"></i>
                                                            </div>
                                                        <?php endif; ?>
                                                        <div class="flex-grow-1">
                                                            <h6 class="mb-1"><?php echo htmlspecialchars($product['name']); ?></h6>
                                                            <p class="text-success fw-bold mb-2">$<?php echo number_format($product['price'], 2); ?></p>
                                                            <?php if (!$is_featured): ?>
                                                                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#featureProductModal<?php echo $product['id']; ?>">
                                                                    <i class="fas fa-plus-circle me-1"></i>Feature Product
                                                                </button>
                                                            <?php else: ?>
                                                                <span class="badge bg-success">Featured</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <!-- Feature Product Modal -->
                                                <?php if (!$is_featured): ?>
                                                    <div class="modal fade" id="featureProductModal<?php echo $product['id']; ?>" tabindex="-1">
                                                        <div class="modal-dialog">
                                                            <div class="modal-content">
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title">Feature Product</h5>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                                </div>
                                                                <form method="POST">
                                                                    <div class="modal-body">
                                                                        <input type="hidden" name="action" value="feature_product">
                                                                        <input type="hidden" name="stream_id" value="<?php echo $current_stream['id']; ?>">
                                                                        <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                                                        
                                                                        <div class="text-center mb-3">
                                                                            <?php if (!empty($product['image_url'])): ?>
                                                                                <img src="../<?php echo htmlspecialchars($product['image_url']); ?>" 
                                                                                     alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                                                                     class="img-fluid rounded" style="max-height: 150px;">
                                                                            <?php endif; ?>
                                                                            <h5 class="mt-2"><?php echo htmlspecialchars($product['name']); ?></h5>
                                                                            <p class="text-success fw-bold">$<?php echo number_format($product['price'], 2); ?></p>
                                                                        </div>
                                                                        
                                                                        <div class="mb-3">
                                                                            <label class="form-label">Special Price (Optional)</label>
                                                                            <input type="number" class="form-control" name="special_price" step="0.01" min="0" placeholder="Leave empty for regular price">
                                                                            <div class="form-text">Set a special price for this product during the live stream</div>
                                                                        </div>
                                                                        
                                                                        <div class="mb-3">
                                                                            <label class="form-label">Discount Percentage (Optional)</label>
                                                                            <input type="number" class="form-control" name="discount_percentage" step="0.01" min="0" max="100" placeholder="e.g., 15 for 15% off">
                                                                            <div class="form-text">Set a discount percentage for this product</div>
                                                                        </div>
                                                                    </div>
                                                                    <div class="modal-footer">
                                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                        <button type="submit" class="btn btn-primary">Feature Product</button>
                                                                    </div>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <div class="text-center py-4">
                                                <i class="fas fa-exclamation-circle fa-2x text-muted mb-3"></i>
                                                <p class="text-muted">You don't have any active products. <a href="products.php">Add products</a> to your catalog first.</p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Start New Stream Section -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="stream-controls">
                                <div class="d-flex justify-content-between align-items-center mb-4">
                                    <h4 class="mb-0">Start a New Live Stream</h4>
                                    <a href="live_stream.php" class="btn btn-return">
                                        <i class="fas fa-list me-2"></i>Live Stream List
                                    </a>
                                </div>
                                <form method="POST">
                                    <input type="hidden" name="action" value="start_stream">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Stream Title *</label>
                                            <input type="text" class="form-control" name="title" required placeholder="Enter stream title">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Category</label>
                                            <select class="form-control" name="category_id">
                                                <option value="">Select Category</option>
                                                <?php foreach ($categories as $category): ?>
                                                    <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Description</label>
                                        <textarea class="form-control" name="description" rows="3" placeholder="Describe what you'll be showcasing in this stream"></textarea>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Schedule Stream (Optional)</label>
                                            <input type="datetime-local" class="form-control" name="scheduled_at">
                                            <div class="form-text">Leave empty to start immediately</div>
                                        </div>
                                        <div class="col-md-6 d-flex align-items-end">
                                            <button type="submit" class="btn btn-live">
                                                <i class="fas fa-video me-2"></i>Start Live Stream
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Stream Statistics -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stats-card">
                            <div class="stats-number"><?php echo count($recent_streams); ?></div>
                            <div class="text-muted">Total Streams</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <div class="stats-number"><?php echo $current_stream ? $current_stream['current_viewers'] : 0; ?></div>
                            <div class="text-muted">Current Viewers</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <div class="stats-number"><?php echo array_sum(array_column($recent_streams, 'total_viewers')); ?></div>
                            <div class="text-muted">Total Viewers</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <div class="stats-number"><?php echo count(array_filter($recent_streams, function($s) { return $s['status'] === 'ended'; })); ?></div>
                            <div class="text-muted">Completed</div>
                        </div>
                    </div>
                </div>

                <!-- Stream History -->
                <div class="row">
                    <div class="col-12">
                        <div class="stream-history-card">
                            <h5 class="mb-4">Recent Streams</h5>
                            <?php if (!empty($recent_streams)): ?>
                                <?php foreach ($recent_streams as $stream): ?>
                                    <div class="stream-item d-flex align-items-center">
                                        <div class="stream-thumbnail me-3">
                                            <i class="fas fa-video"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($stream['title']); ?></h6>
                                            <p class="text-muted mb-1 small"><?php echo htmlspecialchars($stream['description']); ?></p>
                                            <div class="d-flex align-items-center">
                                                <?php echo getStatusBadge($stream['status']); ?>
                                                <span class="ms-2 text-muted small">
                                                    <i class="fas fa-eye me-1"></i><?php echo $stream['total_viewers']; ?> viewers
                                                </span>
                                                <span class="ms-2 text-muted small">
                                                    <i class="fas fa-calendar me-1"></i>
                                                    <?php echo date('M j, Y g:i A', strtotime($stream['created_at'])); ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="text-end">
                                            <?php if ($stream['is_live']): ?>
                                                <a href="live_stream_webrtc.php?stream_id=<?php echo $stream['id']; ?>" class="btn btn-sm btn-danger">
                                                    <i class="fas fa-play me-1"></i>View Live
                                                </a>
                                            <?php else: ?>
                                                <button class="btn btn-sm btn-outline-secondary" disabled>
                                                    <i class="fas fa-check me-1"></i>Ended
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-video fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">No streams yet</h5>
                                    <p class="text-muted">Start your first live stream to showcase your products!</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-refresh for live streams
        function refreshLiveData() {
            if (<?php echo $current_stream ? 'true' : 'false'; ?>) {
                setTimeout(() => {
                    window.location.reload();
                }, 30000); // Refresh every 30 seconds
            }
        }

        // Start auto-refresh if currently live
        refreshLiveData();

        // Real-time viewer count update (simplified)
        function updateViewerCount() {
            // In a real implementation, this would use WebSockets or AJAX polling
            // to get real-time viewer count updates
        }
    </script>
</body>
</html>