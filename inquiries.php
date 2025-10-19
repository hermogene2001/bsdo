<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'client') {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Get user's inquiries
$stmt = $pdo->prepare("
    SELECT i.*, p.name as product_name, p.image_url, u.store_name, u.id as seller_id,
           (SELECT COUNT(*) FROM inquiry_messages WHERE inquiry_id = i.id AND sender_type = 'seller' AND is_read = 0) as unread_count,
           (SELECT MAX(created_at) FROM inquiry_messages WHERE inquiry_id = i.id) as last_message_time
    FROM inquiries i 
    JOIN products p ON i.product_id = p.id 
    JOIN users u ON i.seller_id = u.id 
    WHERE i.user_id = ? 
    ORDER BY i.updated_at DESC
");
$stmt->execute([$user_id]);
$inquiries = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Inquiries - BSDO Sale</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #1cc88a;
            --unread-color: #e74a3b;
            --dark-color: #2e3a59;
            --light-color: #f8f9fc;
        }
        
        body {
            background-color: var(--light-color);
            font-family: 'Nunito', sans-serif;
        }
        
        .navbar {
            background-color: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .inquiry-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            margin-bottom: 20px;
        }
        
        .inquiry-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }
        
        .inquiry-card.unread {
            border-left: 4px solid var(--unread-color);
        }
        
        .unread-badge {
            background: var(--unread-color);
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 0.7rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .chat-modal .modal-dialog {
            max-width: 800px;
        }
        
        .chat-header {
            background: linear-gradient(135deg, var(--primary-color), #667eea);
            color: white;
            padding: 20px;
            border-radius: 15px 15px 0 0;
        }
        
        .chat-messages {
            height: 400px;
            overflow-y: auto;
            padding: 20px;
            background: #f8f9fa;
            border-left: 1px solid #e9ecef;
            border-right: 1px solid #e9ecef;
        }
        
        .message {
            margin-bottom: 15px;
            padding: 12px 15px;
            border-radius: 15px;
            max-width: 70%;
            position: relative;
        }
        
        .message.user {
            background: var(--primary-color);
            color: white;
            margin-left: auto;
            border-bottom-right-radius: 5px;
        }
        
        .message.seller {
            background: white;
            border: 1px solid #e9ecef;
            margin-right: auto;
            border-bottom-left-radius: 5px;
        }
        
        .message-time {
            font-size: 0.7rem;
            opacity: 0.7;
            margin-top: 5px;
        }
        
        .message.seller .message-time {
            text-align: left;
        }
        
        .message.user .message-time {
            text-align: right;
        }
        
        .chat-input {
            border-top: 1px solid #e9ecef;
            padding: 20px;
            background: white;
            border-radius: 0 0 15px 15px;
        }
        
        .product-image {
            width: 60px;
            height: 60px;
            border-radius: 10px;
            object-fit: cover;
            background: linear-gradient(135deg, #f8f9fc, #e9ecef);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--dark-color);
        }
        
        .status-badge {
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .last-message {
            font-size: 0.9rem;
            color: #6c757d;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .notification-bell {
            position: relative;
        }
        
        .notification-dot {
            position: absolute;
            top: -5px;
            right: -5px;
            width: 12px;
            height: 12px;
            background: var(--unread-color);
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.2); opacity: 0.7; }
            100% { transform: scale(1); opacity: 1; }
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
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
            
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="products.php">Products</a></li>
                    <li class="nav-item"><a class="nav-link active" href="inquiries.php">My Inquiries</a></li>
                </ul>
                
                <div class="d-flex align-items-center">
                    <div class="notification-bell me-3" id="notificationBell" style="display: none;">
                        <i class="fas fa-bell fa-lg text-primary"></i>
                        <div class="notification-dot"></div>
                    </div>
                    
                    <div class="dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown">
                            <div class="user-avatar me-2">
                                <?php echo strtoupper(substr($_SESSION['user_name'], 0, 1)); ?>
                            </div>
                            <span><?php echo htmlspecialchars(explode(' ', $_SESSION['user_name'])[0]); ?></span>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="myAccount.php"><i class="fas fa-user me-2"></i>My Account</a></li>
                            <li><a class="dropdown-item" href="inquiries.php"><i class="fas fa-comments me-2"></i>My Inquiries</a></li>
                            <li><a class="dropdown-item" href="cart.php"><i class="fas fa-shopping-cart me-2"></i>Shopping Cart</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container mt-5 pt-5">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="mb-0">
                        <i class="fas fa-comments me-2 text-primary"></i>
                        My Inquiries
                    </h2>
                    <span class="badge bg-primary" id="totalUnread">0 unread</span>
                </div>
                
                <?php if (!empty($inquiries)): ?>
                    <div class="row" id="inquiriesContainer">
                        <?php foreach ($inquiries as $inquiry): ?>
                            <div class="col-lg-6 mb-4">
                                <div class="card inquiry-card <?php echo $inquiry['unread_count'] > 0 ? 'unread' : ''; ?>" 
                                     data-inquiry-id="<?php echo $inquiry['id']; ?>">
                                    <div class="card-body">
                                        <div class="row align-items-center">
                                            <div class="col-2">
                                                <div class="product-image">
                                                    <?php if ($inquiry['image_url']): ?>
                                                        <img src="<?php echo htmlspecialchars($inquiry['image_url']); ?>" 
                                                             alt="<?php echo htmlspecialchars($inquiry['product_name']); ?>" 
                                                             class="w-100 h-100 rounded">
                                                    <?php else: ?>
                                                        <i class="fas fa-box"></i>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="col-8">
                                                <h6 class="card-title mb-1"><?php echo htmlspecialchars($inquiry['product_name']); ?></h6>
                                                <p class="text-muted small mb-1">
                                                    <i class="fas fa-store me-1"></i>
                                                    <?php echo htmlspecialchars($inquiry['store_name']); ?>
                                                </p>
                                                <div class="d-flex align-items-center mb-2">
                                                    <span class="status-badge bg-<?php 
                                                        echo $inquiry['status'] === 'replied' ? 'success' : 
                                                             ($inquiry['status'] === 'resolved' ? 'info' : 'warning'); 
                                                    ?> me-2">
                                                        <?php echo ucfirst($inquiry['status']); ?>
                                                    </span>
                                                    <?php if ($inquiry['unread_count'] > 0): ?>
                                                        <span class="unread-badge me-2" id="unreadBadge_<?php echo $inquiry['id']; ?>">
                                                            <?php echo $inquiry['unread_count']; ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <p class="last-message mb-0" id="lastMessage_<?php echo $inquiry['id']; ?>">
                                                    <?php echo htmlspecialchars($inquiry['message']); ?>
                                                </p>
                                            </div>
                                            <div class="col-2 text-end">
                                                <button class="btn btn-primary btn-sm open-chat" 
                                                        data-inquiry-id="<?php echo $inquiry['id']; ?>"
                                                        data-product-name="<?php echo htmlspecialchars($inquiry['product_name']); ?>"
                                                        data-seller-name="<?php echo htmlspecialchars($inquiry['store_name']); ?>">
                                                    <i class="fas fa-comment-dots"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <!-- Empty State -->
                    <div class="empty-state">
                        <i class="fas fa-comments"></i>
                        <h3>No Inquiries Yet</h3>
                        <p class="mb-4">You haven't made any product inquiries yet. Start by asking sellers about products you're interested in!</p>
                        <a href="products.php" class="btn btn-primary btn-lg">
                            <i class="fas fa-shopping-bag me-2"></i>Browse Products
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
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

</body>
</html>