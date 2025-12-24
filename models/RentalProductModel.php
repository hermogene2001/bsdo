<?php
require_once __DIR__ . '/../config.php';

class RentalProductModel {
    private $pdo;

    public function __construct($database) {
        $this->pdo = $database;
    }

    /**
     * Get rental products for a seller
     */
    public function getSellerRentalProducts($seller_id) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT p.*, c.name as category_name, pc.name as payment_channel_name,
                       COUNT(DISTINCT ro.id) as total_rentals,
                       COALESCE(SUM(CASE WHEN ro.status = 'completed' THEN ro.total_rental_amount ELSE 0 END), 0) as total_rental_revenue
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                LEFT JOIN payment_channels pc ON p.payment_channel_id = pc.id
                LEFT JOIN rental_orders ro ON p.id = ro.product_id
                WHERE p.seller_id = ? AND p.is_rental = 1
                GROUP BY p.id
                ORDER BY p.created_at DESC
            ");
            $stmt->execute([$seller_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Database error in getSellerRentalProducts: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get payment channels
     */
    public function getPaymentChannels() {
        try {
            $stmt = $this->pdo->prepare("SELECT id, name, type FROM payment_channels WHERE is_active = 1 ORDER BY name");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Database error in getPaymentChannels: " . $e->getMessage());
            return [];
        }
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

    /**
     * Add a rental product
     */
    public function addRentalProduct($data, $seller_id) {
        try {
            $this->pdo->beginTransaction();

            // Calculate average of rental prices and verification fee (0.5% of average)
            $avg_rental_price = ($data['rental_price_per_day'] + $data['rental_price_per_week'] + $data['rental_price_per_month']) / 3;
            $verification_fee = $avg_rental_price * 0.005; // 0.5% of average rental price
            
            // Insert product
            $stmt = $this->pdo->prepare("
                INSERT INTO products (
                    seller_id, name, description, category_id, stock, 
                    is_rental, rental_price_per_day, rental_price_per_week, 
                    rental_price_per_month, address, city, state, 
                    country, postal_code, payment_channel_id, upload_fee, product_type, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");

            $stmt->execute([
                $seller_id,
                $data['name'],
                $data['description'],
                $data['category_id'],
                $data['stock'],
                1, // is_rental
                $data['rental_price_per_day'],
                $data['rental_price_per_week'],
                $data['rental_price_per_month'],
                $data['address'],
                $data['city'],
                $data['state'],
                $data['country'],
                $data['postal_code'],
                $data['payment_channel_id'],
                $verification_fee,
                'rental' // product_type
            ]);

            $product_id = $this->pdo->lastInsertId();

            $this->pdo->commit();
            return ['success' => true, 'product_id' => $product_id];
        } catch (PDOException $e) {
            $this->pdo->rollback();
            error_log("Database error in addRentalProduct: " . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to add rental product'];
        }
    }
    
    /**
     * Calculate average rental price from day, week, and month prices
     */
    private function calculateAverageRentalPrice($day_price, $week_price, $month_price) {
        $valid_prices = [];
        
        if ($day_price > 0) {
            $valid_prices[] = $day_price;
        }
        
        if ($week_price > 0) {
            // Convert weekly price to daily equivalent
            $valid_prices[] = $week_price / 7;
        }
        
        if ($month_price > 0) {
            // Convert monthly price to daily equivalent (assuming 30 days)
            $valid_prices[] = $month_price / 30;
        }
        
        if (count($valid_prices) > 0) {
            return array_sum($valid_prices) / count($valid_prices);
        }
        
        // If no valid prices, return 0 or a default value
        return 0;
    }

    /**
     * Update a rental product
     */
    public function updateRentalProduct($data, $product_id, $seller_id) {
        try {
            // Calculate average of rental prices and verification fee (0.5% of average)
            $avg_rental_price = ($data['rental_price_per_day'] + $data['rental_price_per_week'] + $data['rental_price_per_month']) / 3;
            $verification_fee = $avg_rental_price * 0.005; // 0.5% of average rental price
            
            $stmt = $this->pdo->prepare("
                UPDATE products SET 
                    name = ?, description = ?, category_id = ?, stock = ?, 
                    rental_price_per_day = ?, rental_price_per_week = ?, 
                    rental_price_per_month = ?, address = ?, city = ?, 
                    state = ?, country = ?, postal_code = ?, 
                    payment_channel_id = ?, upload_fee = ?, product_type = ?, updated_at = NOW()
                WHERE id = ? AND seller_id = ?
            ");

            $result = $stmt->execute([
                $data['name'],
                $data['description'],
                $data['category_id'],
                $data['stock'],
                $data['rental_price_per_day'],
                $data['rental_price_per_week'],
                $data['rental_price_per_month'],
                $data['address'],
                $data['city'],
                $data['state'],
                $data['country'],
                $data['postal_code'],
                $data['payment_channel_id'],
                $verification_fee,
                'rental', // product_type
                $product_id,
                $seller_id
            ]);

            return $result ? ['success' => true] : ['success' => false, 'error' => 'No rows updated'];
        } catch (PDOException $e) {
            error_log("Database error in updateRentalProduct: " . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to update rental product'];
        }
    }

    /**
     * Delete a rental product
     */
    public function deleteRentalProduct($product_id, $seller_id) {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM products WHERE id = ? AND seller_id = ? AND is_rental = 1");
            $result = $stmt->execute([$product_id, $seller_id]);
            return $result ? ['success' => true] : ['success' => false, 'error' => 'Product not found or unauthorized'];
        } catch (PDOException $e) {
            error_log("Database error in deleteRentalProduct: " . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to delete rental product'];
        }
    }

    /**
     * Get a rental product by ID
     */
    public function getRentalProductById($product_id, $seller_id) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT p.*, c.name as category_name, pc.name as payment_channel_name 
                FROM products p 
                LEFT JOIN categories c ON p.category_id = c.id
                LEFT JOIN payment_channels pc ON p.payment_channel_id = pc.id 
                WHERE p.id = ? AND p.seller_id = ? AND p.is_rental = 1
            ");
            $stmt->execute([$product_id, $seller_id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Database error in getRentalProductById: " . $e->getMessage());
            return false;
        }
    }
}
?>