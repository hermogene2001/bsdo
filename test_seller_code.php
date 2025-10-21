<?php
// Test page to verify seller code functionality
require_once 'config.php';

// Check if seller code cookie exists
$seller_code_cookie = $_COOKIE['seller_code'] ?? 'Not set';

// Check if user is logged in as seller
$is_seller = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'seller';

// Get seller code from database if logged in
$seller_code_db = '';
if ($is_seller && isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT seller_code FROM seller_codes WHERE seller_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $seller_code_db = $row['seller_code'] ?? 'Not found';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seller Code Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h1>Seller Code Test Page</h1>
        
        <div class="card mt-4">
            <div class="card-header">
                <h3>Cookie Information</h3>
            </div>
            <div class="card-body">
                <p><strong>Seller Code in Cookie:</strong> <?php echo htmlspecialchars($seller_code_cookie); ?></p>
                <p><strong>Cookie Status:</strong> 
                    <?php if ($seller_code_cookie !== 'Not set'): ?>
                        <span class="text-success">Cookie is set</span>
                    <?php else: ?>
                        <span class="text-danger">Cookie is not set</span>
                    <?php endif; ?>
                </p>
            </div>
        </div>
        
        <div class="card mt-4">
            <div class="card-header">
                <h3>Session Information</h3>
            </div>
            <div class="card-body">
                <p><strong>User Role:</strong> <?php echo htmlspecialchars($_SESSION['user_role'] ?? 'Not logged in'); ?></p>
                <?php if ($is_seller): ?>
                    <p><strong>Seller Code from Database:</strong> <?php echo htmlspecialchars($seller_code_db); ?></p>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="card mt-4">
            <div class="card-header">
                <h3>All Cookies</h3>
            </div>
            <div class="card-body">
                <pre><?php print_r($_COOKIE); ?></pre>
            </div>
        </div>
        
        <a href="index.php" class="btn btn-primary mt-3">Back to Home</a>
    </div>
</body>
</html>