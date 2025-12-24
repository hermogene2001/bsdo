<?php
require_once 'config.php';
require_once 'models/RentalProductModel.php';

// Initialize model
$rentalModel = new RentalProductModel($pdo);

// Get categories using the model method
$categories = $rentalModel->getCategories();

echo "Categories returned by model:\n";
echo "Count: " . count($categories) . "\n";

if (count($categories) > 0) {
    foreach ($categories as $category) {
        echo "- ID: {$category['id']}, Name: {$category['name']}\n";
    }
} else {
    echo "No categories returned by the model.\n";
}

// Let's also test the raw query to make sure the data is accessible
echo "\nTesting raw query:\n";
try {
    $stmt = $pdo->prepare("SELECT id, name FROM categories WHERE status = 'active' ORDER BY name");
    $stmt->execute();
    $rawCategories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Raw query returned: " . count($rawCategories) . " categories\n";
    foreach ($rawCategories as $category) {
        echo "- ID: {$category['id']}, Name: {$category['name']}\n";
    }
} catch (Exception $e) {
    echo "Error with raw query: " . $e->getMessage() . "\n";
}
?>