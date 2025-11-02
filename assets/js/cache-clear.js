/**
 * KES-SMART Cache Clear Utilities
 * Simple interface for cache management operations
 * Works with the CacheManager for comprehensive cache cleaning
 */

// Simple cache clearing functions for backward compatibility
async function clearAllCaches() {
    try {
        if (window.cacheManager) {
            return await window.cacheManager.clearAllCaches();
        }
        
        // Fallback manual clearing
        const cacheNames = await caches.keys();
        await Promise.all(cacheNames.map(name => caches.delete(name)));
        console.log('All caches cleared manually');
        return true;
    } catch (error) {
        console.error('Error clearing caches:', error);
        return false;
    }
}

async function clearIndexedDB() {
    try {
        if (window.cacheManager) {
            return await window.cacheManager.clearAllIndexedDB();
        }
        
        // Fallback manual clearing
        return new Promise((resolve) => {
            const request = indexedDB.open('kes-smart-offline-data');
            request.onsuccess = (event) => {
                const db = event.target.result;
                const storeNames = ['login_attempts', 'attendance_records', 'form_submissions', 'sync_queue'];
                
                for (const storeName of storeNames) {
                    if (db.objectStoreNames.contains(storeName)) {
                        const transaction = db.transaction([storeName], 'readwrite');
                        const store = transaction.objectStore(storeName);
                        store.clear();
                    }
                }
                
                db.close();
                console.log('IndexedDB cleared manually');
                resolve(true);
            };
            request.onerror = () => resolve(false);
        });
    } catch (error) {
        console.error('Error clearing IndexedDB:', error);
        return false;
    }
}

async function clearLocalStorage() {
    try {
        // Clear non-essential localStorage items
        const keysToKeep = ['user_auth', 'user_session', 'user_role'];
        const keysToRemove = [];
        
        for (let key in localStorage) {
            if (localStorage.hasOwnProperty(key) && !keysToKeep.some(keep => key.includes(keep))) {
                keysToRemove.push(key);
            }
        }
        
        keysToRemove.forEach(key => localStorage.removeItem(key));
        console.log(`Cleared ${keysToRemove.length} localStorage items`);
        return true;
    } catch (error) {
        console.error('Error clearing localStorage:', error);
        return false;
    }
}

async function performQuickCleanup() {
    try {
        if (window.cacheManager) {
            await window.cacheManager.performQuickCleanup();
            return true;
        }
        
        // Fallback quick cleanup
        const results = await Promise.all([
            clearOldCacheEntries(),
            clearOldIndexedDBRecords(),
            clearLocalStorage()
        ]);
        
        return results.every(result => result);
    } catch (error) {
        console.error('Error in quick cleanup:', error);
        return false;
    }
}

async function clearOldCacheEntries() {
    try {
        const maxAge = 7 * 24 * 60 * 60 * 1000; // 7 days
        const cutoffTime = Date.now() - maxAge;
        let cleaned = 0;
        
        const cacheNames = await caches.keys();
        for (const name of cacheNames) {
            if (name.includes('kes-smart')) {
                const cache = await caches.open(name);
                const requests = await cache.keys();
                
                for (const request of requests) {
                    const response = await cache.match(request);
                    if (response) {
                        const dateHeader = response.headers.get('date');
                        if (dateHeader && new Date(dateHeader).getTime() < cutoffTime) {
                            await cache.delete(request);
                            cleaned++;
                        }
                    }
                }
            }
        }
        
        console.log(`Cleared ${cleaned} old cache entries`);
        return true;
    } catch (error) {
        console.error('Error clearing old cache entries:', error);
        return false;
    }
}

async function clearOldIndexedDBRecords() {
    try {
        return new Promise((resolve) => {
            const request = indexedDB.open('kes-smart-offline-data');
            
            request.onsuccess = (event) => {
                const db = event.target.result;
                const maxAge = 30 * 24 * 60 * 60 * 1000; // 30 days
                const syncedMaxAge = 7 * 24 * 60 * 60 * 1000; // 7 days for synced
                const cutoffTime = Date.now() - maxAge;
                const syncedCutoffTime = Date.now() - syncedMaxAge;
                let cleaned = 0;
                
                const storeNames = ['login_attempts', 'attendance_records', 'form_submissions', 'sync_queue'];
                
                for (const storeName of storeNames) {
                    if (db.objectStoreNames.contains(storeName)) {
                        const transaction = db.transaction([storeName], 'readwrite');
                        const store = transaction.objectStore(storeName);
                        const request = store.openCursor();
                        
                        request.onsuccess = (event) => {
                            const cursor = event.target.result;
                            if (cursor) {
                                const record = cursor.value;
                                if ((record.synced && record.timestamp < syncedCutoffTime) ||
                                    (!record.synced && record.timestamp < cutoffTime)) {
                                    cursor.delete();
                                    cleaned++;
                                }
                                cursor.continue();
                            }
                        };
                    }
                }
                
                db.close();
                console.log(`Cleared ${cleaned} old IndexedDB records`);
                resolve(true);
            };
            
            request.onerror = () => resolve(false);
        });
    } catch (error) {
        console.error('Error clearing old IndexedDB records:', error);
        return false;
    }
}

// Storage usage monitoring
async function getStorageUsage() {
    try {
        if (window.cacheManager) {
            return await window.cacheManager.getStorageUsage();
        }
        
        // Fallback usage estimation
        const estimate = await navigator.storage.estimate();
        return {
            total: {
                usage: estimate.usage || 0,
                quota: estimate.quota || 0,
                percentage: estimate.quota ? (estimate.usage / estimate.quota) * 100 : 0
            }
        };
    } catch (error) {
        console.error('Error getting storage usage:', error);
        return null;
    }
}

// Event handlers for manual cleanup
function setupCacheCleanupButtons() {
    // Add click handlers for cleanup buttons if they exist
    const clearCacheBtn = document.getElementById('clear-cache-btn');
    const clearAllBtn = document.getElementById('clear-all-storage-btn');
    const quickCleanBtn = document.getElementById('quick-clean-btn');
    
    if (clearCacheBtn) {
        clearCacheBtn.addEventListener('click', async () => {
            clearCacheBtn.disabled = true;
            clearCacheBtn.textContent = 'Clearing...';
            
            const success = await clearAllCaches();
            
            clearCacheBtn.textContent = success ? 'Cache Cleared!' : 'Error';
            setTimeout(() => {
                clearCacheBtn.disabled = false;
                clearCacheBtn.textContent = 'Clear Cache';
            }, 2000);
        });
    }
    
    if (clearAllBtn) {
        clearAllBtn.addEventListener('click', async () => {
            if (confirm('This will clear all cached data. Continue?')) {
                clearAllBtn.disabled = true;
                clearAllBtn.textContent = 'Clearing...';
                
                const results = await Promise.all([
                    clearAllCaches(),
                    clearIndexedDB(),
                    clearLocalStorage()
                ]);
                
                const success = results.every(result => result);
                clearAllBtn.textContent = success ? 'All Cleared!' : 'Error';
                
                setTimeout(() => {
                    clearAllBtn.disabled = false;
                    clearAllBtn.textContent = 'Clear All Storage';
                    if (success) {
                        window.location.reload();
                    }
                }, 2000);
            }
        });
    }
    
    if (quickCleanBtn) {
        quickCleanBtn.addEventListener('click', async () => {
            quickCleanBtn.disabled = true;
            quickCleanBtn.textContent = 'Cleaning...';
            
            const success = await performQuickCleanup();
            
            quickCleanBtn.textContent = success ? 'Cleaned!' : 'Error';
            setTimeout(() => {
                quickCleanBtn.disabled = false;
                quickCleanBtn.textContent = 'Quick Clean';
            }, 2000);
        });
    }
}

// Auto-setup when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', setupCacheCleanupButtons);
} else {
    setupCacheCleanupButtons();
}

// Expose functions globally
window.clearAllCaches = clearAllCaches;
window.clearIndexedDB = clearIndexedDB;
window.clearLocalStorage = clearLocalStorage;
window.performQuickCleanup = performQuickCleanup;
window.getStorageUsage = getStorageUsage;