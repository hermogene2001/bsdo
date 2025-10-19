<?php
session_start();
require_once '../config.php';

// Check if user is logged in and is seller
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'seller') {
    header('Location: ../login.php');
    exit();
}

$seller_id = $_SESSION['user_id'];

// Get date range parameters
$period = isset($_GET['period']) ? $_GET['period'] : '30days';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Set date range based on period
$date_condition = "";
$params = [$seller_id];

switch ($period) {
    case '7days':
        $date_condition = "AND o.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        break;
    case '30days':
        $date_condition = "AND o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        break;
    case '90days':
        $date_condition = "AND o.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)";
        break;
    case 'custom':
        if (!empty($date_from) && !empty($date_to)) {
            $date_condition = "AND DATE(o.created_at) BETWEEN ? AND ?";
            $params[] = $date_from;
            $params[] = $date_to;
        }
        break;
    case 'all':
    default:
        $date_condition = "";
        break;
}

// Main Analytics Query
$analytics_stmt = $pdo->prepare("
    SELECT 
        -- Revenue Metrics
        COALESCE(SUM(CASE WHEN o.status = 'completed' THEN oi.quantity * oi.price ELSE 0 END), 0) as total_revenue,
        COALESCE(SUM(CASE WHEN o.status = 'completed' THEN oi.quantity * oi.price * 0.1 ELSE 0 END), 0) as total_commission,
        COALESCE(SUM(CASE WHEN o.status = 'completed' THEN oi.quantity * oi.price * 0.9 ELSE 0 END), 0) as net_revenue,
        
        -- Order Metrics
        COUNT(DISTINCT o.id) as total_orders,
        COUNT(DISTINCT CASE WHEN o.status = 'completed' THEN o.id END) as completed_orders,
        COUNT(DISTINCT CASE WHEN o.status = 'pending' THEN o.id END) as pending_orders,
        COUNT(DISTINCT CASE WHEN o.status = 'processing' THEN o.id END) as processing_orders,
        
        -- Product Metrics
        COUNT(DISTINCT p.id) as total_products,
        COUNT(DISTINCT CASE WHEN p.status = 'active' THEN p.id END) as active_products,
        COALESCE(SUM(oi.quantity), 0) as total_units_sold,
        
        -- Customer Metrics
        COUNT(DISTINCT o.user_id) as total_customers,
        
        -- Average Order Value
        CASE 
            WHEN COUNT(DISTINCT o.id) > 0 THEN 
                COALESCE(SUM(CASE WHEN o.status = 'completed' THEN oi.quantity * oi.price ELSE 0 END), 0) / COUNT(DISTINCT CASE WHEN o.status = 'completed' THEN o.id END)
            ELSE 0 
        END as average_order_value,
        
        -- Conversion Rate (Orders per Customer)
        CASE 
            WHEN COUNT(DISTINCT o.user_id) > 0 THEN 
                COUNT(DISTINCT o.id) / COUNT(DISTINCT o.user_id)
            ELSE 0 
        END as orders_per_customer
        
    FROM products p
    LEFT JOIN order_items oi ON p.id = oi.product_id
    LEFT JOIN orders o ON oi.order_id = o.id
    WHERE p.seller_id = ? $date_condition
");

$analytics_stmt->execute($params);
$analytics = $analytics_stmt->fetch(PDO::FETCH_ASSOC);

// Sales Trend Data (Last 30 days by default)
$trend_stmt = $pdo->prepare("
    SELECT 
        DATE(o.created_at) as date,
        COUNT(DISTINCT o.id) as daily_orders,
        COALESCE(SUM(oi.quantity * oi.price), 0) as daily_revenue,
        COALESCE(SUM(oi.quantity), 0) as daily_units_sold,
        COUNT(DISTINCT o.user_id) as daily_customers
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    JOIN products p ON oi.product_id = p.id
    WHERE p.seller_id = ? AND o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY DATE(o.created_at)
    ORDER BY date
");

$trend_stmt->execute([$seller_id]);
$sales_trend = $trend_stmt->fetchAll(PDO::FETCH_ASSOC);

// If no trend data, create empty structure for chart
if (empty($sales_trend)) {
    $sales_trend = [];
    for ($i = 29; $i >= 0; $i--) {
        $sales_trend[] = [
            'date' => date('Y-m-d', strtotime("-$i days")),
            'daily_orders' => 0,
            'daily_revenue' => 0,
            'daily_units_sold' => 0,
            'daily_customers' => 0
        ];
    }
}

// Top Selling Products
$top_products_stmt = $pdo->prepare("
    SELECT 
        p.id,
        p.name,
        p.price,
        p.image_url,
        COUNT(DISTINCT o.id) as order_count,
        COALESCE(SUM(oi.quantity), 0) as units_sold,
        COALESCE(SUM(oi.quantity * oi.price), 0) as total_revenue,
        CASE 
            WHEN COALESCE(SUM(oi.quantity), 0) > 0 THEN 
                COALESCE(SUM(oi.quantity * oi.price), 0) / COALESCE(SUM(oi.quantity), 1)
            ELSE p.price 
        END as average_sale_price
    FROM products p
    LEFT JOIN order_items oi ON p.id = oi.product_id
    LEFT JOIN orders o ON oi.order_id = o.id AND o.status = 'completed'
    WHERE p.seller_id = ?
    GROUP BY p.id
    ORDER BY total_revenue DESC
    LIMIT 10
");

$top_products_stmt->execute([$seller_id]);
$top_products = $top_products_stmt->fetchAll(PDO::FETCH_ASSOC);

// Customer Analytics
$customers_stmt = $pdo->prepare("
    SELECT 
        u.id,
        u.first_name,
        u.last_name,
        u.email,
        COUNT(DISTINCT o.id) as order_count,
        COALESCE(SUM(oi.quantity * oi.price), 0) as total_spent,
        MAX(o.created_at) as last_order_date
    FROM users u
    JOIN orders o ON u.id = o.user_id
    JOIN order_items oi ON o.id = oi.order_id
    JOIN products p ON oi.product_id = p.id
    WHERE p.seller_id = ? AND o.status = 'completed'
    GROUP BY u.id
    ORDER BY total_spent DESC
    LIMIT 10
");

$customers_stmt->execute([$seller_id]);
$top_customers = $customers_stmt->fetchAll(PDO::FETCH_ASSOC);

// Category Performance
$categories_stmt = $pdo->prepare("
    SELECT 
        c.id,
        c.name,
        COUNT(DISTINCT p.id) as product_count,
        COUNT(DISTINCT o.id) as order_count,
        COALESCE(SUM(oi.quantity), 0) as units_sold,
        COALESCE(SUM(oi.quantity * oi.price), 0) as total_revenue
    FROM categories c
    LEFT JOIN products p ON c.id = p.category_id
    LEFT JOIN order_items oi ON p.id = oi.product_id
    LEFT JOIN orders o ON oi.order_id = o.id AND o.status = 'completed'
    WHERE p.seller_id = ?
    GROUP BY c.id
    ORDER BY total_revenue DESC
");

$categories_stmt->execute([$seller_id]);
$category_performance = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);

// Monthly Comparison
$monthly_stmt = $pdo->prepare("
    SELECT 
        DATE_FORMAT(o.created_at, '%Y-%m') as month,
        COUNT(DISTINCT o.id) as order_count,
        COALESCE(SUM(oi.quantity * oi.price), 0) as revenue,
        COALESCE(SUM(oi.quantity), 0) as units_sold
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    JOIN products p ON oi.product_id = p.id
    WHERE p.seller_id = ? AND o.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(o.created_at, '%Y-%m')
    ORDER BY month DESC
    LIMIT 6
");

$monthly_stmt->execute([$seller_id]);
$monthly_data = $monthly_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get seller information
$seller_info_stmt = $pdo->prepare("SELECT first_name, last_name, store_name FROM users WHERE id = ?");
$seller_info_stmt->execute([$seller_id]);
$seller_info = $seller_info_stmt->fetch(PDO::FETCH_ASSOC);

// Log seller activity
logSellerActivity("Accessed analytics page");

function logSellerActivity($activity) {
    global $pdo, $seller_id;
    $stmt = $pdo->prepare("INSERT INTO seller_activities (seller_id, activity, ip_address) VALUES (?, ?, ?)");
    $stmt->execute([$seller_id, $activity, $_SERVER['REMOTE_ADDR']]);
}

function formatCurrency($amount) {
    return '$' . number_format($amount, 2);
}

function formatPercent($value) {
    return number_format($value * 100, 1) . '%';
}

function getGrowthClass($value) {
    if ($value > 0) return 'text-success';
    if ($value < 0) return 'text-danger';
    return 'text-muted';
}

function getGrowthIcon($value) {
    if ($value > 0) return 'fas fa-arrow-up';
    if ($value < 0) return 'fas fa-arrow-down';
    return 'fas fa-minus';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Analytics - BSDO Sale</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #1cc88a;
            --warning-color: #f6c23e;
            --danger-color: #e74a3b;
            --info-color: #36b9cc;
            --dark-color: #2e3a59;
        }
        
        body {
            font-family: 'Nunito', sans-serif;
            background-color: #f8f9fc;
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
            border-left: 4px solid;
            transition: transform 0.2s;
            border-radius: 10px;
        }
        
        .stats-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        
        .stats-card.primary { border-left-color: var(--primary-color); }
        .stats-card.success { border-left-color: var(--secondary-color); }
        .stats-card.warning { border-left-color: var(--warning-color); }
        .stats-card.danger { border-left-color: var(--danger-color); }
        .stats-card.info { border-left-color: var(--info-color); }
        
        .chart-container {
            height: 300px;
            position: relative;
        }
        
        .progress {
            height: 8px;
        }
        
        .analytics-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .metric-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .growth-badge {
            font-size: 0.8em;
            padding: 0.25rem 0.5rem;
        }
        
        .product-image {
            width: 40px;
            height: 40px;
            object-fit: cover;
            border-radius: 5px;
        }
        
        .no-data-placeholder {
            text-align: center;
            padding: 3rem;
            color: #6c757d;
        }
        
        .no-data-placeholder i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
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
                            <span class="text-primary fw-bold"><?php echo strtoupper(substr($seller_info['first_name'], 0, 1)); ?></span>
                        </div>
                        <span><?php echo htmlspecialchars($seller_info['first_name'] . ' ' . $seller_info['last_name']); ?></span>
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
                            <a class="nav-link" href="dashboard.php">
                                <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="products.php">
                                <i class="fas fa-box me-2"></i>Products
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="orders.php">
                                <i class="fas fa-shopping-cart me-2"></i>Orders
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="analytics.php">
                                <i class="fas fa-chart-bar me-2"></i>Analytics
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="profile.php">
                                <i class="fas fa-user me-2"></i>Profile
                            </a>
                        </li>
                        <li class="nav-item">
    <a class="nav-link" href="rental_products.php">
        <i class="fas fa-calendar-alt me-2"></i>Rental Products
    </a>
</li>
                        <li class="nav-item">
                            <a class="nav-link" href="../logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i>Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-lg-10 col-md-12 p-4">
                <!-- Analytics Header -->
                <div class="analytics-header">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h2 class="mb-2"><i class="fas fa-chart-line me-2"></i>Sales Analytics</h2>
                            <p class="mb-0 opacity-75">Track your store performance and sales metrics</p>
                        </div>
                        <div class="col-md-4 text-end">
                            <form method="GET" class="row g-2">
                                <div class="col-6">
                                    <select name="period" class="form-select" onchange="this.form.submit()">
                                        <option value="7days" <?php echo $period === '7days' ? 'selected' : ''; ?>>Last 7 Days</option>
                                        <option value="30days" <?php echo $period === '30days' ? 'selected' : ''; ?>>Last 30 Days</option>
                                        <option value="90days" <?php echo $period === '90days' ? 'selected' : ''; ?>>Last 90 Days</option>
                                        <option value="all" <?php echo $period === 'all' ? 'selected' : ''; ?>>All Time</option>
                                        <option value="custom" <?php echo $period === 'custom' ? 'selected' : ''; ?>>Custom Range</option>
                                    </select>
                                </div>
                                <?php if ($period === 'custom'): ?>
                                <div class="col-3">
                                    <input type="date" name="date_from" class="form-control" value="<?php echo $date_from; ?>" placeholder="From">
                                </div>
                                <div class="col-3">
                                    <input type="date" name="date_to" class="form-control" value="<?php echo $date_to; ?>" placeholder="To">
                                </div>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Check if there's any data -->
                <?php if ($analytics['total_orders'] == 0 && $analytics['total_products'] == 0): ?>
                    <div class="metric-card">
                        <div class="no-data-placeholder">
                            <i class="fas fa-chart-bar"></i>
                            <h4>No Analytics Data Available</h4>
                            <p class="mb-3">You need to have products and sales to see analytics data.</p>
                            <div class="d-flex gap-2 justify-content-center">
                                <a href="products.php" class="btn btn-primary">
                                    <i class="fas fa-plus me-2"></i>Add Products
                                </a>
                                <a href="dashboard.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-tachometer-alt me-2"></i>Back to Dashboard
                                </a>
                            </div>
                        </div>
                    </div>
                <?php else: ?>

                <!-- Key Metrics -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stats-card success h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <div class="text-muted small">Total Revenue</div>
                                        <div class="h4 font-weight-bold"><?php echo formatCurrency($analytics['total_revenue']); ?></div>
                                        <div class="small text-muted">Net: <?php echo formatCurrency($analytics['net_revenue']); ?></div>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-dollar-sign fa-2x text-success opacity-25"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stats-card primary h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <div class="text-muted small">Total Orders</div>
                                        <div class="h4 font-weight-bold"><?php echo number_format($analytics['total_orders']); ?></div>
                                        <div class="small text-muted">
                                            <span class="text-success"><?php echo number_format($analytics['completed_orders']); ?> completed</span>
                                        </div>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-shopping-cart fa-2x text-primary opacity-25"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stats-card info h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <div class="text-muted small">Units Sold</div>
                                        <div class="h4 font-weight-bold"><?php echo number_format($analytics['total_units_sold']); ?></div>
                                        <div class="small text-muted">Active Products: <?php echo number_format($analytics['active_products']); ?></div>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-box fa-2x text-info opacity-25"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stats-card warning h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <div class="text-muted small">Avg. Order Value</div>
                                        <div class="h4 font-weight-bold"><?php echo formatCurrency($analytics['average_order_value']); ?></div>
                                        <div class="small text-muted"><?php echo number_format($analytics['orders_per_customer'], 1); ?> orders/customer</div>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-chart-bar fa-2x text-warning opacity-25"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Row -->
                <div class="row mb-4">
                    <div class="col-lg-8 mb-4">
                        <div class="metric-card">
                            <h5 class="card-title">Sales Trend (Last 30 Days)</h5>
                            <div class="chart-container">
                                <canvas id="salesTrendChart"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4 mb-4">
                        <div class="metric-card">
                            <h5 class="card-title">Order Status Distribution</h5>
                            <div class="chart-container">
                                <canvas id="orderStatusChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Products & Categories -->
                <div class="row mb-4">
                    <div class="col-lg-6 mb-4">
                        <div class="metric-card">
                            <h5 class="card-title">Top Selling Products</h5>
                            <?php if (!empty($top_products)): ?>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Product</th>
                                                <th>Price</th>
                                                <th>Sold</th>
                                                <th>Revenue</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($top_products as $product): ?>
                                                <tr>
                                                    <td>
                                                        <div class="fw-bold"><?php echo htmlspecialchars($product['name']); ?></div>
                                                    </td>
                                                    <td><?php echo formatCurrency($product['price']); ?></td>
                                                    <td><?php echo number_format($product['units_sold']); ?></td>
                                                    <td class="fw-bold text-success"><?php echo formatCurrency($product['total_revenue']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center text-muted py-3">
                                    <i class="fas fa-box-open fa-2x mb-2"></i>
                                    <p>No products with sales yet</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-lg-6 mb-4">
                        <div class="metric-card">
                            <h5 class="card-title">Category Performance</h5>
                            <?php if (!empty($category_performance)): ?>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Category</th>
                                                <th>Products</th>
                                                <th>Orders</th>
                                                <th>Revenue</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($category_performance as $category): ?>
                                                <tr>
                                                    <td class="fw-bold"><?php echo htmlspecialchars($category['name']); ?></td>
                                                    <td><?php echo number_format($category['product_count']); ?></td>
                                                    <td><?php echo number_format($category['order_count']); ?></td>
                                                    <td class="text-success"><?php echo formatCurrency($category['total_revenue']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center text-muted py-3">
                                    <i class="fas fa-tags fa-2x mb-2"></i>
                                    <p>No category data available</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Customer Analytics -->
                <div class="row">
                    <div class="col-12">
                        <div class="metric-card">
                            <h5 class="card-title">Top Customers</h5>
                            <?php if (!empty($top_customers)): ?>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Customer</th>
                                                <th>Orders</th>
                                                <th>Total Spent</th>
                                                <th>Last Order</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($top_customers as $customer): ?>
                                                <tr>
                                                    <td>
                                                        <div class="fw-bold"><?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?></div>
                                                        <small class="text-muted"><?php echo htmlspecialchars($customer['email']); ?></small>
                                                    </td>
                                                    <td><?php echo number_format($customer['order_count']); ?></td>
                                                    <td class="fw-bold text-success"><?php echo formatCurrency($customer['total_spent']); ?></td>
                                                    <td>
                                                        <small class="text-muted">
                                                            <?php echo $customer['last_order_date'] ? date('M j, Y', strtotime($customer['last_order_date'])) : 'N/A'; ?>
                                                        </small>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center text-muted py-3">
                                    <i class="fas fa-users fa-2x mb-2"></i>
                                    <p>No customer data available yet</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sales Trend Chart
        const salesTrendCtx = document.getElementById('salesTrendChart').getContext('2d');
        const salesTrendChart = new Chart(salesTrendCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($sales_trend, 'date')); ?>,
                datasets: [{
                    label: 'Daily Revenue',
                    data: <?php echo json_encode(array_column($sales_trend, 'daily_revenue')); ?>,
                    borderColor: '#4e73df',
                    backgroundColor: 'rgba(78, 115, 223, 0.1)',
                    fill: true,
                    tension: 0.4,
                    yAxisID: 'y'
                }, {
                    label: 'Daily Orders',
                    data: <?php echo json_encode(array_column($sales_trend, 'daily_orders')); ?>,
                    borderColor: '#1cc88a',
                    borderDash: [5, 5],
                    fill: false,
                    tension: 0.4,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                scales: {
                    x: {
                        type: 'category',
                        title: {
                            display: true,
                            text: 'Date'
                        }
                    },
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Revenue ($)'
                        },
                        ticks: {
                            callback: function(value) {
                                return '$' + value;
                            }
                        }
                    },
                    y1: {
                        beginAtZero: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Orders'
                        },
                        grid: {
                            drawOnChartArea: false,
                        }
                    }
                }
            }
        });

        // Order Status Chart
        const orderStatusCtx = document.getElementById('orderStatusChart').getContext('2d');
        const orderStatusChart = new Chart(orderStatusCtx, {
            type: 'doughnut',
            data: {
                labels: ['Completed', 'Pending', 'Processing', 'Other'],
                datasets: [{
                    data: [
                        <?php echo $analytics['completed_orders']; ?>,
                        <?php echo $analytics['pending_orders']; ?>,
                        <?php echo $analytics['processing_orders']; ?>,
                        <?php echo max(0, $analytics['total_orders'] - $analytics['completed_orders'] - $analytics['pending_orders'] - $analytics['processing_orders']); ?>
                    ],
                    backgroundColor: [
                        '#1cc88a',
                        '#f6c23e',
                        '#4e73df',
                        '#6c757d'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Auto-refresh charts every 5 minutes
        setInterval(() => {
            salesTrendChart.update();
            orderStatusChart.update();
        }, 300000);
    </script>
</body>
</html>