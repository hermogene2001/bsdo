<?php
session_start();
require_once '../config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

// Initialize messages
$success_message = '';
$error_message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_carousel_item':
                try {
                    $title = trim($_POST['title']);
                    $description = trim($_POST['description']);
                    $link_url = trim($_POST['link_url']);
                    $sort_order = intval($_POST['sort_order']);
                    $is_active = isset($_POST['is_active']) ? 1 : 0;
                    
                    if (empty($title)) {
                        $error_message = "Title is required!";
                        break;
                    }
                    
                    // Handle file upload
                    $image_path = '';
                    if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
                        $upload_dir = '../uploads/carousel/';
                        $file_name = uniqid() . '_' . basename($_FILES['image_file']['name']);
                        $target_file = $upload_dir . $file_name;
                        
                        // Check file type
                        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
                        $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
                        
                        if (!in_array($imageFileType, $allowed_types)) {
                            $error_message = "Only JPG, JPEG, PNG & GIF files are allowed!";
                            break;
                        }
                        
                        // Check file size (5MB max)
                        if ($_FILES['image_file']['size'] > 5000000) {
                            $error_message = "File is too large. Maximum 5MB allowed!";
                            break;
                        }
                        
                        // Upload file
                        if (move_uploaded_file($_FILES['image_file']['tmp_name'], $target_file)) {
                            $image_path = 'uploads/carousel/' . $file_name;
                        } else {
                            $error_message = "Sorry, there was an error uploading your file.";
                            break;
                        }
                    } else if (!empty($_POST['image_url'])) {
                        // Use provided URL if no file uploaded
                        $image_path = $_POST['image_url'];
                    } else {
                        $error_message = "Either upload an image or provide an image URL!";
                        break;
                    }
                    
                    $stmt = $pdo->prepare("INSERT INTO carousel_items (title, description, image_path, link_url, sort_order, is_active) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$title, $description, $image_path, $link_url, $sort_order, $is_active]);
                    
                    $success_message = "Carousel item added successfully!";
                    logAdminActivity("Added carousel item: " . $title);
                } catch (Exception $e) {
                    $error_message = "Failed to add carousel item: " . $e->getMessage();
                }
                break;
                
            case 'update_carousel_item':
                try {
                    $id = intval($_POST['id']);
                    $title = trim($_POST['title']);
                    $description = trim($_POST['description']);
                    $link_url = trim($_POST['link_url']);
                    $sort_order = intval($_POST['sort_order']);
                    $is_active = isset($_POST['is_active']) ? 1 : 0;
                    
                    if (empty($title)) {
                        $error_message = "Title is required!";
                        break;
                    }
                    
                    // Handle file upload
                    $image_path = '';
                    if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
                        $upload_dir = '../uploads/carousel/';
                        $file_name = uniqid() . '_' . basename($_FILES['image_file']['name']);
                        $target_file = $upload_dir . $file_name;
                        
                        // Check file type
                        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
                        $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
                        
                        if (!in_array($imageFileType, $allowed_types)) {
                            $error_message = "Only JPG, JPEG, PNG & GIF files are allowed!";
                            break;
                        }
                        
                        // Check file size (5MB max)
                        if ($_FILES['image_file']['size'] > 5000000) {
                            $error_message = "File is too large. Maximum 5MB allowed!";
                            break;
                        }
                        
                        // Upload file
                        if (move_uploaded_file($_FILES['image_file']['tmp_name'], $target_file)) {
                            $image_path = 'uploads/carousel/' . $file_name;
                        } else {
                            $error_message = "Sorry, there was an error uploading your file.";
                            break;
                        }
                    } else if (!empty($_POST['image_url'])) {
                        // Use provided URL if no file uploaded
                        $image_path = $_POST['image_url'];
                    }
                    
                    // Get current image path if no new image provided
                    if (empty($image_path)) {
                        $stmt = $pdo->prepare("SELECT image_path FROM carousel_items WHERE id = ?");
                        $stmt->execute([$id]);
                        $current = $stmt->fetch(PDO::FETCH_ASSOC);
                        $image_path = $current['image_path'];
                    }
                    
                    $stmt = $pdo->prepare("UPDATE carousel_items SET title = ?, description = ?, image_path = ?, link_url = ?, sort_order = ?, is_active = ? WHERE id = ?");
                    $stmt->execute([$title, $description, $image_path, $link_url, $sort_order, $is_active, $id]);
                    
                    $success_message = "Carousel item updated successfully!";
                    logAdminActivity("Updated carousel item: " . $title);
                } catch (Exception $e) {
                    $error_message = "Failed to update carousel item: " . $e->getMessage();
                }
                break;
                
            case 'delete_carousel_item':
                try {
                    $id = intval($_POST['id']);
                    
                    // Delete image file if it's in the uploads directory
                    $stmt = $pdo->prepare("SELECT image_path FROM carousel_items WHERE id = ?");
                    $stmt->execute([$id]);
                    $item = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($item && strpos($item['image_path'], 'uploads/carousel/') === 0) {
                        $file_path = '../' . $item['image_path'];
                        if (file_exists($file_path)) {
                            unlink($file_path);
                        }
                    }
                    
                    $stmt = $pdo->prepare("DELETE FROM carousel_items WHERE id = ?");
                    $stmt->execute([$id]);
                    
                    $success_message = "Carousel item deleted successfully!";
                    logAdminActivity("Deleted carousel item ID: " . $id);
                } catch (Exception $e) {
                    $error_message = "Failed to delete carousel item: " . $e->getMessage();
                }
                break;
                
            case 'update_sort_order':
                try {
                    $ids = $_POST['ids'];
                    foreach ($ids as $index => $id) {
                        $stmt = $pdo->prepare("UPDATE carousel_items SET sort_order = ? WHERE id = ?");
                        $stmt->execute([$index, $id]);
                    }
                    
                    $success_message = "Sort order updated successfully!";
                    logAdminActivity("Updated carousel sort order");
                } catch (Exception $e) {
                    $error_message = "Failed to update sort order: " . $e->getMessage();
                }
                break;
        }
    }
}

// Get carousel items
$stmt = $pdo->prepare("SELECT * FROM carousel_items ORDER BY sort_order ASC, id ASC");
$stmt->execute();
$carousel_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Log admin activity
logAdminActivity("Accessed carousel management");

function logAdminActivity($activity) {
    global $pdo;
    if (isset($_SESSION['user_id'])) {
        $stmt = $pdo->prepare("INSERT INTO admin_activities (admin_id, activity, ip_address) VALUES (?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], $activity, $_SERVER['REMOTE_ADDR']]);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carousel Management - BSDO Sale Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #1cc88a;
            --warning-color: #f6c23e;
            --danger-color: #e74a3b;
            --dark-color: #2e3a59;
            --light-color: #f8f9fc;
            --sidebar-width: 250px;
        }
        
        body {
            font-family: 'Nunito', sans-serif;
            background-color: #f8f9fc;
            overflow-x: hidden;
        }
        
        #wrapper {
            display: flex;
        }
        
        #sidebar {
            min-width: var(--sidebar-width);
            max-width: var(--sidebar-width);
            background: var(--dark-color);
            color: #fff;
            transition: all 0.3s;
            min-height: 100vh;
        }
        
        #sidebar.active {
            margin-left: -var(--sidebar-width);
        }
        
        #sidebar .sidebar-header {
            padding: 20px;
            background: rgba(0, 0, 0, 0.2);
        }
        
        #sidebar ul.components {
            padding: 20px 0;
        }
        
        #sidebar ul li a {
            padding: 15px 20px;
            font-size: 1.1em;
            display: block;
            color: #fff;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        #sidebar ul li a:hover {
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
        }
        
        #sidebar ul li.active > a {
            background: var(--primary-color);
            color: #fff;
        }
        
        #sidebar ul li a i {
            margin-right: 10px;
        }
        
        #content {
            width: 100%;
            padding: 20px;
            min-height: 100vh;
            transition: all 0.3s;
        }
        
        .navbar {
            background-color: white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            margin-bottom: 20px;
        }
        
        .card-header {
            background-color: #f8f9fc;
            border-bottom: 1px solid #e3e6f0;
            padding: 15px 20px;
            font-weight: 700;
        }
        
        .sidebar-toggler {
            cursor: pointer;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        
        .carousel-item-card {
            border-left: 4px solid var(--primary-color);
        }
        
        .carousel-image {
            max-width: 200px;
            height: 100px;
            object-fit: cover;
            border-radius: 5px;
        }
        
        .sortable-item {
            cursor: move;
        }
        
        .sortable-item:hover {
            background-color: rgba(78, 115, 223, 0.05);
        }
        
        .drag-handle {
            cursor: move;
            color: #999;
        }
        
        .drag-handle:hover {
            color: var(--primary-color);
        }
    </style>
</head>
<body>
    <div id="wrapper">
        <!-- Sidebar -->
        <nav id="sidebar">
            <div class="sidebar-header">
                <h3><i class="fas fa-shopping-bag me-2"></i>BSDO Admin</h3>
            </div>
            
            <ul class="list-unstyled components">
                <li>
                    <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                </li>
                <li>
                    <a href="users.php"><i class="fas fa-users"></i> Users</a>
                </li>
                <li>
                    <a href="products.php"><i class="fas fa-box"></i> Products</a>
                </li>
                <li>
                    <a href="categories.php"><i class="fas fa-tags"></i> Categories</a>
                </li>
                <li>
                    <a href="sellers.php"><i class="fas fa-store"></i> Sellers</a>
                </li>
                <li>
                    <a href="orders.php"><i class="fas fa-shopping-cart"></i> Orders</a>
                </li>
                <li>
                    <a href="analytics.php"><i class="fas fa-chart-bar"></i> Analytics</a>
                </li>
                <li class="active">
                    <a href="carousel.php"><i class="fas fa-images"></i> Carousel</a>
                </li>
                <li>
                    <a href="payment_slips.php"><i class="fas fa-money-check"></i> Payment Slips</a>
                </li>
                <li>
                    <a href="payment_channels.php"><i class="fas fa-money-bill-wave"></i> Payment Channels</a>
                </li>
                <li>
                    <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
                </li>
                <li>
                    <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </li>
            </ul>
        </nav>

        <!-- Content -->
        <div id="content">
            <!-- Top Navbar -->
            <nav class="navbar navbar-expand-lg navbar-light bg-white mb-4">
                <div class="container-fluid">
                    <button type="button" id="sidebarCollapse" class="btn btn-primary sidebar-toggler">
                        <i class="fas fa-bars"></i>
                    </button>
                    
                    <div class="d-flex align-items-center">
                        <div class="dropdown">
                            <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown">
                                <div class="user-avatar me-2">A</div>
                                <span>Admin User</span>
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="settings.php#profile"><i class="fas fa-user me-2"></i>Profile</a></li>
                                <li><a class="dropdown-item" href="settings.php"><i class="fas fa-cog me-2"></i>Settings</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </nav>

            <!-- Page Heading -->
            <div class="d-sm-flex align-items-center justify-content-between mb-4">
                <h1 class="h3 mb-0 text-gray-800">Carousel Management</h1>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCarouselModal">
                    <i class="fas fa-plus fa-sm text-white-50"></i> Add New Item
                </button>
            </div>

            <!-- Alerts -->
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Carousel Items List -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="fas fa-images me-2"></i>Carousel Items</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($carousel_items)): ?>
                        <form method="POST" id="sortForm">
                            <input type="hidden" name="action" value="update_sort_order">
                            <div class="sortable-list">
                                <?php foreach ($carousel_items as $index => $item): ?>
                                    <div class="sortable-item d-flex align-items-center p-3 mb-3 bg-white rounded shadow-sm border sortable-item-<?php echo $item['id']; ?>">
                                        <div class="drag-handle me-3">
                                            <i class="fas fa-grip-lines"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <div class="d-flex">
                                                <div class="me-3">
                                                    <?php if (!empty($item['image_path'])): ?>
                                                        <img src="../<?php echo htmlspecialchars($item['image_path']); ?>" alt="<?php echo htmlspecialchars($item['title']); ?>" class="carousel-image">
                                                    <?php else: ?>
                                                        <div class="bg-light d-flex align-items-center justify-content-center" style="width: 200px; height: 100px;">
                                                            <i class="fas fa-image fa-2x text-muted"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($item['title']); ?></h6>
                                                    <p class="text-muted small mb-2"><?php echo htmlspecialchars(substr($item['description'], 0, 100)) . (strlen($item['description']) > 100 ? '...' : ''); ?></p>
                                                    <div class="d-flex align-items-center">
                                                        <small class="text-muted me-3">
                                                            <i class="fas fa-link me-1"></i>
                                                            <?php echo !empty($item['link_url']) ? htmlspecialchars($item['link_url']) : 'No link'; ?>
                                                        </small>
                                                        <small class="text-muted me-3">
                                                            <i class="fas fa-sort me-1"></i>
                                                            Order: <?php echo $item['sort_order']; ?>
                                                        </small>
                                                        <small class="text-<?php echo $item['is_active'] ? 'success' : 'danger'; ?>">
                                                            <i class="fas fa-<?php echo $item['is_active'] ? 'check-circle' : 'times-circle'; ?> me-1"></i>
                                                            <?php echo $item['is_active'] ? 'Active' : 'Inactive'; ?>
                                                        </small>
                                                    </div>
                                                </div>
                                                <div class="d-flex align-items-center">
                                                    <button type="button" class="btn btn-sm btn-outline-primary me-2" 
                                                            onclick="editCarouselItem(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars($item['title'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($item['description'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($item['image_path'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($item['link_url'], ENT_QUOTES); ?>', <?php echo $item['sort_order']; ?>, <?php echo $item['is_active']; ?>)">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this carousel item?');">
                                                        <input type="hidden" name="action" value="delete_carousel_item">
                                                        <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <input type="hidden" name="ids[]" value="<?php echo $item['id']; ?>">
                                <?php endforeach; ?>
                            </div>
                            <button type="submit" class="btn btn-success mt-3">
                                <i class="fas fa-save me-2"></i>Save Sort Order
                            </button>
                        </form>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-images fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No carousel items found</h5>
                            <p class="text-muted">Add your first carousel item to get started.</p>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCarouselModal">
                                <i class="fas fa-plus me-2"></i>Add New Item
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Carousel Item Modal -->
    <div class="modal fade" id="addCarouselModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-header">
                        <h5 class="modal-title">Add New Carousel Item</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_carousel_item">
                        <div class="mb-3">
                            <label class="form-label">Title *</label>
                            <input type="text" class="form-control" name="title" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Upload Image</label>
                            <input type="file" class="form-control" name="image_file" accept="image/*">
                            <div class="form-text">Upload an image file (JPG, PNG, GIF) - Max 5MB</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">OR Image URL</label>
                            <input type="text" class="form-control" name="image_url" placeholder="https://example.com/image.jpg">
                            <div class="form-text">Alternative to file upload - enter full image URL</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Link URL</label>
                            <input type="text" class="form-control" name="link_url" placeholder="https://example.com/page">
                            <div class="form-text">Where users will be redirected when clicking the carousel item</div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Sort Order</label>
                                <input type="number" class="form-control" name="sort_order" value="0" min="0">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Status</label>
                                <div class="form-check form-switch mt-2">
                                    <input class="form-check-input" type="checkbox" name="is_active" checked>
                                    <label class="form-check-label">Active</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Carousel Item</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Carousel Item Modal -->
    <div class="modal fade" id="editCarouselModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Carousel Item</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_carousel_item">
                        <input type="hidden" name="id" id="edit_id">
                        <div class="mb-3">
                            <label class="form-label">Title *</label>
                            <input type="text" class="form-control" name="title" id="edit_title" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" id="edit_description" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Current Image</label>
                            <div id="current_image_container"></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Upload New Image</label>
                            <input type="file" class="form-control" name="image_file" accept="image/*">
                            <div class="form-text">Upload a new image file (JPG, PNG, GIF) - Max 5MB</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">OR Image URL</label>
                            <input type="url" class="form-control" name="image_url" id="edit_image_url" placeholder="https://example.com/image.jpg">
                            <div class="form-text">Alternative to file upload - enter full image URL</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Link URL</label>
                            <input type="text" class="form-control" name="link_url" id="edit_link_url" placeholder="https://example.com/page">
                            <div class="form-text">Where users will be redirected when clicking the carousel item</div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Sort Order</label>
                                <input type="number" class="form-control" name="sort_order" id="edit_sort_order" min="0">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Status</label>
                                <div class="form-check form-switch mt-2">
                                    <input class="form-check-input" type="checkbox" name="is_active" id="edit_is_active">
                                    <label class="form-check-label">Active</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Carousel Item</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sidebar Toggle
        document.getElementById('sidebarCollapse').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
        });

        // Edit Carousel Item Function
        function editCarouselItem(id, title, description, imagePath, linkUrl, sortOrder, isActive) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_title').value = title;
            document.getElementById('edit_description').value = description;
            document.getElementById('edit_image_url').value = imagePath;
            document.getElementById('edit_link_url').value = linkUrl;
            document.getElementById('edit_sort_order').value = sortOrder;
            document.getElementById('edit_is_active').checked = isActive == 1;
            
            // Show current image
            var imageContainer = document.getElementById('current_image_container');
            if (imagePath) {
                imageContainer.innerHTML = '<img src="../' + imagePath + '" alt="Current Image" class="img-fluid" style="max-height: 150px;">';
            } else {
                imageContainer.innerHTML = '<p class="text-muted">No image currently set</p>';
            }
            
            var editModal = new bootstrap.Modal(document.getElementById('editCarouselModal'));
            editModal.show();
        }

        // Auto-dismiss alerts
        document.addEventListener('DOMContentLoaded', function() {
            var alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    var bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 5000);
            });
        });
    </script>
</body>
</html>