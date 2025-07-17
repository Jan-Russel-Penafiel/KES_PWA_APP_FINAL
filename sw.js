const CACHE_NAME = 'kes-smart-v1.0.2';
const VERSION = '1.0.2'; // Used for auto-updates
const STATIC_CACHE = 'kes-smart-static-v1';
const DYNAMIC_CACHE = 'kes-smart-dynamic-v1';
const API_CACHE = 'kes-smart-api-v1';
const OFFLINE_FALLBACKS = 'kes-smart-offline-v1';

// Assets to cache immediately on service worker install
const urlsToCache = [
  '/smart/',
  '/smart/index.php',
  '/smart/dashboard.php',
  '/smart/login.php',
  '/smart/qr-scanner.php',
  '/smart/attendance.php',
  '/smart/students.php',
  '/smart/reports.php',
  '/smart/profile.php',
  '/smart/offline.html',
  '/smart/assets/css/style.css',
  '/smart/assets/js/app.js',
  '/smart/assets/js/sw-updater.js',
  '/smart/assets/js/report-tables.js',
  '/smart/assets/icons/icon-72x72.png',
  '/smart/assets/icons/icon-96x96.png',
  '/smart/assets/icons/icon-128x128.png',
  '/smart/assets/icons/icon-144x144.png',
  '/smart/assets/icons/icon-152x152.png',
  '/smart/assets/icons/icon-192x192.png',
  '/smart/assets/icons/icon-384x384.png',
  '/smart/assets/icons/icon-512x512.png',
  'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css',
  'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js',
  'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css',
  'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/webfonts/fa-solid-900.woff2',
  'https://code.jquery.com/jquery-3.6.0.min.js',
  'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css',
  'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js',
  'https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css'
];

// Pages that should have offline fallbacks
const offlineFallbacks = [
  { url: /\.(?:php|html)$/, fallback: '/smart/offline.html' },
  { url: /\/api\//, fallback: '/smart/api/offline-data.json' }
];

// Install Service Worker
self.addEventListener('install', (event) => {
  // Skip waiting forces the waiting service worker to become the active service worker
  self.skipWaiting();
  event.waitUntil(
    caches.open(STATIC_CACHE)
      .then((cache) => {
        console.log('Opened cache');
        return cache.addAll(urlsToCache);
      })
      .then(() => {
        // Create an empty offline API response cache
        return caches.open(OFFLINE_FALLBACKS).then(cache => {
          return cache.add('/smart/offline.html');
        });
      })
  );
});

// Check for updates periodically
const checkForUpdates = async () => {
  try {
    const response = await fetch('/smart/api/check-version.php', {
      cache: 'no-cache'
    });
    
    if (response.ok) {
      const data = await response.json();
      // If the version from the server is different from our current version, register a new service worker
      if (data.version && data.version !== VERSION) {
        console.log(`New version available: ${data.version}, current: ${VERSION}`);
        // This will trigger the browser to download and install the new service worker
        self.registration.update();
      }
    }
  } catch (error) {
    console.error('Error checking for updates:', error);
  }
};

// Helper function to determine if a request is an API call
function isApiRequest(url) {
  const requestUrl = new URL(url, self.location.origin);
  return requestUrl.pathname.includes('/api/');
}

// Helper function to determine if a request is for a static asset
function isStaticAsset(url) {
  const fileExtensions = ['.css', '.js', '.jpg', '.jpeg', '.png', '.gif', '.svg', '.woff', '.woff2', '.ttf', '.eot'];
  return fileExtensions.some(ext => url.pathname.endsWith(ext));
}

// Helper function to determine if a request is for an HTML/PHP page
function isHTMLPage(url) {
  return url.pathname.endsWith('.php') || 
         url.pathname.endsWith('.html') || 
         url.pathname === '/' || 
         url.pathname.endsWith('/');
}

// Fetch event with improved caching strategy
self.addEventListener('fetch', (event) => {
  // Check for updates on navigation requests (once per page load)
  if (event.request.mode === 'navigate') {
    event.waitUntil(checkForUpdates());
  }
  
  const requestUrl = new URL(event.request.url);
  
  // Skip caching for some URLs
  if (
    requestUrl.pathname.startsWith('/smart/api/check-version.php') ||
    requestUrl.pathname.includes('chrome-extension://') ||
    requestUrl.pathname.includes('localhost:')
  ) {
    return;
  }
  
  // Handle different types of requests with appropriate strategies
  if (isApiRequest(event.request.url)) {
    // Network first, then cache for API requests
    event.respondWith(
      fetch(event.request)
        .then(response => {
          // Clone the response
          const responseToCache = response.clone();
          
          // Cache the successful response
          caches.open(API_CACHE)
            .then(cache => {
              cache.put(event.request, responseToCache);
            });
          
          return response;
        })
        .catch(() => {
          // If network fails, try to get from cache
          return caches.match(event.request)
            .then(cachedResponse => {
              if (cachedResponse) {
                return cachedResponse;
              }
              
              // If no cached response, return offline API data
              return caches.match('/smart/api/offline-data.json')
                .catch(() => {
                  // If no offline data, return empty JSON
                  return new Response(JSON.stringify({
                    offline: true,
                    message: 'You are currently offline.'
                  }), {
                    headers: { 'Content-Type': 'application/json' }
                  });
                });
            });
        })
    );
  } else if (isStaticAsset(requestUrl)) {
    // Cache first, then network for static assets
    event.respondWith(
      caches.match(event.request)
        .then(cachedResponse => {
          if (cachedResponse) {
            // Return cached response immediately
            return cachedResponse;
          }
          
          // If not in cache, fetch from network
          return fetch(event.request)
            .then(response => {
              // Clone the response
              const responseToCache = response.clone();
              
              // Cache the successful response
              caches.open(STATIC_CACHE)
                .then(cache => {
                  cache.put(event.request, responseToCache);
                });
              
              return response;
            })
            .catch(error => {
              console.error('Fetch failed for static asset:', error);
              // For failed image requests, could return a placeholder
              if (event.request.url.match(/\.(jpg|jpeg|png|gif|svg)$/)) {
                return caches.match('/smart/assets/icons/icon-72x72.png');
              }
              
              throw error;
            });
        })
    );
  } else if (isHTMLPage(requestUrl)) {
    // Network first, then cache for HTML pages
    event.respondWith(
      fetch(event.request)
        .then(response => {
          // Clone the response
          const responseToCache = response.clone();
          
          // Cache the successful response
          caches.open(DYNAMIC_CACHE)
            .then(cache => {
              cache.put(event.request, responseToCache);
            });
          
          return response;
        })
        .catch(() => {
          // If network fails, try to get from cache
          return caches.match(event.request)
            .then(cachedResponse => {
              if (cachedResponse) {
                return cachedResponse;
              }
              
              // If no cached response, return offline page
              return caches.match('/smart/offline.html');
            });
        })
    );
  } else {
    // Default strategy: try network, fallback to cache
    event.respondWith(
      fetch(event.request)
        .then(response => {
          // Cache the response if it's valid
          if (response.ok) {
            const responseToCache = response.clone();
            caches.open(DYNAMIC_CACHE)
              .then(cache => {
                cache.put(event.request, responseToCache);
              });
          }
          return response;
        })
        .catch(() => {
          return caches.match(event.request)
            .then(cachedResponse => {
              if (cachedResponse) {
                return cachedResponse;
              }
              
              // Find appropriate fallback
              for (const fallback of offlineFallbacks) {
                if (fallback.url.test(event.request.url)) {
                  return caches.match(fallback.fallback);
                }
              }
              
              // Default fallback
              if (event.request.destination === 'document') {
                return caches.match('/smart/offline.html');
              }
              
              // No fallback available
              return new Response('Network error occurred', {
                status: 408,
                headers: { 'Content-Type': 'text/plain' }
              });
            });
        })
    );
  }
});

// Activate Service Worker
self.addEventListener('activate', (event) => {
  const cacheWhitelist = [STATIC_CACHE, DYNAMIC_CACHE, API_CACHE, OFFLINE_FALLBACKS];
  
  // Take control of all clients as soon as it activates
  event.waitUntil(
    Promise.all([
      // Delete old cache versions
      caches.keys().then((cacheNames) => {
        return Promise.all(
          cacheNames.map((cacheName) => {
            if (cacheWhitelist.indexOf(cacheName) === -1) {
              console.log('Deleting old cache:', cacheName);
              return caches.delete(cacheName);
            }
          })
        );
      }),
      // Take control of uncontrolled clients
      self.clients.claim()
    ])
  );
});

// Background Sync for offline actions
self.addEventListener('sync', (event) => {
  if (event.tag === 'background-sync') {
    event.waitUntil(doBackgroundSync());
  } else if (event.tag === 'sync-attendance') {
    event.waitUntil(syncAttendanceData());
  } else if (event.tag === 'sync-forms') {
    event.waitUntil(syncFormData());
  }
});

function doBackgroundSync() {
  // Sync all pending data when back online
  return Promise.all([
    syncAttendanceData(),
    syncFormData()
  ]);
}

function syncAttendanceData() {
  // Get attendance data from IndexedDB and send to server
  return getDataFromIndexedDB('offline-attendance')
    .then(offlineData => {
      if (!offlineData || offlineData.length === 0) {
        return Promise.resolve();
      }
      
      return fetch('/smart/api/sync-attendance.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({ offlineData })
      })
      .then(response => {
        if (response.ok) {
          console.log('Attendance sync completed');
          // Clear synced data from IndexedDB
          return clearDataFromIndexedDB('offline-attendance');
        }
        throw new Error('Failed to sync attendance data');
      });
    })
    .catch(error => {
      console.error('Attendance sync failed:', error);
      throw error;
    });
}

function syncFormData() {
  // Get form submission data from IndexedDB and send to server
  return getDataFromIndexedDB('offline-forms')
    .then(offlineData => {
      if (!offlineData || offlineData.length === 0) {
        return Promise.resolve();
      }
      
      const syncPromises = offlineData.map(formData => {
        return fetch(formData.url, {
          method: formData.method,
          headers: formData.headers,
          body: formData.body
        })
        .then(response => {
          if (response.ok) {
            // Mark this item as synced
            return { id: formData.id, synced: true };
          }
          return { id: formData.id, synced: false };
        })
        .catch(() => {
          return { id: formData.id, synced: false };
        });
      });
      
      return Promise.all(syncPromises)
        .then(results => {
          // Remove successfully synced items
          const syncedIds = results
            .filter(result => result.synced)
            .map(result => result.id);
          
          if (syncedIds.length > 0) {
            return removeItemsFromIndexedDB('offline-forms', syncedIds);
          }
        });
    })
    .catch(error => {
      console.error('Form sync failed:', error);
      throw error;
    });
}

// Helper functions for IndexedDB operations
function getDataFromIndexedDB(storeName) {
  return new Promise((resolve, reject) => {
    const request = indexedDB.open('kes-smart-offline', 1);
    
    request.onerror = event => {
      reject('IndexedDB error: ' + event.target.errorCode);
    };
    
    request.onsuccess = event => {
      const db = event.target.result;
      
      if (!db.objectStoreNames.contains(storeName)) {
        resolve([]);
        return;
      }
      
      const transaction = db.transaction(storeName, 'readonly');
      const store = transaction.objectStore(storeName);
      const getAllRequest = store.getAll();
      
      getAllRequest.onsuccess = () => {
        resolve(getAllRequest.result);
      };
      
      getAllRequest.onerror = event => {
        reject('Error getting data: ' + event.target.errorCode);
      };
    };
    
    request.onupgradeneeded = event => {
      const db = event.target.result;
      
      // Create object stores if they don't exist
      if (!db.objectStoreNames.contains('offline-attendance')) {
        db.createObjectStore('offline-attendance', { keyPath: 'id', autoIncrement: true });
      }
      
      if (!db.objectStoreNames.contains('offline-forms')) {
        db.createObjectStore('offline-forms', { keyPath: 'id', autoIncrement: true });
      }
    };
  });
}

function clearDataFromIndexedDB(storeName) {
  return new Promise((resolve, reject) => {
    const request = indexedDB.open('kes-smart-offline', 1);
    
    request.onerror = event => {
      reject('IndexedDB error: ' + event.target.errorCode);
    };
    
    request.onsuccess = event => {
      const db = event.target.result;
      
      if (!db.objectStoreNames.contains(storeName)) {
        resolve();
        return;
      }
      
      const transaction = db.transaction(storeName, 'readwrite');
      const store = transaction.objectStore(storeName);
      const clearRequest = store.clear();
      
      clearRequest.onsuccess = () => {
        resolve();
      };
      
      clearRequest.onerror = event => {
        reject('Error clearing data: ' + event.target.errorCode);
      };
    };
  });
}

function removeItemsFromIndexedDB(storeName, ids) {
  return new Promise((resolve, reject) => {
    const request = indexedDB.open('kes-smart-offline', 1);
    
    request.onerror = event => {
      reject('IndexedDB error: ' + event.target.errorCode);
    };
    
    request.onsuccess = event => {
      const db = event.target.result;
      
      if (!db.objectStoreNames.contains(storeName)) {
        resolve();
        return;
      }
      
      const transaction = db.transaction(storeName, 'readwrite');
      const store = transaction.objectStore(storeName);
      
      let completed = 0;
      let errors = 0;
      
      ids.forEach(id => {
        const deleteRequest = store.delete(id);
        
        deleteRequest.onsuccess = () => {
          completed++;
          if (completed + errors === ids.length) {
            resolve();
          }
        };
        
        deleteRequest.onerror = () => {
          errors++;
          if (completed + errors === ids.length) {
            resolve();
          }
        };
      });
    };
  });
}

// Also check for updates every 4 hours
self.addEventListener('periodicsync', (event) => {
  if (event.tag === 'update-check') {
    event.waitUntil(checkForUpdates());
  }
});

// Set up a periodic check for updates (every 4 hours)
const FOUR_HOURS = 4 * 60 * 60 * 1000;
setInterval(checkForUpdates, FOUR_HOURS);

// Push notifications
self.addEventListener('push', (event) => {
  const options = {
    body: event.data ? event.data.text() : 'New notification from KES-SMART',
    icon: 'https://aphid-major-dolphin.ngrok-free.app/smart/assets/icons/icon-192x192.png',
    badge: 'https://aphid-major-dolphin.ngrok-free.app/smart/assets/icons/icon-72x72.png',
    vibrate: [200, 100, 200],
    data: {
      dateOfArrival: Date.now(),
      primaryKey: 1
    },
    actions: [
      {
        action: 'explore',
        title: 'View Details',
        icon: 'https://aphid-major-dolphin.ngrok-free.app/smart/assets/icons/icon-72x72.png'
      },
      {
        action: 'close',
        title: 'Close',
        icon: 'https://aphid-major-dolphin.ngrok-free.app/smart/assets/icons/icon-72x72.png'
      }
    ]
  };
  
  event.waitUntil(
    self.registration.showNotification('KES-SMART', options)
  );
});

// Notification click handler
self.addEventListener('notificationclick', (event) => {
  console.log('Notification click received.');
  
  event.notification.close();
  
  if (event.action === 'explore') {
    event.waitUntil(
      clients.openWindow('https://aphid-major-dolphin.ngrok-free.app/smart/dashboard.php')
    );
  } else if (event.action === 'close') {
    event.notification.close();
  } else {
    event.waitUntil(
      clients.openWindow('https://aphid-major-dolphin.ngrok-free.app/smart/')
    );
  }
});
