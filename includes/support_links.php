<?php
/**
 * Customer Support Links Helper
 * Provides functions to retrieve and display customer support links
 */

/**
 * Get all active customer support links
 * @param PDO $pdo Database connection
 * @return array Array of support links
 */
function getCustomerSupportLinks($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM customer_support_links WHERE is_active = 1 ORDER BY sort_order ASC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error fetching support links: " . $e->getMessage());
        return [];
    }
}

/**
 * Display customer support links as HTML
 * @param PDO $pdo Database connection
 * @param string $css_class Optional CSS class for the container
 * @return string HTML output
 */
function displayCustomerSupportLinks($pdo, $css_class = 'support-links') {
    $links = getCustomerSupportLinks($pdo);
    
    if (empty($links)) {
        return '<div class="' . $css_class . '">No support links available.</div>';
    }
    
    $html = '<div class="' . $css_class . '">';
    $html .= '<h4>Customer Support</h4>';
    $html .= '<ul class="list-group">';
    
    foreach ($links as $link) {
        $icon = !empty($link['icon']) ? '<i class="fas ' . htmlspecialchars($link['icon']) . ' me-2"></i>' : '';
        $target = strpos($link['url'], 'http') === 0 ? 'target="_blank"' : '';
        $html .= '<li class="list-group-item">';
        $html .= '<a href="' . htmlspecialchars($link['url']) . '" ' . $target . ' class="text-decoration-none">';
        $html .= $icon . htmlspecialchars($link['name']);
        if (!empty($link['description'])) {
            $html .= '<small class="d-block text-muted">' . htmlspecialchars($link['description']) . '</small>';
        }
        $html .= '</a>';
        $html .= '</li>';
    }
    
    $html .= '</ul>';
    $html .= '</div>';
    
    return $html;
}

/**
 * Get a specific support link by ID
 * @param PDO $pdo Database connection
 * @param int $id Link ID
 * @return array|null Support link data or null if not found
 */
function getSupportLinkById($pdo, $id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM customer_support_links WHERE id = ? AND is_active = 1");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error fetching support link: " . $e->getMessage());
        return null;
    }
}
?>