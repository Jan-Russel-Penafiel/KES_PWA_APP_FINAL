/**
 * KES-SMART Offline Forms Management
 * Handles storing and syncing form submissions when offline
 * Enhanced with Background Sync API and improved fetch-based sync
 */

// IndexedDB configuration
const DB_NAME = 'kes-smart-offline-data';
const DB_VERSION = 2;
const STORE_NAMES = {
  LOGIN: 'login_attempts',
  ATTENDANCE: 'attendance_records',
  FORMS: 'form_submissions',
  SYNC_QUEUE: 'sync_queue'
};

// Sync configuration
const SYNC_CONFIG = {
  MAX_RETRIES: 3,
  RETRY_DELAY: 5000, // 5 seconds
  BATCH_SIZE: 10,
  TIMEOUT: 30000 // 30 seconds
};

let db;
let isSyncing = false;

// Initialize IndexedDB
function initOfflineDB() {
  return new Promise((resolve, reject) => {
    // Open the database
    const request = indexedDB.open(DB_NAME, DB_VERSION);
    
    // Handle errors
    request.onerror = (event) => {
      console.error('IndexedDB error:', event.target.error);
      reject('Failed to initialize offline storage');
    };
    
    // Handle success
    request.onsuccess = (event) => {
      db = event.target.result;
      console.log('Offline storage initialized successfully');
      resolve(db);
    };
    
    // Create object stores when the database is first created or version is updated
    request.onupgradeneeded = (event) => {
      const db = event.target.result;
      
      // Create object stores if they don't exist
      if (!db.objectStoreNames.contains(STORE_NAMES.LOGIN)) {
        const loginStore = db.createObjectStore(STORE_NAMES.LOGIN, { keyPath: 'id', autoIncrement: true });
        loginStore.createIndex('username', 'username', { unique: false });
        loginStore.createIndex('timestamp', 'timestamp', { unique: false });
        loginStore.createIndex('synced', 'synced', { unique: false });
      }
      
      if (!db.objectStoreNames.contains(STORE_NAMES.ATTENDANCE)) {
        const attendanceStore = db.createObjectStore(STORE_NAMES.ATTENDANCE, { keyPath: 'id', autoIncrement: true });
        attendanceStore.createIndex('student_id', 'student_id', { unique: false });
        attendanceStore.createIndex('timestamp', 'timestamp', { unique: false });
        attendanceStore.createIndex('synced', 'synced', { unique: false });
        attendanceStore.createIndex('retry_count', 'retry_count', { unique: false });
      }
      
      if (!db.objectStoreNames.contains(STORE_NAMES.FORMS)) {
        const formsStore = db.createObjectStore(STORE_NAMES.FORMS, { keyPath: 'id', autoIncrement: true });
        formsStore.createIndex('form_type', 'form_type', { unique: false });
        formsStore.createIndex('timestamp', 'timestamp', { unique: false });
        formsStore.createIndex('synced', 'synced', { unique: false });
        formsStore.createIndex('retry_count', 'retry_count', { unique: false });
      }
      
      // New sync queue for Background Sync
      if (!db.objectStoreNames.contains(STORE_NAMES.SYNC_QUEUE)) {
        const syncStore = db.createObjectStore(STORE_NAMES.SYNC_QUEUE, { keyPath: 'id', autoIncrement: true });
        syncStore.createIndex('type', 'type', { unique: false });
        syncStore.createIndex('priority', 'priority', { unique: false });
        syncStore.createIndex('timestamp', 'timestamp', { unique: false });
        syncStore.createIndex('retry_count', 'retry_count', { unique: false });
      }
    };
  });
}

// Store an offline login attempt
function storeOfflineLogin(username, role) {
  return new Promise((resolve, reject) => {
    if (!db) {
      reject('Offline database not initialized');
      return;
    }
    
    const transaction = db.transaction([STORE_NAMES.LOGIN], 'readwrite');
    const store = transaction.objectStore(STORE_NAMES.LOGIN);
    
    const loginData = {
      username: username,
      role: role,
      timestamp: new Date().getTime(),
      synced: false
    };
    
    const request = store.add(loginData);
    
    request.onsuccess = () => resolve(true);
    request.onerror = (event) => {
      console.error('Error storing offline login:', event.target.error);
      reject(event.target.error);
    };
  });
}

// Store offline form submission
function storeOfflineFormSubmission(formType, formData) {
  return new Promise((resolve, reject) => {
    if (!db) {
      reject('Offline database not initialized');
      return;
    }
    
    const transaction = db.transaction([STORE_NAMES.FORMS], 'readwrite');
    const store = transaction.objectStore(STORE_NAMES.FORMS);
    
    const submission = {
      form_type: formType,
      data: formData,
      timestamp: new Date().getTime(),
      synced: false
    };
    
    const request = store.add(submission);
    
    request.onsuccess = () => resolve(true);
    request.onerror = (event) => {
      console.error('Error storing form submission:', event.target.error);
      reject(event.target.error);
    };
  });
}

// Store offline attendance record
function storeOfflineAttendance(studentId, status, date, extraData = {}) {
  return new Promise((resolve, reject) => {
    if (!db) {
      reject('Offline database not initialized');
      return;
    }
    
    const transaction = db.transaction([STORE_NAMES.ATTENDANCE, STORE_NAMES.SYNC_QUEUE], 'readwrite');
    const attendanceStore = transaction.objectStore(STORE_NAMES.ATTENDANCE);
    const syncStore = transaction.objectStore(STORE_NAMES.SYNC_QUEUE);
    
    const timestamp = new Date().getTime();
    
    const attendanceRecord = {
      student_id: studentId,
      status: status,
      date: date || new Date().toISOString().split('T')[0],
      timestamp: timestamp,
      synced: false,
      retry_count: 0,
      ...extraData
    };
    
    const request = attendanceStore.add(attendanceRecord);
    
    request.onsuccess = (event) => {
      const recordId = event.target.result;
      
      // Add to sync queue
      const syncItem = {
        type: 'attendance',
        data_id: recordId,
        priority: 1, // High priority
        timestamp: timestamp,
        retry_count: 0
      };
      
      syncStore.add(syncItem);
      
      // Trigger background sync if available
      registerBackgroundSync('sync-attendance');
      
      resolve({ success: true, id: recordId });
    };
    
    request.onerror = (event) => {
      console.error('Error storing attendance record:', event.target.error);
      reject(event.target.error);
    };
  });
}

// Get all unsynced data from a store
function getUnsyncedData(storeName) {
  return new Promise((resolve, reject) => {
    if (!db) {
      reject('Offline database not initialized');
      return;
    }
    
    const transaction = db.transaction([storeName], 'readonly');
    const store = transaction.objectStore(storeName);
    const request = store.index('timestamp').openCursor();
    
    const results = [];
    
    request.onsuccess = (event) => {
      const cursor = event.target.result;
      if (cursor) {
        if (!cursor.value.synced) {
          results.push(cursor.value);
        }
        cursor.continue();
      } else {
        resolve(results);
      }
    };
    
    request.onerror = (event) => {
      console.error('Error getting unsynced data:', event.target.error);
      reject(event.target.error);
    };
  });
}

// Mark data as synced
function markAsSynced(storeName, id) {
  return new Promise((resolve, reject) => {
    if (!db) {
      reject('Offline database not initialized');
      return;
    }
    
    const transaction = db.transaction([storeName], 'readwrite');
    const store = transaction.objectStore(storeName);
    
    const request = store.get(id);
    
    request.onsuccess = (event) => {
      const data = event.target.result;
      if (data) {
        data.synced = true;
        store.put(data);
        resolve(true);
      } else {
        reject('Data not found');
      }
    };
    
    request.onerror = (event) => {
      console.error('Error marking as synced:', event.target.error);
      reject(event.target.error);
    };
  });
}

// Register Background Sync
function registerBackgroundSync(tag) {
  if ('serviceWorker' in navigator && 'SyncManager' in window) {
    navigator.serviceWorker.ready.then(registration => {
      return registration.sync.register(tag);
    }).then(() => {
      console.log(`Background sync registered: ${tag}`);
      dispatchSyncEvent('sync-registered', { tag });
    }).catch(error => {
      console.warn('Background sync registration failed, falling back to manual sync:', error);
      // Fallback to immediate sync attempt
      syncOfflineData();
    });
  } else {
    console.log('Background Sync not supported, using fetch fallback');
    // Immediate sync attempt as fallback
    syncOfflineData();
  }
}

// Dispatch custom sync events
function dispatchSyncEvent(eventName, detail = {}) {
  const event = new CustomEvent(eventName, { detail });
  window.dispatchEvent(event);
}

// Sync all offline data when back online
async function syncOfflineData() {
  if (!navigator.onLine) {
    console.log('Cannot sync while offline');
    dispatchSyncEvent('sync-failed', { reason: 'offline' });
    return { success: false, message: 'Device is offline' };
  }
  
  if (isSyncing) {
    console.log('Sync already in progress');
    return { success: false, message: 'Sync already in progress' };
  }
  
  if (!db) {
    try {
      await initOfflineDB();
    } catch (error) {
      console.error('Failed to initialize database for sync:', error);
      dispatchSyncEvent('sync-failed', { reason: 'db-error', error });
      return { success: false, message: 'Database initialization failed' };
    }
  }
  
  isSyncing = true;
  dispatchSyncEvent('sync-started');
  
  const syncResults = {
    attendance: { success: 0, failed: 0, total: 0 },
    forms: { success: 0, failed: 0, total: 0 },
    login: { success: 0, failed: 0, total: 0 }
  };
  
  try {
    // Sync attendance records with batch processing
    const attendanceRecords = await getUnsyncedData(STORE_NAMES.ATTENDANCE);
    syncResults.attendance.total = attendanceRecords.length;
    
    if (attendanceRecords.length > 0) {
      console.log(`Syncing ${attendanceRecords.length} attendance records`);
      dispatchSyncEvent('sync-progress', { 
        stage: 'attendance', 
        total: attendanceRecords.length 
      });
      
      // Process in batches
      for (let i = 0; i < attendanceRecords.length; i += SYNC_CONFIG.BATCH_SIZE) {
        const batch = attendanceRecords.slice(i, i + SYNC_CONFIG.BATCH_SIZE);
        
        const batchResults = await Promise.allSettled(
          batch.map(record => syncAttendanceRecord(record))
        );
        
        batchResults.forEach((result, index) => {
          if (result.status === 'fulfilled' && result.value.success) {
            syncResults.attendance.success++;
          } else {
            syncResults.attendance.failed++;
            console.error('Failed to sync attendance:', batch[index], result.reason);
          }
        });
        
        dispatchSyncEvent('sync-progress', { 
          stage: 'attendance', 
          completed: Math.min(i + SYNC_CONFIG.BATCH_SIZE, attendanceRecords.length),
          total: attendanceRecords.length 
        });
      }
    }
    
    // Sync form submissions
    const formSubmissions = await getUnsyncedData(STORE_NAMES.FORMS);
    syncResults.forms.total = formSubmissions.length;
    
    if (formSubmissions.length > 0) {
      console.log(`Syncing ${formSubmissions.length} form submissions`);
      dispatchSyncEvent('sync-progress', { 
        stage: 'forms', 
        total: formSubmissions.length 
      });
      
      for (let i = 0; i < formSubmissions.length; i += SYNC_CONFIG.BATCH_SIZE) {
        const batch = formSubmissions.slice(i, i + SYNC_CONFIG.BATCH_SIZE);
        
        const batchResults = await Promise.allSettled(
          batch.map(form => syncFormSubmission(form))
        );
        
        batchResults.forEach((result, index) => {
          if (result.status === 'fulfilled' && result.value.success) {
            syncResults.forms.success++;
          } else {
            syncResults.forms.failed++;
            console.error('Failed to sync form:', batch[index], result.reason);
          }
        });
        
        dispatchSyncEvent('sync-progress', { 
          stage: 'forms', 
          completed: Math.min(i + SYNC_CONFIG.BATCH_SIZE, formSubmissions.length),
          total: formSubmissions.length 
        });
      }
    }
    
    console.log('Offline data sync complete', syncResults);
    isSyncing = false;
    
    dispatchSyncEvent('sync-completed', { results: syncResults });
    
    // Clean up old synced records
    await cleanupSyncedRecords();
    
    // If user is in offline mode, refresh the page to update state
    if (document.body.classList.contains('offline-mode') && navigator.onLine) {
      setTimeout(() => window.location.reload(), 1000);
    }
    
    return { success: true, results: syncResults };
    
  } catch (error) {
    console.error('Error during sync process:', error);
    isSyncing = false;
    dispatchSyncEvent('sync-failed', { error: error.message });
    return { success: false, error: error.message };
  }
}

// Sync individual attendance record with retry logic
async function syncAttendanceRecord(record, retryCount = 0) {
  try {
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), SYNC_CONFIG.TIMEOUT);
    
    const response = await fetch('api/sync-attendance.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        offlineData: [{
          student_id: record.student_id,
          action: record.action || 'scan',
          status: record.status,
          timestamp: record.date ? new Date(record.date).toISOString() : new Date(record.timestamp).toISOString(),
          location: record.location || 'Offline Sync',
          notes: record.notes || 'Synced from offline storage'
        }]
      }),
      signal: controller.signal
    });
    
    clearTimeout(timeoutId);
    
    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }
    
    const result = await response.json();
    
    if (result.success) {
      await markAsSynced(STORE_NAMES.ATTENDANCE, record.id);
      return { success: true, record };
    } else {
      throw new Error(result.message || 'Sync failed');
    }
    
  } catch (error) {
    console.error('Error syncing attendance record:', error);
    
    // Retry logic
    if (retryCount < SYNC_CONFIG.MAX_RETRIES) {
      await new Promise(resolve => setTimeout(resolve, SYNC_CONFIG.RETRY_DELAY));
      return syncAttendanceRecord(record, retryCount + 1);
    }
    
    // Update retry count in database
    await updateRetryCount(STORE_NAMES.ATTENDANCE, record.id, retryCount + 1);
    
    return { success: false, record, error: error.message };
  }
}

// Sync individual form submission
async function syncFormSubmission(form, retryCount = 0) {
  try {
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), SYNC_CONFIG.TIMEOUT);
    
    const response = await fetch(`api/sync-${form.form_type}.php`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(form.data),
      signal: controller.signal
    });
    
    clearTimeout(timeoutId);
    
    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }
    
    const result = await response.json();
    
    if (result.success) {
      await markAsSynced(STORE_NAMES.FORMS, form.id);
      return { success: true, form };
    } else {
      throw new Error(result.message || 'Sync failed');
    }
    
  } catch (error) {
    console.error('Error syncing form submission:', error);
    
    // Retry logic
    if (retryCount < SYNC_CONFIG.MAX_RETRIES) {
      await new Promise(resolve => setTimeout(resolve, SYNC_CONFIG.RETRY_DELAY));
      return syncFormSubmission(form, retryCount + 1);
    }
    
    // Update retry count in database
    await updateRetryCount(STORE_NAMES.FORMS, form.id, retryCount + 1);
    
    return { success: false, form, error: error.message };
  }
}

// Update retry count for a record
function updateRetryCount(storeName, id, retryCount) {
  return new Promise((resolve, reject) => {
    if (!db) {
      reject('Offline database not initialized');
      return;
    }
    
    const transaction = db.transaction([storeName], 'readwrite');
    const store = transaction.objectStore(storeName);
    
    const request = store.get(id);
    
    request.onsuccess = (event) => {
      const data = event.target.result;
      if (data) {
        data.retry_count = retryCount;
        data.last_retry = new Date().getTime();
        store.put(data);
        resolve(true);
      } else {
        reject('Data not found');
      }
    };
    
    request.onerror = (event) => {
      console.error('Error updating retry count:', event.target.error);
      reject(event.target.error);
    };
  });
}

// Clean up synced records older than 7 days
async function cleanupSyncedRecords() {
  const sevenDaysAgo = new Date().getTime() - (7 * 24 * 60 * 60 * 1000);
  
  for (const storeName of Object.values(STORE_NAMES)) {
    if (storeName === STORE_NAMES.SYNC_QUEUE) continue;
    
    try {
      const allRecords = await getAllRecords(storeName);
      const oldSyncedRecords = allRecords.filter(record => 
        record.synced && record.timestamp < sevenDaysAgo
      );
      
      if (oldSyncedRecords.length > 0) {
        console.log(`Cleaning up ${oldSyncedRecords.length} old records from ${storeName}`);
        
        for (const record of oldSyncedRecords) {
          await deleteRecord(storeName, record.id);
        }
      }
    } catch (error) {
      console.error(`Error cleaning up ${storeName}:`, error);
    }
  }
}

// Get all records from a store
function getAllRecords(storeName) {
  return new Promise((resolve, reject) => {
    if (!db) {
      reject('Offline database not initialized');
      return;
    }
    
    const transaction = db.transaction([storeName], 'readonly');
    const store = transaction.objectStore(storeName);
    const request = store.getAll();
    
    request.onsuccess = (event) => {
      resolve(event.target.result);
    };
    
    request.onerror = (event) => {
      console.error('Error getting all records:', event.target.error);
      reject(event.target.error);
    };
  });
}

// Delete a record
function deleteRecord(storeName, id) {
  return new Promise((resolve, reject) => {
    if (!db) {
      reject('Offline database not initialized');
      return;
    }
    
    const transaction = db.transaction([storeName], 'readwrite');
    const store = transaction.objectStore(storeName);
    const request = store.delete(id);
    
    request.onsuccess = () => resolve(true);
    request.onerror = (event) => {
      console.error('Error deleting record:', event.target.error);
      reject(event.target.error);
    };
  });
}

// Get count of pending items
async function getPendingSyncCount() {
  try {
    const counts = {};
    
    for (const [key, storeName] of Object.entries(STORE_NAMES)) {
      if (storeName === STORE_NAMES.SYNC_QUEUE) continue;
      
      const unsynced = await getUnsyncedData(storeName);
      counts[key.toLowerCase()] = unsynced.length;
    }
    
    return counts;
  } catch (error) {
    console.error('Error getting pending sync count:', error);
    return {};
  }
}

// Initialize the database when the page loads
document.addEventListener('DOMContentLoaded', () => {
  initOfflineDB()
    .then(() => {
      console.log('Offline database ready');
      // Update pending count on load
      updatePendingCount();
    })
    .catch(error => console.error('Failed to initialize offline storage:', error));
  
  // Listen for online events to trigger sync
  window.addEventListener('online', () => {
    console.log('Device is online, triggering sync...');
    syncOfflineData();
  });
  
  // Periodic sync check (every 5 minutes when online)
  setInterval(() => {
    if (navigator.onLine && !isSyncing) {
      getPendingSyncCount().then(counts => {
        const total = Object.values(counts).reduce((sum, count) => sum + count, 0);
        if (total > 0) {
          console.log(`Found ${total} pending items, triggering sync...`);
          syncOfflineData();
        }
      });
    }
  }, 5 * 60 * 1000); // 5 minutes
});

// Update pending count in UI
async function updatePendingCount() {
  try {
    const counts = await getPendingSyncCount();
    const total = Object.values(counts).reduce((sum, count) => sum + count, 0);
    
    dispatchSyncEvent('pending-count-updated', { counts, total });
    
    // Update badge if element exists
    const badge = document.getElementById('sync-pending-badge');
    if (badge) {
      badge.textContent = total;
      badge.style.display = total > 0 ? 'inline-block' : 'none';
    }
  } catch (error) {
    console.error('Error updating pending count:', error);
  }
}

// Export functions for use in other scripts
window.storeOfflineLogin = storeOfflineLogin;
window.storeOfflineFormSubmission = storeOfflineFormSubmission;
window.storeOfflineAttendance = storeOfflineAttendance;
window.syncOfflineData = syncOfflineData;
window.getPendingSyncCount = getPendingSyncCount;
window.updatePendingCount = updatePendingCount;
window.registerBackgroundSync = registerBackgroundSync; 