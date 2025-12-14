<?php
session_start();
require_once 'config.php';

// Check if user is logged in
$is_logged_in = isset($_SESSION['user_id']);

if (!$is_logged_in) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? '';
$user_role = $_SESSION['user_role'] ?? '';

// Get stream ID from URL
$stream_id = intval($_GET['stream_id'] ?? 0);

if (!$stream_id) {
    header('Location: live_streams.php');
    exit();
}

// Get stream details
$stream_stmt = $pdo->prepare("
    SELECT ls.*, u.store_name, u.first_name, u.last_name, 
           c.name as category_name,
           COUNT(lsv.id) as current_viewers
    FROM live_streams ls
    JOIN users u ON ls.seller_id = u.id
    LEFT JOIN categories c ON ls.category_id = c.id
    LEFT JOIN live_stream_viewers lsv ON ls.id = lsv.stream_id AND lsv.is_active = 1
    WHERE ls.id = ?
    GROUP BY ls.id
");
$stream_stmt->execute([$stream_id]);
$stream = $stream_stmt->fetch(PDO::FETCH_ASSOC);

if (!$stream) {
    header('Location: live_streams.php');
    exit();
}

// Handle viewer tracking
$session_id = session_id();

// First, mark any previous active sessions for this user/session as inactive
$update_old_sessions = $pdo->prepare("
    UPDATE live_stream_viewers 
    SET is_active = 0, left_at = NOW() 
    WHERE (user_id = ? OR session_id = ?) 
    AND stream_id = ? 
    AND is_active = 1
");
$update_old_sessions->execute([$user_id, $session_id, $stream_id]);

// Add new viewer record
$add_viewer = $pdo->prepare("
    INSERT INTO live_stream_viewers 
    (stream_id, user_id, session_id, ip_address, joined_at, is_active) 
    VALUES (?, ?, ?, ?, NOW(), 1)
");
$add_viewer->execute([
    $stream_id,
    $user_id,
    $session_id,
    $_SERVER['REMOTE_ADDR']
]);

// Handle comments
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_comment') {
    if ($is_logged_in && $stream['is_live']) {
        $comment = trim($_POST['comment']);
        if (!empty($comment)) {
            $comment_stmt = $pdo->prepare("
                INSERT INTO live_stream_comments (stream_id, user_id, comment, is_seller) 
                VALUES (?, ?, ?, 0)
            ");
            $comment_stmt->execute([$stream_id, $user_id, $comment]);
        }
    }
}

// Get recent comments
$comments_stmt = $pdo->prepare("
    SELECT lsc.*, u.first_name, u.last_name, u.store_name
    FROM live_stream_comments lsc
    LEFT JOIN users u ON lsc.user_id = u.id
    WHERE lsc.stream_id = ?
    ORDER BY lsc.created_at DESC
    LIMIT 50
");
$comments_stmt->execute([$stream_id]);
$comments = $comments_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get stream products
$products_stmt = $pdo->prepare("
    SELECT p.*, lsp.special_price, lsp.discount_percentage, lsp.is_highlighted
    FROM live_stream_products lsp
    JOIN products p ON lsp.product_id = p.id
    WHERE lsp.stream_id = ? AND p.seller_id = ?
    ORDER BY lsp.featured_at DESC
");
$products_stmt->execute([$stream_id, $stream['seller_id']]);
$stream_products = $products_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get seller's other products
$seller_products_stmt = $pdo->prepare("
    SELECT p.*, c.name as category_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.seller_id = ? AND p.status = 'active' AND p.id NOT IN (
        SELECT product_id FROM live_stream_products WHERE stream_id = ?
    )
    ORDER BY p.created_at DESC
    LIMIT 6
");
$seller_products_stmt->execute([$stream['seller_id'], $stream_id]);
$seller_products = $seller_products_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($stream['title']); ?> - Live Stream</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4e73df;
            --live-color: #e74a3b;
            --dark-color: #2e3a59;
        }
        
        body {
            background-color: #000;
            color: white;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .navbar {
            background: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(10px);
        }
        
        .stream-container {
            position: relative;
            background: #000;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 20px;
        }
        
        .stream-video {
            width: 100%;
            height: 500px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 4rem;
            color: white;
            position: relative;
        }
        
        .live-indicator {
            position: absolute;
            top: 15px;
            left: 15px;
            background: var(--live-color);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: bold;
            animation: pulse 2s infinite;
            z-index: 10;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.7; }
            100% { opacity: 1; }
        }
        
        .viewer-count {
            position: absolute;
            top: 15px;
            right: 15px;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: bold;
            z-index: 10;
        }
        
        .connection-status {
            position: absolute;
            bottom: 15px;
            left: 15px;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: bold;
            z-index: 10;
        }
        
        .connection-status.connecting {
            background: rgba(255, 193, 7, 0.7);
        }
        
        .connection-status.connected {
            background: rgba(40, 167, 69, 0.7);
        }
        
        .connection-status.error {
            background: rgba(220, 53, 69, 0.7);
        }
        
        .stream-info {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .chat-container {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 10px;
            height: 500px;
            display: flex;
            flex-direction: column;
        }
        
        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 15px;
        }
        
        .chat-message {
            margin-bottom: 10px;
            padding: 8px 12px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            font-size: 0.9rem;
        }
        
        .chat-message.seller {
            background: rgba(78, 115, 223, 0.3);
            border-left: 3px solid var(--primary-color);
        }
        
        .chat-input {
            padding: 15px;
            border-top: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .product-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            transition: all 0.3s;
        }
        
        .product-card:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }
        
        .product-image {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 8px;
        }
        
        .highlighted {
            border: 2px solid #ffd700;
            box-shadow: 0 0 20px rgba(255, 215, 0, 0.3);
        }
        
        .btn-live {
            background: var(--live-color);
            border: none;
            color: white;
            padding: 10px 20px;
            border-radius: 20px;
            font-weight: bold;
        }
        
        .btn-live:hover {
            background: #c0392b;
            color: white;
        }
        
        .seller-info {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        
        /* Connection Status */
        .connection-status {
            position: absolute;
            bottom: 15px;
            left: 15px;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: bold;
            z-index: 10;
        }

        .connection-status.connecting {
            background: rgba(255, 193, 7, 0.7);
        }

        .connection-status.connected {
            background: rgba(40, 167, 69, 0.7);
        }

        .connection-status.error {
            background: rgba(220, 53, 69, 0.7);
        }

        /* Chat Styles */
        .chat-messages {
            padding: 15px;
            overflow-y: auto;
            flex: 1;
        }

        .chat-message {
            margin-bottom: 15px;
            padding: 10px 15px;
            border-radius: 10px;
            max-width: 80%;
            word-wrap: break-word;
        }

        .own-message {
            margin-left: auto;
            background: rgba(0, 123, 255, 0.2);
            border: 1px solid rgba(0, 123, 255, 0.3);
        }

        .other-message {
            margin-right: auto;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .message-header {
            display: flex;
            justify-content: space-between;
            font-size: 0.8em;
            margin-bottom: 5px;
            color: #aaa;
        }

        .message-sender {
            font-weight: bold;
            color: #fff;
        }

        .message-time {
            opacity: 0.7;
        }

        .chat-input {
            display: flex;
            padding: 15px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        #messageInput {
            flex: 1;
            margin-right: 10px;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
        }

        #messageInput:focus {
            background: rgba(255, 255, 255, 0.15);
            border-color: rgba(0, 123, 255, 0.5);
            color: white;
            box-shadow: 0 0 0 0.25rem rgba(0, 123, 255, 0.25);
        }

        /* Video Fallback */
        .video-fallback {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 400px;
            background: #000;
            color: white;
            text-align: center;
            padding: 20px;
        }

        .seller-avatar {
            width: 50px;
            height: 50px;
            background: var(--primary-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            margin-right: 15px;
        }
        
        #remoteVideo {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: none;
        }
        
        #videoFallback {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 5;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-shopping-bag me-2"></i>BSDO SALE
            </a>
            
            <div class="d-flex align-items-center">
                <a href="live_streams.php" class="btn btn-outline-light me-2">
                    <i class="fas fa-arrow-left me-1"></i>Back to Streams
                </a>
                <?php if ($is_logged_in): ?>
                    <div class="dropdown">
                        <a class="nav-link dropdown-toggle text-white d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown">
                            <div class="bg-white rounded-circle me-2" style="width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;">
                                <span class="text-primary fw-bold"><?php echo strtoupper(substr($user_name, 0, 1)); ?></span>
                            </div>
                            <span><?php echo htmlspecialchars(explode(' ', $user_name)[0]); ?></span>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="myAccount.php"><i class="fas fa-user me-2"></i>My Account</a></li>
                            <li><a class="dropdown-item" href="inquiries.php"><i class="fas fa-comments me-2"></i>My Inquiries</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </div>
                <?php else: ?>
                    <a href="login.php" class="btn btn-outline-light me-2">Login</a>
                    <a href="register.php" class="btn btn-light">Register</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <div class="container-fluid" style="margin-top: 80px;">
        <div class="row">
            <!-- Main Stream Area -->
            <div class="col-lg-8">
                <!-- Stream Video -->
                <div class="stream-container">
                    <div class="status-indicator">
                        <i class="fas fa-circle me-1"></i>LIVE
                    </div>
                    <div class="viewer-count">
                        <i class="fas fa-eye me-1"></i><span id="viewerCount"><?php echo $stream['current_viewers']; ?></span> watching
                    </div>
                    <div id="connectionStatus" class="connection-status connecting">
                        <i class="fas fa-spinner fa-spin me-1"></i>Connecting...
                    </div>
                    <!-- HLS video element -->
                    <video id="hlsPlayer" controls autoplay muted style="width: 100%; height: 500px; background: #000;"></video>
                    <!-- Fallback content when video is not available -->
                    <div id="videoFallback" class="video-fallback">
                        <div class="text-center">
                            <i class="fas fa-spinner fa-spin fa-3x mb-3"></i>
                            <h4>Connecting to Stream</h4>
                            <p class="text-muted">Please wait while we connect you to the live stream...</p>
                        </div>
                    </div>
                </div>
                </div>

                <!-- Stream Info -->
                <div class="stream-info">
                    <div class="seller-info">
                        <div class="seller-avatar">
                            <?php echo strtoupper(substr($stream['first_name'], 0, 1)); ?>
                        </div>
                        <div>
                            <h5 class="mb-1"><?php echo htmlspecialchars($stream['store_name'] ?? 'Seller Store'); ?></h5>
                            <p class="mb-0 text-muted"><?php echo htmlspecialchars($stream['first_name'] . ' ' . $stream['last_name']); ?></p>
                        </div>
                    </div>
                    <h3><?php echo htmlspecialchars($stream['title']); ?></h3>
                    <p class="text-muted"><?php echo htmlspecialchars($stream['description']); ?></p>
                    <?php if (!empty($stream['category_name'])): ?>
                        <span class="badge bg-primary"><?php echo htmlspecialchars($stream['category_name']); ?></span>
                    <?php endif; ?>
                </div>

                <!-- Featured Products -->
                <?php if (!empty($stream_products)): ?>
                    <div class="stream-info">
                        <h5 class="mb-3">Featured Products</h5>
                        <div class="row">
                            <?php foreach ($stream_products as $product): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="product-card <?php echo $product['is_highlighted'] ? 'highlighted' : ''; ?>">
                                        <div class="d-flex">
                                            <?php if (!empty($product['image_url'])): ?>
                                                <img src="<?php echo htmlspecialchars($product['image_url']); ?>" 
                                                     alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                                     class="product-image me-3">
                                            <?php else: ?>
                                                <div class="product-image me-3 bg-secondary d-flex align-items-center justify-content-center">
                                                    <i class="fas fa-image text-white"></i>
                                                </div>
                                            <?php endif; ?>
                                            <div class="flex-grow-1">
                                                <h6 class="mb-1"><?php echo htmlspecialchars($product['name']); ?></h6>
                                                <p class="text-muted small mb-2"><?php echo htmlspecialchars(substr($product['description'], 0, 100)); ?>...</p>
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <?php if ($product['special_price']): ?>
                                                            <span class="text-success fw-bold">$<?php echo number_format($product['special_price'], 2); ?></span>
                                                            <small class="text-muted text-decoration-line-through ms-1">$<?php echo number_format($product['price'], 2); ?></small>
                                                        <?php elseif ($product['product_type'] === 'rental'): ?>
                                                            <span class="text-success fw-bold">$<?php echo number_format($product['rental_price_per_day'], 2); ?>/day</span>
                                                        <?php else: ?>
                                                            <span class="text-success fw-bold">$<?php echo number_format($product['price'], 2); ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <a href="products.php?id=<?php echo $product['id']; ?>" class="btn btn-sm btn-live">
                                                        <i class="fas fa-shopping-cart me-1"></i>Buy Now
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- Live Chat -->
                <div class="chat-container">
                    <div class="chat-header">
                        <i class="fas fa-comments me-2"></i>Live Chat
                    </div>
                    <div id="chatMessages" class="chat-messages"></div>
                    <div class="chat-input">
                        <input type="text" id="messageInput" class="form-control" placeholder="Type your message...">
                        <button id="sendMessageBtn" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </div>
                </div>
                <div class="chat-container">
                    <div class="p-3 border-bottom">
                        <h6 class="mb-0"><i class="fas fa-comments me-2"></i>Live Chat</h6>
                    </div>
                    <div class="chat-messages" id="chatMessages">
                        <?php foreach (array_reverse($comments) as $comment): ?>
                            <div class="chat-message <?php echo $comment['is_seller'] ? 'seller' : ''; ?>">
                                <strong><?php echo htmlspecialchars($comment['first_name'] . ' ' . $comment['last_name']); ?>:</strong>
                                <?php echo htmlspecialchars($comment['comment']); ?>
                                <small class="text-muted d-block"><?php echo date('g:i A', strtotime($comment['created_at'])); ?></small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($is_logged_in && $stream['is_live']): ?>
                        <div class="chat-input">
                            <form method="POST" id="chatForm">
                                <input type="hidden" name="action" value="add_comment">
                                <div class="input-group">
                                    <input type="text" class="form-control" name="comment" placeholder="Type a message..." required>
                                    <button class="btn btn-live" type="submit">
                                        <i class="fas fa-paper-plane"></i>
                                    </button>
                                </div>
                            </form>
                        </div>
                    <?php elseif (!$is_logged_in): ?>
                        <div class="p-3 text-center">
                            <p class="text-muted">Please <a href="login.php" class="text-white">login</a> to join the chat</p>
                        </div>
                    <?php else: ?>
                        <div class="p-3 text-center">
                            <p class="text-muted">Chat is not available for this stream</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Seller's Other Products -->
                <?php if (!empty($seller_products)): ?>
                    <div class="stream-info mt-4">
                        <h6 class="mb-3">More from this Seller</h6>
                        <?php foreach (array_slice($seller_products, 0, 3) as $product): ?>
                            <div class="product-card">
                                <div class="d-flex">
                                    <?php if (!empty($product['image_url'])): ?>
                                        <img src="<?php echo htmlspecialchars($product['image_url']); ?>" 
                                             alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                             class="product-image me-3">
                                    <?php else: ?>
                                        <div class="product-image me-3 bg-secondary d-flex align-items-center justify-content-center">
                                            <i class="fas fa-image text-white"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($product['name']); ?></h6>
                                        <p class="text-success fw-bold mb-1">$<?php echo number_format($product['price'], 2); ?></p>
                                        <a href="products.php?id=<?php echo $product['id']; ?>" class="btn btn-sm btn-outline-light">
                                            View Product
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/hls.js@latest"></script>
    <script>
        let hls = null;
        let messagePollingInterval = null;
        let lastMessageId = 0;
        
        // Initialize when page loads
        $(document).ready(function() {
            <?php if ($stream['is_live']): ?>
                // Initialize HLS player
                initializeHLS();
                
                // Setup chat
                setupChat();
                
                // Notify server that user joined
                updateViewerStatus('join');
            <?php else: ?>
                updateConnectionStatus('error', 'Stream Offline');
            <?php endif; ?>
            
            // Update viewer count periodically
            setInterval(updateViewerCount, 10000);
            updateViewerCount();
        });

        // Initialize HLS player
        function initializeHLS() {
            const video = document.getElementById('hlsPlayer');
            const streamUrl = `<?php echo addslashes($stream['hls_url']); ?>`;
            
            if (Hls.isSupported()) {
                hls = new Hls();
                hls.loadSource(streamUrl);
                hls.attachMedia(video);
                hls.on(Hls.Events.MANIFEST_PARSED, function() {
                    updateConnectionStatus('connected', 'Stream connected');
                    video.play();
                });
                hls.on(Hls.Events.ERROR, function(event, data) {
                    if (data.fatal) {
                        switch(data.type) {
                            case Hls.ErrorTypes.NETWORK_ERROR:
                                updateConnectionStatus('error', 'Network error. Retrying...');
                                hls.startLoad();
                                break;
                            case Hls.ErrorTypes.MEDIA_ERROR:
                                updateConnectionStatus('error', 'Media error. Retrying...');
                                hls.recoverMediaError();
                                break;
                            default:
                                updateConnectionStatus('error', 'Fatal error. Cannot recover.');
                                hls.destroy();
                                break;
                        }
                    }
                });
            } else if (video.canPlayType('application/vnd.apple.mpegurl')) {
                // Fallback for Safari
                video.src = streamUrl;
                video.addEventListener('loadedmetadata', function() {
                    updateConnectionStatus('connected', 'Stream connected');
                    video.play();
                });
                video.addEventListener('error', function() {
                    updateConnectionStatus('error', 'Error playing stream');
                });
            } else {
                updateConnectionStatus('error', 'HLS not supported in your browser');
            }
        }

        // Update connection status UI
        function updateConnectionStatus(status, message) {
            const statusElement = $('#connectionStatus');
            const videoElement = $('#hlsPlayer');
            const fallbackElement = $('#videoFallback');
            
            // Update status element classes
            statusElement.removeClass('connecting connected error')
                        .addClass(status);
            
            if (status === 'connected') {
                statusElement.html(`<i class="fas fa-check-circle"></i> ${message}`);
                videoElement.show();
                fallbackElement.hide();
            } else {
                const icons = {
                    'connecting': 'spinner fa-spin',
                    'reconnecting': 'spinner fa-spin',
                    'error': 'exclamation-triangle',
                    'closed': 'times'
                };
                const icon = icons[status] || 'info-circle';
                statusElement.html(`<i class="fas ${icon}"></i> ${message}`);
                videoElement.hide();
                
                // Show fallback content for non-connected states
                if (status !== 'connected') {
                    fallbackElement.show();
                }
            }
        }

        // Update viewer status (join/leave)
        function updateViewerStatus(action) {
            $.post('update_viewer_status.php', {
                action: action,
                stream_id: <?php echo $stream_id; ?>,
                user_id: <?php echo $user_id; ?>
            });
        }

        // Update viewer count
        function updateViewerCount() {
            $.get('get_viewer_count.php', {
                stream_id: <?php echo $stream_id; ?>
            }).done(function(response) {
                if (response.success) {
                    $('#viewerCount').text(response.count);
                }
            });
        }

        // Show chat error
        function showChatError(message) {
            const errorHtml = `
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            `;
            $('#chatMessages').append(errorHtml);
        }

        // Setup chat functionality
        function setupChat() {
            // Handle send message
            $('#sendMessageBtn').on('click', sendMessage);
            $('#messageInput').on('keypress', function(e) {
                if (e.which === 13) {
                    sendMessage();
                }
            });

            // Start polling for new messages
            pollForNewMessages();
        }

        // Send chat message
        function sendMessage() {
            const message = $('#messageInput').val().trim();
            if (!message) return;

            // Add message to chat immediately (optimistic update)
            addMessageToChat({
                id: 'temp_' + Date.now(),
                user_id: <?php echo $user_id; ?>,
                first_name: '<?php echo addslashes($user_name); ?>',
                message: message,
                created_at: new Date().toISOString()
            }, true);

            // Clear input
            $('#messageInput').val('');

            // Send to server
            $.post('handle_live_chat.php', {
                action: 'send_message',
                stream_id: <?php echo $stream_id; ?>,
                message: message
            }).fail(function() {
                showChatError('Failed to send message. Please try again.');
            });
        }

        // Poll for new messages
        function pollForNewMessages() {
            // Clear any existing interval
            if (messagePollingInterval) {
                clearInterval(messagePollingInterval);
            }

            // Initial fetch
            fetchNewMessages();

            // Set up polling
            messagePollingInterval = setInterval(fetchNewMessages, 2000); // Poll every 2 seconds
        }

        // Fetch new messages from server
        function fetchNewMessages() {
            $.get('handle_live_chat.php', {
                action: 'get_messages',
                stream_id: <?php echo $stream_id; ?>,
                last_id: lastMessageId
            }).done(function(response) {
                if (response.success && response.messages) {
                    response.messages.forEach(function(message) {
                        // Only add messages we haven't seen before
                        if (message.id > lastMessageId) {
                            addMessageToChat(message);
                            lastMessageId = message.id;
                        }
                    });
                    
                    // Auto-scroll to bottom
                    const chatMessages = $('#chatMessages');
                    chatMessages.scrollTop(chatMessages[0].scrollHeight);
                }
            }).fail(function() {
                console.error('Failed to fetch messages');
            });
        }

        // Add message to chat UI
        function addMessageToChat(message, isOwn = false) {
            const messageTime = new Date(message.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            const messageClass = isOwn ? 'own-message' : 'other-message';
            
            // Check if this is a temporary message (already shown optimistically)
            if (message.id.toString().startsWith('temp_') && isOwn) {
                // Update the temporary message with the real one
                const tempId = message.id;
                const tempMessage = $(`[data-message-id="${tempId}"]`);
                if (tempMessage.length) {
                    tempMessage.attr('data-message-id', message.id);
                    tempMessage.find('.message-content').text(message.message);
                    return;
                }
            }
            
            // Don't add duplicate messages
            if ($(`[data-message-id="${message.id}"]`).length) {
                return;
            }
            
            const messageHtml = `
                <div class="chat-message ${messageClass}" data-message-id="${message.id}">
                    <div class="message-header">
                        <span class="message-sender">${message.first_name || 'User'}</span>
                        <span class="message-time">${messageTime}</span>
                    </div>
                    <div class="message-content">${escapeHtml(message.message)}</div>
                </div>
            `;
            
            $('#chatMessages').append(messageHtml);
            
            // Auto-scroll to bottom
            const chatMessages = $('#chatMessages');
            chatMessages.scrollTop(chatMessages[0].scrollHeight);
        }

        // Helper function to escape HTML
        function escapeHtml(unsafe) {
            return $('<div>').text(unsafe).html();
        }

        // Handle page unload
        $(window).on('beforeunload', function() {
            if (messagePollingInterval) {
                clearInterval(messagePollingInterval);
            }
            if (hls) {
                hls.destroy();
            }
            // Notify server that user is leaving
            navigator.sendBeacon('update_viewer_status.php', JSON.stringify({
                action: 'leave',
                stream_id: <?php echo $stream_id; ?>,
                user_id: <?php echo $user_id; ?>
            }));
        });
    </script>
</body>
</html>