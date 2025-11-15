<?php
/**
 * Social Links Helper
 * Provides functions to retrieve and display social media links
 */

/**
 * Get all active social links
 * @param PDO $pdo Database connection
 * @return array Array of social links
 */
function getSocialLinks($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM social_links WHERE is_active = 1 ORDER BY sort_order ASC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error fetching social links: " . $e->getMessage());
        return [];
    }
}

/**
 * Display social links as HTML
 * @param PDO $pdo Database connection
 * @param string $css_class Optional CSS class for the container
 * @param string $link_class Optional CSS class for individual links
 * @return string HTML output
 */
function displaySocialLinks($pdo, $css_class = 'social-links', $link_class = 'social-link') {
    $links = getSocialLinks($pdo);
    
    if (empty($links)) {
        return '<div class="' . $css_class . '">No social links available.</div>';
    }
    
    $html = '<div class="' . $css_class . '">';
    foreach ($links as $link) {
        $icon = !empty($link['icon']) ? '<i class="' . htmlspecialchars($link['icon']) . '"></i>' : '';
        $target = strpos($link['url'], 'http') === 0 ? 'target="_blank"' : '';
        $html .= '<a href="' . htmlspecialchars($link['url']) . '" ' . $target . ' class="' . $link_class . '" title="' . htmlspecialchars($link['name']) . '">';
        $html .= $icon;
        $html .= '</a>';
    }
    $html .= '</div>';
    
    return $html;
}

/**
 * Get a specific social link by ID
 * @param PDO $pdo Database connection
 * @param int $id Link ID
 * @return array|null Social link data or null if not found
 */
function getSocialLinkById($pdo, $id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM social_links WHERE id = ? AND is_active = 1");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error fetching social link: " . $e->getMessage());
        return null;
    }
}
?>