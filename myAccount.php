<?php
session_start();
require_once 'config.php';

// Require logged-in client
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? 'client') !== 'client') {
    header('Location: login.php');
    exit();
}

$clientId = $_SESSION['user_id'];
$error_message = '';
$success_message = '';

// Fetch client info
$client_stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$client_stmt->execute([$clientId]);
$client = $client_stmt->fetch(PDO::FETCH_ASSOC);

if (!$client) {
    session_destroy();
    header('Location: login.php');
    exit();
}

// Common navbar variables (match index.php)
$is_logged_in = isset($_SESSION['user_id']);
$user_name = $_SESSION['user_name'] ?? '';
$user_role = $_SESSION['user_role'] ?? '';
$user_id = $_SESSION['user_id'] ?? null;

// Client pending inquiries badge
$pending_inquiries_count = 0;
if ($is_logged_in && $user_role === 'client') {
    $inquiries_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM inquiries WHERE user_id = ? AND status = 'replied'");
    $inquiries_stmt->execute([$user_id]);
    $pending_inquiries_count = $inquiries_stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'update_profile') {
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        
        if ($first_name === '' || $last_name === '') {
            $error_message = 'First name and last name are required.';
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, phone = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$first_name, $last_name, $phone, $clientId]);
                $_SESSION['user_name'] = $first_name . ' ' . $last_name;
                $success_message = 'Profile updated successfully!';
                // refresh
                $client_stmt->execute([$clientId]);
                $client = $client_stmt->fetch(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                $error_message = 'Error updating profile: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'change_password') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if ($current_password === '' || $new_password === '' || $confirm_password === '') {
            $error_message = 'All password fields are required.';
        } elseif ($new_password !== $confirm_password) {
            $error_message = 'New passwords do not match.';
        } elseif (strlen($new_password) < 8 || !preg_match('/[A-Z]/', $new_password) || !preg_match('/[a-z]/', $new_password) || !preg_match('/[0-9]/', $new_password)) {
            $error_message = 'Password must be at least 8 characters with upper, lower, and number.';
        } else {
            if (password_verify($current_password, $client['password'])) {
                try {
                    $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$hashed, $clientId]);
                    $success_message = 'Password changed successfully!';
                } catch (Exception $e) {
                    $error_message = 'Error changing password: ' . $e->getMessage();
                }
            } else {
                $error_message = 'Current password is incorrect.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Account - BSDO Sale</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary-color: #4e73df; --dark-color: #2e3a59; --light-color: #f8f9fc; --inquiry-color: #6f42c1; --secondary-color: #1cc88a; }
        body { background-color: var(--light-color); font-family: 'Nunito', sans-serif; color: var(--dark-color); }
        .navbar { background-color: white; box-shadow: 0 2px 10px rgba(0,0,0,0.1); transition: all 0.3s ease; }
        .navbar-scrolled { background-color: rgba(255,255,255,0.95) !important; backdrop-filter: blur(10px); }
        .user-avatar { width: 40px; height: 40px; background: var(--primary-color); border-radius: 50%; display:flex; align-items:center; justify-content:center; color:#fff; font-weight:700; }
        .inquiry-badge, .cart-badge { position:absolute; top:-5px; right:-5px; background: var(--inquiry-color); color:#fff; border-radius:50%; width:20px; height:20px; font-size:.7rem; display:flex; align-items:center; justify-content:center; }
        .cart-badge { background: var(--secondary-color); }
        .account-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; border-radius: 14px; padding: 1.75rem; margin: 1.5rem 0; }
        .avatar { width: 80px; height: 80px; border-radius: 50%; background: rgba(255,255,255,.25); display:flex; align-items:center; justify-content:center; font-weight: 700; font-size: 1.75rem; border: 3px solid rgba(255,255,255,.35); }
        .card-shadow { background:#fff; border-radius: 12px; box-shadow: 0 4px 16px rgba(0,0,0,.08); }
        .nav-tabs .nav-link.active { font-weight: 600; }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const forms = document.querySelectorAll('form');
            forms.forEach(function(form){
                form.addEventListener('submit', function(e){
                    const required = this.querySelectorAll('[required]');
                    let ok = true;
                    required.forEach(function(f){ if(!f.value.trim()){ f.classList.add('is-invalid'); ok=false; } else { f.classList.remove('is-invalid'); }});
                    if(!ok){ e.preventDefault(); alert('Please fill in all required fields.'); }
                });
            });
        });
    </script>
    
</head>
<body>
    <!-- Navbar (matches index.php) -->
    <nav class="navbar navbar-expand-lg navbar-light fixed-top">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">
                <i class="fas fa-shopping-bag me-2 text-primary"></i>BSDO SALE
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item"><a class="nav-link" href="index.php#home">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="index.php#products">Products</a></li>
                    <li class="nav-item"><a class="nav-link" href="live_streams.php">Live Streams</a></li>
                    <li class="nav-item"><a class="nav-link" href="products.php?type=regular">Buy</a></li>
                    <li class="nav-item"><a class="nav-link" href="products.php?type=rental">Rent</a></li>
                </ul>
                <div class="d-flex align-items-center">
                    <?php if ($is_logged_in): ?>
                        <div class="dropdown">
                            <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown">
                                <div class="user-avatar me-2"><?php echo strtoupper(substr($user_name, 0, 1)); ?></div>
                                <span class="me-2"><?php echo htmlspecialchars(explode(' ', $user_name)[0]); ?></span>
                                <?php if ($user_role === 'client' && $pending_inquiries_count > 0): ?>
                                    <span class="position-relative me-3">
                                        <i class="fas fa-comments"></i>
                                        <span class="inquiry-badge"><?php echo $pending_inquiries_count; ?></span>
                                    </span>
                                <?php endif; ?>
                                <?php 
                                $total_cart_items = 0;
                                if (isset($_SESSION['cart'])) { $total_cart_items = count($_SESSION['cart']); }
                                if ($total_cart_items > 0): ?>
                                    <span class="position-relative">
                                        <i class="fas fa-shopping-cart"></i>
                                        <span class="cart-badge"><?php echo $total_cart_items; ?></span>
                                    </span>
                                <?php else: ?>
                                    <i class="fas fa-shopping-cart"></i>
                                <?php endif; ?>
                            </a>
                            <ul class="dropdown-menu">
                                <?php if ($user_role === 'seller'): ?>
                                    <li><a class="dropdown-item" href="seller/dashboard.php"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a></li>
                                    <li><a class="dropdown-item" href="seller/live_stream.php"><i class="fas fa-video me-2"></i>Go Live</a></li>
                                <?php elseif ($user_role === 'admin'): ?>
                                    <li><a class="dropdown-item" href="admin/dashboard.php"><i class="fas fa-cog me-2"></i>Admin Panel</a></li>
                                <?php else: ?>
                                    <li><a class="dropdown-item" href="myAccount.php"><i class="fas fa-user me-2"></i>My Account</a></li>
                                    <li><a class="dropdown-item" href="inquiries.php"><i class="fas fa-comments me-2"></i>My Inquiries</a></li>
                                    <li><a class="dropdown-item" href="cart.php"><i class="fas fa-shopping-cart me-2"></i>Cart</a></li>
                                <?php endif; ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <a href="#" class="btn btn-outline-primary me-2" data-bs-toggle="modal" data-bs-target="#loginModal">Login</a>
                        <a href="#" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#registerModal">Register</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <div class="container" style="padding-top: 100px;">
        <div class="account-header card-shadow">
            <div class="d-flex align-items-center">
                <div class="avatar me-3">
                    <?php echo strtoupper(substr($client['first_name'],0,1) . substr($client['last_name'],0,1)); ?>
                </div>
                <div>
                    <h4 class="mb-1">My Account</h4>
                    <div class="small opacity-75"><i class="fas fa-envelope me-2"></i><?php echo htmlspecialchars($client['email']); ?></div>
                </div>
                <div class="ms-auto">
                    <a href="logout.php" class="btn btn-light btn-sm"><i class="fas fa-sign-out-alt me-1"></i>Logout</a>
                </div>
            </div>
        </div>

        <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-1"></i><?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-1"></i><?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card-shadow p-3">
            <ul class="nav nav-tabs" id="accountTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="profile-tab" data-bs-toggle="tab" data-bs-target="#profile" type="button" role="tab"><i class="fas fa-user me-2"></i>Profile</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="password-tab" data-bs-toggle="tab" data-bs-target="#password" type="button" role="tab"><i class="fas fa-lock me-2"></i>Password</button>
                </li>
            </ul>
            <div class="tab-content p-3">
                <div class="tab-pane fade show active" id="profile" role="tabpanel">
                    <form method="POST">
                        <input type="hidden" name="action" value="update_profile">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">First Name *</label>
                                <input type="text" class="form-control" name="first_name" value="<?php echo htmlspecialchars($client['first_name']); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Last Name *</label>
                                <input type="text" class="form-control" name="last_name" value="<?php echo htmlspecialchars($client['last_name']); ?>" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email Address</label>
                                <input type="email" class="form-control" value="<?php echo htmlspecialchars($client['email']); ?>" disabled>
                                <small class="text-muted">Email cannot be changed</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone</label>
                                <input type="tel" class="form-control" name="phone" value="<?php echo htmlspecialchars($client['phone'] ?? ''); ?>">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Update Profile</button>
                    </form>
                </div>
                <div class="tab-pane fade" id="password" role="tabpanel">
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
                        <button type="submit" class="btn btn-primary"><i class="fas fa-key me-2"></i>Change Password</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <footer class="bg-dark text-white py-5 mt-4">
        <div class="container">
            <div class="row">
                <div class="col-lg-4 mb-4">
                    <h5 class="mb-4"><i class="fas fa-shopping-bag me-2"></i>BSDO SALE</h5>
                    <p>Your trusted e-commerce platform with live streaming, real-time inquiries, and rental products.</p>
                </div>
                <div class="col-lg-2 col-md-4 mb-4">
                    <h5 class="mb-4">Quick Links</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="index.php#home" class="text-decoration-none text-light">Home</a></li>
                        <li class="mb-2"><a href="index.php#products" class="text-decoration-none text-light">Products</a></li>
                        <li class="mb-2"><a href="live_streams.php" class="text-decoration-none text-light">Live Streams</a></li>
                    </ul>
                </div>
                <div class="col-lg-3 col-md-4 mb-4">
                    <h5 class="mb-4">Features</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><i class="fas fa-comments me-2 text-primary"></i>Real-time Inquiries</li>
                        <li class="mb-2"><i class="fas fa-video me-2 text-danger"></i>Live Shopping</li>
                        <li class="mb-2"><i class="fas fa-shopping-bag me-2 text-info"></i>Buy Products</li>
                        <li class="mb-2"><i class="fas fa-calendar-alt me-2 text-warning"></i>Rent Products</li>
                    </ul>
                </div>
                <div class="col-lg-3 col-md-4 mb-4">
                    <h5 class="mb-4">Newsletter</h5>
                    <p>Subscribe for updates on new products and live streams</p>
                    <form>
                        <div class="input-group mb-3">
                            <input type="email" class="form-control" placeholder="Your email">
                            <button class="btn btn-primary" type="button">Subscribe</button>
                        </div>
                    </form>
                </div>
            </div>
            <hr class="my-4">
            <div class="text-center">
                <p>&copy; 2024 BSDO Sale. All rights reserved. | Developed by <a href="mailto:Hermogene2001@gmail.com" class="text-decoration-none text-light">HermogenesTech</a></p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar');
            if (window.scrollY > 100) { navbar.classList.add('navbar-scrolled'); } else { navbar.classList.remove('navbar-scrolled'); }
        });
    </script>
</body>
</html>


