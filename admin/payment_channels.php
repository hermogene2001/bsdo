<?php
session_start();
require_once '../config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Initialize messages
$success_message = '';
$error_message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_channel':
                try {
                    $name = trim($_POST['name']);
                    $type = $_POST['type'];
                    $details = trim($_POST['details']);
                    $account_name = trim($_POST['account_name']);
                    $account_number = trim($_POST['account_number']);
                    $bank_name = trim($_POST['bank_name']);
                    $branch_name = trim($_POST['branch_name']);
                    $country = trim($_POST['country']);
                    $is_active = isset($_POST['is_active']) ? 1 : 0;
                    
                    $stmt = $pdo->prepare("INSERT INTO payment_channels (name, type, details, account_name, account_number, bank_name, branch_name, country, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$name, $type, $details, $account_name, $account_number, $bank_name, $branch_name, $country, $is_active]);
                    
                    $success_message = "Payment channel added successfully!";
                    logAdminActivity("Added payment channel: $name");
                } catch (Exception $e) {
                    $error_message = "Failed to add payment channel: " . $e->getMessage();
                }
                break;
                
            case 'update_channel':
                try {
                    $channel_id = intval($_POST['channel_id']);
                    $name = trim($_POST['name']);
                    $type = $_POST['type'];
                    $details = trim($_POST['details']);
                    $account_name = trim($_POST['account_name']);
                    $account_number = trim($_POST['account_number']);
                    $bank_name = trim($_POST['bank_name']);
                    $branch_name = trim($_POST['branch_name']);
                    $country = trim($_POST['country']);
                    $is_active = isset($_POST['is_active']) ? 1 : 0;
                    
                    $stmt = $pdo->prepare("UPDATE payment_channels SET name = ?, type = ?, details = ?, account_name = ?, account_number = ?, bank_name = ?, branch_name = ?, country = ?, is_active = ? WHERE id = ?");
                    $stmt->execute([$name, $type, $details, $account_name, $account_number, $bank_name, $branch_name, $country, $is_active, $channel_id]);
                    
                    $success_message = "Payment channel updated successfully!";
                    logAdminActivity("Updated payment channel ID: $channel_id");
                } catch (Exception $e) {
                    $error_message = "Failed to update payment channel: " . $e->getMessage();
                }
                break;
                
            case 'delete_channel':
                try {
                    $channel_id = intval($_POST['channel_id']);
                    
                    // Check if channel is being used
                    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM products WHERE payment_channel_id = ?");
                    $stmt->execute([$channel_id]);
                    $usage_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                    
                    if ($usage_count > 0) {
                        $error_message = "Cannot delete payment channel. It is being used by $usage_count products.";
                    } else {
                        $stmt = $pdo->prepare("DELETE FROM payment_channels WHERE id = ?");
                        $stmt->execute([$channel_id]);
                        $success_message = "Payment channel deleted successfully!";
                        logAdminActivity("Deleted payment channel ID: $channel_id");
                    }
                } catch (Exception $e) {
                    $error_message = "Failed to delete payment channel: " . $e->getMessage();
                }
                break;
                
            case 'toggle_status':
                try {
                    $channel_id = intval($_POST['channel_id']);
                    $is_active = intval($_POST['is_active']);
                    
                    $stmt = $pdo->prepare("UPDATE payment_channels SET is_active = ? WHERE id = ?");
                    $stmt->execute([$is_active, $channel_id]);
                    
                    $status_text = $is_active ? 'activated' : 'deactivated';
                    $success_message = "Payment channel $status_text successfully!";
                    logAdminActivity("$status_text payment channel ID: $channel_id");
                } catch (Exception $e) {
                    $error_message = "Failed to update payment channel status: " . $e->getMessage();
                }
                break;
        }
    }
}

// Get all payment channels
$stmt = $pdo->prepare("SELECT * FROM payment_channels ORDER BY created_at DESC");
$stmt->execute();
$payment_channels = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Log admin activity
logAdminActivity("Accessed payment channels management page");

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
    <title>Payment Channels - BSDO Sale Admin</title>
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
        
        .channel-type {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        
        .channel-type.bank { background-color: #4e73df; color: white; }
        .channel-type.mobile_money { background-color: #1cc88a; color: white; }
        .channel-type.paypal { background-color: #3b7bbf; color: white; }
        .channel-type.cryptocurrency { background-color: #9b59b6; color: white; }
        .channel-type.other { background-color: #6c757d; color: white; }
        
        .channel-details {
            background-color: #f8f9fc;
            border-radius: 5px;
            padding: 10px;
            margin-top: 5px;
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
                <li class="active">
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
                                <li><a class="dropdown-item" href="settings.php"><i class="fas fa-cog me-2"></i>Settings</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Page Heading -->
            <div class="d-sm-flex align-items-center justify-content-between mb-4">
                <h1 class="h3 mb-0 text-gray-800">Payment Channels</h1>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addChannelModal">
                    <i class="fas fa-plus fa-sm text-white-50"></i> Add New Channel
                </button>
            </div>

            <!-- Alerts -->
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Payment Channels Table Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Payment Channels (<?php echo count($payment_channels); ?>)</h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($payment_channels)): ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Type</th>
                                        <th>Account Details</th>
                                        <th>Status</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($payment_channels as $channel): ?>
                                        <tr>
                                            <td>
                                                <div class="fw-bold"><?php echo htmlspecialchars($channel['name']); ?></div>
                                            </td>
                                            <td>
                                                <span class="channel-type <?php echo $channel['type']; ?>">
                                                    <?php 
                                                    $type_labels = [
                                                        'bank' => 'Bank Transfer',
                                                        'mobile_money' => 'Mobile Money',
                                                        'paypal' => 'PayPal',
                                                        'cryptocurrency' => 'Cryptocurrency',
                                                        'other' => 'Other'
                                                    ];
                                                    echo $type_labels[$channel['type']] ?? $channel['type'];
                                                    ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div>
                                                    <?php if (!empty($channel['account_name'])): ?>
                                                        <div><strong><?php echo htmlspecialchars($channel['account_name']); ?></strong></div>
                                                    <?php endif; ?>
                                                    <?php if (!empty($channel['account_number'])): ?>
                                                        <div><?php echo htmlspecialchars($channel['account_number']); ?></div>
                                                    <?php endif; ?>
                                                    <?php if (!empty($channel['bank_name'])): ?>
                                                        <div><?php echo htmlspecialchars($channel['bank_name']); ?></div>
                                                    <?php endif; ?>
                                                    <?php if (!empty($channel['branch_name'])): ?>
                                                        <div><?php echo htmlspecialchars($channel['branch_name']); ?></div>
                                                    <?php endif; ?>
                                                    <?php if (!empty($channel['country'])): ?>
                                                        <div><?php echo htmlspecialchars($channel['country']); ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if ($channel['is_active']): ?>
                                                    <span class="badge bg-success">Active</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo date('M j, Y', strtotime($channel['created_at'])); ?></td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <button type="button" class="btn btn-outline-primary" 
                                                            onclick="editChannel(<?php echo $channel['id']; ?>)" 
                                                            title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    
                                                    <?php if ($channel['is_active']): ?>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="channel_id" value="<?php echo $channel['id']; ?>">
                                                            <input type="hidden" name="is_active" value="0">
                                                            <button type="submit" name="action" value="toggle_status" 
                                                                    class="btn btn-warning" title="Deactivate">
                                                                <i class="fas fa-pause"></i>
                                                            </button>
                                                        </form>
                                                    <?php else: ?>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="channel_id" value="<?php echo $channel['id']; ?>">
                                                            <input type="hidden" name="is_active" value="1">
                                                            <button type="submit" name="action" value="toggle_status" 
                                                                    class="btn btn-success" title="Activate">
                                                                <i class="fas fa-play"></i>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                    
                                                    <form method="POST" class="d-inline" 
                                                          onsubmit="return confirm('Are you sure you want to delete this payment channel? This action cannot be undone.');">
                                                        <input type="hidden" name="channel_id" value="<?php echo $channel['id']; ?>">
                                                        <button type="submit" name="action" value="delete_channel" 
                                                                class="btn btn-danger" title="Delete">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-money-bill-wave fa-3x text-muted mb-3"></i>
                            <h4 class="text-muted">No payment channels found</h4>
                            <p class="text-muted mb-4">Add your first payment channel to get started.</p>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addChannelModal">
                                <i class="fas fa-plus me-2"></i>Add Payment Channel
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Channel Modal -->
    <div class="modal fade" id="addChannelModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Payment Channel</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_channel">
                        <div class="mb-3">
                            <label class="form-label">Channel Name *</label>
                            <input type="text" class="form-control" name="name" required>
                            <div class="form-text">A descriptive name for this payment channel</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Channel Type *</label>
                            <select class="form-control" name="type" id="addChannelType" required onchange="toggleChannelFields('add')">
                                <option value="">Select Type</option>
                                <option value="bank">Bank Transfer</option>
                                <option value="mobile_money">Mobile Money</option>
                                <option value="paypal">PayPal</option>
                                <option value="cryptocurrency">Cryptocurrency</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Details</label>
                            <textarea class="form-control" name="details" rows="3" placeholder="Additional information about this payment channel"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Account Name</label>
                                <input type="text" class="form-control" name="account_name">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Account Number</label>
                                <input type="text" class="form-control" name="account_number">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Bank Name</label>
                                <input type="text" class="form-control" name="bank_name" id="addBankName">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Branch Name</label>
                                <input type="text" class="form-control" name="branch_name">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Country</label>
                            <input type="text" class="form-control" name="country">
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="is_active" id="addIsActive" checked>
                            <label class="form-check-label" for="addIsActive">Active</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Channel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Channel Modal -->
    <div class="modal fade" id="editChannelModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Payment Channel</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_channel">
                        <input type="hidden" name="channel_id" id="editChannelId">
                        <div class="mb-3">
                            <label class="form-label">Channel Name *</label>
                            <input type="text" class="form-control" name="name" id="editChannelName" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Channel Type *</label>
                            <select class="form-control" name="type" id="editChannelType" required onchange="toggleChannelFields('edit')">
                                <option value="">Select Type</option>
                                <option value="bank">Bank Transfer</option>
                                <option value="mobile_money">Mobile Money</option>
                                <option value="paypal">PayPal</option>
                                <option value="cryptocurrency">Cryptocurrency</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Details</label>
                            <textarea class="form-control" name="details" id="editChannelDetails" rows="3"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Account Name</label>
                                <input type="text" class="form-control" name="account_name" id="editAccountName">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Account Number</label>
                                <input type="text" class="form-control" name="account_number" id="editAccountNumber">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Bank Name</label>
                                <input type="text" class="form-control" name="bank_name" id="editBankName">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Branch Name</label>
                                <input type="text" class="form-control" name="branch_name" id="editBranchName">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Country</label>
                            <input type="text" class="form-control" name="country" id="editCountry">
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="is_active" id="editIsActive">
                            <label class="form-check-label" for="editIsActive">Active</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Channel</button>
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

        // Edit Channel Function
        function editChannel(channelId) {
            // In a real implementation, you would fetch the channel data via AJAX
            // For now, we'll just show the modal
            document.getElementById('editChannelId').value = channelId;
            var editModal = new bootstrap.Modal(document.getElementById('editChannelModal'));
            editModal.show();
        }

        // Toggle channel fields based on type
        function toggleChannelFields(prefix) {
            var typeSelect = document.getElementById(prefix + 'ChannelType');
            var bankNameField = document.getElementById(prefix + 'BankName');
            
            if (typeSelect.value === 'bank') {
                bankNameField.closest('.row').style.display = 'flex';
            } else {
                bankNameField.closest('.row').style.display = 'none';
            }
        }

        // Initialize field visibility on page load
        document.addEventListener('DOMContentLoaded', function() {
            toggleChannelFields('add');
            toggleChannelFields('edit');
        });
    </script>
</body>
</html>