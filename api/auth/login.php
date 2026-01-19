<?php
// API endpoint for user login
require_once __DIR__ . '/../../config.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (empty($input['email']) || empty($input['password'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Email and password are required']);
    exit;
}

$email = filter_var($input['email'], FILTER_SANITIZE_EMAIL);
$password = $input['password'];
$role = $input['role'] ?? 'client'; // Default to client if not specified

try {
    // Get user from database with role check
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND role = ?");
    $stmt->execute([$email, $role]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        // Role-specific verification
        $verification_passed = true;
        $error_message = '';

        if ($role === 'seller') {
            $seller_code = $input['seller_code'] ?? '';
            // Verify seller code
            $stmt = $pdo->prepare("SELECT * FROM seller_codes WHERE seller_id = ? AND seller_code = ?");
            $stmt->execute([$user['id'], $seller_code]);
            if ($stmt->rowCount() === 0) {
                $verification_passed = false;
                $error_message = 'Invalid seller code.';
            }
        } elseif ($role === 'admin') {
            $admin_key = $input['admin_key'] ?? '';
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
            $stmt->execute([$email, $_SERVER['REMOTE_ADDR']]);

            http_response_code(401);
            echo json_encode(['success' => false, 'message' => $error_message]);
            exit;
        }

        // Check if account is active
        if ($user['status'] !== 'active') {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Your account is not active. Please contact support.']);
            exit;
        }

        // Successful login - create session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
        $_SESSION['user_role'] = $user['role'];

        // Record successful attempt
        $stmt = $pdo->prepare("INSERT INTO login_attempts (email, ip_address, success) VALUES (?, ?, 1)");
        $stmt->execute([$email, $_SERVER['REMOTE_ADDR']]);

        // Log activity based on role
        if ($role === 'seller') {
            $stmt = $pdo->prepare("INSERT INTO seller_activities (seller_id, activity, ip_address) VALUES (?, ?, ?)");
            $stmt->execute([$user['id'], "Logged in via API", $_SERVER['REMOTE_ADDR']]);
        } elseif ($role === 'admin') {
            $stmt = $pdo->prepare("INSERT INTO admin_activities (admin_id, activity, ip_address) VALUES (?, ?, ?)");
            $stmt->execute([$user['id'], "Logged in via API", $_SERVER['REMOTE_ADDR']]);
        }

        // Return success response with user data
        echo json_encode([
            'success' => true,
            'message' => 'Login successful',
            'user' => [
                'id' => $user['id'],
                'first_name' => $user['first_name'],
                'last_name' => $user['last_name'],
                'email' => $user['email'],
                'role' => $user['role'],
                'store_name' => $user['store_name'] ?? null,
                'business_type' => $user['business_type'] ?? null
            ],
            'session' => session_id()
        ]);

    } else {
        // Failed login
        $stmt = $pdo->prepare("INSERT INTO login_attempts (email, ip_address, success) VALUES (?, ?, 0)");
        $stmt->execute([$email, $_SERVER['REMOTE_ADDR']]);

        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid email or password for the selected role']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred during login']);
}
?>