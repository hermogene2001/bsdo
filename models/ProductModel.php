<?php
require_once 'config.php';

class ProductModel {
    private $pdo;

    public function __construct($database) {
        $this->pdo = $database;
    }

    /**
     * Get products with filters
     */
    public function getProducts($filters) {
        $category_id = $filters['category_id'] ?? '';
        $search = $filters['search'] ?? '';
        $sort = $filters['sort'] ?? 'newest';
        $min_price = $filters['min_price'] ?? '';
        $max_price = $filters['max_price'] ?? '';
        $limit = $filters['limit'] ?? 12;
        $offset = $filters['offset'] ?? 0;

        $query = "
            SELECT 
                p.*, 
                u.store_name, 
                c.name AS category_name,
                COALESCE(SUM(oi.quantity), 0) AS units_sold,
                COUNT(DISTINCT oi.order_id) AS order_count
            FROM products p
            JOIN users u ON p.seller_id = u.id
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN order_items oi ON p.id = oi.product_id
            LEFT JOIN orders o ON oi.order_id = o.id AND o.status = 'completed'
            WHERE p.status = 'active'
        ";

        $params = [];
        $where_conditions = [];

        // Add category filter
        if (!empty($category_id)) {
            $where_conditions[] = "p.category_id = ?";
            $params[] = $category_id;
        }

        // Add search filter
        if (!empty($search)) {
            $where_conditions[] = "(p.name LIKE ? OR p.description LIKE ? OR u.store_name LIKE ?)";
            $search_term = "%$search%";
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
        }

        // Add price filters
        if (!empty($min_price)) {
            $where_conditions[] = "p.price >= ?";
            $params[] = $min_price;
        }

        if (!empty($max_price)) {
            $where_conditions[] = "p.price <= ?";
            $params[] = $max_price;
        }

        // Add where conditions
        if (!empty($where_conditions)) {
            $query .= " AND " . implode(" AND ", $where_conditions);
        }

        // Group by and sort
        $query .= " GROUP BY p.id";

        // Add sorting
        switch ($sort) {
            case 'price_low':
                $query .= " ORDER BY p.price ASC";
                break;
            case 'price_high':
                $query .= " ORDER BY p.price DESC";
                break;
            case 'popular':
                $query .= " ORDER BY units_sold DESC";
                break;
            case 'name':
                $query .= " ORDER BY p.name ASC";
                break;
            case 'newest':
            default:
                $query .= " ORDER BY p.created_at DESC";
                break;
        }

        // Add pagination
        $query .= " LIMIT " . (int)$limit . " OFFSET " . (int)$offset;

        try {
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Database error in getProducts: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get total product count with filters
     */
    public function getTotalProductCount($filters) {
        $category_id = $filters['category_id'] ?? '';
        $search = $filters['search'] ?? '';
        $min_price = $filters['min_price'] ?? '';
        $max_price = $filters['max_price'] ?? '';

        $query = "
            SELECT COUNT(DISTINCT p.id) AS total 
            FROM products p
            JOIN users u ON p.seller_id = u.id
            WHERE p.status = 'active'
        ";

        $params = [];
        $where_conditions = [];

        // Add filters
        if (!empty($category_id)) {
            $where_conditions[] = "p.category_id = ?";
            $params[] = $category_id;
        }

        if (!empty($search)) {
            $where_conditions[] = "(p.name LIKE ? OR p.description LIKE ? OR u.store_name LIKE ?)";
            $search_term = "%$search%";
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
        }

        if (!empty($min_price)) {
            $where_conditions[] = "p.price >= ?";
            $params[] = $min_price;
        }

        if (!empty($max_price)) {
            $where_conditions[] = "p.price <= ?";
            $params[] = $max_price;
        }

        if (!empty($where_conditions)) {
            $query .= " AND " . implode(" AND ", $where_conditions);
        }

        try {
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            return $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
        } catch (PDOException $e) {
            error_log("Database error in getTotalProductCount: " . $e->getMessage());
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
     * Get price range
     */
    public function getPriceRange() {
        try {
            $stmt = $this->pdo->prepare("SELECT MIN(price) AS min_price, MAX(price) AS max_price FROM products WHERE status = 'active'");
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Database error in getPriceRange: " . $e->getMessage());
            return ['min_price' => 0, 'max_price' => 0];
        }
    }

    /**
     * Add product to cart
     */
    public function addProductToCart(&$cart, $product_id, $quantity) {
        try {
            // Check if product exists and is in stock
            $stmt = $this->pdo->prepare("SELECT name, price, stock FROM products WHERE id = ? AND status = 'active'");
            $stmt->execute([$product_id]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($product) {
                $current_quantity = isset($cart[$product_id]) ? $cart[$product_id]['quantity'] : 0;

                if (($current_quantity + $quantity) <= $product['stock']) {
                    if (isset($cart[$product_id])) {
                        $cart[$product_id]['quantity'] += $quantity;
                    } else {
                        $cart[$product_id] = [
                            'name' => $product['name'],
                            'price' => $product['price'],
                            'quantity' => $quantity
                        ];
                    }
                    return ['success' => true, 'message' => 'Product added to cart successfully!'];
                } else {
                    return ['success' => false, 'message' => "Not enough stock available. Only " . $product['stock'] . " items left."];
                }
            } else {
                return ['success' => false, 'message' => "Product not found or unavailable."];
            }
        } catch (PDOException $e) {
            error_log("Database error in addProductToCart: " . $e->getMessage());
            return ['success' => false, 'message' => "An error occurred while adding the product to cart."];
        }
    }
}
?>