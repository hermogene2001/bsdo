<?php
session_start();
require_once '../config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Handle order actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['order_id'])) {
        $order_id = intval($_POST['order_id']);
        
        switch ($_POST['action']) {
            case 'update_status':
                $new_status = $_POST['status'];
                $valid_statuses = ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled', 'completed'];
                
                if (in_array($new_status, $valid_statuses)) {
                    $stmt = $pdo->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?");
                    if ($stmt->execute([$new_status, $order_id])) {
                        logAdminActivity("Updated order #$order_id status to: $new_status");
                        $success_message = "Order status updated successfully!";
                    } else {
                        $error_message = "Failed to update order status.";
                    }
                }
                break;
                
            case 'cancel':
                $stmt = $pdo->prepare("UPDATE orders SET status = 'cancelled', updated_at = NOW() WHERE id = ?");
                if ($stmt->execute([$order_id])) {
                    logAdminActivity("Cancelled order #$order_id");
                    $success_message = "Order cancelled successfully!";
                } else {
                    $error_message = "Failed to cancel order.";
                }
                break;
                
            case 'delete':
                // Only allow deletion of cancelled orders or orders without completed payments
                $stmt = $pdo->prepare("SELECT status FROM orders WHERE id = ?");
                $stmt->execute([$order_id]);
                $order = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($order && ($order['status'] === 'cancelled' || $order['status'] === 'pending')) {
                    $stmt = $pdo->prepare("DELETE FROM orders WHERE id = ?");
                    if ($stmt->execute([$order_id])) {
                        logAdminActivity("Deleted order #$order_id");
                        $success_message = "Order deleted successfully!";
                    } else {
                        $error_message = "Failed to delete order.";
                    }
                } else {
                    $error_message = "Cannot delete orders that are not cancelled or pending.";
                }
                break;
                
            case 'add_note':
                $note = trim($_POST['note']);
                if (!empty($note)) {
                    $stmt = $pdo->prepare("INSERT INTO order_notes (order_id, admin_id, note) VALUES (?, ?, ?)");
                    if ($stmt->execute([$order_id, $_SESSION['user_id'], $note])) {
                        logAdminActivity("Added note to order #$order_id");
                        $success_message = "Note added successfully!";
                    } else {
                        $error_message = "Failed to add note.";
                    }
                }
                break;
        }
    }
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'created_at';
$sort_order = isset($_GET['order']) ? $_GET['order'] : 'desc';

// Build WHERE clause for filtering
$where_conditions = [];
$params = [];

if (!empty($status_filter) && in_array($status_filter, ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled', 'completed'])) {
    $where_conditions[] = "o.status = ?";
    $params[] = $status_filter;
}

if (!empty($date_from)) {
    $where_conditions[] = "DATE(o.created_at) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $where_conditions[] = "DATE(o.created_at) <= ?";
    $params[] = $date_to;
}

if (!empty($search_query)) {
    $where_conditions[] = "(o.order_number LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
    $search_param = "%$search_query%";
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
$allowed_sorts = ['order_number', 'total_amount', 'created_at', 'status'];
$allowed_orders = ['asc', 'desc'];

if (!in_array($sort_by, $allowed_sorts)) {
    $sort_by = 'created_at';
}
if (!in_array($sort_order, $allowed_orders)) {
    $sort_order = 'desc';
}

// Get all orders with pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM orders o LEFT JOIN users u ON o.user_id = u.id $where_clause";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_orders = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_orders / $limit);

// Get orders with additional info
$sql = "
    SELECT 
        o.*,
        u.first_name,
        u.last_name,
        u.email,
        u.phone,
        COUNT(oi.id) as item_count,
        GROUP_CONCAT(DISTINCT p.name SEPARATOR ', ') as product_names,
        a.street, a.city, a.state, a.zip_code, a.country
    FROM orders o 
    LEFT JOIN users u ON o.user_id = u.id
    LEFT JOIN order_items oi ON o.id = oi.order_id
    LEFT JOIN products p ON oi.product_id = p.id
    LEFT JOIN addresses a ON o.shipping_address_id = a.id
    $where_clause
    GROUP BY o.id 
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
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get order counts by status for filter badges
$status_counts_stmt = $pdo->prepare("
    SELECT status, COUNT(*) as count 
    FROM orders 
    GROUP BY status
");
$status_counts_stmt->execute();
$status_counts = $status_counts_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get overall order statistics
$stats_stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_orders,
        COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_orders,
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_orders,
        COUNT(CASE WHEN status IN ('confirmed', 'processing', 'shipped') THEN 1 END) as active_orders,
        COALESCE(SUM(CASE WHEN status = 'completed' THEN total_amount ELSE 0 END), 0) as total_revenue,
        COALESCE(AVG(CASE WHEN status = 'completed' THEN total_amount ELSE NULL END), 0) as avg_order_value
    FROM orders
");
$stats_stmt->execute();
$order_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Get recent order notes for the orders
$order_ids = array_column($orders, 'id');
if (!empty($order_ids)) {
    $placeholders = str_repeat('?,', count($order_ids) - 1) . '?';
    $notes_stmt = $pdo->prepare("
        SELECT onotes.*, u.first_name, u.last_name 
        FROM order_notes onotes 
        LEFT JOIN users u ON onotes.admin_id = u.id 
        WHERE onotes.order_id IN ($placeholders) 
        ORDER BY onotes.created_at DESC
    ");
    $notes_stmt->execute($order_ids);
    $order_notes = $notes_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Group notes by order_id
    $notes_by_order = [];
    foreach ($order_notes as $note) {
        $notes_by_order[$note['order_id']][] = $note;
    }
} else {
    $notes_by_order = [];
}

// Log admin activity
logAdminActivity("Accessed orders management page");

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

// Helper function to get status badge class
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'completed':
        case 'delivered':
            return 'bg-success';
        case 'confirmed':
        case 'processing':
        case 'shipped':
            return 'bg-primary';
        case 'pending':
            return 'bg-warning';
        case 'cancelled':
            return 'bg-danger';
        default:
            return 'bg-secondary';
    }
}

// Helper function to get status progress
function getStatusProgress($status) {
    $status_order = ['pending' => 1, 'confirmed' => 2, 'processing' => 3, 'shipped' => 4, 'delivered' => 5, 'completed' => 6, 'cancelled' => 0];
    return $status_order[$status] ?? 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders Management - BSDO Sale Admin</title>
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
        
        .stats-card.completed {
            border-left-color: var(--secondary-color);
        }
        
        .stats-card.pending {
            border-left-color: var(--warning-color);
        }
        
        .stats-card.active {
            border-left-color: var(--danger-color);
        }
        
        .order-progress {
            height: 6px;
            background-color: #e9ecef;
            border-radius: 3px;
            overflow: hidden;
            margin: 5px 0;
        }
        
        .order-progress-bar {
            height: 100%;
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            transition: width 0.3s ease;
        }
        
        .order-notes {
            max-height: 150px;
            overflow-y: auto;
            font-size: 0.85rem;
        }
        
        .note-bubble {
            background-color: #f8f9fa;
            border-left: 3px solid var(--primary-color);
            padding: 8px 12px;
            margin: 5px 0;
            border-radius: 0 8px 8px 0;
        }
        
        .customer-avatar {
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
                <li class="active">
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
                <h1 class="h3 mb-0 text-gray-800">Orders Management</h1>
                <a href="#" class="d-none d-sm-inline-block btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#exportModal">
                    <i class="fas fa-download fa-sm text-white-50"></i> Export Orders
                </a>
            </div>

            <!-- Order Statistics -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stats-card total h-100">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Orders</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($order_stats['total_orders']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-shopping-cart fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card stats-card completed h-100">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Completed</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($order_stats['completed_orders']); ?></div>
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
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Pending</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($order_stats['pending_orders']); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-clock fa-2x text-gray-300"></i>
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
                                    <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Total Revenue</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">$<?php echo number_format($order_stats['total_revenue'], 2); ?></div>
                                    <div class="mt-1 text-xs text-muted">Avg: $<?php echo number_format($order_stats['avg_order_value'], 2); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
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
                    <form method="GET" action="orders.php" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Status Filter</label>
                            <div class="d-flex flex-wrap gap-2 mb-2">
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
                        
                        <div class="col-md-3">
                            <label for="date_from" class="form-label">Date From</label>
                            <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                        </div>
                        
                        <div class="col-md-3">
                            <label for="date_to" class="form-label">Date To</label>
                            <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                        </div>
                        
                        <div class="col-md-3">
                            <label for="search" class="form-label">Search Orders</label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="search" name="search" placeholder="Search by order #, customer..." value="<?php echo htmlspecialchars($search_query); ?>">
                                <button class="btn btn-primary" type="submit">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary me-2">Apply Filters</button>
                            <a href="orders.php" class="btn btn-outline-secondary">Clear All</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Orders Table Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">All Orders (<?php echo number_format($total_orders); ?>)</h6>
                    <div class="text-muted small">
                        Page <?php echo $page; ?> of <?php echo max(1, $total_pages); ?>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead>
                                <tr>
                                    <th>Order Details</th>
                                    <th class="sortable" onclick="sortTable('total_amount')">
                                        Amount <i class="fas <?php echo getSortIcon('total_amount', $sort_by, $sort_order); ?> ms-1"></i>
                                    </th>
                                    <th>Progress</th>
                                    <th class="sortable" onclick="sortTable('status')">
                                        Status <i class="fas <?php echo getSortIcon('status', $sort_by, $sort_order); ?> ms-1"></i>
                                    </th>
                                    <th>Notes</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($orders)): ?>
                                    <?php foreach ($orders as $order): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="customer-avatar me-3">
                                                        <?php echo strtoupper(substr($order['first_name'], 0, 1) . substr($order['last_name'], 0, 1)); ?>
                                                    </div>
                                                    <div>
                                                        <div class="fw-bold text-primary">#<?php echo htmlspecialchars($order['order_number']); ?></div>
                                                        <div class="small"><?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></div>
                                                        <div class="small text-muted"><?php echo htmlspecialchars($order['email']); ?></div>
                                                        <div class="small text-muted">
                                                            <?php echo $order['item_count']; ?> items • 
                                                            <?php echo date('M j, Y g:i A', strtotime($order['created_at'])); ?>
                                                        </div>
                                                        <?php if (!empty($order['product_names'])): ?>
                                                            <div class="small text-truncate" style="max-width: 200px;" title="<?php echo htmlspecialchars($order['product_names']); ?>">
                                                                <i class="fas fa-box"></i> <?php echo htmlspecialchars($order['product_names']); ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="text-success fw-bold h5">$<?php echo number_format($order['total_amount'], 2); ?></td>
                                            <td>
                                                <div class="order-progress">
                                                    <div class="order-progress-bar" style="width: <?php echo (getStatusProgress($order['status']) / 6) * 100; ?>%"></div>
                                                </div>
                                                <div class="small text-muted text-center">
                                                    <?php 
                                                    $status_labels = ['pending' => 'Pending', 'confirmed' => 'Confirmed', 'processing' => 'Processing', 'shipped' => 'Shipped', 'delivered' => 'Delivered', 'completed' => 'Completed'];
                                                    echo $status_labels[$order['status']] ?? ucfirst($order['status']); 
                                                    ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge <?php echo getStatusBadgeClass($order['status']); ?>">
                                                    <?php echo ucfirst($order['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="order-notes">
                                                    <?php if (isset($notes_by_order[$order['id']])): ?>
                                                        <?php foreach (array_slice($notes_by_order[$order['id']], 0, 2) as $note): ?>
                                                            <div class="note-bubble">
                                                                <div class="fw-bold"><?php echo htmlspecialchars($note['first_name'] . ' ' . $note['last_name']); ?></div>
                                                                <div class="small"><?php echo htmlspecialchars($note['note']); ?></div>
                                                                <div class="small text-muted"><?php echo date('M j g:i A', strtotime($note['created_at'])); ?></div>
                                                            </div>
                                                        <?php endforeach; ?>
                                                        <?php if (count($notes_by_order[$order['id']]) > 2): ?>
                                                            <div class="text-center small text-primary">+<?php echo count($notes_by_order[$order['id']]) - 2; ?> more notes</div>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="text-muted small">No notes</span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="btn-group-vertical btn-group-sm">
                                                    <!-- View Order Details -->
                                                    <a href="order_details.php?id=<?php echo $order['id']; ?>" class="btn btn-info mb-1" title="View Details">
                                                        <i class="fas fa-eye"></i> View
                                                    </a>
                                                    
                                                    <!-- Status Update Dropdown -->
                                                    <div class="dropdown mb-1">
                                                        <button class="btn btn-warning dropdown-toggle" type="button" data-bs-toggle="dropdown" title="Update Status">
                                                            <i class="fas fa-edit"></i> Status
                                                        </button>
                                                        <ul class="dropdown-menu">
                                                            <?php 
                                                            $status_options = [
                                                                'pending' => 'Pending',
                                                                'confirmed' => 'Confirmed', 
                                                                'processing' => 'Processing',
                                                                'shipped' => 'Shipped',
                                                                'delivered' => 'Delivered',
                                                                'completed' => 'Completed',
                                                                'cancelled' => 'Cancelled'
                                                            ];
                                                            foreach ($status_options as $value => $label): ?>
                                                                <li>
                                                                    <form method="POST" class="d-inline">
                                                                        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                                        <input type="hidden" name="status" value="<?php echo $value; ?>">
                                                                        <button type="submit" name="action" value="update_status" 
                                                                                class="dropdown-item <?php echo $order['status'] === $value ? 'active' : ''; ?>">
                                                                            <?php echo $label; ?>
                                                                        </button>
                                                                    </form>
                                                                </li>
                                                            <?php endforeach; ?>
                                                        </ul>
                                                    </div>
                                                    
                                                    <!-- Add Note -->
                                                    <button type="button" class="btn btn-outline-primary mb-1" data-bs-toggle="modal" data-bs-target="#noteModal<?php echo $order['id']; ?>" title="Add Note">
                                                        <i class="fas fa-sticky-note"></i> Note
                                                    </button>
                                                    
                                                    <!-- Cancel/Delete -->
                                                    <?php if ($order['status'] !== 'cancelled' && $order['status'] !== 'completed'): ?>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                            <button type="submit" name="action" value="cancel" class="btn btn-danger" title="Cancel Order"
                                                                    onclick="return confirm('Are you sure you want to cancel this order?')">
                                                                <i class="fas fa-times"></i> Cancel
                                                            </button>
                                                        </form>
                                                    <?php else: ?>
                                                        <form method="POST" class="d-inline" 
                                                              onsubmit="return confirm('Are you sure you want to delete this order? This action cannot be undone.')">
                                                            <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                            <button type="submit" name="action" value="delete" class="btn btn-outline-danger" title="Delete Order">
                                                                <i class="fas fa-trash"></i> Delete
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <!-- Add Note Modal -->
                                                <div class="modal fade" id="noteModal<?php echo $order['id']; ?>" tabindex="-1">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Add Note to Order #<?php echo $order['order_number']; ?></h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <form method="POST">
                                                                <div class="modal-body">
                                                                    <div class="mb-3">
                                                                        <label class="form-label">Note</label>
                                                                        <textarea class="form-control" name="note" rows="3" placeholder="Add a note about this order..." required></textarea>
                                                                    </div>
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                    <button type="submit" name="action" value="add_note" class="btn btn-primary">Add Note</button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4">
                                            <i class="fas fa-shopping-cart fa-3x text-muted mb-3"></i>
                                            <p class="text-muted">No orders found matching your criteria.</p>
                                            <a href="orders.php" class="btn btn-primary">Clear Filters</a>
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

    <!-- Export Modal -->
    <div class="modal fade" id="exportModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Export Orders</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="export_orders.php">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Date Range</label>
                            <div class="row">
                                <div class="col-md-6">
                                    <input type="date" class="form-control" name="export_date_from" required>
                                </div>
                                <div class="col-md-6">
                                    <input type="date" class="form-control" name="export_date_to" required>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Format</label>
                            <select class="form-control" name="export_format" required>
                                <option value="csv">CSV</option>
                                <option value="excel">Excel</option>
                                <option value="pdf">PDF</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Include</label>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="include_customer" checked>
                                <label class="form-check-label">Customer Information</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="include_products" checked>
                                <label class="form-check-label">Product Details</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="include_notes">
                                <label class="form-check-label">Order Notes</label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Export Orders</button>
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
            window.location.href = 'orders.php?' + urlParams.toString();
        }

        // Auto-submit search when typing stops
        let searchTimeout;
        document.getElementById('search').addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                document.forms[0].submit();
            }, 500);
        });

        // Set date_to to today if not set
        document.addEventListener('DOMContentLoaded', function() {
            if (!document.getElementById('date_to').value) {
                document.getElementById('date_to').value = new Date().toISOString().split('T')[0];
            }
        });
    </script>
</body>
</html>