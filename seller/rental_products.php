<?php
session_start();
require_once '../config.php';
require_once '../models/RentalProductModel.php';
require_once '../utils/SecurityUtils.php';
require_once '../utils/Logger.php';

// Send security headers
SecurityUtils::sendSecurityHeaders();

// Regenerate session ID to prevent fixation attacks
SecurityUtils::regenerateSession();

// Check if user is logged in and is seller
if (!SecurityUtils::isLoggedIn() || !SecurityUtils::checkUserRole('seller')) {
    SecurityUtils::redirectWithError('../login.php', 'You must be logged in as a seller to access this page.');
}

// Initialize model
$rentalModel = new RentalProductModel($pdo);

$seller_id = $_SESSION['user_id'];
$error_message = '';
$success_message = '';

// Handle messages from session
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Get payment verification rate setting
$payment_verification_rate = $rentalModel->getPaymentVerificationRate();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF protection
    if (!isset($_POST['csrf_token']) || !SecurityUtils::validateCSRFToken($_POST['csrf_token'])) {
        $error_message = "Invalid request. Please try again.";
        Logger::warning('CSRF token validation failed', ['user_id' => $seller_id]);
    } else {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add_rental_product':
                    // Sanitize and validate input data
                    $name = SecurityUtils::sanitizeInput($_POST['name']);
                    $description = SecurityUtils::sanitizeInput($_POST['description']);
                    $category_id = SecurityUtils::sanitizeInt($_POST['category_id']);
                    $stock = SecurityUtils::sanitizeInt($_POST['stock'], 0);
                    $payment_channel_id = SecurityUtils::sanitizeInt($_POST['payment_channel_id']);
                    
                    $address = SecurityUtils::sanitizeInput($_POST['address']);
                    $city = SecurityUtils::sanitizeInput($_POST['city']);
                    $state = SecurityUtils::sanitizeInput($_POST['state']);
                    $country = SecurityUtils::sanitizeInput($_POST['country']);
                    $postal_code = SecurityUtils::sanitizeInput($_POST['postal_code']);
                    
                    $rental_price_per_day = SecurityUtils::sanitizeFloat($_POST['rental_price_per_day'], 0);
                    $rental_price_per_week = SecurityUtils::sanitizeFloat($_POST['rental_price_per_week'], 0);
                    $rental_price_per_month = SecurityUtils::sanitizeFloat($_POST['rental_price_per_month'], 0);
                    
                    // Validate required fields
                    if (empty($name) || empty($description) || !$category_id || !$payment_channel_id) {
                        $error_message = "Please fill in all required fields.";
                        Logger::warning('Missing required fields in add_rental_product', ['user_id' => $seller_id]);
                        break;
                    }
                    
                    // Validate prices
                    if ($rental_price_per_day <= 0) {
                        $error_message = "Daily rental price must be greater than zero.";
                        Logger::warning('Invalid rental price', ['rental_price_per_day' => $rental_price_per_day, 'user_id' => $seller_id]);
                        break;
                    }
                    
                    // Prepare data array
                    $data = [
                        'name' => $name,
                        'description' => $description,
                        'category_id' => $category_id,
                        'stock' => $stock,
                        'payment_channel_id' => $payment_channel_id,
                        'address' => $address,
                        'city' => $city,
                        'state' => $state,
                        'country' => $country,
                        'postal_code' => $postal_code,
                        'rental_price_per_day' => $rental_price_per_day,
                        'rental_price_per_week' => $rental_price_per_week,
                        'rental_price_per_month' => $rental_price_per_month
                    ];
                    
                    // Add rental product
                    $result = $rentalModel->addRentalProduct($data, $seller_id);
                    
                    if ($result['success']) {
                        $success_message = "Rental product added successfully!";
                        Logger::info('Rental product added', ['product_id' => $result['product_id'], 'user_id' => $seller_id]);
                        
                        // Redirect to prevent resubmission
                        $_SESSION['success_message'] = $success_message;
                        header('Location: rental_products.php');
                        exit();
                    } else {
                        $error_message = $result['error'] ?? "Failed to add rental product. Please try again.";
                        Logger::error('Failed to add rental product', ['error' => $result['error'], 'user_id' => $seller_id]);
                    }
                    break;
                    
                case 'edit_rental_product':
                    $product_id = SecurityUtils::sanitizeInt($_POST['product_id']);
                    
                    if (!$product_id) {
                        $error_message = "Invalid product ID.";
                        Logger::warning('Invalid product ID for edit', ['product_id' => $_POST['product_id'], 'user_id' => $seller_id]);
                        break;
                    }
                    
                    // Sanitize and validate input data
                    $name = SecurityUtils::sanitizeInput($_POST['name']);
                    $description = SecurityUtils::sanitizeInput($_POST['description']);
                    $category_id = SecurityUtils::sanitizeInt($_POST['category_id']);
                    $stock = SecurityUtils::sanitizeInt($_POST['stock'], 0);
                    $payment_channel_id = SecurityUtils::sanitizeInt($_POST['payment_channel_id']);
                    
                    $address = SecurityUtils::sanitizeInput($_POST['address']);
                    $city = SecurityUtils::sanitizeInput($_POST['city']);
                    $state = SecurityUtils::sanitizeInput($_POST['state']);
                    $country = SecurityUtils::sanitizeInput($_POST['country']);
                    $postal_code = SecurityUtils::sanitizeInput($_POST['postal_code']);
                    
                    $rental_price_per_day = SecurityUtils::sanitizeFloat($_POST['rental_price_per_day'], 0);
                    $rental_price_per_week = SecurityUtils::sanitizeFloat($_POST['rental_price_per_week'], 0);
                    $rental_price_per_month = SecurityUtils::sanitizeFloat($_POST['rental_price_per_month'], 0);
                    
                    // Validate required fields
                    if (empty($name) || empty($description) || !$category_id || !$payment_channel_id) {
                        $error_message = "Please fill in all required fields.";
                        Logger::warning('Missing required fields in edit_rental_product', ['user_id' => $seller_id]);
                        break;
                    }
                    
                    // Validate prices
                    if ($rental_price_per_day <= 0) {
                        $error_message = "Daily rental price must be greater than zero.";
                        Logger::warning('Invalid rental price', ['rental_price_per_day' => $rental_price_per_day, 'user_id' => $seller_id]);
                        break;
                    }
                    
                    // Prepare data array
                    $data = [
                        'name' => $name,
                        'description' => $description,
                        'category_id' => $category_id,
                        'stock' => $stock,
                        'payment_channel_id' => $payment_channel_id,
                        'address' => $address,
                        'city' => $city,
                        'state' => $state,
                        'country' => $country,
                        'postal_code' => $postal_code,
                        'rental_price_per_day' => $rental_price_per_day,
                        'rental_price_per_week' => $rental_price_per_week,
                        'rental_price_per_month' => $rental_price_per_month
                    ];
                    
                    // Update rental product
                    $result = $rentalModel->updateRentalProduct($data, $product_id, $seller_id);
                    
                    if ($result['success']) {
                        $success_message = "Rental product updated successfully!";
                        Logger::info('Rental product updated', ['product_id' => $product_id, 'user_id' => $seller_id]);
                        
                        // Redirect to prevent resubmission
                        $_SESSION['success_message'] = $success_message;
                        header('Location: rental_products.php');
                        exit();
                    } else {
                        $error_message = $result['error'] ?? "Failed to update rental product. Please try again.";
                        Logger::error('Failed to update rental product', ['error' => $result['error'], 'product_id' => $product_id, 'user_id' => $seller_id]);
                    }
                    break;
                    
                case 'delete_rental_product':
                    $product_id = SecurityUtils::sanitizeInt($_POST['product_id']);
                    
                    if (!$product_id) {
                        $error_message = "Invalid product ID.";
                        Logger::warning('Invalid product ID for delete', ['product_id' => $_POST['product_id'], 'user_id' => $seller_id]);
                        break;
                    }
                    
                    // Delete rental product
                    $result = $rentalModel->deleteRentalProduct($product_id, $seller_id);
                    
                    if ($result['success']) {
                        $success_message = "Rental product deleted successfully!";
                        Logger::info('Rental product deleted', ['product_id' => $product_id, 'user_id' => $seller_id]);
                    } else {
                        $error_message = $result['error'] ?? "Failed to delete rental product. Please try again.";
                        Logger::error('Failed to delete rental product', ['error' => $result['error'], 'product_id' => $product_id, 'user_id' => $seller_id]);
                    }
                    break;
                
                case 'update_rental_product':
                    $product_id = SecurityUtils::sanitizeInt($_POST['product_id']);
                    
                    if (!$product_id) {
                        $error_message = "Invalid product ID.";
                        Logger::warning('Invalid product ID for update', ['product_id' => $_POST['product_id'], 'user_id' => $seller_id]);
                        break;
                    }
                    
                    // Sanitize and validate input data
                    $name = SecurityUtils::sanitizeInput($_POST['name']);
                    $description = SecurityUtils::sanitizeInput($_POST['description']);
                    $category_id = SecurityUtils::sanitizeInt($_POST['category_id']);
                    $stock = SecurityUtils::sanitizeInt($_POST['stock'], 0);
                    $payment_channel_id = SecurityUtils::sanitizeInt($_POST['payment_channel_id']);
                    
                    $rental_price_per_day = SecurityUtils::sanitizeFloat($_POST['rental_price_per_day'], 0);
                    $rental_price_per_week = SecurityUtils::sanitizeFloat($_POST['rental_price_per_week'], 0);
                    $rental_price_per_month = SecurityUtils::sanitizeFloat($_POST['rental_price_per_month'], 0);
                    
                    $min_rental_days = SecurityUtils::sanitizeInt($_POST['min_rental_days'], 1);
                    $max_rental_days = SecurityUtils::sanitizeInt($_POST['max_rental_days'], $min_rental_days);
                    $security_deposit = SecurityUtils::sanitizeFloat($_POST['security_deposit'], 0);
                    
                    $address = SecurityUtils::sanitizeInput($_POST['address']);
                    $city = SecurityUtils::sanitizeInput($_POST['city']);
                    $state = SecurityUtils::sanitizeInput($_POST['state']);
                    $country = SecurityUtils::sanitizeInput($_POST['country']);
                    $postal_code = SecurityUtils::sanitizeInput($_POST['postal_code']);
                    
                    // Validate required fields
                    if (empty($name) || empty($description) || !$category_id || !$payment_channel_id) {
                        $error_message = "Please fill in all required fields.";
                        Logger::warning('Missing required fields in update_rental_product', ['user_id' => $seller_id]);
                        break;
                    }
                    
                    // Validate prices
                    if ($rental_price_per_day <= 0) {
                        $error_message = "Daily rental price must be greater than zero.";
                        Logger::warning('Invalid rental price', ['rental_price_per_day' => $rental_price_per_day, 'user_id' => $seller_id]);
                        break;
                    }
                    
                    // Verify product belongs to seller
                    $existing_product = $rentalModel->getRentalProductById($product_id, $seller_id);
                    
                    if (!$existing_product) {
                        $error_message = "Product not found or unauthorized access.";
                        Logger::warning('Unauthorized access to product', ['product_id' => $product_id, 'user_id' => $seller_id]);
                        break;
                    }
                    
                    // Handle image update
                    $image_url = $existing_product['image_url'];
                    $image_gallery = $existing_product['image_gallery'];
                    
                    // Check if user wants to remove current image
                    if (isset($_POST['remove_current_image']) && $_POST['remove_current_image'] == 1) {
                        // Delete the old image file
                        if ($image_url && file_exists($_SERVER['DOCUMENT_ROOT'] . '/' . $image_url)) {
                            unlink($_SERVER['DOCUMENT_ROOT'] . '/' . $image_url);
                        }
                        $image_url = '';
                    }
                    
                    // Handle new image upload
                    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] == 0) {
                        $upload_result = uploadProductImage($_FILES['product_image']);
                        if ($upload_result['success']) {
                            $image_url = $upload_result['path'];
                        } else {
                            $error_message = $upload_result['error'];
                            Logger::error('Failed to upload product image', ['error' => $upload_result['error'], 'user_id' => $seller_id]);
                            break;
                        }
                    }
                    
                    try {
                        $stmt = $pdo->prepare("UPDATE products SET name=?, description=?, category_id=?, stock=?, rental_price_per_day=?, rental_price_per_week=?, rental_price_per_month=?, min_rental_days=?, max_rental_days=?, security_deposit=?, address=?, city=?, state=?, country=?, postal_code=?, payment_channel_id=?, image_url=?, updated_at=NOW() WHERE id=? AND seller_id=?");
                        
                        $result = $stmt->execute([
                            $name, $description, $category_id, $stock,
                            $rental_price_per_day, $rental_price_per_week, $rental_price_per_month,
                            $min_rental_days, $max_rental_days, $security_deposit,
                            $address, $city, $state, $country, $postal_code,
                            $payment_channel_id, $image_url,
                            $product_id, $seller_id
                        ]);
                        
                        if ($result) {
                            $success_message = "Rental product updated successfully!";
                            Logger::info('Rental product updated with image', ['product_id' => $product_id, 'user_id' => $seller_id]);
                            
                            // Redirect to prevent resubmission
                            $_SESSION['success_message'] = $success_message;
                            header('Location: rental_products.php');
                            exit();
                        } else {
                            $error_message = "Failed to update rental product.";
                            Logger::error('Failed to update rental product in database', ['product_id' => $product_id, 'user_id' => $seller_id]);
                        }
                    } catch (Exception $e) {
                        $error_message = "Failed to update rental product: " . $e->getMessage();
                        Logger::error('Exception during rental product update', ['error' => $e->getMessage(), 'product_id' => $product_id, 'user_id' => $seller_id]);
                    }
                    break;
                
                case 'upload_payment_slip':
                    try {
                        $product_id = SecurityUtils::sanitizeInt($_POST['product_id']);
                        $amount = SecurityUtils::sanitizeFloat($_POST['amount'], 0);
                        
                        if (!$product_id || $amount <= 0) {
                            $error_message = "Invalid product ID or amount.";
                            Logger::warning('Invalid parameters for payment slip upload', ['product_id' => $_POST['product_id'], 'amount' => $_POST['amount'], 'user_id' => $seller_id]);
                            break;
                        }
                        
                        // Verify product belongs to seller
                        $stmt = $pdo->prepare("SELECT id FROM products WHERE id = ? AND seller_id = ? AND is_rental = 1");
                        $stmt->execute([$product_id, $seller_id]);
                        
                        if (!$stmt->fetch()) {
                            $error_message = "Product not found or unauthorized access.";
                            Logger::warning('Unauthorized access to product for payment slip', ['product_id' => $product_id, 'user_id' => $seller_id]);
                            break;
                        }
                        
                        // Handle file upload
                        if (isset($_FILES['payment_slip']) && $_FILES['payment_slip']['error'] == 0) {
                            $upload_dir = "../uploads/payment_slips/";
                            
                            // Create uploads directory if it doesn't exist
                            if (!file_exists($upload_dir)) {
                                mkdir($upload_dir, 0777, true);
                            }
                            
                            // Generate unique filename
                            $file_extension = pathinfo($_FILES['payment_slip']['name'], PATHINFO_EXTENSION);
                            $file_name = 'payment_slip_' . $seller_id . '_' . $product_id . '_' . time() . '.' . $file_extension;
                            $target_file = $upload_dir . $file_name;
                            
                            // Check file type
                            $allowed_types = ['jpg', 'jpeg', 'png', 'pdf'];
                            if (!in_array(strtolower($file_extension), $allowed_types)) {
                                $error_message = "Only JPG, JPEG, PNG, and PDF files are allowed!";
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
                                Logger::info('Payment slip uploaded', ['product_id' => $product_id, 'amount' => $amount, 'user_id' => $seller_id]);
                                logSellerActivity("Uploaded payment slip for rental product ID: $product_id");
                            } else {
                                $error_message = "Sorry, there was an error uploading your file.";
                                Logger::error('Failed to move uploaded file', ['target_file' => $target_file, 'user_id' => $seller_id]);
                            }
                        } else {
                            $error_message = "Please select a file to upload!";
                            Logger::warning('No file selected for upload', ['user_id' => $seller_id]);
                        }
                    } catch (Exception $e) {
                        $error_message = "Failed to upload payment slip: " . $e->getMessage();
                        Logger::error('Exception during payment slip upload', ['error' => $e->getMessage(), 'user_id' => $seller_id]);
                    }
                    break;
            }
        }
    }
}

// Get rental products for this seller
$rental_products = $rentalModel->getSellerRentalProducts($seller_id);

// Get categories and payment channels for forms
$categories = $rentalModel->getCategories();
$payment_channels = $rentalModel->getPaymentChannels();

// Generate CSRF token for forms
$csrf_token = SecurityUtils::generateCSRFToken();

// Get rental statistics
try {
    // Total rental products
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM products WHERE seller_id = ? AND is_rental = 1");
    $stmt->execute([$seller_id]);
    $total_rental_products = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Active rental products
    $stmt = $pdo->prepare("SELECT COUNT(*) as active FROM products WHERE seller_id = ? AND is_rental = 1 AND stock > 0");
    $stmt->execute([$seller_id]);
    $active_rental_products = $stmt->fetch(PDO::FETCH_ASSOC)['active'];
    
    // Rental revenue stats
    $stmt = $pdo->prepare("SELECT 
        COUNT(oi.id) as total_rental_orders,
        SUM(oi.price * oi.quantity) as total_rental_revenue,
        SUM(CASE WHEN p.stock > 0 THEN 1 ELSE 0 END) as active_rental_revenue
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        WHERE p.seller_id = ? AND p.is_rental = 1");
    $stmt->execute([$seller_id]);
    $rental_stats = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    Logger::error('Failed to fetch rental statistics', ['error' => $e->getMessage(), 'user_id' => $seller_id]);
    $total_rental_products = 0;
    $active_rental_products = 0;
    $rental_stats = [
        'total_rental_orders' => 0,
        'total_rental_revenue' => 0,
        'active_rental_revenue' => 0
    ];
}

// Image upload function
function uploadProductImage($file) {
    $upload_dir = "../uploads/products/";
    
    // Create uploads directory if it doesn't exist
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    // Handle single file or array of files
    if (is_array($file['name'])) {
        // Multiple files - for now, we'll just use the first one
        $file = [
            'name' => $file['name'][0],
            'type' => $file['type'][0],
            'tmp_name' => $file['tmp_name'][0],
            'error' => $file['error'][0],
            'size' => $file['size'][0]
        ];
    }
    
    // Check if file was uploaded without errors
    if (isset($file) && $file['error'] == 0) {
        // Get file info
        $file_name = basename($file['name']);
        $file_type = pathinfo($file_name, PATHINFO_EXTENSION);
        
        // Generate unique filename
        $unique_name = uniqid() . '_' . time() . '.' . $file_type;
        $target_file = $upload_dir . $unique_name;
        
        // Allow certain file formats
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
        if (in_array(strtolower($file_type), $allowed_types)) {
            // Check file size (5MB max)
            if ($file['size'] <= 5000000) {
                // Upload file
                if (move_uploaded_file($file['tmp_name'], $target_file)) {
                    return ['success' => true, 'path' => 'uploads/products/' . $unique_name];
                } else {
                    return ['success' => false, 'error' => 'Sorry, there was an error uploading your file.'];
                }
            } else {
                return ['success' => false, 'error' => 'Sorry, your file is too large. Maximum 5MB allowed.'];
            }
        } else {
            return ['success' => false, 'error' => 'Sorry, only JPG, JPEG, PNG, and GIF files are allowed.'];
        }
    } else {
        return ['success' => false, 'error' => 'No file uploaded or upload error occurred.'];
    }
}

// Log seller activity
function logSellerActivity($activity) {
    // This would typically log to a database
    Logger::info('Seller activity', ['activity' => $activity]);
}

// Function to get verification status badge
function getVerificationStatusBadge($status) {
    switch ($status) {
        case 'pending':
            return '<span class="badge bg-warning">Pending</span>';
        case 'verified':
            return '<span class="badge bg-success">Verified</span>';
        case 'rejected':
            return '<span class="badge bg-danger">Rejected</span>';
        default:
            return '<span class="badge bg-secondary">' . ucfirst($status) . '</span>';
    }
}
?>

                $min_rental_days = intval($_POST['min_rental_days']);
                $max_rental_days = intval($_POST['max_rental_days']);
                $security_deposit = floatval($_POST['security_deposit']);
                
                // Handle image uploads
                $image_url = null;
                $image_gallery = null;
                
                // Handle single image upload (main image)
                if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] == 0) {
                    $image_url = uploadProductImage($_FILES['product_image']);
                }
                
                // Handle multiple gallery images
                if (isset($_FILES['gallery_images']) && is_array($_FILES['gallery_images']['name']) && count($_FILES['gallery_images']['name']) > 0) {
                    $gallery_images = [];
                    $upload_dir = "../uploads/products/";
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    
                    for ($i = 0; $i < count($_FILES['gallery_images']['name']); $i++) {
                        if ($_FILES['gallery_images']['error'][$i] === 0) {
                            // Validate file type
                            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                            if (in_array($_FILES['gallery_images']['type'][$i], $allowed_types)) {
                                // Validate file size (5MB max)
                                if ($_FILES['gallery_images']['size'][$i] <= 5 * 1024 * 1024) {
                                    $file_extension = pathinfo($_FILES['gallery_images']['name'][$i], PATHINFO_EXTENSION);
                                    $filename = 'gallery_' . time() . '_' . uniqid() . '_' . $i . '.' . $file_extension;
                                    $target_path = $upload_dir . $filename;
                                    
                                    if (move_uploaded_file($_FILES['gallery_images']['tmp_name'][$i], $target_path)) {
                                        $gallery_images[] = 'uploads/products/' . $filename;
                                    }
                                }
                            }
                        }
                    }
                    
                    if (!empty($gallery_images)) {
                        $image_gallery = json_encode($gallery_images);
                    }
                }
                
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO products 
                        (seller_id, name, description, category_id, stock, is_rental, 
                         rental_price_per_day, rental_price_per_week, rental_price_per_month,
                         min_rental_days, max_rental_days, security_deposit, 
                         image_url, image_gallery, address, city, state, country, postal_code, 
                         payment_channel_id, status) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
                    ");
                    $stmt->execute([
                        $seller_id, $name, $description, $category_id, $stock, $is_rental,
                        $rental_price_per_day, $rental_price_per_week, $rental_price_per_month,
                        $min_rental_days, $max_rental_days, $security_deposit, 
                        $image_url, $image_gallery, $address, $city, $state, $country, $postal_code,
                        $payment_channel_id
                    ]);
                    
                    // Calculate average rental price for referral bonus and fee
                    $avg_rental_price = ($rental_price_per_day + $rental_price_per_week + $rental_price_per_month) / 3;
                    
                    // Check if this seller was referred by another seller and award 0.5% referral bonus
                    try {
                        $pdo->beginTransaction();
                        
                        // Check if this seller was referred by another seller
                        $referral_stmt = $pdo->prepare("SELECT inviter_id FROM referrals WHERE invitee_id = ? AND invitee_role = 'seller' LIMIT 1");
                        $referral_stmt->execute([$seller_id]);
                        $referral_result = $referral_stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($referral_result) {
                            $inviter_id = $referral_result['inviter_id'];
                            $referral_bonus = $avg_rental_price * 0.005; // 0.5% of average rental price
                            
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
                    
                    // Calculate 0.5% upload fee based on average rental price
                    $upload_fee = $avg_rental_price * 0.005;
                    
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
                                                $success_message = "Rental product added successfully! Please make payment for verification. You will be charged a verification fee of 0.5% of your average rental price. Payment should be made to:\n" . $channel_info . "\nCheck your payment slips section for payment instructions.";
                                            } else {
                                                $success_message = "Rental product added successfully! Please make payment for verification. You will be charged a verification fee of 0.5% of your average rental price. Check your payment slips section for payment instructions.";
                                            }
                                        } else {
                                            $success_message = "Rental product added successfully! Please make payment for verification. You will be charged a verification fee of 0.5% of your average rental price. Check your payment slips section for payment instructions.";
                                        }
                } catch (Exception $e) {
                    $error_message = "Failed to add rental product: " . $e->getMessage();
                }
                break;
                
            case 'update_rental_product':
                $product_id = intval($_POST['product_id']);
                $name = trim($_POST['name']);
                $description = trim($_POST['description']);
                $category_id = intval($_POST['category_id']);
                $stock = intval($_POST['stock']);
                $payment_channel_id = intval($_POST['payment_channel_id']);
                $rental_price_per_day = floatval($_POST['rental_price_per_day']);
                $rental_price_per_week = floatval($_POST['rental_price_per_week']);
                $rental_price_per_month = floatval($_POST['rental_price_per_month']);
                $min_rental_days = intval($_POST['min_rental_days']);
                $max_rental_days = intval($_POST['max_rental_days']);
                $security_deposit = floatval($_POST['security_deposit']);
                
                // Validate payment channel
                $channel_stmt = $pdo->prepare("SELECT id FROM payment_channels WHERE id = ? AND is_active = 1");
                $channel_stmt->execute([$payment_channel_id]);
                if (!$channel_stmt->fetch()) {
                    $error_message = "Invalid or inactive payment channel selected.";
                    break;
                }
                
                // Address fields
                $address = trim($_POST['address']);
                $city = trim($_POST['city']);
                $state = trim($_POST['state']);
                $country = trim($_POST['country']);
                $postal_code = trim($_POST['postal_code']);
                
                // Verify product belongs to seller
                $stmt = $pdo->prepare("SELECT id, image_url, image_gallery FROM products WHERE id = ? AND seller_id = ?");
                $stmt->execute([$product_id, $seller_id]);
                $existing_product = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($existing_product) {
                    // Handle image update
                    $image_url = $existing_product['image_url'];
                    $image_gallery = $existing_product['image_gallery'];
                    
                    // Check if user wants to remove current image
                    if (isset($_POST['remove_current_image']) && $_POST['remove_current_image'] == 1) {
                        // Delete the old image file
                        if ($image_url && file_exists($_SERVER['DOCUMENT_ROOT'] . '/' . $image_url)) {
                            unlink($_SERVER['DOCUMENT_ROOT'] . '/' . $image_url);
                        }
                        $image_url = null;
                    }
                    
                    // Check if new main image is uploaded
                    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] == 0) {
                        // Delete old image if exists
                        if ($image_url && file_exists($_SERVER['DOCUMENT_ROOT'] . '/' . $image_url)) {
                            unlink($_SERVER['DOCUMENT_ROOT'] . '/' . $image_url);
                        }
                        // Upload new image
                        $image_url = uploadProductImage($_FILES['product_image']);
                    }
                    
                    // Handle gallery images update
                    $existing_gallery = !empty($image_gallery) ? json_decode($image_gallery, true) : [];
                    
                    // Handle new gallery image uploads
                    if (isset($_FILES['gallery_images']) && is_array($_FILES['gallery_images']['name']) && count($_FILES['gallery_images']['name']) > 0) {
                        $upload_dir = "../uploads/products/";
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0777, true);
                        }
                        
                        for ($i = 0; $i < count($_FILES['gallery_images']['name']); $i++) {
                            if ($_FILES['gallery_images']['error'][$i] === 0) {
                                // Validate file type
                                $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                                if (in_array($_FILES['gallery_images']['type'][$i], $allowed_types)) {
                                    // Validate file size (5MB max)
                                    if ($_FILES['gallery_images']['size'][$i] <= 5 * 1024 * 1024) {
                                        $file_extension = pathinfo($_FILES['gallery_images']['name'][$i], PATHINFO_EXTENSION);
                                        $filename = 'gallery_' . time() . '_' . uniqid() . '_' . $i . '.' . $file_extension;
                                        $target_path = $upload_dir . $filename;
                                        
                                        if (move_uploaded_file($_FILES['gallery_images']['tmp_name'][$i], $target_path)) {
                                            $existing_gallery[] = 'uploads/products/' . $filename;
                                        }
                                    }
                                }
                            }
                        }
                    }
                    
                    // Handle gallery image removal
                    if (isset($_POST['remove_gallery_images']) && is_array($_POST['remove_gallery_images'])) {
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
                    }
                    
                    // Update gallery JSON
                    if (empty($existing_gallery)) {
                        $image_gallery_json = null;
                    } else {
                        $image_gallery_json = json_encode($existing_gallery);
                    }
                    
                    $stmt = $pdo->prepare("
                        UPDATE products SET 
                        name = ?, description = ?, category_id = ?, stock = ?,
                        rental_price_per_day = ?, rental_price_per_week = ?, rental_price_per_month = ?,
                        min_rental_days = ?, max_rental_days = ?, security_deposit = ?, 
                        image_url = ?, image_gallery = ?, address = ?, city = ?, state = ?, country = ?, postal_code = ?,
                        payment_channel_id = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $name, $description, $category_id, $stock,
                        $rental_price_per_day, $rental_price_per_week, $rental_price_per_month,
                        $min_rental_days, $max_rental_days, $security_deposit, 
                        $image_url, $image_gallery_json, $address, $city, $state, $country, $postal_code,
                        $payment_channel_id, $product_id
                    ]);
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
                                                $success_message = "Rental product updated successfully! Payment should be made to:\n" . $channel_info . "\nCheck your payment slips section for payment instructions if you've changed the payment channel.";
                                            } else {
                                                $success_message = "Rental product updated successfully!";
                                            }
                                        } else {
                                            $success_message = "Rental product updated successfully!";
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
                            logSellerActivity("Uploaded payment slip for rental product ID: $product_id");
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

// Image upload function
function uploadProductImage($file) {
    $upload_dir = "../uploads/products/";
    
    // Create uploads directory if it doesn't exist
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    // Handle single file or array of files
    if (is_array($file['name'])) {
        // Multiple files - for now, we'll just use the first one
        $file = [
            'name' => $file['name'][0],
            'type' => $file['type'][0],
            'tmp_name' => $file['tmp_name'][0],
            'error' => $file['error'][0],
            'size' => $file['size'][0]
        ];
    }
    
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("File upload error: " . $file['error']);
    }
    
    // Validate file type
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($file['type'], $allowed_types)) {
        throw new Exception("Only JPG, PNG, GIF, and WebP images are allowed.");
    }
    
    // Validate file size (2MB max)
    if ($file['size'] > 5 * 1024 * 1024) {
        throw new Exception("File size must be less than 5MB.");
    }
    
    // Generate unique filename
    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '_' . time() . '.' . $file_extension;
    $filepath = $upload_dir . $filename;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        throw new Exception("Failed to move uploaded file.");
    }
    
    return "uploads/products/" . $filename;
}

// Get seller's rental products
$rental_products_stmt = $pdo->prepare("
    SELECT p.*, c.name as category_name,
           COUNT(DISTINCT ro.id) as total_rentals,
           COALESCE(SUM(CASE WHEN ro.status = 'completed' THEN ro.total_rental_amount ELSE 0 END), 0) as total_rental_revenue
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN rental_orders ro ON p.id = ro.product_id
    WHERE p.seller_id = ? AND p.is_rental = 1
    GROUP BY p.id
    ORDER BY p.created_at DESC
");

$rental_products_stmt->execute([$seller_id]);
$rental_products = $rental_products_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get rental statistics
$rental_stats_stmt = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT p.id) as total_rental_products,
        COUNT(DISTINCT ro.id) as total_rental_orders,
        COUNT(DISTINCT CASE WHEN ro.status = 'active' THEN ro.id END) as active_rentals,
        COUNT(DISTINCT CASE WHEN ro.status = 'pending' THEN ro.id END) as pending_rentals,
        COALESCE(SUM(ro.total_rental_amount), 0) as total_rental_revenue,
        COALESCE(SUM(CASE WHEN ro.status = 'active' THEN ro.total_rental_amount ELSE 0 END), 0) as active_rental_revenue
    FROM products p
    LEFT JOIN rental_orders ro ON p.id = ro.product_id
    WHERE p.seller_id = ? AND p.is_rental = 1
");

$rental_stats_stmt->execute([$seller_id]);
$rental_stats = $rental_stats_stmt->fetch(PDO::FETCH_ASSOC);

// Get categories for forms
$categories_stmt = $pdo->prepare("SELECT id, name FROM categories WHERE status = 'active' ORDER BY name");
$categories_stmt->execute();
$categories = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get payment channels for forms
$channels_stmt = $pdo->prepare("SELECT id, name, type, account_name, account_number FROM payment_channels WHERE is_active = 1 ORDER BY name");
$channels_stmt->execute();
$payment_channels = $channels_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get seller information
$seller_info_stmt = $pdo->prepare("SELECT first_name, last_name, store_name FROM users WHERE id = ?");
$seller_info_stmt->execute([$seller_id]);
$seller_info = $seller_info_stmt->fetch(PDO::FETCH_ASSOC);

// Log seller activity
logSellerActivity("Accessed rental products management");

function logSellerActivity($activity) {
    global $pdo, $seller_id;
    $stmt = $pdo->prepare("INSERT INTO seller_activities (seller_id, activity, ip_address) VALUES (?, ?, ?)");
    $stmt->execute([$seller_id, $activity, $_SERVER['REMOTE_ADDR']]);
}

function formatCurrency($amount) {
    return '$' . number_format($amount, 2);
}

function getRentalStatusBadge($status) {
    switch ($status) {
        case 'active': return '<span class="badge bg-success">Active</span>';
        case 'pending': return '<span class="badge bg-warning">Pending</span>';
        case 'inactive': return '<span class="badge bg-secondary">Inactive</span>';
        case 'completed': return '<span class="badge bg-info">Completed</span>';
        case 'overdue': return '<span class="badge bg-danger">Overdue</span>';
        default: return '<span class="badge bg-secondary">' . ucfirst($status) . '</span>';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rental Products - BSDO Sale</title>
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
        }
        
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .sidebar {
            background-color: var(--dark-color);
            color: white;
            min-height: calc(100vh - 56px);
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
        
        .table th {
            cursor: pointer;
            user-select: none;
        }
        
        .table th:hover {
            background-color: #f8f9fa;
        }
        
        .filter-badge {
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .filter-badge.active {
            transform: scale(1.1);
            box-shadow: 0 0 10px rgba(0,0,0,0.2);
        }
        
        .bulk-actions {
            background: #f8f9fa;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .stats-card {
            border-left: 4px solid;
            transition: transform 0.2s;
        }
        
        .stats-card:hover {
            transform: translateY(-2px);
        }
        
        .stats-card.primary { border-left-color: var(--primary-color); }
        .stats-card.success { border-left-color: var(--secondary-color); }
        .stats-card.warning { border-left-color: var(--warning-color); }
        
        .seller-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }
    </style>
    <style>
        .rental-badge {
            background: linear-gradient(135deg, #36b9cc, #2c9faf);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .rental-card {
            border-left: 4px solid #36b9cc;
        }
        
        .price-option {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        /* Enhanced Product Card Styling */
        .product-card {
            transition: all 0.3s ease;
            border: 1px solid #e3e6f0;
            border-radius: 0.5rem;
            overflow: hidden;
            box-shadow: 0 0.15rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        
        .product-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        
        .product-thumbnail {
            height: 150px;
            background: linear-gradient(135deg, #f8f9fc, #e9ecef);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--dark-color);
        }
        
        .product-thumbnail img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .product-info {
            padding: 1rem;
        }
        
        .product-price {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--primary-color);
        }
        
        .product-stock {
            font-size: 0.875rem;
        }
        
        .status-badge {
            padding: 0.25em 0.4em;
            font-size: 75%;
            font-weight: 700;
            line-height: 1;
            text-align: center;
            white-space: nowrap;
            vertical-align: baseline;
            border-radius: 0.35rem;
        }
        
        .status-active {
            background-color: #1cc88a;
            color: #fff;
        }
        
        .status-pending {
            background-color: #f6c23e;
            color: #fff;
        }
        
        .status-inactive {
            background-color: #e74a3b;
            color: #fff;
        }
        
        .action-buttons .btn {
            margin-right: 0.25rem;
            margin-bottom: 0.25rem;
        }
        
        /* Modal Styles */
        .modal-content {
            border-radius: 0.5rem;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        
        .modal-header {
            background: linear-gradient(135deg, var(--primary-color), #667eea);
            color: white;
            border-radius: 0.5rem 0.5rem 0 0;
        }
        
        /* Form Styles */
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), #667eea);
            border: none;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #3a5fd0, #556bd1);
        }
        
        /* Rental-specific styles */
        .rental-pricing-card {
            background: linear-gradient(135deg, #36b9cc, #2c9faf);
            color: white;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .rental-price {
            font-size: 1.5rem;
            font-weight: 700;
        }
        
        /* Ensure remove buttons are visible on mobile devices */
        @media (max-width: 768px) {
            .btn-danger.btn-sm.position-absolute {
                opacity: 1 !important;
                visibility: visible !important;
            }
        }
    </style>

</head>
<body>
    <!-- Navigation (same as other seller pages) -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <!-- Mobile menu button -->
            <button class="btn btn-link text-white d-lg-none me-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar">
                <i class="fas fa-bars"></i>
            </button>

            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-store me-2"></i>
                <strong>BSDO Seller</strong>
            </a>
            <div class="d-flex align-items-center">
                <div class="dropdown">
                    <a class="nav-link dropdown-toggle text-white d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown">
                        <div class="bg-white rounded-circle me-2" style="width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;">
                            <span class="text-primary fw-bold"><?php echo strtoupper(substr($seller_info['first_name'], 0, 1)); ?></span>
                        </div>
                        <span><?php echo htmlspecialchars($seller_info['first_name'] . ' ' . $seller_info['last_name']); ?></span>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                        <li><a class="dropdown-item" href="settings.php"><i class="fas fa-cog me-2"></i>Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <!-- Mobile Sidebar -->
    <div class="offcanvas offcanvas-start" tabindex="-1" id="mobileSidebar">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title">Menu</h5>
            <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="offcanvas-body">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link" href="dashboard.php">
                        <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="products.php">
                        <i class="fas fa-box me-2"></i>Products
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="rental_products.php">
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
                    <a class="nav-link" href="settings.php">
                        <i class="fas fa-cog me-2"></i>Settings
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
            </ul>
        </div>
    </div>

    <div class="container-fluid ">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-lg-2 sidebar d-none d-lg-block ">
                <div class="pt-4">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">
                                <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="products.php">
                                <i class="fas fa-box me-2"></i>Products
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="rental_products.php">
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
            <div class="col-lg-10 col-md-12 p-4">
                <!-- Alerts -->
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

                <!-- Page Header -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h2 class="mb-1"><i class="fas fa-calendar-alt me-2"></i>Rental Products</h2>
                                <p class="text-muted mb-0">Manage your rental products and bookings</p>
                            </div>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRentalProductModal">
                                <i class="fas fa-plus me-2"></i>Add Rental Product
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Rental Statistics -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stats-card primary h-100">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Rental Products</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $rental_stats['total_rental_products']; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-calendar-alt fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stats-card success h-100">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Rentals</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $rental_stats['total_rental_orders']; ?></div>
                                        <div class="mt-2 text-xs text-muted">
                                            <span class="text-success"><?php echo $rental_stats['active_rentals']; ?> active</span> | 
                                            <span class="text-warning"><?php echo $rental_stats['pending_rentals']; ?> pending</span>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-shopping-cart fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stats-card warning h-100">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Rental Revenue</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">$<?php echo number_format($rental_stats['total_rental_revenue'], 2); ?></div>
                                        <div class="mt-2 text-xs text-muted">
                                            Active: $<?php echo number_format($rental_stats['active_rental_revenue'], 2); ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stats-card info h-100">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Avg. per Rental</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $rental_stats['total_rental_orders'] > 0 ? 
                                                '$' . number_format($rental_stats['total_rental_revenue'] / $rental_stats['total_rental_orders'], 2) : '$0.00'; ?>
                                        </div>
                                        <div class="mt-2 text-xs text-muted">Per rental average</div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-chart-line fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Rental Products List -->
                <div class="card shadow">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-primary">Your Rental Products</h6>
                        <span class="badge bg-primary"><?php echo count($rental_products); ?> products</span>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($rental_products)): ?>
                            <div class="table-responsive">
                                <table class="table table-borderless table-hover">
                                    <thead>
                                        <tr>
                                            <th>Product</th>
                                            <th>Rental Prices</th>
                                            <th>Rental Period</th>
                                            <th>Stock</th>
                                            <th>Total Rentals</th>
                                            <th>Revenue</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($rental_products as $product): ?>
                                            <tr>
                                                <td>
                                                    <div class="fw-bold"><?php echo htmlspecialchars($product['name']); ?></div>
                                                    <small class="text-muted"><?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?></small>
                                                    <div><span class="rental-badge">Rental</span></div>
                                                </td>
                                                
                                                <td>
                                                    <div class="small">
                                                        <div>Day: $<?php echo number_format($product['rental_price_per_day'], 2); ?></div>
                                                        <div>Week: $<?php echo number_format($product['rental_price_per_week'], 2); ?></div>
                                                        <div>Month: $<?php echo number_format($product['rental_price_per_month'], 2); ?></div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="small">
                                                        <div>Min: <?php echo $product['min_rental_days']; ?> days</div>
                                                        <div>Max: <?php echo $product['max_rental_days']; ?> days</div>
                                                        <div>Deposit: $<?php echo number_format($product['security_deposit'], 2); ?></div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="<?php echo $product['stock'] < 3 ? 'text-danger fw-bold' : 'text-success'; ?>">
                                                        <?php echo number_format($product['stock']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo number_format($product['total_rentals']); ?></td>
                                                <td class="fw-bold text-success">$<?php echo number_format($product['total_rental_revenue'], 2); ?></td>
                                                <td>
                                                    <?php 
                                                    // Display verification status
                                                    if (!empty($product['payment_channel_id'])): ?>
                                                        <span class="badge <?php echo !empty($product['verification_payment_status']) && $product['verification_payment_status'] === 'paid' ? 'bg-success' : ($product['verification_payment_status'] === 'pending' ? 'bg-warning' : 'bg-danger'); ?>">
                                                            <?php echo !empty($product['verification_payment_status']) ? ucfirst($product['verification_payment_status']) : 'Pending'; ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">No Channel</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#editRentalProductModal" 
                                                            onclick="editRentalProduct(<?php echo htmlspecialchars(json_encode($product)); ?>)">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <?php if (empty($product['verification_payment_status']) || $product['verification_payment_status'] !== 'paid'): ?>
                                                        <button class="btn btn-sm btn-outline-success" 
                                                                onclick="uploadPaymentSlip(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars($product['name'], ENT_QUOTES); ?>', <?php echo $product['rental_price_per_day']; ?>, <?php echo ($product['rental_price_per_day'] * $payment_verification_rate / 100); ?>)">
                                                            <i class="fas fa-money-check"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-calendar-alt fa-3x text-muted mb-3"></i>
                                <h4 class="text-muted">No Rental Products Yet</h4>
                                <p class="text-muted">Start by adding your first rental product to begin accepting rental bookings.</p>
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRentalProductModal">
                                    <i class="fas fa-plus me-2"></i>Add Your First Rental Product
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Rental Product Modal -->
<div class="modal fade" id="addRentalProductModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Rental Product</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="add_rental_product">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Product Name *</label>
                            <input type="text" class="form-control" name="name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Category *</label>
                            <select class="form-control" name="category_id" required>
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="3"></textarea>
                    </div>

                    <!-- Image Upload Section -->
                    <div class="mb-3">
                        <label class="form-label">Main Product Image</label>
                        <input type="file" class="form-control" name="product_image" accept="image/*">
                        <div class="form-text">Primary image for your product. Supported formats: JPG, PNG, GIF. Max size: 5MB.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Gallery Images</label>
                        <input type="file" class="form-control" name="gallery_images[]" multiple accept="image/*">
                        <div class="form-text">You can upload multiple images for your product gallery. Supported formats: JPG, PNG, GIF. Max size: 5MB per image.</div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Stock Quantity *</label>
                            <input type="number" class="form-control" name="stock" min="1" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Min Rental Days *</label>
                            <input type="number" class="form-control" name="min_rental_days" min="1" value="1" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Max Rental Days *</label>
                            <input type="number" class="form-control" name="max_rental_days" min="1" value="30" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Payment Channel *</label>
                            <select class="form-control" name="payment_channel_id" required>
                                <option value="">Select Payment Channel</option>
                                <?php foreach ($payment_channels as $channel): ?>
                                    <option value="<?php echo $channel['id']; ?>">
                                        <?php echo htmlspecialchars($channel['name']); ?>
                                        (<?php 
                                        $type_labels = [
                                            'bank' => 'Bank',
                                            'mobile_money' => 'Mobile Money',
                                            'paypal' => 'PayPal',
                                            'cryptocurrency' => 'Crypto',
                                            'other' => 'Other'
                                        ];
                                        echo $type_labels[$channel['type']] ?? $channel['type'];
                                        if (!empty($channel['account_number'])) {
                                            echo ' - ' . htmlspecialchars($channel['account_number']);
                                        }
                                        ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Select where to pay the verification fee</div>
                        </div>
                    </div>
                    
                    <h6 class="mb-3">Rental Pricing</h6>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Price Per Day ($) *</label>
                            <input type="number" class="form-control" name="rental_price_per_day" step="0.01" min="0" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Price Per Week ($)</label>
                            <input type="number" class="form-control" name="rental_price_per_week" step="0.01" min="0">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Price Per Month ($)</label>
                            <input type="number" class="form-control" name="rental_price_per_month" step="0.01" min="0">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Security Deposit ($)</label>
                        <input type="number" class="form-control" name="security_deposit" step="0.01" min="0">
                        <div class="form-text">Refundable security deposit for the rental</div>
                    </div>
                    
                    <hr>
                    <h6>Product Location</h6>
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Address</label>
                            <input type="text" class="form-control" name="address" placeholder="Street address">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">City</label>
                            <input type="text" class="form-control" name="city" placeholder="City">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">State/Province</label>
                            <input type="text" class="form-control" name="state" placeholder="State or Province">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Postal Code</label>
                            <input type="text" class="form-control" name="postal_code" placeholder="Postal Code">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Country</label>
                            <input type="text" class="form-control" name="country" placeholder="Country">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Rental Product</button>
                </div>
            </form>
        </div>
    </div>
</div>

    <!-- Edit Rental Product Modal -->
<div class="modal fade" id="editRentalProductModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Rental Product</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="update_rental_product">
                    <input type="hidden" name="product_id" id="editRentalProductId">
                    
                    <!-- Current Image Display -->
                    <div class="mb-3" id="currentImageSection" style="display: none;">
                        <label class="form-label">Current Main Image</label>
                        <div class="current-image-container">
                            <img id="currentProductImage" src="" alt="Current product image" class="img-thumbnail" style="max-height: 200px;">
                            <div class="mt-2">
                                <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeCurrentImage()">
                                    <i class="fas fa-trash me-1"></i>Remove Current Image
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Product Name *</label>
                            <input type="text" class="form-control" name="name" id="editRentalProductName" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Category *</label>
                            <select class="form-control" name="category_id" id="editRentalProductCategory" required>
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" id="editRentalProductDescription" rows="3"></textarea>
                    </div>

                    <!-- Image Upload for Edit -->
                    <div class="mb-3">
                        <label class="form-label">Update Main Product Image</label>
                        <input type="file" class="form-control" name="product_image" accept="image/*">
                        <div class="form-text">Leave empty to keep current image. Supported formats: JPG, PNG, GIF. Max size: 5MB.</div>
                        <input type="hidden" name="remove_current_image" id="removeCurrentImageFlag" value="0">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Add More Gallery Images</label>
                        <input type="file" class="form-control" name="gallery_images[]" multiple accept="image/*">
                        <div class="form-text">Upload additional images for your product gallery</div>
                        <div id="currentGallery" class="mt-2"></div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Stock Quantity *</label>
                            <input type="number" class="form-control" name="stock" id="editRentalProductStock" min="1" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Min Rental Days *</label>
                            <input type="number" class="form-control" name="min_rental_days" id="editRentalProductMinDays" min="1" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Max Rental Days *</label>
                            <input type="number" class="form-control" name="max_rental_days" id="editRentalProductMaxDays" min="1" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Payment Channel *</label>
                            <select class="form-control" name="payment_channel_id" id="editRentalProductPaymentChannel" required>
                                <option value="">Select Payment Channel</option>
                                <?php foreach ($payment_channels as $channel): ?>
                                    <option value="<?php echo $channel['id']; ?>">
                                        <?php echo htmlspecialchars($channel['name']); ?>
                                        (<?php 
                                        $type_labels = [
                                            'bank' => 'Bank',
                                            'mobile_money' => 'Mobile Money',
                                            'paypal' => 'PayPal',
                                            'cryptocurrency' => 'Crypto',
                                            'other' => 'Other'
                                        ];
                                        echo $type_labels[$channel['type']] ?? $channel['type'];
                                        if (!empty($channel['account_number'])) {
                                            echo ' - ' . htmlspecialchars($channel['account_number']);
                                        }
                                        ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <h6 class="mb-3">Rental Pricing</h6>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Price Per Day ($) *</label>
                            <input type="number" class="form-control" name="rental_price_per_day" id="editRentalProductPricePerDay" step="0.01" min="0" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Price Per Week ($)</label>
                            <input type="number" class="form-control" name="rental_price_per_week" id="editRentalProductPricePerWeek" step="0.01" min="0">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Price Per Month ($)</label>
                            <input type="number" class="form-control" name="rental_price_per_month" id="editRentalProductPricePerMonth" step="0.01" min="0">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Security Deposit ($)</label>
                        <input type="number" class="form-control" name="security_deposit" id="editRentalProductSecurityDeposit" step="0.01" min="0">
                        <div class="form-text">Refundable security deposit for the rental</div>
                    </div>
                    
                    <hr>
                    <h6>Product Location</h6>
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Address</label>
                            <input type="text" class="form-control" name="address" id="editRentalProductAddress" placeholder="Street address">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">City</label>
                            <input type="text" class="form-control" name="city" id="editRentalProductCity" placeholder="City">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">State/Province</label>
                            <input type="text" class="form-control" name="state" id="editRentalProductState" placeholder="State or Province">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Postal Code</label>
                            <input type="text" class="form-control" name="postal_code" id="editRentalProductPostalCode" placeholder="Postal Code">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Country</label>
                            <input type="text" class="form-control" name="country" id="editRentalProductCountry" placeholder="Country">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Rental Product</button>
                </div>
            </form>
        </div>
    </div>
</div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Edit Rental Product Function
        function editRentalProduct(product) {
            document.getElementById('editRentalProductId').value = product.id;
            document.getElementById('editRentalProductName').value = product.name;
            document.getElementById('editRentalProductDescription').value = product.description || '';
            document.getElementById('editRentalProductCategory').value = product.category_id;
            document.getElementById('editRentalProductStock').value = product.stock;
            document.getElementById('editRentalProductMinDays').value = product.min_rental_days;
            document.getElementById('editRentalProductMaxDays').value = product.max_rental_days;
            document.getElementById('editRentalProductPricePerDay').value = product.rental_price_per_day;
            document.getElementById('editRentalProductPricePerWeek').value = product.rental_price_per_week || '';
            document.getElementById('editRentalProductPricePerMonth').value = product.rental_price_per_month || '';
            document.getElementById('editRentalProductSecurityDeposit').value = product.security_deposit || '';
            document.getElementById('editRentalProductPaymentChannel').value = product.payment_channel_id || '';
            
            // Address fields
            document.getElementById('editRentalProductAddress').value = product.address || '';
            document.getElementById('editRentalProductCity').value = product.city || '';
            document.getElementById('editRentalProductState').value = product.state || '';
            document.getElementById('editRentalProductPostalCode').value = product.postal_code || '';
            document.getElementById('editRentalProductCountry').value = product.country || '';
            
            // Show current image
            const currentImageSection = document.getElementById('currentImageSection');
            const currentProductImage = document.getElementById('currentProductImage');
            if (product.image_url) {
                currentProductImage.src = '../' + product.image_url;
                currentImageSection.style.display = 'block';
            } else {
                currentImageSection.style.display = 'none';
            }
            
            // Show current gallery images
            const currentGallery = document.getElementById('currentGallery');
            if (product.image_gallery) {
                try {
                    const galleryImages = JSON.parse(product.image_gallery);
                    if (Array.isArray(galleryImages) && galleryImages.length > 0) {
                        let galleryHtml = '<div class="mt-2"><strong>Current Gallery Images:</strong></div>';
                        galleryImages.forEach((img, index) => {
                            galleryHtml += `
                                <div class="d-inline-block position-relative me-2">
                                    <img src="../${img}" class="img-thumbnail" style="width: 60px; height: 60px;" alt="Gallery Image ${index + 1}">
                                    <button type="button" class="btn btn-danger btn-sm position-absolute top-0 end-0" 
                                            onclick="removeGalleryImage('${img}', ${product.id})" 
                                            style="padding: 2px 5px; font-size: 0.7rem;">
                                        ×
                                    </button>
                                </div>
                            `;
                        });
                        currentGallery.innerHTML = galleryHtml;
                    } else {
                        currentGallery.innerHTML = '<span class="text-muted">No gallery images uploaded</span>';
                    }
                } catch (e) {
                    currentGallery.innerHTML = '<span class="text-muted">No gallery images uploaded</span>';
                }
            } else {
                currentGallery.innerHTML = '<span class="text-muted">No gallery images uploaded</span>';
            }
        }

        // Remove current image function
        function removeCurrentImage() {
            if (confirm('Are you sure you want to remove the current image?')) {
                document.getElementById('removeCurrentImageFlag').value = '1';
                document.getElementById('currentImageSection').style.display = 'none';
            }
        }

        // Function to remove gallery images
        function removeGalleryImage(imagePath, productId) {
            if (confirm('Are you sure you want to remove this gallery image?')) {
                // Add hidden input to form for removal
                const form = document.querySelector('#editRentalProductModal form');
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'remove_gallery_images[]';
                hiddenInput.value = imagePath;
                form.appendChild(hiddenInput);
                
                // Remove the image preview
                const imageElement = event.target.closest('.d-inline-block');
                if (imageElement) {
                    imageElement.remove();
                }
            }
        }

        // Upload payment slip function
        function uploadPaymentSlip(productId, productName, productPrice, verificationAmount) {
            // Create modal for payment slip upload
            const modalHtml = `
                <div class="modal fade" id="paymentSlipModal" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Upload Payment Slip</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <form method="POST" enctype="multipart/form-data">
                                <div class="modal-body">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                    <input type="hidden" name="action" value="upload_payment_slip">
                                    <input type="hidden" name="product_id" value="${productId}">
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Product</label>
                                        <div class="form-control-plaintext fw-bold">${productName}</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Product Price</label>
                                        <div class="form-control-plaintext">$${productPrice.toFixed(2)}</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Verification Amount (<?php echo ($payment_verification_rate * 100); ?>%)</label>
                                        <input type="number" class="form-control" name="amount" step="0.01" min="0" value="${verificationAmount.toFixed(2)}" required>
                                        <div class="form-text">Enter the amount you paid for verification</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Payment Slip *</label>
                                        <input type="file" class="form-control" name="payment_slip" accept="image/*,application/pdf" required>
                                        <div class="form-text">Upload your payment slip or receipt. Supported formats: JPG, PNG, GIF, PDF. Max size: 5MB.</div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-primary">Upload Payment Slip</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            `;
            
            // Remove existing modal if any
            const existingModal = document.getElementById('paymentSlipModal');
            if (existingModal) {
                existingModal.remove();
            }
            
            // Add new modal to body
            document.body.insertAdjacentHTML('beforeend', modalHtml);
            
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('paymentSlipModal'));
            modal.show();
            
            // Remove modal from DOM when hidden
            document.getElementById('paymentSlipModal').addEventListener('hidden.bs.modal', function () {
                this.remove();
            });
        }

        // Auto-submit category change
        document.getElementById('categoryFilter')?.addEventListener('change', function() {
            this.form.submit();
        });
    </script>
    
    <!-- Rental Product Fee Calculator -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Add event listeners to the add rental product form
            const addRentalForm = document.querySelector('#addRentalProductModal form');
            if (addRentalForm) {
                const dayPriceInput = addRentalForm.querySelector('input[name="rental_price_per_day"]');
                const weekPriceInput = addRentalForm.querySelector('input[name="rental_price_per_week"]');
                const monthPriceInput = addRentalForm.querySelector('input[name="rental_price_per_month"]');
                
                const feeDisplay = document.createElement('div');
                feeDisplay.className = 'alert alert-info mt-3';
                feeDisplay.style.display = 'none';
                
                // Insert fee display after the month price input
                if (monthPriceInput && monthPriceInput.parentNode) {
                    monthPriceInput.parentNode.appendChild(feeDisplay);
                }
                
                // Function to calculate average price and fee
                function calculateFee() {
                    const dayPrice = parseFloat(dayPriceInput?.value) || 0;
                    const weekPrice = parseFloat(weekPriceInput?.value) || 0;
                    const monthPrice = parseFloat(monthPriceInput?.value) || 0;
                    
                    // Calculate average price
                    const avgPrice = (dayPrice + weekPrice + monthPrice) / 3;
                    const fee = avgPrice * 0.005; // 0.5% fee
                    const total = avgPrice + fee;
                    
                    if (avgPrice > 0) {
                        feeDisplay.innerHTML = `
                            <h6>Payment Verification Fee:</h6>
                            <p>Average Rental Price: $${avgPrice.toFixed(2)}</p>
                            <p>Verification Fee (0.5%): <strong>$${fee.toFixed(2)}</strong></p>
                            <p class="mb-0">Total Amount Due: <strong>$${total.toFixed(2)}</strong></p>
                            <small class="text-muted">You will need to pay this fee to your selected payment channel after submission.</small>
                        `;
                        feeDisplay.style.display = 'block';
                    } else {
                        feeDisplay.style.display = 'none';
                    }
                    
                    return { avgPrice, fee, total };
                }
                
                // Calculate fee when any price changes
                dayPriceInput?.addEventListener('input', calculateFee);
                weekPriceInput?.addEventListener('input', calculateFee);
                monthPriceInput?.addEventListener('input', calculateFee);
                
                // Handle form submission
                addRentalForm.addEventListener('submit', function(e) {
                    const { avgPrice, fee, total } = calculateFee();
                    if (avgPrice > 0) {
                        // Show confirmation modal with fee details
                        const modalAvgPrice = document.getElementById('rentalModalAvgPrice');
                        const modalFeeAmount = document.getElementById('rentalModalFeeAmount');
                        const modalTotalAmount = document.getElementById('rentalModalTotalAmount');
                        
                        if (modalAvgPrice) modalAvgPrice.textContent = avgPrice.toFixed(2);
                        if (modalFeeAmount) modalFeeAmount.textContent = fee.toFixed(2);
                        if (modalTotalAmount) modalTotalAmount.textContent = total.toFixed(2);
                        
                        // Prevent form submission and show modal first
                        e.preventDefault();
                        
                        // Show the modal
                        const modalElement = document.getElementById('rentalFeeInfoModal');
                        if (modalElement) {
                            const modal = new bootstrap.Modal(modalElement);
                            modal.show();
                            
                            // Add event listener to the modal to submit form when closed
                            modalElement.addEventListener('hidden.bs.modal', function() {
                                addRentalForm.removeEventListener('submit', arguments.callee);
                                addRentalForm.submit();
                            }, {once: true});
                        }
                    }
                });
            }
        });
    </script>
    
    <!-- Fee Information Modal -->
    <div class="modal fade" id="rentalFeeInfoModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Payment Verification Fee</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>You will be charged a verification fee of <strong>0.5%</strong> of your rental product's average price.</p>
                    <div class="alert alert-info">
                        <h6>Fee Calculation:</h6>
                        <p>Average Rental Price: $<span id="rentalModalAvgPrice">0.00</span></p>
                        <p>Verification Fee (0.5%): $<span id="rentalModalFeeAmount">0.00</span></p>
                        <hr>
                        <p class="mb-0"><strong>Total Amount Due: $<span id="rentalModalTotalAmount">0.00</span></strong></p>
                    </div>
                    <p>After submitting this rental product, you will need to make a payment of the verification fee to the selected payment channel.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
</body>
</html>