<?php
session_start();
require_once '../config.php';

// Check if user is logged in and is seller
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'seller') {
    header('Location: ../login.php');
    exit();
}

$seller_id = $_SESSION['user_id'];

// Handle order actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_status':
                $order_id = intval($_POST['order_id']);
                $new_status = trim($_POST['status']);
                
                // Verify order belongs to seller
                $stmt = $pdo->prepare("
                    SELECT o.id 
                    FROM orders o
                    JOIN order_items oi ON o.id = oi.order_id
                    JOIN products p ON oi.product_id = p.id
                    WHERE o.id = ? AND p.seller_id = ?
                    LIMIT 1
                ");
                $stmt->execute([$order_id, $seller_id]);
                
                if ($stmt->rowCount() > 0) {
                    $valid_statuses = ['pending', 'processing', 'shipped', 'cancelled'];
                    if (in_array($new_status, $valid_statuses)) {
                        $stmt = $pdo->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?");
                        $stmt->execute([$new_status, $order_id]);
                        $success_message = "Order status updated successfully!";
                    } else {
                        $error_message = "Invalid status selected.";
                    }
                } else {
                    $error_message = "Order not found or access denied.";
                }
                break;
                
            case 'add_note':
                $order_id = intval($_POST['order_id']);
                $note = trim($_POST['note']);
                
                // Verify order belongs to seller
                $stmt = $pdo->prepare("
                    SELECT o.id 
                    FROM orders o
                    JOIN order_items oi ON o.id = oi.order_id
                    JOIN products p ON oi.product_id = p.id
                    WHERE o.id = ? AND p.seller_id = ?
                    LIMIT 1
                ");
                $stmt->execute([$order_id, $seller_id]);
                
                if ($stmt->rowCount() > 0 && !empty($note)) {
                    // For seller notes, we can use admin_id field with a special value or create a separate table
                    $stmt = $pdo->prepare("INSERT INTO order_notes (order_id, admin_id, note) VALUES (?, ?, ?)");
                    $stmt->execute([$order_id, 0, "[Seller] " . $note]);
                    $success_message = "Note added successfully!";
                } else {
                    $error_message = "Unable to add note.";
                }
                break;
        }
    }
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Build query with filters
$query = "
    SELECT 
        o.*,
        u.first_name,
        u.last_name,
        u.email,
        u.phone,
        a.street,
        a.city,
        a.state,
        a.zip_code,
        COUNT(DISTINCT oi.id) as item_count,
        SUM(oi.quantity) as total_quantity,
        GROUP_CONCAT(DISTINCT p.name SEPARATOR ', ') as product_names,
        MAX(oi.price * oi.quantity) as max_item_value
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    JOIN products p ON oi.product_id = p.id
    JOIN users u ON o.user_id = u.id
    LEFT JOIN addresses a ON o.shipping_address_id = a.id
    WHERE p.seller_id = ?
";

$params = [$seller_id];
$where_conditions = [];

// Add status filter
if (!empty($status_filter) && $status_filter !== 'all') {
    $where_conditions[] = "o.status = ?";
    $params[] = $status_filter;
}

// Add date filter
if (!empty($date_from)) {
    $where_conditions[] = "DATE(o.created_at) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $where_conditions[] = "DATE(o.created_at) <= ?";
    $params[] = $date_to;
}

// Add search filter
if (!empty($search)) {
    $where_conditions[] = "(o.order_number LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR p.name LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

// Complete query
if (!empty($where_conditions)) {
    $query .= " AND " . implode(" AND ", $where_conditions);
}

$query .= " GROUP BY o.id ORDER BY o.created_at DESC";

// Get orders
$orders_stmt = $pdo->prepare($query);
$orders_stmt->execute($params);
$orders = $orders_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get order statistics
$stats_stmt = $pdo->prepare("
    SELECT 
        o.status,
        COUNT(DISTINCT o.id) as order_count,
        COALESCE(SUM(oi.quantity * oi.price), 0) as total_value
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    JOIN products p ON oi.product_id = p.id
    WHERE p.seller_id = ?
    GROUP BY o.status
");

$stats_stmt->execute([$seller_id]);
$order_stats = $stats_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get order notes for display
$order_notes = [];
if (!empty($orders)) {
    $order_ids = array_column($orders, 'id');
    $placeholders = str_repeat('?,', count($order_ids) - 1) . '?';
    
    $notes_stmt = $pdo->prepare("
        SELECT onotes.*, u.first_name, u.last_name 
        FROM order_notes onotes 
        LEFT JOIN users u ON onotes.admin_id = u.id 
        WHERE onotes.order_id IN ($placeholders) 
        ORDER BY onotes.created_at DESC
    ");
    $notes_stmt->execute($order_ids);
    $all_notes = $notes_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($all_notes as $note) {
        $order_notes[$note['order_id']][] = $note;
    }
}

// Get seller information
$seller_info_stmt = $pdo->prepare("SELECT first_name, last_name, store_name FROM users WHERE id = ?");
$seller_info_stmt->execute([$seller_id]);
$seller_info = $seller_info_stmt->fetch(PDO::FETCH_ASSOC);

// Log seller activity
logSellerActivity("Accessed orders management page");

function logSellerActivity($activity) {
    global $pdo, $seller_id;
    $stmt = $pdo->prepare("INSERT INTO seller_activities (seller_id, activity, ip_address) VALUES (?, ?, ?)");
    $stmt->execute([$seller_id, $activity, $_SERVER['REMOTE_ADDR']]);
}

function getStatusBadge($status) {
    switch ($status) {
        case 'pending': return '<span class="badge bg-warning">Pending</span>';
        case 'processing': return '<span class="badge bg-primary">Processing</span>';
        case 'shipped': return '<span class="badge bg-info">Shipped</span>';
        case 'delivered': return '<span class="badge bg-success">Delivered</span>';
        case 'completed': return '<span class="badge bg-success">Completed</span>';
        case 'cancelled': return '<span class="badge bg-danger">Cancelled</span>';
        default: return '<span class="badge bg-secondary">' . ucfirst($status) . '</span>';
    }
}

function getStatusOptions($current_status) {
    $statuses = [
        'pending' => 'Pending',
        'processing' => 'Processing', 
        'shipped' => 'Shipped',
        'cancelled' => 'Cancelled'
    ];
    
    $options = '';
    foreach ($statuses as $value => $label) {
        $selected = $current_status === $value ? 'selected' : '';
        $options .= "<option value='$value' $selected>$label</option>";
    }
    return $options;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders Management - BSDO Sale</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #1cc88a;
            --warning-color: #f6c23e;
            --danger-color: #e74a3b;
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
        }
        
        .stats-card:hover {
            transform: translateY(-2px);
        }
        
        .stats-card.primary { border-left-color: var(--primary-color); }
        .stats-card.success { border-left-color: var(--secondary-color); }
        .stats-card.warning { border-left-color: var(--warning-color); }
        .stats-card.danger { border-left-color: var(--danger-color); }
        
        .order-card {
            border: 1px solid #e3e6f0;
            border-radius: 10px;
            transition: all 0.3s;
            margin-bottom: 1rem;
        }
        
        .order-card:hover {
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }
        
        .product-image {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 5px;
        }
        
        .customer-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
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
    <a class="nav-link" href="rental_products.php">
        <i class="fas fa-calendar-alt me-2"></i>Rental Products
    </a>
</li>
                        <li class="nav-item">
                            <a class="nav-link active" href="orders.php">
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
                            <a class="nav-link" href="../logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i>Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-lg-10 col-md-12 p-4">
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

                <!-- Page Header -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h2 class="h4 font-weight-bold text-gray-800">Order Management</h2>
                                <p class="text-muted">Manage and track your customer orders</p>
                            </div>
                            <div>
                                <span class="badge bg-light text-dark fs-6">
                                    <i class="fas fa-shopping-cart me-1"></i>
                                    <?php echo count($orders); ?> Orders
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Order Statistics -->
                <div class="row mb-4">
                    <?php
                    $status_counts = [];
                    foreach ($order_stats as $stat) {
                        $status_counts[$stat['status']] = $stat['order_count'];
                    }
                    ?>
                    
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stats-card primary h-100">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Orders</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo count($orders); ?></div>
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
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Pending</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $status_counts['pending'] ?? 0; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-clock fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stats-card info h-100">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Processing</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $status_counts['processing'] ?? 0; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-shipping-fast fa-2x text-gray-300"></i>
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
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Completed</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo ($status_counts['completed'] ?? 0) + ($status_counts['delivered'] ?? 0); ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    <option value="all">All Statuses</option>
                                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="processing" <?php echo $status_filter === 'processing' ? 'selected' : ''; ?>>Processing</option>
                                    <option value="shipped" <?php echo $status_filter === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                                    <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                    <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">From Date</label>
                                <input type="date" name="date_from" class="form-control" value="<?php echo $date_from; ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">To Date</label>
                                <input type="date" name="date_to" class="form-control" value="<?php echo $date_to; ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Search</label>
                                <div class="input-group">
                                    <input type="text" name="search" class="form-control" placeholder="Order #, Customer, Product..." value="<?php echo htmlspecialchars($search); ?>">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary me-2">Apply Filters</button>
                                <a href="orders.php" class="btn btn-secondary">Clear Filters</a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Orders List -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Orders</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($orders)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Order #</th>
                                            <th>Customer</th>
                                            <th>Products</th>
                                            <th>Amount</th>
                                            <th>Status</th>
                                            <th>Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($orders as $order): ?>
                                            <tr>
                                                <td>
                                                    <strong>#<?php echo htmlspecialchars($order['order_number']); ?></strong>
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="customer-avatar me-2">
                                                            <?php echo strtoupper(substr($order['first_name'], 0, 1)); ?>
                                                        </div>
                                                        <div>
                                                            <div class="fw-bold"><?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></div>
                                                            <small class="text-muted"><?php echo htmlspecialchars($order['email']); ?></small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="fw-bold"><?php echo htmlspecialchars($order['product_names']); ?></div>
                                                    <small class="text-muted"><?php echo $order['total_quantity']; ?> items</small>
                                                </td>
                                                <td class="fw-bold text-success">$<?php echo number_format($order['total_amount'], 2); ?></td>
                                                <td><?php echo getStatusBadge($order['status']); ?></td>
                                                <td>
                                                    <div><?php echo date('M j, Y', strtotime($order['created_at'])); ?></div>
                                                    <small class="text-muted"><?php echo date('g:i A', strtotime($order['created_at'])); ?></small>
                                                </td>
                                                <td>
                                                    <div class="btn-group">
                                                        <button class="btn btn-sm btn-outline-primary" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#orderDetailsModal"
                                                                onclick="viewOrderDetails(<?php echo htmlspecialchars(json_encode($order)); ?>)">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-warning"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#updateStatusModal"
                                                                onclick="updateOrderStatus(<?php echo $order['id']; ?>, '<?php echo $order['status']; ?>')">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-shopping-cart fa-4x text-muted mb-3"></i>
                                <h4 class="text-muted">No Orders Found</h4>
                                <p class="text-muted">You don't have any orders matching your criteria.</p>
                                <?php if (!empty($status_filter) || !empty($search)): ?>
                                    <a href="orders.php" class="btn btn-primary">Clear Filters</a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Order Details Modal -->
    <div class="modal fade" id="orderDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Order Details - <span id="orderNumber"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="orderDetailsContent"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Update Status Modal -->
    <div class="modal fade" id="updateStatusModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Update Order Status</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="order_id" id="updateOrderId">
                        <div class="mb-3">
                            <label class="form-label">Order Status</label>
                            <select class="form-select" name="status" id="updateOrderStatus" required>
                                <?php echo getStatusOptions(''); ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="action" value="update_status" class="btn btn-primary">Update Status</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewOrderDetails(order) {
            document.getElementById('orderNumber').textContent = '#' + order.order_number;
            
            const content = `
                <div class="row">
                    <div class="col-md-6">
                        <h6>Customer Information</h6>
                        <p>
                            <strong>Name:</strong> ${order.first_name} ${order.last_name}<br>
                            <strong>Email:</strong> ${order.email}<br>
                            <strong>Phone:</strong> ${order.phone || 'N/A'}
                        </p>
                    </div>
                    <div class="col-md-6">
                        <h6>Order Information</h6>
                        <p>
                            <strong>Date:</strong> ${new Date(order.created_at).toLocaleDateString()}<br>
                            <strong>Status:</strong> <span class="badge bg-${getStatusColor(order.status)}">${order.status}</span><br>
                            <strong>Total:</strong> $${parseFloat(order.total_amount).toFixed(2)}
                        </p>
                    </div>
                </div>
                ${order.street ? `
                <div class="row mt-3">
                    <div class="col-12">
                        <h6>Shipping Address</h6>
                        <p>
                            ${order.street}<br>
                            ${order.city}, ${order.state} ${order.zip_code}
                        </p>
                    </div>
                </div>
                ` : ''}
                <div class="row mt-3">
                    <div class="col-12">
                        <h6>Products</h6>
                        <p>${order.product_names}</p>
                        <p><strong>Total Quantity:</strong> ${order.total_quantity} items</p>
                    </div>
                </div>
            `;
            
            document.getElementById('orderDetailsContent').innerHTML = content;
        }
        
        function getStatusColor(status) {
            switch(status) {
                case 'pending': return 'warning';
                case 'processing': return 'primary';
                case 'shipped': return 'info';
                case 'completed': return 'success';
                case 'cancelled': return 'danger';
                default: return 'secondary';
            }
        }
        
        function updateOrderStatus(orderId, currentStatus) {
            document.getElementById('updateOrderId').value = orderId;
            document.getElementById('updateOrderStatus').value = currentStatus;
        }
    </script>
</body>
</html>