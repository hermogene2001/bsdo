<?php
// API endpoint for user profile management
require_once __DIR__ . '/../../config.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'PUT') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Check if user is authenticated
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get user profile
    try {
        $stmt = $pdo->prepare("SELECT id, first_name, last_name, email, role, store_name, business_type, created_at, updated_at FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'User not found']);
            exit;
        }

        echo json_encode([
            'success' => true,
            'user' => $user
        ]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'An error occurred while fetching profile']);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    // Update user profile
    $input = json_decode(file_get_contents('php://input'), true);

    // Validate input
    $first_name = trim($input['first_name'] ?? '');
    $last_name = trim($input['last_name'] ?? '');
    $email = filter_var($input['email'] ?? '', FILTER_SANITIZE_EMAIL);

    if (empty($first_name) || empty($last_name) || empty($email)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'First name, last name, and email are required']);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid email format']);
        exit;
    }

    try {
        // Check if email already exists for another user
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $user_id]);
        if ($stmt->rowCount() > 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Email already exists']);
            exit;
        }

        // Update user profile
        $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, updated_at = NOW() WHERE id = ?");
        $result = $stmt->execute([$first_name, $last_name, $email, $user_id]);

        if ($result) {
            // Update session data
            $_SESSION['user_name'] = $first_name . ' ' . $last_name;
            $_SESSION['user_email'] = $email;

            echo json_encode([
                'success' => true,
                'message' => 'Profile updated successfully',
                'user' => [
                    'id' => $user_id,
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    'email' => $email
                ]
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to update profile']);
        }

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'An error occurred while updating profile']);
    }
}
?>