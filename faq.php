<?php
session_start();
require_once 'config.php';

// User session data
$is_logged_in = isset($_SESSION['user_id']);
$user_name = $_SESSION['user_name'] ?? '';
$user_role = $_SESSION['user_role'] ?? '';

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
    <title>FAQ - BSDO Sale</title>
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
            color: var(--dark-color);
        }
        
        .navbar {
            background-color: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .hero-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 80px 0;
        }
        
        .faq-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }
        
        .faq-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }
        
        .faq-question {
            cursor: pointer;
            padding: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .faq-answer {
            padding: 0 20px;
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease, padding 0.3s ease;
        }
        
        .faq-answer.show {
            padding: 20px;
            max-height: 500px;
        }
        
        .faq-toggle {
            transition: transform 0.3s ease;
        }
        
        .faq-toggle.rotated {
            transform: rotate(180deg);
        }
        
        footer {
            background-color: var(--dark-color);
            color: white;
            padding: 60px 0 30px;
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
                    <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="products.php">Products</a></li>
                    <li class="nav-item"><a class="nav-link" href="live_streams.php">Live Streams</a></li>
                </ul>
                
                <div class="d-flex align-items-center">
                    <?php if ($is_logged_in): ?>
                        <div class="dropdown">
                            <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown">
                                <div class="user-avatar me-2" style="width: 40px; height: 40px; background: var(--primary-color); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold;">
                                    <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                                </div>
                                <span class="me-2"><?php echo htmlspecialchars(explode(' ', $user_name)[0]); ?></span>
                            </a>
                            <ul class="dropdown-menu">
                                <?php if ($user_role === 'seller'): ?>
                                    <li><a class="dropdown-item" href="seller/dashboard.php"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a></li>
                                <?php elseif ($user_role === 'admin'): ?>
                                    <li><a class="dropdown-item" href="admin/dashboard.php"><i class="fas fa-cog me-2"></i>Admin Panel</a></li>
                                <?php else: ?>
                                    <li><a class="dropdown-item" href="myAccount.php"><i class="fas fa-user me-2"></i>My Account</a></li>
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

    <section class="hero-section text-center">
        <div class="container">
            <h1 class="display-4 fw-bold mb-4">Frequently Asked Questions</h1>
            <p class="lead">Find answers to common questions about using BSDO Sale</p>
        </div>
    </section>

    <section class="py-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="accordion" id="faqAccordion">
                        <!-- General Questions -->
                        <div class="card faq-card">
                            <div class="faq-question d-flex justify-content-between align-items-center" data-bs-toggle="collapse" data-bs-target="#faq1">
                                <h5 class="mb-0">How do I create an account?</h5>
                                <i class="fas fa-chevron-down faq-toggle"></i>
                            </div>
                            <div id="faq1" class="collapse" data-bs-parent="#faqAccordion">
                                <div class="faq-answer">
                                    <p>To create an account:</p>
                                    <ol>
                                        <li>Click on the "Register" button in the top right corner</li>
                                        <li>Choose your account type (Client, Seller, or Admin)</li>
                                        <li>Fill in your personal information</li>
                                        <li>Verify your email address</li>
                                        <li>Log in with your credentials</li>
                                    </ol>
                                    <p>For sellers, you'll receive a seller code via email after registration.</p>
                                </div>
                            </div>
                        </div>

                        <div class="card faq-card">
                            <div class="faq-question d-flex justify-content-between align-items-center" data-bs-toggle="collapse" data-bs-target="#faq2">
                                <h5 class="mb-0">How do I buy products?</h5>
                                <i class="fas fa-chevron-down faq-toggle"></i>
                            </div>
                            <div id="faq2" class="collapse" data-bs-parent="#faqAccordion">
                                <div class="faq-answer">
                                    <p>To buy products:</p>
                                    <ol>
                                        <li>Browse products on the homepage or in the "Products" section</li>
                                        <li>Click on a product to view details</li>
                                        <li>Select quantity and click "Add to Cart"</li>
                                        <li>Go to your cart and click "Checkout"</li>
                                        <li>Enter shipping information and select payment method</li>
                                        <li>Complete the payment process</li>
                                    </ol>
                                </div>
                            </div>
                        </div>

                        <div class="card faq-card">
                            <div class="faq-question d-flex justify-content-between align-items-center" data-bs-toggle="collapse" data-bs-target="#faq3">
                                <h5 class="mb-0">How do I rent products?</h5>
                                <i class="fas fa-chevron-down faq-toggle"></i>
                            </div>
                            <div id="faq3" class="collapse" data-bs-parent="#faqAccordion">
                                <div class="faq-answer">
                                    <p>To rent products:</p>
                                    <ol>
                                        <li>Navigate to the "Rent" section</li>
                                        <li>Browse rental products</li>
                                        <li>Select rental dates and click "Add to Cart"</li>
                                        <li>Complete the checkout process</li>
                                        <li>You'll receive confirmation and rental instructions</li>
                                    </ol>
                                    <p>Note: Rental products require a security deposit which will be refunded after return.</p>
                                </div>
                            </div>
                        </div>

                        <div class="card faq-card">
                            <div class="faq-question d-flex justify-content-between align-items-center" data-bs-toggle="collapse" data-bs-target="#faq4">
                                <h5 class="mb-0">How do I participate in live streams?</h5>
                                <i class="fas fa-chevron-down faq-toggle"></i>
                            </div>
                            <div id="faq4" class="collapse" data-bs-parent="#faqAccordion">
                                <div class="faq-answer">
                                    <p>To participate in live streams:</p>
                                    <ol>
                                        <li>Visit the "Live Streams" section</li>
                                        <li>Browse upcoming or ongoing live streams</li>
                                        <li>Click "Join" to enter the stream</li>
                                        <li>You can chat with the seller and other viewers</li>
                                        <li>Products featured in the stream can be purchased directly</li>
                                    </ol>
                                    <p>As a seller, you can create live streams from your dashboard.</p>
                                </div>
                            </div>
                        </div>

                        <!-- Seller Questions -->
                        <div class="card faq-card">
                            <div class="faq-question d-flex justify-content-between align-items-center" data-bs-toggle="collapse" data-bs-target="#faq5">
                                <h5 class="mb-0">How do I become a seller?</h5>
                                <i class="fas fa-chevron-down faq-toggle"></i>
                            </div>
                            <div id="faq5" class="collapse" data-bs-parent="#faqAccordion">
                                <div class="faq-answer">
                                    <p>To become a seller:</p>
                                    <ol>
                                        <li>Register as a seller on the registration page</li>
                                        <li>Provide your store information and business details</li>
                                        <li>Verify your email address</li>
                                        <li>Log in using your seller code (sent to your email)</li>
                                        <li>Complete your seller profile in the dashboard</li>
                                    </ol>
                                    <p>Once approved, you can start listing products and creating live streams.</p>
                                </div>
                            </div>
                        </div>

                        <div class="card faq-card">
                            <div class="faq-question d-flex justify-content-between align-items-center" data-bs-toggle="collapse" data-bs-target="#faq6">
                                <h5 class="mb-0">How do I list products for sale?</h5>
                                <i class="fas fa-chevron-down faq-toggle"></i>
                            </div>
                            <div id="faq6" class="collapse" data-bs-parent="#faqAccordion">
                                <div class="faq-answer">
                                    <p>To list products:</p>
                                    <ol>
                                        <li>Log in to your seller dashboard</li>
                                        <li>Click on "Products" in the sidebar</li>
                                        <li>Click "Add New Product"</li>
                                        <li>Fill in product details (name, description, price, images)</li>
                                        <li>Select category and set inventory</li>
                                        <li>Click "Save" to publish your product</li>
                                    </ol>
                                    <p>You can also list rental products from the "Rental Products" section.</p>
                                </div>
                            </div>
                        </div>

                        <!-- Payment Questions -->
                        <div class="card faq-card">
                            <div class="faq-question d-flex justify-content-between align-items-center" data-bs-toggle="collapse" data-bs-target="#faq7">
                                <h5 class="mb-0">What payment methods are accepted?</h5>
                                <i class="fas fa-chevron-down faq-toggle"></i>
                            </div>
                            <div id="faq7" class="collapse" data-bs-parent="#faqAccordion">
                                <div class="faq-answer">
                                    <p>We accept the following payment methods:</p>
                                    <ul>
                                        <li>Credit/Debit Cards (Visa, MasterCard, American Express)</li>
                                        <li>PayPal</li>
                                        <li>Bank Transfer</li>
                                        <li>Cryptocurrency</li>
                                        <li>Cash on Delivery (for local orders)</li>
                                    </ul>
                                    <p>All payments are securely processed and encrypted.</p>
                                </div>
                            </div>
                        </div>

                        <div class="card faq-card">
                            <div class="faq-question d-flex justify-content-between align-items-center" data-bs-toggle="collapse" data-bs-target="#faq8">
                                <h5 class="mb-0">How do I contact customer support?</h5>
                                <i class="fas fa-chevron-down faq-toggle"></i>
                            </div>
                            <div id="faq8" class="collapse" data-bs-parent="#faqAccordion">
                                <div class="faq-answer">
                                    <p>You can contact our customer support through several channels:</p>
                                    <ul>
                                        <li><strong>Live Chat:</strong> Available 24/7 on our website</li>
                                        <li><strong>Email:</strong> support@bsdosale.com</li>
                                        <li><strong>Phone:</strong> +1 (234) 567-890</li>
                                        <li><strong>In-App Support:</strong> Through your account dashboard</li>
                                    </ul>
                                    <p>Our support team typically responds within 24 hours.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card mt-5 p-4 text-center">
                        <h4>Still need help?</h4>
                        <p>Contact our customer support team for personalized assistance.</p>
                        <a href="handle_live_chat.php" class="btn btn-primary">
                            <i class="fas fa-comments me-2"></i>Start Live Chat
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <footer class="bg-dark text-light py-5">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-4 mb-md-0">
                    <h5>BSDO Sale</h5>
                    <p>Your one-stop platform for buying, renting, and live shopping experiences.</p>
                </div>
                <div class="col-md-4 mb-4 mb-md-0">
                    <h5>Quick Links</h5>
                    <ul class="list-unstyled">
                        <li><a href="index.php" class="text-light text-decoration-none">Home</a></li>
                        <li><a href="products.php" class="text-light text-decoration-none">Products</a></li>
                        <li><a href="live_streams.php" class="text-light text-decoration-none">Live Streams</a></li>
                        <li><a href="faq.php" class="text-light text-decoration-none">FAQ</a></li>
                    </ul>
                </div>
                <div class="col-md-4">
                    <h5>Contact Us</h5>
                    <ul class="list-unstyled">
                        <li><i class="fas fa-envelope me-2"></i> support@bsdosale.com</li>
                        <li><i class="fas fa-phone me-2"></i> +1 (234) 567-890</li>
                    </ul>
                </div>
            </div>
            <hr class="my-4">
            <div class="text-center">
                <p>&copy; 2025 BSDO Sale. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add rotation to FAQ toggle icons
        document.querySelectorAll('.faq-question').forEach(question => {
            question.addEventListener('click', function() {
                const icon = this.querySelector('.faq-toggle');
                icon.classList.toggle('rotated');
            });
        });
    </script>
</body>
</html>