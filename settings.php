<?php
/**
 * Файл конфигурации модуля KZ Records
 * 
 * Настройки базы данных загружаются из storage/cache/sessions/db.php
 * Здесь только настройки отображения и кеширования
 */

if (!defined('IN_LR')) {
    die('Доступ запрещен');
}

return [
    // Настройки отображения
    'display' => [
        'default_map' => 'kz_grotto',
        'records_per_page' => 50,
        'map_division' => false,
        'default_tab' => 'kz'
    ],
    
    // Настройки кеширования
    'cache' => [
        'enabled' => true,
        'time' => 1800,  // 30 минут
        'maps_cache_time' => 3600,  // Карты кешируются на 1 час
        'stats_cache_time' => 900,  // Статистика кешируется на 15 минут
        'records_cache_time' => 600  // Рекорды кешируются на 10 минут
    ]
];
