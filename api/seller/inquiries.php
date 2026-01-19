<?php
// API endpoint for seller inquiry management
require_once __DIR__ . '/../../config.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
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
    // Get seller's inquiries
    try {
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 12;
        $status = $_GET['status'] ?? 'all'; // 'pending', 'read', 'answered', 'all'
        
        $sql = "SELECT i.*, u.first_name, u.last_name, u.email, p.name as product_name FROM inquiries i 
                JOIN users u ON i.user_id = u.id 
                JOIN products p ON i.product_id = p.id 
                WHERE i.seller_id = ?";
        $params = [$seller_id];
        
        if ($status !== 'all') {
            $sql .= " AND i.status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY i.updated_at DESC";
        
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
        $inquiries = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Count total inquiries
        $count_sql = "SELECT COUNT(*) as total FROM inquiries i WHERE i.seller_id = ?";
        $count_params = [$seller_id];
        
        if ($status !== 'all') {
            $count_sql .= " AND i.status = ?";
            $count_params[] = $status;
        }
        
        $count_stmt = $pdo->prepare($count_sql);
        $count_stmt->execute($count_params);
        $total = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        $total_pages = ceil($total / $limit);
        
        // Get messages for each inquiry
        $formatted_inquiries = [];
        foreach ($inquiries as $inquiry) {
            $msg_stmt = $pdo->prepare("
                SELECT im.*, u.first_name, u.last_name 
                FROM inquiry_messages im 
                LEFT JOIN users u ON im.sender_id = u.id 
                WHERE im.inquiry_id = ? 
                ORDER BY im.created_at ASC
            ");
            $msg_stmt->execute([$inquiry['id']]);
            $messages = $msg_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $inquiry['messages'] = $messages;
            $formatted_inquiries[] = $inquiry;
        }
        
        echo json_encode([
            'success' => true,
            'inquiries' => $formatted_inquiries,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => $total_pages,
                'total_items' => $total,
                'per_page' => $limit
            ]
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'An error occurred while fetching inquiries']);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Reply to an inquiry
    $input = json_decode(file_get_contents('php://input'), true);
    
    $inquiry_id = intval($input['inquiry_id'] ?? 0);
    $message = trim($input['message'] ?? '');
    
    if ($inquiry_id <= 0 || empty($message)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Inquiry ID and message are required']);
        exit;
    }
    
    // Verify inquiry belongs to seller
    $verify_stmt = $pdo->prepare("SELECT id FROM inquiries WHERE id = ? AND seller_id = ?");
    $verify_stmt->execute([$inquiry_id, $seller_id]);
    if ($verify_stmt->rowCount() === 0) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Unauthorized - you do not own this inquiry']);
        exit;
    }
    
    try {
        // Add reply message
        $stmt = $pdo->prepare("
            INSERT INTO inquiry_messages (inquiry_id, sender_id, sender_type, message) 
            VALUES (?, ?, 'seller', ?)
        ");
        $result = $stmt->execute([$inquiry_id, $seller_id, $message]);
        
        if ($result) {
            // Update inquiry status to answered
            $update_stmt = $pdo->prepare("UPDATE inquiries SET status = 'answered', updated_at = NOW() WHERE id = ?");
            $update_stmt->execute([$inquiry_id]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Reply sent successfully'
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to send reply']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'An error occurred while sending reply']);
    }
}
?>