<?php
session_start();
require_once '../config.php';

// Check if user is logged in and is seller
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'seller') {
    header('Location: ../login.php');
    exit();
}

// Initialize messages
$success_message = '';
$error_message = '';

// Get seller ID
$seller_id = $_SESSION['user_id'];

// Get payment verification rate setting
$stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'payment_verification_rate'");
$stmt->execute();
$payment_verification_rate = floatval($stmt->fetch(PDO::FETCH_ASSOC)['setting_value'] ?? 0.50);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_product':
                $name = trim($_POST['name']);
                $description = trim($_POST['description']);
                $price = floatval($_POST['price']);
                $stock = intval($_POST['stock']);
                $category_id = intval($_POST['category_id']);
                $payment_channel_id = intval($_POST['payment_channel_id']);
                $address = trim($_POST['address']);
                $city = trim($_POST['city']);
                $state = trim($_POST['state']);
                $country = trim($_POST['country']);
                $postal_code = trim($_POST['postal_code']);
                
                // Validate payment channel
                $channel_stmt = $pdo->prepare("SELECT id FROM payment_channels WHERE id = ? AND is_active = 1");
                $channel_stmt->execute([$payment_channel_id]);
                if (!$channel_stmt->fetch()) {
                    $error_message = "Invalid or inactive payment channel selected.";
                    break;
                }
                
                try {
                    // Handle image upload
                    $image_url = '';
                    $image_gallery = null;
                    
                    // Handle single image upload
                    if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
                        $upload_dir = '../uploads/products/';
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0755, true);
                        }
                        
                        $file_extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
                        $filename = 'product_' . time() . '_' . uniqid() . '.' . $file_extension;
                        $target_path = $upload_dir . $filename;
                        
                        if (move_uploaded_file($_FILES['image']['tmp_name'], $target_path)) {
                            $image_url = 'uploads/products/' . $filename;
                        }
                    }
                    
                    // Handle multiple image uploads
                    if (isset($_FILES['gallery_images']) && is_array($_FILES['gallery_images']['name']) && count($_FILES['gallery_images']['name']) > 0) {
                        $gallery_images = [];
                        $upload_dir = '../uploads/products/';
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0755, true);
                        }
                        
                        for ($i = 0; $i < count($_FILES['gallery_images']['name']); $i++) {
                            if ($_FILES['gallery_images']['error'][$i] === 0) {
                                $file_extension = pathinfo($_FILES['gallery_images']['name'][$i], PATHINFO_EXTENSION);
                                $filename = 'gallery_' . time() . '_' . uniqid() . '_' . $i . '.' . $file_extension;
                                $target_path = $upload_dir . $filename;
                                
                                if (move_uploaded_file($_FILES['gallery_images']['tmp_name'][$i], $target_path)) {
                                    $gallery_images[] = 'uploads/products/' . $filename;
                                }
                            }
                        }
                        
                        if (!empty($gallery_images)) {
                            $image_gallery = json_encode($gallery_images);
                        }
                    }
                    
                    $stmt = $pdo->prepare("INSERT INTO products (seller_id, name, description, price, stock, category_id, payment_channel_id, image_url, image_gallery, address, city, state, country, postal_code, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
                    $stmt->execute([$seller_id, $name, $description, $price, $stock, $category_id, $payment_channel_id, $image_url, $image_gallery, $address, $city, $state, $country, $postal_code]);
                    
                    // Check if this seller was referred by another seller and award 0.5% referral bonus
                    try {
                        $pdo->beginTransaction();
                        
                        // Check if this seller was referred by another seller
                        $referral_stmt = $pdo->prepare("SELECT inviter_id FROM referrals WHERE invitee_id = ? AND invitee_role = 'seller' LIMIT 1");
                        $referral_stmt->execute([$seller_id]);
                        $referral_result = $referral_stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($referral_result) {
                            $inviter_id = $referral_result['inviter_id'];
                            $referral_bonus = $price * 0.005; // 0.5% of product price
                
                            // Award the referral bonus to the inviter
                            $bonus_stmt = $pdo->prepare("INSERT INTO user_wallets (user_id, balance) VALUES (?, ?) ON DUPLICATE KEY UPDATE balance = balance + VALUES(balance)");
                            $bonus_stmt->execute([$inviter_id, $referral_bonus]);
                            
                            // Update the referral record with the bonus amount
                            $update_referral_stmt = $pdo->prepare("UPDATE referrals SET reward_to_inviter = reward_to_inviter + ? WHERE invitee_id = ? AND invitee_role = 'seller'");
                            $update_referral_stmt->execute([$referral_bonus, $seller_id]);
                        }
                        
                        $pdo->commit();
                    } catch (Exception $e) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        error_log("Referral bonus error: " . $e->getMessage());
                    }
                    
                    // Calculate 0.5% upload fee
                    $upload_fee = $price * 0.005;
                    
                    // Update the product record with the fee information
                    $product_id = $pdo->lastInsertId();
                    $fee_stmt = $pdo->prepare("UPDATE products SET upload_fee = ?, upload_fee_paid = 0 WHERE id = ?");
                    $fee_stmt->execute([$upload_fee, $product_id]);
                    
                    // Get payment channel details for the success message
                    $channel_stmt = $pdo->prepare("SELECT pc.account_name, pc.account_number, pc.bank_name, pc.type FROM payment_channels pc JOIN products p ON pc.id = p.payment_channel_id WHERE p.id = ?");
                    $channel_stmt->execute([$product_id]);
                    $payment_channel = $channel_stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($payment_channel) {
                        $channel_info = "";
                        if (!empty($payment_channel['account_name'])) {
                            $channel_info .= "Account Name: " . htmlspecialchars($payment_channel['account_name']) . "\n";
                        }
                        if (!empty($payment_channel['account_number'])) {
                            $channel_info .= "Account Number: " . htmlspecialchars($payment_channel['account_number']) . "\n";
                        }
                        if (!empty($payment_channel['bank_name'])) {
                            $channel_info .= "Bank: " . htmlspecialchars($payment_channel['bank_name']) . "\n";
                        }
                        
                        if (!empty($channel_info)) {
                            $success_message = "Product added successfully! Please make payment for verification. You will be charged a verification fee of 0.5% of your product price. Payment should be made to:\n" . $channel_info . "\nCheck your payment slips section for payment instructions.";
                        } else {
                            $success_message = "Product added successfully! Please make payment for verification. You will be charged a verification fee of 0.5% of your product price. Check your payment slips section for payment instructions.";
                        }
                    } else {
                        $success_message = "Product added successfully! Please make payment for verification. You will be charged a verification fee of 0.5% of your product price. Check your payment slips section for payment instructions.";
                    }
                    logSellerActivity("Added new product: $name");
                } catch (Exception $e) {
                    $error_message = "Failed to add product: " . $e->getMessage();
                }
                break;
                
            case 'edit_product':
                $product_id = intval($_POST['product_id']);
                $name = trim($_POST['name']);
                $description = trim($_POST['description']);
                $price = floatval($_POST['price']);
                $stock = intval($_POST['stock']);
                $category_id = intval($_POST['category_id']);
                $payment_channel_id = intval($_POST['payment_channel_id']);
                $address = trim($_POST['address']);
                $city = trim($_POST['city']);
                $state = trim($_POST['state']);
                $country = trim($_POST['country']);
                $postal_code = trim($_POST['postal_code']);
                
                // Validate payment channel
                $channel_stmt = $pdo->prepare("SELECT id FROM payment_channels WHERE id = ? AND is_active = 1");
                $channel_stmt->execute([$payment_channel_id]);
                if (!$channel_stmt->fetch()) {
                    $error_message = "Invalid or inactive payment channel selected.";
                    break;
                }
                
                // Verify product belongs to seller
                $stmt = $pdo->prepare("SELECT id, image_url, image_gallery FROM products WHERE id = ? AND seller_id = ?");
                $stmt->execute([$product_id, $seller_id]);
                if ($product = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    // Handle image update
                    $image_sql = '';
                    $params = [$name, $description, $price, $stock, $category_id, $payment_channel_id, $address, $city, $state, $country, $postal_code];
                    
                    // Handle single image update
                    if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
                        $upload_dir = '../uploads/products/';
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0755, true);
                        }
                        
                        $file_extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
                        $filename = 'product_' . time() . '_' . uniqid() . '.' . $file_extension;
                        $target_path = $upload_dir . $filename;
                        
                        if (move_uploaded_file($_FILES['image']['tmp_name'], $target_path)) {
                            $image_url = 'uploads/products/' . $filename;
                            $image_sql .= ', image_url = ?';
                            $params[] = $image_url;
                        }
                    }
                    
                    // Handle gallery images update
                    if (isset($_FILES['gallery_images']) && is_array($_FILES['gallery_images']['name']) && count($_FILES['gallery_images']['name']) > 0) {
                        // Decode existing gallery images
                        $existing_gallery = !empty($product['image_gallery']) ? json_decode($product['image_gallery'], true) : [];
                        
                        // Handle new gallery image uploads
                        $upload_dir = '../uploads/products/';
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0755, true);
                        }
                        
                        for ($i = 0; $i < count($_FILES['gallery_images']['name']); $i++) {
                            if ($_FILES['gallery_images']['error'][$i] === 0) {
                                $file_extension = pathinfo($_FILES['gallery_images']['name'][$i], PATHINFO_EXTENSION);
                                $filename = 'gallery_' . time() . '_' . uniqid() . '_' . $i . '.' . $file_extension;
                                $target_path = $upload_dir . $filename;
                                
                                if (move_uploaded_file($_FILES['gallery_images']['tmp_name'][$i], $target_path)) {
                                    $existing_gallery[] = 'uploads/products/' . $filename;
                                }
                            }
                        }
                        
                        if (!empty($existing_gallery)) {
                            $image_gallery_json = json_encode($existing_gallery);
                            $image_sql .= ', image_gallery = ?';
                            $params[] = $image_gallery_json;
                        }
                    }
                    
                    // Handle gallery image removal
                    if (isset($_POST['remove_gallery_images']) && is_array($_POST['remove_gallery_images'])) {
                        $existing_gallery = !empty($product['image_gallery']) ? json_decode($product['image_gallery'], true) : [];
                        
                        // Remove specified images
                        foreach ($_POST['remove_gallery_images'] as $image_to_remove) {
                            if (($key = array_search($image_to_remove, $existing_gallery)) !== false) {
                                unset($existing_gallery[$key]);
                                // Delete the file from server
                                if (file_exists('../' . $image_to_remove)) {
                                    unlink('../' . $image_to_remove);
                                }
                            }
                        }
                        
                        // Re-index array
                        $existing_gallery = array_values($existing_gallery);
                        
                        if (empty($existing_gallery)) {
                            $image_gallery_json = null;
                            $image_sql .= ', image_gallery = NULL';
                        } else {
                            $image_gallery_json = json_encode($existing_gallery);
                            $image_sql .= ', image_gallery = ?';
                            $params[] = $image_gallery_json;
                        }
                    }
                    
                    $params[] = $product_id;
                    $stmt = $pdo->prepare("UPDATE products SET name = ?, description = ?, price = ?, stock = ?, category_id = ?, payment_channel_id = ?, address = ?, city = ?, state = ?, country = ?, postal_code = ? $image_sql WHERE id = ?");
                    $stmt->execute($params);
                    // Get payment channel details for the success message
                                        $channel_stmt = $pdo->prepare("SELECT pc.account_name, pc.account_number, pc.bank_name, pc.type FROM payment_channels pc JOIN products p ON pc.id = p.payment_channel_id WHERE p.id = ?");
                                        $channel_stmt->execute([$product_id]);
                                        $payment_channel = $channel_stmt->fetch(PDO::FETCH_ASSOC);
                                        
                                        if ($payment_channel) {
                                            $channel_info = "";
                                            if (!empty($payment_channel['account_name'])) {
                                                $channel_info .= "Account Name: " . htmlspecialchars($payment_channel['account_name']) . "\n";
                                            }
                                            if (!empty($payment_channel['account_number'])) {
                                                $channel_info .= "Account Number: " . htmlspecialchars($payment_channel['account_number']) . "\n";
                                            }
                                            if (!empty($payment_channel['bank_name'])) {
                                                $channel_info .= "Bank: " . htmlspecialchars($payment_channel['bank_name']) . "\n";
                                            }
                                            
                                            if (!empty($channel_info)) {
                                                $success_message = "Product updated successfully! Payment should be made to:\n" . $channel_info . "\nCheck your payment slips section for payment instructions if you've changed the payment channel.";
                                            } else {
                                                $success_message = "Product updated successfully!";
                                            }
                                        } else {
                                            $success_message = "Product updated successfully!";
                                        }
                    logSellerActivity("Updated product: $name");
                } else {
                    $error_message = "Product not found or access denied.";
                }
                break;
                
            case 'delete_product':
                $product_id = intval($_POST['product_id']);
                
                // Verify product belongs to seller and has no orders
                $stmt = $pdo->prepare("SELECT p.id, p.name, COUNT(oi.id) as order_count 
                                      FROM products p 
                                      LEFT JOIN order_items oi ON p.id = oi.product_id 
                                      WHERE p.id = ? AND p.seller_id = ? 
                                      GROUP BY p.id");
                $stmt->execute([$product_id, $seller_id]);
                if ($product = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    if ($product['order_count'] == 0) {
                        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
                        $stmt->execute([$product_id]);
                        $success_message = "Product deleted successfully!";
                        logSellerActivity("Deleted product: {$product['name']}");
                    } else {
                        $error_message = "Cannot delete product with existing orders.";
                    }
                } else {
                    $error_message = "Product not found or access denied.";
                }
                break;
                
            case 'upload_payment_slip':
                try {
                    $product_id = intval($_POST['product_id']);
                    $amount = floatval($_POST['amount']);
                    
                    // Verify the product belongs to this seller
                    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND seller_id = ?");
                    $stmt->execute([$product_id, $_SESSION['user_id']]);
                    $product = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$product) {
                        $error_message = "Invalid product!";
                        break;
                    }
                    
                    // Handle file upload
                    if (isset($_FILES['payment_slip']) && $_FILES['payment_slip']['error'] === UPLOAD_ERR_OK) {
                        $upload_dir = '../uploads/payment_slips/';
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0755, true);
                        }
                        
                        $file_name = uniqid() . '_' . basename($_FILES['payment_slip']['name']);
                        $target_file = $upload_dir . $file_name;
                        
                        // Check file type
                        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
                        $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];
                        
                        if (!in_array($imageFileType, $allowed_types)) {
                            $error_message = "Only JPG, JPEG, PNG, GIF & PDF files are allowed!";
                            break;
                        }
                        
                        // Check file size (5MB max)
                        if ($_FILES['payment_slip']['size'] > 5000000) {
                            $error_message = "File is too large. Maximum 5MB allowed!";
                            break;
                        }
                        
                        // Upload file
                        if (move_uploaded_file($_FILES['payment_slip']['tmp_name'], $target_file)) {
                            $slip_path = 'uploads/payment_slips/' . $file_name;
                            
                            // Insert payment slip record
                            $stmt = $pdo->prepare("INSERT INTO payment_slips (product_id, seller_id, slip_path, amount, verification_rate) VALUES (?, ?, ?, ?, ?)");
                            $stmt->execute([$product_id, $_SESSION['user_id'], $slip_path, $amount, $payment_verification_rate]);
                            
                            $success_message = "Payment slip uploaded successfully!";
                            logSellerActivity("Uploaded payment slip for product ID: $product_id");
                        } else {
                            $error_message = "Sorry, there was an error uploading your file.";
                        }
                    } else {
                        $error_message = "Please select a file to upload!";
                    }
                } catch (Exception $e) {
                    $error_message = "Failed to upload payment slip: " . $e->getMessage();
                }
                break;
        }
    }
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'created_at';
$sort_order = isset($_GET['order']) ? $_GET['order'] : 'desc';

// Build WHERE clause for filtering
$where_conditions = [];
$params = [$_SESSION['user_id']];

if (!empty($status_filter) && in_array($status_filter, ['active', 'inactive', 'pending'])) {
    $where_conditions[] = "p.status = ?";
    $params[] = $status_filter;
}

if (!empty($category_filter)) {
    $where_conditions[] = "p.category_id = ?";
    $params[] = $category_filter;
}

if (!empty($search_query)) {
    $where_conditions[] = "(p.name LIKE ? OR p.description LIKE ?)";
    $search_param = "%$search_query%";
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = 'WHERE p.seller_id = ?';
if (!empty($where_conditions)) {
    $where_clause .= " AND " . implode(" AND ", $where_conditions);
}

// Validate sort parameters
$allowed_sorts = ['name', 'price', 'stock', 'created_at'];
$allowed_orders = ['asc', 'desc'];

if (!in_array($sort_by, $allowed_sorts)) {
    $sort_by = 'created_at';
}
if (!in_array($sort_order, $allowed_orders)) {
    $sort_order = 'desc';
}

// Get all products with pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM products p $where_clause";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_products = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_products / $limit);

// Get products with additional info
$sql = "
    SELECT p.*, 
           c.name as category_name,
           pc.name as payment_channel_name,
           COALESCE(SUM(oi.quantity), 0) as total_sold,
           COALESCE(SUM(oi.quantity * oi.price), 0) as total_revenue
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN payment_channels pc ON p.payment_channel_id = pc.id
    LEFT JOIN order_items oi ON p.id = oi.product_id 
    LEFT JOIN orders o ON oi.order_id = o.id AND o.status = 'completed'
    $where_clause
    GROUP BY p.id 
    ORDER BY $sort_by $sort_order 
    LIMIT ? OFFSET ?
";

$stmt = $pdo->prepare($sql);

// Bind parameters separately for filters
foreach ($params as $key => $value) {
    $stmt->bindValue(($key + 1), $value);
}

// Bind LIMIT and OFFSET as integers (at the end of the parameter list)
$stmt->bindValue((count($params) + 1), $limit, PDO::PARAM_INT);
$stmt->bindValue((count($params) + 2), $offset, PDO::PARAM_INT);

$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get categories for filter dropdown
$categories_stmt = $pdo->prepare("SELECT id, name FROM categories WHERE status = 'active' ORDER BY name");
$categories_stmt->execute();
$categories = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get payment channels for the form
$channels_stmt = $pdo->prepare("SELECT id, name FROM payment_channels WHERE is_active = 1 ORDER BY name");
$channels_stmt->execute();
$payment_channels = $channels_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get product counts by status for filter badges
$status_counts_stmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM products WHERE seller_id = ? GROUP BY status");
$status_counts_stmt->execute([$_SESSION['user_id']]);
$status_counts = $status_counts_stmt->fetchAll(PDO::FETCH_ASSOC);

// If no status counts, initialize with zeros
if (empty($status_counts)) {
    $status_counts = [
        ['status' => 'active', 'count' => 0],
        ['status' => 'inactive', 'count' => 0],
        ['status' => 'pending', 'count' => 0]
    ];
}

// Get payment slips for seller's products
$stmt = $pdo->prepare("
    SELECT ps.*, p.name as product_name 
    FROM payment_slips ps 
    JOIN products p ON ps.product_id = p.id 
    WHERE ps.seller_id = ? 
    ORDER BY ps.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$payment_slips = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get seller profile for account info
$stmt = $pdo->prepare("SELECT first_name, last_name, phone, account_number FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$seller_info = $stmt->fetch(PDO::FETCH_ASSOC);

// Initialize seller info with default values if not found
if (!$seller_info) {
    $seller_info = [
        'first_name' => 'Seller',
        'last_name' => '',
        'phone' => '',
        'account_number' => ''
    ];
}

// Log seller activity
logSellerActivity("Accessed products page");

function logSellerActivity($activity) {
    global $pdo;
    if (isset($_SESSION['user_id'])) {
        $stmt = $pdo->prepare("INSERT INTO seller_activities (seller_id, activity, ip_address) VALUES (?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], $activity, $_SERVER['REMOTE_ADDR']]);
    }
}

// Helper function to build pagination query string
function buildQueryString($page, $exclude = []) {
    $params = $_GET;
    $params['page'] = $page;
    foreach ($exclude as $key) {
        unset($params[$key]);
    }
    return http_build_query($params);
}

// Helper function to get sort icon
function getSortIcon($column, $current_sort, $current_order) {
    if ($column === $current_sort) {
        return $current_order === 'asc' ? 'fa-sort-up' : 'fa-sort-down';
    }
    return 'fa-sort';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products Management - BSDO Seller</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #1cc88a;
            --warning-color: #f6c23e;
            --danger-color: #e74a3b;
            --dark-color: #2e3a59;
        }
        
        body {
            font-family: 'Nunito', sans-serif;
            background-color: #f8f9fc;
            min-height: 100vh;
        }
        
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .sidebar {
            background-color: var(--dark-color);
            color: white;
            min-height: calc(100vh - 56px);
            position: sticky;
            top: 56px;
        }
        
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 12px 20px;
            border-left: 3px solid transparent;
            transition: all 0.3s;
        }
        
        .sidebar .nav-link:hover {
            color: white;
            background-color: rgba(255, 255, 255, 0.1);
            border-left-color: var(--primary-color);
        }
        
        .sidebar .nav-link.active {
            color: white;
            background-color: rgba(255, 255, 255, 0.15);
            border-left-color: var(--primary-color);
        }
        
        .product-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 5px;
        }
        
        .stats-card {
            border-left: 4px solid;
            transition: transform 0.2s;
            border-radius: 0.5rem;
        }
        
        .stats-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        
        .stats-card.primary { border-left-color: var(--primary-color); }
        .stats-card.success { border-left-color: var(--secondary-color); }
        .stats-card.warning { border-left-color: var(--warning-color); }
        
        .modal-header {
            background: linear-gradient(135deg, var(--primary-color), #667eea);
            color: white;
        }
        
        .modal-header .btn-close {
            filter: invert(1);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), #667eea);
            border: none;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #3a5fd0, #556bd1);
        }
        
        .btn-outline-primary:hover {
            background: linear-gradient(135deg, var(--primary-color), #667eea);
            border-color: var(--primary-color);
        }
        
        .gallery-image-container {
            position: relative;
            margin-bottom: 10px;
        }
        
        .gallery-image-container .remove-btn {
            position: absolute;
            top: 5px;
            right: 5px;
            opacity: 0.7;
            transition: opacity 0.3s;
        }
        
        .gallery-image-container:hover .remove-btn {
            opacity: 1;
        }
        
        .scroll-to-top {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
        }
        
        /* Table improvements */
        .table th {
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
        }
        
        .badge {
            font-size: 0.75em;
            padding: 0.35em 0.65em;
        }
        
        .bg-success { background-color: var(--secondary-color) !important; }
        .bg-warning { background-color: var(--warning-color) !important; }
        .bg-secondary { background-color: #6c757d !important; }
        
        /* Card improvements */
        .card {
            border-radius: 0.5rem;
            border: 1px solid #e3e6f0;
            box-shadow: 0 0.15rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        
        .card-header {
            background-color: #f8f9fc;
            border-bottom: 1px solid #e3e6f0;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="fas fa-store me-2"></i>Seller Dashboard
            </a>
            
            <!-- Mobile menu button -->
            <button class="navbar-toggler d-lg-none" type="button" data-bs-toggle="collapse" data-bs-target="#mobileSidebar">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <!-- Seller info -->
            <div class="d-flex align-items-center">
                <div class="seller-avatar me-2">
                    <?php 
                        $initials = strtoupper(substr($seller_info['first_name'], 0, 1) . substr($seller_info['last_name'], 0, 1));
                        echo $initials ?: 'S';
                    ?>
                </div>
                <span class="text-white"><?php echo htmlspecialchars($seller_info['first_name'] . ' ' . $seller_info['last_name']); ?></span>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar - Desktop -->
            <div class="col-lg-2 sidebar d-none d-lg-block">
                <div class="pt-4">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">
                                <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="products.php">
                                <i class="fas fa-box me-2"></i>Products
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="rental_products.php">
                                <i class="fas fa-calendar-alt me-2"></i>Rental Products
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="orders.php">
                                <i class="fas fa-shopping-cart me-2"></i>Orders
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="live_stream.php">
                                <i class="fas fa-video me-2"></i>Live Stream
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="analytics.php">
                                <i class="fas fa-chart-bar me-2"></i>Analytics
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="profile.php">
                                <i class="fas fa-user me-2"></i>Profile
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="payment_verification.php">
                                <i class="fas fa-money-check me-2"></i>Payment Verification
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="withdrawal_request.php">
                                <i class="fas fa-money-bill-transfer me-2"></i>Withdraw Funds
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i>Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-lg-10 col-12 p-4">
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

                <!-- Page Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h1 class="h3 mb-0 text-gray-800">Products Management</h1>
                        <p class="text-muted">Manage your product catalog and inventory</p>
                    </div>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProductModal">
                        <i class="fas fa-plus me-2"></i>Add New Product
                    </button>
                </div>

                <!-- Quick Stats -->
                <div class="row mb-4">
                    <div class="col-xl-4 col-md-6 mb-4">
                        <div class="card stats-card primary h-100">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Products</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($total_products); ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-box fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-4 col-md-6 mb-4">
                        <div class="card stats-card success h-100">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Active Products</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php 
                                                $active_count = 0;
                                                foreach ($status_counts as $status) {
                                                    if ($status['status'] === 'active') {
                                                        $active_count = $status['count'];
                                                        break;
                                                    }
                                                }
                                                echo number_format($active_count);
                                            ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-4 col-md-6 mb-4">
                        <div class="card stats-card warning h-100">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Pending Review</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php 
                                                $pending_count = 0;
                                                foreach ($status_counts as $status) {
                                                    if ($status['status'] === 'pending') {
                                                        $pending_count = $status['count'];
                                                        break;
                                                    }
                                                }
                                                echo number_format($pending_count);
                                            ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-clock fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters Card -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Filters & Search</h6>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Status Filter</label>
                                <select class="form-select" name="status" onchange="this.form.submit()">
                                    <option value="">All Status</option>
                                    <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Category Filter</label>
                                <select class="form-select" name="category" onchange="this.form.submit()">
                                    <option value="">All Categories</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo $cat['id']; ?>" <?php echo $category_filter == $cat['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Search</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" name="search" placeholder="Search products..." value="<?php echo htmlspecialchars($search_query); ?>">
                                    <button class="btn btn-outline-primary" type="submit"><i class="fas fa-search"></i></button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Products Table -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-primary">Products</h6>
                    </div>
                    <div class="card-body">
                        <?php if (empty($products)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No products found. Add your first product to get started!</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-bordered" width="100%" cellspacing="0">
                                    <thead>
                                        <tr>
                                            <th>Image</th>
                                            <th>Name</th>
                                            <th>Price</th>
                                            <th>Stock</th>
                                            <th>Category</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($products as $product): ?>
                                            <tr>
                                                <td>
                                                    <?php if ($product['image_url']): ?>
                                                        <img src="../<?php echo htmlspecialchars($product['image_url']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="product-image">
                                                    <?php else: ?>
                                                        <div class="bg-light border d-flex align-items-center justify-content-center product-image">
                                                            <i class="fas fa-image text-muted"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($product['name']); ?></td>
                                                <td>$<?php echo number_format($product['price'], 2); ?></td>
                                                <td><?php echo $product['stock']; ?></td>
                                                <td><?php echo htmlspecialchars($product['category_name']); ?></td>
                                                <td>
                                                    <span class="badge 
                                                        <?php 
                                                            switch ($product['status']) {
                                                                case 'active': echo 'bg-success'; break;
                                                                case 'inactive': echo 'bg-secondary'; break;
                                                                case 'pending': echo 'bg-warning'; break;
                                                                default: echo 'bg-info';
                                                            }
                                                        ?>">
                                                        <?php echo ucfirst($product['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editProductModal<?php echo $product['id']; ?>">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteProduct(<?php echo $product['id']; ?>)">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination -->
                            <?php if ($total_pages > 1): ?>
                                <nav aria-label="Page navigation">
                                    <ul class="pagination justify-content-center">
                                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?<?php echo buildQueryString($page - 1); ?>">Previous</a>
                                        </li>
                                        
                                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                            <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                                                <a class="page-link" href="?<?php echo buildQueryString($i); ?>"><?php echo $i; ?></a>
                                            </li>
                                        <?php endfor; ?>
                                        
                                        <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                            <a class="page-link" href="?<?php echo buildQueryString($page + 1); ?>">Next</a>
                                        </li>
                                    </ul>
                                </nav>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="py-4 bg-white mt-auto">
        <div class="container-fluid">
            <div class="d-flex align-items-center justify-content-between small">
                <div class="text-muted">Copyright &copy; BSDO Sale <?php echo date('Y'); ?></div>
                <div>
                    <a href="#" class="small text-muted me-3">Privacy Policy</a>
                    <a href="#" class="small text-muted">Terms &amp; Conditions</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Scroll to Top Button-->
    <a class="scroll-to-top rounded" href="#page-top" style="display: none;">
        <i class="fas fa-angle-up"></i>
    </a>

    <!-- Mobile Sidebar Modal -->
    <div class="offcanvas offcanvas-start d-lg-none" tabindex="-1" id="mobileSidebar">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title">Menu</h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="offcanvas-body">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link" href="dashboard.php">
                        <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="products.php">
                        <i class="fas fa-box me-2"></i>Products
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="rental_products.php">
                        <i class="fas fa-calendar-alt me-2"></i>Rental Products
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="orders.php">
                        <i class="fas fa-shopping-cart me-2"></i>Orders
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="live_stream.php">
                        <i class="fas fa-video me-2"></i>Live Stream
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="analytics.php">
                        <i class="fas fa-chart-bar me-2"></i>Analytics
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="profile.php">
                        <i class="fas fa-user me-2"></i>Profile
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="payment_verification.php">
                        <i class="fas fa-money-check me-2"></i>Payment Verification
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="withdrawal_request.php">
                        <i class="fas fa-money-bill-transfer me-2"></i>Withdraw Funds
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../logout.php">
                        <i class="fas fa-sign-out-alt me-2"></i>Logout
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <!-- Add Product Modal -->
    <div class="modal fade" id="addProductModal" tabindex="-1" aria-labelledby="addProductModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="add_product">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addProductModalLabel">Add New Product</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Product Name *</label>
                                    <input type="text" class="form-control" name="name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Price ($) *</label>
                                    <input type="number" class="form-control" name="price" step="0.01" min="0" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Description *</label>
                            <textarea class="form-control" name="description" rows="3" required></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Stock Quantity *</label>
                                    <input type="number" class="form-control" name="stock" min="0" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Category *</label>
                                    <select class="form-select" name="category_id" required>
                                        <option value="">Select Category</option>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Payment Channel *</label>
                            <select class="form-select" name="payment_channel_id" required>
                                <option value="">Select Payment Channel</option>
                                <?php foreach ($payment_channels as $channel): ?>
                                    <option value="<?php echo $channel['id']; ?>"><?php echo htmlspecialchars($channel['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Main Image</label>
                                    <input type="file" class="form-control" name="image" accept="image/*">
                                    <small class="form-text text-muted">Recommended: 800x600px</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Gallery Images</label>
                                    <input type="file" class="form-control" name="gallery_images[]" accept="image/*" multiple>
                                    <small class="form-text text-muted">Select multiple images</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Address</label>
                                    <input type="text" class="form-control" name="address" placeholder="Street address">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">City</label>
                                    <input type="text" class="form-control" name="city" placeholder="City">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">State/Province</label>
                                    <input type="text" class="form-control" name="state" placeholder="State or Province">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Country</label>
                                    <input type="text" class="form-control" name="country" placeholder="Country">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Postal Code</label>
                            <input type="text" class="form-control" name="postal_code" placeholder="Postal Code">
                        </div>
                        
                        <div class="alert alert-info mt-3" id="feeInfo" style="display: none;">
                            <h6>Payment Verification Fee:</h6>
                            <p>Product Price: $<span id="productPrice">0.00</span></p>
                            <p>Verification Fee (0.5%): <strong>$<span id="feeAmount">0.00</span></strong></p>
                            <p class="mb-0">Total Amount Due: <strong>$<span id="totalAmount">0.00</span></strong></p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Product</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Product Modals -->
    <?php foreach ($products as $product): ?>
    <div class="modal fade" id="editProductModal<?php echo $product['id']; ?>" tabindex="-1" aria-labelledby="editProductModalLabel<?php echo $product['id']; ?>" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="edit_product">
                    <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editProductModalLabel<?php echo $product['id']; ?>">Edit Product: <?php echo htmlspecialchars($product['name']); ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Product Name *</label>
                                    <input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars($product['name']); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Price ($) *</label>
                                    <input type="number" class="form-control" name="price" step="0.01" min="0" value="<?php echo $product['price']; ?>" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Description *</label>
                            <textarea class="form-control" name="description" rows="3" required><?php echo htmlspecialchars($product['description']); ?></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Stock Quantity *</label>
                                    <input type="number" class="form-control" name="stock" min="0" value="<?php echo $product['stock']; ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Category *</label>
                                    <select class="form-select" name="category_id" required>
                                        <option value="">Select Category</option>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?php echo $category['id']; ?>" <?php echo $product['category_id'] == $category['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($category['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Payment Channel *</label>
                            <select class="form-select" name="payment_channel_id" required>
                                <option value="">Select Payment Channel</option>
                                <?php foreach ($payment_channels as $channel): ?>
                                    <option value="<?php echo $channel['id']; ?>" <?php echo $product['payment_channel_id'] == $channel['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($channel['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Main Image</label>
                                    <?php if ($product['image_url']): ?>
                                        <div class="mb-2">
                                            <img src="../<?php echo htmlspecialchars($product['image_url']); ?>" alt="Current image" class="img-thumbnail" style="max-height: 100px;">
                                        </div>
                                    <?php endif; ?>
                                    <input type="file" class="form-control" name="image" accept="image/*">
                                    <small class="form-text text-muted">Leave blank to keep current image</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Gallery Images</label>
                                    <input type="file" class="form-control" name="gallery_images[]" accept="image/*" multiple>
                                    <small class="form-text text-muted">Select new images to add to gallery</small>
                                    
                                    <?php 
                                    if (!empty($product['image_gallery'])): 
                                        $gallery_images = json_decode($product['image_gallery'], true);
                                        if (!empty($gallery_images)):
                                    ?>
                                        <div class="mt-2">
                                            <p class="small mb-1">Current gallery images:</p>
                                            <div class="row">
                                                <?php foreach ($gallery_images as $image): ?>
                                                    <div class="col-3 mb-2">
                                                        <div class="gallery-image-container">
                                                            <img src="../<?php echo htmlspecialchars($image); ?>" alt="Gallery image" class="img-thumbnail w-100" style="height: 80px; object-fit: cover;">
                                                            <button type="button" class="btn btn-danger btn-sm remove-btn" onclick="markImageForRemoval(this, '<?php echo htmlspecialchars($image); ?>')">
                                                                <i class="fas fa-times"></i>
                                                            </button>
                                                            <input type="hidden" name="remove_gallery_images[]" value="">
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Address</label>
                                    <input type="text" class="form-control" name="address" value="<?php echo htmlspecialchars($product['address']); ?>" placeholder="Street address">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">City</label>
                                    <input type="text" class="form-control" name="city" value="<?php echo htmlspecialchars($product['city']); ?>" placeholder="City">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">State/Province</label>
                                    <input type="text" class="form-control" name="state" value="<?php echo htmlspecialchars($product['state']); ?>" placeholder="State or Province">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Country</label>
                                    <input type="text" class="form-control" name="country" value="<?php echo htmlspecialchars($product['country']); ?>" placeholder="Country">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Postal Code</label>
                            <input type="text" class="form-control" name="postal_code" value="<?php echo htmlspecialchars($product['postal_code']); ?>" placeholder="Postal Code">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-labelledby="deleteConfirmModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteConfirmModalLabel">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this product? This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Delete</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Global variable to store product ID for deletion
        let productIdToDelete = null;
        
        // Initialize all modals
        document.addEventListener('DOMContentLoaded', function() {
            // Fee calculation for add product form
            const priceInput = document.querySelector('#addProductModal input[name="price"]');
            const feeInfo = document.getElementById('feeInfo');
            const productPriceSpan = document.getElementById('productPrice');
            const feeAmountSpan = document.getElementById('feeAmount');
            const totalAmountSpan = document.getElementById('totalAmount');
            
            if (priceInput) {
                priceInput.addEventListener('input', function() {
                    const price = parseFloat(this.value) || 0;
                    if (price > 0) {
                        const fee = price * 0.005;
                        const total = price + fee;
                        
                        productPriceSpan.textContent = price.toFixed(2);
                        feeAmountSpan.textContent = fee.toFixed(2);
                        totalAmountSpan.textContent = total.toFixed(2);
                        feeInfo.style.display = 'block';
                    } else {
                        feeInfo.style.display = 'none';
                    }
                });
            }
            
            // Scroll to top button
            const scrollButton = document.querySelector('.scroll-to-top');
            if (scrollButton) {
                window.addEventListener('scroll', function() {
                    if (window.pageYOffset > 100) {
                        scrollButton.style.display = 'block';
                    } else {
                        scrollButton.style.display = 'none';
                    }
                });
                
                scrollButton.addEventListener('click', function(e) {
                    e.preventDefault();
                    window.scrollTo({top: 0, behavior: 'smooth'});
                });
            }
            
            // Initialize tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
        
        // Delete product function
        function deleteProduct(productId) {
            productIdToDelete = productId;
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
            deleteModal.show();
        }
        
        // Confirm delete
        document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
            if (productIdToDelete) {
                // Create a form and submit it
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'delete_product';
                form.appendChild(actionInput);
                
                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'product_id';
                idInput.value = productIdToDelete;
                form.appendChild(idInput);
                
                document.body.appendChild(form);
                form.submit();
            }
        });
        
        // Mark gallery image for removal
        function markImageForRemoval(button, imagePath) {
            const hiddenInput = button.closest('.gallery-image-container').querySelector('input[type="hidden"]');
            if (hiddenInput) {
                hiddenInput.value = imagePath;
                button.closest('.col-3').style.opacity = '0.5';
                button.disabled = true;
            }
        }
    </script>
</body>
</html>