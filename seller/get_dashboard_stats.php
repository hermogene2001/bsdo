<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'seller') {
    echo json_encode(['success' => false]);
    exit;
}

$seller_id = $_SESSION['user_id'];

// Get real-time stats
$pending_orders_stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT o.id) as pending_orders
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    JOIN products p ON oi.product_id = p.id
    WHERE p.seller_id = ? AND o.status = 'pending'
");
$pending_orders_stmt->execute([$seller_id]);
$pending_orders = $pending_orders_stmt->fetch(PDO::FETCH_ASSOC)['pending_orders'];

$pending_inquiries_stmt = $pdo->prepare("
    SELECT COUNT(*) as pending_inquiries 
    FROM inquiries i 
    JOIN products p ON i.product_id = p.id 
    WHERE p.seller_id = ? AND i.status = 'pending'
");
$pending_inquiries_stmt->execute([$seller_id]);
$pending_inquiries = $pending_inquiries_stmt->fetch(PDO::FETCH_ASSOC)['pending_inquiries'];

echo json_encode([
    'success' => true,
    'pending_orders' => $pending_orders,
    'pending_inquiries' => $pending_inquiries
]);
?>