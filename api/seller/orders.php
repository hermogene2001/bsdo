<?php
// API endpoint for seller order management
require_once __DIR__ . '/../../config.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'PUT') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Check if user is authenticated as a seller
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'seller') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized - sellers only']);
    exit;
}

$seller_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get seller's orders
    try {
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 12;
        $status = $_GET['status'] ?? 'all'; // 'pending', 'processing', 'shipped', 'delivered', 'completed', 'cancelled', 'all'
        
        $sql = "SELECT o.*, u.first_name, u.last_name, u.email, u.phone, p.name as product_name FROM orders o 
                JOIN users u ON o.user_id = u.id 
                JOIN products p ON o.product_id = p.id 
                WHERE o.seller_id = ?";
        $params = [$seller_id];
        
        if ($status !== 'all') {
            $sql .= " AND o.status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY o.created_at DESC";
        
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
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Count total orders
        $count_sql = "SELECT COUNT(*) as total FROM orders o WHERE o.seller_id = ?";
        $count_params = [$seller_id];
        
        if ($status !== 'all') {
            $count_sql .= " AND o.status = ?";
            $count_params[] = $status;
        }
        
        $count_stmt = $pdo->prepare($count_sql);
        $count_stmt->execute($count_params);
        $total = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        $total_pages = ceil($total / $limit);
        
        echo json_encode([
            'success' => true,
            'orders' => $orders,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => $total_pages,
                'total_items' => $total,
                'per_page' => $limit
            ]
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'An error occurred while fetching orders']);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    // Update order status
    $input = json_decode(file_get_contents('php://input'), true);
    
    $order_id = intval($input['order_id'] ?? 0);
    $new_status = $input['status'] ?? '';
    
    if ($order_id <= 0 || empty($new_status)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Order ID and status are required']);
        exit;
    }
    
    // Validate status
    $valid_statuses = ['pending', 'processing', 'shipped', 'delivered', 'completed', 'cancelled'];
    if (!in_array($new_status, $valid_statuses)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid status']);
        exit;
    }
    
    // Verify order belongs to seller
    $verify_stmt = $pdo->prepare("SELECT id FROM orders WHERE id = ? AND seller_id = ?");
    $verify_stmt->execute([$order_id, $seller_id]);
    if ($verify_stmt->rowCount() === 0) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Unauthorized - you do not own this order']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?");
        $result = $stmt->execute([$new_status, $order_id]);
        
        if ($result) {
            echo json_encode([
                'success' => true,
                'message' => 'Order status updated successfully'
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to update order status']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'An error occurred while updating order status']);
    }
}
?>