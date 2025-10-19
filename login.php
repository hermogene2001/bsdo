<?php
require_once 'config.php';
session_start();

// If already logged in, redirect to appropriate dashboard
if (isset($_SESSION['user_id'])) {
    redirectToDashboard();
}

// Brute force protection - check attempts in last 30 minutes
$max_attempts = 5;
$lockout_time = 30 * 60; // 30 minutes in seconds

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if role is set, otherwise default to 'client'
    $role = isset($_POST['role']) ? $_POST['role'] : 'client';
    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];
    $seller_code = isset($_POST['seller_code']) ? trim($_POST['seller_code']) : '';
    $admin_key = isset($_POST['admin_key']) ? trim($_POST['admin_key']) : '';
    $ip_address = $_SERVER['REMOTE_ADDR'];
    
    // Validate required fields
    if (empty($email) || empty($password)) {
        $_SESSION['login_error'] = 'Please fill all required fields.';
        header('Location: index.php#loginModal');
        exit();
    }
    
    // Check if account is temporarily locked
    $stmt = $pdo->prepare("SELECT COUNT(*) as attempts FROM login_attempts 
                          WHERE email = ? AND ip_address = ? AND attempt_time > DATE_SUB(NOW(), INTERVAL 30 MINUTE) AND success = 0");
    $stmt->execute([$email, $ip_address]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['attempts'] >= $max_attempts) {
        $_SESSION['login_error'] = 'Too many failed login attempts. Please try again in 30 minutes.';
        header('Location: index.php#loginModal');
        exit();
    }
    
    // Get user from database with role check
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND role = ?");
    $stmt->execute([$email, $role]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && password_verify($password, $user['password'])) {
        // Role-specific verification
        $verification_passed = true;
        $error_message = '';
        
        if ($role === 'seller') {
            // Verify seller code
            $stmt = $pdo->prepare("SELECT * FROM seller_codes WHERE seller_id = ? AND seller_code = ?");
            $stmt->execute([$user['id'], $seller_code]);
            if ($stmt->rowCount() === 0) {
                $verification_passed = false;
                $error_message = 'Invalid seller code.';
            }
        } elseif ($role === 'admin') {
            // Verify admin key
            $stmt = $pdo->prepare("SELECT * FROM admin_keys WHERE admin_id = ? AND security_key = ?");
            $stmt->execute([$user['id'], $admin_key]);
            if ($stmt->rowCount() === 0) {
                $verification_passed = false;
                $error_message = 'Invalid admin security key.';
            }
        }
        
        if (!$verification_passed) {
            // Record failed attempt
            $stmt = $pdo->prepare("INSERT INTO login_attempts (email, ip_address, success) VALUES (?, ?, 0)");
            $stmt->execute([$email, $ip_address]);
            
            $_SESSION['login_error'] = $error_message;
            $_SESSION['login_form_data'] = [
                'role' => $role,
                'email' => $email,
                'seller_code' => $seller_code,
                'admin_key' => $admin_key
            ];
            header('Location: index.php#loginModal');
            exit();
        }
        
        // Check if account is active
        if ($user['status'] !== 'active') {
            $_SESSION['login_error'] = 'Your account is not active. Please contact support.';
            header('Location: index.php#loginModal');
            exit();
        }
        
        // Successful login
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
        $_SESSION['user_role'] = $user['role'];
        
        // Record successful attempt
        $stmt = $pdo->prepare("INSERT INTO login_attempts (email, ip_address, success) VALUES (?, ?, 1)");
        $stmt->execute([$email, $ip_address]);
        
        // Log activity based on role
        logLoginActivity($user['id'], $user['role'], $ip_address);
        
        // Redirect to appropriate dashboard
        redirectToDashboard();
        
    } else {
        // Failed login
        $stmt = $pdo->prepare("INSERT INTO login_attempts (email, ip_address, success) VALUES (?, ?, 0)");
        $stmt->execute([$email, $ip_address]);
        
        $_SESSION['login_error'] = 'Invalid email or password for the selected role.';
        $_SESSION['login_form_data'] = [
            'role' => $role,
            'email' => $email
        ];
        header('Location: index.php#loginModal');
        exit();
    }
} else {
    // If not POST request, redirect to home
    header('Location: index.php');
    exit();
}

function redirectToDashboard() {
    if (isset($_SESSION['user_role'])) {
        switch ($_SESSION['user_role']) {
            case 'admin':
                header('Location: admin/dashboard.php');
                break;
            case 'seller':
                header('Location: seller/dashboard.php');
                break;
            case 'client':
            default:
                header('Location: index.php'); // Clients stay on main site
                break;
        }
        exit();
    }
}

function logLoginActivity($user_id, $role, $ip_address) {
    global $pdo;
    
    if ($role === 'seller') {
        $stmt = $pdo->prepare("INSERT INTO seller_activities (seller_id, activity, ip_address) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, "Logged in from landing page", $ip_address]);
    } elseif ($role === 'admin') {
        $stmt = $pdo->prepare("INSERT INTO admin_activities (admin_id, activity, ip_address) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, "Logged in from admin panel", $ip_address]);
    }
}
?>