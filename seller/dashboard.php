<?php
session_start();
require_once '../config.php';

// Check if user is logged in and is seller
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'seller') {
    header('Location: ../login.php');
    exit();
}

$seller_id = $_SESSION['user_id'];

// Get seller information including seller code
$seller_stmt = $pdo->prepare("
    SELECT u.*, sc.seller_code 
    FROM users u 
    LEFT JOIN seller_codes sc ON u.id = sc.seller_id 
    WHERE u.id = ?
");
$seller_stmt->execute([$seller_id]);
$seller = $seller_stmt->fetch(PDO::FETCH_ASSOC);

// Get dashboard statistics
$stats_stmt = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT p.id) as total_products,
        COUNT(DISTINCT CASE WHEN p.status = 'active' THEN p.id END) as active_products,
        COUNT(DISTINCT o.id) as total_orders,
        COALESCE(SUM(oi.quantity * oi.price), 0) as total_revenue
    FROM users u
    LEFT JOIN products p ON u.id = p.seller_id
    LEFT JOIN order_items oi ON p.id = oi.product_id
    LEFT JOIN orders o ON oi.order_id = o.id AND o.status = 'completed'
    WHERE u.id = ?
");
$stats_stmt->execute([$seller_id]);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Get recent products
$products_stmt = $pdo->prepare("
    SELECT p.*, c.name as category_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    WHERE p.seller_id = ? 
    ORDER BY p.created_at DESC 
    LIMIT 5
");
$products_stmt->execute([$seller_id]);
$recent_products = $products_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent orders
$orders_stmt = $pdo->prepare("
    SELECT o.*, u.first_name, u.last_name, COUNT(oi.id) as item_count
    FROM orders o
    JOIN users u ON o.user_id = u.id
    JOIN order_items oi ON o.id = oi.order_id
    JOIN products p ON oi.product_id = p.id
    WHERE p.seller_id = ?
    GROUP BY o.id
    ORDER BY o.created_at DESC
    LIMIT 5
");
$orders_stmt->execute([$seller_id]);
$recent_orders = $orders_stmt->fetchAll(PDO::FETCH_ASSOC);

function formatCurrency($amount) {
    return '$' . number_format($amount, 2);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - BSDO Seller</title>
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
        
        .stats-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s;
            border-left: 4px solid var(--primary-color);
            margin-bottom: 1.5rem;
        }
        
        .stats-card:hover {
            transform: translateY(-2px);
        }
        
        .stats-card.success { border-left-color: var(--secondary-color); }
        .stats-card.warning { border-left-color: #f6c23e; }
        .stats-card.info { border-left-color: #36b9cc; }
        
        /* Seller code display styles */
        .seller-code-display {
            background: linear-gradient(135deg, #1cc88a, #18a873);
            color: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
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
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-store me-2"></i>
                <strong>BSDO Seller</strong>
            </a>
            
            <div class="d-flex align-items-center">
                <div class="dropdown">
                    <a class="nav-link dropdown-toggle text-white d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown">
                        <div class="bg-white rounded-circle me-2" style="width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;">
                            <span class="text-primary fw-bold"><?php echo strtoupper(substr($seller['first_name'], 0, 1)); ?></span>
                        </div>
                        <span><?php echo htmlspecialchars($seller['first_name'] . ' ' . $seller['last_name']); ?></span>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                        <li><a class="dropdown-item" href="settings.php"><i class="fas fa-cog me-2"></i>Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-lg-2 sidebar d-none d-lg-block">
                <div class="pt-4">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="dashboard.php">
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

            <!-- Main Content -->
            <div class="col-lg-10 col-md-12 p-4">
                <!-- Seller Code Display -->
                <?php if (!empty($seller['seller_code'])): ?>
                    <div class="seller-code-display">
                        <h4><i class="fas fa-key me-2"></i>Your Seller Code</h4>
                        <p>Use this code to login to your seller account</p>
                        <div class="seller-code" id="sellerCodeDisplay"><?php echo htmlspecialchars($seller['seller_code']); ?></div>
                        <div>
                            <button class="copy-btn" onclick="copySellerCode()">
                                <i class="fas fa-copy me-2"></i>Copy Code
                            </button>
                            <button class="copy-btn ms-2" onclick="saveSellerCodeToCookie()">
                                <i class="fas fa-cookie-bite me-2"></i>Save to Cookies
                            </button>
                        </div>
                        <p class="mb-0 mt-2"><small><i class="fas fa-info-circle me-2"></i>This code will be auto-filled when you login next time</small></p>
                    </div>
                <?php endif; ?>

                <!-- Page Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2><i class="fas fa-tachometer-alt me-2"></i>Dashboard</h2>
                        <p class="text-muted mb-0">Welcome back, <?php echo htmlspecialchars($seller['first_name']); ?>!</p>
                    </div>
                    <div class="text-end">
                        <div class="small text-muted">Member since <?php echo date('F Y', strtotime($seller['created_at'])); ?></div>
                    </div>
                </div>

                <!-- Quick Stats -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="stats-card">
                            <div class="h4 mb-1"><?php echo $stats['total_products']; ?></div>
                            <div class="small">Total Products</div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="stats-card success">
                            <div class="h4 mb-1"><?php echo $stats['active_products']; ?></div>
                            <div class="small">Active Products</div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="stats-card warning">
                            <div class="h4 mb-1"><?php echo $stats['total_orders']; ?></div>
                            <div class="small">Total Orders</div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="stats-card info">
                            <div class="h4 mb-1"><?php echo formatCurrency($stats['total_revenue']); ?></div>
                            <div class="small">Total Revenue</div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Recent Products -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="fas fa-box me-2"></i>Recent Products</h5>
                                <a href="products.php" class="btn btn-sm btn-primary">View All</a>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($recent_products)): ?>
                                    <div class="table-responsive">
                                        <table class="table">
                                            <thead>
                                                <tr>
                                                    <th>Product</th>
                                                    <th>Price</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($recent_products as $product): ?>
                                                    <tr>
                                                        <td>
                                                            <div class="fw-bold"><?php echo htmlspecialchars($product['name']); ?></div>
                                                            <div class="small text-muted"><?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?></div>
                                                        </td>
                                                        <td><?php echo formatCurrency($product['price']); ?></td>
                                                        <td>
                                                            <span class="badge bg-<?php echo $product['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                                                <?php echo ucfirst($product['status']); ?>
                                                            </span>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted text-center py-3">No products found.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Orders -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="fas fa-shopping-cart me-2"></i>Recent Orders</h5>
                                <a href="orders.php" class="btn btn-sm btn-primary">View All</a>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($recent_orders)): ?>
                                    <div class="table-responsive">
                                        <table class="table">
                                            <thead>
                                                <tr>
                                                    <th>Order</th>
                                                    <th>Customer</th>
                                                    <th>Items</th>
                                                    <th>Amount</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($recent_orders as $order): ?>
                                                    <tr>
                                                        <td>
                                                            <div class="fw-bold">#<?php echo $order['id']; ?></div>
                                                            <div class="small text-muted"><?php echo date('M j, Y', strtotime($order['created_at'])); ?></div>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></td>
                                                        <td><?php echo $order['item_count']; ?></td>
                                                        <td><?php echo formatCurrency($order['total_amount']); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted text-center py-3">No orders found.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
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
    </script>
</body>
</html>
