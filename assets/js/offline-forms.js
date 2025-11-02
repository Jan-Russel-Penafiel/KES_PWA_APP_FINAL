/**
 * KES-SMART Offline Forms Management
 * Handles storing and syncing form submissions when offline
 */

// IndexedDB configuration
const DB_NAME = 'kes-smart-offline-data';
const DB_VERSION = 1;
const STORE_NAMES = {
  LOGIN: 'login_attempts',
  ATTENDANCE: 'attendance_records',
  FORMS: 'form_submissions'
};

let db;

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
      }
      
      if (!db.objectStoreNames.contains(STORE_NAMES.ATTENDANCE)) {
        const attendanceStore = db.createObjectStore(STORE_NAMES.ATTENDANCE, { keyPath: 'id', autoIncrement: true });
        attendanceStore.createIndex('student_id', 'student_id', { unique: false });
        attendanceStore.createIndex('timestamp', 'timestamp', { unique: false });
      }
      
      if (!db.objectStoreNames.contains(STORE_NAMES.FORMS)) {
        const formsStore = db.createObjectStore(STORE_NAMES.FORMS, { keyPath: 'id', autoIncrement: true });
        formsStore.createIndex('form_type', 'form_type', { unique: false });
        formsStore.createIndex('timestamp', 'timestamp', { unique: false });
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
function storeOfflineAttendance(studentId, status, date) {
  return new Promise((resolve, reject) => {
    if (!db) {
      reject('Offline database not initialized');
      return;
    }
    
    const transaction = db.transaction([STORE_NAMES.ATTENDANCE], 'readwrite');
    const store = transaction.objectStore(STORE_NAMES.ATTENDANCE);
    
    const attendanceRecord = {
      student_id: studentId,
      status: status,
      date: date || new Date().toISOString().split('T')[0],
      timestamp: new Date().getTime(),
      synced: false
    };
    
    const request = store.add(attendanceRecord);
    
    request.onsuccess = () => resolve(true);
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

// Sync all offline data when back online
async function syncOfflineData() {
  if (!navigator.onLine) {
    console.log('Cannot sync while offline');
    return;
  }
  
  if (!db) {
    try {
      await initOfflineDB();
    } catch (error) {
      console.error('Failed to initialize database for sync:', error);
      return;
    }
  }
  
  // Sync login attempts
  try {
    const loginAttempts = await getUnsyncedData(STORE_NAMES.LOGIN);
    if (loginAttempts.length > 0) {
      console.log(`Syncing ${loginAttempts.length} login attempts`);
      
      for (const attempt of loginAttempts) {
        try {
          // Send login attempt to server
          const response = await fetch('api/auth.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
              action: 'sync_login',
              username: attempt.username,
              role: attempt.role,
              timestamp: attempt.timestamp
            })
          });
          
          if (response.ok) {
            await markAsSynced(STORE_NAMES.LOGIN, attempt.id);
          }
        } catch (error) {
          console.error('Error syncing login attempt:', error);
        }
      }
    }
    
    // Sync attendance records
    const attendanceRecords = await getUnsyncedData(STORE_NAMES.ATTENDANCE);
    if (attendanceRecords.length > 0) {
      console.log(`Syncing ${attendanceRecords.length} attendance records`);
      
      for (const record of attendanceRecords) {
        try {
          // Send attendance record to server
          const response = await fetch('api/sync-attendance.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
            },
            body: JSON.stringify({
              student_id: record.student_id,
              status: record.status,
              date: record.date,
              timestamp: record.timestamp
            })
          });
          
          if (response.ok) {
            await markAsSynced(STORE_NAMES.ATTENDANCE, record.id);
          }
        } catch (error) {
          console.error('Error syncing attendance record:', error);
        }
      }
    }
    
    // Sync form submissions
    const formSubmissions = await getUnsyncedData(STORE_NAMES.FORMS);
    if (formSubmissions.length > 0) {
      console.log(`Syncing ${formSubmissions.length} form submissions`);
      
      for (const submission of formSubmissions) {
        try {
          // Send form submission to server
          const response = await fetch(`api/sync-${submission.form_type}.php`, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
            },
            body: JSON.stringify(submission.data)
          });
          
          if (response.ok) {
            await markAsSynced(STORE_NAMES.FORMS, submission.id);
          }
        } catch (error) {
          console.error('Error syncing form submission:', error);
        }
      }
    }
    
    console.log('Offline data sync complete');
    
    // If user is in offline mode, refresh the page to update state
    if (document.body.classList.contains('offline-mode') && navigator.onLine) {
      window.location.reload();
    }
    
  } catch (error) {
    console.error('Error during sync process:', error);
  }
}

// Initialize the database when the page loads
document.addEventListener('DOMContentLoaded', () => {
  initOfflineDB().catch(error => console.error('Failed to initialize offline storage:', error));
  
  // Listen for online events to trigger sync
  window.addEventListener('online', syncOfflineData);
});

// Export functions for use in other scripts
window.storeOfflineLogin = storeOfflineLogin;
window.storeOfflineFormSubmission = storeOfflineFormSubmission;
window.storeOfflineAttendance = storeOfflineAttendance;
window.syncOfflineData = syncOfflineData; 