/**
 * Enhanced Cache Manager for KES-SMART PWA
 * Fixes IndexedDB version conflicts and handles offline attendance storage
 */

// Prevent multiple initialization by wrapping in IIFE
(function() {
  'use strict';
  
  // Check if already loaded
  if (window.enhancedCacheManagerLoaded) {
    console.log('Enhanced Cache Manager already loaded, skipping initialization');
    return;
  }
  window.enhancedCacheManagerLoaded = true;

  // Database configuration - ensure consistency across all modules
  const CACHE_DB_CONFIG = {
    name: 'kes-smart-offline-data',
    version: 3, // Increment to fix existing issues
    stores: {
      LOGIN: 'login_attempts',
      ATTENDANCE: 'attendance_records',
      FORMS: 'form_submissions',
      CACHE: 'cache_data'
    }
  };

let cacheDB = null;
let isInitializing = false;

/**
 * Initialize the enhanced cache database
 * This will fix any existing version conflicts
 */
function initEnhancedCacheDB() {
  return new Promise((resolve, reject) => {
    if (cacheDB && cacheDB.version >= CACHE_DB_CONFIG.version) {
      console.log('✅ Enhanced cache database already initialized (v' + cacheDB.version + ')');
      resolve(cacheDB);
      return;
    }
    
    if (isInitializing) {
      console.log('⏳ Database initialization already in progress...');
      // Wait for initialization to complete
      const checkInterval = setInterval(() => {
        if (!isInitializing) {
          clearInterval(checkInterval);
          if (cacheDB) {
            resolve(cacheDB);
          } else {
            reject(new Error('Database initialization failed'));
          }
        }
      }, 100);
      return;
    }
    
    isInitializing = true;
    console.log('🔄 Initializing enhanced cache database...');
    
    // Close any existing connection
    if (cacheDB) {
      cacheDB.close();
      cacheDB = null;
    }
    
    const request = indexedDB.open(CACHE_DB_CONFIG.name, CACHE_DB_CONFIG.version);
    
    request.onerror = (event) => {
      console.error('❌ Enhanced cache database error:', event.target.error);
      isInitializing = false;
      reject(new Error('Failed to initialize enhanced cache database: ' + event.target.error));
    };
    
    request.onblocked = (event) => {
      console.warn('⚠ Database upgrade blocked. Attempting alternative initialization...');
      isInitializing = false;
      
      // Try to open with current version first
      setTimeout(() => {
        console.log('🔄 Attempting to open database with current version...');
        tryOpenWithCurrentVersion()
          .then(resolve)
          .catch(fallbackError => {
            console.error('❌ Fallback failed:', fallbackError);
            // Show user-friendly message and provide manual fix option
            showDatabaseBlockedMessage();
            reject(new Error('Database upgrade blocked. Please close other tabs and refresh the page.'));
          });
      }, 1000);
    };
    
    request.onsuccess = (event) => {
      cacheDB = event.target.result;
      isInitializing = false;
      
      // Verify all stores exist
      const missingStores = Object.values(CACHE_DB_CONFIG.stores).filter(
        storeName => !cacheDB.objectStoreNames.contains(storeName)
      );
      
      if (missingStores.length > 0) {
        console.error('❌ Missing stores:', missingStores);
        console.log('🔄 Forcing database reset to fix missing stores...');
        cacheDB.close();
        cacheDB = null;
        
        // Delete and recreate database
        const deleteRequest = indexedDB.deleteDatabase(CACHE_DB_CONFIG.name);
        deleteRequest.onsuccess = () => {
          console.log('✅ Old database deleted, recreating...');
          initEnhancedCacheDB().then(resolve).catch(reject);
        };
        deleteRequest.onerror = () => {
          reject(new Error('Failed to reset database'));
        };
        return;
      }
      
      console.log('✅ Enhanced cache database initialized successfully (v' + cacheDB.version + ')');
      console.log('📦 Available stores:', Array.from(cacheDB.objectStoreNames));
      
      // Update global variables for backward compatibility
      if (typeof window !== 'undefined') {
        window.db = cacheDB;
        window.DB_NAME = CACHE_DB_CONFIG.name;
        window.DB_VERSION = CACHE_DB_CONFIG.version;
        window.STORE_NAMES = CACHE_DB_CONFIG.stores;
      }
      
      resolve(cacheDB);
    };
    
    request.onupgradeneeded = (event) => {
      console.log('🔄 Database upgrade needed from version', event.oldVersion, 'to', event.newVersion);
      const db = event.target.result;
      const transaction = event.target.transaction;
      
      // Create LOGIN store
      if (!db.objectStoreNames.contains(CACHE_DB_CONFIG.stores.LOGIN)) {
        console.log('📦 Creating LOGIN store...');
        const loginStore = db.createObjectStore(CACHE_DB_CONFIG.stores.LOGIN, { 
          keyPath: 'id', 
          autoIncrement: true 
        });
        loginStore.createIndex('username', 'username', { unique: false });
        loginStore.createIndex('timestamp', 'timestamp', { unique: false });
        loginStore.createIndex('synced', 'synced', { unique: false });
      }
      
      // Create ATTENDANCE store with all necessary indexes
      if (!db.objectStoreNames.contains(CACHE_DB_CONFIG.stores.ATTENDANCE)) {
        console.log('📦 Creating ATTENDANCE store...');
        const attendanceStore = db.createObjectStore(CACHE_DB_CONFIG.stores.ATTENDANCE, { 
          keyPath: 'id', 
          autoIncrement: true 
        });
        attendanceStore.createIndex('student_id', 'student_id', { unique: false });
        attendanceStore.createIndex('timestamp', 'timestamp', { unique: false });
        attendanceStore.createIndex('synced', 'synced', { unique: false });
        attendanceStore.createIndex('scan_type', 'scan_type', { unique: false });
        attendanceStore.createIndex('date', 'date', { unique: false });
      } else {
        // Update existing attendance store
        console.log('📦 Updating ATTENDANCE store indexes...');
        const attendanceStore = transaction.objectStore(CACHE_DB_CONFIG.stores.ATTENDANCE);
        
        // Add missing indexes
        const requiredIndexes = ['synced', 'scan_type', 'date'];
        requiredIndexes.forEach(indexName => {
          if (!attendanceStore.indexNames.contains(indexName)) {
            attendanceStore.createIndex(indexName, indexName, { unique: false });
            console.log(`✅ Added ${indexName} index to attendance store`);
          }
        });
      }
      
      // Create FORMS store
      if (!db.objectStoreNames.contains(CACHE_DB_CONFIG.stores.FORMS)) {
        console.log('📦 Creating FORMS store...');
        const formsStore = db.createObjectStore(CACHE_DB_CONFIG.stores.FORMS, { 
          keyPath: 'id', 
          autoIncrement: true 
        });
        formsStore.createIndex('form_type', 'form_type', { unique: false });
        formsStore.createIndex('timestamp', 'timestamp', { unique: false });
        formsStore.createIndex('synced', 'synced', { unique: false });
      }
      
      // Create CACHE store for general caching needs
      if (!db.objectStoreNames.contains(CACHE_DB_CONFIG.stores.CACHE)) {
        console.log('📦 Creating CACHE store...');
        const cacheStore = db.createObjectStore(CACHE_DB_CONFIG.stores.CACHE, { 
          keyPath: 'key' 
        });
        cacheStore.createIndex('timestamp', 'timestamp', { unique: false });
        cacheStore.createIndex('expires', 'expires', { unique: false });
      }
      
      console.log('✅ Database upgrade completed successfully');
    };
  });
}

/**
 * Try to open database with current version (fallback for blocked upgrades)
 */
function tryOpenWithCurrentVersion() {
  return new Promise((resolve, reject) => {
    // First, try to detect the current version
    const detectRequest = indexedDB.open(CACHE_DB_CONFIG.name);
    
    detectRequest.onsuccess = (event) => {
      const currentDB = event.target.result;
      const currentVersion = currentDB.version;
      currentDB.close();
      
      console.log(`🔍 Detected current database version: ${currentVersion}`);
      
      // Try to open with the current version
      const openRequest = indexedDB.open(CACHE_DB_CONFIG.name, currentVersion);
      
      openRequest.onsuccess = (event) => {
        cacheDB = event.target.result;
        console.log(`✅ Opened database with current version ${currentVersion}`);
        
        // Check if we have the required stores
        const missingStores = Object.values(CACHE_DB_CONFIG.stores).filter(
          storeName => !cacheDB.objectStoreNames.contains(storeName)
        );
        
        if (missingStores.length > 0) {
          console.warn(`⚠ Missing stores: ${missingStores.join(', ')}`);
          console.log('💡 Some features may not work properly until database is upgraded');
          
          // Create a warning but don't fail completely
          console.log('[WARNING] Database partially initialized. Some features may be limited.');
        }
        
        resolve(cacheDB);
      };
      
      openRequest.onerror = (event) => {
        console.error('❌ Failed to open with current version:', event.target.error);
        reject(event.target.error);
      };
    };
    
    detectRequest.onerror = (event) => {
      console.error('❌ Failed to detect database version:', event.target.error);
      reject(event.target.error);
    };
  });
}

/**
 * Show user-friendly message when database is blocked
 */
function showDatabaseBlockedMessage() {
  console.log('📢 Showing database blocked message to user');
  
  // Try to show toast message
  console.log('[WARNING] Database upgrade needed. Please close other tabs of this site and refresh.');
  
  // Create a more prominent notification
  const alertHtml = `
    <div class="alert alert-warning alert-dismissible fade show" role="alert" style="position: fixed; top: 20px; right: 20px; z-index: 9999; max-width: 400px;">
      <h6><i class="fas fa-database me-2"></i>Database Upgrade Needed</h6>
      <p class="mb-2">The offline database needs to be updated, but it's blocked by other tabs.</p>
      <div class="d-grid gap-2">
        <button class="btn btn-sm btn-primary" onclick="window.location.reload()">
          <i class="fas fa-sync me-1"></i>Refresh Page
        </button>
        <button class="btn btn-sm btn-outline-warning" onclick="fixDatabaseBlocked()">
          <i class="fas fa-tools me-1"></i>Force Reset Database
        </button>
      </div>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  `;
  
  // Add to page
  document.body.insertAdjacentHTML('beforeend', alertHtml);
  
  // Auto-remove after 10 seconds
  setTimeout(() => {
    const alert = document.querySelector('.alert-warning[role="alert"]');
    if (alert) alert.remove();
  }, 10000);
}

/**
 * Force fix for blocked database (user-triggered)
 */
function fixDatabaseBlocked() {
  console.log('🔧 User requested database reset to fix blocked upgrade');
  
  if (confirm('This will reset the offline database and you may lose unsync data. Continue?')) {
    // Close any existing connection
    if (cacheDB) {
      cacheDB.close();
      cacheDB = null;
    }
    
    // Force delete the database
    const deleteRequest = indexedDB.deleteDatabase(CACHE_DB_CONFIG.name);
    
    deleteRequest.onsuccess = () => {
      console.log('✅ Database force deleted successfully');
      alert('Database reset successful! The page will now reload.');
      window.location.reload();
    };
    
    deleteRequest.onerror = (event) => {
      console.error('❌ Failed to force delete database:', event.target.error);
      alert('Failed to reset database. Please close all tabs and try again.');
    };
    
    deleteRequest.onblocked = () => {
      alert('Reset is still blocked. Please close ALL tabs of this website and try again.');
    };
  }
}

/**
 * Initialize in degraded mode when database can't be opened
 */
function initializeDegradedMode() {
  console.log('⚠ Initializing Enhanced Cache Manager in degraded mode...');
  
  // Create mock database functions that use localStorage as fallback
  const degradedStorage = {
    store: (key, data) => {
      try {
        localStorage.setItem(`kes_degraded_${key}`, JSON.stringify(data));
        return Promise.resolve(true);
      } catch (error) {
        console.error('Degraded storage failed:', error);
        return Promise.reject(error);
      }
    },
    
    get: (key) => {
      try {
        const data = localStorage.getItem(`kes_degraded_${key}`);
        return Promise.resolve(data ? JSON.parse(data) : []);
      } catch (error) {
        console.error('Degraded retrieval failed:', error);
        return Promise.resolve([]);
      }
    },
    
    remove: (key) => {
      try {
        localStorage.removeItem(`kes_degraded_${key}`);
        return Promise.resolve(true);
      } catch (error) {
        return Promise.resolve(false);
      }
    }
  };
  
  // Override functions with localStorage versions
  window.storeEnhancedOfflineAttendance = (data) => {
    console.log('📝 Storing attendance in degraded mode (localStorage)');
    const record = {
      ...data,
      id: Date.now(), // Simple ID generation
      degraded: true
    };
    
    return degradedStorage.get('attendance').then(records => {
      records.push(record);
      return degradedStorage.store('attendance', records).then(() => record);
    });
  };
  
  window.getUnsyncedAttendanceRecords = () => {
    console.log('📋 Getting attendance from degraded mode (localStorage)');
    return degradedStorage.get('attendance').then(records => {
      return records.filter(r => !r.synced);
    });
  };
  
  window.markAttendanceAsSynced = (recordId) => {
    console.log('✅ Marking attendance as synced in degraded mode');
    return degradedStorage.get('attendance').then(records => {
      const record = records.find(r => r.id === recordId);
      if (record) {
        record.synced = true;
        record.synced_at = new Date().toISOString();
        return degradedStorage.store('attendance', records);
      }
      return Promise.resolve(true);
    });
  };
  
  // Show warning about degraded mode
  console.log('[WARNING] Offline features running in limited mode. Some data may not persist.');
  
  console.log('⚠ Degraded mode initialization complete');
}

/**
 * Enhanced offline attendance storage
 * Fixes the "object store not found" error
 */
function storeEnhancedOfflineAttendance(attendanceData) {
  return new Promise(async (resolve, reject) => {
    try {
      // Ensure database is initialized
      if (!cacheDB) {
        console.log('📦 Database not ready, initializing...');
        await initEnhancedCacheDB();
      }
      
      // Verify attendance store exists
      if (!cacheDB.objectStoreNames.contains(CACHE_DB_CONFIG.stores.ATTENDANCE)) {
        throw new Error('ATTENDANCE store not found. Database version: ' + cacheDB.version);
      }
      
      const transaction = cacheDB.transaction([CACHE_DB_CONFIG.stores.ATTENDANCE], 'readwrite');
      const store = transaction.objectStore(CACHE_DB_CONFIG.stores.ATTENDANCE);
      
      // Prepare attendance record
      const record = {
        ...attendanceData,
        timestamp: attendanceData.timestamp || new Date().getTime(),
        date: attendanceData.date || new Date().toISOString().split('T')[0],
        synced: false,
        sync_attempts: 0,
        created_at: new Date().toISOString()
      };
      
      const request = store.add(record);
      
      request.onsuccess = (event) => {
        const id = event.target.result;
        console.log('✅ Enhanced offline attendance stored successfully:', { id, ...record });
        resolve({ id, ...record });
      };
      
      request.onerror = (event) => {
        console.error('❌ Error storing enhanced offline attendance:', event.target.error);
        reject(new Error('Failed to store attendance: ' + event.target.error));
      };
      
      transaction.onerror = (event) => {
        console.error('❌ Transaction error:', event.target.error);
        reject(new Error('Transaction failed: ' + event.target.error));
      };
      
    } catch (error) {
      console.error('❌ Exception in storeEnhancedOfflineAttendance:', error);
      reject(error);
    }
  });
}

/**
 * Get unsynced attendance records
 */
function getUnsyncedAttendanceRecords() {
  return new Promise(async (resolve, reject) => {
    try {
      if (!cacheDB) {
        await initEnhancedCacheDB();
      }
      
      const transaction = cacheDB.transaction([CACHE_DB_CONFIG.stores.ATTENDANCE], 'readonly');
      const store = transaction.objectStore(CACHE_DB_CONFIG.stores.ATTENDANCE);
      
      // Use cursor instead of index to avoid invalid key errors
      const request = store.openCursor();
      const unsyncedRecords = [];
      
      request.onsuccess = (event) => {
        const cursor = event.target.result;
        if (cursor) {
          const record = cursor.value;
          // Check if record is not synced (false, null, undefined, or missing field)
          if (!record.synced) {
            unsyncedRecords.push(record);
          }
          cursor.continue();
        } else {
          // All records processed
          console.log(`📊 Found ${unsyncedRecords.length} unsynced attendance record(s)`);
          resolve(unsyncedRecords);
        }
      };
      
      request.onerror = (event) => {
        console.error('❌ Error getting unsynced records:', event.target.error);
        reject(new Error('Failed to get unsynced records: ' + event.target.error));
      };
      
      transaction.onerror = (event) => {
        console.error('❌ Transaction error in getUnsyncedAttendanceRecords:', event.target.error);
        reject(new Error('Transaction failed: ' + event.target.error));
      };
      
    } catch (error) {
      console.error('❌ Exception in getUnsyncedAttendanceRecords:', error);
      // Fallback: try to get all records and filter manually
      try {
        const transaction = cacheDB.transaction([CACHE_DB_CONFIG.stores.ATTENDANCE], 'readonly');
        const store = transaction.objectStore(CACHE_DB_CONFIG.stores.ATTENDANCE);
        const getAllRequest = store.getAll();
        
        getAllRequest.onsuccess = (event) => {
          const allRecords = event.target.result || [];
          const unsyncedRecords = allRecords.filter(record => !record.synced);
          console.log(`📊 Fallback: Found ${unsyncedRecords.length} unsynced attendance record(s)`);
          resolve(unsyncedRecords);
        };
        
        getAllRequest.onerror = (event) => {
          console.error('❌ Fallback failed:', event.target.error);
          resolve([]); // Return empty array instead of rejecting
        };
        
      } catch (fallbackError) {
        console.error('❌ Fallback exception:', fallbackError);
        resolve([]); // Return empty array instead of rejecting
      }
    }
  });
}

/**
 * Mark attendance record as synced
 */
function markAttendanceAsSynced(recordId) {
  return new Promise(async (resolve, reject) => {
    try {
      if (!cacheDB) {
        await initEnhancedCacheDB();
      }
      
      const transaction = cacheDB.transaction([CACHE_DB_CONFIG.stores.ATTENDANCE], 'readwrite');
      const store = transaction.objectStore(CACHE_DB_CONFIG.stores.ATTENDANCE);
      
      const getRequest = store.get(recordId);
      
      getRequest.onsuccess = (event) => {
        const record = event.target.result;
        if (record) {
          record.synced = true;
          record.synced_at = new Date().toISOString();
          
          const putRequest = store.put(record);
          putRequest.onsuccess = () => {
            console.log('✅ Record marked as synced:', recordId);
            resolve(true);
          };
          putRequest.onerror = (event) => {
            reject(new Error('Failed to mark as synced: ' + event.target.error));
          };
        } else {
          reject(new Error('Record not found: ' + recordId));
        }
      };
      
      getRequest.onerror = (event) => {
        reject(new Error('Failed to get record: ' + event.target.error));
      };
      
    } catch (error) {
      console.error('❌ Exception in markAttendanceAsSynced:', error);
      reject(error);
    }
  });
}

/**
 * Clear old synced records to prevent database bloat
 */
function cleanupOldRecords(daysToKeep = 7) {
  return new Promise(async (resolve, reject) => {
    try {
      if (!cacheDB) {
        await initEnhancedCacheDB();
      }
      
      const cutoffTime = new Date().getTime() - (daysToKeep * 24 * 60 * 60 * 1000);
      const transaction = cacheDB.transaction([CACHE_DB_CONFIG.stores.ATTENDANCE], 'readwrite');
      const store = transaction.objectStore(CACHE_DB_CONFIG.stores.ATTENDANCE);
      const index = store.index('timestamp');
      
      const request = index.openCursor(IDBKeyRange.upperBound(cutoffTime));
      let deletedCount = 0;
      
      request.onsuccess = (event) => {
        const cursor = event.target.result;
        if (cursor) {
          const record = cursor.value;
          // Only delete synced records
          if (record.synced) {
            cursor.delete();
            deletedCount++;
          }
          cursor.continue();
        } else {
          console.log(`🧹 Cleanup completed: ${deletedCount} old records deleted`);
          resolve(deletedCount);
        }
      };
      
      request.onerror = (event) => {
        reject(new Error('Cleanup failed: ' + event.target.error));
      };
      
    } catch (error) {
      console.error('❌ Exception in cleanupOldRecords:', error);
      reject(error);
    }
  });
}

/**
 * Clean corrupted attendance records
 */
function cleanCorruptedAttendanceRecords() {
  return new Promise(async (resolve, reject) => {
    try {
      if (!cacheDB) {
        await initEnhancedCacheDB();
      }
      
      console.log('🧹 Cleaning corrupted attendance records...');
      
      const transaction = cacheDB.transaction([CACHE_DB_CONFIG.stores.ATTENDANCE], 'readwrite');
      const store = transaction.objectStore(CACHE_DB_CONFIG.stores.ATTENDANCE);
      const request = store.openCursor();
      
      let cleanedCount = 0;
      let totalCount = 0;
      
      request.onsuccess = (event) => {
        const cursor = event.target.result;
        if (cursor) {
          totalCount++;
          const record = cursor.value;
          
          // Fix records with invalid synced values
          if (record.synced === null || record.synced === undefined) {
            record.synced = false;
            const updateRequest = cursor.update(record);
            updateRequest.onsuccess = () => cleanedCount++;
          }
          
          cursor.continue();
        } else {
          console.log(`✅ Cleaned ${cleanedCount} of ${totalCount} attendance records`);
          resolve({ cleaned: cleanedCount, total: totalCount });
        }
      };
      
      request.onerror = (event) => {
        console.error('❌ Error cleaning records:', event.target.error);
        reject(new Error('Failed to clean records: ' + event.target.error));
      };
      
    } catch (error) {
      console.error('❌ Exception in cleanCorruptedAttendanceRecords:', error);
      reject(error);
    }
  });
}

/**
 * Reset database (for troubleshooting)
 */
function resetEnhancedCacheDB() {
  return new Promise((resolve, reject) => {
    console.log('🔄 Resetting enhanced cache database...');
    
    if (cacheDB) {
      cacheDB.close();
      cacheDB = null;
    }
    
    const deleteRequest = indexedDB.deleteDatabase(CACHE_DB_CONFIG.name);
    
    deleteRequest.onsuccess = () => {
      console.log('✅ Database reset successful');
      initEnhancedCacheDB().then(resolve).catch(reject);
    };
    
    deleteRequest.onerror = (event) => {
      console.error('❌ Failed to reset database:', event.target.error);
      reject(new Error('Failed to reset database: ' + event.target.error));
    };
    
    deleteRequest.onblocked = () => {
      console.warn('⚠ Database reset blocked. Please close all tabs and refresh.');
      reject(new Error('Database reset blocked. Please close all tabs and refresh.'));
    };
  });
}

/**
 * Enhanced initialization that fixes existing issues
 */
function initializeEnhancedCacheManager() {
  return new Promise(async (resolve, reject) => {
    try {
      console.log('🚀 Starting Enhanced Cache Manager initialization...');
      
      // Check if IndexedDB is supported
      if (!('indexedDB' in window)) {
        throw new Error('IndexedDB not supported in this browser');
      }
      
      // Try multiple initialization strategies
      let initError = null;
      
      try {
        // Strategy 1: Normal initialization
        await initEnhancedCacheDB();
      } catch (error) {
        initError = error;
        console.warn('⚠ Primary initialization failed:', error.message);
        
        if (error.message.includes('blocked')) {
          console.log('🔄 Trying fallback initialization strategy...');
          
          try {
            // Strategy 2: Try with current version
            await tryOpenWithCurrentVersion();
            console.log('✅ Fallback strategy succeeded');
          } catch (fallbackError) {
            console.error('❌ Fallback strategy also failed:', fallbackError.message);
            
            // Strategy 3: Work in degraded mode
            console.log('🔄 Initializing in degraded mode...');
            initializeDegradedMode();
          }
        } else {
          throw error; // Re-throw non-blocked errors
        }
      }
      
      // Set up global variables for backward compatibility
      if (typeof window !== 'undefined') {
        // Override the old functions with enhanced versions
        window.storeOfflineAttendance = storeEnhancedOfflineAttendance;
        window.getUnsyncedData = (storeName) => {
          if (storeName === CACHE_DB_CONFIG.stores.ATTENDANCE) {
            return getUnsyncedAttendanceRecords();
          }
          // Fallback for other stores
          return Promise.resolve([]);
        };
        window.markAsSynced = (storeName, recordId) => {
          if (storeName === CACHE_DB_CONFIG.stores.ATTENDANCE) {
            return markAttendanceAsSynced(recordId);
          }
          return Promise.resolve(true);
        };
        
        // Export new functions
        window.storeEnhancedOfflineAttendance = storeEnhancedOfflineAttendance;
        window.getUnsyncedAttendanceRecords = getUnsyncedAttendanceRecords;
        window.markAttendanceAsSynced = markAttendanceAsSynced;
        window.cleanupOldRecords = cleanupOldRecords;
        window.cleanCorruptedAttendanceRecords = cleanCorruptedAttendanceRecords;
        window.resetEnhancedCacheDB = resetEnhancedCacheDB;
        window.initEnhancedCacheDB = initEnhancedCacheDB;
        window.fixDatabaseBlocked = fixDatabaseBlocked;
        window.startDatabaseRetryTimer = startDatabaseRetryTimer;
        
        // Update global database reference
        window.db = cacheDB;
        window.STORE_NAMES = CACHE_DB_CONFIG.stores;
        window.CACHE_DB_CONFIG = CACHE_DB_CONFIG;
      }
      
      console.log('✅ Enhanced Cache Manager initialized successfully');
      resolve(cacheDB);
      
    } catch (error) {
      console.error('❌ Enhanced Cache Manager initialization failed:', error);
      reject(error);
    }
  });
}

/**
 * Periodic retry for blocked database upgrades
 */
function startDatabaseRetryTimer() {
  console.log('⏰ Starting database retry timer (will retry every 30 seconds)');
  
  const retryInterval = setInterval(() => {
    // Only retry if we don't have a working database
    if (!cacheDB || cacheDB.version < CACHE_DB_CONFIG.version) {
      console.log('🔄 Retrying database initialization...');
      
      initEnhancedCacheDB()
        .then(() => {
          console.log('✅ Retry successful! Database now working properly.');
          clearInterval(retryInterval);
          
          console.log('[SUCCESS] Database upgraded successfully!');
        })
        .catch(error => {
          console.log('⏳ Retry failed, will try again in 30 seconds:', error.message);
        });
    } else {
      // Database is working, stop retrying
      clearInterval(retryInterval);
      console.log('✅ Database is working properly, stopping retry timer');
    }
  }, 30000); // Retry every 30 seconds
  
  // Stop retrying after 10 minutes
  setTimeout(() => {
    clearInterval(retryInterval);
    console.log('⏹ Stopped database retry timer after 10 minutes');
  }, 600000);
}

// Auto-fix corrupted records on initialization
function autoFixCorruptedRecords() {
  setTimeout(async () => {
    try {
      console.log('🔧 Running auto-fix for corrupted records...');
      const result = await cleanCorruptedAttendanceRecords();
      if (result.cleaned > 0) {
        console.log(`✅ Auto-fixed ${result.cleaned} corrupted records`);
      }
    } catch (error) {
      console.warn('⚠️ Auto-fix failed, but system will still work:', error.message);
    }
  }, 2000); // Run after 2 seconds to let everything initialize
}

// Auto-initialize when the DOM is ready
if (typeof document !== 'undefined') {
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
      initializeEnhancedCacheManager()
        .then(() => {
          console.log('✅ Enhanced Cache Manager ready');
          autoFixCorruptedRecords(); // Auto-fix any issues
        })
        .catch(error => {
          console.error('Failed to initialize Enhanced Cache Manager:', error);
          
          // Start retry timer for blocked databases
          if (error.message.includes('blocked')) {
            startDatabaseRetryTimer();
          }
        });
    });
  } else {
    initializeEnhancedCacheManager()
      .then(() => {
        console.log('✅ Enhanced Cache Manager ready');
        autoFixCorruptedRecords(); // Auto-fix any issues
      })
      .catch(error => {
        console.error('Failed to initialize Enhanced Cache Manager:', error);
        
        // Start retry timer for blocked databases
        if (error.message.includes('blocked')) {
          startDatabaseRetryTimer();
        }
      });
  }
}

// Add console commands for debugging
if (typeof window !== 'undefined') {
  setTimeout(() => {
    console.log('🛠 ENHANCED CACHE MANAGER COMMANDS:');
    console.log('  - fixDatabaseBlocked() - Force reset blocked database');
    console.log('  - window.resetEnhancedCacheDB() - Clean database reset');
    console.log('  - window.cleanCorruptedAttendanceRecords() - Fix corrupted records');
    console.log('  - window.initEnhancedCacheDB() - Reinitialize database');
    console.log('  - startDatabaseRetryTimer() - Start automatic retry');
    console.log('📊 Database status:', cacheDB ? `Ready (v${cacheDB.version})` : 'Not initialized');
  }, 1000);
}

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
  module.exports = {
    initializeEnhancedCacheManager,
    storeEnhancedOfflineAttendance,
    getUnsyncedAttendanceRecords,
    markAttendanceAsSynced,
    cleanupOldRecords,
    resetEnhancedCacheDB,
    CACHE_DB_CONFIG
  };
}

})(); // Close the IIFE