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
            --regular-color: #4e73df;
            --rental-color: #f6c23e;
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
        
        /* Inquiry Modal Styles */
        .modal-header.bg-primary {
            background: linear-gradient(135deg, var(--primary-color), #667eea) !important;
        }
        
        .modal-header.bg-success {
            background: linear-gradient(135deg, #28a745, #20c997) !important;
        }
        
        #inquiryMessage {
            resize: vertical;
            min-height: 120px;
        }
        
        .cart-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: var(--primary-color);
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 0.7rem;
            display: flex;
            align-items: center;
            justify-content: center;
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
                            <div class="d-grid gap-2 mb-3">
                                <button class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#inquiryModal" 
                                        onclick="setInquiryProduct(<?= $product['id'] ?>, '<?= htmlspecialchars($product['name'], ENT_QUOTES) ?>')">
                                    <i class="fas fa-question-circle me-1"></i>Send Inquiry
                                </button>
                            </div>
                        <?php elseif (!$is_logged_in): ?>
                            <div class="d-grid gap-2">
                                <a href="login.php" class="btn btn-primary btn-lg">
                                    <i class="fas fa-lock me-1"></i>Login to Send Inquiry
                                </a>
                                <button class="btn btn-outline-primary btn-lg" data-bs-toggle="modal" data-bs-target="#loginModal">
                                    <i class="fas fa-question-circle me-1"></i>Send Inquiry
                                </button>
                            </div>
                            
                            <!-- Login Modal -->
                            <div class="modal fade" id="loginModal" tabindex="-1" aria-labelledby="loginModalLabel" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content">
                                        <div class="modal-header bg-primary text-white">
                                            <h5 class="modal-title" id="loginModalLabel">Login Required</h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body text-center">
                                            <i class="fas fa-lock fa-3x text-primary mb-3"></i>
                                            <p>You need to be logged in to send inquiries or make purchases.</p>
                                            <p>Please <a href="login.php">login</a> or <a href="register.php">register</a> to continue.</p>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <a href="login.php" class="btn btn-primary">Login</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
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
        
        // Handle inquiry form submission
        document.getElementById('inquiryForm').addEventListener('submit', function(e) {
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
    
    <!-- Inquiry Modal -->
    <div class="modal fade" id="inquiryModal" tabindex="-1" aria-labelledby="inquiryModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="inquiryModalLabel">Ask About Product</h5>
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
</body>
</html>