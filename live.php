<?php
session_start();
require_once 'config.php';

// Check if user is logged in
$is_logged_in = isset($_SESSION['user_id']);
$user_name = $_SESSION['user_name'] ?? '';
$user_role = $_SESSION['user_role'] ?? '';
$user_id = $_SESSION['user_id'] ?? null;

// Handle add to cart action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    if (!$is_logged_in) {
        header("Location: login.php");
        exit();
    }
    
    $product_id = intval($_POST['product_id']);
    
    // Initialize cart if not exists
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    
    // Check if product exists and is in stock
    $product_check = $pdo->prepare("SELECT name, price, stock FROM products WHERE id = ? AND status = 'active'");
    $product_check->execute([$product_id]);
    $product = $product_check->fetch(PDO::FETCH_ASSOC);
    
    if ($product) {
        $current_quantity = isset($_SESSION['cart'][$product_id]) ? $_SESSION['cart'][$product_id]['quantity'] : 0;
        $quantity = isset($_POST['quantity']) ? max(1, intval($_POST['quantity'])) : 1;
        
        if (($current_quantity + $quantity) <= $product['stock']) {
            if (isset($_SESSION['cart'][$product_id])) {
                $_SESSION['cart'][$product_id]['quantity'] += $quantity;
            } else {
                $_SESSION['cart'][$product_id] = [
                    'name' => $product['name'],
                    'price' => $product['price'],
                    'quantity' => $quantity
                ];
            }
            $success_message = "Product added to cart successfully!";
        } else {
            $error_message = "Not enough stock available. Only " . $product['stock'] . " items left.";
        }
    } else {
        $error_message = "Product not found or unavailable.";
    }
}

// Get active live streams with seller information
$live_streams_stmt = $pdo->prepare("
    SELECT ls.*, u.store_name, u.first_name, u.last_name, 
           COUNT(lsv.id) as current_viewers,
           c.name as category_name
    FROM live_streams ls 
    JOIN users u ON ls.seller_id = u.id 
    LEFT JOIN live_stream_viewers lsv ON ls.id = lsv.stream_id AND lsv.is_active = 1
    LEFT JOIN categories c ON ls.category_id = c.id
    WHERE ls.is_live = 1
    GROUP BY ls.id
    ORDER BY ls.started_at DESC
");
$live_streams_stmt->execute();
$live_streams = $live_streams_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get products from sellers who are currently live
$live_seller_ids = array_column($live_streams, 'seller_id');
$live_products = [];

// Get search query if provided
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

if (!empty($live_seller_ids)) {
    if (!empty($search_query)) {
        // Search products from live sellers
        $placeholders = str_repeat('?,', count($live_seller_ids) - 1) . '?';
        $products_query = "
            SELECT p.*, u.store_name, c.name as category_name 
            FROM products p 
            JOIN users u ON p.seller_id = u.id 
            LEFT JOIN categories c ON p.category_id = c.id 
            WHERE p.status = 'active' AND p.seller_id IN ($placeholders)
            AND (p.name LIKE ? OR p.description LIKE ? OR u.store_name LIKE ?)
            ORDER BY p.created_at DESC 
            LIMIT 20
        ";
        
        $search_term = "%$search_query%";
        $params = array_merge($live_seller_ids, [$search_term, $search_term, $search_term]);
        
        $products_stmt = $pdo->prepare($products_query);
        $products_stmt->execute($params);
        $live_products = $products_stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Get all products from live sellers
        $placeholders = str_repeat('?,', count($live_seller_ids) - 1) . '?';
        $products_query = "
            SELECT p.*, u.store_name, c.name as category_name 
            FROM products p 
            JOIN users u ON p.seller_id = u.id 
            LEFT JOIN categories c ON p.category_id = c.id 
            WHERE p.status = 'active' AND p.seller_id IN ($placeholders)
            ORDER BY p.created_at DESC 
            LIMIT 20
        ";
        
        $products_stmt = $pdo->prepare($products_query);
        $products_stmt->execute($live_seller_ids);
        $live_products = $products_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Get statistics
$stats_stmt = $pdo->prepare("
    SELECT 
        (SELECT COUNT(*) FROM live_streams WHERE is_live = 1) as live_now,
        (SELECT COALESCE(SUM(viewer_count), 0) FROM live_streams WHERE is_live = 1) as total_viewers
");
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Streams & Products - BSDO Sale</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #1cc88a;
            --live-color: #e74a3b;
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
        }
        
        .hero-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 100px 0 60px;
            margin-top: 76px;
        }
        
        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            border: none;
            transition: transform 0.3s;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
        }
        
        .stats-number {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .stats-label {
            font-size: 0.9rem;
            color: #6c757d;
        }
        
        .stream-card, .product-card {
            border: none;
            border-radius: 15px;
            overflow: hidden;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            height: 100%;
        }
        
        .stream-card:hover, .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
        }
        
        .stream-thumbnail {
            height: 200px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
            position: relative;
        }
        
        .live-indicator {
            position: absolute;
            top: 10px;
            left: 10px;
            background: var(--live-color);
            color: white;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 0.7rem;
            font-weight: bold;
            z-index: 10;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.7; }
            100% { opacity: 1; }
        }
        
        .viewer-count {
            position: absolute;
            bottom: 10px;
            right: 10px;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
        }
        
        .seller-info {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .seller-avatar {
            width: 40px;
            height: 40px;
            background: var(--primary-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            margin-right: 10px;
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
        
        .product-price {
            color: var(--primary-color);
            font-size: 1.5rem;
            font-weight: bold;
        }
        
        .section-title {
            position: relative;
            padding-bottom: 15px;
            margin-bottom: 30px;
        }
        
        .section-title:after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 50px;
            height: 3px;
            background: var(--primary-color);
            border-radius: 3px;
        }
        
        .btn-watch {
            background: var(--live-color);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: bold;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
        }
        
        .btn-watch:hover {
            background: #c0392b;
            color: white;
            transform: translateY(-2px);
        }
        
        .btn-cart {
            background: var(--secondary-color);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 5px;
            font-weight: bold;
            width: 100%;
            transition: all 0.3s;
        }
        
        .btn-cart:hover {
            background: #169c6f;
            transform: translateY(-2px);
        }
        
        .alert {
            border-radius: 10px;
        }
        
        .search-form {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            margin-bottom: 30px;
        }
        
        footer {
            background-color: var(--dark-color);
            color: white;
            padding: 60px 0 30px;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
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
                    <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="products.php">Products</a></li>
                    <li class="nav-item"><a class="nav-link active" href="live.php">Live Now</a></li>
                    <li class="nav-item"><a class="nav-link" href="products.php?type=regular">Regular</a></li>
                    <li class="nav-item"><a class="nav-link" href="products.php?type=rental">Rental</a></li>
                </ul>
                
                <div class="d-flex align-items-center">
                    <?php if ($is_logged_in): ?>
                        <!-- Logged In User Menu -->
                        <div class="dropdown">
                            <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown">
                                <div class="user-avatar me-2">
                                    <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                                </div>
                                <span class="me-2"><?php echo htmlspecialchars(explode(' ', $user_name)[0]); ?></span>
                            </a>
                            <ul class="dropdown-menu">
                                <?php if ($user_role === 'seller'): ?>
                                    <li><a class="dropdown-item" href="seller/dashboard.php"><i class="fas fa-tachometer-alt me-2"></i>Seller Dashboard</a></li>
                                    <li><a class="dropdown-item" href="seller/live_stream.php"><i class="fas fa-video me-2"></i>Go Live</a></li>
                                <?php elseif ($user_role === 'admin'): ?>
                                    <li><a class="dropdown-item" href="admin/dashboard.php"><i class="fas fa-cog me-2"></i>Admin Panel</a></li>
                                <?php else: ?>
                                    <li><a class="dropdown-item" href="myAccount.php"><i class="fas fa-user me-2"></i>My Account</a></li>
                                    <li><a class="dropdown-item" href="inquiries.php"><i class="fas fa-comments me-2"></i>My Inquiries</a></li>
                                <?php endif; ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <!-- Guest User Menu -->
                        <a href="login.php" class="btn btn-outline-primary me-2">Login</a>
                        <a href="register.php" class="btn btn-primary">Register</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h1 class="display-4 fw-bold mb-3">Live Shopping Experience</h1>
                    <p class="lead mb-4">Watch live product demonstrations, interact with sellers, and shop in real-time with exclusive live stream deals.</p>
                    <div class="d-flex gap-3">
                        <a href="#live-streams" class="btn btn-light btn-lg">
                            <i class="fas fa-video me-2"></i>Watch Live Streams
                        </a>
                        <a href="#live-products" class="btn btn-outline-light btn-lg">
                            <i class="fas fa-shopping-cart me-2"></i>Shop Live Products
                        </a>
                    </div>
                </div>
                <div class="col-lg-4 text-center d-none d-lg-block">
                    <i class="fas fa-broadcast-tower fa-8x opacity-50"></i>
                </div>
            </div>
        </div>
    </section>

    <!-- Messages -->
    <?php if (isset($success_message)): ?>
        <div class="container mt-4">
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </div>
    <?php endif; ?>
    
    <?php if (isset($error_message)): ?>
        <div class="container mt-4">
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </div>
    <?php endif; ?>

    <!-- Statistics Section -->
    <section class="py-5 bg-light">
        <div class="container">
            <div class="row g-4">
                <div class="col-md-6">
                    <div class="stats-card">
                        <div class="stats-number text-danger"><?php echo $stats['live_now']; ?></div>
                        <div class="stats-label">Streams Live Now</div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="stats-card">
                        <div class="stats-number text-primary"><?php echo $stats['total_viewers']; ?></div>
                        <div class="stats-label">Total Viewers</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Live Streams Section -->
    <section id="live-streams" class="py-5">
        <div class="container">
            <div class="section-title">
                <h2>Live Streams</h2>
                <p>Watch sellers showcase their products in real-time</p>
            </div>
            
            <?php if (!empty($live_streams)): ?>
                <div class="row g-4">
                    <?php foreach ($live_streams as $stream): ?>
                        <div class="col-lg-4 col-md-6">
                            <div class="stream-card">
                                <div class="stream-thumbnail">
                                    <div class="live-indicator">
                                        <i class="fas fa-circle me-1"></i>LIVE
                                    </div>
                                    <div class="viewer-count">
                                        <i class="fas fa-eye me-1"></i><?php echo $stream['current_viewers']; ?>
                                    </div>
                                    <i class="fas fa-video"></i>
                                </div>
                                <div class="card-body">
                                    <div class="seller-info">
                                        <div class="seller-avatar">
                                            <?php echo strtoupper(substr($stream['first_name'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <h6 class="mb-0"><?php echo htmlspecialchars($stream['store_name'] ?? 'Seller Store'); ?></h6>
                                            <small class="text-muted"><?php echo htmlspecialchars($stream['first_name'] . ' ' . $stream['last_name']); ?></small>
                                        </div>
                                    </div>
                                    
                                    <h5 class="card-title mt-3"><?php echo htmlspecialchars($stream['title']); ?></h5>
                                    <p class="card-text text-muted small"><?php echo htmlspecialchars(substr($stream['description'], 0, 100)); ?>...</p>
                                    
                                    <?php if (!empty($stream['category_name'])): ?>
                                        <span class="badge bg-light text-dark mb-3">
                                            <i class="fas fa-tag me-1"></i><?php echo htmlspecialchars($stream['category_name']); ?>
                                        </span>
                                    <?php endif; ?>
                                    
                                    <div class="d-grid">
                                        <a href="watch_stream.php?stream_id=<?php echo $stream['id']; ?>" class="btn-watch">
                                            <i class="fas fa-play me-2"></i>Join Stream
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-video-slash fa-5x text-muted mb-4"></i>
                    <h3 class="mb-3">No Live Streams Currently</h3>
                    <p class="text-muted mb-4">Check back later for live shopping streams from our sellers.</p>
                    <a href="index.php" class="btn btn-primary">Browse Products</a>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Products from Live Sellers Section -->
    <section id="live-products" class="py-5 bg-light">
        <div class="container">
            <div class="section-title">
                <h2>Products from Live Sellers</h2>
                <p>Shop products from sellers who are currently live streaming</p>
            </div>
            
            <!-- Search Form -->
            <div class="search-form">
                <form method="GET" class="row g-3">
                    <div class="col-md-10">
                        <input type="text" class="form-control form-control-lg" name="search" 
                               placeholder="Search products from live sellers..." 
                               value="<?php echo htmlspecialchars($search_query); ?>">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary btn-lg w-100">
                            <i class="fas fa-search me-2"></i>Search
                        </button>
                    </div>
                </form>
            </div>
            
            <?php if (!empty($live_products)): ?>
                <div class="row g-4">
                    <?php foreach ($live_products as $product): ?>
                        <div class="col-lg-3 col-md-4 col-sm-6">
                            <div class="product-card">
                                <div class="product-image">
                                    <i class="fas fa-shopping-bag"></i>
                                </div>
                                <div class="card-body">
                                    <span class="badge bg-primary position-absolute" style="top: 10px; right: 10px;">
                                        <i class="fas fa-video me-1"></i>LIVE
                                    </span>
                                    <h5 class="card-title"><?php echo htmlspecialchars($product['name']); ?></h5>
                                    <p class="text-muted small"><?php echo htmlspecialchars(substr($product['description'], 0, 60)); ?>...</p>
                                    <div class="mb-2">
                                        <small class="text-muted">
                                            <i class="fas fa-store me-1"></i><?php echo htmlspecialchars($product['store_name']); ?>
                                        </small>
                                    </div>
                                    <div class="product-price mb-3">
                                        <?php echo '$' . number_format($product['price'], 2); ?>
                                        <?php if ($product['product_type'] === 'rental'): ?>
                                            <span class="badge bg-warning">Rental</span>
                                        <?php endif; ?>
                                    </div>
                                    <form method="POST">
                                        <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                        <button type="submit" name="add_to_cart" class="btn-cart">
                                            <i class="fas fa-shopping-cart me-2"></i>Add to Cart
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-shopping-bag fa-5x text-muted mb-4"></i>
                    <h3 class="mb-3">
                        <?php if (!empty($search_query)): ?>
                            No products found matching "<?php echo htmlspecialchars($search_query); ?>"
                        <?php else: ?>
                            No Products from Live Sellers
                        <?php endif; ?>
                    </h3>
                    <p class="text-muted mb-4">
                        <?php if (!empty($search_query)): ?>
                            Try different search terms or browse all products from live sellers.
                        <?php else: ?>
                            There are currently no products available from sellers who are live streaming.
                        <?php endif; ?>
                    </p>
                    <?php if (!empty($search_query)): ?>
                        <a href="live.php" class="btn btn-primary me-2">Clear Search</a>
                    <?php endif; ?>
                    <a href="products.php" class="btn btn-primary">Browse All Products</a>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- How It Works Section -->
    <section class="py-5">
        <div class="container">
            <div class="text-center mb-5">
                <h2>How Live Shopping Works</h2>
                <p class="text-muted">Experience the future of e-commerce with real-time interactions</p>
            </div>
            <div class="row g-4">
                <div class="col-md-4 text-center">
                    <div class="feature-icon mb-3">
                        <i class="fas fa-video fa-3x text-primary"></i>
                    </div>
                    <h4>Watch Live</h4>
                    <p class="text-muted">Join live streams to see products in action with real-time demonstrations.</p>
                </div>
                <div class="col-md-4 text-center">
                    <div class="feature-icon mb-3">
                        <i class="fas fa-comments fa-3x text-primary"></i>
                    </div>
                    <h4>Interact Live</h4>
                    <p class="text-muted">Ask questions and get immediate responses from sellers during the stream.</p>
                </div>
                <div class="col-md-4 text-center">
                    <div class="feature-icon mb-3">
                        <i class="fas fa-shopping-cart fa-3x text-primary"></i>
                    </div>
                    <h4>Shop Instantly</h4>
                    <p class="text-muted">Purchase featured products directly during the live stream with special deals.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="row">
                <div class="col-lg-4 mb-4">
                    <h5 class="mb-4"><i class="fas fa-shopping-bag me-2"></i>BSDO SALE</h5>
                    <p>Experience the future of e-commerce with live shopping streams and real-time interactions.</p>
                </div>
                <div class="col-lg-2 col-md-4 mb-4">
                    <h5 class="mb-4">Quick Links</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="index.php" class="text-decoration-none text-light">Home</a></li>
                        <li class="mb-2"><a href="products.php" class="text-decoration-none text-light">Products</a></li>
                        <li class="mb-2"><a href="live.php" class="text-decoration-none text-light">Live Streams</a></li>
                    </ul>
                </div>
                <div class="col-lg-3 col-md-4 mb-4">
                    <h5 class="mb-4">Support</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="#" class="text-decoration-none text-light">Help Center</a></li>
                        <li class="mb-2"><a href="#" class="text-decoration-none text-light">FAQ</a></li>
                        <li class="mb-2"><a href="#" class="text-decoration-none text-light">Contact Us</a></li>
                    </ul>
                </div>
                <div class="col-lg-3 col-md-4 mb-4">
                    <h5 class="mb-4">Connect</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="#" class="text-decoration-none text-light"><i class="fab fa-facebook me-2"></i>Facebook</a></li>
                        <li class="mb-2"><a href="#" class="text-decoration-none text-light"><i class="fab fa-twitter me-2"></i>Twitter</a></li>
                        <li class="mb-2"><a href="#" class="text-decoration-none text-light"><i class="fab fa-instagram me-2"></i>Instagram</a></li>
                    </ul>
                </div>
            </div>
            <hr class="my-4">
            <div class="text-center">
                <p>© 2024 BSDO Sale. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-refresh for live streams
        function refreshLiveStreams() {
            setTimeout(() => {
                window.location.reload();
            }, 60000); // Refresh every minute
        }

        // Start auto-refresh
        refreshLiveStreams();
    </script>
</body>
</html>