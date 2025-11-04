<?php
session_start();
require_once 'config.php';

// Check if user is logged in
$is_logged_in = isset($_SESSION['user_id']);
$user_name = $_SESSION['user_name'] ?? '';
$user_role = $_SESSION['user_role'] ?? '';
$user_id = $_SESSION['user_id'] ?? null;

// Get filter parameters
$filter = $_GET['filter'] ?? 'all'; // all, live, upcoming, ended
$category = $_GET['category'] ?? 'all';

// Build query for live streams - FIXED VERSION
$query = "
    SELECT ls.*, u.store_name, u.first_name, u.last_name, 
           COUNT(lsv.id) as current_viewers,
           c.name as category_name
    FROM live_streams ls 
    JOIN users u ON ls.seller_id = u.id 
    LEFT JOIN live_stream_viewers lsv ON ls.id = lsv.stream_id 
    LEFT JOIN categories c ON ls.category_id = c.id
";

$params = [];
$conditions = [];

// Apply filters - FIXED conditions
if ($filter === 'live') {
    $conditions[] = "ls.is_live = 1";
} elseif ($filter === 'upcoming') {
    $conditions[] = "ls.is_live = 0 AND (ls.scheduled_at IS NULL OR ls.scheduled_at > NOW())";
} elseif ($filter === 'ended') {
    $conditions[] = "ls.is_live = 0 AND ls.ended_at IS NOT NULL";
}

if ($category !== 'all' && $category !== '') {
    $conditions[] = "c.name = ?";
    $params[] = $category;
}

if (!empty($conditions)) {
    $query .= " WHERE " . implode(" AND ", $conditions);
}

$query .= " GROUP BY ls.id ORDER BY ls.is_live DESC, ls.started_at DESC, ls.created_at DESC";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $streams = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // If there's still an error, use a simpler query
    error_log("Stream query error: " . $e->getMessage());
    $streams = [];
}

// Get categories for filter - SIMPLIFIED QUERY
try {
    $categories_stmt = $pdo->prepare("SELECT id, name FROM categories WHERE status = 'active'");
    $categories_stmt->execute();
    $categories = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $categories = [];
}

// Get statistics - FIXED QUERY
try {
    $stats_stmt = $pdo->prepare("
        SELECT 
            (SELECT COUNT(*) FROM live_streams WHERE is_live = 1) as live_now,
            (SELECT COUNT(*) FROM live_streams WHERE is_live = 0 AND ended_at IS NULL) as upcoming,
            (SELECT COUNT(*) FROM live_streams WHERE ended_at IS NOT NULL) as total_ended,
            (SELECT COALESCE(SUM(viewer_count), 0) FROM live_streams WHERE is_live = 1) as total_viewers
    ");
    $stats_stmt->execute();
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $stats = [
        'live_now' => 0,
        'upcoming' => 0,
        'total_ended' => 0,
        'total_viewers' => 0
    ];
}

// If no streams found with categories, try without category join
if (empty($streams)) {
    $simple_query = "
        SELECT ls.*, u.store_name, u.first_name, u.last_name, 
               COUNT(lsv.id) as current_viewers
        FROM live_streams ls 
        JOIN users u ON ls.seller_id = u.id 
        LEFT JOIN live_stream_viewers lsv ON ls.id = lsv.stream_id 
        GROUP BY ls.id 
        ORDER BY ls.is_live DESC, ls.started_at DESC
    ";
    
    try {
        $simple_stmt = $pdo->prepare($simple_query);
        $simple_stmt->execute();
        $streams = $simple_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $streams = [];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Streams - BSDO Sale</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #1cc88a;
            --live-color: #e74a3b;
            --upcoming-color: #f6c23e;
            --ended-color: #6c757d;
            --dark-color: #2e3a59;
            --light-color: #f8f9fc;
        }
        
        .hero-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 100px 0 60px;
            margin-top: 76px;
        }
        
        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            border: none;
            transition: transform 0.3s;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
        }
        
        .stats-number {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .stats-label {
            font-size: 0.9rem;
            color: #6c757d;
        }
        
        .stream-card {
            border: none;
            border-radius: 15px;
            overflow: hidden;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            height: 100%;
        }
        
        .stream-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
        }
        
        .stream-thumbnail {
            height: 200px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
            position: relative;
        }
        
        .stream-status {
            position: absolute;
            top: 10px;
            left: 10px;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 0.7rem;
            font-weight: bold;
            z-index: 10;
        }
        
        .status-live {
            background: var(--live-color);
            color: white;
            animation: pulse 2s infinite;
        }
        
        .status-upcoming {
            background: var(--upcoming-color);
            color: white;
        }
        
        .status-ended {
            background: var(--ended-color);
            color: white;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.7; }
            100% { opacity: 1; }
        }
        
        .viewer-count {
            position: absolute;
            bottom: 10px;
            right: 10px;
            background: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
        }
        
        .seller-info {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .seller-avatar {
            width: 40px;
            height: 40px;
            background: var(--primary-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            margin-right: 10px;
        }
        
        .filter-section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            margin-bottom: 30px;
        }
        
        .btn-filter {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 10px 20px;
            margin: 5px;
            transition: all 0.3s;
        }
        
        .btn-filter.active {
            border-color: var(--primary-color);
            background: rgba(78, 115, 223, 0.1);
            color: var(--primary-color);
        }
        
        .schedule-badge {
            background: var(--upcoming-color);
            color: white;
            padding: 4px 8px;
            border-radius: 10px;
            font-size: 0.7rem;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        
        .navbar {
            background-color: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .alert-info {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 15px;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light fixed-top bg-white shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">
                <i class="fas fa-shopping-bag me-2 text-primary"></i>BSDO SALE
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="products.php">Products</a></li>
                    <li class="nav-item"><a class="nav-link active" href="live_streams.php">Live Streams</a></li>
                    <li class="nav-item"><a class="nav-link" href="products.php?type=regular">Regular</a></li>
                    <li class="nav-item"><a class="nav-link" href="products.php?type=rental">Rental</a></li>
                </ul>
                
                <div class="d-flex align-items-center">
                    <?php if ($is_logged_in): ?>
                        <!-- Logged In User Menu -->
                        <div class="dropdown">
                            <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown">
                                <div class="user-avatar me-2">
                                    <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                                </div>
                                <span class="me-2"><?php echo htmlspecialchars(explode(' ', $user_name)[0]); ?></span>
                            </a>
                            <ul class="dropdown-menu">
                                <?php if ($user_role === 'seller'): ?>
                                    <li><a class="dropdown-item" href="seller/dashboard.php"><i class="fas fa-tachometer-alt me-2"></i>Seller Dashboard</a></li>
                                    <li><a class="dropdown-item" href="seller/live_stream.php"><i class="fas fa-video me-2"></i>Go Live</a></li>
                                <?php elseif ($user_role === 'admin'): ?>
                                    <li><a class="dropdown-item" href="admin/dashboard.php"><i class="fas fa-cog me-2"></i>Admin Panel</a></li>
                                <?php else: ?>
                                    <li><a class="dropdown-item" href="myAccount.php"><i class="fas fa-user me-2"></i>My Account</a></li>
                                    <li><a class="dropdown-item" href="inquiries.php"><i class="fas fa-comments me-2"></i>My Inquiries</a></li>
                                <?php endif; ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <!-- Guest User Menu -->
                        <a href="login.php" class="btn btn-outline-primary me-2">Login</a>
                        <a href="register.php" class="btn btn-primary">Register</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h1 class="display-4 fw-bold mb-3">Live Shopping Streams</h1>
                    <p class="lead mb-4">Watch live product demonstrations, interact with sellers, and shop in real-time with exclusive live stream deals.</p>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Looking for products from live sellers?</strong> 
                        <a href="live.php" class="text-white fw-bold text-decoration-underline">Visit our new Live Shopping page</a> 
                        to see products from sellers who are currently live streaming.
                    </div>
                    <?php if ($is_logged_in && $user_role === 'seller'): ?>
                        <a href="seller/live_stream.php" class="btn btn-light btn-lg">
                            <i class="fas fa-video me-2"></i>Start Your Live Stream
                        </a>
                    <?php endif; ?>
                </div>
                <div class="col-lg-4 text-center">
                    <i class="fas fa-broadcast-tower fa-10x opacity-25"></i>
                </div>
            </div>
        </div>
    </section>

    <!-- Statistics Section -->
    <section class="py-5 bg-light">
        <div class="container">
            <div class="row g-4">
                <div class="col-md-3">
                    <div class="stats-card">
                        <div class="stats-number text-danger"><?php echo $stats['live_now']; ?></div>
                        <div class="stats-label">Live Now</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <div class="stats-number text-warning"><?php echo $stats['upcoming']; ?></div>
                        <div class="stats-label">Upcoming</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <div class="stats-number text-primary"><?php echo $stats['total_viewers']; ?></div>
                        <div class="stats-label">Total Viewers</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <div class="stats-number text-success"><?php echo $stats['total_ended']; ?></div>
                        <div class="stats-label">Completed Streams</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Filters Section -->
    <section class="py-4">
        <div class="container">
            <div class="filter-section">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h4 class="mb-3">Filter Streams</h4>
                        <div class="btn-group flex-wrap">
                            <a href="?filter=all&category=<?php echo $category; ?>" 
                               class="btn btn-filter <?php echo $filter === 'all' ? 'active' : ''; ?>">
                                All Streams
                            </a>
                            <a href="?filter=live&category=<?php echo $category; ?>" 
                               class="btn btn-filter <?php echo $filter === 'live' ? 'active' : ''; ?>">
                                <i class="fas fa-circle text-danger me-1"></i> Live Now
                            </a>
                            <a href="?filter=upcoming&category=<?php echo $category; ?>" 
                               class="btn btn-filter <?php echo $filter === 'upcoming' ? 'active' : ''; ?>">
                                <i class="fas fa-clock text-warning me-1"></i> Upcoming
                            </a>
                            <a href="?filter=ended&category=<?php echo $category; ?>" 
                               class="btn btn-filter <?php echo $filter === 'ended' ? 'active' : ''; ?>">
                                <i class="fas fa-check-circle text-success me-1"></i> Completed
                            </a>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Filter by Category</label>
                            <select class="form-select" onchange="window.location.href='?filter=<?php echo $filter; ?>&category='+this.value">
                                <option value="all" <?php echo $category === 'all' ? 'selected' : ''; ?>>All Categories</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo htmlspecialchars($cat['name']); ?>" 
                                        <?php echo $category === $cat['name'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Streams Grid -->
    <section class="py-5">
        <div class="container">
            <?php if (!empty($streams)): ?>
                <div class="row g-4">
                    <?php foreach ($streams as $stream): ?>
                        <div class="col-lg-4 col-md-6">
                            <div class="stream-card">
                                <div class="stream-thumbnail">
                                    <!-- Status Badge -->
                                    <div class="stream-status <?php 
                                        echo $stream['is_live'] ? 'status-live' : 
                                            ($stream['ended_at'] ? 'status-ended' : 'status-upcoming'); 
                                    ?>">
                                        <?php 
                                        if ($stream['is_live']) {
                                            echo '<i class="fas fa-circle me-1"></i>LIVE';
                                        } elseif ($stream['ended_at']) {
                                            echo '<i class="fas fa-check-circle me-1"></i>ENDED';
                                        } else {
                                            echo '<i class="fas fa-clock me-1"></i>UPCOMING';
                                        }
                                        ?>
                                    </div>
                                    
                                    <!-- Thumbnail Content -->
                                    <?php if (!empty($stream['thumbnail_url'])): ?>
                                        <img src="<?php echo htmlspecialchars($stream['thumbnail_url']); ?>" 
                                             alt="<?php echo htmlspecialchars($stream['title']); ?>" 
                                             class="w-100 h-100" style="object-fit: cover;">
                                    <?php else: ?>
                                        <i class="fas fa-video"></i>
                                    <?php endif; ?>
                                    
                                    <!-- Viewer Count -->
                                    <?php if ($stream['is_live']): ?>
                                        <div class="viewer-count">
                                            <i class="fas fa-eye me-1"></i><?php echo $stream['current_viewers'] ?? $stream['viewer_count'] ?? 0; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="card-body">
                                    <!-- Seller Info -->
                                    <div class="seller-info">
                                        <div class="seller-avatar">
                                            <?php echo strtoupper(substr($stream['first_name'] ?? 'S', 0, 1)); ?>
                                        </div>
                                        <div>
                                            <h6 class="mb-0"><?php echo htmlspecialchars($stream['store_name'] ?? 'Seller Store'); ?></h6>
                                            <small class="text-muted"><?php echo htmlspecialchars(($stream['first_name'] ?? 'Seller') . ' ' . ($stream['last_name'] ?? '')); ?></small>
                                        </div>
                                    </div>
                                    
                                    <!-- Stream Info -->
                                    <h6 class="card-title"><?php echo htmlspecialchars($stream['title'] ?? 'Live Stream'); ?></h6>
                                    <p class="card-text text-muted small"><?php echo htmlspecialchars($stream['description'] ?? 'Live product demonstration and Q&A session'); ?></p>
                                    
                                    <!-- Stream Meta -->
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <?php if (!empty($stream['category_name'])): ?>
                                            <span class="badge bg-light text-dark">
                                                <i class="fas fa-tag me-1"></i><?php echo htmlspecialchars($stream['category_name']); ?>
                                            </span>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($stream['scheduled_at']) && strtotime($stream['scheduled_at']) > time()): ?>
                                            <span class="schedule-badge">
                                                <i class="fas fa-calendar me-1"></i>
                                                <?php echo date('M j, g:i A', strtotime($stream['scheduled_at'])); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Action Button -->
                                    <div class="d-grid">
                                        <?php if ($stream['is_live']): ?>
                                            <a href="watch_stream.php?stream_id=<?php echo $stream['id']; ?>" 
                                               class="btn btn-danger btn-sm">
                                                <i class="fas fa-play me-2"></i>Join Live Stream
                                            </a>
                                        <?php elseif (empty($stream['ended_at']) && empty($stream['is_live'])): ?>
                                            <button class="btn btn-warning btn-sm" onclick="setReminder(<?php echo $stream['id']; ?>)">
                                                <i class="fas fa-bell me-2"></i>Set Reminder
                                            </button>
                                        <?php else: ?>
                                            <button class="btn btn-outline-secondary btn-sm" disabled>
                                                <i class="fas fa-check me-2"></i>Stream Ended
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <!-- Empty State -->
                <div class="empty-state">
                    <i class="fas fa-broadcast-tower"></i>
                    <h3>No Streams Found</h3>
                    <p class="mb-4">
                        <?php if ($filter === 'live'): ?>
                            There are no live streams at the moment. Check back later or browse upcoming streams.
                        <?php elseif ($filter === 'upcoming'): ?>
                            No upcoming streams scheduled. Sellers might be planning new streams soon.
                        <?php elseif ($filter === 'ended'): ?>
                            No completed streams found in the selected category.
                        <?php else: ?>
                            No streams found. <?php echo ($is_logged_in && $user_role === 'seller') ? 'Be the first to start a stream!' : 'Check back later for live shopping streams.'; ?>
                        <?php endif; ?>
                    </p>
                    <?php if ($is_logged_in && $user_role === 'seller'): ?>
                        <a href="seller/live_stream.php" class="btn btn-primary btn-lg">
                            <i class="fas fa-video me-2"></i>Start Your First Stream
                        </a>
                    <?php else: ?>
                        <a href="?filter=all&category=all" class="btn btn-outline-primary">Show All Streams</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- How It Works Section -->
    <section class="py-5 bg-light">
        <div class="container">
            <h2 class="text-center mb-5">How Live Shopping Works</h2>
            <div class="row g-4">
                <div class="col-md-4 text-center">
                    <div class="feature-icon mb-3">
                        <i class="fas fa-video fa-3x text-primary"></i>
                    </div>
                    <h4>Watch Live</h4>
                    <p class="text-muted">Join live streams to see products in action with real-time demonstrations.</p>
                </div>
                <div class="col-md-4 text-center">
                    <div class="feature-icon mb-3">
                        <i class="fas fa-comments fa-3x text-primary"></i>
                    </div>
                    <h4>Interact Live</h4>
                    <p class="text-muted">Ask questions and get immediate responses from sellers during the stream.</p>
                </div>
                <div class="col-md-4 text-center">
                    <div class="feature-icon mb-3">
                        <i class="fas fa-shopping-cart fa-3x text-primary"></i>
                    </div>
                    <h4>Shop Instantly</h4>
                    <p class="text-muted">Purchase featured products directly during the live stream with special deals.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-dark text-white py-5">
        <div class="container">
            <div class="row">
                <div class="col-lg-4 mb-4">
                    <h5 class="mb-4"><i class="fas fa-shopping-bag me-2"></i>BSDO SALE</h5>
                    <p>Experience the future of e-commerce with live shopping streams and real-time interactions.</p>
                </div>
                <div class="col-lg-2 col-md-4 mb-4">
                    <h5 class="mb-4">Quick Links</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="index.php" class="text-decoration-none text-light">Home</a></li>
                        <li class="mb-2"><a href="products.php" class="text-decoration-none text-light">Products</a></li>
                        <li class="mb-2"><a href="live.php" class="text-decoration-none text-light">Live Shopping</a></li>
                        <li class="mb-2"><a href="live_streams.php" class="text-decoration-none text-light">All Streams</a></li>
                    </ul>
                </div>
                <div class="col-lg-3 col-md-4 mb-4">
                    <h5 class="mb-4">Stream Categories</h5>
                    <ul class="list-unstyled">
                        <?php foreach (array_slice($categories, 0, 5) as $cat): ?>
                            <li class="mb-2">
                                <a href="?filter=all&category=<?php echo urlencode($cat['name']); ?>" 
                                   class="text-decoration-none text-light">
                                    <i class="fas fa-tag me-2"></i><?php echo htmlspecialchars($cat['name']); ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="col-lg-3 col-md-4 mb-4">
                    <h5 class="mb-4">Get Notified</h5>
                    <p>Get notified when your favorite sellers go live</p>
                    <form>
                        <div class="input-group mb-3">
                            <input type="email" class="form-control" placeholder="Your email address">
                            <button class="btn btn-primary" type="button">Subscribe</button>
                        </div>
                    </form>
                </div>
            </div>
            <hr class="my-4">
            <div class="text-center">
                <p>&copy; 2024 BSDO Sale. All rights reserved. | Developed by <a href="mailto:Hermogene2001@gmail.com" class="text-decoration-none text-light">HermogenesTech</a></p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function setReminder(streamId) {
            if (confirm('Would you like to set a reminder for this stream?')) {
                // Add reminder logic here
                alert('Reminder feature coming soon!');
            }
        }

        // Auto-refresh for live streams
        function refreshLiveStreams() {
            if (window.location.search.includes('filter=live')) {
                setTimeout(() => {
                    window.location.reload();
                }, 30000); // Refresh every 30 seconds for live streams
            }
        }

        // Start auto-refresh if on live filter
        if (window.location.search.includes('filter=live')) {
            refreshLiveStreams();
        }
    </script>
</body>
</html>