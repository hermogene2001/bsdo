<?php
session_start();
require_once 'config.php';
require_once 'utils/SecurityUtils.php';
require_once 'utils/Logger.php';

// Send security headers
SecurityUtils::sendSecurityHeaders();

// Regenerate session ID to prevent fixation attacks
SecurityUtils::regenerateSession();

// Get filter parameters with proper validation
$category_id = '';
if (isset($_GET['category'])) {
    $category_id = SecurityUtils::sanitizeInt($_GET['category']);
    if ($category_id === false) {
        $category_id = '';
        Logger::warning('Invalid category ID provided', ['category' => $_GET['category']]);
    }
}

$search = '';
if (isset($_GET['search'])) {
    $search = SecurityUtils::sanitizeInput($_GET['search']);
}

$sort = 'newest';
$valid_sort_options = ['price_low', 'price_high', 'popular', 'name', 'newest'];
if (isset($_GET['sort']) && in_array($_GET['sort'], $valid_sort_options)) {
    $sort = $_GET['sort'];
}

$min_price = '';
if (isset($_GET['min_price'])) {
    $min_price = SecurityUtils::sanitizeFloat($_GET['min_price'], 0);
    if ($min_price === false) {
        $min_price = '';
        Logger::warning('Invalid min_price provided', ['min_price' => $_GET['min_price']]);
    }
}

$max_price = '';
if (isset($_GET['max_price'])) {
    $max_price = SecurityUtils::sanitizeFloat($_GET['max_price'], 0);
    if ($max_price === false) {
        $max_price = '';
        Logger::warning('Invalid max_price provided', ['max_price' => $_GET['max_price']]);
    }
}

$page = 1;
if (isset($_GET['page'])) {
    $page = SecurityUtils::sanitizeInt($_GET['page'], 1);
    if ($page === false) {
        $page = 1;
        Logger::warning('Invalid page number provided', ['page' => $_GET['page']]);
    }
}

$limit = 12; // Products per page
$offset = ($page - 1) * $limit;

// Check if user is logged in
$is_logged_in = SecurityUtils::isLoggedIn();
$user_id = $_SESSION['user_id'] ?? null;
$user_role = $_SESSION['user_role'] ?? null;
$user_name = $_SESSION['user_name'] ?? null;

$error_message = '';
$success_message = '';

// Handle messages from session
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Prepare filters array
$filters = [
    'category_id' => $category_id,
    'search' => $search,
    'sort' => $sort,
    'min_price' => $min_price,
    'max_price' => $max_price,
    'limit' => $limit,
    'offset' => $offset
];

// Get products with filters
$sql = "SELECT p.*, c.name as category_name, u.store_name, (SELECT COALESCE(SUM(oi.quantity), 0) FROM order_items oi JOIN orders o ON oi.order_id = o.id AND o.status = 'completed' WHERE oi.product_id = p.id) as units_sold FROM products p LEFT JOIN categories c ON p.category_id = c.id LEFT JOIN users u ON p.seller_id = u.id WHERE p.status = 'active'";

$params = [];

if (!empty($filters['category_id'])) {
    $sql .= " AND p.category_id = ?";
    $params[] = $filters['category_id'];
}

if (!empty($filters['search'])) {
    $sql .= " AND (p.name LIKE ? OR p.description LIKE ?)";
    $search_param = "%" . $filters['search'] . "%";
    $params[] = $search_param;
    $params[] = $search_param;
}

if ($filters['min_price'] !== '') {
    $sql .= " AND p.price >= ?";
    $params[] = $filters['min_price'];
}

if ($filters['max_price'] !== '') {
    $sql .= " AND p.price <= ?";
    $params[] = $filters['max_price'];
}

// Apply sorting
$sort_sql = " ORDER BY ";
switch ($filters['sort']) {
    case 'price_low':
        $sort_sql .= "p.price ASC";
        break;
    case 'price_high':
        $sort_sql .= "p.price DESC";
        break;
    case 'popular':
        $sort_sql .= "units_sold DESC, p.created_at DESC";
        break;
    case 'name':
        $sort_sql .= "p.name ASC";
        break;
    case 'newest':
    default:
        $sort_sql .= "p.created_at DESC";
        break;
}

// For LIMIT and OFFSET, we'll append them directly since PDO has issues with integer binding in LIMIT clauses
$sql .= $sort_sql . " LIMIT " . (int)$filters['limit'] . " OFFSET " . (int)$filters['offset'];

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    Logger::error('Database error fetching products', ['error' => $e->getMessage()]);
    $products = [];
    $error_message = "Error loading products. Please try again later.";
}

// Get total count for pagination
$count_sql = "SELECT COUNT(*) FROM products p WHERE p.status = 'active'";
$count_params = [];

if (!empty($filters['category_id'])) {
    $count_sql .= " AND p.category_id = ?";
    $count_params[] = $filters['category_id'];
}

if (!empty($filters['search'])) {
    $count_sql .= " AND (p.name LIKE ? OR p.description LIKE ?)";
    $search_param = "%" . $filters['search'] . "%";
    $count_params[] = $search_param;
    $count_params[] = $search_param;
}

if ($filters['min_price'] !== '') {
    $count_sql .= " AND p.price >= ?";
    $count_params[] = $filters['min_price'];
}

if ($filters['max_price'] !== '') {
    $count_sql .= " AND p.price <= ?";
    $count_params[] = $filters['max_price'];
}

try {
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($count_params);
    $total_products = $count_stmt->fetchColumn();
} catch (PDOException $e) {
    Logger::error('Database error counting products', ['error' => $e->getMessage()]);
    $total_products = 0;
}

$total_pages = ceil($total_products / $limit);

// Get categories for filter
try {
    $categories_stmt = $pdo->prepare("SELECT id, name FROM categories WHERE status = 'active' ORDER BY name");
    $categories_stmt->execute();
    $categories = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    Logger::error('Database error fetching categories', ['error' => $e->getMessage()]);
    $categories = [];
}

// Get price range for filter
try {
    $price_range_stmt = $pdo->prepare("SELECT MIN(price) as min_price, MAX(price) as max_price FROM products WHERE status = 'active'");
    $price_range_stmt->execute();
    $price_range = $price_range_stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    Logger::error('Database error fetching price range', ['error' => $e->getMessage()]);
    $price_range = ['min_price' => 0, 'max_price' => 0];
}

// Handle add to cart action (only for logged in clients)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $is_logged_in && $user_role === 'client') {
    // CSRF protection
    if (!isset($_POST['csrf_token']) || !SecurityUtils::validateCSRFToken($_POST['csrf_token'])) {
        $error_message = "Invalid request. Please try again.";
        Logger::warning('CSRF token validation failed', ['user_id' => $user_id]);
    } else {
        if (isset($_POST['add_to_cart'])) {
            $product_id = SecurityUtils::sanitizeInt($_POST['product_id']);
            $quantity = 1;
            
            if (isset($_POST['quantity'])) {
                $quantity = SecurityUtils::sanitizeInt($_POST['quantity'], 1, 100);
                if ($quantity === false) {
                    $quantity = 1;
                    Logger::warning('Invalid quantity provided', ['quantity' => $_POST['quantity']]);
                }
            }
            
            if ($product_id !== false) {
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
                        
                        $result = ['success' => true, 'message' => 'Product added to cart successfully!'];
                        Logger::info('Product added to cart', ['product_id' => $product_id, 'quantity' => $quantity, 'user_id' => $user_id]);
                    } else {
                        $result = ['success' => false, 'message' => 'Not enough stock available. Only ' . $product['stock'] . ' items left.'];
                        Logger::warning('Insufficient stock for add to cart', ['product_id' => $product_id, 'requested' => $quantity, 'available' => $product['stock']]);
                    }
                } else {
                    $result = ['success' => false, 'message' => 'Product not found or unavailable'];
                    Logger::warning('Product not found for add to cart', ['product_id' => $product_id]);
                }
                
                if ($result['success']) {
                    $success_message = $result['message'];
                    Logger::info('Product added to cart', ['product_id' => $product_id, 'quantity' => $quantity, 'user_id' => $user_id]);
                } else {
                    $error_message = $result['message'];
                    Logger::warning('Failed to add product to cart', ['product_id' => $product_id, 'reason' => $error_message]);
                }
            } else {
                $error_message = "Invalid product ID.";
                Logger::warning('Invalid product ID for add to cart', ['product_id' => $_POST['product_id']]);
            }
        }
    }
}

// Generate CSRF token for forms
$csrf_token = SecurityUtils::generateCSRFToken();

function formatCurrency($amount) {
    return '$' . number_format($amount, 2);
}

function getStockBadge($stock) {
    if ($stock > 10) {
        return '<span class="badge bg-success">In Stock</span>';
    } elseif ($stock > 0) {
        return '<span class="badge bg-warning">Low Stock</span>';
    } else {
        return '<span class="badge bg-danger">Out of Stock</span>';
    }
}

// Function to display product location
function getProductLocation($product) {
    $location = '';
    if (!empty($product['city'])) {
        $location .= $product['city'];
    }
    if (!empty($product['state'])) {
        $location .= (!empty($location) ? ', ' : '') . $product['state'];
    }
    if (!empty($product['country'])) {
        $location .= (!empty($location) ? ', ' : '') . $product['country'];
    }
    return $location;
}

// Function to format address
function formatAddress($product) {
    $addressParts = [];
    if (!empty($product['address'])) {
        $addressParts[] = $product['address'];
    }
    if (!empty($product['city'])) {
        $addressParts[] = $product['city'];
    }
    if (!empty($product['state'])) {
        $addressParts[] = $product['state'];
    }
    if (!empty($product['postal_code'])) {
        $addressParts[] = $product['postal_code'];
    }
    if (!empty($product['country'])) {
        $addressParts[] = $product['country'];
    }
    
    return implode(', ', $addressParts);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products - BSDO Sale</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #1cc88a;
            --dark-color: #2e3a59;
            --light-color: #f8f9fc;
        }
        
        body {
            font-family: 'Nunito', sans-serif;
            background-color: var(--light-color);
        }
        
        .navbar {
            background-color: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .products-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            padding: 2rem;
            margin-top: 2rem;
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
            overflow: hidden;
        }
        
        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .carousel-control-prev,
        .carousel-control-next {
            width: 30px;
            height: 30px;
            top: 50%;
            transform: translateY(-50%);
            background-color: rgba(0, 0, 0, 0.5);
            border-radius: 50%;
            opacity: 0;
            transition: opacity 0.3s;
        }
        
        .product-card:hover .carousel-control-prev,
        .product-card:hover .carousel-control-next {
            opacity: 1;
        }
        
        /* Always show carousel controls on mobile devices */
        @media (max-width: 768px) {
            .carousel-control-prev,
            .carousel-control-next {
                opacity: 1;
                width: 40px;
                height: 40px;
            }
            
            .carousel-control-prev-icon,
            .carousel-control-next-icon {
                width: 20px;
                height: 20px;
            }
        }
        
        .carousel-control-prev {
            left: 10px;
        }
        
        .carousel-control-next {
            right: 10px;
        }
        
        .carousel-control-prev-icon,
        .carousel-control-next-icon {
            width: 15px;
            height: 15px;
        }
        
        .filter-sidebar {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .price-slider {
            width: 100%;
        }
        
        .price-values {
            display: flex;
            justify-content: space-between;
            margin-top: 0.5rem;
        }
        
        .category-badge {
            background: var(--primary-color);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .seller-badge {
            background: var(--secondary-color);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .pagination .page-link {
            color: var(--primary-color);
            border: 1px solid var(--primary-color);
        }
        
        .pagination .page-item.active .page-link {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .search-box {
            position: relative;
        }
        
        .search-box .btn {
            position: absolute;
            right: 5px;
            top: 5px;
            z-index: 2;
        }
        
        .results-info {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .quantity-input {
            width: 70px;
            text-align: center;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">
                <i class="fas fa-shopping-bag me-2 text-primary"></i>BSDO SALE
            </a>
            
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link active" href="products.php">Products</a></li>
                    <li class="nav-item"><a class="nav-link" href="categories.php">Categories</a></li>
                    <?php if ($is_logged_in && $user_role === 'client'): ?>
                        <li class="nav-item"><a class="nav-link" href="cart.php">Cart</a></li>
                        <li class="nav-item"><a class="nav-link" href="orders.php">Orders</a></li>
                    <?php endif; ?>
                </ul>
                
                <div class="d-flex align-items-center">
                    <?php if ($is_logged_in): ?>
                        <div class="dropdown">
                            <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown">
                                <div class="user-avatar me-2">
                                    <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                                </div>
                                <span><?php echo htmlspecialchars(explode(' ', $user_name)[0]); ?></span>
                                <?php if ($user_role === 'client' && isset($_SESSION['cart']) && count($_SESSION['cart']) > 0): ?>
                                    <span class="position-relative ms-2">
                                        <i class="fas fa-shopping-cart"></i>
                                        <span class="cart-badge"><?php echo count($_SESSION['cart']); ?></span>
                                    </span>
                                <?php endif; ?>
                            </a>
                            <ul class="dropdown-menu">
                                <?php if ($user_role === 'seller'): ?>
                                    <li><a class="dropdown-item" href="seller/dashboard.php"><i class="fas fa-tachometer-alt me-2"></i>Seller Dashboard</a></li>
                                <?php elseif ($user_role === 'admin'): ?>
                                    <li><a class="dropdown-item" href="admin/dashboard.php"><i class="fas fa-cog me-2"></i>Admin Panel</a></li>
                                <?php else: ?>
                                    <li><a class="dropdown-item" href="myAccount.php"><i class="fas fa-user me-2"></i>My Account</a></li>
                                    <li><a class="dropdown-item" href="cart.php"><i class="fas fa-shopping-cart me-2"></i>Shopping Cart</a></li>
                                    <li><a class="dropdown-item" href="orders.php"><i class="fas fa-receipt me-2"></i>My Orders</a></li>
                                <?php endif; ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <a href="login.php" class="btn btn-outline-primary me-2">Login</a>
                        <a href="register.php" class="btn btn-primary">Register</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <div class="container">
        <!-- Alerts -->
        <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show mt-4" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show mt-4" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Page Header -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="mb-1">Products</h2>
                        <p class="text-muted mb-0">Discover amazing products from trusted sellers</p>
                    </div>
                    <div class="results-info">
                        Showing <?php echo count($products); ?> of <?php echo $total_products; ?> products
                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <!-- Filters Sidebar -->
            <div class="col-lg-3">
                <div class="filter-sidebar">
                    <h5 class="mb-3">Filters</h5>
                    
                    <!-- Search -->
                    <div class="mb-4">
                        <label class="form-label">Search Products</label>
                        <form method="GET" class="search-box">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="text" name="search" class="form-control" placeholder="Search..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="fas fa-search"></i>
                            </button>
                        </form>
                    </div>
                    
                    <!-- Categories -->
                    <div class="mb-4">
                        <label class="form-label">Categories</label>
                        <div class="list-group">
                            <a href="products.php" 
                               class="list-group-item list-group-item-action <?php echo empty($category_id) ? 'active' : ''; ?>">
                                All Categories
                            </a>
                            <?php foreach ($categories as $category): ?>
                                <a href="products.php?category=<?php echo $category['id']; ?>" 
                                   class="list-group-item list-group-item-action <?php echo $category_id == $category['id'] ? 'active' : ''; ?>">
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Price Range -->
                    <div class="mb-4">
                        <label class="form-label">Price Range</label>
                        <form method="GET" id="priceForm">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <?php if (!empty($category_id)): ?>
                                <input type="hidden" name="category" value="<?php echo $category_id; ?>">
                            <?php endif; ?>
                            <?php if (!empty($search)): ?>
                                <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                            <?php endif; ?>
                            <?php if (!empty($sort)): ?>
                                <input type="hidden" name="sort" value="<?php echo $sort; ?>">
                            <?php endif; ?>
                            
                            <div class="row g-2">
                                <div class="col-6">
                                    <input type="number" name="min_price" class="form-control" placeholder="Min" 
                                           value="<?php echo $min_price; ?>" step="0.01" min="0">
                                </div>
                                <div class="col-6">
                                    <input type="number" name="max_price" class="form-control" placeholder="Max" 
                                           value="<?php echo $max_price; ?>" step="0.01" min="0">
                                </div>
                            </div>
                            <button type="submit" class="btn btn-outline-primary btn-sm w-100 mt-2">Apply</button>
                            <?php if (!empty($min_price) || !empty($max_price)): ?>
                                <a href="products.php<?php echo $category_id ? '?category=' . $category_id : ''; ?>" 
                                   class="btn btn-outline-secondary btn-sm w-100 mt-1">Clear</a>
                            <?php endif; ?>
                        </form>
                    </div>
                    
                    <!-- Sorting -->
                    <div class="mb-3">
                        <label class="form-label">Sort By</label>
                        <form method="GET" id="sortForm">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <?php if (!empty($category_id)): ?>
                                <input type="hidden" name="category" value="<?php echo $category_id; ?>">
                            <?php endif; ?>
                            <?php if (!empty($search)): ?>
                                <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                            <?php endif; ?>
                            <?php if (!empty($min_price)): ?>
                                <input type="hidden" name="min_price" value="<?php echo $min_price; ?>">
                            <?php endif; ?>
                            <?php if (!empty($max_price)): ?>
                                <input type="hidden" name="max_price" value="<?php echo $max_price; ?>">
                            <?php endif; ?>
                            
                            <select name="sort" class="form-select" onchange="this.form.submit()">
                                <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                                <option value="price_low" <?php echo $sort === 'price_low' ? 'selected' : ''; ?>>Price: Low to High</option>
                                <option value="price_high" <?php echo $sort === 'price_high' ? 'selected' : ''; ?>>Price: High to Low</option>
                                <option value="popular" <?php echo $sort === 'popular' ? 'selected' : ''; ?>>Most Popular</option>
                                <option value="name" <?php echo $sort === 'name' ? 'selected' : ''; ?>>Name: A to Z</option>
                            </select>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Products Grid -->
            <div class="col-lg-9">
                <div class="products-container">
                    <?php if (!empty($products)): ?>
                        <div class="row g-4">
                            <?php foreach ($products as $product): ?>
                                <div class="col-xl-3 col-lg-4 col-md-6">
                                    <div class="card product-card h-100">
                                        <div class="product-image position-relative">
                                            <?php 
                                            // Check if product has gallery images
                                            $gallery_images = !empty($product['image_gallery']) ? json_decode($product['image_gallery'], true) : [];
                                            if (!empty($gallery_images) && is_array($gallery_images)): ?>
                                                <!-- Product Image Carousel -->
                                                <div id="productCarousel<?php echo $product['id']; ?>" class="carousel slide" data-bs-ride="carousel">
                                                    <div class="carousel-inner">
                                                        <?php if ($product['image_url']): ?>
                                                            <div class="carousel-item active">
                                                                <img src="<?php echo htmlspecialchars($product['image_url']); ?>" 
                                                                     class="d-block w-100" 
                                                                     alt="<?php echo htmlspecialchars($product['name']); ?>"
                                                                     style="height: 200px; object-fit: cover;">
                                                            </div>
                                                        <?php endif; ?>
                                                        <?php foreach ($gallery_images as $index => $gallery_image): ?>
                                                            <div class="carousel-item <?php echo (!$product['image_url'] && $index === 0) ? 'active' : ''; ?>">
                                                                <img src="<?php echo htmlspecialchars($gallery_image); ?>" 
                                                                     class="d-block w-100" 
                                                                     alt="<?php echo htmlspecialchars($product['name']); ?> Gallery Image"
                                                                     style="height: 200px; object-fit: cover;">
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                    <?php if ($product['image_url'] || !empty($gallery_images)): ?>
                                                        <button class="carousel-control-prev" type="button" data-bs-target="#productCarousel<?php echo $product['id']; ?>" data-bs-slide="prev">
                                                            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                                                            <span class="visually-hidden">Previous</span>
                                                        </button>
                                                        <button class="carousel-control-next" type="button" data-bs-target="#productCarousel<?php echo $product['id']; ?>" data-bs-slide="next">
                                                            <span class="carousel-control-next-icon" aria-hidden="true"></span>
                                                            <span class="visually-hidden">Next</span>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            <?php elseif ($product['image_url']): ?>
                                                <!-- Single Product Image -->
                                                <img src="<?php echo htmlspecialchars($product['image_url']); ?>" 
                                                     alt="<?php echo htmlspecialchars($product['name']); ?>"
                                                     style="width: 100%; height: 200px; object-fit: cover;">
                                            <?php else: ?>
                                                <!-- Placeholder -->
                                                <div class="d-flex align-items-center justify-content-center h-100">
                                                    <i class="fas fa-box fa-3x opacity-50"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="card-body d-flex flex-column">
                                            <div class="mb-2 d-flex flex-wrap gap-2">
                                                <span class="category-badge"><?php echo htmlspecialchars($product['category_name'] ?? 'General'); ?></span>
                                                <?php if ($product['product_type'] === 'rental'): ?>
                                                    <span class="badge bg-info">Rental</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Regular</span>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <h6 class="card-title"><?php echo htmlspecialchars($product['name']); ?></h6>
                                            <p class="card-text small text-muted flex-grow-1">
                                                <?php echo strlen($product['description']) > 80 ? 
                                                    substr(htmlspecialchars($product['description']), 0, 80) . '...' : 
                                                    htmlspecialchars($product['description']); ?>
                                            </p>
                                            
                                            <!-- Product Location -->
                                            <?php $location = getProductLocation($product); ?>
                                            <?php if (!empty($location)): ?>
                                                <div class="mb-2">
                                                    <small class="text-muted">
                                                        <i class="fas fa-map-marker-alt me-1"></i>
                                                        <?php echo htmlspecialchars($location); ?>
                                                    </small>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <div class="mt-auto">
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <?php if ($product['product_type'] === 'rental'): ?>
                                                        <div class="rental-pricing">
                                                            <div class="h6 text-primary mb-0">$<?php echo number_format($product['rental_price_per_day'], 2); ?>/day</div>
                                                            <?php if ($product['rental_price_per_week'] > 0): ?>
                                                                <small class="text-muted">$<?php echo number_format($product['rental_price_per_week'], 2); ?>/week</small>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="h5 text-primary mb-0"><?php echo formatCurrency($product['price']); ?></span>
                                                    <?php endif; ?>
                                                    <?php echo getStockBadge($product['stock']); ?>
                                                </div>
                                                
                                                <div class="d-flex justify-content-between align-items-center mb-3">
                                                    <small class="text-muted">Sold: <?php echo $product['units_sold'] ?? 0; ?></small>
                                                    <span class="seller-badge"><?php echo htmlspecialchars($product['store_name']); ?></span>
                                                </div>
                                                
                                                <?php if ($is_logged_in && $user_role === 'client'): ?>
                                                    <div class="d-grid gap-2">
                                                        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#inquiryModal" 
                                                                onclick="setInquiryProduct(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars($product['name'], ENT_QUOTES); ?>')">
                                                            <i class="fas fa-question-circle me-1"></i>Send Inquiry
                                                        </button>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="d-grid gap-2">
                                                        <?php if ($is_logged_in): ?>
                                                            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#inquiryModal" 
                                                                    onclick="setInquiryProduct(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars($product['name'], ENT_QUOTES); ?>')">
                                                                <i class="fas fa-question-circle me-1"></i>Send Inquiry
                                                            </button>
                                                        <?php else: ?>
                                                            <a href="login.php" class="btn btn-primary btn-sm">
                                                                <i class="fas fa-question-circle me-1"></i>Send Inquiry
                                                            </a>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <nav class="mt-5">
                                <ul class="pagination justify-content-center">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" 
                                               href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                                Previous
                                            </a>
                                        </li>
                                    <?php endif; ?>

                                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                            <a class="page-link" 
                                               href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>

                                    <?php if ($page < $total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link" 
                                               href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                                Next
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>

                    <?php else: ?>
                        <!-- Empty State -->
                        <div class="text-center py-5">
                            <i class="fas fa-search fa-3x text-muted mb-3"></i>
                            <h4 class="text-muted">No Products Found</h4>
                            <p class="text-muted mb-4">
                                <?php if (!empty($search) || !empty($category_id) || !empty($min_price) || !empty($max_price)): ?>
                                    Try adjusting your filters or search terms.
                                <?php else: ?>
                                    No products are currently available.
                                <?php endif; ?>
                            </p>
                            <a href="products.php" class="btn btn-primary">Clear Filters</a>
                        </div>
                    <?php endif; ?>
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
                        <li class="mb-2"><a href="index.php" class="text-decoration-none text-light">Home</a></li>
                        <li class="mb-2"><a href="products.php" class="text-decoration-none text-light">Products</a></li>
                        <li class="mb-2"><a href="categories.php" class="text-decoration-none text-light">Categories</a></li>
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
        // Quantity input validation
        document.querySelectorAll('.quantity-input').forEach(input => {
            input.addEventListener('change', function() {
                const max = parseInt(this.getAttribute('max'));
                const min = parseInt(this.getAttribute('min'));
                let value = parseInt(this.value);
                
                if (isNaN(value) || value < min) this.value = min;
                if (value > max) this.value = max;
            });
        });

        // Auto-dismiss alerts after 5 seconds
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            setTimeout(() => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }, 5000);
        });

        // Price range form submission
        document.getElementById('priceForm')?.addEventListener('submit', function(e) {
            const minPrice = parseFloat(this.querySelector('[name="min_price"]').value) || 0;
            const maxPrice = parseFloat(this.querySelector('[name="max_price"]').value) || 0;
            
            if (maxPrice > 0 && minPrice > maxPrice) {
                e.preventDefault();
                alert('Minimum price cannot be greater than maximum price.');
            }
        });
    </script>
    
    <!-- Inquiry Modal -->
    <div class="modal fade" id="inquiryModal" tabindex="-1" aria-labelledby="inquiryModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="inquiryModalLabel">Send Inquiry</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-3">Send an inquiry to the seller about: <strong id="inquiryProductName"></strong></p>
                    <form id="inquiryForm">
                        <input type="hidden" id="inquiryProductId" name="product_id">
                        <div class="mb-3">
                            <label for="inquiryMessage" class="form-label">Your Message</label>
                            <textarea class="form-control" id="inquiryMessage" name="message" rows="4" 
                                      placeholder="Ask about availability, pricing, delivery, or any other questions..." required></textarea>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane me-1"></i>Send Inquiry
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Success Modal -->
    <div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="successModalLabel">Inquiry Sent</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                    <p>Your inquiry has been sent to the seller successfully. They will respond shortly.</p>
                    <p>You can view and continue the conversation in your <a href="inquiries.php">Inquiries</a> section.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-success" data-bs-dismiss="modal">OK</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function setInquiryProduct(productId, productName) {
            document.getElementById('inquiryProductId').value = productId;
            document.getElementById('inquiryProductName').textContent = productName;
        }
        
        // Handle inquiry form submission
        document.getElementById('inquiryForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const productId = document.getElementById('inquiryProductId').value;
            const message = document.getElementById('inquiryMessage').value;
            
            if (!productId || !message.trim()) {
                alert('Please enter a message');
                return;
            }
            
            // Send inquiry via AJAX
            fetch('create_inquiry.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'product_id=' + encodeURIComponent(productId) + '&message=' + encodeURIComponent(message)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Close inquiry modal and show success modal
                    const inquiryModal = bootstrap.Modal.getInstance(document.getElementById('inquiryModal'));
                    inquiryModal.hide();
                    
                    const successModal = new bootstrap.Modal(document.getElementById('successModal'));
                    successModal.show();
                    
                    // Clear form
                    document.getElementById('inquiryForm').reset();
                } else {
                    alert('Failed to send inquiry: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to send inquiry. Please try again.');
            });
        });
    </script>
</body>
</html>