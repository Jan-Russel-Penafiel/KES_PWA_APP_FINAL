/**
 * KES-SMART Offline Form Handler
 * Manages form submissions when offline and syncs when back online
 */

// Initialize IndexedDB
function initOfflineDB() {
  return new Promise((resolve, reject) => {
    const request = indexedDB.open('kes-smart-offline', 1);
    
    request.onerror = event => {
      console.error('IndexedDB error:', event.target.errorCode);
      reject('Could not open offline database');
    };
    
    request.onsuccess = event => {
      const db = event.target.result;
      console.log('Offline database opened successfully');
      resolve(db);
    };
    
    request.onupgradeneeded = event => {
      const db = event.target.result;
      
      // Create object stores if they don't exist
      if (!db.objectStoreNames.contains('offline-attendance')) {
        db.createObjectStore('offline-attendance', { keyPath: 'id', autoIncrement: true });
        console.log('Created offline-attendance store');
      }
      
      if (!db.objectStoreNames.contains('offline-forms')) {
        db.createObjectStore('offline-forms', { keyPath: 'id', autoIncrement: true });
        console.log('Created offline-forms store');
      }
    };
  });
}

// Save form data to IndexedDB
function saveFormData(formData) {
  return new Promise((resolve, reject) => {
    initOfflineDB().then(db => {
      const transaction = db.transaction('offline-forms', 'readwrite');
      const store = transaction.objectStore('offline-forms');
      
      const request = store.add(formData);
      
      request.onsuccess = () => {
        console.log('Form data saved for offline use');
        resolve(true);
      };
      
      request.onerror = event => {
        console.error('Error saving form data:', event.target.error);
        reject(event.target.error);
      };
    }).catch(error => {
      console.error('Failed to initialize offline database:', error);
      reject(error);
    });
  });
}

// Save attendance data to IndexedDB
function saveAttendanceData(attendanceData) {
  return new Promise((resolve, reject) => {
    initOfflineDB().then(db => {
      const transaction = db.transaction('offline-attendance', 'readwrite');
      const store = transaction.objectStore('offline-attendance');
      
      const request = store.add(attendanceData);
      
      request.onsuccess = () => {
        console.log('Attendance data saved for offline use');
        resolve(true);
      };
      
      request.onerror = event => {
        console.error('Error saving attendance data:', event.target.error);
        reject(event.target.error);
      };
    }).catch(error => {
      console.error('Failed to initialize offline database:', error);
      reject(error);
    });
  });
}

// Register a sync event when back online
function registerSync(syncTag) {
  if ('serviceWorker' in navigator && 'SyncManager' in window) {
    navigator.serviceWorker.ready
      .then(registration => {
        return registration.sync.register(syncTag);
      })
      .then(() => {
        console.log(`${syncTag} sync registered`);
      })
      .catch(error => {
        console.error('Sync registration failed:', error);
      });
  }
}

// Handle form submissions
function handleFormSubmit(event) {
  // Only intercept POST forms
  if (event.target.method.toLowerCase() !== 'post') return;
  
  // Check if we're offline
  if (!navigator.onLine) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    const formObject = {};
    
    // Convert FormData to object
    formData.forEach((value, key) => {
      formObject[key] = value;
    });
    
    // Create a record of this form submission
    const formRecord = {
      url: form.action,
      method: form.method,
      headers: {
        'Content-Type': form.enctype || 'application/x-www-form-urlencoded'
      },
      body: JSON.stringify(formObject),
      timestamp: new Date().toISOString()
    };
    
    // Save to IndexedDB
    saveFormData(formRecord)
      .then(() => {
        // Register for sync when back online
        registerSync('sync-forms');
        
        // Show success message to user
        showOfflineMessage('Form saved for submission when back online', 'success');
        
        // If there's a success redirect in the form, simulate it
        const redirectInput = form.querySelector('input[name="success_redirect"]');
        if (redirectInput) {
          setTimeout(() => {
            window.location.href = redirectInput.value;
          }, 1500);
        }
      })
      .catch(error => {
        showOfflineMessage('Failed to save form data for offline use', 'danger');
        console.error('Error in offline form submission:', error);
      });
  }
}

// Handle QR attendance scanning when offline
function handleOfflineAttendance(studentId, action) {
  if (!navigator.onLine) {
    const attendanceData = {
      student_id: studentId,
      action: action,
      timestamp: new Date().toISOString(),
      device_info: {
        userAgent: navigator.userAgent,
        platform: navigator.platform
      }
    };
    
    saveAttendanceData(attendanceData)
      .then(() => {
        registerSync('sync-attendance');
        showOfflineMessage('Attendance recorded and will sync when online', 'success');
      })
      .catch(error => {
        showOfflineMessage('Failed to save attendance data', 'danger');
        console.error('Error saving offline attendance:', error);
      });
    
    return true; // Indicate we handled it offline
  }
  
  return false; // Not handled offline
}

// Show offline message to user
function showOfflineMessage(message, type = 'info') {
  // Create message element
  const messageDiv = document.createElement('div');
  messageDiv.className = `alert alert-${type} offline-alert`;
  messageDiv.style.position = 'fixed';
  messageDiv.style.bottom = '20px';
  messageDiv.style.right = '20px';
  messageDiv.style.maxWidth = '300px';
  messageDiv.style.zIndex = '9999';
  messageDiv.style.boxShadow = '0 4px 8px rgba(0,0,0,0.1)';
  messageDiv.style.borderRadius = '8px';
  
  // Add offline icon
  if (!navigator.onLine) {
    messageDiv.innerHTML = `<i class="fas fa-wifi-slash me-2"></i>${message}`;
  } else {
    messageDiv.innerHTML = `<i class="fas fa-check-circle me-2"></i>${message}`;
  }
  
  // Add to body
  document.body.appendChild(messageDiv);
  
  // Remove after 5 seconds
  setTimeout(() => {
    if (document.body.contains(messageDiv)) {
      messageDiv.style.opacity = '0';
      messageDiv.style.transition = 'opacity 0.5s ease-out';
      
      setTimeout(() => {
        if (document.body.contains(messageDiv)) {
          document.body.removeChild(messageDiv);
        }
      }, 500);
    }
  }, 5000);
}

// Update UI based on online/offline status
function updateOnlineStatus() {
  const isOnline = navigator.onLine;
  
  // Add/remove offline class to body
  if (isOnline) {
    document.body.classList.remove('offline-mode');
    
    // Try to sync data if we just came back online
    if ('serviceWorker' in navigator && 'SyncManager' in window) {
      navigator.serviceWorker.ready.then(registration => {
        registration.sync.register('background-sync');
      });
    }
  } else {
    document.body.classList.add('offline-mode');
    showOfflineMessage('You are currently offline. Some features may be limited.', 'warning');
  }
  
  // Find and update any offline status indicators
  const offlineIndicators = document.querySelectorAll('.offline-indicator');
  offlineIndicators.forEach(indicator => {
    if (isOnline) {
      indicator.classList.remove('active');
      indicator.title = 'Online';
    } else {
      indicator.classList.add('active');
      indicator.title = 'Offline - Limited functionality';
    }
  });
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
  // Add offline indicator to the page if it doesn't exist
  if (!document.querySelector('.offline-indicator')) {
    const indicator = document.createElement('div');
    indicator.className = 'offline-indicator';
    indicator.innerHTML = '<i class="fas fa-wifi-slash"></i>';
    indicator.style.position = 'fixed';
    indicator.style.top = '10px';
    indicator.style.right = '10px';
    indicator.style.backgroundColor = 'rgba(0,0,0,0.7)';
    indicator.style.color = 'white';
    indicator.style.padding = '5px 10px';
    indicator.style.borderRadius = '20px';
    indicator.style.fontSize = '12px';
    indicator.style.zIndex = '9999';
    indicator.style.display = 'none';
    
    document.body.appendChild(indicator);
    
    // Only show when offline
    if (!navigator.onLine) {
      indicator.style.display = 'block';
    }
  }
  
  // Initialize online/offline status
  updateOnlineStatus();
  
  // Listen for online/offline events
  window.addEventListener('online', updateOnlineStatus);
  window.addEventListener('offline', updateOnlineStatus);
  
  // Intercept form submissions
  document.addEventListener('submit', handleFormSubmit);
  
  // Initialize IndexedDB
  initOfflineDB().catch(error => {
    console.error('Failed to initialize offline database:', error);
  });
});

// Export functions for use in other scripts
window.offlineUtils = {
  handleOfflineAttendance,
  showOfflineMessage,
  saveFormData,
  saveAttendanceData
}; 