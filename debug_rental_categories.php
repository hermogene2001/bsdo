<?php
session_start();
require_once 'config.php';
require_once 'models/RentalProductModel.php';
require_once 'utils/SecurityUtils.php';

// For testing purposes, let's simulate a logged-in seller
// This is just for testing - in real scenario, the user should be logged in
$_SESSION['user_id'] = 1; // Test seller ID
$_SESSION['user_role'] = 'seller';

// Initialize model
$rentalModel = new RentalProductModel($pdo);

$seller_id = $_SESSION['user_id'];

// Get categories and payment channels for forms
$categories = $rentalModel->getCategories();
$payment_channels = $rentalModel->getPaymentChannels();

echo "Debug rental_products.php categories loading:\n";
echo "Seller ID: $seller_id\n";
echo "Categories count: " . count($categories) . "\n";
echo "Payment channels count: " . count($payment_channels) . "\n";

if (count($categories) > 0) {
    echo "\nCategories loaded:\n";
    foreach ($categories as $category) {
        echo "- ID: {$category['id']}, Name: {$category['name']}\n";
    }
} else {
    echo "\nNo categories loaded!\n";
}

if (count($payment_channels) > 0) {
    echo "\nPayment channels loaded:\n";
    foreach ($payment_channels as $channel) {
        echo "- ID: {$channel['id']}, Name: {$channel['name']}\n";
    }
} else {
    echo "\nNo payment channels loaded!\n";
}

// Test the HTML output that would be generated
echo "\nHTML output for category dropdown:\n";
echo '<select class="form-control" name="category_id" required>' . "\n";
echo '    <option value="">Select Category</option>' . "\n";
foreach ($categories as $category) {
    echo '    <option value="' . $category['id'] . '">' . htmlspecialchars($category['name']) . '</option>' . "\n";
}
echo '</select>' . "\n";
?>