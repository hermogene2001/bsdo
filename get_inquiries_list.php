<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'client') {
    exit('Unauthorized');
}

$user_id = $_SESSION['user_id'];

// Get user's inquiries (same query as main page)
$stmt = $pdo->prepare("
    SELECT i.*, p.name as product_name, p.image_url, u.store_name,
           (SELECT COUNT(*) FROM inquiry_messages WHERE inquiry_id = i.id AND sender_type = 'seller' AND is_read = 0) as unread_count
    FROM inquiries i 
    JOIN products p ON i.product_id = p.id 
    JOIN users u ON i.seller_id = u.id 
    WHERE i.user_id = ? 
    ORDER BY i.updated_at DESC
");
$stmt->execute([$user_id]);
$inquiries = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($inquiries as $inquiry): ?>
    <div class="col-lg-6 mb-4">
        <div class="card inquiry-card <?php echo $inquiry['unread_count'] > 0 ? 'unread' : ''; ?>" 
             data-inquiry-id="<?php echo $inquiry['id']; ?>">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-2">
                        <div class="product-image">
                            <?php if ($inquiry['image_url']): ?>
                                <img src="<?php echo htmlspecialchars($inquiry['image_url']); ?>" 
                                     alt="<?php echo htmlspecialchars($inquiry['product_name']); ?>" 
                                     class="w-100 h-100 rounded">
                            <?php else: ?>
                                <i class="fas fa-box"></i>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-8">
                        <h6 class="card-title mb-1"><?php echo htmlspecialchars($inquiry['product_name']); ?></h6>
                        <p class="text-muted small mb-1">
                            <i class="fas fa-store me-1"></i>
                            <?php echo htmlspecialchars($inquiry['store_name']); ?>
                        </p>
                        <div class="d-flex align-items-center mb-2">
                            <span class="status-badge bg-<?php 
                                echo $inquiry['status'] === 'replied' ? 'success' : 
                                     ($inquiry['status'] === 'resolved' ? 'info' : 'warning'); 
                            ?> me-2">
                                <?php echo ucfirst($inquiry['status']); ?>
                            </span>
                            <?php if ($inquiry['unread_count'] > 0): ?>
                                <span class="unread-badge me-2" id="unreadBadge_<?php echo $inquiry['id']; ?>">
                                    <?php echo $inquiry['unread_count']; ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <p class="last-message mb-0">
                            <?php echo htmlspecialchars($inquiry['message']); ?>
                        </p>
                    </div>
                    <div class="col-2 text-end">
                        <button class="btn btn-primary btn-sm open-chat" 
                                data-inquiry-id="<?php echo $inquiry['id']; ?>"
                                data-product-name="<?php echo htmlspecialchars($inquiry['product_name']); ?>"
                                data-seller-name="<?php echo htmlspecialchars($inquiry['store_name']); ?>">
                            <i class="fas fa-comment-dots"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>