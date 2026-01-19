<?php
// API endpoint for listing live streams
require_once __DIR__ . '/../../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get query parameters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 12;
$category = $_GET['category'] ?? '';
$is_live = $_GET['is_live'] ?? null;
$status = $_GET['status'] ?? 'live'; // Default to showing live streams

try {
    // Build query
    $sql = "SELECT ls.*, u.store_name, c.name as category_name FROM live_streams ls 
            JOIN users u ON ls.seller_id = u.id 
            LEFT JOIN categories c ON ls.category_id = c.id 
            WHERE 1=1";
    
    $params = [];

    if ($is_live !== null) {
        $sql .= " AND ls.is_live = :is_live";
        $params[':is_live'] = $is_live ? 1 : 0;
    } else {
        // By default, show only live streams
        $sql .= " AND ls.is_live = 1";
    }
    
    if (!empty($category)) {
        $sql .= " AND c.name = :category";
        $params[':category'] = $category;
    }
    
    if (!empty($status)) {
        $sql .= " AND ls.status = :status";
        $params[':status'] = $status;
    }

    $sql .= " ORDER BY ls.started_at DESC";

    // Add pagination
    $offset = ($page - 1) * $limit;
    $sql .= " LIMIT :limit OFFSET :offset";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    // Bind other parameters
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    
    $stmt->execute();
    $streams = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get total count for pagination
    $countSql = "SELECT COUNT(*) as total FROM live_streams ls 
                 JOIN users u ON ls.seller_id = u.id 
                 LEFT JOIN categories c ON ls.category_id = c.id 
                 WHERE 1=1";
                 
    if ($is_live !== null) {
        $countSql .= " AND ls.is_live = :is_live";
    } else {
        $countSql .= " AND ls.is_live = 1";
    }
    
    if (!empty($category)) {
        $countSql .= " AND c.name = :category";
    }
    
    if (!empty($status)) {
        $countSql .= " AND ls.status = :status";
    }
    
    $countStmt = $pdo->prepare($countSql);
    
    if ($is_live !== null) {
        $countStmt->bindValue(':is_live', $is_live ? 1 : 0);
    }
    
    if (!empty($category)) {
        $countStmt->bindValue(':category', $category);
    }
    
    if (!empty($status)) {
        $countStmt->bindValue(':status', $status);
    }
    
    $countStmt->execute();
    $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Format the streams data
    $formatted_streams = [];
    foreach ($streams as $stream) {
        $formatted_streams[] = [
            'id' => $stream['id'],
            'title' => $stream['title'],
            'description' => $stream['description'],
            'seller_store_name' => $stream['store_name'],
            'category_name' => $stream['category_name'],
            'thumbnail_url' => $stream['thumbnail_url'],
            'is_live' => (bool)$stream['is_live'],
            'viewer_count' => $stream['viewer_count'],
            'max_viewers' => $stream['max_viewers'],
            'started_at' => $stream['started_at'],
            'scheduled_at' => $stream['scheduled_at'],
            'status' => $stream['status'],
            'streaming_method' => $stream['streaming_method'],
            'created_at' => $stream['created_at'],
            'updated_at' => $stream['updated_at']
        ];
    }

    // Calculate pagination info
    $total_pages = ceil($total / $limit);
    
    echo json_encode([
        'success' => true,
        'streams' => $formatted_streams,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $total_pages,
            'total_items' => $total,
            'per_page' => $limit
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred while fetching live streams']);
}
?>