<?php

if (!defined('IN_LR')) {
    die('Access denied');
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
