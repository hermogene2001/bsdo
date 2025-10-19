<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'seller') {
    header('Location: ../login.php');
    exit;
}

$seller_id = $_SESSION['user_id'];

// List inquiries for products owned by this seller
$stmt = $pdo->prepare("\n    SELECT i.*, p.name as product_name, p.image_url, \n           u.first_name AS customer_first_name, u.last_name AS customer_last_name,\n           (SELECT COUNT(*) FROM inquiry_messages im WHERE im.inquiry_id = i.id AND im.sender_type = 'user' AND im.is_read = 0) as unread_count,\n           (SELECT MAX(created_at) FROM inquiry_messages WHERE inquiry_id = i.id) as last_message_time\n    FROM inquiries i\n    JOIN products p ON i.product_id = p.id\n    JOIN users u ON i.user_id = u.id\n    WHERE p.seller_id = ?\n    ORDER BY i.updated_at DESC\n");
$stmt->execute([$seller_id]);
$inquiries = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Inquiries - Seller</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .inquiry-card { cursor: pointer; border: 1px solid #e3e6f0; }
        .inquiry-card.unread { border-color: #0d6efd; box-shadow: 0 0 0 .2rem rgba(13,110,253,.15); }
        .product-image { width: 60px; height: 60px; object-fit: cover; border-radius: 6px; background: #f8f9fc; display: flex; align-items: center; justify-content: center; }
        .unread-badge { background: #dc3545; color: #fff; border-radius: 10px; padding: 2px 8px; font-size: 12px; }
        .chat-messages { max-height: 380px; overflow-y: auto; }

        /* Separate incoming (user) vs outgoing (seller) */
        .message { display: flex; margin-bottom: 10px; }
        .message.user { justify-content: flex-start; }
        .message.seller { justify-content: flex-end; }
        .message-content { max-width: 75%; padding: 8px 12px; border-radius: 10px; font-size: 14px; }
        .message.user .message-content { background: #f1f5ff; color: #0b2e4e; border-top-left-radius: 4px; }
        .message.seller .message-content { background: #e9f7ef; color: #0f5132; border-top-right-radius: 4px; }
        .message-time { font-size: 11px; color: #6c757d; margin-top: 4px; }
        .message.user .message-time { text-align: left; }
        .message.seller .message-time { text-align: right; }
    </style>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php"><i class="fas fa-store me-2"></i>BSDO Seller</a>
            <div class="d-flex">
                <a href="dashboard.php" class="btn btn-outline-light btn-sm">Back to Dashboard</a>
            </div>
        </div>
    </nav>

    <div class="container mt-5 pt-5">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="mb-0"><i class="fas fa-comments me-2 text-primary"></i>Customer Inquiries</h2>
                    <span class="badge bg-primary" id="totalUnread">0 unread</span>
                </div>

                <?php if (!empty($inquiries)): ?>
                    <div class="row" id="inquiriesContainer">
                        <?php foreach ($inquiries as $inquiry): ?>
                            <div class="col-lg-6 mb-4">
                                <div class="card inquiry-card <?php echo $inquiry['unread_count'] > 0 ? 'unread' : ''; ?>">
                                    <div class="card-body">
                                        <div class="row g-3 align-items-center">
                                            <div class="col-2">
                                                <div class="product-image">
                                                    <?php if ($inquiry['image_url']): ?>
                                                        <img src="<?php echo htmlspecialchars($inquiry['image_url']); ?>" alt="<?php echo htmlspecialchars($inquiry['product_name']); ?>" class="w-100 h-100 rounded">
                                                    <?php else: ?>
                                                        <i class="fas fa-box"></i>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="col-7">
                                                <h6 class="card-title mb-1"><?php echo htmlspecialchars($inquiry['product_name']); ?></h6>
                                                <div>
                                                    <span class="badge bg-<?php 
                                                        echo $inquiry['status'] === 'replied' ? 'success' : 
                                                             ($inquiry['status'] === 'resolved' ? 'info' : 'warning'); 
                                                    ?> me-2">
                                                        <?php echo ucfirst($inquiry['status']); ?>
                                                    </span>
                                                    <?php if ($inquiry['unread_count'] > 0): ?>
                                                        <span class="unread-badge me-2" id="unreadBadge_<?php echo $inquiry['id']; ?>"><?php echo $inquiry['unread_count']; ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <small class="text-muted">Updated: <?php echo date('M j, g:i A', strtotime($inquiry['updated_at'])); ?></small>
                                            </div>
                                            <div class="col-3 text-end">
                                                <button class="btn btn-sm btn-primary open-chat" data-inquiry-id="<?php echo $inquiry['id']; ?>" data-product-name="<?php echo htmlspecialchars($inquiry['product_name']); ?>" data-customer-name="<?php echo htmlspecialchars(trim(($inquiry['customer_first_name'] ?? '') . ' ' . ($inquiry['customer_last_name'] ?? ''))); ?>">
                                                    Open Chat
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">No inquiries yet.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Chat Modal -->
    <div class="modal fade" id="chatModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div class="d-flex align-items-center w-100">
                        <h5 class="modal-title mb-0"><i class="fas fa-comments me-2"></i><span id="chatTitle">Chat</span></h5>
                        <small class="ms-2 text-muted" id="chatCustomerName"></small>
                        <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal"></button>
                    </div>
                </div>
                <div class="modal-body">
                    <div id="chatMessages" class="chat-messages"></div>
                </div>
                <div class="modal-footer">
                    <input type="text" id="chatInput" class="form-control" placeholder="Type a reply...">
                    <button id="sendMessageBtn" class="btn btn-primary"><i class="fas fa-paper-plane"></i></button>
                </div>
            </div>
        </div>
    </div>

<script>
let sellerChatInterval = null;

function updateTotalUnread() {
    let total = 0;
    document.querySelectorAll('[id^="unreadBadge_"]').forEach(el => total += parseInt(el.textContent));
    document.getElementById('totalUnread').textContent = total + ' unread';
}

function openChat(inquiryId, productName) {
    document.getElementById('chatTitle').textContent = productName;
    const modalEl = document.getElementById('chatModal');
    const modal = new bootstrap.Modal(modalEl);
    modal.show();

    // Load messages
    $('#chatMessages').html('<div class="text-center text-muted">Loading...</div>');
    $.get('seller_get_inquiry_messages.php', { inquiry_id: inquiryId }, function(html) {
        $('#chatMessages').html(html);
        $('#chatMessages').scrollTop($('#chatMessages')[0].scrollHeight);
        // Mark user messages as read
        $.post('seller_mark_messages_read.php', { inquiry_id: inquiryId });
        // Clear unread badge
        $('#unreadBadge_' + inquiryId).remove();
        updateTotalUnread();
    });

    // Start polling while modal open
    if (sellerChatInterval) clearInterval(sellerChatInterval);
    sellerChatInterval = setInterval(function(){
        $.get('seller_get_inquiry_messages.php', { inquiry_id: inquiryId }, function(html) {
            $('#chatMessages').html(html);
            $('#chatMessages').scrollTop($('#chatMessages')[0].scrollHeight);
        });
    }, 3000);

    // Wire send
    $('#sendMessageBtn').off('click').on('click', function() {
        const msg = $('#chatInput').val().trim();
        if (!msg) return;
        $.post('seller_send_message.php', { inquiry_id: inquiryId, message: msg }, function(resp) {
            if (resp && resp.success) {
                $('#chatInput').val('');
                // Optimistic refresh
                $.get('seller_get_inquiry_messages.php', { inquiry_id: inquiryId }, function(html) {
                    $('#chatMessages').html(html);
                    $('#chatMessages').scrollTop($('#chatMessages')[0].scrollHeight);
                });
            }
        }, 'json');
    });
}

$(function(){
    updateTotalUnread();
    $('.open-chat').on('click', function(){
        const name = $(this).data('customer-name') || '';
        $('#chatCustomerName').text(name ? '(' + name + ')' : '');
        openChat($(this).data('inquiry-id'), $(this).data('product-name'));
    });
    $('#chatModal').on('hidden.bs.modal', function(){
        if (sellerChatInterval) {
            clearInterval(sellerChatInterval);
            sellerChatInterval = null;
        }
    });
});
</script>
</body>
</html>


