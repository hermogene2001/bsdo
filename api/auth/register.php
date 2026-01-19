<?php
// API endpoint for user registration
require_once __DIR__ . '/../../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

// Validate required fields
if (empty($input['first_name']) || empty($input['last_name']) || empty($input['email']) || empty($input['password'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Please fill all required fields']);
    exit;
}

// Validate email format
if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid email format']);
    exit;
}

// Validate password
if (empty($input['password']) || empty($input['confirm_password'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Password and confirm password are required']);
    exit;
}

if ($input['password'] !== $input['confirm_password']) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Passwords do not match']);
    exit;
}

// Validate password strength
if (strlen($input['password']) < 8 || 
    !preg_match('/[A-Z]/', $input['password']) || 
    !preg_match('/[a-z]/', $input['password']) || 
    !preg_match('/[0-9]/', $input['password'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters long and contain uppercase, lowercase letters and numbers']);
    exit;
}

$role = $input['role'] ?? 'client';
$store_name = $input['store_name'] ?? '';
$business_type = $input['business_type'] ?? '';
$referral_code = $input['referral_code'] ?? '';

// Role-specific validation
if ($role === 'seller') {
    if (empty($store_name) || empty($business_type)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Please fill all seller-specific fields']);
        exit;
    }
}

try {
    // Check if email already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$input['email']]);
    if ($stmt->rowCount() > 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Email already registered']);
        exit;
    }

    // Hash password
    $hashed_password = password_hash($input['password'], PASSWORD_DEFAULT);

    // Determine status based on role
    $status = ($role === 'client') ? 'active' : 'active';

    // Insert user into database
    $stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, email, password, role, store_name, business_type, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    if ($stmt->execute([
        $input['first_name'],
        $input['last_name'], 
        $input['email'], 
        $hashed_password, 
        $role, 
        $store_name, 
        $business_type, 
        $status
    ])) {
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

                    // Regardless of role, inviter always gets $0.20
                    $reward_inviter = 0.20;
                    // Invitee gets no reward
                    $reward_invitee = 0.00;

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

        // Return success response
        echo json_encode([
            'success' => true,
            'message' => 'Registration successful',
            'user' => [
                'id' => $user_id,
                'first_name' => $input['first_name'],
                'last_name' => $input['last_name'],
                'email' => $input['email'],
                'role' => $role,
                'store_name' => $store_name,
                'business_type' => $business_type
            ]
        ]);

    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Registration failed. Please try again.']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred during registration']);
}
?>