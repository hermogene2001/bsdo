<?php
// API endpoint for admin dashboard
require_once __DIR__ . '/../../config.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
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

$admin_id = $_SESSION['user_id'];

try {
    // Get admin stats
    $stats_sql = "
        SELECT 
            (SELECT COUNT(*) FROM users) as total_users,
            (SELECT COUNT(*) FROM users WHERE role = 'seller') as total_sellers,
            (SELECT COUNT(*) FROM users WHERE role = 'client') as total_clients,
            (SELECT COUNT(*) FROM products WHERE status = 'active') as total_products,
            (SELECT COUNT(*) FROM orders WHERE status = 'completed') as total_orders,
            (SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE status = 'completed') as total_revenue,
            (SELECT COUNT(*) FROM inquiries) as total_inquiries,
            (SELECT COUNT(*) FROM live_streams WHERE is_live = 1) as live_streams_now
    ";
    
    $stats_stmt = $pdo->prepare($stats_sql);
    $stats_stmt->execute();
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

    // Get recent activities
    $activities_sql = "
        SELECT 
            'user_registration' as type,
            CONCAT(u.first_name, ' ', u.last_name) as actor,
            u.role as actor_role,
            'New user registered' as action,
            ua.created_at as timestamp
        FROM users u
        JOIN (SELECT created_at, user_id FROM (SELECT created_at, id as user_id FROM users ORDER BY created_at DESC LIMIT 10) t) ua ON u.id = ua.user_id
        
        UNION ALL
        
        SELECT 
            'order_created' as type,
            CONCAT(u.first_name, ' ', u.last_name) as actor,
            u.role as actor_role,
            CONCAT('Order #', o.id, ' created - $', o.total_amount) as action,
            o.created_at as timestamp
        FROM orders o
        JOIN users u ON o.user_id = u.id
        ORDER BY timestamp DESC
        LIMIT 10
    ";
    
    $activities_stmt = $pdo->prepare($activities_sql);
    $activities_stmt->execute();
    $recent_activities = $activities_stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'dashboard_data' => [
            'stats' => $stats,
            'recent_activities' => $recent_activities
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred while fetching dashboard data']);
}
?>