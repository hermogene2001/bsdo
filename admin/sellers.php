<?php
session_start();
require_once '../config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Handle seller actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['seller_id'])) {
        $seller_id = intval($_POST['seller_id']);
        
        switch ($_POST['action']) {
            case 'approve':
                $stmt = $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ? AND role = 'seller'");
                if ($stmt->execute([$seller_id])) {
                    logAdminActivity("Approved seller ID: $seller_id");
                    $success_message = "Seller approved successfully!";
                } else {
                    $error_message = "Failed to approve seller.";
                }
                break;
                
            case 'reject':
                $stmt = $pdo->prepare("UPDATE users SET status = 'inactive' WHERE id = ? AND role = 'seller'");
                if ($stmt->execute([$seller_id])) {
                    logAdminActivity("Rejected seller ID: $seller_id");
                    $success_message = "Seller rejected successfully!";
                } else {
                    $error_message = "Failed to reject seller.";
                }
                break;
                
            case 'suspend':
                $stmt = $pdo->prepare("UPDATE users SET status = 'inactive' WHERE id = ? AND role = 'seller'");
                if ($stmt->execute([$seller_id])) {
                    logAdminActivity("Suspended seller ID: $seller_id");
                    $success_message = "Seller suspended successfully!";
                } else {
                    $error_message = "Failed to suspend seller.";
                }
                break;
                
            case 'activate':
                $stmt = $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ? AND role = 'seller'");
                if ($stmt->execute([$seller_id])) {
                    logAdminActivity("Activated seller ID: $seller_id");
                    $success_message = "Seller activated successfully!";
                } else {
                    $error_message = "Failed to activate seller.";
                }
                break;
                
            case 'delete':
                // Check if seller has products or orders before deletion
                $stmt = $pdo->prepare("SELECT COUNT(*) as product_count FROM products WHERE seller_id = ?");
                $stmt->execute([$seller_id]);
                $product_count = $stmt->fetch(PDO::FETCH_ASSOC)['product_count'];
                
                if ($product_count == 0) {
                    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'seller'");
                    if ($stmt->execute([$seller_id])) {
                        // Also delete seller code
                        $stmt = $pdo->prepare("DELETE FROM seller_codes WHERE seller_id = ?");
                        $stmt->execute([$seller_id]);
                        logAdminActivity("Deleted seller ID: $seller_id");
                        $success_message = "Seller deleted successfully!";
                    } else {
                        $error_message = "Failed to delete seller.";
                    }
                } else {
                    $error_message = "Cannot delete seller with active products. Please reassign or delete products first.";
                }
                break;
                
            case 'generate_new_code':
                $new_code = 'SELLER' . str_pad($seller_id, 6, '0', STR_PAD_LEFT) . substr(uniqid(), -3);
                $stmt = $pdo->prepare("UPDATE seller_codes SET seller_code = ? WHERE seller_id = ?");
                if ($stmt->execute([$new_code, $seller_id])) {
                    logAdminActivity("Generated new code for seller ID: $seller_id");
                    $success_message = "New seller code generated successfully!";
                } else {
                    $error_message = "Failed to generate new seller code.";
                }
                break;
        }
    }
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'created_at';
$sort_order = isset($_GET['order']) ? $_GET['order'] : 'desc';

// Build WHERE clause for filtering
$where_conditions = ["u.role = 'seller'"];
$params = [];

if (!empty($status_filter) && in_array($status_filter, ['active', 'inactive', 'pending'])) {
    $where_conditions[] = "u.status = ?";
    $params[] = $status_filter;
}

if (!empty($search_query)) {
    $where_conditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.store_name LIKE ? OR sc.seller_code LIKE ?)";
    $search_param = "%$search_query%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = "WHERE " . implode(" AND ", $where_conditions);
}

// Validate sort parameters
$allowed_sorts = ['first_name', 'email', 'created_at', 'total_products', 'total_sales', 'total_revenue'];
$allowed_orders = ['asc', 'desc'];

if (!in_array($sort_by, $allowed_sorts)) {
    $sort_by = 'created_at';
}
if (!in_array($sort_order, $allowed_orders)) {
    $sort_order = 'desc';
}

// Get all sellers with pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM users u LEFT JOIN seller_codes sc ON u.id = sc.seller_id $where_clause";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_sellers = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_sellers / $limit);

// Get sellers with additional info
$sql = "
    SELECT 
        u.*,
        sc.seller_code,
        COUNT(DISTINCT p.id) as total_products,
        COUNT(DISTINCT oi.id) as total_sales,
        COALESCE(SUM(CASE WHEN o.status = 'completed' THEN oi.quantity * oi.price ELSE 0 END), 0) as total_revenue,
        COALESCE(SUM(CASE WHEN o.status = 'completed' THEN oi.quantity * oi.price * 0.1 ELSE 0 END), 0) as platform_commission,
        COUNT(DISTINCT CASE WHEN p.status = 'active' THEN p.id END) as active_products,
        COUNT(DISTINCT CASE WHEN p.status = 'pending' THEN p.id END) as pending_products
    FROM users u 
    LEFT JOIN seller_codes sc ON u.id = sc.seller_id
    LEFT JOIN products p ON u.id = p.seller_id
    LEFT JOIN order_items oi ON p.id = oi.product_id
    LEFT JOIN orders o ON oi.order_id = o.id
    $where_clause
    GROUP BY u.id 
    ORDER BY $sort_by $sort_order 
    LIMIT :limit OFFSET :offset
";

$stmt = $pdo->prepare($sql);

// Bind parameters separately for filters
foreach ($params as $key => $value) {
    $stmt->bindValue(($key + 1), $value);
}

// Bind LIMIT and OFFSET as integers
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

$stmt->execute();
$sellers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get seller counts by status for filter badges
$status_counts_stmt = $pdo->prepare("
    SELECT status, COUNT(*) as count 
    FROM users 
    WHERE role = 'seller'
    GROUP BY status
");
$status_counts_stmt->execute();
$status_counts = $status_counts_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get overall seller statistics
$stats_stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_sellers,
        COUNT(CASE WHEN status = 'active' THEN 1 END) as active_sellers,
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_sellers,
        COUNT(CASE WHEN status = 'inactive' THEN 1 END) as inactive_sellers
    FROM users 
    WHERE role = 'seller'
");
$stats_stmt->execute();
$seller_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Log admin activity
logAdminActivity("Accessed sellers management page");

function logAdminActivity($activity) {
    global $pdo;
    if (isset($_SESSION['user_id'])) {
        $stmt = $pdo->prepare("INSERT INTO admin_activities (admin_id, activity, ip_address) VALUES (?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], $activity, $_SERVER['REMOTE_ADDR']]);
    }
}

// Helper function to build pagination query string
function buildQueryString($page, $exclude = []) {
    $params = $_GET;
    $params['page'] = $page;
    foreach ($exclude as $key) {
        unset($params[$key]);
    }
    return http_build_query($params);
}

// Helper function to get sort icon
function getSortIcon($column, $current_sort, $current_order) {
    if ($column === $current_sort) {
        return $current_order === 'asc' ? 'fa-sort-up' : 'fa-sort-down';
    }
    return 'fa-sort';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sellers Management - BSDO Sale Admin</title>
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
        
        .table th {
            border-top: none;
            font-weight: 700;
            color: var(--dark-color);
            cursor: pointer;
        }
        
        .table th:hover {
            background-color: #f8f9fc;
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
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background-color: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.2rem;
        }
        
        .filter-badge {
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .filter-badge.active {
            transform: scale(1.1);
            box-shadow: 0 0 10px rgba(0,0,0,0.2);
        }
        
        .pagination .page-link {
            color: var(--primary-color);
        }
        
        .pagination .page-item.active .page-link {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .sortable:hover {
            background-color: #f8f9fc;
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
                <h1 class="h3 mb-0 text-gray-800">Sellers Management</h1>
                <a href="#" class="d-none d-sm-inline-block btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#addSellerModal">
                    <i class="fas fa-plus fa-sm text-white-50"></i> Add New Seller
                </a>
            </div>

            <!-- Seller Statistics -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stats-card total h-100">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Sellers</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($seller_stats['total_sellers']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-store fa-2x text-gray-300"></i>
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
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Active Sellers</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($seller_stats['active_sellers']); ?></div>
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
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Pending Approval</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($seller_stats['pending_sellers']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-clock fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stats-card inactive h-100">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Inactive Sellers</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($seller_stats['inactive_sellers']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-pause-circle fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Alerts -->
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Filters Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Filters & Search</h6>
                </div>
                <div class="card-body">
                    <form method="GET" action="sellers.php" class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Status Filter</label>
                            <div class="d-flex flex-wrap gap-2">
                                <span class="badge filter-badge <?php echo empty($status_filter) ? 'bg-primary active' : 'bg-secondary'; ?>" onclick="clearFilter('status')">All</span>
                                <?php foreach ($status_counts as $status_count): ?>
                                    <span class="badge filter-badge <?php echo ($status_filter === $status_count['status']) ? 'bg-primary active' : 'bg-light text-dark'; ?>" 
                                          onclick="setFilter('status', '<?php echo $status_count['status']; ?>')">
                                        <?php echo ucfirst($status_count['status']); ?> 
                                        <span class="badge bg-dark"><?php echo $status_count['count']; ?></span>
                                    </span>
                                <?php endforeach; ?>
                                <input type="hidden" name="status" id="statusFilter" value="<?php echo htmlspecialchars($status_filter); ?>">
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="search" class="form-label">Search Sellers</label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="search" name="search" placeholder="Search by name, email, store, or code..." value="<?php echo htmlspecialchars($search_query); ?>">
                                <button class="btn btn-primary" type="submit">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary me-2">Apply Filters</button>
                            <a href="sellers.php" class="btn btn-outline-secondary">Clear All</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Sellers Table Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">All Sellers (<?php echo number_format($total_sellers); ?>)</h6>
                    <div class="text-muted small">
                        Page <?php echo $page; ?> of <?php echo max(1, $total_pages); ?>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead>
                                <tr>
                                    <th>Seller</th>
                                    <th class="sortable" onclick="sortTable('total_products')">
                                        Products <i class="fas <?php echo getSortIcon('total_products', $sort_by, $sort_order); ?> ms-1"></i>
                                    </th>
                                    <th class="sortable" onclick="sortTable('total_sales')">
                                        Sales <i class="fas <?php echo getSortIcon('total_sales', $sort_by, $sort_order); ?> ms-1"></i>
                                    </th>
                                    <th class="sortable" onclick="sortTable('total_revenue')">
                                        Revenue <i class="fas <?php echo getSortIcon('total_revenue', $sort_by, $sort_order); ?> ms-1"></i>
                                    </th>
                                    <th>Commission</th>
                                    <th>Seller Code</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($sellers)): ?>
                                    <?php foreach ($sellers as $seller): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="seller-avatar me-3">
                                                        <?php echo strtoupper(substr($seller['first_name'], 0, 1) . substr($seller['last_name'], 0, 1)); ?>
                                                    </div>
                                                    <div>
                                                        <div class="fw-bold"><?php echo htmlspecialchars($seller['first_name'] . ' ' . $seller['last_name']); ?></div>
                                                        <div class="text-muted small"><?php echo htmlspecialchars($seller['email']); ?></div>
                                                        <?php if (!empty($seller['store_name'])): ?>
                                                            <div class="text-info small"><i class="fas fa-store"></i> <?php echo htmlspecialchars($seller['store_name']); ?></div>
                                                        <?php endif; ?>
                                                        <div class="text-muted small">Joined: <?php echo date('M j, Y', strtotime($seller['created_at'])); ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="text-center">
                                                    <div class="fw-bold h5"><?php echo number_format($seller['total_products']); ?></div>
                                                    <div class="small text-muted">
                                                        <span class="text-success"><?php echo $seller['active_products']; ?> active</span> | 
                                                        <span class="text-warning"><?php echo $seller['pending_products']; ?> pending</span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="text-center fw-bold h5"><?php echo number_format($seller['total_sales']); ?></td>
                                            <td class="text-success fw-bold">$<?php echo number_format($seller['total_revenue'], 2); ?></td>
                                            <td>
                                                <span class="badge commission-badge">$<?php echo number_format($seller['platform_commission'], 2); ?></span>
                                            </td>
                                            <td>
                                                <code class="bg-light p-1 rounded"><?php echo htmlspecialchars($seller['seller_code']); ?></code>
                                                <button class="btn btn-sm btn-outline-secondary ms-1" onclick="copyToClipboard('<?php echo $seller['seller_code']; ?>')" title="Copy Code">
                                                    <i class="fas fa-copy"></i>
                                                </button>
                                            </td>
                                            <td>
                                                <span class="badge 
                                                    <?php echo $seller['status'] === 'active' ? 'bg-success' : ($seller['status'] === 'pending' ? 'bg-warning' : 'bg-secondary'); ?>">
                                                    <?php echo ucfirst($seller['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <!-- View Seller Details -->
                                                    <a href="seller_details.php?id=<?php echo $seller['id']; ?>" class="btn btn-info" title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    
                                                    <!-- Status-specific actions -->
                                                    <?php if ($seller['status'] === 'pending'): ?>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="seller_id" value="<?php echo $seller['id']; ?>">
                                                            <button type="submit" name="action" value="approve" class="btn btn-success" title="Approve Seller">
                                                                <i class="fas fa-check"></i>
                                                            </button>
                                                        </form>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="seller_id" value="<?php echo $seller['id']; ?>">
                                                            <button type="submit" name="action" value="reject" class="btn btn-danger" title="Reject Seller">
                                                                <i class="fas fa-times"></i>
                                                            </button>
                                                        </form>
                                                    <?php elseif ($seller['status'] === 'active'): ?>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="seller_id" value="<?php echo $seller['id']; ?>">
                                                            <button type="submit" name="action" value="suspend" class="btn btn-warning" title="Suspend Seller">
                                                                <i class="fas fa-pause"></i>
                                                            </button>
                                                        </form>
                                                    <?php else: ?>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="seller_id" value="<?php echo $seller['id']; ?>">
                                                            <button type="submit" name="action" value="activate" class="btn btn-success" title="Activate Seller">
                                                                <i class="fas fa-play"></i>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                    
                                                    <!-- Generate New Code -->
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="seller_id" value="<?php echo $seller['id']; ?>">
                                                        <button type="submit" name="action" value="generate_new_code" class="btn btn-outline-primary" title="Generate New Code">
                                                            <i class="fas fa-sync-alt"></i>
                                                        </button>
                                                    </form>
                                                    
                                                    <!-- Delete Seller -->
                                                    <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this seller? This action cannot be undone.');">
                                                        <input type="hidden" name="seller_id" value="<?php echo $seller['id']; ?>">
                                                        <button type="submit" name="action" value="delete" class="btn btn-danger" title="Delete Seller">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-4">
                                            <i class="fas fa-store fa-3x text-muted mb-3"></i>
                                            <p class="text-muted">No sellers found matching your criteria.</p>
                                            <a href="sellers.php" class="btn btn-primary">Clear Filters</a>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <nav aria-label="Page navigation">
                            <ul class="pagination justify-content-center">
                                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?<?php echo buildQueryString($page - 1); ?>">Previous</a>
                                </li>
                                
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?<?php echo buildQueryString($i); ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                
                                <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?<?php echo buildQueryString($page + 1); ?>">Next</a>
                                </li>
                            </ul>
                        </nav>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Seller Modal -->
    <div class="modal fade" id="addSellerModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Seller</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="add_seller.php">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">First Name</label>
                                <input type="text" class="form-control" name="first_name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Last Name</label>
                                <input type="text" class="form-control" name="last_name" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Store Name</label>
                            <input type="text" class="form-control" name="store_name">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Business Type</label>
                            <select class="form-control" name="business_type">
                                <option value="retail">Retail</option>
                                <option value="wholesale">Wholesale</option>
                                <option value="manufacturer">Manufacturer</option>
                                <option value="service">Service Provider</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" class="form-control" name="password" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Seller</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sidebar Toggle
        document.getElementById('sidebarCollapse').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
        });

        // Filter functions
        function setFilter(type, value) {
            document.getElementById(type + 'Filter').value = value;
            document.forms[0].submit();
        }

        function clearFilter(type) {
            document.getElementById(type + 'Filter').value = '';
            document.forms[0].submit();
        }

        // Sort table function
        function sortTable(column) {
            const urlParams = new URLSearchParams(window.location.search);
            let currentSort = urlParams.get('sort') || 'created_at';
            let currentOrder = urlParams.get('order') || 'desc';
            
            let newOrder = 'asc';
            if (column === currentSort) {
                newOrder = currentOrder === 'asc' ? 'desc' : 'asc';
            }
            
            urlParams.set('sort', column);
            urlParams.set('order', newOrder);
            window.location.href = 'sellers.php?' + urlParams.toString();
        }

        // Copy to clipboard function
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                // Show temporary feedback
                const originalTitle = event.target.title;
                event.target.innerHTML = '<i class="fas fa-check"></i>';
                event.target.title = 'Copied!';
                event.target.classList.remove('btn-outline-secondary');
                event.target.classList.add('btn-success');
                
                setTimeout(() => {
                    event.target.innerHTML = '<i class="fas fa-copy"></i>';
                    event.target.title = originalTitle;
                    event.target.classList.remove('btn-success');
                    event.target.classList.add('btn-outline-secondary');
                }, 2000);
            });
        }

        // Auto-submit search when typing stops
        let searchTimeout;
        document.getElementById('search').addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                document.forms[0].submit();
            }, 500);
        });
    </script>
</body>
</html>