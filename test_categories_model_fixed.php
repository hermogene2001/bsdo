<?php
require_once 'config.php';
require_once 'models/RentalProductModel.php';

// Fix the path issue by defining the correct path for the model
class RentalProductModelFixed {
    private $pdo;

    public function __construct($database) {
        $this->pdo = $database;
    }

    /**
     * Get categories
     */
    public function getCategories() {
        try {
            $stmt = $this->pdo->prepare("SELECT id, name FROM categories WHERE status = 'active' ORDER BY name");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Database error in getCategories: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get payment channels
     */
    public function getPaymentChannels() {
        try {
            $stmt = $this->pdo->prepare("SELECT id, name FROM payment_channels WHERE is_active = 1 ORDER BY name");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Database error in getPaymentChannels: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get payment verification rate
     */
    public function getPaymentVerificationRate() {
        try {
            $stmt = $this->pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'payment_verification_rate'");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return floatval($result['setting_value'] ?? 0.50);
        } catch (PDOException $e) {
            error_log("Database error in getPaymentVerificationRate: " . $e->getMessage());
            return 0.50;
        }
    }
}

// Initialize model
$rentalModel = new RentalProductModelFixed($pdo);

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