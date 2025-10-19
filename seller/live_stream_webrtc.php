<?php
session_start();
require_once '../config.php';

// Check if user is logged in and is seller
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'seller') {
    header('Location: ../login.php');
    exit();
}

$seller_id = $_SESSION['user_id'];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'start_stream':
                $title = trim($_POST['title']);
                $description = trim($_POST['description']);
                $category_id = intval($_POST['category_id']);
                
                // Generate unique stream key
                $stream_key = 'stream_' . $seller_id . '_' . time() . '_' . bin2hex(random_bytes(8));
                
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO live_streams (seller_id, title, description, category_id, stream_key, is_live, status, started_at) 
                        VALUES (?, ?, ?, ?, ?, 1, 'live', NOW())
                    ");
                    $stmt->execute([$seller_id, $title, $description, $category_id, $stream_key]);
                    $stream_id = $pdo->lastInsertId();
                    
                    header("Location: live_stream_webrtc.php?stream_id=" . $stream_id);
                    exit();
                } catch (Exception $e) {
                    $error_message = "Failed to start stream: " . $e->getMessage();
                }
                break;
                
            case 'end_stream':
                $stream_id = intval($_POST['stream_id']);
                
                // Verify stream belongs to seller
                $stmt = $pdo->prepare("SELECT id FROM live_streams WHERE id = ? AND seller_id = ?");
                $stmt->execute([$stream_id, $seller_id]);
                if ($stmt->rowCount() > 0) {
                    $update_stmt = $pdo->prepare("
                        UPDATE live_streams 
                        SET is_live = 0, status = 'ended', ended_at = NOW() 
                        WHERE id = ?
                    ");
                    $update_stmt->execute([$stream_id]);
                    $success_message = "Stream ended successfully!";
                    // Redirect to live stream list after ending
                    header("Location: live_stream.php");
                    exit();
                } else {
                    $error_message = "Stream not found or access denied.";
                }
                break;
                
            case 'feature_product':
                $stream_id = intval($_POST['stream_id']);
                $product_id = intval($_POST['product_id']);
                $special_price = !empty($_POST['special_price']) ? floatval($_POST['special_price']) : null;
                $discount_percentage = !empty($_POST['discount_percentage']) ? floatval($_POST['discount_percentage']) : null;
                
                // Verify stream belongs to seller and product belongs to seller
                $stmt = $pdo->prepare("
                    SELECT ls.id 
                    FROM live_streams ls 
                    JOIN products p ON p.seller_id = ls.seller_id 
                    WHERE ls.id = ? AND ls.seller_id = ? AND p.id = ?
                ");
                $stmt->execute([$stream_id, $seller_id, $product_id]);
                if ($stmt->rowCount() > 0) {
                    // Check if product is already featured
                    $check_stmt = $pdo->prepare("SELECT id FROM live_stream_products WHERE stream_id = ? AND product_id = ?");
                    $check_stmt->execute([$stream_id, $product_id]);
                    
                    if ($check_stmt->rowCount() > 0) {
                        // Update existing featured product
                        $update_stmt = $pdo->prepare("
                            UPDATE live_stream_products 
                            SET special_price = ?, discount_percentage = ?, featured_at = NOW()
                            WHERE stream_id = ? AND product_id = ?
                        ");
                        $update_stmt->execute([$special_price, $discount_percentage, $stream_id, $product_id]);
                    } else {
                        // Add new featured product
                        $insert_stmt = $pdo->prepare("
                            INSERT INTO live_stream_products (stream_id, product_id, special_price, discount_percentage) 
                            VALUES (?, ?, ?, ?)
                        ");
                        $insert_stmt->execute([$stream_id, $product_id, $special_price, $discount_percentage]);
                    }
                    
                    $success_message = "Product featured successfully!";
                } else {
                    $error_message = "Invalid stream or product.";
                }
                break;
                
            case 'remove_featured_product':
                $stream_id = intval($_POST['stream_id']);
                $product_id = intval($_POST['product_id']);
                
                // Verify stream belongs to seller
                $stmt = $pdo->prepare("SELECT id FROM live_streams WHERE id = ? AND seller_id = ?");
                $stmt->execute([$stream_id, $seller_id]);
                if ($stmt->rowCount() > 0) {
                    $delete_stmt = $pdo->prepare("DELETE FROM live_stream_products WHERE stream_id = ? AND product_id = ?");
                    $delete_stmt->execute([$stream_id, $product_id]);
                    $success_message = "Product removed from stream!";
                } else {
                    $error_message = "Stream not found or access denied.";
                }
                break;
                
            case 'highlight_product':
                $stream_id = intval($_POST['stream_id']);
                $product_id = intval($_POST['product_id']);
                
                // Verify stream belongs to seller
                $stmt = $pdo->prepare("SELECT id FROM live_streams WHERE id = ? AND seller_id = ?");
                $stmt->execute([$stream_id, $seller_id]);
                if ($stmt->rowCount() > 0) {
                    $update_stmt = $pdo->prepare("UPDATE live_stream_products SET is_highlighted = 1 WHERE stream_id = ? AND product_id = ?");
                    $update_stmt->execute([$stream_id, $product_id]);
                    $success_message = "Product highlighted!";
                } else {
                    $error_message = "Stream not found or access denied.";
                }
                break;
                
            case 'unhighlight_product':
                $stream_id = intval($_POST['stream_id']);
                $product_id = intval($_POST['product_id']);
                
                // Verify stream belongs to seller
                $stmt = $pdo->prepare("SELECT id FROM live_streams WHERE id = ? AND seller_id = ?");
                $stmt->execute([$stream_id, $seller_id]);
                if ($stmt->rowCount() > 0) {
                    $update_stmt = $pdo->prepare("UPDATE live_stream_products SET is_highlighted = 0 WHERE stream_id = ? AND product_id = ?");
                    $update_stmt->execute([$stream_id, $product_id]);
                    $success_message = "Product unhighlighted!";
                } else {
                    $error_message = "Stream not found or access denied.";
                }
                break;
        }
    }
}

// Get current stream
$stream_id = intval($_GET['stream_id'] ?? 0);
$current_stream = null;

if ($stream_id) {
    $stream_stmt = $pdo->prepare("
        SELECT ls.*, c.name as category_name, COUNT(lsv.id) as current_viewers
        FROM live_streams ls
        LEFT JOIN categories c ON ls.category_id = c.id
        LEFT JOIN live_stream_viewers lsv ON ls.id = lsv.stream_id AND lsv.is_active = 1
        WHERE ls.id = ? AND ls.seller_id = ?
        GROUP BY ls.id
    ");
    $stream_stmt->execute([$stream_id, $seller_id]);
    $current_stream = $stream_stmt->fetch(PDO::FETCH_ASSOC);
}

// Get seller's products (only products created by this seller)
$products_stmt = $pdo->prepare("
    SELECT id, name, price, image_url, description 
    FROM products 
    WHERE seller_id = ? AND status = 'active' 
    ORDER BY created_at DESC
");
$products_stmt->execute([$seller_id]);
$seller_products = $products_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get featured products for current stream (only products from this seller)
$featured_products = [];
if ($current_stream) {
    $featured_stmt = $pdo->prepare("
        SELECT lsp.*, p.name, p.price, p.image_url, p.description
        FROM live_stream_products lsp
        JOIN products p ON lsp.product_id = p.id
        WHERE lsp.stream_id = ? AND p.seller_id = ?
        ORDER BY lsp.featured_at DESC
    ");
    $featured_stmt->execute([$stream_id, $seller_id]);
    $featured_products = $featured_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get categories
$categories_stmt = $pdo->prepare("SELECT id, name FROM categories WHERE status = 'active' ORDER BY name");
$categories_stmt->execute();
$categories = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Stream with Camera - BSDO Seller</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #000;
            color: white;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .stream-container {
            position: relative;
            background: #000;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 20px;
        }
        
        #videoElement {
            width: 100%;
            height: 500px;
            background: #000;
            object-fit: cover;
        }
        
        .stream-controls {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .btn-live {
            background: #e74a3b;
            border: none;
            color: white;
            padding: 12px 30px;
            border-radius: 25px;
            font-weight: bold;
        }
        
        .btn-live:hover {
            background: #c0392b;
            color: white;
        }
        
        .btn-end {
            background: #6c757d;
            border: none;
            color: white;
            padding: 12px 30px;
            border-radius: 25px;
            font-weight: bold;
        }
        
        .btn-return {
            background: #4e73df;
            border: none;
            color: white;
            padding: 12px 30px;
            border-radius: 25px;
            font-weight: bold;
        }
        
        .camera-controls {
            position: absolute;
            bottom: 20px;
            left: 20px;
            right: 20px;
            display: flex;
            justify-content: center;
            gap: 10px;
        }
        
        .camera-btn {
            background: rgba(0, 0, 0, 0.7);
            border: none;
            color: white;
            padding: 10px 15px;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .camera-btn:hover {
            background: rgba(0, 0, 0, 0.9);
        }
        
        .camera-btn.active {
            background: #e74a3b;
        }
        
        .status-indicator {
            position: absolute;
            top: 20px;
            left: 20px;
            background: #e74a3b;
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
            top: 20px;
            right: 20px;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: bold;
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
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
        }
        
        .highlighted {
            border: 2px solid #ffd700;
            box-shadow: 0 0 20px rgba(255, 215, 0, 0.3);
        }
        
        .featured-products-container {
            max-height: 500px;
            overflow-y: auto;
        }
        
        .product-list-container {
            max-height: 500px;
            overflow-y: auto;
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($current_stream): ?>
            <!-- Live Stream with Camera -->
            <div class="row">
                <div class="col-lg-8">
                    <div class="stream-container">
                        <video id="videoElement" autoplay muted></video>
                        <div class="status-indicator">
                            <i class="fas fa-circle me-1"></i>LIVE
                        </div>
                        <div class="viewer-count">
                            <i class="fas fa-eye me-1"></i><?php echo $current_stream['current_viewers']; ?> watching
                        </div>
                        <div class="camera-controls">
                            <button class="camera-btn" id="startCamera" title="Start Camera">
                                <i class="fas fa-video"></i>
                            </button>
                            <button class="camera-btn" id="stopCamera" title="Stop Camera" style="display: none;">
                                <i class="fas fa-video-slash"></i>
                            </button>
                            <button class="camera-btn" id="switchCamera" title="Switch Camera" style="display: none;">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                            <button class="camera-btn" id="muteAudio" title="Mute Audio" style="display: none;">
                                <i class="fas fa-microphone"></i>
                            </button>
                        </div>
                    </div>

                    <div class="stream-controls">
                        <h4><?php echo htmlspecialchars($current_stream['title']); ?></h4>
                        <p><?php echo htmlspecialchars($current_stream['description']); ?></p>
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <span class="badge bg-primary me-2"><?php echo htmlspecialchars($current_stream['category_name'] ?? 'General'); ?></span>
                                <small class="text-muted">Started: <?php echo date('M j, Y g:i A', strtotime($current_stream['started_at'])); ?></small>
                            </div>
                            <div>
                                <a href="live_stream.php" class="btn btn-return me-2">
                                    <i class="fas fa-list me-2"></i>Live Stream List
                                </a>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="end_stream">
                                    <input type="hidden" name="stream_id" value="<?php echo $current_stream['id']; ?>">
                                    <button type="submit" class="btn btn-end" onclick="return confirm('Are you sure you want to end this stream? This will close the live interaction with clients.')">
                                        <i class="fas fa-stop me-2"></i>End Stream
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <!-- Featured Products Section -->
                    <div class="mb-4">
                        <h5 class="mb-3">Featured Products</h5>
                        <div class="featured-products-container">
                            <?php if (!empty($featured_products)): ?>
                                <?php foreach ($featured_products as $product): ?>
                                    <div class="product-card <?php echo $product['is_highlighted'] ? 'highlighted' : ''; ?>">
                                        <div class="d-flex">
                                            <?php if (!empty($product['image_url'])): ?>
                                                <img src="../<?php echo htmlspecialchars($product['image_url']); ?>" 
                                                     alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                                     class="product-image me-3">
                                            <?php else: ?>
                                                <div class="product-image me-3 bg-secondary d-flex align-items-center justify-content-center">
                                                    <i class="fas fa-image text-white"></i>
                                                </div>
                                            <?php endif; ?>
                                            <div class="flex-grow-1">
                                                <h6 class="mb-1"><?php echo htmlspecialchars($product['name']); ?></h6>
                                                <p class="text-muted small mb-2"><?php echo htmlspecialchars(substr($product['description'], 0, 60)); ?>...</p>
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <?php if ($product['special_price']): ?>
                                                            <span class="text-success fw-bold">$<?php echo number_format($product['special_price'], 2); ?></span>
                                                            <small class="text-muted text-decoration-line-through ms-1">$<?php echo number_format($product['price'], 2); ?></small>
                                                        <?php else: ?>
                                                            <span class="text-success fw-bold">$<?php echo number_format($product['price'], 2); ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div>
                                                        <?php if ($product['is_highlighted']): ?>
                                                            <form method="POST" class="d-inline">
                                                                <input type="hidden" name="action" value="unhighlight_product">
                                                                <input type="hidden" name="stream_id" value="<?php echo $current_stream['id']; ?>">
                                                                <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                                                                <button type="submit" class="btn btn-sm btn-warning" title="Unhighlight Product">
                                                                    <i class="fas fa-star"></i>
                                                                </button>
                                                            </form>
                                                        <?php else: ?>
                                                            <form method="POST" class="d-inline">
                                                                <input type="hidden" name="action" value="highlight_product">
                                                                <input type="hidden" name="stream_id" value="<?php echo $current_stream['id']; ?>">
                                                                <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                                                                <button type="submit" class="btn btn-sm btn-outline-warning" title="Highlight Product">
                                                                    <i class="far fa-star"></i>
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="action" value="remove_featured_product">
                                                            <input type="hidden" name="stream_id" value="<?php echo $current_stream['id']; ?>">
                                                            <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-danger ms-1" title="Remove from Stream">
                                                                <i class="fas fa-times"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-box-open fa-2x text-muted mb-3"></i>
                                    <p class="text-muted">No products featured yet. Add products from your catalog below.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Add Products to Stream -->
                    <div>
                        <h5 class="mb-3">Add Products to Stream</h5>
                        <div class="product-list-container">
                            <?php if (!empty($seller_products)): ?>
                                <?php foreach ($seller_products as $product): ?>
                                    <?php 
                                    // Check if product is already featured
                                    $is_featured = false;
                                    $featured_data = null;
                                    foreach ($featured_products as $fp) {
                                        if ($fp['product_id'] == $product['id']) {
                                            $is_featured = true;
                                            $featured_data = $fp;
                                            break;
                                        }
                                    }
                                    ?>
                                    <div class="product-card">
                                        <div class="d-flex">
                                            <?php if (!empty($product['image_url'])): ?>
                                                <img src="../<?php echo htmlspecialchars($product['image_url']); ?>" 
                                                     alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                                     class="product-image me-3">
                                            <?php else: ?>
                                                <div class="product-image me-3 bg-secondary d-flex align-items-center justify-content-center">
                                                    <i class="fas fa-image text-white"></i>
                                                </div>
                                            <?php endif; ?>
                                            <div class="flex-grow-1">
                                                <h6 class="mb-1"><?php echo htmlspecialchars($product['name']); ?></h6>
                                                <p class="text-success fw-bold mb-2">$<?php echo number_format($product['price'], 2); ?></p>
                                                <?php if (!$is_featured): ?>
                                                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#featureProductModal<?php echo $product['id']; ?>">
                                                        <i class="fas fa-plus-circle me-1"></i>Feature Product
                                                    </button>
                                                <?php else: ?>
                                                    <span class="badge bg-success">Featured</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Feature Product Modal -->
                                    <?php if (!$is_featured): ?>
                                        <div class="modal fade" id="featureProductModal<?php echo $product['id']; ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content bg-dark text-white">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Feature Product</h5>
                                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <form method="POST">
                                                        <div class="modal-body">
                                                            <input type="hidden" name="action" value="feature_product">
                                                            <input type="hidden" name="stream_id" value="<?php echo $current_stream['id']; ?>">
                                                            <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                                            
                                                            <div class="text-center mb-3">
                                                                <?php if (!empty($product['image_url'])): ?>
                                                                    <img src="../<?php echo htmlspecialchars($product['image_url']); ?>" 
                                                                         alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                                                         class="img-fluid rounded" style="max-height: 150px;">
                                                                <?php endif; ?>
                                                                <h5 class="mt-2"><?php echo htmlspecialchars($product['name']); ?></h5>
                                                                <p class="text-success fw-bold">$<?php echo number_format($product['price'], 2); ?></p>
                                                            </div>
                                                            
                                                            <div class="mb-3">
                                                                <label class="form-label">Special Price (Optional)</label>
                                                                <input type="number" class="form-control bg-dark text-white" name="special_price" step="0.01" min="0" placeholder="Leave empty for regular price">
                                                                <div class="form-text">Set a special price for this product during the live stream</div>
                                                            </div>
                                                            
                                                            <div class="mb-3">
                                                                <label class="form-label">Discount Percentage (Optional)</label>
                                                                <input type="number" class="form-control bg-dark text-white" name="discount_percentage" step="0.01" min="0" max="100" placeholder="e.g., 15 for 15% off">
                                                                <div class="form-text">Set a discount percentage for this product</div>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                            <button type="submit" class="btn btn-primary">Feature Product</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-exclamation-circle fa-2x text-muted mb-3"></i>
                                    <p class="text-muted">You don't have any active products. <a href="products.php" class="text-white">Add products</a> to your catalog first.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- Start New Stream Form -->
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="stream-controls">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h4 class="mb-0">Start a New Live Stream</h4>
                            <a href="live_stream.php" class="btn btn-return">
                                <i class="fas fa-list me-2"></i>Live Stream List
                            </a>
                        </div>
                        <form method="POST">
                            <input type="hidden" name="action" value="start_stream">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Stream Title *</label>
                                    <input type="text" class="form-control bg-dark text-white" name="title" required placeholder="Enter stream title">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Category</label>
                                    <select class="form-control bg-dark text-white" name="category_id">
                                        <option value="">Select Category</option>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <textarea class="form-control bg-dark text-white" name="description" rows="3" placeholder="Describe what you'll be showcasing in this stream"></textarea>
                            </div>
                            <div class="text-center">
                                <button type="submit" class="btn btn-live">
                                    <i class="fas fa-video me-2"></i>Start Live Stream with Camera
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <div class="text-center mt-5">
                        <h5>How to Use Live Streaming</h5>
                        <div class="row mt-4">
                            <div class="col-md-4 mb-3">
                                <div class="p-3 bg-dark rounded">
                                    <i class="fas fa-video fa-2x text-primary mb-2"></i>
                                    <h6>Start Streaming</h6>
                                    <p class="small text-muted">Click "Start Live Stream" to begin showcasing your products</p>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="p-3 bg-dark rounded">
                                    <i class="fas fa-box fa-2x text-primary mb-2"></i>
                                    <h6>Feature Products</h6>
                                    <p class="small text-muted">Add products from your catalog to showcase during the stream</p>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="p-3 bg-dark rounded">
                                    <i class="fas fa-users fa-2x text-primary mb-2"></i>
                                    <h6>Interact with Clients</h6>
                                    <p class="small text-muted">Clients can watch, chat, and purchase featured products in real-time</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let stream = null;
        let currentFacingMode = 'user';
        let isMuted = false;

        // Start camera when page loads if stream is active
        document.addEventListener('DOMContentLoaded', function() {
            <?php if ($current_stream): ?>
                startCamera();
            <?php endif; ?>
        });

        async function startCamera() {
            try {
                // Request camera access
                stream = await navigator.mediaDevices.getUserMedia({
                    video: { 
                        facingMode: currentFacingMode,
                        width: { ideal: 1280 },
                        height: { ideal: 720 }
                    },
                    audio: true
                });

                const videoElement = document.getElementById('videoElement');
                videoElement.srcObject = stream;

                // Show camera controls
                document.getElementById('startCamera').style.display = 'none';
                document.getElementById('stopCamera').style.display = 'flex';
                document.getElementById('switchCamera').style.display = 'flex';
                document.getElementById('muteAudio').style.display = 'flex';

                console.log('Camera started successfully');
            } catch (error) {
                console.error('Error accessing camera:', error);
                alert('Error accessing camera: ' + error.message);
            }
        }

        function stopCamera() {
            if (stream) {
                stream.getTracks().forEach(track => track.stop());
                stream = null;
                
                const videoElement = document.getElementById('videoElement');
                videoElement.srcObject = null;

                // Hide camera controls
                document.getElementById('startCamera').style.display = 'flex';
                document.getElementById('stopCamera').style.display = 'none';
                document.getElementById('switchCamera').style.display = 'none';
                document.getElementById('muteAudio').style.display = 'none';
            }
        }

        async function switchCamera() {
            if (stream) {
                stopCamera();
                currentFacingMode = currentFacingMode === 'user' ? 'environment' : 'user';
                await startCamera();
            }
        }

        function toggleMute() {
            if (stream) {
                const audioTracks = stream.getAudioTracks();
                audioTracks.forEach(track => {
                    track.enabled = isMuted;
                });
                isMuted = !isMuted;
                
                const muteBtn = document.getElementById('muteAudio');
                muteBtn.innerHTML = isMuted ? '<i class="fas fa-microphone-slash"></i>' : '<i class="fas fa-microphone"></i>';
            }
        }

        // Event listeners
        document.getElementById('startCamera').addEventListener('click', startCamera);
        document.getElementById('stopCamera').addEventListener('click', stopCamera);
        document.getElementById('switchCamera').addEventListener('click', switchCamera);
        document.getElementById('muteAudio').addEventListener('click', toggleMute);

        // Clean up when page unloads
        window.addEventListener('beforeunload', function() {
            stopCamera();
        });
    </script>
</body>
</html>