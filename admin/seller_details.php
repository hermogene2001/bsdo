<?php
session_start();
require_once '../config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Check if seller ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: sellers.php');
    exit();
}

$seller_id = intval($_GET['id']);

// Get seller details
$stmt = $pdo->prepare("
    SELECT 
        u.*,
        sc.seller_code,
        COUNT(DISTINCT p.id) as total_products,
        COUNT(DISTINCT oi.id) as total_sales,
        COALESCE(SUM(CASE WHEN o.status = 'completed' THEN oi.quantity * oi.price ELSE 0 END), 0) as total_revenue,
        COALESCE(SUM(CASE WHEN o.status = 'completed' THEN oi.quantity * oi.price * 0.1 ELSE 0 END), 0) as platform_commission,
        COUNT(DISTINCT CASE WHEN p.status = 'active' THEN p.id END) as active_products,
        COUNT(DISTINCT CASE WHEN p.status = 'pending' THEN p.id END) as pending_products,
        COUNT(DISTINCT ls.id) as total_streams,
        COUNT(DISTINCT CASE WHEN ls.is_live = 1 THEN ls.id END) as live_streams
    FROM users u 
    LEFT JOIN seller_codes sc ON u.id = sc.seller_id
    LEFT JOIN products p ON u.id = p.seller_id
    LEFT JOIN order_items oi ON p.id = oi.product_id
    LEFT JOIN orders o ON oi.order_id = o.id
    LEFT JOIN live_streams ls ON u.id = ls.seller_id
    WHERE u.id = ? AND u.role = 'seller'
    GROUP BY u.id
");

$stmt->execute([$seller_id]);
$seller = $stmt->fetch(PDO::FETCH_ASSOC);

// If seller not found
if (!$seller) {
    header('Location: sellers.php');
    exit();
}

// Get seller's recent products
$product_stmt = $pdo->prepare("
    SELECT p.*, c.name as category_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.seller_id = ?
    ORDER BY p.created_at DESC
    LIMIT 5
");
$product_stmt->execute([$seller_id]);
$recent_products = $product_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get seller's recent streams
$stream_stmt = $pdo->prepare("
    SELECT *
    FROM live_streams
    WHERE seller_id = ?
    ORDER BY created_at DESC
    LIMIT 5
");
$stream_stmt->execute([$seller_id]);
$recent_streams = $stream_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get seller's recent orders
$order_stmt = $pdo->prepare("
    SELECT o.*, p.name as product_name
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    JOIN products p ON oi.product_id = p.id
    WHERE p.seller_id = ?
    ORDER BY o.created_at DESC
    LIMIT 5
");
$order_stmt->execute([$seller_id]);
$recent_orders = $order_stmt->fetchAll(PDO::FETCH_ASSOC);

// Log admin activity
logAdminActivity("Viewed seller details for ID: $seller_id");

function logAdminActivity($activity) {
    global $pdo;
    if (isset($_SESSION['user_id'])) {
        $stmt = $pdo->prepare("INSERT INTO admin_activities (admin_id, activity, ip_address) VALUES (?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], $activity, $_SERVER['REMOTE_ADDR']]);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seller Details - <?php echo htmlspecialchars($seller['first_name'] . ' ' . $seller['last_name']); ?> - BSDO Sale Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #1cc88a;
            --warning-color: #f6c23e;
            --danger-color: #e74a3b;
            --dark-color: #2e3a59;
            --light-color: #f8f9fc;
            --sidebar-width: 250px;
        }
        
        body {
            font-family: 'Nunito', sans-serif;
            background-color: #f8f9fc;
            overflow-x: hidden;
        }
        
        #wrapper {
            display: flex;
        }
        
        #sidebar {
            min-width: var(--sidebar-width);
            max-width: var(--sidebar-width);
            background: var(--dark-color);
            color: #fff;
            transition: all 0.3s;
            min-height: 100vh;
        }
        
        #sidebar.active {
            margin-left: -var(--sidebar-width);
        }
        
        #sidebar .sidebar-header {
            padding: 20px;
            background: rgba(0, 0, 0, 0.2);
        }
        
        #sidebar ul.components {
            padding: 20px 0;
        }
        
        #sidebar ul li a {
            padding: 15px 20px;
            font-size: 1.1em;
            display: block;
            color: #fff;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        #sidebar ul li a:hover {
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
        }
        
        #sidebar ul li.active > a {
            background: var(--primary-color);
            color: #fff;
        }
        
        #sidebar ul li a i {
            margin-right: 10px;
        }
        
        #content {
            width: 100%;
            padding: 20px;
            min-height: 100vh;
            transition: all 0.3s;
        }
        
        .navbar {
            background-color: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            margin-bottom: 20px;
        }
        
        .card-header {
            background-color: #f8f9fc;
            border-bottom: 1px solid #e3e6f0;
            padding: 15px 20px;
            font-weight: 700;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-success {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
        }
        
        .btn-warning {
            background-color: var(--warning-color);
            border-color: var(--warning-color);
        }
        
        .btn-danger {
            background-color: var(--danger-color);
            border-color: var(--danger-color);
        }
        
        .badge-success {
            background-color: var(--secondary-color);
        }
        
        .badge-warning {
            background-color: var(--warning-color);
        }
        
        .badge-danger {
            background-color: var(--danger-color);
        }
        
        .badge-primary {
            background-color: var(--primary-color);
        }
        
        .sidebar-toggler {
            cursor: pointer;
        }
        
        .seller-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background-color: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 2rem;
            margin: 0 auto 15px;
        }
        
        .stats-card {
            border-left: 4px solid;
            transition: transform 0.2s;
        }
        
        .stats-card:hover {
            transform: translateY(-2px);
        }
        
        .stats-card.total {
            border-left-color: var(--primary-color);
        }
        
        .stats-card.active {
            border-left-color: var(--secondary-color);
        }
        
        .stats-card.pending {
            border-left-color: var(--warning-color);
        }
        
        .stats-card.inactive {
            border-left-color: var(--danger-color);
        }
        
        .commission-badge {
            background: linear-gradient(45deg, var(--warning-color), var(--secondary-color));
            color: white;
        }
        
        .product-image {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 5px;
        }
        
        .stream-status-live {
            background-color: var(--secondary-color);
        }
        
        .stream-status-ended {
            background-color: var(--danger-color);
        }
        
        .stream-status-scheduled {
            background-color: var(--warning-color);
        }
    </style>
</head>
<body>
    <div id="wrapper">
        <!-- Sidebar -->
        <nav id="sidebar">
            <div class="sidebar-header">
                <h3><i class="fas fa-shopping-bag me-2"></i>BSDO Admin</h3>
            </div>
            
            <ul class="list-unstyled components">
                <li>
                    <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                </li>
                <li>
                    <a href="users.php"><i class="fas fa-users"></i> Users</a>
                </li>
                <li>
                    <a href="products.php"><i class="fas fa-box"></i> Products</a>
                </li>
                <li>
                    <a href="categories.php"><i class="fas fa-tags"></i> Categories</a>
                </li>
                <li class="active">
                    <a href="sellers.php"><i class="fas fa-store"></i> Sellers</a>
                </li>
                <li>
                    <a href="orders.php"><i class="fas fa-shopping-cart"></i> Orders</a>
                </li>
                <li>
                    <a href="analytics.php"><i class="fas fa-chart-bar"></i> Analytics</a>
                </li>
                <li>
                    <a href="carousel.php"><i class="fas fa-images"></i> Carousel</a>
                </li>
                <li>
                    <a href="payment_slips.php"><i class="fas fa-money-check"></i> Payment Slips</a>
                </li>
                <li>
                    <a href="payment_channels.php"><i class="fas fa-money-bill-wave"></i> Payment Channels</a>
                </li>
                <li>
                    <a href="withdrawal_requests.php"><i class="fas fa-money-bill-transfer"></i> Withdrawal Requests</a>
                </li>
                <li>
                    <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
                </li>
                <li>
                    <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </li>
            </ul>
        </nav>

        <!-- Content -->
        <div id="content">
            <!-- Top Navbar -->
            <nav class="navbar navbar-expand-lg navbar-light bg-white mb-4">
                <div class="container-fluid">
                    <button type="button" id="sidebarCollapse" class="btn btn-primary sidebar-toggler">
                        <i class="fas fa-bars"></i>
                    </button>
                    
                    <div class="d-flex align-items-center">
                        <div class="dropdown">
                            <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown">
                                <div class="user-avatar me-2">A</div>
                                <span>Admin User</span>
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="#"><i class="fas fa-user me-2"></i>Profile</a></li>
                                <li><a class="dropdown-item" href="#"><i class="fas fa-cog me-2"></i>Settings</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Page Heading -->
            <div class="d-sm-flex align-items-center justify-content-between mb-4">
                <h1 class="h3 mb-0 text-gray-800">Seller Details</h1>
                <a href="sellers.php" class="d-none d-sm-inline-block btn btn-primary shadow-sm">
                    <i class="fas fa-arrow-left fa-sm text-white-50"></i> Back to Sellers
                </a>
            </div>

            <!-- Seller Profile Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Seller Profile</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 text-center">
                            <div class="seller-avatar">
                                <?php echo strtoupper(substr($seller['first_name'], 0, 1) . substr($seller['last_name'], 0, 1)); ?>
                            </div>
                            <h4><?php echo htmlspecialchars($seller['first_name'] . ' ' . $seller['last_name']); ?></h4>
                            <p class="text-muted"><?php echo htmlspecialchars($seller['store_name'] ?? 'No Store Name'); ?></p>
                            
                            <div class="mt-3">
                                <span class="badge 
                                    <?php echo $seller['status'] === 'active' ? 'bg-success' : ($seller['status'] === 'pending' ? 'bg-warning' : 'bg-secondary'); ?>">
                                    <?php echo ucfirst($seller['status']); ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="col-md-8">
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Email:</strong> <?php echo htmlspecialchars($seller['email']); ?></p>
                                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($seller['phone'] ?? 'Not provided'); ?></p>
                                    <p><strong>Business Type:</strong> <?php echo htmlspecialchars($seller['business_type'] ?? 'Not specified'); ?></p>
                                    <p><strong>Address:</strong> <?php echo htmlspecialchars($seller['address'] ?? 'Not provided'); ?></p>
                                </div>
                                
                                <div class="col-md-6">
                                    <p><strong>Seller Code:</strong> <code><?php echo htmlspecialchars($seller['seller_code']); ?></code></p>
                                    <p><strong>Member Since:</strong> <?php echo date('M j, Y', strtotime($seller['created_at'])); ?></p>
                                    <p><strong>Last Login:</strong> <?php echo isset($seller['last_login']) && $seller['last_login'] ? date('M j, Y g:i A', strtotime($seller['last_login'])) : 'Never'; ?></p>
                                </div>
                            </div>
                            
                            <div class="mt-4">
                                <h5>Quick Actions</h5>
                                <div class="btn-group" role="group">
                                    <?php if ($seller['status'] === 'pending'): ?>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="seller_id" value="<?php echo $seller['id']; ?>">
                                            <button type="submit" name="action" value="approve" class="btn btn-success btn-sm">
                                                <i class="fas fa-check"></i> Approve
                                            </button>
                                        </form>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="seller_id" value="<?php echo $seller['id']; ?>">
                                            <button type="submit" name="action" value="reject" class="btn btn-danger btn-sm">
                                                <i class="fas fa-times"></i> Reject
                                            </button>
                                        </form>
                                    <?php elseif ($seller['status'] === 'active'): ?>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="seller_id" value="<?php echo $seller['id']; ?>">
                                            <button type="submit" name="action" value="suspend" class="btn btn-warning btn-sm">
                                                <i class="fas fa-pause"></i> Suspend
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="seller_id" value="<?php echo $seller['id']; ?>">
                                            <button type="submit" name="action" value="activate" class="btn btn-success btn-sm">
                                                <i class="fas fa-play"></i> Activate
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    
                                    <a href="seller_details.php?id=<?php echo $seller['id']; ?>" class="btn btn-info btn-sm">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this seller? This action cannot be undone.');">
                                        <input type="hidden" name="seller_id" value="<?php echo $seller['id']; ?>">
                                        <button type="submit" name="action" value="delete" class="btn btn-danger btn-sm">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stats-card total h-100">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Products</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($seller['total_products']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-box fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stats-card active h-100">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Active Products</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($seller['active_products']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stats-card pending h-100">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Pending Products</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($seller['pending_products']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-clock fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stats-card total h-100">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Total Revenue</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">$<?php echo number_format($seller['total_revenue'], 2); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stats Row 2 -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stats-card total h-100">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Streams</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($seller['total_streams']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-video fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stats-card active h-100">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Live Streams</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($seller['live_streams']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-circle-dot fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stats-card total h-100">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Total Sales</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($seller['total_sales']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-shopping-cart fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stats-card pending h-100">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Commission</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">$<?php echo number_format($seller['platform_commission'], 2); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-percentage fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Recent Products -->
                <div class="col-md-6">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex justify-content-between align-items-center">
                            <h6 class="m-0 font-weight-bold text-primary">Recent Products</h6>
                            <a href="products.php?seller=<?php echo $seller['id']; ?>" class="btn btn-sm btn-primary">View All</a>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($recent_products)): ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered">
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
                                                        <?php if (!empty($product['image_url'])): ?>
                                                            <img src="<?php echo htmlspecialchars($product['image_url']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="product-image me-2">
                                                        <?php endif; ?>
                                                        <?php echo htmlspecialchars($product['name']); ?>
                                                    </td>
                                                    <td>$<?php echo number_format($product['price'], 2); ?></td>
                                                    <td>
                                                        <span class="badge 
                                                            <?php echo $product['status'] === 'active' ? 'bg-success' : ($product['status'] === 'pending' ? 'bg-warning' : 'bg-secondary'); ?>">
                                                            <?php echo ucfirst($product['status']); ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-muted text-center">No products found for this seller.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Recent Streams -->
                <div class="col-md-6">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex justify-content-between align-items-center">
                            <h6 class="m-0 font-weight-bold text-primary">Recent Streams</h6>
                            <a href="#" class="btn btn-sm btn-primary">View All</a>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($recent_streams)): ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <thead>
                                            <tr>
                                                <th>Title</th>
                                                <th>Status</th>
                                                <th>Viewers</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_streams as $stream): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($stream['title']); ?></td>
                                                    <td>
                                                        <span class="badge 
                                                            <?php 
                                                                if ($stream['status'] === 'live') echo 'stream-status-live';
                                                                elseif ($stream['status'] === 'ended') echo 'stream-status-ended';
                                                                else echo 'stream-status-scheduled';
                                                            ?>">
                                                            <?php echo ucfirst($stream['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo number_format($stream['viewer_count']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-muted text-center">No streams found for this seller.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Orders -->
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">Recent Orders</h6>
                    <a href="orders.php?seller=<?php echo $seller['id']; ?>" class="btn btn-sm btn-primary">View All</a>
                </div>
                <div class="card-body">
                    <?php if (!empty($recent_orders)): ?>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Order ID</th>
                                        <th>Product</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_orders as $order): ?>
                                        <tr>
                                            <td>#<?php echo $order['id']; ?></td>
                                            <td><?php echo htmlspecialchars($order['product_name']); ?></td>
                                            <td>$<?php echo number_format($order['total_amount'], 2); ?></td>
                                            <td>
                                                <span class="badge 
                                                    <?php echo $order['status'] === 'completed' ? 'bg-success' : ($order['status'] === 'pending' ? 'bg-warning' : 'bg-secondary'); ?>">
                                                    <?php echo ucfirst($order['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M j, Y', strtotime($order['created_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-muted text-center">No orders found for this seller.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sidebar Toggle
        document.getElementById('sidebarCollapse').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
        });
    </script>
</body>
</html>