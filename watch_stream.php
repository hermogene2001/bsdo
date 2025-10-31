<?php
session_start();
require_once 'config.php';

// Check if user is logged in
$is_logged_in = isset($_SESSION['user_id']);
$user_id = $_SESSION['user_id'] ?? null;
$user_name = $_SESSION['user_name'] ?? '';

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
if ($is_logged_in) {
    // Check if user is already viewing
    $viewer_check = $pdo->prepare("
        SELECT id FROM live_stream_viewers 
        WHERE stream_id = ? AND user_id = ? AND is_active = 1
    ");
    $viewer_check->execute([$stream_id, $user_id]);
    
    if ($viewer_check->rowCount() == 0) {
        // Add viewer
        $add_viewer = $pdo->prepare("
            INSERT INTO live_stream_viewers (stream_id, user_id, session_id, ip_address) 
            VALUES (?, ?, ?, ?)
        ");
        $add_viewer->execute([$stream_id, $user_id, session_id(), $_SERVER['REMOTE_ADDR']]);
    }
} else {
    // Track anonymous viewer
    $session_id = session_id();
    $viewer_check = $pdo->prepare("
        SELECT id FROM live_stream_viewers 
        WHERE stream_id = ? AND session_id = ? AND is_active = 1
    ");
    $viewer_check->execute([$stream_id, $session_id]);
    
    if ($viewer_check->rowCount() == 0) {
        $add_viewer = $pdo->prepare("
            INSERT INTO live_stream_viewers (stream_id, user_id, session_id, ip_address) 
            VALUES (?, NULL, ?, ?)
        ");
        $add_viewer->execute([$stream_id, $session_id, $_SERVER['REMOTE_ADDR']]);
    }
}

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

// Get stream products (only products from the seller who is currently live)
$products_stmt = $pdo->prepare("
    SELECT p.*, lsp.special_price, lsp.discount_percentage, lsp.is_highlighted
    FROM live_stream_products lsp
    JOIN products p ON lsp.product_id = p.id
    WHERE lsp.stream_id = ? AND p.seller_id = ?
    ORDER BY lsp.featured_at DESC
");
$products_stmt->execute([$stream_id, $stream['seller_id']]);
$stream_products = $products_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get seller's other products (only products from the seller who is currently live)
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
                    <div class="stream-video">
                        <div class="live-indicator">
                            <i class="fas fa-circle me-1"></i>LIVE
                        </div>
                        <div class="viewer-count">
                            <i class="fas fa-eye me-1"></i><?php echo $stream['current_viewers']; ?> watching
                        </div>
                        <!-- Remote video element for WebRTC streaming -->
                        <video id="remoteVideo" autoplay playsinline style="width: 100%; height: 100%; object-fit: cover;"></video>
                        <!-- Fallback content when video is not available -->
                        <div id="videoFallback" class="text-center" style="width: 100%; display: none;">
                            <div class="mb-3">
                                <i class="fas fa-video fa-3x mb-3"></i>
                                <h4>Connecting to Live Stream...</h4>
                                <p class="text-muted">Establishing connection to seller's video feed</p>
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
    <script>
        let peerConnection = null;
        let remoteStream = null;
        let roomId = null;
        let messagePollingInterval = null;
        let lastMessageId = 0;
        let localStream = null;

        // WebRTC configuration
        const configuration = {
            iceServers: [
                { urls: 'stun:stun.l.google.com:19302' },
                { urls: 'stun:stun1.l.google.com:19302' }
            ]
        };

        // Initialize when page loads
        document.addEventListener('DOMContentLoaded', function() {
            <?php if ($stream['is_live']): ?>
                initializeWebRTC();
            <?php endif; ?>
        });

        // Initialize WebRTC for client
        async function initializeWebRTC() {
            <?php if ($stream['is_live']): ?>
            try {
                // Join room for this stream
                roomId = 'room_<?php echo $stream_id; ?>_<?php echo strtotime($stream['started_at']); ?>';
                
                const response = await fetch('webrtc_server.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=join_room&room_id=${roomId}`
                });
                
                const data = await response.json();
                if (data.success) {
                    console.log('Joined WebRTC room:', roomId);
                    
                    // Create peer connection
                    createPeerConnection();
                    
                    // Start polling for messages
                    startMessagePolling();
                } else {
                    console.error('Failed to join WebRTC room:', data.error);
                }
            } catch (error) {
                console.error('Error initializing WebRTC:', error);
            }
            <?php endif; ?>
        }

        // Create peer connection
        function createPeerConnection() {
            peerConnection = new RTCPeerConnection(configuration);
            
            // Handle remote stream
            peerConnection.ontrack = (event) => {
                console.log('Received remote stream');
                const videoElement = document.getElementById('remoteVideo');
                const fallbackElement = document.getElementById('videoFallback');
                if (videoElement) {
                    videoElement.srcObject = event.streams[0];
                    videoElement.style.display = 'block';
                    if (fallbackElement) {
                        fallbackElement.style.display = 'none';
                    }
                }
            };
            
            // Handle ICE candidates
            peerConnection.onicecandidate = async (event) => {
                if (event.candidate) {
                    try {
                        await fetch('webrtc_server.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: `action=send_candidate&room_id=${roomId}&candidate=${encodeURIComponent(JSON.stringify(event.candidate))}`
                        });
                    } catch (error) {
                        console.error('Error sending candidate:', error);
                    }
                }
            };
            
            // Handle connection state changes
            peerConnection.onconnectionstatechange = () => {
                console.log('Connection state:', peerConnection.connectionState);
                const videoElement = document.getElementById('remoteVideo');
                const fallbackElement = document.getElementById('videoFallback');
                
                if (peerConnection.connectionState === 'connected') {
                    if (videoElement) videoElement.style.display = 'block';
                    if (fallbackElement) fallbackElement.style.display = 'none';
                } else if (peerConnection.connectionState === 'failed' || peerConnection.connectionState === 'disconnected') {
                    if (videoElement) videoElement.style.display = 'none';
                    if (fallbackElement) {
                        fallbackElement.style.display = 'block';
                        fallbackElement.innerHTML = `
                            <div class="mb-3">
                                <i class="fas fa-exclamation-triangle fa-3x mb-3 text-warning"></i>
                                <h4>Connection Lost</h4>
                                <p class="text-muted">Unable to connect to the live stream. Please try again.</p>
                                <button class="btn btn-primary" onclick="initializeWebRTC()">Retry Connection</button>
                            </div>
                        `;
                    }
                }
            };
            
            // Handle ICE connection state changes
            peerConnection.oniceconnectionstatechange = () => {
                console.log('ICE connection state:', peerConnection.iceConnectionState);
            };
        }

        // Start polling for WebRTC messages
        function startMessagePolling() {
            messagePollingInterval = setInterval(async () => {
                if (!roomId) return;
                
                try {
                    const response = await fetch('webrtc_server.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=get_messages&room_id=${roomId}&last_id=${lastMessageId}`
                    });
                    
                    const data = await response.json();
                    if (data.success && data.messages.length > 0) {
                        data.messages.forEach(message => {
                            handleWebRTCMessage(message);
                            lastMessageId = Math.max(lastMessageId, message.id);
                        });
                    }
                } catch (error) {
                    console.error('Error polling messages:', error);
                }
            }, 1000); // Poll every second
        }

        // Handle incoming WebRTC messages
        function handleWebRTCMessage(message) {
            const data = JSON.parse(message.message_data);
            
            switch (message.message_type) {
                case 'offer':
                    handleOffer(data);
                    break;
                case 'answer':
                    handleAnswer(data);
                    break;
                case 'candidate':
                    handleCandidate(data);
                    break;
            }
        }

        // Handle incoming offer from seller
        async function handleOffer(offer) {
            if (!peerConnection) {
                createPeerConnection();
            }
            
            try {
                await peerConnection.setRemoteDescription(new RTCSessionDescription(offer));
                const answer = await peerConnection.createAnswer();
                await peerConnection.setLocalDescription(answer);
                
                // Send answer back to seller
                await fetch('webrtc_server.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=send_answer&room_id=${roomId}&answer=${encodeURIComponent(JSON.stringify(answer))}`
                });
            } catch (error) {
                console.error('Error handling offer:', error);
            }
        }

        // Handle incoming answer from seller
        async function handleAnswer(answer) {
            try {
                await peerConnection.setRemoteDescription(new RTCSessionDescription(answer));
            } catch (error) {
                console.error('Error handling answer:', error);
            }
        }

        // Handle incoming ICE candidate
        async function handleCandidate(candidate) {
            try {
                if (peerConnection) {
                    await peerConnection.addIceCandidate(new RTCIceCandidate(candidate));
                }
            } catch (error) {
                console.error('Error handling candidate:', error);
            }
        }

        // Send offer to connect to seller
        async function sendOffer() {
            if (!peerConnection) {
                createPeerConnection();
            }
            
            try {
                const offer = await peerConnection.createOffer();
                await peerConnection.setLocalDescription(offer);
                
                // Send offer to seller
                await fetch('webrtc_server.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=send_offer&room_id=${roomId}&offer=${encodeURIComponent(JSON.stringify(offer))}`
                });
            } catch (error) {
                console.error('Error creating offer:', error);
            }
        }

        // Update chat functionality
        function loadMessages() {
            const inquiryId = <?php echo $inquiry_id ?? 'null'; ?>;
            if (inquiryId) {
                fetch('get_inquiry_messages.php?inquiry_id=' + inquiryId)
                    .then(response => response.text())
                    .then(html => {
                        document.getElementById('chatMessages').innerHTML = html;
                        scrollChatToBottom();
                    });
            }
        }

        function scrollChatToBottom() {
            const chatMessages = document.getElementById('chatMessages');
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }

        // Scroll to bottom on page load
        scrollChatToBottom();

        // Auto-refresh for live data
        function refreshLiveData() {
            if (<?php echo $stream['is_live'] ? 'true' : 'false'; ?>) {
                setTimeout(() => {
                    window.location.reload();
                }, 30000); // Refresh every 30 seconds
            }
        }

        // Start auto-refresh if stream is live
        refreshLiveData();

        // Chat form submission
        document.getElementById('chatForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            
            fetch('', {
                method: 'POST',
                body: formData
            }).then(() => {
                window.location.reload();
            });
        });

        // Clean up when page unloads
        window.addEventListener('beforeunload', function() {
            if (messagePollingInterval) {
                clearInterval(messagePollingInterval);
            }
            if (roomId) {
                fetch('webrtc_server.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=leave_room&room_id=${roomId}`
                });
            }
        });
    </script>
</body>
</html>