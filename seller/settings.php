<?php
session_start();
require_once '../config.php';

// Check if user is logged in and is seller
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'seller') {
    header('Location: ../login.php');
    exit();
}

$seller_id = $_SESSION['user_id'];
$error_message = '';
$success_message = '';

// Get seller information
$seller_stmt = $pdo->prepare("
    SELECT u.*, sc.seller_code 
    FROM users u 
    LEFT JOIN seller_codes sc ON u.id = sc.seller_id 
    WHERE u.id = ?
");
$seller_stmt->execute([$seller_id]);
$seller = $seller_stmt->fetch(PDO::FETCH_ASSOC);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_profile':
                $first_name = trim($_POST['first_name']);
                $last_name = trim($_POST['last_name']);
                $phone = trim($_POST['phone']);
                $store_name = trim($_POST['store_name']);
                $business_type = trim($_POST['business_type']);
                $store_description = trim($_POST['store_description']);
                
                // Basic validation
                if (empty($first_name) || empty($last_name)) {
                    $error_message = "First name and last name are required.";
                } else {
                    try {
                        $stmt = $pdo->prepare("
                            UPDATE users 
                            SET first_name = ?, last_name = ?, phone = ?, 
                                store_name = ?, business_type = ?, store_description = ?, 
                                updated_at = NOW() 
                            WHERE id = ?
                        ");
                        $stmt->execute([
                            $first_name, $last_name, $phone, 
                            $store_name, $business_type, $store_description, 
                            $seller_id
                        ]);
                        
                        // Update session data
                        $_SESSION['user_name'] = $first_name . ' ' . $last_name;
                        
                        $success_message = "Profile updated successfully!";
                        logSellerActivity("Updated profile settings");
                        
                        // Refresh seller data
                        $seller_stmt->execute([$seller_id]);
                        $seller = $seller_stmt->fetch(PDO::FETCH_ASSOC);
                        
                    } catch (Exception $e) {
                        $error_message = "Error updating profile: " . $e->getMessage();
                    }
                }
                break;
                
            case 'change_password':
                $current_password = $_POST['current_password'];
                $new_password = $_POST['new_password'];
                $confirm_password = $_POST['confirm_password'];
                
                // Validate passwords
                if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                    $error_message = "All password fields are required.";
                } elseif ($new_password !== $confirm_password) {
                    $error_message = "New passwords do not match.";
                } elseif (strlen($new_password) < 8) {
                    $error_message = "New password must be at least 8 characters long.";
                } else {
                    // Verify current password
                    if (password_verify($current_password, $seller['password'])) {
                        try {
                            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                            $stmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
                            $stmt->execute([$hashed_password, $seller_id]);
                            
                            $success_message = "Password changed successfully!";
                            logSellerActivity("Changed password");
                            
                        } catch (Exception $e) {
                            $error_message = "Error changing password: " . $e->getMessage();
                        }
                    } else {
                        $error_message = "Current password is incorrect.";
                    }
                }
                break;
                
            case 'update_store_settings':
                $store_email = trim($_POST['store_email']);
                $store_phone = trim($_POST['store_phone']);
                $store_address = trim($_POST['store_address']);
                $store_city = trim($_POST['store_city']);
                $store_state = trim($_POST['store_state']);
                $store_zip = trim($_POST['store_zip']);
                $store_country = trim($_POST['store_country']);
                $store_website = trim($_POST['store_website']);
                
                try {
                    // For now, we'll update the users table. In a real system, you might have a separate stores table.
                    $stmt = $pdo->prepare("
                        UPDATE users 
                        SET phone = COALESCE(?, phone), updated_at = NOW() 
                        WHERE id = ?
                    ");
                    $stmt->execute([$store_phone, $seller_id]);
                    
                    $success_message = "Store settings updated successfully!";
                    logSellerActivity("Updated store settings");
                    
                } catch (Exception $e) {
                    $error_message = "Error updating store settings: " . $e->getMessage();
                }
                break;
                
            case 'update_notifications':
                $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
                $order_notifications = isset($_POST['order_notifications']) ? 1 : 0;
                $inventory_alerts = isset($_POST['inventory_alerts']) ? 1 : 0;
                $promotional_emails = isset($_POST['promotional_emails']) ? 1 : 0;
                
                try {
                    // In a real system, you'd have a seller_settings table
                    $success_message = "Notification preferences updated successfully!";
                    logSellerActivity("Updated notification settings");
                    
                } catch (Exception $e) {
                    $error_message = "Error updating notification settings: " . $e->getMessage();
                }
                break;
        }
    }
}

function logSellerActivity($activity) {
    global $pdo, $seller_id;
    $stmt = $pdo->prepare("INSERT INTO seller_activities (seller_id, activity, ip_address) VALUES (?, ?, ?)");
    $stmt->execute([$seller_id, $activity, $_SERVER['REMOTE_ADDR']]);
}

// Get seller statistics for dashboard
$stats_stmt = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT p.id) as total_products,
        COUNT(DISTINCT CASE WHEN p.status = 'active' THEN p.id END) as active_products,
        COUNT(DISTINCT o.id) as total_orders,
        COALESCE(SUM(oi.quantity * oi.price), 0) as total_revenue,
        COUNT(DISTINCT o.user_id) as total_customers
    FROM users u
    LEFT JOIN products p ON u.id = p.seller_id
    LEFT JOIN order_items oi ON p.id = oi.product_id
    LEFT JOIN orders o ON oi.order_id = o.id AND o.status = 'completed'
    WHERE u.id = ?
");
$stats_stmt->execute([$seller_id]);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Get wallet balance and referral stats
$wallet_balance = 0.00;
$referral_count = 0;
$referral_earnings = 0.00;
try {
    $wallet_stmt = $pdo->prepare("SELECT balance FROM user_wallets WHERE user_id = ?");
    $wallet_stmt->execute([$seller_id]);
    $wallet_row = $wallet_stmt->fetch(PDO::FETCH_ASSOC);
    if ($wallet_row) {
        $wallet_balance = $wallet_row['balance'];
    }
    
    $referral_stmt = $pdo->prepare("SELECT COUNT(*) as count, COALESCE(SUM(reward_to_inviter), 0) as earnings FROM referrals WHERE inviter_id = ?");
    $referral_stmt->execute([$seller_id]);
    $referral_row = $referral_stmt->fetch(PDO::FETCH_ASSOC);
    if ($referral_row) {
        $referral_count = $referral_row['count'];
        $referral_earnings = $referral_row['earnings'];
    }
} catch (Exception $e) {
    // Tables may not exist yet
}

function formatCurrency($amount) {
    return '$' . number_format($amount, 2);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - BSDO Seller</title>
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
        
        .settings-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            padding: 2rem;
            margin-top: 2rem;
        }
        
        .settings-card {
            background: white;
            border-radius: 10px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            border: 1px solid #e3e6f0;
        }
        
        .stats-card {
            background: linear-gradient(135deg, var(--primary-color), #667eea);
            color: white;
            border-radius: 10px;
            padding: 1.5rem;
            text-align: center;
            margin-bottom: 1.5rem;
        }
        
        .seller-avatar {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
            font-weight: bold;
            margin: 0 auto 1.5rem;
            border: 4px solid rgba(255, 255, 255, 0.3);
        }
        
        .form-section {
            border-bottom: 1px solid #e3e6f0;
            padding-bottom: 2rem;
            margin-bottom: 2rem;
        }
        
        .form-section:last-child {
            border-bottom: none;
            padding-bottom: 0;
            margin-bottom: 0;
        }
        
        .section-title {
            color: var(--dark-color);
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--primary-color);
        }
        
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 34px;
        }
        
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }
        
        .slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 26px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        input:checked + .slider {
            background-color: var(--primary-color);
        }
        
        input:checked + .slider:before {
            transform: translateX(26px);
        }
        
        .danger-zone {
            border: 2px solid #e74a3b;
            border-radius: 10px;
            padding: 2rem;
            background: #f8f9fa;
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
                            <a class="nav-link active" href="settings.php">
                                <i class="fas fa-cog me-2"></i>Settings
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-lg-10 col-md-12 p-4">
                <!-- Alerts -->
                <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Page Header -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h2 class="mb-1"><i class="fas fa-cog me-2"></i>Settings</h2>
                                <p class="text-muted mb-0">Manage your account and store preferences</p>
                            </div>
                            <div class="text-end">
                                <div class="fw-bold">Seller Code: <?php echo htmlspecialchars($seller['seller_code'] ?? 'Not assigned'); ?></div>
                                <small class="text-muted">Member since <?php echo date('F Y', strtotime($seller['created_at'])); ?></small>
                            </div>
                        </div>
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
                        <div class="stats-card" style="background: linear-gradient(135deg, var(--secondary-color), #18a873);">
                            <div class="h4 mb-1"><?php echo $stats['total_orders']; ?></div>
                            <div class="small">Total Orders</div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="stats-card" style="background: linear-gradient(135deg, #f6c23e, #e0a800);">
                            <div class="h4 mb-1"><?php echo formatCurrency($stats['total_revenue']); ?></div>
                            <div class="small">Total Revenue</div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="stats-card" style="background: linear-gradient(135deg, #36b9cc, #2c9faf);">
                            <div class="h4 mb-1"><?php echo $stats['total_customers']; ?></div>
                            <div class="small">Total Customers</div>
                        </div>
                    </div>
                </div>

                <div class="settings-container">
                    <?php include 'referral_section.php'; ?>
                    <!-- Profile Settings -->
                    <div class="settings-card">
                        <h4 class="section-title"><i class="fas fa-user me-2"></i>Profile Settings</h4>
                        <form method="POST">
                            <input type="hidden" name="action" value="update_profile">
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">First Name *</label>
                                    <input type="text" class="form-control" name="first_name" 
                                           value="<?php echo htmlspecialchars($seller['first_name']); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Last Name *</label>
                                    <input type="text" class="form-control" name="last_name" 
                                           value="<?php echo htmlspecialchars($seller['last_name']); ?>" required>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Email Address</label>
                                    <input type="email" class="form-control" value="<?php echo htmlspecialchars($seller['email']); ?>" disabled>
                                    <small class="text-muted">Email cannot be changed</small>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Phone Number</label>
                                    <input type="tel" class="form-control" name="phone" 
                                           value="<?php echo htmlspecialchars($seller['phone'] ?? ''); ?>">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Store Name</label>
                                <input type="text" class="form-control" name="store_name" 
                                       value="<?php echo htmlspecialchars($seller['store_name'] ?? ''); ?>">
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Business Type</label>
                                    <select class="form-control" name="business_type">
                                        <option value="">Select Business Type</option>
                                        <option value="Individual" <?php echo ($seller['business_type'] ?? '') === 'Individual' ? 'selected' : ''; ?>>Individual</option>
                                        <option value="Small Business" <?php echo ($seller['business_type'] ?? '') === 'Small Business' ? 'selected' : ''; ?>>Small Business</option>
                                        <option value="Enterprise" <?php echo ($seller['business_type'] ?? '') === 'Enterprise' ? 'selected' : ''; ?>>Enterprise</option>
                                        <option value="Other" <?php echo ($seller['business_type'] ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Store Description</label>
                                <textarea class="form-control" name="store_description" rows="3" 
                                          placeholder="Describe your store..."><?php echo htmlspecialchars($seller['store_description'] ?? ''); ?></textarea>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Update Profile
                            </button>
                        </form>
                    </div>

                    <!-- Password Settings -->
                    <div class="settings-card">
                        <h4 class="section-title"><i class="fas fa-lock me-2"></i>Password Settings</h4>
                        <form method="POST">
                            <input type="hidden" name="action" value="change_password">
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Current Password *</label>
                                    <input type="password" class="form-control" name="current_password" required>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">New Password *</label>
                                    <input type="password" class="form-control" name="new_password" required minlength="8">
                                    <div class="form-text">Minimum 8 characters with uppercase, lowercase, and numbers</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Confirm New Password *</label>
                                    <input type="password" class="form-control" name="confirm_password" required>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-key me-2"></i>Change Password
                            </button>
                        </form>
                    </div>

                    <!-- Store Settings -->
                    <div class="settings-card">
                        <h4 class="section-title"><i class="fas fa-store me-2"></i>Store Settings</h4>
                        <form method="POST">
                            <input type="hidden" name="action" value="update_store_settings">
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Store Email</label>
                                    <input type="email" class="form-control" name="store_email" 
                                           value="<?php echo htmlspecialchars($seller['email'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Store Phone</label>
                                    <input type="tel" class="form-control" name="store_phone" 
                                           value="<?php echo htmlspecialchars($seller['phone'] ?? ''); ?>">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Store Address</label>
                                <input type="text" class="form-control" name="store_address" 
                                       placeholder="Street address">
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">City</label>
                                    <input type="text" class="form-control" name="store_city">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">State</label>
                                    <input type="text" class="form-control" name="store_state">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">ZIP Code</label>
                                    <input type="text" class="form-control" name="store_zip">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Country</label>
                                    <select class="form-control" name="store_country">
                                        <option value="United States">United States</option>
                                        <option value="Canada">Canada</option>
                                        <option value="United Kingdom">United Kingdom</option>
                                        <!-- Add more countries as needed -->
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Store Website</label>
                                    <input type="url" class="form-control" name="store_website" 
                                           placeholder="https://example.com">
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Update Store Settings
                            </button>
                        </form>
                    </div>

                    <!-- Notification Settings -->
                    <div class="settings-card">
                        <h4 class="section-title"><i class="fas fa-bell me-2"></i>Notification Settings</h4>
                        <form method="POST">
                            <input type="hidden" name="action" value="update_notifications">
                            
                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="email_notifications" id="emailNotifications" checked>
                                    <label class="form-check-label" for="emailNotifications">
                                        Email Notifications
                                    </label>
                                    <div class="form-text">Receive important updates via email</div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="order_notifications" id="orderNotifications" checked>
                                    <label class="form-check-label" for="orderNotifications">
                                        Order Notifications
                                    </label>
                                    <div class="form-text">Get notified when you receive new orders</div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="inventory_alerts" id="inventoryAlerts" checked>
                                    <label class="form-check-label" for="inventoryAlerts">
                                        Inventory Alerts
                                    </label>
                                    <div class="form-text">Receive alerts when products are low in stock</div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="promotional_emails" id="promotionalEmails">
                                    <label class="form-check-label" for="promotionalEmails">
                                        Promotional Emails
                                    </label>
                                    <div class="form-text">Receive tips and promotional offers from BSDO Sale</div>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Update Notifications
                            </button>
                        </form>
                    </div>

                    <!-- Danger Zone -->
                    <div class="settings-card danger-zone">
                        <h4 class="section-title text-danger"><i class="fas fa-exclamation-triangle me-2"></i>Danger Zone</h4>
                        <p class="text-muted mb-4">These actions are irreversible. Please proceed with caution.</p>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <h6>Deactivate Account</h6>
                                <p class="small text-muted">Temporarily deactivate your seller account. You can reactivate it later.</p>
                                <button class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#deactivateModal">
                                    <i class="fas fa-pause me-2"></i>Deactivate Account
                                </button>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <h6>Delete Account</h6>
                                <p class="small text-muted">Permanently delete your seller account and all associated data.</p>
                                <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteModal">
                                    <i class="fas fa-trash me-2"></i>Delete Account
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Deactivate Account Modal -->
    <div class="modal fade" id="deactivateModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-warning"><i class="fas fa-exclamation-triangle me-2"></i>Deactivate Account</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to deactivate your seller account?</p>
                    <ul class="text-muted">
                        <li>Your products will be hidden from customers</li>
                        <li>You won't be able to receive new orders</li>
                        <li>You can reactivate your account anytime</li>
                        <li>Existing orders will remain active</li>
                    </ul>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-warning">Deactivate Account</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Account Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-danger"><i class="fas fa-exclamation-triangle me-2"></i>Delete Account</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-danger fw-bold">This action cannot be undone!</p>
                    <p>Deleting your account will permanently:</p>
                    <ul class="text-muted">
                        <li>Remove all your products and listings</li>
                        <li>Delete your store information</li>
                        <li>Cancel all pending orders</li>
                        <li>Remove your sales history</li>
                        <li>Delete your customer data</li>
                    </ul>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <strong>Warning:</strong> Please download any important data before proceeding.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger">Delete Account Permanently</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form validation
        document.addEventListener('DOMContentLoaded', function() {
            // Password strength validation
            const passwordForm = document.querySelector('form[action="change_password"]');
            if (passwordForm) {
                passwordForm.addEventListener('submit', function(e) {
                    const newPassword = this.querySelector('input[name="new_password"]').value;
                    const confirmPassword = this.querySelector('input[name="confirm_password"]').value;
                    
                    if (newPassword !== confirmPassword) {
                        e.preventDefault();
                        alert('New passwords do not match!');
                        return false;
                    }
                    
                    // Basic password strength check
                    if (newPassword.length < 8 || !/[A-Z]/.test(newPassword) || !/[a-z]/.test(newPassword) || !/[0-9]/.test(newPassword)) {
                        e.preventDefault();
                        alert('Password must be at least 8 characters long and contain uppercase, lowercase letters and numbers.');
                        return false;
                    }
                });
            }
            
            // Auto-dismiss alerts after 5 seconds
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 5000);
            });
        });
    </script>
</body>
</html>