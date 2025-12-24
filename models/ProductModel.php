<?php
require_once __DIR__ . '/../config.php';

class ProductModel {
    private $pdo;

    public function __construct($database) {
        $this->pdo = $database;
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
     * Get products with filters for seller
     */
    public function getSellerProducts($seller_id, $filters = []) {
        $status_filter = $filters['status'] ?? '';
        $category_filter = $filters['category'] ?? '';
        $search_query = $filters['search'] ?? '';
        $sort_by = $filters['sort'] ?? 'created_at';
        $sort_order = $filters['order'] ?? 'desc';
        $limit = $filters['limit'] ?? 10;
        $offset = $filters['offset'] ?? 0;

        // Build WHERE clause for filtering
        $where_conditions = [];
        $params = [$seller_id];

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

        try {
            $stmt = $this->pdo->prepare($sql);

            // Bind parameters separately for filters
            foreach ($params as $key => $value) {
                $stmt->bindValue(($key + 1), $value);
            }

            // Bind LIMIT and OFFSET as integers (at the end of the parameter list)
            $stmt->bindValue((count($params) + 1), $limit, PDO::PARAM_INT);
            $stmt->bindValue((count($params) + 2), $offset, PDO::PARAM_INT);

            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Database error in getSellerProducts: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get total product count for seller with filters
     */
    public function getSellerProductCount($seller_id, $filters = []) {
        $status_filter = $filters['status'] ?? '';
        $category_filter = $filters['category'] ?? '';
        $search_query = $filters['search'] ?? '';

        // Build WHERE clause for filtering
        $where_conditions = [];
        $params = [$seller_id];

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

        $count_sql = "SELECT COUNT(*) as total FROM products p $where_clause";

        try {
            $count_stmt = $this->pdo->prepare($count_sql);
            $count_stmt->execute($params);
            return $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
        } catch (PDOException $e) {
            error_log("Database error in getSellerProductCount: " . $e->getMessage());
            return 0;
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
     * Get product status counts for seller
     */
    public function getProductStatusCounts($seller_id) {
        try {
            $stmt = $this->pdo->prepare("SELECT status, COUNT(*) as count FROM products WHERE seller_id = ? GROUP BY status");
            $stmt->execute([$seller_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Database error in getProductStatusCounts: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get payment slips for seller's products
     */
    public function getSellerPaymentSlips($seller_id) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT ps.*, p.name as product_name 
                FROM payment_slips ps 
                JOIN products p ON ps.product_id = p.id 
                WHERE ps.seller_id = ? 
                ORDER BY ps.created_at DESC
            ");
            $stmt->execute([$seller_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Database error in getSellerPaymentSlips: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get seller info
     */
    public function getSellerInfo($seller_id) {
        try {
            $stmt = $this->pdo->prepare("SELECT first_name, last_name, phone, account_number FROM users WHERE id = ?");
            $stmt->execute([$seller_id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Database error in getSellerInfo: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Add a new product
     */
    public function addProduct($seller_id, $data) {
        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare("
                INSERT INTO products (
                    seller_id, name, description, price, stock, category_id, 
                    payment_channel_id, image_url, image_gallery, address, 
                    city, state, country, postal_code, status, upload_fee, upload_fee_paid
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, 0)
            ");

            $upload_fee = $data['price'] * 0.005; // 0.5% fee

            $stmt->execute([
                $seller_id,
                $data['name'],
                $data['description'],
                $data['price'],
                $data['stock'],
                $data['category_id'],
                $data['payment_channel_id'],
                $data['image_url'] ?? '',
                $data['image_gallery'] ?? null,
                $data['address'] ?? '',
                $data['city'] ?? '',
                $data['state'] ?? '',
                $data['country'] ?? '',
                $data['postal_code'] ?? '',
                $upload_fee
            ]);

            $product_id = $this->pdo->lastInsertId();

            // Check if this seller was referred by another seller and award 0.5% referral bonus
            $this->awardReferralBonus($seller_id, $data['price']);

            $this->pdo->commit();
            return ['success' => true, 'product_id' => $product_id];
        } catch (PDOException $e) {
            $this->pdo->rollback();
            error_log("Database error in addProduct: " . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to add product'];
        }
    }

    /**
     * Update a product
     */
    public function updateProduct($product_id, $seller_id, $data) {
        try {
            $sql = "UPDATE products SET 
                        name = ?, description = ?, price = ?, stock = ?, 
                        category_id = ?, payment_channel_id = ?, 
                        address = ?, city = ?, state = ?, country = ?, 
                        postal_code = ?";

            $params = [
                $data['name'],
                $data['description'],
                $data['price'],
                $data['stock'],
                $data['category_id'],
                $data['payment_channel_id'],
                $data['address'] ?? '',
                $data['city'] ?? '',
                $data['state'] ?? '',
                $data['country'] ?? '',
                $data['postal_code'] ?? ''
            ];

            // Add image updates if provided
            if (isset($data['image_url'])) {
                $sql .= ", image_url = ?";
                $params[] = $data['image_url'];
            }

            if (isset($data['image_gallery'])) {
                $sql .= ", image_gallery = ?";
                $params[] = $data['image_gallery'];
            }

            $sql .= " WHERE id = ? AND seller_id = ?";
            $params[] = $product_id;
            $params[] = $seller_id;

            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute($params);

            return $result ? ['success' => true] : ['success' => false, 'error' => 'No rows updated'];
        } catch (PDOException $e) {
            error_log("Database error in updateProduct: " . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to update product'];
        }
    }

    /**
     * Delete a product
     */
    public function deleteProduct($product_id, $seller_id) {
        try {
            // Verify product belongs to seller and has no orders
            $stmt = $this->pdo->prepare("
                SELECT p.id, p.name, COUNT(oi.id) as order_count 
                FROM products p 
                LEFT JOIN order_items oi ON p.id = oi.product_id 
                WHERE p.id = ? AND p.seller_id = ? 
                GROUP BY p.id
            ");
            $stmt->execute([$product_id, $seller_id]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($product) {
                if ($product['order_count'] == 0) {
                    $stmt = $this->pdo->prepare("DELETE FROM products WHERE id = ?");
                    $result = $stmt->execute([$product_id]);
                    return $result ? 
                        ['success' => true] : 
                        ['success' => false, 'error' => 'Failed to delete product'];
                } else {
                    return ['success' => false, 'error' => 'Cannot delete product with existing orders'];
                }
            } else {
                return ['success' => false, 'error' => 'Product not found or access denied'];
            }
        } catch (PDOException $e) {
            error_log("Database error in deleteProduct: " . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to delete product'];
        }
    }

    /**
     * Upload payment slip
     */
    public function uploadPaymentSlip($product_id, $seller_id, $data) {
        try {
            // Verify the product belongs to this seller
            $stmt = $this->pdo->prepare("SELECT * FROM products WHERE id = ? AND seller_id = ?");
            $stmt->execute([$product_id, $seller_id]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$product) {
                return ['success' => false, 'error' => 'Invalid product'];
            }

            $stmt = $this->pdo->prepare("
                INSERT INTO payment_slips (
                    product_id, seller_id, slip_path, amount, verification_rate
                ) VALUES (?, ?, ?, ?, ?)
            ");

            $result = $stmt->execute([
                $product_id,
                $seller_id,
                $data['slip_path'],
                $data['amount'],
                $data['verification_rate']
            ]);

            return $result ? 
                ['success' => true] : 
                ['success' => false, 'error' => 'Failed to upload payment slip'];
        } catch (PDOException $e) {
            error_log("Database error in uploadPaymentSlip: " . $e->getMessage());
            return ['success' => false, 'error' => 'Failed to upload payment slip'];
        }
    }

    /**
     * Award referral bonus to inviter
     */
    private function awardReferralBonus($seller_id, $product_price) {
        try {
            // Check if this seller was referred by another seller
            $referral_stmt = $this->pdo->prepare("
                SELECT inviter_id FROM referrals 
                WHERE invitee_id = ? AND invitee_role = 'seller' 
                LIMIT 1
            ");
            $referral_stmt->execute([$seller_id]);
            $referral_result = $referral_stmt->fetch(PDO::FETCH_ASSOC);

            if ($referral_result) {
                $inviter_id = $referral_result['inviter_id'];
                $referral_bonus = $product_price * 0.005; // 0.5% of product price

                // Award the referral bonus to the inviter
                $bonus_stmt = $this->pdo->prepare("
                    INSERT INTO user_wallets (user_id, balance) 
                    VALUES (?, ?) 
                    ON DUPLICATE KEY UPDATE balance = balance + VALUES(balance)
                ");
                $bonus_stmt->execute([$inviter_id, $referral_bonus]);

                // Update the referral record with the bonus amount
                $update_referral_stmt = $this->pdo->prepare("
                    UPDATE referrals 
                    SET reward_to_inviter = reward_to_inviter + ? 
                    WHERE invitee_id = ? AND invitee_role = 'seller'
                ");
                $update_referral_stmt->execute([$referral_bonus, $seller_id]);
            }
        } catch (PDOException $e) {
            error_log("Referral bonus error: " . $e->getMessage());
        }
    }
}
?>