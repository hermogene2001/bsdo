<?php

class SecurityUtils {
    /**
     * Generate a CSRF token
     */
    public static function generateCSRFToken() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Validate CSRF token
     */
    public static function validateCSRFToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Sanitize input data
     */
    public static function sanitizeInput($data) {
        return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Validate and sanitize integer
     */
    public static function sanitizeInt($value, $min = null, $max = null) {
        $intValue = filter_var($value, FILTER_VALIDATE_INT);
        if ($intValue === false) {
            return false;
        }
        
        if ($min !== null && $intValue < $min) {
            return false;
        }
        
        if ($max !== null && $intValue > $max) {
            return false;
        }
        
        return $intValue;
    }

    /**
     * Validate and sanitize float
     */
    public static function sanitizeFloat($value, $min = null, $max = null) {
        $floatValue = filter_var($value, FILTER_VALIDATE_FLOAT);
        if ($floatValue === false) {
            return false;
        }
        
        if ($min !== null && $floatValue < $min) {
            return false;
        }
        
        if ($max !== null && $floatValue > $max) {
            return false;
        }
        
        return $floatValue;
    }

    /**
     * Send security headers
     */
    public static function sendSecurityHeaders() {
        // Prevent Clickjacking
        header('X-Frame-Options: DENY');
        
        // XSS Protection
        header('X-XSS-Protection: 1; mode=block');
        
        // Content Type Sniffing Protection
        header('X-Content-Type-Options: nosniff');
        
        // Referrer Policy
        header('Referrer-Policy: no-referrer-when-downgrade');
        
        // Content Security Policy
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; img-src 'self' data: https:; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; connect-src 'self'; frame-ancestors 'none';");
    }

    /**
     * Regenerate session ID to prevent session fixation
     */
    public static function regenerateSession() {
        if (session_status() == PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    /**
     * Check if user is logged in
     */
    public static function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }

    /**
     * Check user role
     */
    public static function checkUserRole($requiredRole) {
        return isset($_SESSION['user_role']) && $_SESSION['user_role'] === $requiredRole;
    }

    /**
     * Redirect with error message
     */
    public static function redirectWithError($url, $message) {
        $_SESSION['error_message'] = $message;
        header("Location: $url");
        exit();
    }

    /**
     * Log security events
     */
    public static function logSecurityEvent($event, $details = '') {
        $timestamp = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $userId = $_SESSION['user_id'] ?? 'guest';
        
        $logEntry = "[$timestamp] [SECURITY] [$ip] [User: $userId] $event $details\n";
        error_log($logEntry, 3, __DIR__ . '/../logs/security.log');
    }
}

?>