<?php
session_start();
require_once 'config.php';

// Check if user is logged in and is a client
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'client') {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$error_message = '';
$success_message = '';

// Initialize cart if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// -------------------- CART ACTIONS --------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    switch ($action) {
        case 'update_quantity':
            $product_id = intval($_POST['product_id']);
            $quantity   = max(0, intval($_POST['quantity']));

            if (isset($_SESSION['cart'][$product_id])) {
                if ($quantity > 0) {
                    $_SESSION['cart'][$product_id]['quantity'] = $quantity;
                    $success_message = "Cart updated successfully!";
                } else {
                    unset($_SESSION['cart'][$product_id]);
                    $success_message = "Item removed from cart!";
                }
            }
            break;

        case 'remove_item':
            $product_id = intval($_POST['product_id']);
            unset($_SESSION['cart'][$product_id]);
            $success_message = "Item removed from cart!";
            break;

        case 'clear_cart':
            $_SESSION['cart'] = [];
            $success_message = "Cart cleared successfully!";
            break;

        case 'checkout':
            if (!empty($_SESSION['cart'])) {
                $order_number = 'ORD' . date('Ymd') . str_pad(rand(0, 999), 3, '0', STR_PAD_LEFT);
                $total_amount = calculateCartTotal();

                try {
                    $pdo->beginTransaction();

                    // Create order
                    $order_stmt = $pdo->prepare("
                        INSERT INTO orders (user_id, order_number, total_amount, status) 
                        VALUES (?, ?, ?, 'pending')
                    ");
                    $order_stmt->execute([$user_id, $order_number, $total_amount]);
                    $order_id = $pdo->lastInsertId();

                    // Add order items
                    foreach ($_SESSION['cart'] as $product_id => $item) {
                        $quantity = $item['quantity'] ?? 0;
                        $price    = $item['price'] ?? 0;

                        $item_stmt = $pdo->prepare("
                            INSERT INTO order_items (order_id, product_id, quantity, price) 
                            VALUES (?, ?, ?, ?)
                        ");
                        $item_stmt->execute([$order_id, $product_id, $quantity, $price]);

                        // Update product stock
                        $stock_stmt = $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
                        $stock_stmt->execute([$quantity, $product_id]);
                    }

                    $pdo->commit();
                    $_SESSION['cart'] = [];
                    $success_message = "Order placed successfully! Order #: " . $order_number;
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error_message = "Checkout failed: " . $e->getMessage();
                }
            } else {
                $error_message = "Your cart is empty!";
            }
            break;
    }
}

// -------------------- FUNCTIONS --------------------
function calculateCartTotal() {
    $total = 0;
    if (isset($_SESSION['cart'])) {
        foreach ($_SESSION['cart'] as $item) {
            $price    = $item['price'] ?? 0;
            $quantity = $item['quantity'] ?? 0;
            $total += $price * $quantity;
        }
    }
    return $total;
}

// -------------------- LOAD CART ITEMS --------------------
$cart_items = [];
if (!empty($_SESSION['cart'])) {
    $product_ids = array_keys($_SESSION['cart']);
    $placeholders = str_repeat('?,', count($product_ids) - 1) . '?';

    $products_stmt = $pdo->prepare("
        SELECT p.*, u.store_name, c.name AS category_name 
        FROM products p
        JOIN users u ON p.seller_id = u.id
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.id IN ($placeholders) AND p.status = 'active'
    ");
    $products_stmt->execute($product_ids);
    $products = $products_stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($products as $product) {
        $cart_items[] = [
            'product'    => $product,
            'quantity'   => $_SESSION['cart'][$product['id']]['quantity'] ?? 0,
            'item_total' => ($_SESSION['cart'][$product['id']]['price'] ?? $product['price']) 
                          * ($_SESSION['cart'][$product['id']]['quantity'] ?? 0)
        ];
        // Sync missing price into session
        if (!isset($_SESSION['cart'][$product['id']]['price'])) {
            $_SESSION['cart'][$product['id']]['price'] = $product['price'];
        }
    }
}

// -------------------- USER INFO --------------------
$user_stmt = $pdo->prepare("SELECT first_name, last_name, email, phone FROM users WHERE id = ?");
$user_stmt->execute([$user_id]);
$user_info = $user_stmt->fetch(PDO::FETCH_ASSOC);

$cart_total = calculateCartTotal();
$item_count = count($_SESSION['cart']);
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart - BSDO Sale</title>
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
        
        .cart-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            padding: 2rem;
            margin-top: 2rem;
        }
        
        .cart-item {
            border: 1px solid #e3e6f0;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }
        
        .cart-item:hover {
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        
        .product-image {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 10px;
            background: linear-gradient(135deg, #f8f9fc, #e9ecef);
        }
        
        .quantity-input {
            width: 80px;
            text-align: center;
        }
        
        .summary-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 2rem;
        }
        
        .empty-cart {
            text-align: center;
            padding: 4rem 2rem;
        }
        
        .empty-cart-icon {
            font-size: 4rem;
            color: #6c757d;
            margin-bottom: 1rem;
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
                    <li class="nav-item"><a class="nav-link active" href="cart.php">Cart</a></li>
                </ul>
                
                <div class="d-flex align-items-center">
                    <div class="dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown">
                            <div class="user-avatar me-2">
                                <?php echo strtoupper(substr($_SESSION['user_name'], 0, 1)); ?>
                            </div>
                            <span class="me-2"><?php echo htmlspecialchars(explode(' ', $_SESSION['user_name'])[0]); ?></span>
                            <?php if ($item_count > 0): ?>
                                <span class="position-relative">
                                    <i class="fas fa-shopping-cart"></i>
                                    <span class="cart-badge"><?php echo $item_count; ?></span>
                                </span>
                            <?php else: ?>
                                <i class="fas fa-shopping-cart"></i>
                            <?php endif; ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="client/dashboard.php"><i class="fas fa-user me-2"></i>My Account</a></li>
                            <li><a class="dropdown-item" href="cart.php"><i class="fas fa-shopping-cart me-2"></i>Shopping Cart</a></li>
                            <li><a class="dropdown-item" href="orders.php"><i class="fas fa-receipt me-2"></i>My Orders</a></li>
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

        <div class="row">
            <div class="col-lg-8">
                <div class="cart-container">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2 class="mb-0">Shopping Cart</h2>
                        <span class="text-muted"><?php echo $item_count; ?> item(s)</span>
                    </div>
                    
                    <?php if (!empty($cart_items)): ?>
                        <!-- Cart Items -->
                        <?php foreach ($cart_items as $cart_item): ?>
                            <?php $product = $cart_item['product']; ?>
                            <div class="cart-item">
                                <div class="row align-items-center">
                                    <div class="col-md-2">
                                        <div class="product-image d-flex align-items-center justify-content-center">
                                            <?php if ($product['image_url']): ?>
                                                <img src="<?php echo htmlspecialchars($product['image_url']); ?>" 
                                                     alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                                     class="w-100 h-100" style="object-fit: cover;">
                                            <?php else: ?>
                                                <i class="fas fa-box text-muted"></i>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($product['name']); ?></h6>
                                        <p class="text-muted small mb-1"><?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?></p>
                                        <p class="text-muted small mb-0">Sold by: <?php echo htmlspecialchars($product['store_name']); ?></p>
                                    </div>
                                    
                                    <div class="col-md-2">
                                        <span class="h6 text-primary">$<?php echo number_format($product['price'], 2); ?></span>
                                    </div>
                                    
                                    <div class="col-md-2">
                                        <form method="POST" class="d-flex align-items-center">
                                            <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                            <input type="number" name="quantity" value="<?php echo $cart_item['quantity']; ?>" 
                                                   min="1" max="10" class="form-control quantity-input" 
                                                   onchange="this.form.submit()">
                                            <input type="hidden" name="action" value="update_quantity">
                                        </form>
                                    </div>
                                    
                                    <div class="col-md-2 text-end">
                                        <span class="h6 me-3">$<?php echo number_format($cart_item['item_total'], 2); ?></span>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                            <input type="hidden" name="action" value="remove_item">
                                            <button type="submit" class="btn btn-outline-danger btn-sm">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <!-- Clear Cart Button -->
                        <div class="text-end mt-3">
                            <form method="POST">
                                <input type="hidden" name="action" value="clear_cart">
                                <button type="submit" class="btn btn-outline-danger">
                                    <i class="fas fa-trash me-2"></i>Clear Cart
                                </button>
                            </form>
                        </div>
                        
                    <?php else: ?>
                        <!-- Empty Cart -->
                        <div class="empty-cart">
                            <div class="empty-cart-icon">
                                <i class="fas fa-shopping-cart"></i>
                            </div>
                            <h3 class="text-muted">Your cart is empty</h3>
                            <p class="text-muted mb-4">Start shopping to add items to your cart</p>
                            <a href="index.php" class="btn btn-primary btn-lg">
                                <i class="fas fa-shopping-bag me-2"></i>Start Shopping
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Order Summary -->
            <div class="col-lg-4">
                <div class="summary-card">
                    <h4 class="text-white mb-4">Order Summary</h4>
                    
                    <div class="d-flex justify-content-between mb-2">
                        <span>Items (<?php echo $item_count; ?>):</span>
                        <span>$<?php echo number_format($cart_total, 2); ?></span>
                    </div>
                    
                    <div class="d-flex justify-content-between mb-2">
                        <span>Shipping:</span>
                        <span><?php echo $cart_total > 0 ? '$5.00' : 'FREE'; ?></span>
                    </div>
                    
                    <div class="d-flex justify-content-between mb-2">
                        <span>Tax:</span>
                        <span>$<?php echo number_format($cart_total * 0.08, 2); ?></span>
                    </div>
                    
                    <hr class="my-3" style="border-color: rgba(255,255,255,0.3);">
                    
                    <div class="d-flex justify-content-between mb-4">
                        <strong class="h5">Total:</strong>
                        <strong class="h5">$<?php echo number_format($cart_total + ($cart_total > 0 ? 5 : 0) + ($cart_total * 0.08), 2); ?></strong>
                    </div>
                    
                    <?php if (!empty($cart_items)): ?>
                        <form method="POST">
                            <input type="hidden" name="action" value="checkout">
                            <button type="submit" class="btn btn-light btn-lg w-100">
                                <i class="fas fa-lock me-2"></i>Proceed to Checkout
                            </button>
                        </form>
                    <?php else: ?>
                        <button class="btn btn-light btn-lg w-100" disabled>
                            <i class="fas fa-lock me-2"></i>Proceed to Checkout
                        </button>
                    <?php endif; ?>
                    
                    <div class="text-center mt-3">
                        <small class="opacity-75">
                            <i class="fas fa-lock me-1"></i>Secure checkout guaranteed
                        </small>
                    </div>
                </div>
                
                <!-- Continue Shopping -->
                <div class="text-center mt-3">
                    <a href="index.php" class="btn btn-outline-primary w-100">
                        <i class="fas fa-arrow-left me-2"></i>Continue Shopping
                    </a>
                </div>
                
                <!-- Trust Badges -->
                <div class="text-center mt-4">
                    <div class="row">
                        <div class="col-4">
                            <i class="fas fa-shield-alt fa-2x text-primary mb-2"></i>
                            <p class="small mb-0">Secure</p>
                        </div>
                        <div class="col-4">
                            <i class="fas fa-truck fa-2x text-primary mb-2"></i>
                            <p class="small mb-0">Fast Delivery</p>
                        </div>
                        <div class="col-4">
                            <i class="fas fa-undo fa-2x text-primary mb-2"></i>
                            <p class="small mb-0">Easy Returns</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recently Viewed (Optional) -->
        <?php if (!empty($cart_items)): ?>
        <div class="row mt-5">
            <div class="col-12">
                <div class="cart-container">
                    <h4 class="mb-4">You Might Also Like</h4>
                    <div class="row">
                        <!-- Add recommended products here -->
                        <div class="col-12 text-center py-4">
                            <p class="text-muted">Recommended products will appear here based on your cart items.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <footer class="bg-dark text-white py-5 mt-4">
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
        // Quantity input validation
        document.querySelectorAll('.quantity-input').forEach(input => {
            input.addEventListener('change', function() {
                if (this.value < 1) this.value = 1;
                if (this.value > 10) this.value = 10;
            });
        });

        // Confirm clear cart
        document.querySelector('form[action="clear_cart"]')?.addEventListener('submit', function(e) {
            if (!confirm('Are you sure you want to clear your entire cart?')) {
                e.preventDefault();
            }
        });

        // Confirm remove item
        document.querySelectorAll('form[action="remove_item"]').forEach(form => {
            form.addEventListener('submit', function(e) {
                if (!confirm('Are you sure you want to remove this item from your cart?')) {
                    e.preventDefault();
                }
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
    </script>
</body>
</html>