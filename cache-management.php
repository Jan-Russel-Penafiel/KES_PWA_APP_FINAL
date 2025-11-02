<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KES-SMART Cache Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .stats-card {
            border-left: 4px solid #007bff;
            transition: all 0.3s ease;
        }
        .stats-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .progress-custom {
            height: 8px;
            border-radius: 4px;
        }
        .storage-item {
            border-bottom: 1px solid #eee;
            padding: 15px 0;
        }
        .storage-item:last-child {
            border-bottom: none;
        }
        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
        }
        .status-good { background-color: #28a745; }
        .status-warning { background-color: #ffc107; }
        .status-danger { background-color: #dc3545; }
        .cleanup-log {
            background-color: #f8f9fa;
            border-radius: 8px;
            max-height: 300px;
            overflow-y: auto;
            font-family: 'Courier New', monospace;
            font-size: 0.85em;
        }
        .btn-cleanup {
            background: linear-gradient(45deg, #007bff, #0056b3);
            border: none;
            transition: all 0.3s ease;
        }
        .btn-cleanup:hover {
            transform: scale(1.05);
        }
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }
    </style>
</head>
<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="text-center text-white">
            <div class="spinner-border mb-3" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <div>Processing cache operation...</div>
        </div>
    </div>

    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h1 class="h3 mb-0">
                            <i class="fas fa-database text-primary me-2"></i>
                            Cache Management
                        </h1>
                        <p class="text-muted mb-0">Monitor and manage system cache to prevent crashes</p>
                    </div>
                    <div>
                        <button class="btn btn-outline-primary me-2" onclick="refreshStats()">
                            <i class="fas fa-sync-alt"></i> Refresh
                        </button>
                        <a href="dashboard.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Status Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card stats-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-subtitle text-muted">Cache Storage</h6>
                                <h4 class="card-title mb-0" id="cacheSize">-</h4>
                            </div>
                            <div class="text-primary">
                                <i class="fas fa-hdd fa-2x"></i>
                            </div>
                        </div>
                        <div class="progress progress-custom mt-2">
                            <div class="progress-bar" id="cacheProgress" role="progressbar" style="width: 0%"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-subtitle text-muted">IndexedDB</h6>
                                <h4 class="card-title mb-0" id="indexedDBSize">-</h4>
                            </div>
                            <div class="text-info">
                                <i class="fas fa-database fa-2x"></i>
                            </div>
                        </div>
                        <div class="progress progress-custom mt-2">
                            <div class="progress-bar bg-info" id="indexedDBProgress" role="progressbar" style="width: 0%"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-subtitle text-muted">localStorage</h6>
                                <h4 class="card-title mb-0" id="localStorageSize">-</h4>
                            </div>
                            <div class="text-warning">
                                <i class="fas fa-save fa-2x"></i>
                            </div>
                        </div>
                        <div class="progress progress-custom mt-2">
                            <div class="progress-bar bg-warning" id="localStorageProgress" role="progressbar" style="width: 0%"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-subtitle text-muted">Total Usage</h6>
                                <h4 class="card-title mb-0" id="totalUsage">-</h4>
                            </div>
                            <div class="text-success">
                                <i class="fas fa-chart-pie fa-2x"></i>
                            </div>
                        </div>
                        <div class="progress progress-custom mt-2">
                            <div class="progress-bar bg-success" id="totalProgress" role="progressbar" style="width: 0%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="row">
            <!-- Storage Details -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-bar me-2"></i>
                            Storage Details
                        </h5>
                    </div>
                    <div class="card-body">
                        <div id="storageDetails">
                            <div class="text-center py-4">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                                <div class="mt-2">Loading storage information...</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Cleanup Log -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-list-alt me-2"></i>
                            Cleanup Log
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="cleanup-log p-3" id="cleanupLog">
                            <div class="text-muted">No cleanup operations yet...</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Controls -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-tools me-2"></i>
                            Cache Operations
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <button class="btn btn-cleanup btn-primary" onclick="performQuickCleanup()">
                                <i class="fas fa-broom me-2"></i>
                                Quick Cleanup
                            </button>
                            <button class="btn btn-warning" onclick="performRoutineCleanup()">
                                <i class="fas fa-recycle me-2"></i>
                                Routine Cleanup
                            </button>
                            <button class="btn btn-danger" onclick="performEmergencyCleanup()">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                Emergency Cleanup
                            </button>
                            <hr>
                            <button class="btn btn-outline-secondary" onclick="clearAllCaches()">
                                <i class="fas fa-trash-alt me-2"></i>
                                Clear All Caches
                            </button>
                            <button class="btn btn-outline-secondary" onclick="clearIndexedDB()">
                                <i class="fas fa-database me-2"></i>
                                Clear IndexedDB
                            </button>
                            <button class="btn btn-outline-secondary" onclick="clearLocalStorage()">
                                <i class="fas fa-save me-2"></i>
                                Clear localStorage
                            </button>
                        </div>
                    </div>
                </div>

                <!-- System Status -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-heartbeat me-2"></i>
                            System Status
                        </h5>
                    </div>
                    <div class="card-body">
                        <div id="systemStatus">
                            <div class="mb-3">
                                <span class="status-indicator status-good"></span>
                                <strong>Cache Manager:</strong> <span id="cacheManagerStatus">Loading...</span>
                            </div>
                            <div class="mb-3">
                                <span class="status-indicator status-good"></span>
                                <strong>Service Worker:</strong> <span id="serviceWorkerStatus">Loading...</span>
                            </div>
                            <div class="mb-3">
                                <span class="status-indicator status-good"></span>
                                <strong>Auto Cleanup:</strong> <span id="autoCleanupStatus">Loading...</span>
                            </div>
                            <div class="mb-3">
                                <strong>Last Cleanup:</strong> <span id="lastCleanup">-</span>
                            </div>
                            <div>
                                <strong>Next Cleanup:</strong> <span id="nextCleanup">-</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recommendations -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-lightbulb me-2"></i>
                            Recommendations
                        </h5>
                    </div>
                    <div class="card-body">
                        <div id="recommendations">
                            <div class="text-muted">Loading recommendations...</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/cache-manager.js"></script>
    <script src="assets/js/cache-clear.js"></script>
    <script>
        let updateInterval;
        
        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            initializePage();
        });
        
        async function initializePage() {
            try {
                await checkSystemStatus();
                await refreshStats();
                
                // Auto-refresh every 30 seconds
                updateInterval = setInterval(refreshStats, 30000);
                
                logMessage('Cache management interface initialized');
            } catch (error) {
                console.error('Failed to initialize page:', error);
                logMessage('Error: Failed to initialize interface - ' + error.message, 'error');
            }
        }
        
        async function checkSystemStatus() {
            try {
                // Check Cache Manager
                const cacheManagerElement = document.getElementById('cacheManagerStatus');
                if (window.cacheManager) {
                    cacheManagerElement.textContent = 'Active';
                    cacheManagerElement.previousElementSibling.className = 'status-indicator status-good';
                } else {
                    cacheManagerElement.textContent = 'Not Available';
                    cacheManagerElement.previousElementSibling.className = 'status-indicator status-warning';
                }
                
                // Check Service Worker
                const swElement = document.getElementById('serviceWorkerStatus');
                if ('serviceWorker' in navigator && navigator.serviceWorker.controller) {
                    swElement.textContent = 'Active';
                    swElement.previousElementSibling.className = 'status-indicator status-good';
                } else {
                    swElement.textContent = 'Not Available';
                    swElement.previousElementSibling.className = 'status-indicator status-warning';
                }
                
                // Check Auto Cleanup
                const autoCleanupElement = document.getElementById('autoCleanupStatus');
                if (window.cacheManager && window.cacheManager.isRunning !== undefined) {
                    autoCleanupElement.textContent = window.cacheManager.isRunning ? 'Running' : 'Enabled';
                    autoCleanupElement.previousElementSibling.className = 'status-indicator status-good';
                } else {
                    autoCleanupElement.textContent = 'Unknown';
                    autoCleanupElement.previousElementSibling.className = 'status-indicator status-warning';
                }
                
            } catch (error) {
                console.error('Error checking system status:', error);
            }
        }
        
        async function refreshStats() {
            try {
                let usage;
                
                if (window.cacheManager) {
                    usage = await window.cacheManager.getStorageUsage();
                } else {
                    usage = await getStorageUsage();
                }
                
                if (usage) {
                    updateStatsDisplay(usage);
                    updateStorageDetails(usage);
                    updateRecommendations(usage);
                }
                
                // Update cleanup info
                if (window.cacheManager) {
                    const stats = await window.cacheManager.getCleanupStats();
                    if (stats) {
                        document.getElementById('lastCleanup').textContent = 
                            stats.lastCleanup ? new Date(stats.lastCleanup).toLocaleString() : 'Never';
                        
                        // Estimate next cleanup (every 6 hours)
                        if (stats.lastCleanup) {
                            const nextCleanup = new Date(new Date(stats.lastCleanup).getTime() + 6 * 60 * 60 * 1000);
                            document.getElementById('nextCleanup').textContent = nextCleanup.toLocaleString();
                        }
                    }
                }
                
            } catch (error) {
                console.error('Error refreshing stats:', error);
                logMessage('Error: Failed to refresh statistics - ' + error.message, 'error');
            }
        }
        
        function updateStatsDisplay(usage) {
            // Update cache stats
            if (usage.cache) {
                document.getElementById('cacheSize').textContent = formatBytes(usage.cache.size);
                document.getElementById('cacheProgress').style.width = Math.min(usage.cache.percentage, 100) + '%';
                document.getElementById('cacheProgress').className = 
                    'progress-bar ' + getProgressColor(usage.cache.percentage);
            }
            
            // Update IndexedDB stats
            if (usage.indexedDB) {
                document.getElementById('indexedDBSize').textContent = formatBytes(usage.indexedDB.size);
                document.getElementById('indexedDBProgress').style.width = Math.min(usage.indexedDB.percentage, 100) + '%';
                document.getElementById('indexedDBProgress').className = 
                    'progress-bar ' + getProgressColor(usage.indexedDB.percentage);
            }
            
            // Update localStorage stats
            if (usage.localStorage) {
                document.getElementById('localStorageSize').textContent = formatBytes(usage.localStorage.size);
                document.getElementById('localStorageProgress').style.width = Math.min(usage.localStorage.percentage, 100) + '%';
                document.getElementById('localStorageProgress').className = 
                    'progress-bar ' + getProgressColor(usage.localStorage.percentage);
            }
            
            // Update total stats
            if (usage.total) {
                document.getElementById('totalUsage').textContent = 
                    formatBytes(usage.total.usage) + ' / ' + formatBytes(usage.total.quota);
                document.getElementById('totalProgress').style.width = Math.min(usage.total.percentage, 100) + '%';
                document.getElementById('totalProgress').className = 
                    'progress-bar ' + getProgressColor(usage.total.percentage);
            }
        }
        
        function updateStorageDetails(usage) {
            const details = document.getElementById('storageDetails');
            let html = '';
            
            const storageTypes = [
                { key: 'cache', name: 'Cache Storage', icon: 'fas fa-hdd', data: usage.cache },
                { key: 'indexedDB', name: 'IndexedDB', icon: 'fas fa-database', data: usage.indexedDB },
                { key: 'localStorage', name: 'localStorage', icon: 'fas fa-save', data: usage.localStorage },
                { key: 'total', name: 'Total Usage', icon: 'fas fa-chart-pie', data: usage.total }
            ];
            
            storageTypes.forEach(type => {
                if (type.data) {
                    const statusClass = getStatusClass(type.data.percentage);
                    html += `
                        <div class="storage-item">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="d-flex align-items-center">
                                    <i class="${type.icon} text-primary me-3"></i>
                                    <div>
                                        <h6 class="mb-1">${type.name}</h6>
                                        <div class="d-flex align-items-center">
                                            <span class="status-indicator ${statusClass} me-2"></span>
                                            <small class="text-muted">
                                                ${formatBytes(type.data.size || type.data.usage)}
                                                ${type.data.quota ? ' / ' + formatBytes(type.data.quota) : ''}
                                            </small>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <div class="fw-bold">${Math.round(type.data.percentage)}%</div>
                                    <div class="progress progress-custom" style="width: 100px;">
                                        <div class="progress-bar ${getProgressColor(type.data.percentage)}" 
                                             style="width: ${Math.min(type.data.percentage, 100)}%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                }
            });
            
            details.innerHTML = html;
        }
        
        function updateRecommendations(usage) {
            const recommendations = document.getElementById('recommendations');
            let html = '';
            
            if (window.cacheManager && window.cacheManager.getCleanupRecommendations) {
                const recs = window.cacheManager.getCleanupRecommendations(usage);
                if (recs && recs.length > 0) {
                    recs.forEach(rec => {
                        html += `<div class="alert alert-info py-2 mb-2"><small>${rec}</small></div>`;
                    });
                } else {
                    html = '<div class="text-success"><i class="fas fa-check-circle me-2"></i>All storage levels are optimal</div>';
                }
            } else {
                // Basic recommendations
                const issues = [];
                if (usage.cache && usage.cache.percentage > 80) {
                    issues.push('Cache storage is high - consider clearing old files');
                }
                if (usage.indexedDB && usage.indexedDB.percentage > 80) {
                    issues.push('IndexedDB storage is high - consider syncing and clearing old records');
                }
                if (usage.localStorage && usage.localStorage.percentage > 80) {
                    issues.push('localStorage is high - consider clearing temporary data');
                }
                if (usage.total && usage.total.percentage > 90) {
                    issues.push('Total storage critically high - immediate cleanup recommended');
                }
                
                if (issues.length > 0) {
                    issues.forEach(issue => {
                        html += `<div class="alert alert-warning py-2 mb-2"><small>${issue}</small></div>`;
                    });
                } else {
                    html = '<div class="text-success"><i class="fas fa-check-circle me-2"></i>All storage levels are optimal</div>';
                }
            }
            
            recommendations.innerHTML = html;
        }
        
        function getProgressColor(percentage) {
            if (percentage < 60) return 'bg-success';
            if (percentage < 80) return 'bg-warning';
            return 'bg-danger';
        }
        
        function getStatusClass(percentage) {
            if (percentage < 60) return 'status-good';
            if (percentage < 80) return 'status-warning';
            return 'status-danger';
        }
        
        function formatBytes(bytes) {
            if (bytes === 0) return '0 B';
            const k = 1024;
            const sizes = ['B', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
        
        function showLoading() {
            document.getElementById('loadingOverlay').style.display = 'flex';
        }
        
        function hideLoading() {
            document.getElementById('loadingOverlay').style.display = 'none';
        }
        
        function logMessage(message, type = 'info') {
            const log = document.getElementById('cleanupLog');
            const timestamp = new Date().toLocaleTimeString();
            const icon = type === 'error' ? '❌' : type === 'warning' ? '⚠️' : 'ℹ️';
            
            const logEntry = document.createElement('div');
            logEntry.innerHTML = `<span class="text-muted">[${timestamp}]</span> ${icon} ${message}`;
            
            log.appendChild(logEntry);
            log.scrollTop = log.scrollHeight;
            
            // Keep only last 50 entries
            while (log.children.length > 50) {
                log.removeChild(log.firstChild);
            }
        }
        
        // Cleanup functions
        async function performQuickCleanup() {
            showLoading();
            logMessage('Starting quick cleanup...');
            
            try {
                let result;
                if (window.cacheManager) {
                    result = await window.cacheManager.performQuickCleanup();
                } else {
                    result = await performQuickCleanup();
                }
                
                logMessage('Quick cleanup completed successfully');
                await refreshStats();
            } catch (error) {
                logMessage('Quick cleanup failed: ' + error.message, 'error');
            } finally {
                hideLoading();
            }
        }
        
        async function performRoutineCleanup() {
            showLoading();
            logMessage('Starting routine cleanup...');
            
            try {
                let result;
                if (window.cacheManager) {
                    result = await window.cacheManager.manualCleanup();
                } else {
                    // Fallback to server-side cleanup
                    const response = await fetch('api/server-cache-manager.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'cleanup' })
                    });
                    result = await response.json();
                }
                
                logMessage('Routine cleanup completed successfully');
                await refreshStats();
            } catch (error) {
                logMessage('Routine cleanup failed: ' + error.message, 'error');
            } finally {
                hideLoading();
            }
        }
        
        async function performEmergencyCleanup() {
            if (!confirm('This will aggressively clean all old data. Continue?')) {
                return;
            }
            
            showLoading();
            logMessage('Starting emergency cleanup...', 'warning');
            
            try {
                let result;
                if (window.cacheManager) {
                    result = await window.cacheManager.emergencyCleanup();
                } else {
                    // Fallback to server-side cleanup
                    const response = await fetch('api/server-cache-manager.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'emergency' })
                    });
                    result = await response.json();
                }
                
                logMessage('Emergency cleanup completed', 'warning');
                await refreshStats();
            } catch (error) {
                logMessage('Emergency cleanup failed: ' + error.message, 'error');
            } finally {
                hideLoading();
            }
        }
        
        async function clearAllCaches() {
            if (!confirm('This will clear all cached files. Continue?')) {
                return;
            }
            
            showLoading();
            logMessage('Clearing all caches...');
            
            try {
                const success = await clearAllCaches();
                if (success) {
                    logMessage('All caches cleared successfully');
                } else {
                    logMessage('Failed to clear some caches', 'warning');
                }
                await refreshStats();
            } catch (error) {
                logMessage('Failed to clear caches: ' + error.message, 'error');
            } finally {
                hideLoading();
            }
        }
        
        async function clearIndexedDB() {
            if (!confirm('This will clear all offline data. Continue?')) {
                return;
            }
            
            showLoading();
            logMessage('Clearing IndexedDB...');
            
            try {
                const success = await clearIndexedDB();
                if (success) {
                    logMessage('IndexedDB cleared successfully');
                } else {
                    logMessage('Failed to clear IndexedDB', 'warning');
                }
                await refreshStats();
            } catch (error) {
                logMessage('Failed to clear IndexedDB: ' + error.message, 'error');
            } finally {
                hideLoading();
            }
        }
        
        async function clearLocalStorage() {
            if (!confirm('This will clear temporary localStorage data. Continue?')) {
                return;
            }
            
            showLoading();
            logMessage('Clearing localStorage...');
            
            try {
                const success = await clearLocalStorage();
                if (success) {
                    logMessage('localStorage cleared successfully');
                } else {
                    logMessage('Failed to clear localStorage', 'warning');
                }
                await refreshStats();
            } catch (error) {
                logMessage('Failed to clear localStorage: ' + error.message, 'error');
            } finally {
                hideLoading();
            }
        }
        
        // Cleanup interval when page is closed
        window.addEventListener('beforeunload', function() {
            if (updateInterval) {
                clearInterval(updateInterval);
            }
        });
    </script>
</body>
</html>