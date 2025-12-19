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
            case 'update_general_settings':
                try {
                    $pdo->beginTransaction();
                    
                    $settings = [
                        'site_name' => $_POST['site_name'],
                        'site_email' => $_POST['site_email'],
                        'currency' => $_POST['currency'],
                        'timezone' => $_POST['timezone'],
                        'date_format' => $_POST['date_format'],
                        'items_per_page' => intval($_POST['items_per_page'])
                    ];
                    
                    foreach ($settings as $key => $value) {
                        $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) 
                                              VALUES (?, ?) 
                                              ON DUPLICATE KEY UPDATE setting_value = ?");
                        $stmt->execute([$key, $value, $value]);
                    }
                    
                    $pdo->commit();
                    $success_message = "General settings updated successfully!";
                    logAdminActivity("Updated general settings");
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error_message = "Failed to update settings: " . $e->getMessage();
                }
                break;
                
            case 'update_payment_settings':
                try {
                    $pdo->beginTransaction();
                    
                    $settings = [
                        'commission_rate' => floatval($_POST['commission_rate']),
                        'tax_rate' => floatval($_POST['tax_rate']),
                        'payment_methods' => implode(',', $_POST['payment_methods'] ?? []),
                        'currency_code' => $_POST['currency_code'],
                        'min_order_amount' => floatval($_POST['min_order_amount']),
                        'payment_verification_rate' => floatval($_POST['payment_verification_rate']) // New setting
                    ];
                    
                    foreach ($settings as $key => $value) {
                        $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) 
                                              VALUES (?, ?) 
                                              ON DUPLICATE KEY UPDATE setting_value = ?");
                        $stmt->execute([$key, $value, $value]);
                    }
                    
                    $pdo->commit();
                    $success_message = "Payment settings updated successfully!";
                    logAdminActivity("Updated payment settings");
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error_message = "Failed to update payment settings: " . $e->getMessage();
                }
                break;
                
            case 'update_email_settings':
                try {
                    $pdo->beginTransaction();
                    
                    $settings = [
                        'smtp_host' => $_POST['smtp_host'],
                        'smtp_port' => intval($_POST['smtp_port']),
                        'smtp_username' => $_POST['smtp_username'],
                        'smtp_password' => $_POST['smtp_password'],
                        'smtp_encryption' => $_POST['smtp_encryption'],
                        'from_email' => $_POST['from_email'],
                        'from_name' => $_POST['from_name']
                    ];
                    
                    foreach ($settings as $key => $value) {
                        $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) 
                                              VALUES (?, ?) 
                                              ON DUPLICATE KEY UPDATE setting_value = ?");
                        $stmt->execute([$key, $value, $value]);
                    }
                    
                    $pdo->commit();
                    $success_message = "Email settings updated successfully!";
                    logAdminActivity("Updated email settings");
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error_message = "Failed to update email settings: " . $e->getMessage();
                }
                break;
                
            case 'update_security_settings':
                try {
                    $pdo->beginTransaction();
                    
                    $settings = [
                        'login_attempts' => intval($_POST['login_attempts']),
                        'lockout_time' => intval($_POST['lockout_time']),
                        'session_timeout' => intval($_POST['session_timeout']),
                        'password_min_length' => intval($_POST['password_min_length']),
                        'require_2fa' => isset($_POST['require_2fa']) ? 1 : 0,
                        'enable_captcha' => isset($_POST['enable_captcha']) ? 1 : 0
                    ];
                    
                    foreach ($settings as $key => $value) {
                        $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) 
                                              VALUES (?, ?) 
                                              ON DUPLICATE KEY UPDATE setting_value = ?");
                        $stmt->execute([$key, $value, $value]);
                    }
                    
                    $pdo->commit();
                    $success_message = "Security settings updated successfully!";
                    logAdminActivity("Updated security settings");
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error_message = "Failed to update security settings: " . $e->getMessage();
                }
                break;
                
            case 'update_profile':
                try {
                    $user_id = $_SESSION['user_id'];
                    $first_name = trim($_POST['first_name']);
                    $last_name = trim($_POST['last_name']);
                    $email = trim($_POST['email']);
                    $phone = trim($_POST['phone']);
                    
                    // Check if email already exists (excluding current user)
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                    $stmt->execute([$email, $user_id]);
                    if ($stmt->rowCount() > 0) {
                        $error_message = "Email already exists!";
                        break;
                    }
                    
                    $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ? WHERE id = ?");
                    $stmt->execute([$first_name, $last_name, $email, $phone, $user_id]);
                    
                    // Update session data
                    $_SESSION['user_name'] = $first_name . ' ' . $last_name;
                    $_SESSION['user_email'] = $email;
                    
                    $success_message = "Profile updated successfully!";
                    logAdminActivity("Updated admin profile");
                } catch (Exception $e) {
                    $error_message = "Failed to update profile: " . $e->getMessage();
                }
                break;
                
            case 'change_password':
                try {
                    $user_id = $_SESSION['user_id'];
                    $current_password = $_POST['current_password'];
                    $new_password = $_POST['new_password'];
                    $confirm_password = $_POST['confirm_password'];
                    
                    // Verify current password
                    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!password_verify($current_password, $user['password'])) {
                        $error_message = "Current password is incorrect!";
                        break;
                    }
                    
                    if ($new_password !== $confirm_password) {
                        $error_message = "New passwords do not match!";
                        break;
                    }
                    
                    if (strlen($new_password) < 8) {
                        $error_message = "Password must be at least 8 characters long!";
                        break;
                    }
                    
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmt->execute([$hashed_password, $user_id]);
                    
                    $success_message = "Password changed successfully!";
                    logAdminActivity("Changed admin password");
                } catch (Exception $e) {
                    $error_message = "Failed to change password: " . $e->getMessage();
                }
                break;
                
            case 'clear_cache':
                // Clear cache directories or reset cache
                $cache_dirs = ['../cache/', '../temp/'];
                $cleared = 0;
                foreach ($cache_dirs as $dir) {
                    if (is_dir($dir)) {
                        array_map('unlink', glob("$dir/*.*"));
                        $cleared++;
                    }
                }
                $success_message = "Cache cleared successfully! ($cleared directories)";
                logAdminActivity("Cleared system cache");
                break;
                
            case 'backup_database':
                try {
                    $backup_file = '../backups/backup_' . date('Y-m-d_H-i-s') . '.sql';
                    $backup_dir = dirname($backup_file);
                    
                    if (!is_dir($backup_dir)) {
                        mkdir($backup_dir, 0755, true);
                    }
                    
                    // Simple backup - in production, use mysqldump command
                    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
                    $backup_content = "";
                    
                    foreach ($tables as $table) {
                        $backup_content .= "-- Table: $table\n";
                        $result = $pdo->query("SELECT * FROM $table");
                        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
                            $columns = implode("`, `", array_keys($row));
                            $values = implode("', '", array_map('addslashes', array_values($row)));
                            $backup_content .= "INSERT INTO `$table` (`$columns`) VALUES ('$values');\n";
                        }
                        $backup_content .= "\n";
                    }
                    
                    file_put_contents($backup_file, $backup_content);
                    $success_message = "Database backup created successfully!";
                    logAdminActivity("Created database backup");
                } catch (Exception $e) {
                    $error_message = "Failed to create backup: " . $e->getMessage();
                }
                break;
                
            case 'update_support_links':
                try {
                    $pdo->beginTransaction();
                    
                    // Delete existing links
                    $stmt = $pdo->prepare("DELETE FROM customer_support_links");
                    $stmt->execute();
                    
                    // Insert new links
                    if (isset($_POST['support_links'])) {
                        $links = $_POST['support_links'];
                        $stmt = $pdo->prepare("INSERT INTO customer_support_links (name, url, description, icon, is_active, sort_order) VALUES (?, ?, ?, ?, ?, ?)");
                        
                        foreach ($links as $index => $link) {
                            $stmt->execute([
                                $link['name'],
                                $link['url'],
                                $link['description'],
                                $link['icon'],
                                isset($link['is_active']) ? 1 : 0,
                                $index
                            ]);
                        }
                    }
                    
                    $pdo->commit();
                    $success_message = "Customer support links updated successfully!";
                    logAdminActivity("Updated customer support links");
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error_message = "Failed to update support links: " . $e->getMessage();
                }
                break;
                
            case 'update_social_links':
                try {
                    $pdo->beginTransaction();
                    
                    // Delete existing links
                    $stmt = $pdo->prepare("DELETE FROM social_links");
                    $stmt->execute();
                    
                    // Insert new links
                    if (isset($_POST['social_links'])) {
                        $links = $_POST['social_links'];
                        $stmt = $pdo->prepare("INSERT INTO social_links (name, url, icon, is_active, sort_order) VALUES (?, ?, ?, ?, ?)");
                        
                        foreach ($links as $index => $link) {
                            $stmt->execute([
                                $link['name'],
                                $link['url'],
                                $link['icon'],
                                isset($link['is_active']) ? 1 : 0,
                                $index
                            ]);
                        }
                    }
                    
                    $pdo->commit();
                    $success_message = "Social links updated successfully!";
                    logAdminActivity("Updated social links");
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error_message = "Failed to update social links: " . $e->getMessage();
                }
                break;
        }
    }
}

// Get current settings
$settings_stmt = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings");
$settings_stmt->execute();
$system_settings = [];
while ($row = $settings_stmt->fetch(PDO::FETCH_ASSOC)) {
    $system_settings[$row['setting_key']] = $row['setting_value'];
}

// Get admin profile
$admin_stmt = $pdo->prepare("SELECT first_name, last_name, email, phone FROM users WHERE id = ?");
$admin_stmt->execute([$_SESSION['user_id']]);
$admin_profile = $admin_stmt->fetch(PDO::FETCH_ASSOC);

// Get customer support links
$support_links_stmt = $pdo->prepare("SELECT * FROM customer_support_links ORDER BY sort_order ASC");
$support_links_stmt->execute();
$support_links = $support_links_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get social links
$social_links_stmt = $pdo->prepare("SELECT * FROM social_links ORDER BY sort_order ASC");
$social_links_stmt->execute();
$social_links = $social_links_stmt->fetchAll(PDO::FETCH_ASSOC);

// Default values for settings
$default_settings = [
    'site_name' => 'BSDO Sale',
    'site_email' => 'admin@bsdosale.com',
    'currency' => 'USD',
    'timezone' => 'UTC',
    'date_format' => 'Y-m-d',
    'items_per_page' => '10',
    'commission_rate' => '10',
    'tax_rate' => '0',
    'payment_methods' => 'card,paypal,bank',
    'currency_code' => 'USD',
    'min_order_amount' => '0',
    'payment_verification_rate' => '0.50', // New default setting
    'login_attempts' => '5',
    'lockout_time' => '30',
    'session_timeout' => '60',
    'password_min_length' => '8',
    'require_2fa' => '0',
    'enable_captcha' => '0'
];

// Merge with actual settings
$current_settings = array_merge($default_settings, $system_settings);

// Log admin activity
logAdminActivity("Accessed settings page");

function logAdminActivity($activity) {
    global $pdo;
    if (isset($_SESSION['user_id'])) {
        $stmt = $pdo->prepare("INSERT INTO admin_activities (admin_id, activity, ip_address) VALUES (?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], $activity, $_SERVER['REMOTE_ADDR'] ?? '']);
    }
}

// Get available timezones
$timezones = DateTimeZone::listIdentifiers(DateTimeZone::ALL);

// Get currency codes
$currencies = [
    'USD' => 'US Dollar', 'EUR' => 'Euro', 'GBP' => 'British Pound',
    'JPY' => 'Japanese Yen', 'CAD' => 'Canadian Dollar', 'AUD' => 'Australian Dollar',
    'CHF' => 'Swiss Franc', 'CNY' => 'Chinese Yuan', 'INR' => 'Indian Rupee'
];

// Get payment methods
$payment_methods = [
    'card' => 'Credit/Debit Card',
    'paypal' => 'PayPal',
    'bank' => 'Bank Transfer',
    'crypto' => 'Cryptocurrency',
    'cash' => 'Cash on Delivery'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - BSDO Sale Admin</title>
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
        
        .nav-pills .nav-link {
            color: var(--dark-color);
            border-radius: 5px;
            margin-bottom: 5px;
        }
        
        .nav-pills .nav-link.active {
            background-color: var(--primary-color);
            color: white;
        }
        
        .nav-pills .nav-link i {
            margin-right: 8px;
            width: 20px;
            text-align: center;
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
        
        .setting-group {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            border-left: 4px solid var(--primary-color);
        }
        
        .password-strength {
            height: 5px;
            border-radius: 2px;
            margin-top: 5px;
            transition: all 0.3s;
        }
        
        .strength-weak { background-color: var(--danger-color); width: 25%; }
        .strength-medium { background-color: var(--warning-color); width: 50%; }
        .strength-strong { background-color: var(--secondary-color); width: 75%; }
        .strength-very-strong { background-color: #28a745; width: 100%; }
        
        .backup-status {
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
        }
        
        .backup-success { background-color: #d4edda; color: #155724; }
        .backup-warning { background-color: #fff3cd; color: #856404; }
        .backup-danger { background-color: #f8d7da; color: #721c24; }
        
        .system-info {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
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
                <li class="active">
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
                                <li><a class="dropdown-item" href="#profile-settings"><i class="fas fa-user me-2"></i>Profile</a></li>
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
                <h1 class="h3 mb-0 text-gray-800">System Settings</h1>
                <div class="d-flex gap-2">
                    <button class="btn btn-primary" onclick="testEmailSettings()">
                        <i class="fas fa-envelope fa-sm text-white-50"></i> Test Email
                    </button>
                    <button class="btn btn-success" onclick="saveAllSettings()">
                        <i class="fas fa-save fa-sm text-white-50"></i> Save All
                    </button>
                </div>
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

            <div class="row">
                <!-- Settings Navigation -->
                <div class="col-lg-3 mb-4">
                    <div class="card">
                        <div class="card-body">
                            <ul class="nav nav-pills flex-column" id="settingsTabs" role="tablist">
                                <li class="nav-item">
                                    <a class="nav-link active" id="general-tab" data-bs-toggle="pill" href="#general" role="tab">
                                        <i class="fas fa-cog"></i> General Settings
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" id="payment-tab" data-bs-toggle="pill" href="#payment" role="tab">
                                        <i class="fas fa-credit-card"></i> Payment Settings
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" id="email-tab" data-bs-toggle="pill" href="#email" role="tab">
                                        <i class="fas fa-envelope"></i> Email Settings
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" id="security-tab" data-bs-toggle="pill" href="#security" role="tab">
                                        <i class="fas fa-shield-alt"></i> Security Settings
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" id="profile-tab" data-bs-toggle="pill" href="#profile" role="tab">
                                        <i class="fas fa-user"></i> Profile Settings
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" id="support-tab" data-bs-toggle="pill" href="#support" role="tab">
                                        <i class="fas fa-headset"></i> Support Links
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" id="social-tab" data-bs-toggle="pill" href="#social" role="tab">
                                        <i class="fas fa-hashtag"></i> Social Links
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" id="maintenance-tab" data-bs-toggle="pill" href="#maintenance" role="tab">
                                        <i class="fas fa-tools"></i> Maintenance
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- System Information -->
                    <div class="system-info">
                        <h6><i class="fas fa-info-circle"></i> System Info</h6>
                        <div class="small">
                            <div>PHP: <?php echo phpversion(); ?></div>
                            <div>MySQL: <?php echo $pdo->getAttribute(PDO::ATTR_SERVER_VERSION); ?></div>
                            <div>Users: <?php 
                                $user_count = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
                                echo number_format($user_count);
                            ?></div>
                            <div>Orders: <?php 
                                $order_count = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
                                echo number_format($order_count);
                            ?></div>
                        </div>
                    </div>
                </div>

                <!-- Settings Content -->
                <div class="col-lg-9">
                    <div class="tab-content" id="settingsTabsContent">
                        <!-- General Settings -->
                        <div class="tab-pane fade show active" id="general" role="tabpanel">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0"><i class="fas fa-cog me-2"></i>General Settings</h5>
                                </div>
                                <div class="card-body">
                                    <form method="POST">
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Site Name</label>
                                                <input type="text" class="form-control" name="site_name" 
                                                       value="<?php echo htmlspecialchars($current_settings['site_name']); ?>" required>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Site Email</label>
                                                <input type="email" class="form-control" name="site_email" 
                                                       value="<?php echo htmlspecialchars($current_settings['site_email']); ?>" required>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-4 mb-3">
                                                <label class="form-label">Currency</label>
                                                <select class="form-control" name="currency">
                                                    <?php foreach ($currencies as $code => $name): ?>
                                                        <option value="<?php echo $code; ?>" 
                                                            <?php echo $current_settings['currency'] === $code ? 'selected' : ''; ?>>
                                                            <?php echo $name; ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <label class="form-label">Timezone</label>
                                                <select class="form-control" name="timezone">
                                                    <?php foreach ($timezones as $tz): ?>
                                                        <option value="<?php echo $tz; ?>" 
                                                            <?php echo $current_settings['timezone'] === $tz ? 'selected' : ''; ?>>
                                                            <?php echo $tz; ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <label class="form-label">Items Per Page</label>
                                                <input type="number" class="form-control" name="items_per_page" 
                                                       value="<?php echo htmlspecialchars($current_settings['items_per_page']); ?>" min="5" max="100">
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Date Format</label>
                                            <select class="form-control" name="date_format">
                                                <option value="Y-m-d" <?php echo $current_settings['date_format'] === 'Y-m-d' ? 'selected' : ''; ?>>YYYY-MM-DD</option>
                                                <option value="d/m/Y" <?php echo $current_settings['date_format'] === 'd/m/Y' ? 'selected' : ''; ?>>DD/MM/YYYY</option>
                                                <option value="m/d/Y" <?php echo $current_settings['date_format'] === 'm/d/Y' ? 'selected' : ''; ?>>MM/DD/YYYY</option>
                                            </select>
                                        </div>
                                        <button type="submit" name="action" value="update_general_settings" class="btn btn-primary">
                                            <i class="fas fa-save me-2"></i>Save General Settings
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Payment Settings -->
                        <div class="tab-pane fade" id="payment" role="tabpanel">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0"><i class="fas fa-credit-card me-2"></i>Payment Settings</h5>
                                </div>
                                <div class="card-body">
                                    <form method="POST">
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Commission Rate (%)</label>
                                                <input type="number" class="form-control" name="commission_rate" 
                                                       value="<?php echo htmlspecialchars($current_settings['commission_rate']); ?>" step="0.1" min="0" max="50">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Tax Rate (%)</label>
                                                <input type="number" class="form-control" name="tax_rate" 
                                                       value="<?php echo htmlspecialchars($current_settings['tax_rate']); ?>" step="0.1" min="0" max="30">
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Minimum Order Amount</label>
                                                <input type="number" class="form-control" name="min_order_amount" 
                                                       value="<?php echo htmlspecialchars($current_settings['min_order_amount']); ?>" step="0.01" min="0">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Currency Code</label>
                                                <select class="form-control" name="currency_code">
                                                    <?php foreach ($currencies as $code => $name): ?>
                                                        <option value="<?php echo $code; ?>" 
                                                            <?php echo $current_settings['currency_code'] === $code ? 'selected' : ''; ?>>
                                                            <?php echo $code; ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Payment Verification Rate (%)</label>
                                                <input type="number" class="form-control" name="payment_verification_rate" 
                                                       value="<?php echo htmlspecialchars($current_settings['payment_verification_rate'] ?? '0.50'); ?>" step="0.01" min="0" max="100">
                                                <div class="form-text">Percentage of product price to be paid for verification</div>
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Payment Methods</label>
                                            <div class="row">
                                                <?php 
                                                $enabled_methods = explode(',', $current_settings['payment_methods']);
                                                foreach ($payment_methods as $key => $name): ?>
                                                    <div class="col-md-3 mb-2">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox" name="payment_methods[]" 
                                                                   value="<?php echo $key; ?>" 
                                                                   <?php echo in_array($key, $enabled_methods) ? 'checked' : ''; ?>>
                                                            <label class="form-check-label"><?php echo $name; ?></label>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                        <button type="submit" name="action" value="update_payment_settings" class="btn btn-primary">
                                            <i class="fas fa-save me-2"></i>Save Payment Settings
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Email Settings -->
                        <div class="tab-pane fade" id="email" role="tabpanel">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0"><i class="fas fa-envelope me-2"></i>Email Settings</h5>
                                </div>
                                <div class="card-body">
                                    <form method="POST">
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">SMTP Host</label>
                                                <input type="text" class="form-control" name="smtp_host" 
                                                       value="<?php echo htmlspecialchars($current_settings['smtp_host']); ?>">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">SMTP Port</label>
                                                <input type="number" class="form-control" name="smtp_port" 
                                                       value="<?php echo htmlspecialchars($current_settings['smtp_port']); ?>">
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">SMTP Username</label>
                                                <input type="text" class="form-control" name="smtp_username" 
                                                       value="<?php echo htmlspecialchars($current_settings['smtp_username']); ?>">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">SMTP Password</label>
                                                <input type="password" class="form-control" name="smtp_password" 
                                                       value="<?php echo htmlspecialchars($current_settings['smtp_password']); ?>">
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Encryption</label>
                                                <select class="form-control" name="smtp_encryption">
                                                    <option value="">None</option>
                                                    <option value="ssl" <?php echo $current_settings['smtp_encryption'] === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                                                    <option value="tls" <?php echo $current_settings['smtp_encryption'] === 'tls' ? 'selected' : ''; ?>>TLS</option>
                                                </select>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">From Email</label>
                                                <input type="email" class="form-control" name="from_email" 
                                                       value="<?php echo htmlspecialchars($current_settings['from_email']); ?>">
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">From Name</label>
                                            <input type="text" class="form-control" name="from_name" 
                                                   value="<?php echo htmlspecialchars($current_settings['from_name']); ?>">
                                        </div>
                                        <button type="submit" name="action" value="update_email_settings" class="btn btn-primary">
                                            <i class="fas fa-save me-2"></i>Save Email Settings
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Security Settings -->
                        <div class="tab-pane fade" id="security" role="tabpanel">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0"><i class="fas fa-shield-alt me-2"></i>Security Settings</h5>
                                </div>
                                <div class="card-body">
                                    <form method="POST">
                                        <div class="row">
                                            <div class="col-md-4 mb-3">
                                                <label class="form-label">Max Login Attempts</label>
                                                <input type="number" class="form-control" name="login_attempts" 
                                                       value="<?php echo htmlspecialchars($current_settings['login_attempts']); ?>" min="1" max="10">
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <label class="form-label">Lockout Time (minutes)</label>
                                                <input type="number" class="form-control" name="lockout_time" 
                                                       value="<?php echo htmlspecialchars($current_settings['lockout_time']); ?>" min="1" max="1440">
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <label class="form-label">Session Timeout (minutes)</label>
                                                <input type="number" class="form-control" name="session_timeout" 
                                                       value="<?php echo htmlspecialchars($current_settings['session_timeout']); ?>" min="5" max="1440">
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Minimum Password Length</label>
                                                <input type="number" class="form-control" name="password_min_length" 
                                                       value="<?php echo htmlspecialchars($current_settings['password_min_length']); ?>" min="6" max="32">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Security Features</label>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="require_2fa" 
                                                           <?php echo $current_settings['require_2fa'] ? 'checked' : ''; ?>>
                                                    <label class="form-check-label">Require 2-Factor Authentication</label>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="enable_captcha" 
                                                           <?php echo $current_settings['enable_captcha'] ? 'checked' : ''; ?>>
                                                    <label class="form-check-label">Enable CAPTCHA on Login</label>
                                                </div>
                                            </div>
                                        </div>
                                        <button type="submit" name="action" value="update_security_settings" class="btn btn-primary">
                                            <i class="fas fa-save me-2"></i>Save Security Settings
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Profile Settings -->
                        <div class="tab-pane fade" id="profile" role="tabpanel">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0"><i class="fas fa-user me-2"></i>Profile Settings</h5>
                                </div>
                                <div class="card-body">
                                    <!-- Profile Update Form -->
                                    <form method="POST">
                                        <h6 class="border-bottom pb-2">Update Profile Information</h6>
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">First Name</label>
                                                <input type="text" class="form-control" name="first_name" 
                                                       value="<?php echo htmlspecialchars($admin_profile['first_name']); ?>" required>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Last Name</label>
                                                <input type="text" class="form-control" name="last_name" 
                                                       value="<?php echo htmlspecialchars($admin_profile['last_name']); ?>" required>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Email Address</label>
                                                <input type="email" class="form-control" name="email" 
                                                       value="<?php echo htmlspecialchars($admin_profile['email']); ?>" required>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Phone Number</label>
                                                <input type="tel" class="form-control" name="phone" 
                                                       value="<?php echo htmlspecialchars($admin_profile['phone'] ?? ''); ?>">
                                            </div>
                                        </div>
                                        <button type="submit" name="action" value="update_profile" class="btn btn-primary">
                                            <i class="fas fa-save me-2"></i>Update Profile
                                        </button>
                                    </form>

                                    <!-- Password Change Form -->
                                    <form method="POST">
                                        <h6 class="border-bottom pb-2">Change Password</h6>
                                        <div class="row">
                                            <div class="col-md-4 mb-3">
                                                <label class="form-label">Current Password</label>
                                                <input type="password" class="form-control" name="current_password" required>
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <label class="form-label">New Password</label>
                                                <input type="password" class="form-control" name="new_password" required 
                                                       onkeyup="checkPasswordStrength(this.value)">
                                                <div class="password-strength" id="passwordStrength"></div>
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <label class="form-label">Confirm Password</label>
                                                <input type="password" class="form-control" name="confirm_password" required>
                                            </div>
                                        </div>
                                        <button type="submit" name="action" value="change_password" class="btn btn-primary">
                                            <i class="fas fa-key me-2"></i>Change Password
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Customer Support Links -->
                        <div class="tab-pane fade" id="support" role="tabpanel">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0"><i class="fas fa-headset me-2"></i>Customer Support Links</h5>
                                </div>
                                <div class="card-body">
                                    <p class="text-muted">Manage customer support links that will be displayed to both sellers and clients.</p>
                                    
                                    <form method="POST" action="<?php echo $_SERVER['PHP_SELF']; ?>">
                                        <input type="hidden" name="action" value="update_support_links">
                                        
                                        <div id="support-links-container">
                                            <?php foreach ($support_links as $index => $link): ?>
                                            <div class="card mb-3 support-link-item">
                                                <div class="card-body">
                                                    <div class="row">
                                                        <div class="col-md-5">
                                                            <div class="mb-3">
                                                                <label class="form-label">Link Name</label>
                                                                <input type="text" class="form-control" name="support_links[<?php echo $index; ?>][name]" value="<?php echo htmlspecialchars($link['name']); ?>" required>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label">URL</label>
                                                                <input type="text" class="form-control" name="support_links[<?php echo $index; ?>][url]" value="<?php echo htmlspecialchars($link['url']); ?>" required>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-5">
                                                            <div class="mb-3">
                                                                <label class="form-label">Description</label>
                                                                <textarea class="form-control" name="support_links[<?php echo $index; ?>][description]" rows="2"><?php echo htmlspecialchars($link['description']); ?></textarea>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label">Icon Class (Font Awesome)</label>
                                                                <input type="text" class="form-control" name="support_links[<?php echo $index; ?>][icon]" value="<?php echo htmlspecialchars($link['icon']); ?>" placeholder="e.g., fa-comments">
                                                            </div>
                                                        </div>
                                                        <div class="col-md-2">
                                                            <div class="mb-3">
                                                                <label class="form-label">Active</label><br>
                                                                <div class="form-check form-switch">
                                                                    <input class="form-check-input" type="checkbox" name="support_links[<?php echo $index; ?>][is_active]" <?php echo $link['is_active'] ? 'checked' : ''; ?>>
                                                                </div>
                                                            </div>
                                                            <div class="mb-3">
                                                                <button type="button" class="btn btn-danger remove-link">Remove</button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                            
                                            <!-- Template for new links -->
                                            <div id="new-link-template" class="card mb-3 support-link-item d-none">
                                                <div class="card-body">
                                                    <div class="row">
                                                        <div class="col-md-5">
                                                            <div class="mb-3">
                                                                <label class="form-label">Link Name</label>
                                                                <input type="text" class="form-control" name="support_links[new_0][name]" required>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label">URL</label>
                                                                <input type="text" class="form-control" name="support_links[new_0][url]" required>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-5">
                                                            <div class="mb-3">
                                                                <label class="form-label">Description</label>
                                                                <textarea class="form-control" name="support_links[new_0][description]" rows="2"></textarea>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label">Icon Class (Font Awesome)</label>
                                                                <input type="text" class="form-control" name="support_links[new_0][icon]" placeholder="e.g., fa-comments">
                                                            </div>
                                                        </div>
                                                        <div class="col-md-2">
                                                            <div class="mb-3">
                                                                <label class="form-label">Active</label><br>
                                                                <div class="form-check form-switch">
                                                                    <input class="form-check-input" type="checkbox" name="support_links[new_0][is_active]" checked>
                                                                </div>
                                                            </div>
                                                            <div class="mb-3">
                                                                <button type="button" class="btn btn-danger remove-link">Remove</button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <button type="button" id="add-support-link" class="btn btn-secondary">Add New Link</button>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <button type="submit" class="btn btn-primary">Save Support Links</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Social Media Links -->
                        <div class="tab-pane fade" id="social" role="tabpanel">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0"><i class="fas fa-hashtag me-2"></i>Social Media Links</h5>
                                </div>
                                <div class="card-body">
                                    <p class="text-muted">Manage social media links that will be displayed to both sellers and clients.</p>
                                    
                                    <form method="POST" action="<?php echo $_SERVER['PHP_SELF']; ?>">
                                        <input type="hidden" name="action" value="update_social_links">
                                        
                                        <div id="social-links-container">
                                            <?php foreach ($social_links as $index => $link): ?>
                                            <div class="card mb-3 social-link-item">
                                                <div class="card-body">
                                                    <div class="row">
                                                        <div class="col-md-4">
                                                            <div class="mb-3">
                                                                <label class="form-label">Platform Name</label>
                                                                <input type="text" class="form-control" name="social_links[<?php echo $index; ?>][name]" value="<?php echo htmlspecialchars($link['name']); ?>" required>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-5">
                                                            <div class="mb-3">
                                                                <label class="form-label">URL</label>
                                                                <input type="text" class="form-control" name="social_links[<?php echo $index; ?>][url]" value="<?php echo htmlspecialchars($link['url']); ?>" required>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <div class="mb-3">
                                                                <label class="form-label">Icon Class (Font Awesome)</label>
                                                                <input type="text" class="form-control" name="social_links[<?php echo $index; ?>][icon]" value="<?php echo htmlspecialchars($link['icon']); ?>" placeholder="e.g., fab fa-facebook-f">
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label class="form-label">Active</label><br>
                                                                <div class="form-check form-switch">
                                                                    <input class="form-check-input" type="checkbox" name="social_links[<?php echo $index; ?>][is_active]" <?php echo $link['is_active'] ? 'checked' : ''; ?>>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6 text-end">
                                                            <div class="mb-3">
                                                                <button type="button" class="btn btn-danger remove-social-link">Remove</button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                            
                                            <!-- Template for new links -->
                                            <div id="new-social-link-template" class="card mb-3 social-link-item d-none">
                                                <div class="card-body">
                                                    <div class="row">
                                                        <div class="col-md-4">
                                                            <div class="mb-3">
                                                                <label class="form-label">Platform Name</label>
                                                                <input type="text" class="form-control" name="social_links[new_0][name]" required>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-5">
                                                            <div class="mb-3">
                                                                <label class="form-label">URL</label>
                                                                <input type="text" class="form-control" name="social_links[new_0][url]" required>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <div class="mb-3">
                                                                <label class="form-label">Icon Class (Font Awesome)</label>
                                                                <input type="text" class="form-control" name="social_links[new_0][icon]" placeholder="e.g., fab fa-facebook-f">
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <div class="mb-3">
                                                                <label class="form-label">Active</label><br>
                                                                <div class="form-check form-switch">
                                                                    <input class="form-check-input" type="checkbox" name="social_links[new_0][is_active]" checked>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6 text-end">
                                                            <div class="mb-3">
                                                                <button type="button" class="btn btn-danger remove-social-link">Remove</button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <button type="button" id="add-social-link" class="btn btn-secondary">Add New Social Link</button>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <button type="submit" class="btn btn-primary">Save Social Links</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Maintenance -->
                        <div class="tab-pane fade" id="maintenance" role="tabpanel">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0"><i class="fas fa-tools me-2"></i>System Maintenance</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6 mb-4">
                                            <div class="setting-group">
                                                <h6><i class="fas fa-broom me-2"></i>Clear Cache</h6>
                                                <p class="text-muted small">Clear temporary cache files to free up space and resolve issues.</p>
                                                <form method="POST">
                                                    <button type="submit" name="action" value="clear_cache" class="btn btn-warning">
                                                        <i class="fas fa-broom me-2"></i>Clear Cache
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                        <div class="col-md-6 mb-4">
                                            <div class="setting-group">
                                                <h6><i class="fas fa-database me-2"></i>Database Backup</h6>
                                                <p class="text-muted small">Create a backup of your database for safety and migration.</p>
                                                <form method="POST">
                                                    <button type="submit" name="action" value="backup_database" class="btn btn-success">
                                                        <i class="fas fa-download me-2"></i>Create Backup
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Backup Status -->
                                    <div class="backup-status backup-warning">
                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                        Last backup: 
                                        <?php
                                        $backup_files = glob('../backups/backup_*.sql');
                                        if (!empty($backup_files)) {
                                            $latest_backup = max($backup_files);
                                            echo date('F j, Y g:i A', filemtime($latest_backup));
                                        } else {
                                            echo 'No backups found';
                                        }
                                        ?>
                                    </div>

                                    <!-- System Logs -->
                                    <div class="setting-group mt-4">
                                        <h6><i class="fas fa-clipboard-list me-2"></i>Recent Activity Logs</h6>
                                        <div style="max-height: 200px; overflow-y: auto;">
                                            <?php
                                            $logs_stmt = $pdo->prepare("
                                                SELECT a.activity, a.created_at, u.first_name, u.last_name 
                                                FROM admin_activities a 
                                                LEFT JOIN users u ON a.admin_id = u.id 
                                                ORDER BY a.created_at DESC 
                                                LIMIT 10
                                            ");
                                            $logs_stmt->execute();
                                            $recent_logs = $logs_stmt->fetchAll(PDO::FETCH_ASSOC);
                                            
                                            if (!empty($recent_logs)) {
                                                foreach ($recent_logs as $log) {
                                                    echo '<div class="small border-bottom pb-2 mb-2">';
                                                    echo '<div class="fw-bold">' . htmlspecialchars($log['activity']) . '</div>';
                                                    echo '<div class="text-muted">';
                                                    echo 'By: ' . htmlspecialchars($log['first_name'] . ' ' . $log['last_name']);
                                                    echo ' • ' . date('M j, g:i A', strtotime($log['created_at']));
                                                    echo '</div></div>';
                                                }
                                            } else {
                                                echo '<p class="text-muted small">No recent activity</p>';
                                            }
                                            ?>
                                        </div>
                                    </div>
                                </div>
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

        // Password strength checker
        function checkPasswordStrength(password) {
            const strengthBar = document.getElementById('passwordStrength');
            let strength = 0;
            
            if (password.length >= 8) strength++;
            if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength++;
            if (password.match(/\d/)) strength++;
            if (password.match(/[^a-zA-Z\d]/)) strength++;
            
            strengthBar.className = 'password-strength ';
            if (password.length === 0) {
                strengthBar.className = 'password-strength';
            } else if (strength === 1) {
                strengthBar.className += 'strength-weak';
            } else if (strength === 2) {
                strengthBar.className += 'strength-medium';
            } else if (strength === 3) {
                strengthBar.className += 'strength-strong';
            } else if (strength === 4) {
                strengthBar.className += 'strength-very-strong';
            }
        }

        // Test email settings
        function testEmailSettings() {
            alert('Email test functionality would be implemented here. This would send a test email to verify SMTP settings.');
        }

        // Save all settings
        function saveAllSettings() {
            alert('This would save all settings across all tabs. In a real implementation, this would submit all forms.');
        }

        // Handle tab persistence
        document.addEventListener('DOMContentLoaded', function() {
            const activeTab = localStorage.getItem('activeSettingsTab');
            if (activeTab) {
                const tab = new bootstrap.Tab(document.getElementById(activeTab + '-tab'));
                tab.show();
            }
            
            // Save active tab on change
            document.querySelectorAll('#settingsTabs .nav-link').forEach(tab => {
                tab.addEventListener('shown.bs.tab', function (e) {
                    localStorage.setItem('activeSettingsTab', e.target.id.replace('-tab', ''));
                });
            });
            
            // Support links functionality
            let linkCounter = <?php echo count($support_links); ?>;
            
            document.getElementById('add-support-link').addEventListener('click', function() {
                const template = document.getElementById('new-link-template');
                const clone = template.cloneNode(true);
                clone.classList.remove('d-none');
                
                // Update names to use unique indices
                const inputs = clone.querySelectorAll('input, textarea');
                inputs.forEach(input => {
                    if (input.name) {
                        input.name = input.name.replace('new_0', 'new_' + linkCounter);
                    }
                });
                
                // Remove the template ID
                clone.removeAttribute('id');
                
                // Insert before the template
                template.parentNode.insertBefore(clone, template);
                
                linkCounter++;
            });
            
            // Handle remove buttons for support links
            document.addEventListener('click', function(e) {
                if (e.target.classList.contains('remove-link')) {
                    e.target.closest('.support-link-item').remove();
                }
            });
            
            // Social links functionality
            let socialLinkCounter = <?php echo count($social_links); ?>;
            
            document.getElementById('add-social-link').addEventListener('click', function() {
                const template = document.getElementById('new-social-link-template');
                const clone = template.cloneNode(true);
                clone.classList.remove('d-none');
                
                // Update names to use unique indices
                const inputs = clone.querySelectorAll('input, textarea');
                inputs.forEach(input => {
                    if (input.name) {
                        input.name = input.name.replace('new_0', 'new_' + socialLinkCounter);
                    }
                });
                
                // Remove the template ID
                clone.removeAttribute('id');
                
                // Insert before the template
                template.parentNode.insertBefore(clone, template);
                
                socialLinkCounter++;
            });
            
            // Handle remove buttons for social links
            document.addEventListener('click', function(e) {
                if (e.target.classList.contains('remove-social-link')) {
                    e.target.closest('.social-link-item').remove();
                }
            });
        });
    </script>
</body>
</html>