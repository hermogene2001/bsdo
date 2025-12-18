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

// Get minimum withdrawal amount from settings
$min_withdrawal_amount = 5.00;
$stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'min_withdrawal_amount'");
$stmt->execute();
$setting = $stmt->fetch(PDO::FETCH_ASSOC);
if ($setting) {
    $min_withdrawal_amount = floatval($setting['setting_value']);
}

// Get seller wallet balance
$wallet_balance = 0.00;
try {
    $wallet_stmt = $pdo->prepare("SELECT balance FROM user_wallets WHERE user_id = ?");
    $wallet_stmt->execute([$seller_id]);
    $wallet_row = $wallet_stmt->fetch(PDO::FETCH_ASSOC);
    if ($wallet_row) {
        $wallet_balance = floatval($wallet_row['balance']);
    }
} catch (Exception $e) {
    $error_message = "Error fetching wallet balance.";
}

// Handle withdrawal request submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_withdrawal'])) {
    $amount = floatval($_POST['amount']);
    $payment_method = trim($_POST['payment_method']);
    $payment_details = trim($_POST['payment_details']);
    
    // Validation
    if ($amount <= 0) {
        $error_message = "Please enter a valid amount.";
    } elseif ($amount < $min_withdrawal_amount) {
        $error_message = "Minimum withdrawal amount is $" . number_format($min_withdrawal_amount, 2);
    } elseif ($amount > $wallet_balance) {
        $error_message = "Insufficient balance. Your current balance is $" . number_format($wallet_balance, 2);
    } elseif (empty($payment_method)) {
        $error_message = "Please select a payment method.";
    } elseif (empty($payment_details)) {
        $error_message = "Please provide payment details.";
    } else {
        try {
            // Insert withdrawal request
            $stmt = $pdo->prepare("INSERT INTO withdrawal_requests (seller_id, amount, payment_method, payment_details) VALUES (?, ?, ?, ?)");
            $stmt->execute([$seller_id, $amount, $payment_method, $payment_details]);
            
            // Deduct amount from wallet (in a real system, this might be done after approval)
            $new_balance = $wallet_balance - $amount;
            $update_stmt = $pdo->prepare("UPDATE user_wallets SET balance = ? WHERE user_id = ?");
            $update_stmt->execute([$new_balance, $seller_id]);
            
            $success_message = "Withdrawal request submitted successfully! Your request is pending approval.";
            
            // Refresh wallet balance
            $wallet_balance = $new_balance;
        } catch (Exception $e) {
            $error_message = "Error submitting withdrawal request: " . $e->getMessage();
        }
    }
}

// Get recent withdrawal requests
$withdrawal_requests = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM withdrawal_requests WHERE seller_id = ? ORDER BY created_at DESC LIMIT 10");
    $stmt->execute([$seller_id]);
    $withdrawal_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Continue without requests if there's an error
}

function formatCurrency($amount) {
    return '$' . number_format($amount, 2);
}

function formatDate($date) {
    return date('M j, Y g:i A', strtotime($date));
}

function getStatusBadgeClass($status) {
    switch ($status) {
        case 'approved':
            return 'bg-success';
        case 'rejected':
            return 'bg-danger';
        default:
            return 'bg-warning';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Withdrawal Request - BSDO Seller</title>
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
        
        .section-title {
            color: var(--dark-color);
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--primary-color);
        }
        
        .wallet-balance {
            font-size: 2rem;
            font-weight: bold;
        }
        
        .minimum-amount {
            font-size: 1.2rem;
            color: #e74a3b;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <!-- Mobile menu button -->
            <button class="btn btn-link text-white d-lg-none me-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar">
                <i class="fas fa-bars"></i>
            </button>

            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-store me-2"></i>
                <strong>BSDO Seller</strong>
            </a>
            
            <div class="d-flex align-items-center">
                <div class="dropdown">
                    <a class="nav-link dropdown-toggle text-white d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown">
                        <div class="bg-white rounded-circle me-2" style="width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;">
                            <span class="text-primary fw-bold"><?php echo strtoupper(substr($_SESSION['user_name'], 0, 1)); ?></span>
                        </div>
                        <span><?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
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

    <!-- Mobile Sidebar -->
    <div class="offcanvas offcanvas-start" tabindex="-1" id="mobileSidebar">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title">Menu</h5>
            <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="offcanvas-body">
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
                    <a class="nav-link" href="live_stream.php">
                        <i class="fas fa-video me-2"></i>Live Stream
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
                    <a class="nav-link active" href="withdrawal_request.php">
                        <i class="fas fa-money-bill-transfer me-2"></i>Withdraw Funds
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="settings.php">
                        <i class="fas fa-cog me-2"></i>Settings
                    </a>
                </li>
            </ul>
        </div>
    </div>

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
                            <a class="nav-link" href="live_stream.php">
                                <i class="fas fa-video me-2"></i>Live Stream
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
                            <a class="nav-link active" href="withdrawal_request.php">
                                <i class="fas fa-money-bill-transfer me-2"></i>Withdraw Funds
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="settings.php">
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
                                <h2 class="mb-1"><i class="fas fa-money-bill-transfer me-2"></i>Withdraw Funds</h2>
                                <p class="text-muted mb-0">Request withdrawal of your earned referral funds</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Wallet Balance Card -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="stats-card">
                            <div class="h4 mb-1">Available Balance</div>
                            <div class="wallet-balance mb-2"><?php echo formatCurrency($wallet_balance); ?></div>
                            <div class="small">Minimum withdrawal amount: <span class="minimum-amount"><?php echo formatCurrency($min_withdrawal_amount); ?></span></div>
                        </div>
                    </div>
                </div>

                <div class="settings-container">
                    <!-- Withdrawal Request Form -->
                    <div class="settings-card">
                        <h4 class="section-title"><i class="fas fa-paper-plane me-2"></i>Request Withdrawal</h4>
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Amount *</label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="number" class="form-control" name="amount" step="0.01" min="0.01" 
                                               max="<?php echo $wallet_balance; ?>" placeholder="0.00" required>
                                    </div>
                                    <div class="form-text">Available balance: <?php echo formatCurrency($wallet_balance); ?></div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Payment Method *</label>
                                    <select class="form-control" name="payment_method" required>
                                        <option value="">Select Payment Method</option>
                                        <option value="bank_transfer">Bank Transfer</option>
                                        <option value="paypal">PayPal</option>
                                        <option value="mobile_money">Mobile Money</option>
                                        <option value="cryptocurrency">Cryptocurrency</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Payment Details *</label>
                                <textarea class="form-control" name="payment_details" rows="3" 
                                          placeholder="Provide your payment details (e.g., bank account number, PayPal email, mobile money number, etc.)" required></textarea>
                                <div class="form-text">Include all necessary information for processing your payment</div>
                            </div>
                            
                            <button type="submit" name="request_withdrawal" class="btn btn-primary" 
                                    <?php echo ($wallet_balance < $min_withdrawal_amount) ? 'disabled' : ''; ?>>
                                <i class="fas fa-paper-plane me-2"></i>Submit Withdrawal Request
                            </button>
                            
                            <?php if ($wallet_balance < $min_withdrawal_amount): ?>
                                <div class="mt-2 text-muted">
                                    <i class="fas fa-info-circle me-2"></i>
                                    You need at least <?php echo formatCurrency($min_withdrawal_amount); ?> to request a withdrawal.
                                </div>
                            <?php endif; ?>
                        </form>
                    </div>

                    <!-- Recent Withdrawal Requests -->
                    <div class="settings-card">
                        <h4 class="section-title"><i class="fas fa-history me-2"></i>Withdrawal History</h4>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Amount</th>
                                        <th>Payment Method</th>
                                        <th>Status</th>
                                        <th>Details</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($withdrawal_requests)): ?>
                                        <?php foreach ($withdrawal_requests as $request): ?>
                                            <tr>
                                                <td><?php echo formatDate($request['created_at']); ?></td>
                                                <td><?php echo formatCurrency($request['amount']); ?></td>
                                                <td><?php echo ucfirst(str_replace('_', ' ', $request['payment_method'])); ?></td>
                                                <td>
                                                    <span class="badge <?php echo getStatusBadgeClass($request['status']); ?>">
                                                        <?php echo ucfirst($request['status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars(substr($request['payment_details'], 0, 30)) . (strlen($request['payment_details']) > 30 ? '...' : ''); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center text-muted">No withdrawal requests yet</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-dismiss alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
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