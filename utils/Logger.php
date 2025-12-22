<?php

class Logger {
    private static $logFile = __DIR__ . '/../logs/app.log';
    
    public static function info($message, $context = []) {
        self::log('INFO', $message, $context);
    }
    
    public static function error($message, $context = []) {
        self::log('ERROR', $message, $context);
    }
    
    public static function warning($message, $context = []) {
        self::log('WARNING', $message, $context);
    }
    
    public static function debug($message, $context = []) {
        self::log('DEBUG', $message, $context);
    }
    
    private static function log($level, $message, $context = []) {
        // Create logs directory if it doesn't exist
        $logDir = dirname(self::$logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userId = $_SESSION['user_id'] ?? 'guest';
        
        $contextString = !empty($context) ? json_encode($context) : '';
        $logEntry = "[$timestamp] [$level] [$ip] [User: $userId] $message $contextString\n";
        
        error_log($logEntry, 3, self::$logFile);
    }
}

?>