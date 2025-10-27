<?php

if (!defined('IN_LR')) {
    die('Access denied');
}

/**
 * Security Configuration for KZ Records Module
 * Конфигурация безопасности для модуля KZ Records
 */

class KzSecurityHelper {
    public static function checkInjectionAttempts($ip) {
        return true;
    }
    
    public static function logInjectionAttempt($ip, $details) {
        $log_entry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'ip' => $ip,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            'details' => $details
        ];
        
        $log_file = defined('STORAGE') ? STORAGE . 'security/injection_attempts.log' : 'storage/security/injection_attempts.log';
        $log_dir = dirname($log_file);
        
        if (!is_dir($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
        
        file_put_contents($log_file, json_encode($log_entry) . "\n", FILE_APPEND | LOCK_EX);
    }
}

class KzRateLimiter {
    
    private static $requests = [];
    public static function checkRateLimit($ip, $max_requests = 60, $time_window = 60) {
        $now = time();
        $window_start = $now - $time_window;
        
        if (!isset(self::$requests[$ip])) {
            self::$requests[$ip] = [];
        }

        self::$requests[$ip] = array_filter(self::$requests[$ip], function($timestamp) use ($window_start) {
            return $timestamp > $window_start;
        });
        
        if (count(self::$requests[$ip]) >= $max_requests) {
            return false;
        }

        self::$requests[$ip][] = $now;
        
        return true;
    }
}

?>
