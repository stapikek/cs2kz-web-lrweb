<?php

if (!defined('IN_LR')) {
    die('Access denied');
}

require_once('app/modules/module_page_kz_records/security.php');

class KzRecordsModule {
    
    private $settings;
    private $conn;
    private $cache_dir;
    
    public function __construct() {
        $this->loadSettings();
        $this->initDatabase();
        $this->initCache();
    }
    
    private function loadSettings() {
        $db_file = defined('STORAGE') ? STORAGE . 'cache/sessions/db.php' : 'storage/cache/sessions/db.php';
        
        if (!file_exists($db_file)) {
            throw new Exception('Database configuration file not found: ' . $db_file);
        }
        
        $db_config = require $db_file;
        
        $required_keys = ['HOST', 'USER', 'PASS', 'DB'];
        if (empty($db_config['kz'][0]) || !is_array($db_config['kz'][0])) {
            throw new Exception('KZ server configuration not found in db.php');
        }
        
        $kz_config = $db_config['kz'][0];
 
        foreach ($required_keys as $key) {
            if (empty($kz_config[$key])) {
                throw new Exception("Database {$key} is required in db.php");
            }
        }
        
        if (!is_array($kz_config['DB']) || empty($kz_config['DB'][0]['DB'])) {
            throw new Exception('Database name is required in db.php');
        }
        
        $db_config_item = $kz_config['DB'][0];
        
        $settings_file = 'app/modules/module_page_kz_records/settings.php';
        if (!file_exists($settings_file)) {
            throw new Exception('Settings file not found: ' . $settings_file);
        }
        
        $module_settings = require $settings_file;
        
        $this->settings = [
            'database' => [
                'host' => $kz_config['HOST'],
                'port' => $kz_config['PORT'] ?? 3306,
                'username' => $kz_config['USER'],
                'password' => $kz_config['PASS'],
                'database' => $db_config_item['DB'],
                'charset' => 'utf8mb4'
            ],
            'display' => $module_settings['display'] ?? [
                'default_map' => 'kz_longjumps2',
                'records_per_page' => 50,
                'map_division' => true,
                'default_tab' => 'kz'
            ],
            'cache' => $module_settings['cache'] ?? [
                'enabled' => true,
                'time' => 1800,
                'maps_cache_time' => 3600,
                'stats_cache_time' => 900,
                'records_cache_time' => 600
            ]
        ];
    }
    
    private function initDatabase() {
        try {
        $db = $this->settings['database'];
        
        $host = $db['host'];
            if (!empty($db['port']) && $db['port'] != 3306) {
                $host .= ':' . $db['port'];
            }
            
            $this->conn = new mysqli(
                $host,
                $db['username'],
                $db['password'],
                $db['database']
            );
            
            if ($this->conn->connect_error) {
                throw new Exception('Database connection failed: ' . $this->conn->connect_error);
            }
            
            if (isset($db['charset'])) {
                $this->conn->set_charset($db['charset']);
            }
            
        } catch (Exception $e) {
            error_log('KzRecordsModule Database Error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    private function initCache() {
        $this->cache_dir = defined('STORAGE') ? STORAGE . 'modules_cache/kz_records/' : 'storage/modules_cache/kz_records/';
        
        if (!is_dir($this->cache_dir)) {
            mkdir($this->cache_dir, 0777, true);
        }
    }
    
    public function isConnected() {
        return $this->conn && !$this->conn->connect_error;
    }
    
    public function getConfig() {
        return [
            'db' => $this->settings['database'],
            'display' => [
                'default_map' => $this->settings['display']['default_map'] ?? 'kz_longjumps2',
                'limit' => $this->settings['display']['records_per_page'] ?? 100,
                'map_division' => $this->settings['display']['map_division'] ?? true,
                'tab_opened' => $this->settings['display']['default_tab'] ?? 'kz'
            ]
        ];
    }
    
    private function validateMapName($map_name) {
        if (empty($map_name) || !is_string($map_name)) {
            return false;
        }
        
        $map_name = trim($map_name);
        
        $len = strlen($map_name);
        if ($len < 1 || $len > 64) {
            return false;
        }
        
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $map_name)) {
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
                '/exec|execute|sp_executesql|open|close|fetch|deallocate/i',
                '/cursor|global|local|static|dynamic|forward_only|scroll/i',
                '/keyset|fast_forward|read_only|scroll_locks|optimistic/i',
                '/type_warning|holdlock|nolock|readpast|readuncommitted/i',
                '/repeatableread|serializable|snapshot|updlock|xlock/i',
                '/paglock|tablock|tablockx|rowlock|nowait|readcommitted/i'
            ];
        }
        
        foreach ($dangerous_patterns as $pattern) {
            if (preg_match($pattern, $map_name)) {
                return false;
            }
        }
        
        static $dangerous_chars = null;
        if ($dangerous_chars === null) {
            $dangerous_chars = [';', "'", '"', '\\', '/', '`', '[', ']', '(', ')', '{', '}', '<', '>', '&', '|', '^', '~', '!', '@', '#', '$', '%', '+', '=', '?', ':'];
        }
        
        foreach ($dangerous_chars as $char) {
            if (strpos($map_name, $char) !== false) {
                return false;
            }
        }
        
        return true;
    }
    
    private function getCachedData($key, $callback, $custom_cache_time = null) {
        $cache_enabled = $this->settings['cache']['enabled'] ?? true;
        if (!$cache_enabled) {
            return $callback();
        }
        
        $cache_file = $this->cache_dir . $key . '.cache';
        
        if ($custom_cache_time !== null) {
            $cache_time = $custom_cache_time;
        } else {
            static $cache_times = [
                'maps' => null,
                'statistics' => null,
                'records_' => null,
                'default' => null
            ];
            
            if ($cache_times['maps'] === null) {
                $cache_times['maps'] = $this->settings['cache']['maps_cache_time'] ?? 3600;
                $cache_times['statistics'] = $this->settings['cache']['stats_cache_time'] ?? 900;
                $cache_times['records_'] = $this->settings['cache']['records_cache_time'] ?? 600;
                $cache_times['default'] = $this->settings['cache']['time'] ?? 1800;
            }
            
            if (strpos($key, 'maps') === 0) {
                $cache_time = $cache_times['maps'];
            } elseif (strpos($key, 'statistics') === 0) {
                $cache_time = $cache_times['statistics'];
            } elseif (strpos($key, 'records_') === 0) {
                $cache_time = $cache_times['records_'];
            } else {
                $cache_time = $cache_times['default'];
            }
        }
        
        if (file_exists($cache_file)) {
            $file_time = filemtime($cache_file);
            if ((time() - $file_time) < $cache_time) {
                $cached_data = file_get_contents($cache_file);
                if ($cached_data !== false) {
                    $decoded = json_decode($cached_data, true);
                    if ($decoded !== null) {
                        return $decoded;
                    }
                }
            }
        }
        
        $data = $callback();
        file_put_contents($cache_file, json_encode($data));
        
        return $data;
    }
    
    public function clearCache() {
        $cache_dir = MODULESCACHE . '/module_page_kz_records';
        if (is_dir($cache_dir)) {
            $files = glob($cache_dir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }
    
    public function getMaps() {
        $cache_key = 'maps';
        
        return $this->getCachedData($cache_key, function() {
            $maps = [];
            
            $query = "SELECT DISTINCT m.Name as MapName 
                      FROM Maps m 
                      INNER JOIN MapCourses mc ON m.ID = mc.MapID 
                      WHERE m.Name LIKE 'kz_%'
                      ORDER BY m.Name ASC 
                      LIMIT 1000";
            
            $stmt = $this->conn->prepare($query);
            
            if (!$stmt) {
                error_log('KzRecordsModule: Failed to prepare maps statement - ' . $this->conn->error);
                return $maps;
            }
            
            if (!$stmt->execute()) {
                error_log('KzRecordsModule: Failed to execute maps statement - ' . $stmt->error);
                $stmt->close();
                return $maps;
            }
            
            $result = $stmt->get_result();
            
            if ($result && $result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $map = $row['MapName'];
                    
                    if (preg_match('/^[a-zA-Z0-9_-]{1,64}$/', $map)) {
                        $maps[] = $map;
                    }
                }
            }
            
            $stmt->close();
            return $maps;
        });
    }
    
    public function getMapRecords($map_name) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        
        if (!KzRateLimiter::checkRateLimit($ip, 60, 60)) {
            error_log("KzRecordsModule: Rate limit exceeded - IP: {$ip}");
            return [];
        }
        
        if (!$this->validateMapName($map_name)) {
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
            $request_uri = $_SERVER['REQUEST_URI'] ?? 'unknown';
            
            KzSecurityHelper::logInjectionAttempt($ip, [
                'map_name' => $map_name,
                'user_agent' => $user_agent,
                'request_uri' => $request_uri
            ]);
            
            if (!KzSecurityHelper::checkInjectionAttempts($ip)) {
                error_log("KzRecordsModule: IP blocked due to repeated injection attempts - IP: {$ip}");
                return [];
            }
            
            error_log("KzRecordsModule: Potential SQL injection attempt detected - Map: '{$map_name}' | IP: {$ip} | User-Agent: {$user_agent} | URI: {$request_uri}");
            return [];
        }
        
        $map_name = trim($map_name);
        if (strlen($map_name) === 0) {
            return [];
        }
        
        return $this->getCachedData('records_' . md5($map_name), function() use ($map_name) {
            $records = [];
            $limit = min(max($this->settings['display']['records_per_page'] ?? 100, 1), 1000);
            
            $query = "SELECT 
                        t.SteamID64 as SteamID,
                        p.Alias as PlayerName,
                        t.RunTime as Time,
                        t.Created as Date,
                        ROW_NUMBER() OVER (ORDER BY t.RunTime ASC) as place
                      FROM Maps m 
                      INNER JOIN MapCourses mc ON m.ID = mc.MapID 
                      INNER JOIN Times t ON mc.ID = t.MapCourseID 
                      INNER JOIN Players p ON t.SteamID64 = p.SteamID64 
                      WHERE m.Name = ? AND t.StyleIDFlags = 0 AND p.Cheater = 0
                      ORDER BY t.RunTime ASC 
                      LIMIT ?";
            
            $stmt = $this->conn->prepare($query);
            if (!$stmt) {
                error_log('KzRecordsModule: Failed to prepare statement - ' . $this->conn->error);
                return [];
            }
            
            $stmt->bind_param('si', $map_name, $limit);
            
            if (!$stmt->execute()) {
                error_log('KzRecordsModule: Failed to execute statement - ' . $stmt->error);
                $stmt->close();
                return [];
            }
            
            $result = $stmt->get_result();
            
            if ($result && $result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $steam_id = $row['SteamID'] ?? '';
                    if (!preg_match('/^7656119[0-9]{10}$/', $steam_id)) continue;
                    
                    $player_name = $row['PlayerName'] ?? '';
                    if (strlen($player_name) < 1 || strlen($player_name) > 64) continue;
                    
                    $time = $row['Time'] ?? 0;
                    if (!is_numeric($time) || $time < 0 || $time > 999999) continue;
                    
                    $place = $row['place'] ?? 0;
                    if (!is_numeric($place) || $place < 1 || $place > 10000) continue;
                    
                    $records[] = [
                        'SteamID' => $steam_id,
                        'PlayerName' => htmlspecialchars($player_name, ENT_QUOTES, 'UTF-8'),
                        'Time' => (float)$time,
                        'FormattedTime' => $this->formatTime($time),
                        'Date' => strtotime($row['Date']),
                        'place' => (int)$place
                    ];
                }
            }
            
            $stmt->close();
            return $records;
        });
    }
    
    public function getStatistics() {
        return $this->getCachedData('statistics', function() {
            $stats = [
                'total_records' => 0,
                'total_players' => 0,
                'total_maps' => 0
            ];
            
            $query = "SELECT 
                        COUNT(t.ID) as total_records,
                        COUNT(DISTINCT t.SteamID64) as total_players,
                        COUNT(DISTINCT m.Name) as total_maps
                      FROM Times t 
                      JOIN MapCourses mc ON t.MapCourseID = mc.ID 
                      JOIN Maps m ON mc.MapID = m.ID 
                      JOIN Players p ON t.SteamID64 = p.SteamID64 
                      WHERE t.StyleIDFlags = 0 AND p.Cheater = 0";
            
            $stmt = $this->conn->prepare($query);
            
            if (!$stmt) {
                error_log('KzRecordsModule: Failed to prepare statistics statement - ' . $this->conn->error);
                return $stats;
            }
            
            if (!$stmt->execute()) {
                error_log('KzRecordsModule: Failed to execute statistics statement - ' . $stmt->error);
                $stmt->close();
                return $stats;
            }
            
            $result = $stmt->get_result();
            if ($result && $row = $result->fetch_assoc()) {
                $stats['total_records'] = (int)$row['total_records'];
                $stats['total_players'] = (int)$row['total_players'];
                $stats['total_maps'] = (int)$row['total_maps'];
            }
            
            $stmt->close();
            return $stats;
        });
    }
    
    private function formatTime($seconds) {
        if (empty($seconds) || $seconds == 0) {
            return '0:00.000';
        }
        
        $minutes = floor($seconds / 60);
        $seconds = $seconds % 60;
        return sprintf('%d:%06.3f', $minutes, $seconds);
    }
    
    public function __destruct() {
        if ($this->conn) {
            $this->conn->close();
        }
    }
}

if (class_exists('KzRecordsModule') && !isset($KzRecords)) {
    $KzRecords = new KzRecordsModule();
}
