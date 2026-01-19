<?php
// API endpoint for admin user management
require_once __DIR__ . '/../../config.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'PUT' && $_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Check if user is authenticated as an admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized - admins only']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get users list
    try {
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 12;
        $role = $_GET['role'] ?? 'all'; // 'all', 'client', 'seller', 'admin'
        $status = $_GET['status'] ?? 'all'; // 'all', 'active', 'inactive'
        
        $sql = "SELECT id, first_name, last_name, email, role, status, created_at FROM users WHERE 1=1";
        $params = [];
        
        if ($role !== 'all') {
            $sql .= " AND role = ?";
            $params[] = $role;
        }
        
        if ($status !== 'all') {
            $sql .= " AND status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY created_at DESC";
        
        $offset = ($page - 1) * $limit;
        $sql .= " LIMIT :limit OFFSET :offset";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        
        // Bind other params
        for ($i = 0; $i < count($params); $i++) {
            $stmt->bindValue($i + 1, $params[$i]);
        }
        
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Count total users
        $count_sql = "SELECT COUNT(*) as total FROM users WHERE 1=1";
        $count_params = [];
        
        if ($role !== 'all') {
            $count_sql .= " AND role = ?";
            $count_params[] = $role;
        }
        
        if ($status !== 'all') {
            $count_sql .= " AND status = ?";
            $count_params[] = $status;
        }
        
        $count_stmt = $pdo->prepare($count_sql);
        $count_stmt->execute($count_params);
        $total = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        $total_pages = ceil($total / $limit);
        
        echo json_encode([
            'success' => true,
            'users' => $users,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => $total_pages,
                'total_items' => $total,
                'per_page' => $limit
            ]
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'An error occurred while fetching users']);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    // Update user status
    $input = json_decode(file_get_contents('php://input'), true);
    
    $user_id = intval($input['user_id'] ?? 0);
    $new_status = $input['status'] ?? '';
    
    if ($user_id <= 0 || empty($new_status)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'User ID and status are required']);
        exit;
    }
    
    // Validate status
    $valid_statuses = ['active', 'inactive', 'suspended'];
    if (!in_array($new_status, $valid_statuses)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid status']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE users SET status = ?, updated_at = NOW() WHERE id = ?");
        $result = $stmt->execute([$new_status, $user_id]);
        
        if ($result) {
            echo json_encode([
                'success' => true,
                'message' => 'User status updated successfully'
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to update user status']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'An error occurred while updating user status']);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    // Delete user
    $user_id = intval($_GET['id'] ?? 0);
    if ($user_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'User ID is required']);
        exit;
    }
    
    // Prevent admin from deleting themselves
    if ($user_id == $_SESSION['user_id']) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'You cannot delete your own account']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'");
        $result = $stmt->execute([$user_id]);
        
        if ($result && $stmt->rowCount() > 0) {
            echo json_encode([
                'success' => true,
                'message' => 'User deleted successfully'
            ]);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'User not found or is an admin']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'An error occurred while deleting user']);
    }
}
?>