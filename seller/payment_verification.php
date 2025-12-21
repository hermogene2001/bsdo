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

// Get payment verification rate setting
$stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'payment_verification_rate'");
$stmt->execute();
$payment_verification_rate = floatval($stmt->fetch(PDO::FETCH_ASSOC)['setting_value'] ?? 0.50);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'upload_payment_slip':
                $product_id = intval($_POST['product_id']);
                $amount = floatval($_POST['amount']);
                
                // Validate product belongs to seller
                $stmt = $pdo->prepare("SELECT id, name, price FROM products WHERE id = ? AND seller_id = ?");
                $stmt->execute([$product_id, $seller_id]);
                $product = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$product) {
                    $error_message = "Invalid product!";
                    break;
                }
                
                // Handle file upload
                if (isset($_FILES['payment_slip']) && $_FILES['payment_slip']['error'] === UPLOAD_ERR_OK) {
                    $upload_dir = '../uploads/payment_slips/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    $file_name = uniqid() . '_' . basename($_FILES['payment_slip']['name']);
                    $target_file = $upload_dir . $file_name;
                    
                    // Check file type
                    $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
                    $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];
                    
                    if (!in_array($imageFileType, $allowed_types)) {
                        $error_message = "Only JPG, JPEG, PNG, GIF & PDF files are allowed!";
                        break;
                    }
                    
                    // Check file size (5MB max)
                    if ($_FILES['payment_slip']['size'] > 5000000) {
                        $error_message = "File is too large. Maximum 5MB allowed!";
                        break;
                    }
                    
                    // Upload file
                    if (move_uploaded_file($_FILES['payment_slip']['tmp_name'], $target_file)) {
                        $slip_path = 'uploads/payment_slips/' . $file_name;
                        
                        // Insert payment slip record
                        $stmt = $pdo->prepare("INSERT INTO payment_slips (product_id, seller_id, slip_path, amount, verification_rate) VALUES (?, ?, ?, ?, ?)");
                        $stmt->execute([$product_id, $seller_id, $slip_path, $amount, $payment_verification_rate]);
                        
                        $success_message = "Payment slip uploaded successfully! It will be reviewed by our team.";
                        logSellerActivity("Uploaded payment slip for product ID: $product_id");
                    } else {
                        $error_message = "Sorry, there was an error uploading your file.";
                    }
                } else {
                    $error_message = "Please select a file to upload!";
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

// Get products that need payment verification
$stmt = $pdo->prepare("
    SELECT p.*, c.name as category_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.seller_id = ? 
    AND (p.verification_payment_status IS NULL OR p.verification_payment_status != 'paid')
    AND p.payment_channel_id IS NOT NULL
    ORDER BY p.created_at DESC
");
$stmt->execute([$seller_id]);
$unverified_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get pending payment slips for seller's products
$stmt = $pdo->prepare("
    SELECT ps.*, p.name as product_name 
    FROM payment_slips ps 
    JOIN products p ON ps.product_id = p.id 
    WHERE ps.seller_id = ? AND ps.status = 'pending'
    ORDER BY ps.created_at DESC
");
$stmt->execute([$seller_id]);
$pending_payment_slips = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get verified payment slips
$stmt = $pdo->prepare("
    SELECT ps.*, p.name as product_name 
    FROM payment_slips ps 
    JOIN products p ON ps.product_id = p.id 
    WHERE ps.seller_id = ? AND ps.status = 'verified'
    ORDER BY ps.updated_at DESC
    LIMIT 10
");
$stmt->execute([$seller_id]);
$verified_payment_slips = $stmt->fetchAll(PDO::FETCH_ASSOC);

logSellerActivity("Accessed payment verification page");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Verification - BSDO Seller</title>
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
            min-height: 100vh;
        }
        
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .sidebar {
            background-color: var(--dark-color);
            color: white;
            min-height: calc(100vh - 56px);
            position: sticky;
            top: 56px;
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
        
        .card {
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            border: none;
            margin-bottom: 1.5rem;
        }
        
        .stats-card {
            border-left: 4px solid var(--primary-color);
        }
        
        .stats-card.success { border-left-color: var(--secondary-color); }
        .stats-card.warning { border-left-color: var(--warning-color); }
        .stats-card.danger { border-left-color: var(--danger-color); }
        
        .table th {
            font-weight: 600;
        }
        
        .upload-area {
            border: 2px dashed #ddd;
            border-radius: 10px;
            padding: 2rem;
            text-align: center;
            transition: all 0.3s;
            cursor: pointer;
        }
        
        .upload-area:hover {
            border-color: var(--primary-color);
            background-color: rgba(78, 115, 223, 0.05);
        }
        
        .upload-area i {
            font-size: 3rem;
            color: #ddd;
            margin-bottom: 1rem;
        }
        
        .upload-area:hover i {
            color: var(--primary-color);
        }
        
        @media (max-width: 991.98px) {
            .sidebar {
                display: none;
            }
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
            
            <!-- Mobile menu button -->
            <button class="navbar-toggler d-lg-none" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <!-- Mobile navigation menu -->
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav d-lg-none">
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
                        <a class="nav-link active" href="payment_verification.php">
                            <i class="fas fa-money-check me-2"></i>Payment Verification
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="settings.php">
                            <i class="fas fa-cog me-2"></i>Settings
                        </a>
                    </li>
                </ul>
            </div>

            <div class="d-flex align-items-center">
                <div class="dropdown">
                    <a class="nav-link dropdown-toggle text-white d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown">
                        <div class="bg-white rounded-circle me-2" style="width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;">
                            <span class="text-primary fw-bold"><?php echo strtoupper(substr($_SESSION['first_name'] ?? 'S', 0, 1)); ?></span>
                        </div>
                        <span><?php echo htmlspecialchars($_SESSION['first_name'] ?? 'Seller') . ' ' . htmlspecialchars($_SESSION['last_name'] ?? ''); ?></span>
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
            <div class="col-lg-2 sidebar">
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
                            <a class="nav-link active" href="payment_verification.php">
                                <i class="fas fa-money-check me-2"></i>Payment Verification
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
            <div class="col-lg-10 col-12 p-4">
                <!-- Page Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2><i class="fas fa-money-check me-2"></i>Payment Verification</h2>
                        <p class="text-muted mb-0">Manage payment verification for your products</p>
                    </div>
                </div>

                <!-- Messages -->
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

                <!-- Stats Cards -->
                <div class="row mb-4">
                    <div class="col-md-4 mb-3">
                        <div class="card stats-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="mb-1"><?php echo count($unverified_products); ?></h5>
                                        <p class="mb-0 small">Products Needing Payment</p>
                                    </div>
                                    <i class="fas fa-exclamation-circle fa-2x text-primary"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card stats-card warning">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="mb-1"><?php echo count($pending_payment_slips); ?></h5>
                                        <p class="mb-0 small">Pending Verification</p>
                                    </div>
                                    <i class="fas fa-clock fa-2x text-warning"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card stats-card success">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="mb-1"><?php echo count($verified_payment_slips); ?></h5>
                                        <p class="mb-0 small">Verified Payments</p>
                                    </div>
                                    <i class="fas fa-check-circle fa-2x text-success"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Products Needing Payment Verification -->
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-exclamation-circle me-2"></i>Products Needing Payment Verification</h5>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($unverified_products)): ?>
                                    <div class="table-responsive">
                                        <table class="table">
                                            <thead>
                                                <tr>
                                                    <th>Product</th>
                                                    <th>Price</th>
                                                    <th>Fee (<?php echo $payment_verification_rate; ?>%)</th>
                                                    <th>Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($unverified_products as $product): ?>
                                                    <tr>
                                                        <td>
                                                            <div class="fw-bold"><?php echo htmlspecialchars($product['name']); ?></div>
                                                            <div class="small text-muted"><?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?></div>
                                                        </td>
                                                        <td><?php echo '$' . number_format($product['price'], 2); ?></td>
                                                        <td><?php echo '$' . number_format($product['price'] * $payment_verification_rate / 100, 2); ?></td>
                                                        <td>
                                                            <button class="btn btn-sm btn-primary" 
                                                                    onclick="showUploadForm(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars($product['name'], ENT_QUOTES); ?>', <?php echo $product['price'] * $payment_verification_rate / 100; ?>)">
                                                                <i class="fas fa-upload me-1"></i>Upload Slip
                                                            </button>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-5">
                                        <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                                        <h5 class="text-success">All Products Verified!</h5>
                                        <p class="text-muted">No products require payment verification at this time.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Pending Payment Slips -->
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-clock me-2"></i>Pending Payment Slips</h5>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($pending_payment_slips)): ?>
                                    <div class="table-responsive">
                                        <table class="table">
                                            <thead>
                                                <tr>
                                                    <th>Product</th>
                                                    <th>Amount</th>
                                                    <th>Date Submitted</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($pending_payment_slips as $slip): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($slip['product_name']); ?></td>
                                                        <td><?php echo '$' . number_format($slip['amount'], 2); ?></td>
                                                        <td><?php echo date('M j, Y', strtotime($slip['created_at'])); ?></td>
                                                        <td>
                                                            <span class="badge bg-warning">Pending Review</span>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-5">
                                        <i class="fas fa-file-invoice fa-3x text-muted mb-3"></i>
                                        <h5 class="text-muted">No Pending Payments</h5>
                                        <p class="text-muted">You have no pending payment verifications.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Recently Verified Payments -->
                        <div class="card mt-4">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-check-circle me-2"></i>Recently Verified Payments</h5>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($verified_payment_slips)): ?>
                                    <div class="table-responsive">
                                        <table class="table">
                                            <thead>
                                                <tr>
                                                    <th>Product</th>
                                                    <th>Amount</th>
                                                    <th>Date Verified</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($verified_payment_slips as $slip): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($slip['product_name']); ?></td>
                                                        <td><?php echo '$' . number_format($slip['amount'], 2); ?></td>
                                                        <td><?php echo date('M j, Y', strtotime($slip['updated_at'])); ?></td>
                                                        <td>
                                                            <span class="badge bg-success">Verified</span>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-3">
                                        <p class="text-muted mb-0">No verified payments yet.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Upload Payment Slip Modal -->
    <div class="modal fade" id="uploadSlipModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-header">
                        <h5 class="modal-title">Upload Payment Slip</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="upload_payment_slip">
                        <input type="hidden" name="product_id" id="product_id">
                        
                        <div class="mb-3">
                            <label class="form-label">Product</label>
                            <input type="text" class="form-control" id="product_name" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Verification Fee Amount ($)</label>
                            <input type="number" class="form-control" name="amount" id="amount" step="0.01" min="0" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Payment Slip</label>
                            <div class="upload-area" id="uploadArea">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <p class="mb-0">Click to browse or drag & drop your payment slip</p>
                                <p class="small text-muted mb-0">JPG, PNG, GIF, PDF (Max 5MB)</p>
                            </div>
                            <input type="file" class="form-control d-none" name="payment_slip" id="payment_slip" accept=".jpg,.jpeg,.png,.gif,.pdf" required>
                        </div>
                        
                        <div class="alert alert-info">
                            <h6 class="alert-heading"><i class="fas fa-info-circle me-2"></i>Important Information</h6>
                            <p class="mb-0">Please ensure your payment slip is clear and legible. Our team will review your payment within 24-48 hours.</p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Upload Payment Slip</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Function to show upload form modal
        function showUploadForm(productId, productName, amount) {
            document.getElementById('product_id').value = productId;
            document.getElementById('product_name').value = productName;
            document.getElementById('amount').value = amount.toFixed(2);
            new bootstrap.Modal(document.getElementById('uploadSlipModal')).show();
        }
        
        // File upload area interaction
        document.addEventListener('DOMContentLoaded', function() {
            const uploadArea = document.getElementById('uploadArea');
            const fileInput = document.getElementById('payment_slip');
            
            uploadArea.addEventListener('click', () => {
                fileInput.click();
            });
            
            fileInput.addEventListener('change', function() {
                if (this.files.length > 0) {
                    uploadArea.innerHTML = `
                        <i class="fas fa-file-alt text-primary"></i>
                        <p class="mb-0">${this.files[0].name}</p>
                        <p class="small text-muted mb-0">Click to change file</p>
                    `;
                }
            });
            
            uploadArea.addEventListener('dragover', (e) => {
                e.preventDefault();
                uploadArea.classList.add('border-primary');
            });
            
            uploadArea.addEventListener('dragleave', () => {
                uploadArea.classList.remove('border-primary');
            });
            
            uploadArea.addEventListener('drop', (e) => {
                e.preventDefault();
                uploadArea.classList.remove('border-primary');
                
                if (e.dataTransfer.files.length) {
                    fileInput.files = e.dataTransfer.files;
                    uploadArea.innerHTML = `
                        <i class="fas fa-file-alt text-primary"></i>
                        <p class="mb-0">${e.dataTransfer.files[0].name}</p>
                        <p class="small text-muted mb-0">Click to change file</p>
                    `;
                }
            });
        });
    </script>
</body>
</html>