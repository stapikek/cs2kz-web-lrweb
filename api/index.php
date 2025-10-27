<?php

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if (!defined('IN_LR')) {
    define('IN_LR', true);
}

if (!defined('STORAGE')) {
    define('STORAGE', 'storage/');
}
if (!defined('MODULESCACHE')) {
    define('MODULESCACHE', STORAGE . 'modules_cache/');
}

require_once('app/modules/module_page_kz_records/forward/data.php');
require_once('app/modules/module_page_kz_records/security.php');

$KzRecords = new KzRecordsModule();

$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

if (!KzRateLimiter::checkRateLimit($ip, 100, 60)) {
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'error' => 'Too many requests',
        'timestamp' => time()
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

if (!$KzRecords->isConnected()) {
    http_response_code(503);
    echo json_encode([
        'success' => false,
        'error' => 'Database connection unavailable',
        'timestamp' => time()
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

$endpoint = $_GET['endpoint'] ?? 'stats';

function sanitizeInput($input) {
    if (!is_string($input)) {
        return '';
    }
    
    $input = trim($input);
    $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    $input = strip_tags($input);
    $input = preg_replace('/[<>"\']/', '', $input);
    return $input;
}

function sendResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit();
}

function sendError($message, $statusCode = 400) {
    sendResponse([
        'success' => false,
        'error' => $message,
        'timestamp' => time()
    ], $statusCode);
}

function validateMapName($map) {
    if (empty($map) || !is_string($map)) {
        return false;
    }
    
    $map = trim($map);
    $len = strlen($map);
    
    if ($len < 1 || $len > 64) {
        return false;
    }
    
    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $map)) {
        return false;
    }
    
    static $dangerous_patterns = null;
    if ($dangerous_patterns === null) {
        $dangerous_patterns = [
            '/union|select|insert|update|delete|drop|create|alter|exec|script/i',
            '/<script|javascript:|vbscript:|onload|onerror|onclick/i',
            '/--|\/\*|\*\//i',
            '/xp_|sp_|fn_|char|nchar|varchar|nvarchar|text|ntext/i',
            '/image|binary|varbinary|bit|tinyint|smallint|int|bigint/i',
            '/real|float|decimal|numeric|money|smallmoney/i',
            '/datetime|smalldatetime|timestamp|uniqueidentifier|sql_variant/i',
            '/table|view|procedure|function|trigger|index|constraint|key/i',
            '/foreign|primary|check|default|null|identity|seed|increment/i',
            '/collate|with|for|grant|revoke|deny|backup|restore/i',
            '/bulk|openrowset|opendatasource|openquery|linked|server/i',
            '/remote|distributed|transaction|commit|rollback|savepoint/i',
            '/begin|end|if|else|while|break|continue|goto|return/i',
            '/throw|try|catch|waitfor|raiserror|print|declare|set/i',
            '/exec|execute|sp_executesql/i'
        ];
    }
    
    foreach ($dangerous_patterns as $pattern) {
        if (preg_match($pattern, $map)) {
            return false;
        }
    }
    
    static $dangerous_chars = null;
    if ($dangerous_chars === null) {
        $dangerous_chars = [';', "'", '"', '\\', '/', '`', '[', ']', '(', ')', '{', '}', '<', '>', '&', '|', '^', '~', '!', '@', '#', '$', '%', '+', '=', '?', ':'];
    }
    
    foreach ($dangerous_chars as $char) {
        if (strpos($map, $char) !== false) {
            return false;
        }
    }
    
    return true;
}



try {
    switch ($endpoint) {
        
        case 'stats':
            $stats = $KzRecords->getStatistics();
            sendResponse([
                'success' => true,
                'data' => $stats,
                'timestamp' => time()
            ]);
            break;
        
        case 'maps':
            $maps = $KzRecords->getMaps();
            sendResponse([
                'success' => true,
                'data' => $maps,
                'timestamp' => time()
            ]);
            break;
        
        case 'records':
            $map = $_GET['map'] ?? null;
            
            if (!$map) {
                sendError('Map parameter is required');
            }
            
            $original_map = $map;
            $map = sanitizeInput($map);
            
            if (!validateMapName($map)) {
                $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
                error_log("KZ API: Potential SQL injection attempt - Map: '{$original_map}' | IP: {$ip} | User-Agent: {$user_agent}");
                sendError('Invalid map name format');
            }

            if (strlen($map) > 64 || strlen($map) < 1) {
                sendError('Map name length invalid');
            }
            
            $records = $KzRecords->getMapRecords($map);
            sendResponse([
                'success' => true,
                'data' => [
                    'map' => $map,
                    'records' => $records,
                    'count' => count($records)
                ],
                'timestamp' => time()
            ]);
            break;
        
        case 'map_info':
            $map = $_GET['map'] ?? null;
            
            if (!$map) {
                sendError('Map parameter is required');
            }
            
            $original_map = $map;
            $map = sanitizeInput($map);
            
            if (!validateMapName($map)) {
                $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
                error_log("KZ API: Potential SQL injection attempt - Map: '{$original_map}' | IP: {$ip} | User-Agent: {$user_agent}");
                sendError('Invalid map name format');
            }
            
            if (strlen($map) > 64 || strlen($map) < 1) {
                sendError('Map name length invalid');
            }
            
            $records = $KzRecords->getMapRecords($map);
            $top_record = !empty($records) ? $records[0] : null;
            
            sendResponse([
                'success' => true,
                'data' => [
                    'map_name' => $map,
                    'top_record' => $top_record,
                    'has_records' => !empty($records),
                    'total_records' => count($records)
                ],
                'timestamp' => time()
            ]);
            break;
        
        default:
            sendError('Unknown API endpoint: ' . $endpoint, 404);
    }
    
} catch (Exception $e) {
    error_log("KZ API Error: " . $e->getMessage());
    sendError('Internal server error', 500);
}
