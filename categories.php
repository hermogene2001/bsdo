<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];
$error_message = '';
$success_message = '';

// Get all categories with product counts
$categories_stmt = $pdo->prepare("
    SELECT 
        c.*,
        COUNT(p.id) as product_count,
        COALESCE(AVG(p.price), 0) as avg_price,
        MIN(p.price) as min_price,
        MAX(p.price) as max_price
    FROM categories c
    LEFT JOIN products p ON c.id = p.category_id AND p.status = 'active'
    WHERE c.status = 'active'
    GROUP BY c.id
    ORDER BY c.name
");
$categories_stmt->execute();
$categories = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get featured products from each category (top 4 by created_at)
// Get featured products from each category (top 4 by created_at)
$featured_products_stmt = $pdo->prepare("
    SELECT * 
    FROM (
        SELECT 
            p.*,
            c.name AS category_name,
            u.store_name,
            c.id AS cat_id,   -- fixed alias to avoid duplicate
            ROW_NUMBER() OVER (PARTITION BY p.category_id ORDER BY p.created_at DESC) AS row_num
        FROM products p
        JOIN categories c ON p.category_id = c.id
        JOIN users u ON p.seller_id = u.id
        WHERE p.status = 'active'
    ) sub
    WHERE row_num <= 4
    ORDER BY category_name, created_at DESC
");
$featured_products_stmt->execute();
$featured_products = $featured_products_stmt->fetchAll(PDO::FETCH_ASSOC);


// Group featured products by category
$products_by_category = [];
foreach ($featured_products as $product) {
    $category_id = $product['category_id'];
    if (!isset($products_by_category[$category_id])) {
        $products_by_category[$category_id] = [];
    }
    $products_by_category[$category_id][] = $product;
}

// Get category statistics
$stats_stmt = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT c.id) as total_categories,
        COUNT(DISTINCT p.id) as total_products,
        COUNT(DISTINCT u.id) as total_sellers,
        COALESCE(AVG(p.price), 0) as avg_product_price
    FROM categories c
    LEFT JOIN products p ON c.id = p.category_id AND p.status = 'active'
    LEFT JOIN users u ON p.seller_id = u.id AND u.status = 'active'
    WHERE c.status = 'active'
");
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Handle add to cart action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user_role === 'client') {
    if (isset($_POST['add_to_cart'])) {
        $product_id = intval($_POST['product_id']);
        $quantity = isset($_POST['quantity']) ? max(1, intval($_POST['quantity'])) : 1;
        
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
                $success_message = "Product added to cart successfully!";
            } else {
                $error_message = "Not enough stock available. Only " . $product['stock'] . " items left.";
            }
        } else {
            $error_message = "Product not found or unavailable.";
        }
    }
}

function formatCurrency($amount) {
    return '$' . number_format($amount, 2);
}

function getCategoryIcon($categoryName) {
    $icons = [
        'Electronics' => 'mobile-alt',
        'Clothing' => 'tshirt',
        'Home & Garden' => 'home',
        'Sports' => 'basketball-ball',
        'Books' => 'book',
        'Beauty' => 'spa',
        'Toys' => 'gamepad',
        'Automotive' => 'car',
        'Health' => 'heartbeat',
        'Jewelry' => 'gem'
    ];
    
    foreach ($icons as $category => $icon) {
        if (stripos($categoryName, $category) !== false) {
            return $icon;
        }
    }
    
    return 'shopping-bag';
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categories - BSDO Sale</title>
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
        
        .categories-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            padding: 2rem;
            margin-top: 2rem;
        }
        
        .category-card {
            border: none;
            border-radius: 15px;
            overflow: hidden;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            height: 100%;
        }
        
        .category-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
        }
        
        .category-header {
            background: linear-gradient(135deg, var(--primary-color), #667eea);
            color: white;
            padding: 2rem;
            text-align: center;
            border-radius: 15px 15px 0 0;
        }
        
        .category-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.9;
        }
        
        .product-card {
            border: 1px solid #e3e6f0;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }
        
        .product-card:hover {
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        
        .product-image {
            height: 120px;
            background: linear-gradient(135deg, #f8f9fc, #e9ecef);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--dark-color);
            margin-bottom: 1rem;
        }
        
        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 8px;
        }
        
        .stats-card {
            background: linear-gradient(135deg, var(--primary-color), #667eea);
            color: white;
            border-radius: 10px;
            padding: 1.5rem;
            text-align: center;
            margin-bottom: 1.5rem;
        }
        
        .price-range {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .category-badge {
            background: var(--secondary-color);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .seller-badge {
            background: #6c757d;
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.7rem;
        }
        
        .quantity-input {
            width: 70px;
            text-align: center;
        }
        
        .empty-category {
            text-align: center;
            padding: 2rem;
            color: #6c757d;
        }
        
        .empty-category-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
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
                    <li class="nav-item"><a class="nav-link" href="products.php">Products</a></li>
                    <li class="nav-item"><a class="nav-link active" href="categories.php">Categories</a></li>
                    <?php if ($user_role === 'client'): ?>
                        <li class="nav-item"><a class="nav-link" href="cart.php">Cart</a></li>
                        <li class="nav-item"><a class="nav-link" href="orders.php">Orders</a></li>
                    <?php endif; ?>
                </ul>
                
                <div class="d-flex align-items-center">
                    <div class="dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown">
                            <div class="user-avatar me-2">
                                <?php echo strtoupper(substr($_SESSION['user_name'], 0, 1)); ?>
                            </div>
                            <span><?php echo htmlspecialchars(explode(' ', $_SESSION['user_name'])[0]); ?></span>
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
                                <li><a class="dropdown-item" href="client/dashboard.php"><i class="fas fa-user me-2"></i>My Account</a></li>
                                <li><a class="dropdown-item" href="cart.php"><i class="fas fa-shopping-cart me-2"></i>Shopping Cart</a></li>
                                <li><a class="dropdown-item" href="orders.php"><i class="fas fa-receipt me-2"></i>My Orders</a></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </div>
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
                        <h2 class="mb-1">Product Categories</h2>
                        <p class="text-muted mb-0">Browse products by category</p>
                    </div>
                    <div class="text-muted">
                        <?php echo $stats['total_categories']; ?> Categories • 
                        <?php echo $stats['total_products']; ?> Products
                    </div>
                </div>
            </div>
        </div>

        <!-- Category Statistics -->
        <div class="row mt-4">
            <div class="col-md-3 mb-3">
                <div class="stats-card">
                    <div class="h4 mb-1"><?php echo $stats['total_categories']; ?></div>
                    <div class="small">Categories</div>
                </div>
            </div>
            
            <div class="col-md-3 mb-3">
                <div class="stats-card" style="background: linear-gradient(135deg, var(--secondary-color), #18a873);">
                    <div class="h4 mb-1"><?php echo $stats['total_products']; ?></div>
                    <div class="small">Total Products</div>
                </div>
            </div>
            
            <div class="col-md-3 mb-3">
                <div class="stats-card" style="background: linear-gradient(135deg, #f6c23e, #e0a800);">
                    <div class="h4 mb-1"><?php echo $stats['total_sellers']; ?></div>
                    <div class="small">Active Sellers</div>
                </div>
            </div>
            
            <div class="col-md-3 mb-3">
                <div class="stats-card" style="background: linear-gradient(135deg, #36b9cc, #2c9faf);">
                    <div class="h4 mb-1"><?php echo formatCurrency($stats['avg_product_price']); ?></div>
                    <div class="small">Avg. Price</div>
                </div>
            </div>
        </div>

        <!-- Categories Grid -->
        <div class="categories-container">
            <?php if (!empty($categories)): ?>
                <div class="row g-4">
                    <?php foreach ($categories as $category): ?>
                        <div class="col-12">
                            <div class="category-card">
                                <!-- Category Header -->
                                <div class="category-header">
                                    <div class="category-icon">
                                        <i class="fas fa-<?php echo getCategoryIcon($category['name']); ?>"></i>
                                    </div>
                                    <h4 class="mb-2"><?php echo htmlspecialchars($category['name']); ?></h4>
                                    <p class="mb-0 opacity-75">
                                        <?php echo $category['product_count']; ?> Products • 
                                        Price Range: <?php echo formatCurrency($category['min_price']); ?> - <?php echo formatCurrency($category['max_price']); ?>
                                    </p>
                                    <?php if (!empty($category['description'])): ?>
                                        <p class="mt-2 mb-0 opacity-75">
                                            <?php echo htmlspecialchars($category['description']); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Category Products -->
                                <div class="p-4">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h5 class="mb-0">Featured Products</h5>
                                        <a href="products.php?category=<?php echo $category['id']; ?>" class="btn btn-primary btn-sm">
                                            View All <?php echo $category['product_count']; ?> Products
                                            <i class="fas fa-arrow-right ms-1"></i>
                                        </a>
                                    </div>
                                    
                                    <?php if (isset($products_by_category[$category['id']]) && !empty($products_by_category[$category['id']])): ?>
                                        <div class="row g-3">
                                            <?php foreach ($products_by_category[$category['id']] as $product): ?>
                                                <div class="col-xl-3 col-lg-4 col-md-6">
                                                    <div class="product-card">
                                                        <div class="product-image">
                                                            <?php if ($product['image_url']): ?>
                                                                <img src="<?php echo htmlspecialchars($product['image_url']); ?>" 
                                                                     alt="<?php echo htmlspecialchars($product['name']); ?>">
                                                            <?php else: ?>
                                                                <i class="fas fa-box fa-2x opacity-50"></i>
                                                            <?php endif; ?>
                                                        </div>
                                                        
                                                        <h6 class="mb-1"><?php echo htmlspecialchars($product['name']); ?></h6>
                                                        
                                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                                            <span class="h6 text-primary mb-0"><?php echo formatCurrency($product['price']); ?></span>
                                                            <?php echo getStockBadge($product['stock']); ?>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <span class="seller-badge"><?php echo htmlspecialchars($product['store_name']); ?></span>
                                                        </div>
                                                        
                                                        <?php if ($user_role === 'client'): ?>
                                                            <form method="POST" class="d-grid gap-2">
                                                                <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                                                <div class="d-flex align-items-center gap-2">
                                                                    <label class="small text-muted">Qty:</label>
                                                                    <input type="number" name="quantity" value="1" min="1" 
                                                                           max="<?php echo min($product['stock'], 10); ?>" 
                                                                           class="form-control form-control-sm quantity-input">
                                                                </div>
                                                                <button type="submit" name="add_to_cart" class="btn btn-primary btn-sm">
                                                                    <i class="fas fa-cart-plus me-1"></i>Add to Cart
                                                                </button>
                                                            </form>
                                                        <?php else: ?>
                                                            <button class="btn btn-outline-primary btn-sm w-100" disabled>
                                                                <i class="fas fa-eye me-1"></i>View Product
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <!-- Empty Category State -->
                                        <div class="empty-category">
                                            <div class="empty-category-icon">
                                                <i class="fas fa-box-open"></i>
                                            </div>
                                            <h5 class="text-muted">No Products Available</h5>
                                            <p class="text-muted">Check back soon for products in this category</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <!-- No Categories State -->
                <div class="text-center py-5">
                    <i class="fas fa-tags fa-3x text-muted mb-3"></i>
                    <h4 class="text-muted">No Categories Available</h4>
                    <p class="text-muted mb-4">Product categories will appear here once they are added to the system.</p>
                    <?php if ($user_role === 'admin'): ?>
                        <a href="admin/categories.php" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>Manage Categories
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Quick Navigation -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="categories-container text-center">
                    <h5 class="mb-3">Can't find what you're looking for?</h5>
                    <div class="d-flex gap-3 justify-content-center flex-wrap">
                        <a href="products.php" class="btn btn-outline-primary">
                            <i class="fas fa-search me-2"></i>Browse All Products
                        </a>
                        <a href="index.php" class="btn btn-outline-secondary">
                            <i class="fas fa-home me-2"></i>Back to Home
                        </a>
                        <?php if ($user_role === 'client' && isset($_SESSION['cart']) && !empty($_SESSION['cart'])): ?>
                            <a href="cart.php" class="btn btn-primary">
                                <i class="fas fa-shopping-cart me-2"></i>View Cart (<?php echo count($_SESSION['cart']); ?>)
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

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

        // Smooth scrolling for category navigation
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    </script>
</body>
</html>