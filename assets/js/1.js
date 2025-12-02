(function() {
    'use strict';
    
    let translations = {};
    let translationsLoaded = false;
    
    function loadTranslations() {
        const lang = window.LR_LANG || window.LANGUAGE || 'RU';
        
        fetch('/app/modules/module_page_kz_records/translation.json')
            .then(response => response.json())
            .then(data => {
                translations = {};
                Object.keys(data).forEach(key => {
                    translations[key] = data[key][lang] || data[key]['RU'];
                });
                translationsLoaded = true;
            })
            .catch(() => {
                translationsLoaded = true;
            });
    }
    
    function t(key) {
        return (translationsLoaded && translations[key]) ? translations[key] : key;
    }
    
    function validateMapName(mapName) {
        if (!mapName || typeof mapName !== 'string') {
            return false;
        }
        
        const trimmed = mapName.trim();
        const len = trimmed.length;
        
        if (len < 1 || len > 64) {
            return false;
        }
        
        if (!/^[a-zA-Z0-9_-]+$/.test(trimmed)) {
            return false;
        }
        
        if (!validateMapName.dangerousPatterns) {
            validateMapName.dangerousPatterns = [
                /union|select|insert|update|delete|drop|create|alter|exec|script/i,
                /<script|javascript:|vbscript:|onload|onerror|onclick/i,
                /--|\/\*|\*\//i
            ];
        }
        
        for (const pattern of validateMapName.dangerousPatterns) {
            if (pattern.test(trimmed)) {
                return false;
            }
        }
        
        if (!validateMapName.dangerousChars) {
            validateMapName.dangerousChars = [';', "'", '"', '\\', '/', '`', '[', ']', '(', ')', '{', '}', '<', '>', '&', '|', '^', '~', '!', '@', '#', '$', '%', '+', '=', '?', ':'];
        }
        
        for (const char of validateMapName.dangerousChars) {
            if (trimmed.includes(char)) {
                return false;
            }
        }
        
        return true;
    }
    
    loadTranslations();
    
    
    window.toggleMapsSidebar = function() {
        const sidebar = document.querySelector('.kz-maps-sidebar');
        if (sidebar) {
            sidebar.classList.toggle('active');
        }
    };
    
    
    window.selectMap = function(mapName) {
        if (!validateMapName(mapName)) {
            return;
        }
        
        loadMapRecords(mapName);
        updateURL(mapName);
    };
    
    
    
    window.filterMaps = function() {
        const input = document.getElementById('map-search-input');
        if (!input) return;
        
        const filter = input.value.toUpperCase();
        const mapItems = document.querySelectorAll('.map-item');
        
        mapItems.forEach(item => {
            const mapName = item.getAttribute('data-map');
            if (mapName && mapName.toUpperCase().indexOf(filter) > -1) {
                item.style.display = '';
            } else {
                item.style.display = 'none';
            }
        });
    };
    
    
    window.toggleMobileMaps = function() {
        const content = document.querySelector('.mobile-maps-content');
        if (content) {
            content.classList.toggle('active');
        }
    };
    
    window.selectMobileMap = function(mapName) {
        if (!validateMapName(mapName)) {
            return;
        }
        
        loadMapRecords(mapName);
        updateURL(mapName);
        const mobileContent = document.querySelector('.mobile-maps-content');
        if (mobileContent) {
            mobileContent.classList.remove('active');
        }
    };
    
    
    
    function initMobileAutoClose() {
        if (window.innerWidth <= 768) {
            const mapItems = document.querySelectorAll('.map-item');
            const sidebar = document.querySelector('.kz-maps-sidebar');
            
            mapItems.forEach(item => {
                item.addEventListener('click', () => {
                    if (sidebar) {
                        sidebar.classList.remove('active');
                    }
                });
            });
        }
    }
    
    function initOutsideClick() {
        document.addEventListener('click', (e) => {
            const sidebar = document.querySelector('.kz-maps-sidebar');
            const toggleBtn = document.querySelector('.maps-toggle-btn');
            
            if (sidebar && toggleBtn) {
                if (!sidebar.contains(e.target) && 
                    !toggleBtn.contains(e.target) && 
                    sidebar.classList.contains('active')) {
                    sidebar.classList.remove('active');
                }
            }
        });
    }
    
    
    function initKeyboardNavigation() {
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                const sidebar = document.querySelector('.kz-maps-sidebar');
                if (sidebar && sidebar.classList.contains('active')) {
                    sidebar.classList.remove('active');
                }
            }
            
            if (e.key === '/' && !e.ctrlKey && !e.metaKey) {
                const searchInput = document.getElementById('map-search-input');
                if (searchInput && document.activeElement !== searchInput) {
                    e.preventDefault();
                    searchInput.focus();
                }
            }
        });
    }
    
    function highlightActiveMap() {
        const activeMapItem = document.querySelector('.map-item.active');
        if (activeMapItem) {
            activeMapItem.scrollIntoView({ 
                behavior: 'smooth', 
                block: 'center' 
            });
        }
    }
    
    
    function loadMapRecords(mapName) {
        if (!validateMapName(mapName)) {
            return;
        }
        
        const leaderboardBody = document.querySelector('.leaderboard-table-body');
        const leaderboardTitle = document.querySelector('.leaderboard-title');
        const leaderboardCount = document.querySelector('.leaderboard-count');
        
        if (!leaderboardBody || !leaderboardTitle || !leaderboardCount) return;
        
        leaderboardBody.classList.add('updating');
        
        showLoadingIndicator(leaderboardBody);
        
        const apiUrl = `${window.location.origin}/app/modules/module_page_kz_records/api/index.php?endpoint=records&map=${encodeURIComponent(mapName)}`;
        
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 10000); 
        
        fetch(apiUrl, { signal: controller.signal })
            .then(response => {
                clearTimeout(timeoutId);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    updateLeaderboard(data.data, mapName);
                } else {
                    showError(t('_loading_records_error') + ': ' + data.error);
                }
            })
            .catch(error => {
                clearTimeout(timeoutId);
                if (error.name === 'AbortError') {
                    showError('Request timeout. Please try again.');
                } else {
                    const url = new URL(window.location);
                    url.searchParams.set('map', mapName);
                    window.location.href = url.toString();
                }
            })
            .finally(() => {
                leaderboardBody.classList.remove('updating');
            });
    }
    
    function showLoadingIndicator(container) {
        container.innerHTML = `
            <div class="loading-indicator">
                <div class="loading-spinner"></div>
                <p>Загрузка рекордов...</p>
            </div>
        `;
    }
    
    function updateLeaderboard(data, mapName) {
        const leaderboardBody = document.querySelector('.leaderboard-table-body');
        const leaderboardTitle = document.querySelector('.leaderboard-title');
        const leaderboardCount = document.querySelector('.leaderboard-count');
        
        if (!leaderboardBody || !leaderboardTitle || !leaderboardCount) {
            return;
        }
        
        leaderboardTitle.innerHTML = `
            <i class="fa-solid fa-ranking-star"></i>
            ${mapName}
        `;
        
        leaderboardCount.textContent = `${t('_total_records')} ${data.count}`;
        
        if (data.records && data.records.length > 0) {
            const fragment = document.createDocumentFragment();
            
            data.records.forEach(record => {
                const row = document.createElement('div');
                row.className = `leaderboard-row ${record.place <= 3 ? 'top-' + record.place : ''}`;
                
                row.innerHTML = `
                    <div class="table-col col-place" data-label="${t('_place_label')}">
                        <span class="place-number place-${record.place}">
                            #${record.place}
                        </span>
                    </div>
                    <div class="table-col col-player" data-label="${t('_player_label')}">
                        <a href="/profiles/${record.SteamID}/?search=1" 
                           class="player-name-link"
                           title="${t('_view_profile_title')}">
                            ${escapeHtml(record.PlayerName)}
                        </a>
                    </div>
                    <div class="table-col col-time" data-label="${t('_time_label')}">
                        <span class="time-value">${escapeHtml(record.FormattedTime)}</span>
                    </div>
                    <div class="table-col col-actions">
                        <a href="https://steamcommunity.com/profiles/${record.SteamID}" 
                           target="_blank" 
                           class="action-btn"
                           title="${t('_steam_title')}">
                           <svg><use href="/resources/img/sprite.svg#steam"></use></svg>
                        </a>
                    </div>
                `;
                
                fragment.appendChild(row);
            });
            
            leaderboardBody.innerHTML = '';
            leaderboardBody.appendChild(fragment);
        } else {
            leaderboardBody.innerHTML = `
                <div class="no-records">
                    <i class="fa-solid fa-inbox"></i>
                    <p>${t('_no_records_message')}</p>
                </div>
            `;
        }
    }
    
    function updateActiveMap(mapName) {
        document.querySelectorAll('.map-item, .mobile-map-item').forEach(item => {
            item.classList.remove('active');
        });
        
        const activeItem = document.querySelector(`[data-map="${mapName}"]`);
        if (activeItem) {
            activeItem.classList.add('active');
        }
    }
    
    function updateURL(mapName) {
        const url = new URL(window.location);
        url.searchParams.set('map', mapName);
        window.history.pushState({}, '', url.toString());
    }
    
    function showError(message) {
        const leaderboardBody = document.querySelector('.leaderboard-table-body');
        if (leaderboardBody) {
            leaderboardBody.innerHTML = `
                <div class="error-message">
                    <i class="fa-solid fa-exclamation-triangle"></i>
                    <p>${escapeHtml(message)}</p>
                </div>
            `;
        }
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    
    function initBrowserHistory() {
        window.addEventListener('popstate', function(event) {
            const urlParams = new URLSearchParams(window.location.search);
            const mapName = urlParams.get('map');
            if (mapName) {
                loadMapRecords(mapName);
                updateActiveMap(mapName);
            }
        });
    }
    
    function init() {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init);
            return;
        }
        
        initMobileAutoClose();
        initOutsideClick();
        initKeyboardNavigation();
        initBrowserHistory();
        
        highlightActiveMap();
    }
    
    init();
    
    window.KzRecordsModule = {
        selectMap: window.selectMap,
        toggleSidebar: window.toggleMapsSidebar,
        filterMaps: window.filterMaps,
        toggleMobileMaps: window.toggleMobileMaps,
        selectMobileMap: window.selectMobileMap
    };
    
})();
