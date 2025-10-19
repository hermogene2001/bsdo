<?php
session_start();
require_once 'config.php';

// Check if user is logged in and is a client
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'client') {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$error_message = '';
$success_message = '';

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Build query with filters
$query = "
    SELECT 
        o.*,
        COUNT(oi.id) as item_count,
        SUM(oi.quantity) as total_quantity,
        GROUP_CONCAT(p.name SEPARATOR ', ') as product_names,
        a.street, a.city, a.state, a.zip_code
    FROM orders o
    LEFT JOIN order_items oi ON o.id = oi.order_id
    LEFT JOIN products p ON oi.product_id = p.id
    LEFT JOIN addresses a ON o.shipping_address_id = a.id
    WHERE o.user_id = ?
";

$params = [$user_id];
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
    $where_conditions[] = "(o.order_number LIKE ? OR p.name LIKE ?)";
    $search_term = "%$search%";
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
        status,
        COUNT(*) as order_count,
        COALESCE(SUM(total_amount), 0) as total_value
    FROM orders 
    WHERE user_id = ? 
    GROUP BY status
");

$stats_stmt->execute([$user_id]);
$order_stats = $stats_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user information
$user_stmt = $pdo->prepare("SELECT first_name, last_name, email, phone FROM users WHERE id = ?");
$user_stmt->execute([$user_id]);
$user_info = $user_stmt->fetch(PDO::FETCH_ASSOC);

// Handle order actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'cancel_order':
                $order_id = intval($_POST['order_id']);
                
                // Verify order belongs to user
                $verify_stmt = $pdo->prepare("SELECT id FROM orders WHERE id = ? AND user_id = ? AND status = 'pending'");
                $verify_stmt->execute([$order_id, $user_id]);
                
                if ($verify_stmt->rowCount() > 0) {
                    $update_stmt = $pdo->prepare("UPDATE orders SET status = 'cancelled', updated_at = NOW() WHERE id = ?");
                    $update_stmt->execute([$order_id]);
                    $success_message = "Order cancelled successfully!";
                } else {
                    $error_message = "Order cannot be cancelled or does not exist.";
                }
                break;
                
            case 'request_return':
                $order_id = intval($_POST['order_id']);
                $reason = trim($_POST['return_reason']);
                
                // Verify order belongs to user and is delivered/completed
                $verify_stmt = $pdo->prepare("SELECT id FROM orders WHERE id = ? AND user_id = ? AND status IN ('delivered', 'completed')");
                $verify_stmt->execute([$order_id, $user_id]);
                
                if ($verify_stmt->rowCount() > 0 && !empty($reason)) {
                    // In a real system, you'd have a returns table
                    $success_message = "Return request submitted successfully!";
                } else {
                    $error_message = "Return request cannot be processed.";
                }
                break;
        }
    }
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

function getStatusColor($status) {
    switch ($status) {
        case 'pending': return 'warning';
        case 'processing': return 'primary';
        case 'shipped': return 'info';
        case 'delivered': return 'success';
        case 'completed': return 'success';
        case 'cancelled': return 'danger';
        default: return 'secondary';
    }
}

function formatOrderDate($date) {
    return date('M j, Y g:i A', strtotime($date));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - BSDO Sale</title>
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
            background-color: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .orders-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            padding: 2rem;
            margin-top: 2rem;
        }
        
        .order-card {
            border: 1px solid #e3e6f0;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
        }
        
        .order-card:hover {
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        
        .stats-card {
            background: linear-gradient(135deg, var(--primary-color), #667eea);
            color: white;
            border-radius: 10px;
            padding: 1.5rem;
            text-align: center;
            margin-bottom: 1.5rem;
        }
        
        .status-timeline {
            display: flex;
            justify-content: space-between;
            position: relative;
            margin: 2rem 0;
        }
        
        .status-timeline::before {
            content: '';
            position: absolute;
            top: 15px;
            left: 0;
            right: 0;
            height: 2px;
            background: #e3e6f0;
            z-index: 1;
        }
        
        .status-step {
            text-align: center;
            position: relative;
            z-index: 2;
            flex: 1;
        }
        
        .status-indicator {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #e3e6f0;
            margin: 0 auto 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.8rem;
        }
        
        .status-step.active .status-indicator {
            background: var(--primary-color);
        }
        
        .status-step.completed .status-indicator {
            background: var(--secondary-color);
        }
        
        .product-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 5px;
            background: linear-gradient(135deg, #f8f9fc, #e9ecef);
        }
        
        .empty-orders {
            text-align: center;
            padding: 4rem 2rem;
        }
        
        .empty-orders-icon {
            font-size: 4rem;
            color: #6c757d;
            margin-bottom: 1rem;
        }
        
        .order-actions .btn {
            margin: 0.2rem;
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
                    <li class="nav-item"><a class="nav-link" href="products.php">Products</a></li>
                    <li class="nav-item"><a class="nav-link" href="cart.php">Cart</a></li>
                    <li class="nav-item"><a class="nav-link active" href="orders.php">Orders</a></li>
                </ul>
                
                <div class="d-flex align-items-center">
                    <div class="dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown">
                            <div class="user-avatar me-2">
                                <?php echo strtoupper(substr($_SESSION['user_name'], 0, 1)); ?>
                            </div>
                            <span><?php echo htmlspecialchars(explode(' ', $_SESSION['user_name'])[0]); ?></span>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="client/dashboard.php"><i class="fas fa-user me-2"></i>My Account</a></li>
                            <li><a class="dropdown-item" href="cart.php"><i class="fas fa-shopping-cart me-2"></i>Shopping Cart</a></li>
                            <li><a class="dropdown-item" href="orders.php"><i class="fas fa-receipt me-2"></i>My Orders</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="container">
        <!-- Alerts -->
        <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show mt-4" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show mt-4" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Page Header -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="mb-1">My Orders</h2>
                        <p class="text-muted mb-0">Track and manage your orders</p>
                    </div>
                    <div>
                        <span class="badge bg-light text-dark fs-6">
                            <i class="fas fa-receipt me-1"></i>
                            <?php echo count($orders); ?> Orders
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Order Statistics -->
        <div class="row mt-4">
            <?php
            $status_counts = [];
            $total_value = 0;
            foreach ($order_stats as $stat) {
                $status_counts[$stat['status']] = $stat['order_count'];
                $total_value += $stat['total_value'];
            }
            ?>
            
            <div class="col-md-3 mb-3">
                <div class="stats-card">
                    <div class="h4 mb-1"><?php echo count($orders); ?></div>
                    <div class="small">Total Orders</div>
                </div>
            </div>
            
            <div class="col-md-3 mb-3">
                <div class="stats-card" style="background: linear-gradient(135deg, var(--secondary-color), #18a873);">
                    <div class="h4 mb-1">$<?php echo number_format($total_value, 2); ?></div>
                    <div class="small">Total Spent</div>
                </div>
            </div>
            
            <div class="col-md-3 mb-3">
                <div class="stats-card" style="background: linear-gradient(135deg, #f6c23e, #e0a800);">
                    <div class="h4 mb-1"><?php echo $status_counts['pending'] ?? 0; ?></div>
                    <div class="small">Pending Orders</div>
                </div>
            </div>
            
            <div class="col-md-3 mb-3">
                <div class="stats-card" style="background: linear-gradient(135deg, #36b9cc, #2c9faf);">
                    <div class="h4 mb-1"><?php echo ($status_counts['completed'] ?? 0) + ($status_counts['delivered'] ?? 0); ?></div>
                    <div class="small">Completed Orders</div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="orders-container">
            <div class="row mb-4">
                <div class="col-12">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select" onchange="this.form.submit()">
                                <option value="all">All Statuses</option>
                                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="processing" <?php echo $status_filter === 'processing' ? 'selected' : ''; ?>>Processing</option>
                                <option value="shipped" <?php echo $status_filter === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                                <option value="delivered" <?php echo $status_filter === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                                <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">From Date</label>
                            <input type="date" name="date_from" class="form-control" value="<?php echo $date_from; ?>" onchange="this.form.submit()">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">To Date</label>
                            <input type="date" name="date_to" class="form-control" value="<?php echo $date_to; ?>" onchange="this.form.submit()">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Search</label>
                            <div class="input-group">
                                <input type="text" name="search" class="form-control" placeholder="Order #, Product..." value="<?php echo htmlspecialchars($search); ?>">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Orders List -->
            <?php if (!empty($orders)): ?>
                <div class="row">
                    <div class="col-12">
                        <?php foreach ($orders as $order): ?>
                            <div class="order-card">
                                <div class="row align-items-center">
                                    <div class="col-md-8">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div>
                                                <h5 class="mb-1">Order #<?php echo htmlspecialchars($order['order_number']); ?></h5>
                                                <p class="text-muted mb-1">
                                                    <i class="fas fa-calendar me-1"></i>
                                                    <?php echo formatOrderDate($order['created_at']); ?>
                                                </p>
                                                <p class="mb-1">
                                                    <strong>Items:</strong> <?php echo $order['item_count']; ?> 
                                                    • <strong>Total Quantity:</strong> <?php echo $order['total_quantity']; ?>
                                                </p>
                                                <p class="mb-0">
                                                    <strong>Amount:</strong> 
                                                    <span class="text-success fw-bold">$<?php echo number_format($order['total_amount'], 2); ?></span>
                                                </p>
                                            </div>
                                            <div>
                                                <?php echo getStatusBadge($order['status']); ?>
                                            </div>
                                        </div>
                                        
                                        <!-- Order Progress -->
                                        <div class="status-timeline">
                                            <?php
                                            $statuses = ['pending', 'processing', 'shipped', 'delivered'];
                                            $current_status_index = array_search($order['status'], $statuses);
                                            $current_status_index = $current_status_index !== false ? $current_status_index : -1;
                                            ?>
                                            
                                            <?php foreach ($statuses as $index => $status): ?>
                                                <div class="status-step <?php echo $index <= $current_status_index ? 'completed' : ''; ?> <?php echo $index == $current_status_index ? 'active' : ''; ?>">
                                                    <div class="status-indicator">
                                                        <?php if ($index <= $current_status_index): ?>
                                                            <i class="fas fa-check"></i>
                                                        <?php else: ?>
                                                            <?php echo $index + 1; ?>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="small"><?php echo ucfirst($status); ?></div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        
                                        <!-- Shipping Address -->
                                        <?php if ($order['street']): ?>
                                            <div class="mt-2">
                                                <small class="text-muted">
                                                    <i class="fas fa-map-marker-alt me-1"></i>
                                                    <?php echo htmlspecialchars($order['street'] . ', ' . $order['city'] . ', ' . $order['state'] . ' ' . $order['zip_code']); ?>
                                                </small>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <div class="order-actions text-end">
                                            <!-- View Details Button -->
                                            <button class="btn btn-outline-primary btn-sm" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#orderDetailsModal"
                                                    onclick="viewOrderDetails(<?php echo htmlspecialchars(json_encode($order)); ?>)">
                                                <i class="fas fa-eye me-1"></i>View Details
                                            </button>
                                            
                                            <!-- Action Buttons based on status -->
                                            <?php if ($order['status'] === 'pending'): ?>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                    <input type="hidden" name="action" value="cancel_order">
                                                    <button type="submit" class="btn btn-outline-danger btn-sm" 
                                                            onclick="return confirm('Are you sure you want to cancel this order?')">
                                                        <i class="fas fa-times me-1"></i>Cancel
                                                    </button>
                                                </form>
                                            <?php elseif (in_array($order['status'], ['delivered', 'completed'])): ?>
                                                <button class="btn btn-outline-warning btn-sm" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#returnModal"
                                                        onclick="setupReturn(<?php echo $order['id']; ?>)">
                                                    <i class="fas fa-undo me-1"></i>Return
                                                </button>
                                            <?php endif; ?>
                                            
                                            <?php if ($order['status'] === 'shipped'): ?>
                                                <button class="btn btn-outline-success btn-sm">
                                                    <i class="fas fa-truck me-1"></i>Track
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php else: ?>
                <!-- Empty Orders State -->
                <div class="empty-orders">
                    <div class="empty-orders-icon">
                        <i class="fas fa-receipt"></i>
                    </div>
                    <h3 class="text-muted">No Orders Found</h3>
                    <p class="text-muted mb-4">
                        <?php if (!empty($status_filter) || !empty($search)): ?>
                            No orders match your current filters.
                        <?php else: ?>
                            You haven't placed any orders yet.
                        <?php endif; ?>
                    </p>
                    <div class="d-flex gap-2 justify-content-center">
                        <?php if (!empty($status_filter) || !empty($search)): ?>
                            <a href="orders.php" class="btn btn-primary">Clear Filters</a>
                        <?php endif; ?>
                        <a href="index.php" class="btn btn-outline-primary">
                            <i class="fas fa-shopping-bag me-2"></i>Start Shopping
                        </a>
                    </div>
                </div>
            <?php endif; ?>
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

    <!-- Return Request Modal -->
    <div class="modal fade" id="returnModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Request Return</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="order_id" id="returnOrderId">
                        <input type="hidden" name="action" value="request_return">
                        
                        <div class="mb-3">
                            <label class="form-label">Reason for Return</label>
                            <select class="form-select" name="return_reason" required>
                                <option value="">Select a reason</option>
                                <option value="wrong_item">Wrong item received</option>
                                <option value="damaged">Item arrived damaged</option>
                                <option value="not_as_described">Not as described</option>
                                <option value="changed_mind">Changed my mind</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Additional Notes</label>
                            <textarea class="form-control" name="return_notes" rows="3" placeholder="Please provide any additional details..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Submit Request</button>
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
                        <h6>Order Information</h6>
                        <p>
                            <strong>Order Date:</strong> ${new Date(order.created_at).toLocaleDateString()}<br>
                            <strong>Status:</strong> <span class="badge bg-${getStatusColor(order.status)}">${order.status}</span><br>
                            <strong>Items:</strong> ${order.item_count}<br>
                            <strong>Total Quantity:</strong> ${order.total_quantity}
                        </p>
                    </div>
                    <div class="col-md-6">
                        <h6>Payment Details</h6>
                        <p>
                            <strong>Order Total:</strong> $${parseFloat(order.total_amount).toFixed(2)}<br>
                            <strong>Payment Method:</strong> Credit Card<br>
                            <strong>Payment Status:</strong> Paid
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
                case 'delivered': return 'success';
                case 'completed': return 'success';
                case 'cancelled': return 'danger';
                default: return 'secondary';
            }
        }
        
        function setupReturn(orderId) {
            document.getElementById('returnOrderId').value = orderId;
        }
        
        // Auto-dismiss alerts after 5 seconds
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            setTimeout(() => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }, 5000);
        });
    </script>
</body>
</html>