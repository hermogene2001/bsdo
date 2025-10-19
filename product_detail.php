<?php
session_start();
require_once 'config.php';

// Check if product ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: products.php');
    exit();
}

$product_id = intval($_GET['id']);

// Get product details
$product_stmt = $pdo->prepare("SELECT p.*, u.store_name, c.name as category_name FROM products p JOIN users u ON p.seller_id = u.id LEFT JOIN categories c ON p.category_id = c.id WHERE p.id = ? AND p.status = 'active'");
$product_stmt->execute([$product_id]);
$product = $product_stmt->fetch(PDO::FETCH_ASSOC);

// If product not found, redirect
if (!$product) {
    header('Location: products.php');
    exit();
}

// Get gallery images
$gallery_images = [];
if (!empty($product['image_gallery'])) {
    $gallery_images = json_decode($product['image_gallery'], true);
    if (!is_array($gallery_images)) {
        $gallery_images = [];
    }
}

// User session data
$is_logged_in = isset($_SESSION['user_id']);
$user_role = $_SESSION['user_role'] ?? '';
$user_id = $_SESSION['user_id'] ?? null;

// Handle add to cart action
$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user_role === 'client') {
    if (isset($_POST['add_to_cart'])) {
        $quantity = isset($_POST['quantity']) ? max(1, intval($_POST['quantity'])) : 1;
        
        // Initialize cart if not exists
        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }
        
        // Check stock
        if ($quantity <= $product['stock']) {
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
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($product['name']) ?> - BSDO Sale</title>
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
        
        .product-image {
            height: 400px;
            overflow: hidden;
            border-radius: 15px;
            background: linear-gradient(135deg, #f8f9fc, #e9ecef);
        }
        
        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .thumbnail {
            cursor: pointer;
            border: 2px solid transparent;
            border-radius: 10px;
            transition: all 0.3s;
        }
        
        .thumbnail:hover, .thumbnail.active {
            border-color: var(--primary-color);
        }
        
        .product-card {
            border: none;
            border-radius: 15px;
            overflow: hidden;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
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
        
        .seller-info {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
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
                    <?php if ($user_role === 'client'): ?>
                        <li class="nav-item"><a class="nav-link" href="cart.php">Cart</a></li>
                        <li class="nav-item"><a class="nav-link" href="orders.php">Orders</a></li>
                    <?php endif; ?>
                </ul>
                
                <div class="d-flex align-items-center">
                    <div class="dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown">
                            <div class="user-avatar me-2">
                                <?php echo strtoupper(substr($_SESSION['user_name'] ?? '', 0, 1)); ?>
                            </div>
                            <span><?php echo htmlspecialchars(explode(' ', $_SESSION['user_name'] ?? '')[0]); ?></span>
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
                </div>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Alerts -->
        <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Product Images -->
            <div class="col-lg-6">
                <div class="card product-card mb-4">
                    <div class="position-relative">
                        <?php if ($product['image_url']): ?>
                            <img src="<?= htmlspecialchars($product['image_url']) ?>" 
                                 alt="<?= htmlspecialchars($product['name']) ?>"
                                 class="product-image w-100">
                            <span class="product-badge <?= $product['product_type'] === 'rental' ? 'badge-rental' : 'badge-regular'; ?>">
                                <?= strtoupper($product['product_type'] ?? 'REGULAR'); ?>
                            </span>
                        <?php else: ?>
                            <div class="product-image d-flex align-items-center justify-content-center">
                                <i class="fas fa-box fa-5x opacity-50"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Thumbnails -->
                    <div class="row mt-3 g-2">
                        <?php if ($product['image_url']): ?>
                            <div class="col-3">
                                <img src="<?= htmlspecialchars($product['image_url']) ?>" 
                                     alt="Main image"
                                     class="img-fluid rounded thumbnail active"
                                     onclick="changeMainImage(this)">
                            </div>
                        <?php endif; ?>
                        
                        <?php foreach ($gallery_images as $index => $gallery_image): ?>
                            <div class="col-3">
                                <img src="<?= htmlspecialchars($gallery_image) ?>" 
                                     alt="Gallery image <?= $index + 1 ?>"
                                     class="img-fluid rounded thumbnail"
                                     onclick="changeMainImage(this)">
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Product Info -->
            <div class="col-lg-6">
                <div class="card product-card">
                    <div class="card-body">
                        <h1 class="card-title mb-3"><?= htmlspecialchars($product['name']) ?></h1>
                        
                        <p class="text-muted mb-4">by <strong><?= htmlspecialchars($product['store_name']) ?></strong></p>
                        
                        <?php if ($product['product_type'] === 'rental'): ?>
                            <div class="rental-price mb-4">
                                <div class="h3 text-primary mb-1">
                                    $<?= number_format($product['rental_price_per_day'], 2) ?>/day
                                </div>
                                <?php if ($product['rental_price_per_week'] > 0): ?>
                                    <small class="text-muted">
                                        $<?= number_format($product['rental_price_per_week'], 2) ?>/week
                                    </small>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="h2 text-primary mb-4">
                                $<?= number_format($product['price'], 2) ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="mb-4">
                            <span class="badge bg-success">Stock: <?= $product['stock'] ?></span>
                            <span class="badge bg-secondary ms-2">
                                Category: <?= htmlspecialchars($product['category_name'] ?? 'Uncategorized') ?>
                            </span>
                        </div>
                        
                        <div class="mb-4">
                            <h5>Description</h5>
                            <p><?= htmlspecialchars($product['description']) ?></p>
                        </div>
                        
                        <!-- Rental Specific Information -->
                        <?php if ($product['product_type'] === 'rental'): ?>
                            <div class="mb-4">
                                <h5>Rental Details</h5>
                                <div class="row">
                                    <div class="col-md-6">
                                        <p><strong>Min Rental:</strong> <?= $product['min_rental_days'] ?> days</p>
                                        <p><strong>Max Rental:</strong> <?= $product['max_rental_days'] ?> days</p>
                                    </div>
                                    <div class="col-md-6">
                                        <p><strong>Security Deposit:</strong> $<?= number_format($product['security_deposit'], 2) ?></p>
                                        <?php if (!empty($product['address'])): ?>
                                            <p><strong>Location:</strong> <?= htmlspecialchars($product['address']) ?>, <?= htmlspecialchars($product['city']) ?>, <?= htmlspecialchars($product['state']) ?>, <?= htmlspecialchars($product['country']) ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($is_logged_in && $user_role === 'client'): ?>
                            <?php if ($product['product_type'] === 'rental'): ?>
                                <div class="d-grid gap-2 mb-3">
                                    <button class="btn btn-rental btn-lg" onclick="alert('Rental booking feature coming soon!')">
                                        <i class="fas fa-calendar-plus me-1"></i>Book Rental
                                    </button>
                                    <button class="btn btn-outline-primary btn-lg" data-bs-toggle="modal" data-bs-target="#inquiryModal" 
                                            onclick="setInquiryProduct(<?= $product['id'] ?>, '<?= htmlspecialchars($product['name'], ENT_QUOTES) ?>')">
                                        <i class="fas fa-question-circle me-1"></i>Ask About Rental
                                    </button>
                                </div>
                            <?php else: ?>
                                <form method="POST" class="mb-3">
                                    <div class="d-flex align-items-center gap-2 mb-3">
                                        <label class="small text-muted">Quantity:</label>
                                        <input type="number" name="quantity" value="1" min="1" 
                                               max="<?= min($product['stock'], 10) ?>" 
                                               class="form-control form-control-sm" style="width: 100px;">
                                    </div>
                                    <button type="submit" name="add_to_cart" class="btn btn-regular btn-lg w-100">
                                        <i class="fas fa-cart-plus me-1"></i>Add to Cart
                                    </button>
                                </form>
                            <?php endif; ?>
                        <?php elseif (!$is_logged_in): ?>
                            <a href="#" class="btn btn-primary btn-lg w-100" data-bs-toggle="modal" data-bs-target="#loginModal">
                                <i class="fas fa-lock me-1"></i>Login to Purchase
                            </a>
                        <?php else: ?>
                            <button class="btn btn-outline-primary btn-lg w-100" disabled>
                                <i class="fas fa-eye me-1"></i>View Only
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Seller Info -->
                <div class="card seller-info mt-4">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <div class="user-avatar me-3">
                                <?= strtoupper(substr($product['store_name'], 0, 1)) ?>
                            </div>
                            <div>
                                <h5 class="mb-0">Seller: <?= htmlspecialchars($product['store_name']) ?></h5>
                                <small class="text-muted">Joined <?= date('M Y', strtotime($product['created_at'])) ?></small>
                            </div>
                        </div>
                        <p class="mb-0">This seller has been verified and offers high-quality products.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Inquiry Modal -->
    <?php if ($is_logged_in && $user_role === 'client'): ?>
    <div class="modal fade" id="inquiryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Send Inquiry to Seller</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">Product: <span id="inquiryProductName"></span></div>
                    <form method="POST">
                        <input type="hidden" id="inquiryProductId" name="product_id">
                        <div class="mb-3">
                            <label class="form-label">Your Message</label>
                            <textarea class="form-control" name="inquiry_message" rows="4" placeholder="Ask about product details, pricing, availability..." required></textarea>
                        </div>
                        <div class="d-grid">
                            <button type="submit" name="send_inquiry" class="btn btn-primary"><i class="fas fa-paper-plane me-2"></i>Send Inquiry</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Login Modal -->
    <div class="modal fade" id="loginModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content auth-form">
                <div class="modal-header border-0">
                    <h5 class="modal-title">Login to Your Account</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Please login to purchase this product.</p>
                    <a href="login.php" class="btn btn-primary w-100">Login</a>
                    <p class="mt-3 text-center">Don't have an account? <a href="register.php">Register now</a></p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function changeMainImage(element) {
            const mainImage = document.querySelector('.product-image img');
            if (mainImage) {
                mainImage.src = element.src;
            }
            
            // Update active class
            document.querySelectorAll('.thumbnail').forEach(img => {
                img.classList.remove('active');
            });
            element.classList.add('active');
        }
        
        function setInquiryProduct(productId, productName) {
            document.getElementById('inquiryProductId').value = productId;
            document.getElementById('inquiryProductName').textContent = productName;
        }
        
        // Auto-dismiss alerts after 5 seconds
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            setTimeout(() => {
                const bsAlert = bootstrap.Alert.getInstance(alert);
                if (bsAlert) bsAlert.close();
            }, 5000);
        });
    </script>
</body>
</html>