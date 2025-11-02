/**
 * KES-SMART Automatic Cache Management System
 * Prevents system crashes by managing storage quotas and cleaning old data
 * Author: Smart School Management System
 * Version: 1.0.0
 */

class CacheManager {
    constructor() {
        this.config = {
            // Storage limits (in bytes)
            MAX_CACHE_SIZE: 100 * 1024 * 1024, // 100MB
            MAX_INDEXEDDB_SIZE: 50 * 1024 * 1024, // 50MB
            MAX_LOCALSTORAGE_SIZE: 5 * 1024 * 1024, // 5MB
            
            // Cleanup thresholds (percentage of max)
            CLEANUP_THRESHOLD: 0.8, // Clean when 80% full
            EMERGENCY_THRESHOLD: 0.95, // Emergency clean when 95% full
            
            // Retention periods (in milliseconds)
            CACHE_MAX_AGE: 7 * 24 * 60 * 60 * 1000, // 7 days
            INDEXEDDB_MAX_AGE: 30 * 24 * 60 * 60 * 1000, // 30 days
            SYNCED_DATA_MAX_AGE: 7 * 24 * 60 * 60 * 1000, // 7 days for synced data
            
            // Cleanup intervals
            ROUTINE_CLEANUP_INTERVAL: 60 * 60 * 1000, // 1 hour
            STORAGE_CHECK_INTERVAL: 5 * 60 * 1000, // 5 minutes
            
            // Cache names
            CACHE_NAMES: [
                'kes-smart-static-v1',
                'kes-smart-dynamic-v1',
                'kes-smart-api-v1',
                'kes-smart-offline-v1',
                'kes-smart-auth-v1'
            ],
            
            // IndexedDB stores
            DB_NAME: 'kes-smart-offline-data',
            STORE_NAMES: {
                LOGIN: 'login_attempts',
                ATTENDANCE: 'attendance_records',
                FORMS: 'form_submissions',
                SYNC_QUEUE: 'sync_queue'
            }
        };
        
        this.isRunning = false;
        this.cleanupPromise = null;
        this.listeners = new Map();
        
        this.init();
    }

    /**
     * Initialize the cache manager
     */
    async init() {
        try {
            // Check if browser supports required APIs
            if (!this.checkBrowserSupport()) {
                console.warn('[CacheManager] Browser does not support all required APIs');
                return;
            }

            // Start monitoring
            this.startMonitoring();
            
            // Perform initial cleanup
            await this.performRoutineCleanup();
            
            // Setup event listeners
            this.setupEventListeners();
            
            console.log('[CacheManager] Initialized successfully');
            this.emit('initialized');
            
        } catch (error) {
            console.error('[CacheManager] Initialization failed:', error);
            this.emit('error', { type: 'initialization', error });
        }
    }

    /**
     * Check if browser supports required APIs
     */
    checkBrowserSupport() {
        return !!(
            'caches' in window &&
            'indexedDB' in window &&
            'localStorage' in window &&
            'navigator' in window &&
            navigator.storage &&
            navigator.storage.estimate
        );
    }

    /**
     * Start monitoring storage usage
     */
    startMonitoring() {
        // Routine cleanup interval
        setInterval(() => {
            this.performRoutineCleanup();
        }, this.config.ROUTINE_CLEANUP_INTERVAL);

        // Storage check interval
        setInterval(() => {
            this.checkStorageUsage();
        }, this.config.STORAGE_CHECK_INTERVAL);

        // Listen for before unload to perform quick cleanup
        window.addEventListener('beforeunload', () => {
            this.performQuickCleanup();
        });

        // Listen for visibility change to cleanup when tab becomes inactive
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                this.performQuickCleanup();
            }
        });
    }

    /**
     * Setup event listeners for storage events
     */
    setupEventListeners() {
        // Listen for storage events
        window.addEventListener('storage', (e) => {
            if (e.storageArea === localStorage) {
                this.checkLocalStorageUsage();
            }
        });

        // Listen for quota exceeded errors
        window.addEventListener('error', (e) => {
            if (e.message && e.message.includes('QuotaExceededError')) {
                console.warn('[CacheManager] Quota exceeded, performing emergency cleanup');
                this.performEmergencyCleanup();
            }
        });
    }

    /**
     * Get current storage usage statistics
     */
    async getStorageUsage() {
        try {
            const estimate = await navigator.storage.estimate();
            const cacheSize = await this.getCacheSize();
            const indexedDBSize = await this.getIndexedDBSize();
            const localStorageSize = this.getLocalStorageSize();

            return {
                total: {
                    usage: estimate.usage || 0,
                    quota: estimate.quota || 0,
                    percentage: estimate.quota ? (estimate.usage / estimate.quota) * 100 : 0
                },
                cache: {
                    size: cacheSize,
                    percentage: (cacheSize / this.config.MAX_CACHE_SIZE) * 100
                },
                indexedDB: {
                    size: indexedDBSize,
                    percentage: (indexedDBSize / this.config.MAX_INDEXEDDB_SIZE) * 100
                },
                localStorage: {
                    size: localStorageSize,
                    percentage: (localStorageSize / this.config.MAX_LOCALSTORAGE_SIZE) * 100
                }
            };
        } catch (error) {
            console.error('[CacheManager] Error getting storage usage:', error);
            return null;
        }
    }

    /**
     * Get cache storage size
     */
    async getCacheSize() {
        try {
            let totalSize = 0;
            
            for (const cacheName of this.config.CACHE_NAMES) {
                const cache = await caches.open(cacheName);
                const keys = await cache.keys();
                
                for (const request of keys) {
                    try {
                        const response = await cache.match(request);
                        if (response) {
                            const blob = await response.blob();
                            totalSize += blob.size;
                        }
                    } catch (e) {
                        // Skip corrupted cache entries
                        console.warn('[CacheManager] Corrupted cache entry:', request.url);
                    }
                }
            }
            
            return totalSize;
        } catch (error) {
            console.error('[CacheManager] Error calculating cache size:', error);
            return 0;
        }
    }

    /**
     * Get IndexedDB size (approximate)
     */
    async getIndexedDBSize() {
        try {
            return new Promise((resolve) => {
                const request = indexedDB.open(this.config.DB_NAME);
                
                request.onsuccess = async (event) => {
                    const db = event.target.result;
                    let totalSize = 0;
                    
                    try {
                        for (const storeName of Object.values(this.config.STORE_NAMES)) {
                            if (db.objectStoreNames.contains(storeName)) {
                                const transaction = db.transaction([storeName], 'readonly');
                                const store = transaction.objectStore(storeName);
                                const records = await this.getAllRecordsFromStore(store);
                                
                                // Estimate size based on JSON string length
                                for (const record of records) {
                                    totalSize += JSON.stringify(record).length * 2; // UTF-16 encoding
                                }
                            }
                        }
                    } catch (e) {
                        console.warn('[CacheManager] Error calculating IndexedDB size:', e);
                    }
                    
                    db.close();
                    resolve(totalSize);
                };
                
                request.onerror = () => resolve(0);
            });
        } catch (error) {
            console.error('[CacheManager] Error accessing IndexedDB:', error);
            return 0;
        }
    }

    /**
     * Get localStorage size
     */
    getLocalStorageSize() {
        try {
            let totalSize = 0;
            for (let key in localStorage) {
                if (localStorage.hasOwnProperty(key)) {
                    totalSize += (localStorage[key].length + key.length) * 2; // UTF-16 encoding
                }
            }
            return totalSize;
        } catch (error) {
            console.error('[CacheManager] Error calculating localStorage size:', error);
            return 0;
        }
    }

    /**
     * Get all records from an IndexedDB store
     */
    getAllRecordsFromStore(store) {
        return new Promise((resolve) => {
            const request = store.getAll();
            request.onsuccess = () => resolve(request.result || []);
            request.onerror = () => resolve([]);
        });
    }

    /**
     * Check storage usage and trigger cleanup if necessary
     */
    async checkStorageUsage() {
        try {
            const usage = await this.getStorageUsage();
            if (!usage) return;

            const isOverThreshold = (
                usage.total.percentage > this.config.CLEANUP_THRESHOLD * 100 ||
                usage.cache.percentage > this.config.CLEANUP_THRESHOLD * 100 ||
                usage.indexedDB.percentage > this.config.CLEANUP_THRESHOLD * 100 ||
                usage.localStorage.percentage > this.config.CLEANUP_THRESHOLD * 100
            );

            const isEmergency = (
                usage.total.percentage > this.config.EMERGENCY_THRESHOLD * 100 ||
                usage.cache.percentage > this.config.EMERGENCY_THRESHOLD * 100 ||
                usage.indexedDB.percentage > this.config.EMERGENCY_THRESHOLD * 100 ||
                usage.localStorage.percentage > this.config.EMERGENCY_THRESHOLD * 100
            );

            if (isEmergency) {
                console.warn('[CacheManager] Emergency cleanup triggered - storage critically full');
                await this.performEmergencyCleanup();
            } else if (isOverThreshold) {
                console.log('[CacheManager] Cleanup triggered - storage over threshold');
                await this.performRoutineCleanup();
            }

            this.emit('storage-check', usage);

        } catch (error) {
            console.error('[CacheManager] Error checking storage usage:', error);
        }
    }

    /**
     * Perform routine cleanup
     */
    async performRoutineCleanup() {
        if (this.isRunning) {
            console.log('[CacheManager] Cleanup already in progress');
            return this.cleanupPromise;
        }

        this.isRunning = true;
        console.log('[CacheManager] Starting routine cleanup...');

        this.cleanupPromise = this._performCleanup(false);
        
        try {
            const result = await this.cleanupPromise;
            console.log('[CacheManager] Routine cleanup completed:', result);
            this.emit('cleanup-completed', { type: 'routine', result });
            return result;
        } catch (error) {
            console.error('[CacheManager] Routine cleanup failed:', error);
            this.emit('cleanup-failed', { type: 'routine', error });
            throw error;
        } finally {
            this.isRunning = false;
            this.cleanupPromise = null;
        }
    }

    /**
     * Perform emergency cleanup (more aggressive)
     */
    async performEmergencyCleanup() {
        if (this.isRunning) {
            console.log('[CacheManager] Emergency cleanup - cancelling routine cleanup');
            // Don't wait for routine cleanup, start emergency immediately
        }

        this.isRunning = true;
        console.warn('[CacheManager] Starting emergency cleanup...');

        try {
            const result = await this._performCleanup(true);
            console.log('[CacheManager] Emergency cleanup completed:', result);
            this.emit('cleanup-completed', { type: 'emergency', result });
            return result;
        } catch (error) {
            console.error('[CacheManager] Emergency cleanup failed:', error);
            this.emit('cleanup-failed', { type: 'emergency', error });
            throw error;
        } finally {
            this.isRunning = false;
        }
    }

    /**
     * Perform quick cleanup (for page unload)
     */
    async performQuickCleanup() {
        try {
            // Only clean up clearly old data quickly
            await Promise.all([
                this.cleanOldSyncedRecords(),
                this.cleanOldCacheEntries(true), // Quick mode
                this.cleanOldLocalStorageEntries()
            ]);
            
            console.log('[CacheManager] Quick cleanup completed');
        } catch (error) {
            console.error('[CacheManager] Quick cleanup failed:', error);
        }
    }

    /**
     * Internal cleanup implementation
     */
    async _performCleanup(isEmergency = false) {
        const results = {
            cache: { cleaned: 0, errors: 0 },
            indexedDB: { cleaned: 0, errors: 0 },
            localStorage: { cleaned: 0, errors: 0 }
        };

        try {
            // Clean cache storage
            const cacheResult = await this.cleanOldCacheEntries(false, isEmergency);
            results.cache = cacheResult;

            // Clean IndexedDB
            const indexedDBResult = await this.cleanOldIndexedDBRecords(isEmergency);
            results.indexedDB = indexedDBResult;

            // Clean localStorage
            const localStorageResult = await this.cleanOldLocalStorageEntries(isEmergency);
            results.localStorage = localStorageResult;

            // If emergency and still over quota, perform aggressive cleanup
            if (isEmergency) {
                const usage = await this.getStorageUsage();
                if (usage && usage.total.percentage > this.config.EMERGENCY_THRESHOLD * 100) {
                    console.warn('[CacheManager] Performing aggressive emergency cleanup');
                    await this.performAggressiveCleanup();
                }
            }

        } catch (error) {
            console.error('[CacheManager] Cleanup operation failed:', error);
            results.error = error.message;
        }

        return results;
    }

    /**
     * Clean old cache entries
     */
    async cleanOldCacheEntries(quickMode = false, isEmergency = false) {
        const result = { cleaned: 0, errors: 0 };
        const maxAge = isEmergency ? this.config.CACHE_MAX_AGE / 2 : this.config.CACHE_MAX_AGE;
        const cutoffTime = Date.now() - maxAge;

        try {
            for (const cacheName of this.config.CACHE_NAMES) {
                try {
                    const cache = await caches.open(cacheName);
                    const requests = await cache.keys();

                    for (const request of requests) {
                        try {
                            const response = await cache.match(request);
                            if (response) {
                                const dateHeader = response.headers.get('date');
                                const cacheTime = dateHeader ? new Date(dateHeader).getTime() : 0;

                                // In quick mode, only clean obviously old entries
                                if (quickMode && Date.now() - cacheTime < this.config.CACHE_MAX_AGE / 2) {
                                    continue;
                                }

                                if (cacheTime < cutoffTime || isEmergency) {
                                    await cache.delete(request);
                                    result.cleaned++;
                                    
                                    // In quick mode, don't clean too many at once
                                    if (quickMode && result.cleaned > 10) break;
                                }
                            }
                        } catch (e) {
                            result.errors++;
                            console.warn('[CacheManager] Error cleaning cache entry:', e);
                        }
                    }
                } catch (e) {
                    result.errors++;
                    console.warn('[CacheManager] Error accessing cache:', cacheName, e);
                }
            }
        } catch (error) {
            result.errors++;
            console.error('[CacheManager] Error cleaning cache entries:', error);
        }

        return result;
    }

    /**
     * Clean old IndexedDB records
     */
    async cleanOldIndexedDBRecords(isEmergency = false) {
        const result = { cleaned: 0, errors: 0 };

        try {
            return new Promise((resolve) => {
                const request = indexedDB.open(this.config.DB_NAME);

                request.onsuccess = async (event) => {
                    const db = event.target.result;

                    try {
                        for (const storeName of Object.values(this.config.STORE_NAMES)) {
                            if (db.objectStoreNames.contains(storeName)) {
                                try {
                                    const storeResult = await this.cleanStoreRecords(db, storeName, isEmergency);
                                    result.cleaned += storeResult.cleaned;
                                    result.errors += storeResult.errors;
                                } catch (e) {
                                    result.errors++;
                                    console.warn('[CacheManager] Error cleaning store:', storeName, e);
                                }
                            }
                        }
                    } catch (e) {
                        result.errors++;
                        console.error('[CacheManager] Error cleaning IndexedDB:', e);
                    }

                    db.close();
                    resolve(result);
                };

                request.onerror = () => {
                    result.errors++;
                    resolve(result);
                };
            });
        } catch (error) {
            result.errors++;
            console.error('[CacheManager] Error accessing IndexedDB for cleanup:', error);
            return result;
        }
    }

    /**
     * Clean records from a specific IndexedDB store
     */
    cleanStoreRecords(db, storeName, isEmergency = false) {
        return new Promise((resolve) => {
            const result = { cleaned: 0, errors: 0 };
            const maxAge = isEmergency ? this.config.INDEXEDDB_MAX_AGE / 3 : this.config.INDEXEDDB_MAX_AGE;
            const syncedMaxAge = this.config.SYNCED_DATA_MAX_AGE;
            const cutoffTime = Date.now() - maxAge;
            const syncedCutoffTime = Date.now() - syncedMaxAge;

            try {
                const transaction = db.transaction([storeName], 'readwrite');
                const store = transaction.objectStore(storeName);
                const request = store.openCursor();

                request.onsuccess = (event) => {
                    const cursor = event.target.result;
                    if (cursor) {
                        const record = cursor.value;
                        let shouldDelete = false;

                        // Delete synced records older than syncedMaxAge
                        if (record.synced && record.timestamp < syncedCutoffTime) {
                            shouldDelete = true;
                        }
                        // Delete unsynced records older than maxAge
                        else if (!record.synced && record.timestamp < cutoffTime) {
                            shouldDelete = true;
                        }
                        // In emergency, be more aggressive
                        else if (isEmergency) {
                            // Delete synced records older than 1 day
                            if (record.synced && record.timestamp < (Date.now() - 24 * 60 * 60 * 1000)) {
                                shouldDelete = true;
                            }
                            // Delete failed records with high retry count
                            else if (record.retry_count && record.retry_count > 5) {
                                shouldDelete = true;
                            }
                        }

                        if (shouldDelete) {
                            try {
                                cursor.delete();
                                result.cleaned++;
                            } catch (e) {
                                result.errors++;
                            }
                        }

                        cursor.continue();
                    } else {
                        resolve(result);
                    }
                };

                request.onerror = () => {
                    result.errors++;
                    resolve(result);
                };
            } catch (e) {
                result.errors++;
                resolve(result);
            }
        });
    }

    /**
     * Clean old localStorage entries
     */
    async cleanOldLocalStorageEntries(isEmergency = false) {
        const result = { cleaned: 0, errors: 0 };

        try {
            const keysToRemove = [];
            const maxAge = isEmergency ? this.config.CACHE_MAX_AGE / 4 : this.config.CACHE_MAX_AGE;
            const cutoffTime = Date.now() - maxAge;

            for (let key in localStorage) {
                if (localStorage.hasOwnProperty(key)) {
                    try {
                        // Check for timestamped data
                        if (key.includes('_timestamp_') || key.includes('_cache_')) {
                            const value = localStorage.getItem(key);
                            if (value) {
                                try {
                                    const data = JSON.parse(value);
                                    if (data.timestamp && data.timestamp < cutoffTime) {
                                        keysToRemove.push(key);
                                    }
                                } catch (e) {
                                    // If can't parse, check if key contains old timestamp
                                    const timestampMatch = key.match(/_(\d{13})_/);
                                    if (timestampMatch && parseInt(timestampMatch[1]) < cutoffTime) {
                                        keysToRemove.push(key);
                                    }
                                }
                            }
                        }
                        // In emergency, remove larger data items
                        else if (isEmergency && localStorage.getItem(key).length > 1000) {
                            // Keep only essential data
                            if (!key.includes('user_') && !key.includes('auth_') && !key.includes('session_')) {
                                keysToRemove.push(key);
                            }
                        }
                    } catch (e) {
                        result.errors++;
                    }
                }
            }

            // Remove identified keys
            for (const key of keysToRemove) {
                try {
                    localStorage.removeItem(key);
                    result.cleaned++;
                } catch (e) {
                    result.errors++;
                }
            }

        } catch (error) {
            result.errors++;
            console.error('[CacheManager] Error cleaning localStorage:', error);
        }

        return result;
    }

    /**
     * Perform aggressive cleanup in emergency situations
     */
    async performAggressiveCleanup() {
        console.warn('[CacheManager] Performing aggressive cleanup');

        try {
            // Clear all dynamic caches
            await caches.delete('kes-smart-dynamic-v1');
            
            // Clear all API caches except essential auth
            await caches.delete('kes-smart-api-v1');
            
            // Clear old static cache versions
            const cacheNames = await caches.keys();
            for (const name of cacheNames) {
                if (name.includes('kes-smart') && !name.includes('-v1')) {
                    await caches.delete(name);
                }
            }

            // Clear all synced IndexedDB records older than 1 day
            await this.clearOldSyncedRecords(24 * 60 * 60 * 1000); // 1 day

            console.log('[CacheManager] Aggressive cleanup completed');
        } catch (error) {
            console.error('[CacheManager] Aggressive cleanup failed:', error);
        }
    }

    /**
     * Clear old synced records from IndexedDB
     */
    cleanOldSyncedRecords() {
        return this.clearOldSyncedRecords(this.config.SYNCED_DATA_MAX_AGE);
    }

    /**
     * Clear synced records older than specified age
     */
    clearOldSyncedRecords(maxAge) {
        return new Promise((resolve) => {
            const request = indexedDB.open(this.config.DB_NAME);

            request.onsuccess = async (event) => {
                const db = event.target.result;
                let totalCleaned = 0;

                try {
                    for (const storeName of Object.values(this.config.STORE_NAMES)) {
                        if (db.objectStoreNames.contains(storeName)) {
                            const result = await this.clearSyncedFromStore(db, storeName, maxAge);
                            totalCleaned += result;
                        }
                    }
                } catch (e) {
                    console.error('[CacheManager] Error clearing synced records:', e);
                }

                db.close();
                resolve(totalCleaned);
            };

            request.onerror = () => resolve(0);
        });
    }

    /**
     * Clear synced records from a specific store
     */
    clearSyncedFromStore(db, storeName, maxAge) {
        return new Promise((resolve) => {
            const cutoffTime = Date.now() - maxAge;
            let cleaned = 0;

            const transaction = db.transaction([storeName], 'readwrite');
            const store = transaction.objectStore(storeName);
            const request = store.openCursor();

            request.onsuccess = (event) => {
                const cursor = event.target.result;
                if (cursor) {
                    const record = cursor.value;
                    if (record.synced && record.timestamp < cutoffTime) {
                        cursor.delete();
                        cleaned++;
                    }
                    cursor.continue();
                } else {
                    resolve(cleaned);
                }
            };

            request.onerror = () => resolve(cleaned);
        });
    }

    /**
     * Check localStorage usage specifically
     */
    async checkLocalStorageUsage() {
        const size = this.getLocalStorageSize();
        const percentage = (size / this.config.MAX_LOCALSTORAGE_SIZE) * 100;

        if (percentage > this.config.EMERGENCY_THRESHOLD * 100) {
            console.warn('[CacheManager] localStorage critically full, cleaning...');
            await this.cleanOldLocalStorageEntries(true);
        } else if (percentage > this.config.CLEANUP_THRESHOLD * 100) {
            console.log('[CacheManager] localStorage over threshold, cleaning...');
            await this.cleanOldLocalStorageEntries(false);
        }
    }

    /**
     * Force clear all caches (for manual cleanup)
     */
    async clearAllCaches() {
        try {
            const cacheNames = await caches.keys();
            await Promise.all(cacheNames.map(name => caches.delete(name)));
            console.log('[CacheManager] All caches cleared');
            this.emit('all-caches-cleared');
            return true;
        } catch (error) {
            console.error('[CacheManager] Error clearing all caches:', error);
            return false;
        }
    }

    /**
     * Force clear all IndexedDB data (for manual cleanup)
     */
    async clearAllIndexedDB() {
        try {
            return new Promise((resolve) => {
                const request = indexedDB.open(this.config.DB_NAME);

                request.onsuccess = async (event) => {
                    const db = event.target.result;

                    for (const storeName of Object.values(this.config.STORE_NAMES)) {
                        if (db.objectStoreNames.contains(storeName)) {
                            const transaction = db.transaction([storeName], 'readwrite');
                            const store = transaction.objectStore(storeName);
                            store.clear();
                        }
                    }

                    db.close();
                    console.log('[CacheManager] All IndexedDB data cleared');
                    this.emit('all-indexeddb-cleared');
                    resolve(true);
                };

                request.onerror = () => resolve(false);
            });
        } catch (error) {
            console.error('[CacheManager] Error clearing IndexedDB:', error);
            return false;
        }
    }

    /**
     * Get cleanup statistics
     */
    async getCleanupStats() {
        try {
            const usage = await this.getStorageUsage();
            const stats = {
                lastCleanup: localStorage.getItem('cachemanager_last_cleanup'),
                cleanupCount: parseInt(localStorage.getItem('cachemanager_cleanup_count') || '0'),
                usage: usage,
                recommendations: this.getCleanupRecommendations(usage)
            };

            return stats;
        } catch (error) {
            console.error('[CacheManager] Error getting cleanup stats:', error);
            return null;
        }
    }

    /**
     * Get cleanup recommendations based on usage
     */
    getCleanupRecommendations(usage) {
        const recommendations = [];

        if (!usage) return recommendations;

        if (usage.cache.percentage > 80) {
            recommendations.push('Cache storage is high - consider clearing old cached files');
        }

        if (usage.indexedDB.percentage > 80) {
            recommendations.push('IndexedDB storage is high - consider syncing and clearing old records');
        }

        if (usage.localStorage.percentage > 80) {
            recommendations.push('localStorage is high - consider clearing temporary data');
        }

        if (usage.total.percentage > 90) {
            recommendations.push('Total storage critically high - immediate cleanup recommended');
        }

        return recommendations;
    }

    /**
     * Event emitter methods
     */
    on(event, callback) {
        if (!this.listeners.has(event)) {
            this.listeners.set(event, []);
        }
        this.listeners.get(event).push(callback);
    }

    off(event, callback) {
        if (this.listeners.has(event)) {
            const callbacks = this.listeners.get(event);
            const index = callbacks.indexOf(callback);
            if (index > -1) {
                callbacks.splice(index, 1);
            }
        }
    }

    emit(event, data) {
        if (this.listeners.has(event)) {
            this.listeners.get(event).forEach(callback => {
                try {
                    callback(data);
                } catch (error) {
                    console.error('[CacheManager] Event callback error:', error);
                }
            });
        }
    }

    /**
     * Update cleanup statistics
     */
    updateCleanupStats() {
        localStorage.setItem('cachemanager_last_cleanup', new Date().toISOString());
        const count = parseInt(localStorage.getItem('cachemanager_cleanup_count') || '0') + 1;
        localStorage.setItem('cachemanager_cleanup_count', count.toString());
    }

    /**
     * Public methods for manual control
     */
    async manualCleanup() {
        return await this.performRoutineCleanup();
    }

    async emergencyCleanup() {
        return await this.performEmergencyCleanup();
    }

    async getStatus() {
        return {
            isRunning: this.isRunning,
            usage: await this.getStorageUsage(),
            stats: await this.getCleanupStats()
        };
    }
}

// Create and export the global cache manager instance
window.CacheManager = CacheManager;

// Auto-initialize cache manager when script loads
let cacheManager;

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        cacheManager = new CacheManager();
        window.cacheManager = cacheManager;
    });
} else {
    cacheManager = new CacheManager();
    window.cacheManager = cacheManager;
}

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = CacheManager;
}