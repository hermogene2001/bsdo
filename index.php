<?php
session_start();
require_once 'config.php';

// Handle session messages
$login_error = $_SESSION['login_error'] ?? '';
$login_form_data = $_SESSION['login_form_data'] ?? [];
$registration_success = $_SESSION['registration_success'] ?? '';
$register_error = $_SESSION['register_error'] ?? '';

// Check for registration success from URL parameters
$registration_success = '';
if (isset($_GET['registration']) && $_GET['registration'] === 'success') {
    $role = $_GET['role'] ?? 'client';
    if ($role === 'seller') {
        $seller_code = $_GET['seller_code'] ?? '';
        if (!empty($seller_code)) {
            $registration_success = "Account created successfully! Your seller code is: <strong>" . htmlspecialchars($seller_code) . "</strong>. Please save this code for future login.";
            // Also set in session for display in modal
            $_SESSION['seller_code'] = $seller_code;
        } else {
            $registration_success = "Seller account created successfully! Please check your email for your seller code.";
        }
    } else {
        $registration_success = "Account created successfully! Please login.";
    }
}

// Clear session messages after retrieving
unset($_SESSION['login_error'], $_SESSION['login_form_data'], $_SESSION['registration_success'], $_SESSION['register_error'], $_SESSION['seller_code']);

// User session data
$is_logged_in = isset($_SESSION['user_id']);
$user_name = $_SESSION['user_name'] ?? '';
$user_role = $_SESSION['user_role'] ?? '';
$user_id = $_SESSION['user_id'] ?? null;

// Get filter parameters
$product_type = isset($_GET['type']) ? $_GET['type'] : '';
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build products query with filters
$query = "
    SELECT p.*, u.store_name, c.name as category_name 
    FROM products p 
    JOIN users u ON p.seller_id = u.id 
    LEFT JOIN categories c ON p.category_id = c.id 
    WHERE p.status = 'active'
";

$params = [];
$where_conditions = [];

// Add product type filter
if (!empty($product_type)) {
    $where_conditions[] = "p.product_type = ?";
    $params[] = $product_type;
}

// Add search filter
if (!empty($search_query)) {
    $where_conditions[] = "(p.name LIKE ? OR p.description LIKE ? OR u.store_name LIKE ? OR c.name LIKE ?)";
    $search_term = "%$search_query%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

// Add where conditions
if (!empty($where_conditions)) {
    $query .= " AND " . implode(" AND ", $where_conditions);
}

$query .= " ORDER BY p.created_at DESC LIMIT 12";

// Get featured products
$featured_products_stmt = $pdo->prepare($query);
$featured_products_stmt->execute($params);
$featured_products = $featured_products_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total count of products matching filters (for display purposes)
$count_query = "
    SELECT COUNT(*) as total 
    FROM products p 
    JOIN users u ON p.seller_id = u.id 
    LEFT JOIN categories c ON p.category_id = c.id 
    WHERE p.status = 'active'
";

$count_params = [];
$count_where_conditions = [];

// Add product type filter for count
if (!empty($product_type)) {
    $count_where_conditions[] = "p.product_type = ?";
    $count_params[] = $product_type;
}

// Add search filter for count
if (!empty($search_query)) {
    $count_where_conditions[] = "(p.name LIKE ? OR p.description LIKE ? OR u.store_name LIKE ? OR c.name LIKE ?)";
    $count_params[] = $search_term;
    $count_params[] = $search_term;
    $count_params[] = $search_term;
    $count_params[] = $search_term;
}

// Add where conditions for count
if (!empty($count_where_conditions)) {
    $count_query .= " AND " . implode(" AND ", $count_where_conditions);
}

$count_stmt = $pdo->prepare($count_query);
$count_stmt->execute($count_params);
$total_products = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Note: Live streams functionality moved to live_streams.php

// Get user's pending inquiries count
$pending_inquiries_count = 0;
if ($is_logged_in && $user_role === 'client') {
    $inquiries_stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM inquiries 
        WHERE user_id = ? AND status = 'replied'
    ");
    $inquiries_stmt->execute([$user_id]);
    $pending_inquiries_count = $inquiries_stmt->fetch(PDO::FETCH_ASSOC)['count'];
}

// Get active carousel items
$carousel_stmt = $pdo->prepare("SELECT * FROM carousel_items WHERE is_active = 1 ORDER BY sort_order ASC, id ASC");
$carousel_stmt->execute();
$carousel_items = $carousel_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats_stmt = $pdo->prepare("
    SELECT 
        (SELECT COUNT(*) FROM products WHERE status = 'active' AND product_type = 'regular') as total_regular_products,
        (SELECT COUNT(*) FROM products WHERE status = 'active' AND product_type = 'rental') as total_rental_products,
        (SELECT COUNT(*) FROM users WHERE role = 'seller' AND status = 'active') as total_sellers,
        (SELECT COUNT(*) FROM orders WHERE status = 'completed') as total_orders
");
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

function getCategoryIcon($categoryName) {
    $icons = [
        'Electronics' => 'mobile-alt',
        'Clothing' => 'tshirt',
        'Home & Garden' => 'home',
        'Sports' => 'basketball-ball',
        'Books' => 'book',
        'Beauty' => 'spa'
    ];
    
    foreach ($icons as $category => $icon) {
        if (stripos($categoryName, $category) !== false) {
            return $icon;
        }
    }
    return 'shopping-bag';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BSDO Sale - Shop, Rent & Connect Live</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #1cc88a;
            --rental-color: #f6c23e;
            --regular-color: #36b9cc;
            --live-color: #e74a3b;
            --inquiry-color: #6f42c1;
            --dark-color: #2e3a59;
            --light-color: #f8f9fc;
        }
        
        body {
            font-family: 'Nunito', sans-serif;
            background-color: var(--light-color);
            color: var(--dark-color);
        }
        
        .navbar {
            background-color: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }
        
        .navbar-scrolled {
            background-color: rgba(255, 255, 255, 0.95) !important;
            backdrop-filter: blur(10px);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), #667eea);
            border: none;
        }
        
        .btn-rental {
            background: linear-gradient(135deg, var(--rental-color), #e6a700);
            border: none;
            color: white;
        }
        
        .btn-regular {
            background: linear-gradient(135deg, var(--regular-color), #258391);
            border: none;
            color: white;
        }
        
        .btn-live {
            background: linear-gradient(135deg, var(--live-color), #c9302c);
            border: none;
            color: white;
        }
        
        .hero-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 120px 0 80px;
            position: relative;
            overflow: hidden;
        }
        
        .carousel-item {
            transition: transform 0.6s ease-in-out;
        }
        
        .carousel-caption {
            background: rgba(0, 0, 0, 0.5);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 50px;
        }
        
        .carousel-caption h2 {
            font-weight: bold;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.5);
        }
        
        .carousel-caption p {
            font-size: 1.2rem;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.5);
        }
        
        .carousel-item img {
            width: 100%;
            height: 400px;
            object-fit: cover;
        }
        
        .carousel-control-prev,
        .carousel-control-next {
            width: 5%;
        }
        
        .carousel-indicators button {
            width: 10px;
            height: 10px;
            border-radius: 50%;
        }
        
        .inquiry-badge, .cart-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: var(--inquiry-color);
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 0.7rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .cart-badge {
            background: var(--secondary-color);
        }
        
        .product-card {
            border: none;
            border-radius: 15px;
            overflow: hidden;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            height: 100%;
        }
        
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
        }
        
        .product-image {
            height: 200px;
            background: linear-gradient(135deg, #f8f9fc, #e9ecef);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--dark-color);
            font-size: 3rem;
            position: relative;
        }
        
        .product-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 10;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: bold;
        }
        
        .badge-regular {
            background: var(--regular-color);
            color: white;
        }
        
        .badge-rental {
            background: var(--rental-color);
            color: white;
        }
        
        .rental-price {
            font-size: 1rem;
            color: var(--rental-color);
            font-weight: bold;
        }
        
        .stats-section {
            background: white;
            padding: 60px 0;
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            background: var(--primary-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }
        
        .auth-form {
            background-color: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            padding: 30px;
        }
        
        .role-selector {
            display: flex;
            justify-content: center;
            margin-bottom: 20px;
            gap: 10px;
        }
        
        .role-option {
            flex: 1;
            text-align: center;
            padding: 15px 10px;
            cursor: pointer;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            transition: all 0.3s;
        }
        
        .role-option.active {
            border-color: var(--primary-color);
            background-color: rgba(78, 115, 223, 0.1);
            transform: scale(1.05);
        }
        
        footer {
            background-color: var(--dark-color);
            color: white;
            padding: 60px 0 30px;
        }
        
        /* Seller code display styles */
        .seller-code-display {
            background: linear-gradient(135deg, #1cc88a, #18a873);
            color: white;
            border-radius: 10px;
            padding: 15px;
            margin: 15px 0;
            text-align: center;
            box-shadow: 0 4px 15px rgba(28, 200, 138, 0.3);
        }
        
        .seller-code {
            font-family: 'Courier New', monospace;
            font-size: 1.2rem;
            font-weight: bold;
            background: rgba(255, 255, 255, 0.2);
            padding: 8px 15px;
            border-radius: 5px;
            display: inline-block;
            margin: 10px 0;
        }
        
        .copy-btn {
            background: white;
            color: #1cc88a;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .copy-btn:hover {
            background: #f0f0f0;
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light fixed-top">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">
                <i class="fas fa-shopping-bag me-2 text-primary"></i>BSDO SALE
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item"><a class="nav-link" href="#home">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="#products">Products</a></li>
                    <li class="nav-item"><a class="nav-link" href="live_streams.php">Live Streams</a></li>
                    <li class="nav-item"><a class="nav-link" href="products.php?type=regular">Buy</a></li>
                    <li class="nav-item"><a class="nav-link" href="products.php?type=rental">Rent</a></li>
                </ul>
                
                <div class="d-flex align-items-center">
                    <?php if ($is_logged_in): ?>
                        <div class="dropdown">
                            <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown">
                                <div class="user-avatar me-2">
                                    <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                                </div>
                                <span class="me-2"><?php echo htmlspecialchars(explode(' ', $user_name)[0]); ?></span>
                                
                                <?php if ($user_role === 'client' && $pending_inquiries_count > 0): ?>
                                    <span class="position-relative me-3">
                                        <i class="fas fa-comments"></i>
                                        <span class="inquiry-badge"><?php echo $pending_inquiries_count; ?></span>
                                    </span>
                                <?php endif; ?>
                                
                                <?php 
                                $total_cart_items = 0;
                                if (isset($_SESSION['cart'])) {
                                    $total_cart_items = count($_SESSION['cart']);
                                }
                                if ($total_cart_items > 0): ?>
                                    <span class="position-relative">
                                        <i class="fas fa-shopping-cart"></i>
                                        <span class="cart-badge"><?php echo $total_cart_items; ?></span>
                                    </span>
                                <?php else: ?>
                                    <i class="fas fa-shopping-cart"></i>
                                <?php endif; ?>
                            </a>
                            <ul class="dropdown-menu">
                                <?php if ($user_role === 'seller'): ?>
                                    <li><a class="dropdown-item" href="seller/dashboard.php"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a></li>
                                    <li><a class="dropdown-item" href="seller/live_stream.php"><i class="fas fa-video me-2"></i>Go Live</a></li>
                                <?php elseif ($user_role === 'admin'): ?>
                                    <li><a class="dropdown-item" href="admin/dashboard.php"><i class="fas fa-cog me-2"></i>Admin Panel</a></li>
                                <?php else: ?>
                                    <li><a class="dropdown-item" href="myAccount.php"><i class="fas fa-user me-2"></i>My Account</a></li>
                                    <li><a class="dropdown-item" href="inquiries.php"><i class="fas fa-comments me-2"></i>My Inquiries</a></li>
                                    <li><a class="dropdown-item" href="cart.php"><i class="fas fa-shopping-cart me-2"></i>Cart</a></li>
                                <?php endif; ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <a href="#" class="btn btn-outline-primary me-2" data-bs-toggle="modal" data-bs-target="#loginModal">Login</a>
                        <a href="#" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#registerModal">Register</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show" style="margin-top: 80px; position: fixed; top: 0; right: 20px; z-index: 9999; min-width: 300px;">
            <?php echo htmlspecialchars($_SESSION['success_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <?php if (!empty($carousel_items)): ?>
    <section id="carousel" class="py-0">
        <div id="mainCarousel" class="carousel slide" data-bs-ride="carousel">
            <div class="carousel-indicators">
                <?php foreach ($carousel_items as $index => $item): ?>
                    <button type="button" data-bs-target="#mainCarousel" data-bs-slide-to="<?php echo $index; ?>" <?php echo $index === 0 ? 'class="active"' : ''; ?> aria-current="<?php echo $index === 0 ? 'true' : 'false'; ?>"></button>
                <?php endforeach; ?>
            </div>
            <div class="carousel-inner">
                <?php foreach ($carousel_items as $index => $item): ?>
                    <div class="carousel-item <?php echo $index === 0 ? 'active' : ''; ?>">
                        <img src="<?php echo htmlspecialchars($item['image_path']); ?>" class="d-block w-100" alt="<?php echo htmlspecialchars($item['title']); ?>" style="height: 400px; object-fit: cover;">
                        <div class="carousel-caption d-none d-md-block">
                            <h2><?php echo htmlspecialchars($item['title']); ?></h2>
                            <p><?php echo htmlspecialchars($item['description']); ?></p>
                            <?php if (!empty($item['link_url'])): ?>
                                <a href="<?php echo htmlspecialchars($item['link_url']); ?>" class="btn btn-light btn-lg">Learn More</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <button class="carousel-control-prev" type="button" data-bs-target="#mainCarousel" data-bs-slide="prev">
                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Previous</span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#mainCarousel" data-bs-slide="next">
                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Next</span>
            </button>
        </div>
    </section>
    <?php endif; ?>

    <section id="home" class="hero-section text-center">
        <div class="container position-relative">
            <h1 class="display-4 fw-bold mb-4">Shop, Rent & Connect Live</h1>
            <p class="lead mb-5">Discover amazing products, chat with sellers in real-time, and join live shopping experiences.</p>
            <a href="#products" class="btn btn-light btn-lg me-2">Explore Products</a>
            <a href="live_streams.php" class="btn btn-live btn-lg me-2">
                <i class="fas fa-broadcast-tower me-2"></i>Live Streams
            </a>
            <a href="#features" class="btn btn-outline-light btn-lg">Learn More</a>
        </div>
    </section>


    <section id="products" class="py-5 bg-light">
        <div class="container">
            <div class="row mb-4">
                <div class="col-12">
                    <h2 class="text-center mb-4">Featured Products</h2>
                    
                    <!-- Search and Filter Section -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <form method="GET" class="row g-3">
                                <div class="col-md-6">
                                    <label for="search" class="form-label">Search Products</label>
                                    <input type="text" class="form-control" id="search" name="search" placeholder="Search by name, description, store, or category..." value="<?php echo htmlspecialchars($search_query); ?>">
                                </div>
                                <div class="col-md-4">
                                    <label for="type" class="form-label">Product Type</label>
                                    <select class="form-control" id="type" name="type">
                                        <option value="">All Types</option>
                                        <option value="regular" <?php echo $product_type === 'regular' ? 'selected' : ''; ?>>Regular Products</option>
                                        <option value="rental" <?php echo $product_type === 'rental' ? 'selected' : ''; ?>>Rental Products</option>
                                    </select>
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Product Type Tabs -->
                    <div class="d-flex justify-content-center mb-4">
                        <div class="btn-group" role="group">
                            <a href="index.php" class="btn <?php echo empty($product_type) && empty($search_query) ? 'btn-primary' : 'btn-outline-primary'; ?>">All Products</a>
                            <a href="index.php?type=regular" class="btn <?php echo $product_type === 'regular' ? 'btn-primary' : 'btn-outline-primary'; ?>">Regular Products</a>
                            <a href="index.php?type=rental" class="btn <?php echo $product_type === 'rental' ? 'btn-primary' : 'btn-outline-primary'; ?>">Rental Products</a>
                        </div>
                    </div>
                    
                    <!-- Results Info -->
                    <div class="text-center mb-4">
                        <p class="text-muted">
                            <?php 
                            if (!empty($search_query)) {
                                echo "Showing results for \"" . htmlspecialchars($search_query) . "\" - ";
                            }
                            echo "Displaying " . count($featured_products) . " of " . $total_products . " products";
                            ?>
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="row g-4">
                <?php if (!empty($featured_products)): ?>
                    <?php foreach ($featured_products as $product): ?>
                        <div class="col-lg-3 col-md-6">
                            <div class="card product-card h-100">
                                <div class="product-image">
                                    <?php if ($product['image_url']): ?>
                                        <img src="<?php echo htmlspecialchars($product['image_url']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="w-100 h-100" style="object-fit: cover;">
                                    <?php else: ?>
                                        <i class="fas fa-<?php echo getCategoryIcon($product['category_name'] ?? ''); ?>"></i>
                                    <?php endif; ?>
                                    <span class="product-badge <?php echo $product['product_type'] === 'rental' ? 'badge-rental' : 'badge-regular'; ?>">
                                        <?php echo strtoupper($product['product_type'] ?? 'REGULAR'); ?>
                                    </span>
                                </div>
                                <div class="card-body">
                                    <h6 class="card-title"><?php echo htmlspecialchars($product['name']); ?></h6>
                                    <p class="text-muted small mb-2"><i class="fas fa-tag me-1"></i><?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?></p>
                                    
                                    <?php if ($product['product_type'] === 'rental'): ?>
                                        <div class="rental-price mb-2">
                                            <div class="h6 text-primary mb-1"><i class="fas fa-calendar-day me-1"></i>$<?php echo number_format($product['rental_price_per_day'], 2); ?>/day</div>
                                            <?php if ($product['rental_price_per_week'] > 0): ?>
                                                <small class="text-muted">$<?php echo number_format($product['rental_price_per_week'], 2); ?>/week</small>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="h5 text-primary mb-2">$<?php echo number_format($product['price'], 2); ?></div>
                                    <?php endif; ?>
                                    
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <small class="text-muted"><?php echo htmlspecialchars($product['store_name'] ?? 'Seller'); ?></small>
                                        <span class="badge bg-success">Stock: <?php echo $product['stock'] ?? 0; ?></span>
                                    </div>
                                    
                                    <div class="btn-group w-100">
                                        <a href="product_detail.php?id=<?php echo $product['id']; ?>" class="btn btn-primary">
                                            <i class="fas fa-eye me-1"></i>View
                                        </a>
                                        <?php if ($product['product_type'] === 'rental'): ?>
                                            <a href="product_detail.php?id=<?php echo $product['id']; ?>&action=rent" class="btn btn-rental">
                                                <i class="fas fa-calendar-plus me-1"></i>Rent
                                            </a>
                                        <?php else: ?>
                                            <a href="product_detail.php?id=<?php echo $product['id']; ?>&action=buy" class="btn btn-regular">
                                                <i class="fas fa-shopping-cart me-1"></i>Buy
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12">
                        <div class="text-center py-5">
                            <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                            <h4>No products found</h4>
                            <p class="text-muted">Try adjusting your search or filter criteria</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <!--<section class="stats-section">-->
    <!--    <div class="container">-->
    <!--        <div class="row text-center">-->
    <!--            <div class="col-md-3 mb-4">-->
    <!--                <div class="stat-number text-primary"><?php echo number_format($stats['total_regular_products']); ?></div>-->
    <!--                <div class="text-muted">Regular Products</div>-->
    <!--            </div>-->
    <!--            <div class="col-md-3 mb-4">-->
    <!--                <div class="stat-number text-warning"><?php echo number_format($stats['total_rental_products']); ?></div>-->
    <!--                <div class="text-muted">Rental Products</div>-->
    <!--            </div>-->
    <!--            <div class="col-md-3 mb-4">-->
    <!--                <div class="stat-number text-success"><?php echo number_format($stats['total_sellers']); ?></div>-->
    <!--                <div class="text-muted">Active Sellers</div>-->
    <!--            </div>-->
    <!--            <div class="col-md-3 mb-4">-->
    <!--                <div class="stat-number text-info"><?php echo number_format($stats['total_orders']); ?></div>-->
    <!--                <div class="text-muted">Completed Orders</div>-->
    <!--            </div>-->
    <!--        </div>-->
    <!--    </div>-->
    <!--</section>-->

    <!-- Features Section -->
    <section id="features" class="py-5">
        <div class="container">
            <h2 class="text-center mb-5">Why Choose BSDO Sale?</h2>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="text-center">
                        <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                            <i class="fas fa-video fa-2x"></i>
                        </div>
                        <h4>Live Shopping</h4>
                        <p class="text-muted">Connect with sellers in real-time through our live streaming feature.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="text-center">
                        <div class="bg-success text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                            <i class="fas fa-comments fa-2x"></i>
                        </div>
                        <h4>Real-time Inquiries</h4>
                        <p class="text-muted">Chat directly with sellers to get instant answers to your questions.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="text-center">
                        <div class="bg-warning text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                            <i class="fas fa-calendar-alt fa-2x"></i>
                        </div>
                        <h4>Rent Products</h4>
                        <p class="text-muted">Rent products for short-term use at affordable daily or weekly rates.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Login Modal -->
    <div class="modal fade" id="loginModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content auth-form">
                <div class="modal-header border-0">
                    <h5 class="modal-title">Login to Your Account</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?php if (!empty($login_error)): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <?php echo htmlspecialchars($login_error); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($registration_success)): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <?php echo $registration_success; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <div class="role-selector mb-4">
                        <div class="role-option active" data-role="client"><i class="fas fa-user"></i><div>Client</div></div>
                        <div class="role-option" data-role="seller"><i class="fas fa-store"></i><div>Seller</div></div>
                        <div class="role-option" data-role="admin"><i class="fas fa-user-shield"></i><div>Admin</div></div>
                    </div>
                    
                    <form id="loginForm" action="login.php" method="POST">
                        <input type="hidden" id="loginRole" name="role" value="client">
                        <div class="mb-3">
                            <label for="loginEmail" class="form-label">Email address</label>
                            <input type="email" class="form-control" id="loginEmail" name="email" 
                                   value="<?php echo htmlspecialchars($login_form_data['email'] ?? ''); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="loginPassword" class="form-label">Password</label>
                            <input type="password" class="form-control" id="loginPassword" name="password" required>
                        </div>
                        
                        <div id="sellerFields" class="role-fields" style="display: none;">
                            <div class="mb-3">
                                <label for="sellerCode" class="form-label">Seller Code</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-key"></i></span>
                                    <input type="text" class="form-control" id="sellerCode" name="seller_code" 
                                           value="<?php echo htmlspecialchars($_COOKIE['seller_code'] ?? ''); ?>"
                                           placeholder="Enter your seller code">
                                </div>
                                <small class="text-muted">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Your seller code was provided during registration
                                </small>
                            </div>
                        </div>
                        
                        <div id="adminFields" class="role-fields" style="display: none;">
                            <div class="mb-3">
                                <label for="adminKey" class="form-label">Admin Security Key</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control" id="adminKey" name="admin_key" placeholder="Enter admin security key">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="rememberMe" name="remember_me">
                            <label class="form-check-label" for="rememberMe">Remember me</label>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg">Login</button>
                        </div>
                    </form>
                    <hr class="my-4">
                    <div class="text-center">
                        <p>Don't have an account? <a href="#" data-bs-toggle="modal" data-bs-target="#registerModal" data-bs-dismiss="modal" class="text-decoration-none">Register now</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Register Modal -->
    <div class="modal fade" id="registerModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content auth-form">
                <div class="modal-header border-0">
                    <h5 class="modal-title">Create an Account</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?php if (!empty($register_error)): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <?php echo htmlspecialchars($register_error); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <div class="role-selector mb-4">
                        <div class="role-option active" data-role="client"><i class="fas fa-user"></i><div>Client</div></div>
                        <div class="role-option" data-role="seller"><i class="fas fa-store"></i><div>Seller</div></div>
                    </div>
                    
                    <form id="registerForm" action="register.php" method="POST">
                        <input type="hidden" id="registerRole" name="role" value="client">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="firstName" class="form-label">First Name</label>
                                <input type="text" class="form-control" id="firstName" name="first_name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="lastName" class="form-label">Last Name</label>
                                <input type="text" class="form-control" id="lastName" name="last_name" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="registerEmail" class="form-label">Email address</label>
                            <input type="email" class="form-control" id="registerEmail" name="email" required>
                        </div>
                        <div id="sellerRegisterFields" class="role-fields" style="display: none;">
                            <div class="mb-3">
                                <label for="storeName" class="form-label">Store Name</label>
                                <input type="text" class="form-control" id="storeName" name="store_name">
                            </div>
                            <div class="mb-3">
                                <label for="businessType" class="form-label">Business Type</label>
                                <select class="form-control" id="businessType" name="business_type">
                                    <option value="">Select Business Type</option>
                                    <option value="retail">Retail</option>
                                    <option value="wholesale">Wholesale</option>
                                    <option value="manufacturer">Manufacturer</option>
                                    <option value="service">Service Provider</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="referralCode" class="form-label">
                                <i class="fas fa-gift text-success me-1"></i>Referral Code (Optional)
                            </label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-ticket-alt"></i></span>
                                <input type="text" class="form-control" id="referralCode" name="referral_code" placeholder="Enter referral code if you have one">
                            </div>
                            <small class="text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                If you were invited by a seller, enter their code to get rewards!
                            </small>
                        </div>
                        <div class="mb-3">
                            <label for="registerPassword" class="form-label">Password</label>
                            <input type="password" class="form-control" id="registerPassword" name="password" required>
                            <div class="form-text">Must be at least 8 characters with uppercase, lowercase, and numbers.</div>
                        </div>
                        <div class="mb-3">
                            <label for="confirmPassword" class="form-label">Confirm Password</label>
                            <input type="password" class="form-control" id="confirmPassword" name="confirm_password" required>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="termsAgree" name="terms_agree" required>
                            <label class="form-check-label" for="termsAgree">I agree to the <a href="#" class="text-decoration-none">Terms and Conditions</a></label>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg">Register</button>
                        </div>
                    </form>
                    <hr class="my-4">
                    <div class="text-center">
                        <p>Already have an account? <a href="#" data-bs-toggle="modal" data-bs-target="#loginModal" data-bs-dismiss="modal" class="text-decoration-none">Login here</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer class="bg-dark text-white py-5">
        <div class="container">
            <div class="row">
                <div class="col-lg-4 mb-4">
                    <h5 class="mb-4"><i class="fas fa-shopping-bag me-2"></i>BSDO SALE</h5>
                    <p>Your trusted e-commerce platform with live streaming, real-time inquiries, and rental products.</p>
                </div>
                <div class="col-lg-2 col-md-4 mb-4">
                    <h5 class="mb-4">Quick Links</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="#home" class="text-decoration-none text-light">Home</a></li>
                        <li class="mb-2"><a href="#products" class="text-decoration-none text-light">Products</a></li>
                        <li class="mb-2"><a href="live_streams.php" class="text-decoration-none text-light">Live Streams</a></li>
                    </ul>
                </div>
                <div class="col-lg-3 col-md-4 mb-4">
                    <h5 class="mb-4">Features</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><i class="fas fa-comments me-2 text-primary"></i>Real-time Inquiries</li>
                        <li class="mb-2"><i class="fas fa-video me-2 text-danger"></i>Live Shopping</li>
                        <li class="mb-2"><i class="fas fa-shopping-bag me-2 text-info"></i>Buy Products</li>
                        <li class="mb-2"><i class="fas fa-calendar-alt me-2 text-warning"></i>Rent Products</li>
                    </ul>
                </div>
                <div class="col-lg-3 col-md-4 mb-4">
                    <h5 class="mb-4">Newsletter</h5>
                    <p>Subscribe for updates on new products and live streams</p>
                    <form>
                        <div class="input-group mb-3">
                            <input type="email" class="form-control" placeholder="Your email">
                            <button class="btn btn-primary" type="button">Subscribe</button>
                        </div>
                    </form>
                </div>
            </div>
            <hr class="my-4">
            <div class="text-center">
                <p>&copy; 2024 BSDO Sale. All rights reserved. | Developed by <a href="mailto:Hermogene2001@gmail.com" class="text-decoration-none text-light">HermogenesTech</a></p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar');
            if (window.scrollY > 100) {
                navbar.classList.add('navbar-scrolled');
            } else {
                navbar.classList.remove('navbar-scrolled');
            }
        });

        function setInquiryProduct(productId, productName) {
            document.getElementById('inquiryProductId').value = productId;
            document.getElementById('inquiryProductName').textContent = productName;
        }

        document.querySelectorAll('.role-option').forEach(option => {
            option.addEventListener('click', function() {
                const parent = this.parentElement;
                parent.querySelectorAll('.role-option').forEach(opt => opt.classList.remove('active'));
                this.classList.add('active');
                const role = this.getAttribute('data-role');
                
                const loginRole = document.getElementById('loginRole');
                const registerRole = document.getElementById('registerRole');
                if (loginRole) loginRole.value = role;
                if (registerRole) registerRole.value = role;
                
                document.querySelectorAll('.role-fields').forEach(field => field.style.display = 'none');
                
                if (role === 'seller') {
                    const sellerFields = document.getElementById('sellerFields');
                    const sellerRegFields = document.getElementById('sellerRegisterFields');
                    if (sellerFields) sellerFields.style.display = 'block';
                    if (sellerRegFields) sellerRegFields.style.display = 'block';
                } else if (role === 'admin') {
                    const adminFields = document.getElementById('adminFields');
                    if (adminFields) adminFields.style.display = 'block';
                }
            });
        });

        const registerForm = document.getElementById('registerForm');
        if (registerForm) {
            registerForm.addEventListener('submit', function(e) {
                const password = document.getElementById('registerPassword').value;
                const confirmPassword = document.getElementById('confirmPassword').value;
                const role = document.getElementById('registerRole').value;
                
                if (password !== confirmPassword) {
                    e.preventDefault();
                    alert('Passwords do not match!');
                    return false;
                }
                
                if (password.length < 8 || !/[A-Z]/.test(password) || !/[a-z]/.test(password) || !/[0-9]/.test(password)) {
                    e.preventDefault();
                    alert('Password must be at least 8 characters long and contain uppercase, lowercase letters and numbers.');
                    return false;
                }
                
                if (role === 'seller') {
                    const storeName = document.getElementById('storeName').value;
                    const businessType = document.getElementById('businessType').value;
                    if (!storeName || !businessType) {
                        e.preventDefault();
                        alert('Please fill all seller-specific fields.');
                        return false;
                    }
                }
            });
        }

        const loginForm = document.getElementById('loginForm');
        if (loginForm) {
            loginForm.addEventListener('submit', function(e) {
                const role = document.getElementById('loginRole').value;
                if (role === 'admin') {
                    const adminKey = document.getElementById('adminKey').value;
                    if (!adminKey) {
                        e.preventDefault();
                        alert('Please enter the admin security key.');
                        return false;
                    }
                }
                if (role === 'seller') {
                    const sellerCode = document.getElementById('sellerCode').value;
                    if (!sellerCode) {
                        e.preventDefault();
                        alert('Please enter your seller code.');
                        return false;
                    }
                }
            });
        }

        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                const href = this.getAttribute('href');
                if (href !== '#') {
                    const target = document.querySelector(href);
                    if (target) {
                        e.preventDefault();
                        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                }
            });
        });

        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            setTimeout(() => {
                const bsAlert = bootstrap.Alert.getInstance(alert);
                if (bsAlert) bsAlert.close();
            }, 5000);
        });

        function checkForInquiryUpdates() {
            <?php if ($is_logged_in && $user_role === 'client'): ?>
            fetch('check_inquiry_updates.php')
                .then(response => response.json())
                .then(data => {
                    if (data.new_messages > 0) {
                        const badge = document.querySelector('.inquiry-badge');
                        if (badge) badge.textContent = data.new_messages;
                        if (data.new_messages > <?php echo $pending_inquiries_count; ?>) {
                            showNotification('New message from seller!');
                        }
                    }
                })
                .catch(err => console.error('Error checking updates:', err));
            <?php endif; ?>
        }

        function showNotification(message) {
            const notification = document.createElement('div');
            notification.className = 'alert alert-info alert-dismissible fade show position-fixed';
            notification.style.cssText = 'top: 100px; right: 20px; z-index: 9999; min-width: 300px;';
            notification.innerHTML = `${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
            document.body.appendChild(notification);
            setTimeout(() => notification.remove(), 5000);
        }

        // Function to copy seller code to clipboard
        function copySellerCode() {
            const sellerCode = document.getElementById('sellerCodeDisplay').textContent;
            navigator.clipboard.writeText(sellerCode).then(() => {
                alert('Seller code copied to clipboard!');
            }).catch(err => {
                console.error('Failed to copy: ', err);
                // Fallback for older browsers
                const textArea = document.createElement('textarea');
                textArea.value = sellerCode;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                alert('Seller code copied to clipboard!');
            });
        }

        // Function to save seller code to cookie
        function saveSellerCodeToCookie() {
            const sellerCode = document.getElementById('sellerCodeDisplay').textContent;
            // Set cookie to expire in 30 days
            const expiryDate = new Date();
            expiryDate.setDate(expiryDate.getDate() + 30);
            document.cookie = `seller_code=${sellerCode}; expires=${expiryDate.toUTCString()}; path=/`;
            alert('Seller code saved to cookies! It will be auto-filled next time you login as a seller.');
        }

        setInterval(checkForInquiryUpdates, 30000);

        document.addEventListener('DOMContentLoaded', function() {
            // Check for referral code in URL and auto-fill
            const urlParams = new URLSearchParams(window.location.search);
            const referralCode = urlParams.get('ref');
            
            if (referralCode) {
                // Pre-fill the referral code field
                const referralInput = document.getElementById('referralCode');
                if (referralInput) {
                    referralInput.value = referralCode;
                    // Highlight the field to draw attention
                    referralInput.style.borderColor = '#1cc88a';
                    referralInput.style.backgroundColor = '#f0fff4';
                }
                
                // Show registration modal automatically if user came via referral link
                const registerModal = new bootstrap.Modal(document.getElementById('registerModal'));
                registerModal.show();
                
                // Show a friendly message
                setTimeout(() => {
                    const referralAlert = document.createElement('div');
                    referralAlert.className = 'alert alert-success alert-dismissible fade show';
                    referralAlert.style.cssText = 'position: fixed; top: 80px; right: 20px; z-index: 9999; min-width: 350px;';
                    referralAlert.innerHTML = `
                        <i class="fas fa-gift me-2"></i>
                        <strong>Welcome!</strong> You've been invited with referral code: <code>${referralCode}</code>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    `;
                    document.body.appendChild(referralAlert);
                    setTimeout(() => referralAlert.remove(), 8000);
                }, 500);
            }
            
            <?php if (!empty($login_error) || !empty($registration_success)): ?>
                const loginModal = new bootstrap.Modal(document.getElementById('loginModal'));
                loginModal.show();
            <?php endif; ?>
            
            <?php if (!empty($register_error)): ?>
                const registerModal = new bootstrap.Modal(document.getElementById('registerModal'));
                registerModal.show();
            <?php endif; ?>
            
            <?php if (!empty($login_form_data)): ?>
                setTimeout(() => {
                    const formData = <?php echo json_encode($login_form_data); ?>;
                    const roleOptions = document.querySelectorAll('#loginModal .role-option');
                    roleOptions.forEach(option => {
                        if (option.getAttribute('data-role') === formData.role) {
                            option.click();
                        }
                    });
                }, 500);
            <?php endif; ?>
        });
    </script>
</body>
</html>