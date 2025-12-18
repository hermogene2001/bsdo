<?php
session_start();
require_once '../config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Get date range parameters
$date_range = isset($_GET['date_range']) ? $_GET['date_range'] : '30days';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

// Set default date range if not provided
if (empty($start_date) || empty($end_date)) {
    switch ($date_range) {
        case '7days':
            $start_date = date('Y-m-d', strtotime('-7 days'));
            $end_date = date('Y-m-d');
            break;
        case '30days':
            $start_date = date('Y-m-d', strtotime('-30 days'));
            $end_date = date('Y-m-d');
            break;
        case '90days':
            $start_date = date('Y-m-d', strtotime('-90 days'));
            $end_date = date('Y-m-d');
            break;
        case '1year':
            $start_date = date('Y-m-d', strtotime('-1 year'));
            $end_date = date('Y-m-d');
            break;
        default:
            $start_date = date('Y-m-d', strtotime('-30 days'));
            $end_date = date('Y-m-d');
    }
}

// Overall Statistics - FIXED QUERY
$stats_stmt = $pdo->prepare("
    SELECT 
    COUNT(DISTINCT o.id) as total_orders,
    (SELECT COUNT(DISTINCT id) FROM users WHERE role = 'client') as total_customers,
    (SELECT COUNT(DISTINCT id) FROM users WHERE role = 'seller') as total_sellers,
    (SELECT COUNT(DISTINCT id) FROM products WHERE status = 'active') as total_products,
    COALESCE(SUM(CASE WHEN o.status = 'completed' THEN o.total_amount ELSE 0 END), 0) as total_revenue,
    COALESCE(AVG(CASE WHEN o.status = 'completed' THEN o.total_amount ELSE NULL END), 0) as avg_order_value,
    COUNT(DISTINCT CASE WHEN o.status = 'completed' THEN o.user_id END) as repeat_customers
FROM orders o
WHERE o.created_at BETWEEN ? AND ?;

");

$stats_stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
$overall_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Revenue Trends (Last 12 months)
$revenue_trends_stmt = $pdo->prepare("
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        COALESCE(SUM(CASE WHEN status = 'completed' THEN total_amount ELSE 0 END), 0) as revenue
    FROM orders 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month
");

$revenue_trends_stmt->execute();
$revenue_trends = $revenue_trends_stmt->fetchAll(PDO::FETCH_ASSOC);

// Daily Revenue (Last 30 days)
$daily_revenue_stmt = $pdo->prepare("
    SELECT 
        DATE(created_at) as date,
        COALESCE(SUM(CASE WHEN status = 'completed' THEN total_amount ELSE 0 END), 0) as revenue,
        COUNT(*) as orders
    FROM orders 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY DATE(created_at)
    ORDER BY date
");

$daily_revenue_stmt->execute();
$daily_revenue = $daily_revenue_stmt->fetchAll(PDO::FETCH_ASSOC);

// Top Selling Products - FIXED QUERY
$top_products_stmt = $pdo->prepare("
    SELECT 
        p.name,
        p.price,
        COUNT(oi.id) as units_sold,
        COALESCE(SUM(oi.quantity * oi.price), 0) as revenue,
        u.first_name as seller_first_name,
        u.last_name as seller_last_name
    FROM products p
    LEFT JOIN order_items oi ON p.id = oi.product_id
    LEFT JOIN orders o ON oi.order_id = o.id AND o.status = 'completed'
    LEFT JOIN users u ON p.seller_id = u.id
    WHERE o.created_at BETWEEN ? AND ? OR o.created_at IS NULL
    GROUP BY p.id
    ORDER BY revenue DESC
    LIMIT 10
");

$top_products_stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
$top_products = $top_products_stmt->fetchAll(PDO::FETCH_ASSOC);

// Top Customers - FIXED QUERY
$top_customers_stmt = $pdo->prepare("
    SELECT 
        u.first_name,
        u.last_name,
        u.email,
        COUNT(o.id) as orders_count,
        COALESCE(SUM(o.total_amount), 0) as total_spent,
        MAX(o.created_at) as last_order_date
    FROM users u
    LEFT JOIN orders o ON u.id = o.user_id AND o.status = 'completed' AND o.created_at BETWEEN ? AND ?
    WHERE u.role = 'client'
    GROUP BY u.id
    ORDER BY total_spent DESC
    LIMIT 10
");

$top_customers_stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
$top_customers = $top_customers_stmt->fetchAll(PDO::FETCH_ASSOC);

// Top Sellers - FIXED QUERY
$top_sellers_stmt = $pdo->prepare("
    SELECT 
        u.first_name,
        u.last_name,
        u.email,
        u.store_name,
        COUNT(DISTINCT p.id) as products_count,
        COUNT(oi.id) as units_sold,
        COALESCE(SUM(oi.quantity * oi.price), 0) as revenue,
        COALESCE(SUM(oi.quantity * oi.price * 0.1), 0) as commission
    FROM users u
    LEFT JOIN products p ON u.id = p.seller_id
    LEFT JOIN order_items oi ON p.id = oi.product_id
    LEFT JOIN orders o ON oi.order_id = o.id AND o.status = 'completed' AND o.created_at BETWEEN ? AND ?
    WHERE u.role = 'seller'
    GROUP BY u.id
    ORDER BY revenue DESC
    LIMIT 10
");

$top_sellers_stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
$top_sellers = $top_sellers_stmt->fetchAll(PDO::FETCH_ASSOC);

// Order Status Distribution
$order_status_stmt = $pdo->prepare("
    SELECT 
        status,
        COUNT(*) as count,
        COALESCE(SUM(total_amount), 0) as amount
    FROM orders 
    WHERE created_at BETWEEN ? AND ?
    GROUP BY status
");

$order_status_stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
$order_statuses = $order_status_stmt->fetchAll(PDO::FETCH_ASSOC);

// Customer Acquisition Trends
$customer_acquisition_stmt = $pdo->prepare("
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        COUNT(*) as new_customers
    FROM users 
    WHERE role = 'client' AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month
");

$customer_acquisition_stmt->execute();
$customer_acquisition = $customer_acquisition_stmt->fetchAll(PDO::FETCH_ASSOC);

// Sales by Category - FIXED QUERY
$sales_by_category_stmt = $pdo->prepare("
    SELECT 
        c.name as category_name,
        COUNT(oi.id) as units_sold,
        COALESCE(SUM(oi.quantity * oi.price), 0) as revenue
    FROM categories c
    LEFT JOIN products p ON c.id = p.category_id
    LEFT JOIN order_items oi ON p.id = oi.product_id
    LEFT JOIN orders o ON oi.order_id = o.id AND o.status = 'completed' AND o.created_at BETWEEN ? AND ?
    GROUP BY c.id
    ORDER BY revenue DESC
");

$sales_by_category_stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
$sales_by_category = $sales_by_category_stmt->fetchAll(PDO::FETCH_ASSOC);

$performance_metrics_stmt = $pdo->prepare("
    SELECT 
        -- Conversion Rate (simplified)
        (SELECT COUNT(DISTINCT o.user_id) FROM orders o 
         WHERE o.status = 'completed' AND o.created_at BETWEEN ? AND ?) / 
        GREATEST((SELECT COUNT(DISTINCT u.id) FROM users u 
                  WHERE u.role = 'client' AND u.created_at BETWEEN ? AND ?), 1) * 100 as conversion_rate,
        
        -- Average Order Value
        COALESCE((SELECT AVG(o.total_amount) FROM orders o 
                  WHERE o.status = 'completed' AND o.created_at BETWEEN ? AND ?), 0) as avg_order_value,
        
        -- Customer Lifetime Value (estimated)
        COALESCE((SELECT SUM(o.total_amount) FROM orders o 
                  WHERE o.status = 'completed' AND o.created_at BETWEEN ? AND ?) / 
        GREATEST((SELECT COUNT(DISTINCT o.user_id) FROM orders o 
                  WHERE o.status = 'completed' AND o.created_at BETWEEN ? AND ?), 1), 0) as avg_clv,
        
        -- Repeat Customer Rate
        (SELECT COUNT(DISTINCT o.user_id) FROM orders o 
         WHERE o.status = 'completed' AND o.created_at BETWEEN ? AND ?
           AND o.user_id IN (
             SELECT o2.user_id FROM orders o2 
             WHERE o2.status = 'completed' AND o2.created_at BETWEEN ? AND ?
             GROUP BY o2.user_id HAVING COUNT(*) > 1
         )) / 
        GREATEST((SELECT COUNT(DISTINCT o.user_id) FROM orders o 
                  WHERE o.status = 'completed' AND o.created_at BETWEEN ? AND ?), 1) * 100 as repeat_customer_rate
");

$performance_metrics_stmt->execute([
    $start_date . ' 00:00:00', $end_date . ' 23:59:59', // 1–2
    $start_date . ' 00:00:00', $end_date . ' 23:59:59', // 3–4
    $start_date . ' 00:00:00', $end_date . ' 23:59:59', // 5–6
    $start_date . ' 00:00:00', $end_date . ' 23:59:59', // 7–8
    $start_date . ' 00:00:00', $end_date . ' 23:59:59', // 9–10
    $start_date . ' 00:00:00', $end_date . ' 23:59:59', // 11–12
    $start_date . ' 00:00:00', $end_date . ' 23:59:59', // 13–14
    $start_date . ' 00:00:00', $end_date . ' 23:59:59'  // 15–16
]);

$performance_metrics = $performance_metrics_stmt->fetch(PDO::FETCH_ASSOC);

// Log admin activity
logAdminActivity("Accessed analytics page");

function logAdminActivity($activity) {
    global $pdo;
    if (isset($_SESSION['user_id'])) {
        $stmt = $pdo->prepare("INSERT INTO admin_activities (admin_id, activity, ip_address) VALUES (?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], $activity, $_SERVER['REMOTE_ADDR']]);
    }
}

// Helper function to format numbers with K/M suffixes
function formatNumber($number) {
    if ($number >= 1000000) {
        return number_format($number / 1000000, 1) . 'M';
    } elseif ($number >= 1000) {
        return number_format($number / 1000, 1) . 'K';
    }
    return number_format($number);
}

// Helper function to get growth percentage
function getGrowthPercentage($current, $previous) {
    if ($previous == 0) return $current > 0 ? 100 : 0;
    return round((($current - $previous) / $previous) * 100, 1);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics - BSDO Sale Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        
        .stats-card {
            border-left: 4px solid;
            transition: transform 0.2s;
        }
        
        .stats-card:hover {
            transform: translateY(-2px);
        }
        
        .stats-card.primary {
            border-left-color: var(--primary-color);
        }
        
        .stats-card.success {
            border-left-color: var(--secondary-color);
        }
        
        .stats-card.warning {
            border-left-color: var(--warning-color);
        }
        
        .stats-card.danger {
            border-left-color: var(--danger-color);
        }
        
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }
        
        .metric-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .growth-positive {
            color: var(--secondary-color);
        }
        
        .growth-negative {
            color: var(--danger-color);
        }
        
        .table th {
            border-top: none;
            font-weight: 700;
        }
        
        .sidebar-toggler {
            cursor: pointer;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        
        .progress {
            height: 8px;
            margin: 5px 0;
        }
        
        .analytics-filter {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: #6c757d;
        }
        
        .no-data i {
            font-size: 48px;
            margin-bottom: 15px;
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
                <li>
                    <a href="sellers.php"><i class="fas fa-store"></i> Sellers</a>
                </li>
                <li>
                    <a href="orders.php"><i class="fas fa-shopping-cart"></i> Orders</a>
                </li>
                <li class="active">
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
                <h1 class="h3 mb-0 text-gray-800">Business Analytics</h1>
                <div class="d-flex gap-2">
                    <button class="btn btn-primary" onclick="window.print()">
                        <i class="fas fa-print fa-sm text-white-50"></i> Print Report
                    </button>
                    <button class="btn btn-success" onclick="exportAnalytics()">
                        <i class="fas fa-download fa-sm text-white-50"></i> Export Data
                    </button>
                </div>
            </div>

            <!-- Date Range Filter -->
            <div class="analytics-filter">
                <form method="GET" action="analytics.php" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Quick Range</label>
                        <select class="form-control" name="date_range" onchange="this.form.submit()">
                            <option value="7days" <?php echo $date_range === '7days' ? 'selected' : ''; ?>>Last 7 Days</option>
                            <option value="30days" <?php echo $date_range === '30days' ? 'selected' : ''; ?>>Last 30 Days</option>
                            <option value="90days" <?php echo $date_range === '90days' ? 'selected' : ''; ?>>Last 90 Days</option>
                            <option value="1year" <?php echo $date_range === '1year' ? 'selected' : ''; ?>>Last 1 Year</option>
                            <option value="custom" <?php echo $date_range === 'custom' ? 'selected' : ''; ?>>Custom Range</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">Start Date</label>
                        <input type="date" class="form-control" name="start_date" value="<?php echo $start_date; ?>" 
                               <?php echo $date_range !== 'custom' ? 'readonly' : ''; ?>>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">End Date</label>
                        <input type="date" class="form-control" name="end_date" value="<?php echo $end_date; ?>" 
                               <?php echo $date_range !== 'custom' ? 'readonly' : ''; ?>>
                    </div>
                    
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">Apply Filter</button>
                    </div>
                </form>
            </div>

            <!-- Key Metrics -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stats-card primary h-100">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Revenue</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">$<?php echo number_format($overall_stats['total_revenue'], 2); ?></div>
                                    <div class="mt-2 mb-0 text-muted text-xs">
                                        <span>Period: <?php echo $start_date . ' to ' . $end_date; ?></span>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stats-card success h-100">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Orders</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($overall_stats['total_orders']); ?></div>
                                    <div class="mt-2 mb-0 text-muted text-xs">
                                        <span>Completed: <?php echo number_format($overall_stats['repeat_customers']); ?></span>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-shopping-cart fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stats-card warning h-100">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Avg Order Value</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">$<?php echo number_format($overall_stats['avg_order_value'], 2); ?></div>
                                    <div class="mt-2 mb-0 text-muted text-xs">
                                        <span>Based on completed orders</span>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-chart-line fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stats-card danger h-100">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Platform Stats</div>
                                    <div class="h6 mb-0 font-weight-bold text-gray-800">
                                        Customers: <?php echo number_format($overall_stats['total_customers']); ?>
                                    </div>
                                    <div class="h6 mb-0 font-weight-bold text-gray-800">
                                        Sellers: <?php echo number_format($overall_stats['total_sellers']); ?>
                                    </div>
                                    <div class="h6 mb-0 font-weight-bold text-gray-800">
                                        Products: <?php echo number_format($overall_stats['total_products']); ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-users fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row 1 -->
            <div class="row mb-4">
                <div class="col-xl-8 col-lg-7">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Revenue Trends (Last 12 Months)</h6>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="revenueTrendsChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-4 col-lg-5">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Sales by Category</h6>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($sales_by_category) && array_sum(array_column($sales_by_category, 'revenue')) > 0): ?>
                                <div class="chart-container">
                                    <canvas id="salesByCategoryChart"></canvas>
                                </div>
                            <?php else: ?>
                                <div class="no-data">
                                    <i class="fas fa-chart-pie"></i>
                                    <p>No category sales data available</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row 2 -->
            <div class="row mb-4">
                <div class="col-xl-6 col-lg-6">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Order Status Distribution</h6>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($order_statuses)): ?>
                                <div class="chart-container">
                                    <canvas id="orderStatusChart"></canvas>
                                </div>
                            <?php else: ?>
                                <div class="no-data">
                                    <i class="fas fa-chart-bar"></i>
                                    <p>No order data available for the selected period</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-xl-6 col-lg-6">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Customer Acquisition</h6>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($customer_acquisition)): ?>
                                <div class="chart-container">
                                    <canvas id="customerAcquisitionChart"></canvas>
                                </div>
                            <?php else: ?>
                                <div class="no-data">
                                    <i class="fas fa-user-plus"></i>
                                    <p>No customer acquisition data available</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Performance Metrics -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card shadow">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Performance Metrics</h6>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-md-3 mb-3">
                                    <div class="metric-card">
                                        <div class="h4"><?php echo number_format($performance_metrics['conversion_rate'], 1); ?>%</div>
                                        <div class="small">Conversion Rate</div>
                                    </div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <div class="metric-card">
                                        <div class="h4">$<?php echo number_format($performance_metrics['avg_order_value'], 2); ?></div>
                                        <div class="small">Average Order Value</div>
                                    </div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <div class="metric-card">
                                        <div class="h4">$<?php echo number_format($performance_metrics['avg_clv'], 2); ?></div>
                                        <div class="small">Customer Lifetime Value</div>
                                    </div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <div class="metric-card">
                                        <div class="h4"><?php echo number_format($performance_metrics['repeat_customer_rate'], 1); ?>%</div>
                                        <div class="small">Repeat Customer Rate</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Top Performers -->
            <div class="row">
                <div class="col-xl-4 col-lg-6">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex justify-content-between align-items-center">
                            <h6 class="m-0 font-weight-bold text-primary">Top Selling Products</h6>
                            <span class="badge bg-primary">Top 10</span>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($top_products)): ?>
                                <div class="table-responsive">
                                    <table class="table table-borderless table-hover">
                                        <thead>
                                            <tr>
                                                <th>Product</th>
                                                <th>Revenue</th>
                                                <th>Units</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($top_products as $product): ?>
                                                <tr>
                                                    <td>
                                                        <div class="fw-bold"><?php echo htmlspecialchars($product['name']); ?></div>
                                                        <div class="small text-muted">$<?php echo number_format($product['price'], 2); ?></div>
                                                    </td>
                                                    <td class="text-success fw-bold">$<?php echo number_format($product['revenue'], 2); ?></td>
                                                    <td><?php echo number_format($product['units_sold']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="no-data">
                                    <i class="fas fa-box"></i>
                                    <p>No product sales data available</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-xl-4 col-lg-6">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex justify-content-between align-items-center">
                            <h6 class="m-0 font-weight-bold text-primary">Top Customers</h6>
                            <span class="badge bg-primary">Top 10</span>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($top_customers)): ?>
                                <div class="table-responsive">
                                    <table class="table table-borderless table-hover">
                                        <thead>
                                            <tr>
                                                <th>Customer</th>
                                                <th>Orders</th>
                                                <th>Spent</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($top_customers as $customer): ?>
                                                <tr>
                                                    <td>
                                                        <div class="fw-bold"><?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?></div>
                                                        <div class="small text-muted"><?php echo htmlspecialchars($customer['email']); ?></div>
                                                    </td>
                                                    <td><?php echo number_format($customer['orders_count']); ?></td>
                                                    <td class="text-success fw-bold">$<?php echo number_format($customer['total_spent'], 2); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="no-data">
                                    <i class="fas fa-users"></i>
                                    <p>No customer data available</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-xl-4 col-lg-6">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex justify-content-between align-items-center">
                            <h6 class="m-0 font-weight-bold text-primary">Top Sellers</h6>
                            <span class="badge bg-primary">Top 10</span>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($top_sellers)): ?>
                                <div class="table-responsive">
                                    <table class="table table-borderless table-hover">
                                        <thead>
                                            <tr>
                                                <th>Seller</th>
                                                <th>Revenue</th>
                                                <th>Commission</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($top_sellers as $seller): ?>
                                                <tr>
                                                    <td>
                                                        <div class="fw-bold"><?php echo htmlspecialchars($seller['store_name'] ?: $seller['first_name'] . ' ' . $seller['last_name']); ?></div>
                                                        <div class="small text-muted"><?php echo number_format($seller['products_count']); ?> products</div>
                                                    </td>
                                                    <td class="text-success fw-bold">$<?php echo number_format($seller['revenue'], 2); ?></td>
                                                    <td class="text-warning fw-bold">$<?php echo number_format($seller['commission'], 2); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="no-data">
                                    <i class="fas fa-store"></i>
                                    <p>No seller data available</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
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

        // Revenue Trends Chart
        <?php if (!empty($revenue_trends)): ?>
        const revenueCtx = document.getElementById('revenueTrendsChart').getContext('2d');
        const revenueTrendsChart = new Chart(revenueCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($revenue_trends, 'month')); ?>,
                datasets: [{
                    label: 'Revenue',
                    data: <?php echo json_encode(array_column($revenue_trends, 'revenue')); ?>,
                    borderColor: '#4e73df',
                    backgroundColor: 'rgba(78, 115, 223, 0.1)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '$' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
        <?php endif; ?>

        // Sales by Category Chart
        <?php if (!empty($sales_by_category) && array_sum(array_column($sales_by_category, 'revenue')) > 0): ?>
        const categoryCtx = document.getElementById('salesByCategoryChart').getContext('2d');
        const salesByCategoryChart = new Chart(categoryCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_column($sales_by_category, 'category_name')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($sales_by_category, 'revenue')); ?>,
                    backgroundColor: [
                        '#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b',
                        '#858796', '#5a5c69', '#6f42c1', '#e83e8c', '#fd7e14'
                    ],
                    hoverBackgroundColor: [
                        '#2e59d9', '#17a673', '#2c9faf', '#dda20a', '#be2617',
                        '#6b6d7d', '#484a54', '#59359f', '#d91a72', '#dc6502'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                    }
                },
                cutout: '60%',
            }
        });
        <?php endif; ?>

        // Order Status Chart
        <?php if (!empty($order_statuses)): ?>
        const statusCtx = document.getElementById('orderStatusChart').getContext('2d');
        const orderStatusChart = new Chart(statusCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($order_statuses, 'status')); ?>,
                datasets: [{
                    label: 'Number of Orders',
                    data: <?php echo json_encode(array_column($order_statuses, 'count')); ?>,
                    backgroundColor: '#4e73df',
                    borderColor: '#2e59d9',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
        <?php endif; ?>

        // Customer Acquisition Chart
        <?php if (!empty($customer_acquisition)): ?>
        const acquisitionCtx = document.getElementById('customerAcquisitionChart').getContext('2d');
        const customerAcquisitionChart = new Chart(acquisitionCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($customer_acquisition, 'month')); ?>,
                datasets: [{
                    label: 'New Customers',
                    data: <?php echo json_encode(array_column($customer_acquisition, 'new_customers')); ?>,
                    borderColor: '#1cc88a',
                    backgroundColor: 'rgba(28, 200, 138, 0.1)',
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
        <?php endif; ?>

        // Export analytics data
        function exportAnalytics() {
            const data = {
                date_range: '<?php echo $date_range; ?>',
                start_date: '<?php echo $start_date; ?>',
                end_date: '<?php echo $end_date; ?>',
                overall_stats: <?php echo json_encode($overall_stats); ?>,
                performance_metrics: <?php echo json_encode($performance_metrics); ?>
            };
            
            const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `analytics-${new Date().toISOString().split('T')[0]}.json`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        }

        // Handle custom date range selection
        document.querySelector('select[name="date_range"]').addEventListener('change', function() {
            const isCustom = this.value === 'custom';
            document.querySelector('input[name="start_date"]').readOnly = !isCustom;
            document.querySelector('input[name="end_date"]').readOnly = !isCustom;
            
            if (!isCustom) {
                this.form.submit();
            }
        });
    </script>
</body>
</html>