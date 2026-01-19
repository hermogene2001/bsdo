<?php
// API endpoint for seller dashboard
require_once __DIR__ . '/../../config.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
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

try {
    // Get seller stats
    $stats_sql = "
        SELECT 
            (SELECT COUNT(*) FROM products WHERE seller_id = ? AND status = 'active') as active_products,
            (SELECT COUNT(*) FROM orders WHERE seller_id = ? AND status = 'completed') as completed_orders,
            (SELECT COUNT(*) FROM orders WHERE seller_id = ? AND status = 'pending') as pending_orders,
            (SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE seller_id = ? AND status = 'completed') as total_revenue,
            (SELECT COUNT(*) FROM inquiries WHERE seller_id = ?) as total_inquiries
    ";
    
    $stats_stmt = $pdo->prepare($stats_sql);
    $stats_stmt->execute([$seller_id, $seller_id, $seller_id, $seller_id, $seller_id]);
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

    // Get recent orders
    $orders_sql = "
        SELECT o.*, u.first_name, u.last_name, u.email
        FROM orders o
        JOIN users u ON o.user_id = u.id
        WHERE o.seller_id = ?
        ORDER BY o.created_at DESC
        LIMIT 10
    ";
    
    $orders_stmt = $pdo->prepare($orders_sql);
    $orders_stmt->execute([$seller_id]);
    $recent_orders = $orders_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get recent inquiries
    $inquiries_sql = "
        SELECT i.*, u.first_name, u.last_name, u.email, p.name as product_name
        FROM inquiries i
        JOIN users u ON i.user_id = u.id
        JOIN products p ON i.product_id = p.id
        WHERE i.seller_id = ?
        ORDER BY i.updated_at DESC
        LIMIT 10
    ";
    
    $inquiries_stmt = $pdo->prepare($inquiries_sql);
    $inquiries_stmt->execute([$seller_id]);
    $recent_inquiries = $inquiries_stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'dashboard_data' => [
            'stats' => $stats,
            'recent_orders' => $recent_orders,
            'recent_inquiries' => $recent_inquiries
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred while fetching dashboard data']);
}
?>