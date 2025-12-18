<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = $_POST['role'];
    $store_name = isset($_POST['store_name']) ? trim($_POST['store_name']) : '';
    $business_type = isset($_POST['business_type']) ? trim($_POST['business_type']) : '';
    $referral_code = isset($_POST['referral_code']) ? trim($_POST['referral_code']) : '';
    
    // Validate inputs
    if (empty($first_name) || empty($last_name) || empty($email) || empty($password)) {
        die('Please fill all required fields.');
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        die('Invalid email format.');
    }
    
    if ($password !== $confirm_password) {
        die('Passwords do not match.');
    }
    
    if (strlen($password) < 8 || !preg_match('/[A-Z]/', $password) || 
        !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        die('Password must be at least 8 characters long and contain uppercase, lowercase letters and numbers.');
    }
    
    // Role-specific validation
    if ($role === 'seller') {
        if (empty($store_name) || empty($business_type)) {
            die('Please fill all seller-specific fields.');
        }
    }
    
    // Check if email already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->rowCount() > 0) {
        die('Email already registered.');
    }
    
    // Hash password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Determine status based on role
    $status = ($role === 'client') ? 'active' : 'pending';
    
    // Insert user into database
    $stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, email, password, role, store_name, business_type, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    if ($stmt->execute([$first_name, $last_name, $email, $hashed_password, $role, $store_name, $business_type, $status])) {
        $user_id = $pdo->lastInsertId();
        
        // If seller, generate seller code
        $seller_code = '';
        if ($role === 'seller') {
            $seller_code = 'SELLER' . str_pad($user_id, 6, '0', STR_PAD_LEFT);
            $stmt = $pdo->prepare("INSERT INTO seller_codes (seller_id, seller_code) VALUES (?, ?)");
            $stmt->execute([$user_id, $seller_code]);
        }

        // Referral handling and wallet credits
        try {
            if (!empty($referral_code)) {
                // Find inviter seller by code
                $invStmt = $pdo->prepare("SELECT seller_id FROM seller_codes WHERE seller_code = ? LIMIT 1");
                $invStmt->execute([$referral_code]);
                $invRow = $invStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($invRow && isset($invRow['seller_id'])) {
                    $inviter_id = (int)$invRow['seller_id'];
                    $reward_inviter = 0.00;
                    $reward_invitee = 0.00;

                    if ($role === 'seller') {
                        // Seller invited a seller -> $0.20 immediate reward
                        $reward_inviter = 0.20;
                    } elseif ($role === 'client') {
                        // Seller invited a client -> $0.50 to client (invitee)
                        $reward_invitee = 0.50;
                    }

                    if ($reward_inviter > 0) {
                        $pdo->prepare("INSERT INTO user_wallets (user_id, balance) VALUES (?, ?) ON DUPLICATE KEY UPDATE balance = balance + VALUES(balance)")
                            ->execute([$inviter_id, $reward_inviter]);
                    }
                    if ($reward_invitee > 0) {
                        $pdo->prepare("INSERT INTO user_wallets (user_id, balance) VALUES (?, ?) ON DUPLICATE KEY UPDATE balance = balance + VALUES(balance)")
                            ->execute([$user_id, $reward_invitee]);
                    }

                    $pdo->prepare("INSERT INTO referrals (inviter_id, invitee_id, invitee_role, referral_code, reward_to_inviter, reward_to_invitee) VALUES (?, ?, ?, ?, ?, ?)")
                        ->execute([$inviter_id, $user_id, $role, $referral_code, $reward_inviter, $reward_invitee]);
                }
            }
        } catch (Exception $e) {
            error_log('Referral error: ' . $e->getMessage());
        }
        
        // Set cookie for seller code if this is a seller registration
        if ($role === 'seller' && !empty($seller_code)) {
            // Set cookie to expire in 30 days
            setcookie('seller_code', $seller_code, time() + (30 * 24 * 60 * 60), '/', '', false, true);
        }
        
        // Redirect to login page with success message
        header('Location: index.php?registration=success&role=' . $role . (!empty($seller_code) ? '&seller_code=' . urlencode($seller_code) : ''));
        exit();
    } else {
        die('Registration failed. Please try again.');
    }
}
?>