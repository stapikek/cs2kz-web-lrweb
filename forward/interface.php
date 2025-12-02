<?php

if (!defined('IN_LR')) {
    die('Access denied');
}

require_once('app/modules/module_page_kz_records/forward/data.php');
require_once('app/modules/module_page_kz_records/security.php');

$KzRecords = new KzRecordsModule();

$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

if (!KzRateLimiter::checkRateLimit($ip, 30, 60)) {
    echo '<div class="alert alert-warning">Too many requests. Please try again later.</div>';
    return;
}

if (!$KzRecords->isConnected()) {
    echo '<div class="alert alert-danger">Database connection failed. Please try again later.</div>';
    return;
}

$t = function($key) use ($Translate) {
    return $Translate->get_translate_module_phrase('module_page_kz_records', '_' . $key);
};

$current_map = $KzRecords->getConfig()['display']['default_map'];
if (isset($_GET['map']) && is_string($_GET['map'])) {
    $map_param = trim($_GET['map']);
    if (preg_match('/^[a-zA-Z0-9_-]{1,64}$/', $map_param)) {
        $current_map = $map_param;
    }
}

$maps = $KzRecords->getMaps();
$records = $KzRecords->getMapRecords($current_map);
$stats = $KzRecords->getStatistics();
?>

<div class="kz-records-module">
    <div class="kz-header">
        <div class="kz-header-content">
        <h1 class="kz-title"><?= $t('title') ?></h1>
        <p class="kz-description"><?= $t('description') ?></p>
        </div>

        <div class="kz-stats-cards">
            <div class="kz-stat-card">
                <div class="stat-icon">
                <svg><use href="/resources/img/sprite.svg#star-fill"></use></svg>
                </div>
                <div class="stat-info">
                    <div class="stat-value"><?= number_format($stats['total_records']) ?></div>
                    <div class="stat-label"><?= $t('total_records') ?></div>
                </div>
            </div>
            
            <div class="kz-stat-card">
                <div class="stat-icon">
                <svg><use href="/resources/img/sprite.svg#three-users"></use></svg>
                </div>
                <div class="stat-info">
                    <div class="stat-value"><?= number_format($stats['total_players']) ?></div>
                    <div class="stat-label"><?= $t('total_players') ?></div>
                </div>
            </div>
            
            <div class="kz-stat-card">
                <div class="stat-icon">
                <svg><use href="/resources/img/sprite.svg#play-triangle"></use></svg>
                </div>
                <div class="stat-info">
                    <div class="stat-value"><?= number_format($stats['total_maps']) ?></div>
                    <div class="stat-label"><?= $t('total_maps') ?></div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="mobile-maps-block">
        <div class="mobile-maps-header">
            <button class="mobile-maps-toggle" onclick="toggleMobileMaps()">
                <i class="fa-solid fa-bars"></i> <?= $t('map') ?>
            </button>
        </div>
        
        <div class="mobile-maps-content" id="mobile-maps-content">
            <div class="mobile-maps-list-content active">
                <ul class="mobile-maps-list">
                    <?php foreach ($maps as $map): ?>
                    <li class="mobile-map-item <?= $map === $current_map ? 'active' : '' ?>" 
                        data-map="<?= htmlspecialchars($map) ?>"
                        onclick="selectMobileMap('<?= htmlspecialchars($map) ?>')">
                        <?= htmlspecialchars($map) ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
    <div class="kz-main-content">
        <div class="kz-maps-sidebar">
            <div class="maps-sidebar-sticky">
                <button class="maps-toggle-btn" onclick="toggleMapsSidebar()">
                    <i class="fa-solid fa-bars"></i> <?= $t('map') ?>
                </button>
                
                <div class="maps-search">
                    <input type="text" 
                           id="map-search-input" 
                           class="maps-search-input" 
                           placeholder="<?= $t('search_placeholder') ?>"
                           onkeyup="filterMaps()">
                    <i class="fa-solid fa-search"></i>
                </div>
                
                <div class="maps-content active">
                    <ul class="maps-list">
                        <?php foreach ($maps as $map): ?>
                        <li class="map-item <?= $map === $current_map ? 'active' : '' ?>" 
                            data-map="<?= htmlspecialchars($map) ?>"
                            onclick="selectMap('<?= htmlspecialchars($map) ?>')">
                            <?= htmlspecialchars($map) ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
        <div class="kz-leaderboard">
            <div class="leaderboard-header">
                <h2 class="leaderboard-title">
                    <i class="fa-solid fa-ranking-star"></i>
                    <?= htmlspecialchars($current_map) ?>
                </h2>
                <div class="leaderboard-count">
                 <?= $t('total_records') ?> <?= count($records) ?>
                </div>
            </div>
            
            <div class="leaderboard-table">
                <div class="leaderboard-table-header">
                    <div class="table-col col-place"><?= $t('place') ?></div>
                    <div class="table-col col-player"><?= $t('player') ?></div>
                    <div class="table-col col-time"><?= $t('time') ?></div>
                    <div class="table-col col-actions"></div>
                </div>
                
                <div class="leaderboard-table-body">
                    <?php if (empty($records)): ?>
                    <div class="no-records">
                        <i class="fa-solid fa-inbox"></i>
                        <p><?= $t('no_records') ?></p>
                    </div>
                    <?php else: ?>
                        <?php foreach ($records as $record): ?>
                        <div class="leaderboard-row <?= $record['place'] <= 3 ? 'top-' . $record['place'] : '' ?>">
                            <div class="table-col col-place" data-label="<?= $t('place') ?>: ">
                                <span class="place-number place-<?= $record['place'] ?>">
                                    #<?= $record['place'] ?>
                                </span>
                            </div>
                            <div class="table-col col-player" data-label="<?= $t('player') ?>: ">
                                <a href="/profiles/<?= $record['SteamID'] ?>/?search=1" 
                                   class="player-name-link"
                                   title="<?= $t('view_profile') ?>">
                                    <?= htmlspecialchars($record['PlayerName']) ?>
                                </a>
                            </div>
                            <div class="table-col col-time" data-label="<?= $t('time') ?>: ">
                                <span class="time-value"><?= htmlspecialchars($record['FormattedTime']) ?></span>
                            </div>
                            <div class="table-col col-actions">
                                <a href="https://steamcommunity.com/profiles/<?= $record['SteamID'] ?>" 
                                   target="_blank" 
                                   class="action-btn"
                                   title="Steam">
                                   <svg><use href="/resources/img/sprite.svg#steam"></use></svg>
                                </a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
