const CACHE_NAME = 'kes-smart-v1.0.7';
const VERSION = '1.0.7'; // Used for auto-updates - notifications disabled
const STATIC_CACHE = 'kes-smart-static-v1';
const DYNAMIC_CACHE = 'kes-smart-dynamic-v1';
const API_CACHE = 'kes-smart-api-v1';
const OFFLINE_FALLBACKS = 'kes-smart-offline-v1';
const AUTH_CACHE = 'kes-smart-auth-v1';

// Assets to cache immediately on service worker install
const urlsToCache = [
  // Ensure index.php is at the top of the list to prioritize it
  'index.php',
  './',  // Root URL
  'https://aphid-major-dolphin.ngrok-free.app/smart/',
  'https://aphid-major-dolphin.ngrok-free.app/smart/index.php',
  'https://aphid-major-dolphin.ngrok-free.app/smart/dashboard.php',
  'https://aphid-major-dolphin.ngrok-free.app/smart/login.php',
  'https://aphid-major-dolphin.ngrok-free.app/smart/offline-auth.php',
  'https://aphid-major-dolphin.ngrok-free.app/smart/qr-scanner.php',
  'https://aphid-major-dolphin.ngrok-free.app/smart/attendance.php',
  'https://aphid-major-dolphin.ngrok-free.app/smart/students.php',
  'https://aphid-major-dolphin.ngrok-free.app/smart/reports.php',
  'https://aphid-major-dolphin.ngrok-free.app/smart/profile.php',
  'https://aphid-major-dolphin.ngrok-free.app/smart/student-profile.php',
  'https://aphid-major-dolphin.ngrok-free.app/smart/sections.php',
  'https://aphid-major-dolphin.ngrok-free.app/smart/users.php',
  'https://aphid-major-dolphin.ngrok-free.app/smart/offline.html',
  'https://aphid-major-dolphin.ngrok-free.app/smart/assets/css/style.css',
  'https://aphid-major-dolphin.ngrok-free.app/smart/assets/css/pwa.css',
  'https://aphid-major-dolphin.ngrok-free.app/smart/assets/js/app.js',
  'https://aphid-major-dolphin.ngrok-free.app/smart/assets/js/sw-updater.js',
  'https://aphid-major-dolphin.ngrok-free.app/smart/assets/js/report-tables.js',
  'https://aphid-major-dolphin.ngrok-free.app/smart/assets/js/offline-forms.js',
  'https://aphid-major-dolphin.ngrok-free.app/smart/api/auth.php',
  'https://aphid-major-dolphin.ngrok-free.app/smart/api/offline-data.json',
  'https://img.icons8.com/color/72/000000/clipboard.png',
  'https://img.icons8.com/color/96/000000/clipboard.png',
  'https://img.icons8.com/color/128/000000/clipboard.png',
  'https://img.icons8.com/color/144/000000/clipboard.png',
  'https://img.icons8.com/color/152/000000/clipboard.png',
  'https://img.icons8.com/color/192/000000/clipboard.png',
  'https://img.icons8.com/color/384/000000/clipboard.png',
  'https://img.icons8.com/color/512/000000/clipboard.png',
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
  { url: /\.(?:php|html)$/, fallback: 'https://aphid-major-dolphin.ngrok-free.app/smart/offline.html' },
  { url: /\/api\//, fallback: 'https://aphid-major-dolphin.ngrok-free.app/smart/api/offline-data.json' }
];

// Define a list of resources that should never fallback to the offline page
// Index page and core assets should always try to load from cache first
const neverFallbackUrls = [
  /index\.php$/,
  /\/$/,
  /assets\/css\/pwa\.css$/,
  /assets\/js\/offline-forms\.js$/,
  /assets\/js\/sw-updater\.js$/
];

// Install Service Worker
self.addEventListener('install', (event) => {
  // Skip waiting forces the waiting service worker to become the active service worker
  self.skipWaiting();
  console.log('[SW] Installing new version:', VERSION);
  
  event.waitUntil(
    caches.open(STATIC_CACHE)
      .then((cache) => {
        console.log('Opened cache');
        
        // Try to cache all resources, but handle failures gracefully
        return cache.addAll(urlsToCache).catch(error => {
          console.error('Failed to cache all resources at once:', error);
          
          // Fall back to caching resources individually
          console.log('Attempting to cache resources individually...');
          const cachePromises = urlsToCache.map(url => {
            // Attempt to fetch and cache each URL individually
            return fetch(url, { mode: 'no-cors', cache: 'no-cache' })
              .then(response => {
                if (response.ok || response.type === 'opaque') {
                  return cache.put(url, response);
                }
                console.warn(`Failed to fetch resource: ${url}`);
                return Promise.resolve(); // Continue despite failure
              })
              .catch(err => {
                console.warn(`Failed to cache: ${url}`, err);
                return Promise.resolve(); // Continue despite failure
              });
          });
          
          // Wait for all individual caching attempts to complete
          return Promise.allSettled(cachePromises).then(() => {
            console.log('Completed caching resources individually');
          });
        });
      })
      .then(() => {
        // Create an empty offline API response cache
        return caches.open(OFFLINE_FALLBACKS).then(cache => {
          // Use catch to handle errors when adding offline.html
          return cache.add('https://aphid-major-dolphin.ngrok-free.app/smart/offline.html')
            .catch(error => {
              console.error('Failed to cache offline.html:', error);
              // Continue despite failure
              return Promise.resolve();
            });
        });
      })
      .then(() => {
        console.log('[SW] New version installed successfully:', VERSION);
        // Client notifications disabled - silent updates
        return Promise.resolve();
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
        
        // Automatically skip waiting and take control
        self.skipWaiting();
        
        // Client notifications disabled - silent updates
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

// Helper function to determine if a request is for authentication
function isAuthRequest(url) {
  const requestUrl = new URL(url, self.location.origin);
  return requestUrl.pathname.includes('/api/auth.php');
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

// Helper function to determine if a request is for the offline auth page
function isOfflineAuthPage(url) {
  return url.pathname.includes('offline-auth.php');
}

// Helper function to determine if a request is for the index page
function isIndexPage(url) {
  return url.pathname.endsWith('index.php') || 
         url.pathname === '/' || 
         url.pathname.endsWith('/');
}

// Helper function to check if a URL should never fall back to the offline page
function shouldNeverFallback(url) {
  return neverFallbackUrls.some(pattern => pattern.test(url.href));
}

// Fetch event with improved caching strategy
self.addEventListener('fetch', (event) => {
  // Check for updates on navigation requests (once per page load)
  if (event.request.mode === 'navigate') {
    event.waitUntil(checkForUpdates());
  }
  
  // Force update check on first request after activation
  if (event.request.mode === 'navigate' && !self.updateCheckPerformed) {
    self.updateCheckPerformed = true;
    event.waitUntil(
      checkForUpdates().then(() => {
        console.log('[SW] Initial update check completed');
      })
    );
  }
  
  const requestUrl = new URL(event.request.url);
  
  // Skip caching for some URLs
  if (
    requestUrl.pathname.includes('/api/check-version.php') ||
    requestUrl.pathname.includes('chrome-extension://') ||
    requestUrl.pathname.includes('localhost:')
  ) {
    return;
  }
  
  // Special handling for index.php (network first, with long-lived cache fallback)
  if (isIndexPage(requestUrl)) {
    event.respondWith(
      fetch(event.request)
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
        .catch(() => {
          // If network fails, try to get from cache
          return caches.match(event.request)
            .then(cachedResponse => {
              if (cachedResponse) {
                return cachedResponse;
              }
              
              // If no cached index found, try alternate URLs
              const alternateUrls = ['/', 'index.php', './index.php'];
              
              // Try each alternate URL in the cache
              const findInCache = async () => {
                for (const url of alternateUrls) {
                  const altResponse = await caches.match(url);
                  if (altResponse) return altResponse;
                }
                return null;
              };
              
              return findInCache().then(response => {
                if (response) return response;
                
                // If all fails, return a basic offline message for index
                return new Response(
                  `<!DOCTYPE html>
                  <html>
                  <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <title>KES-SMART - Offline</title>
                    <style>
                      body { font-family: sans-serif; text-align: center; padding: 20px; }
                      .message { margin-top: 20px; color: #555; }
                      .btn { background: #007bff; color: white; border: none; padding: 10px 20px; 
                             border-radius: 5px; cursor: pointer; margin-top: 20px; }
                    </style>
                  </head>
                  <body>
                    <h1>KES-SMART</h1>
                    <div class="message">
                      <p>You are currently offline and the home page is not cached.</p>
                      <p>Please check your connection and try again.</p>
                    </div>
                    <button class="btn" onclick="window.location.reload()">Retry</button>
                  </body>
                  </html>`,
                  {
                    headers: {
                      'Content-Type': 'text/html',
                      'Cache-Control': 'no-store'
                    }
                  }
                );
              });
            });
        })
    );
    return;
  }
  
  // Handle different types of requests with appropriate strategies
  if (isAuthRequest(event.request.url)) {
    // Special handling for auth API requests
    if (event.request.method === 'POST') {
      event.respondWith(
        fetch(event.request.clone())
          .then(response => {
            // Clone the response
            const responseToCache = response.clone();
            
            // Cache the successful response
            caches.open(AUTH_CACHE)
              .then(cache => {
                cache.put(event.request.url, responseToCache);
              });
            
            return response;
          })
          .catch(() => {
            // If network fails, try to get from cache
            return caches.match(event.request.url)
              .then(cachedResponse => {
                if (cachedResponse) {
                  return cachedResponse;
                }
                
                // If no cached response, return error for auth
                return new Response(JSON.stringify({
                  success: false,
                  offline: true,
                  message: 'You are offline. Please try again when connected.'
                }), {
                  headers: { 'Content-Type': 'application/json' }
                });
              });
          })
      );
    } else {
      // For GET requests to auth endpoints, try network first then cache
      event.respondWith(
        fetch(event.request)
          .then(response => {
            // Clone the response
            const responseToCache = response.clone();
            
            // Cache the successful response
            caches.open(AUTH_CACHE)
              .then(cache => {
                cache.put(event.request, responseToCache);
              });
            
            return response;
          })
          .catch(() => caches.match(event.request))
      );
    }
  } else if (isApiRequest(event.request.url)) {
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
              
              // If no cached response for API, find appropriate fallback
              for (const fallback of offlineFallbacks) {
                if (fallback.url.test(event.request.url)) {
                  return caches.match(fallback.fallback);
                }
              }
              
              // Default API fallback
              return caches.match('api/offline-data.json');
            });
        })
    );
  } else if (isStaticAsset(requestUrl)) {
    // Cache first for static assets
    event.respondWith(
      caches.match(event.request)
        .then(cachedResponse => {
          if (cachedResponse) {
            return cachedResponse;
          }
          
          // If not in cache, get from network
          return fetch(event.request)
            .then(response => {
              // Clone the response
              const responseToCache = response.clone();
              
              // Cache the successful response
              caches.open(DYNAMIC_CACHE)
                .then(cache => {
                  cache.put(event.request, responseToCache);
                });
              
              return response;
            });
        })
    );
  } else if (isHTMLPage(requestUrl)) {
    // Special handling for pages - network first, then cache, then fallback
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
              
              // Check if this URL should never fallback
              if (shouldNeverFallback(requestUrl)) {
                return new Response(
                  `<html><body>
                    <h1>Cannot load this page offline</h1>
                    <p>This page requires internet connection.</p>
                    <button onclick="window.location.reload()">Retry</button>
                  </body></html>`,
                  { headers: { 'Content-Type': 'text/html' } }
                );
              }
              
              // If no cached response, use appropriate fallback
              for (const fallback of offlineFallbacks) {
                if (fallback.url.test(event.request.url)) {
                  return caches.match(fallback.fallback);
                }
              }
              
              // Default fallback
              return caches.match('offline.html');
            });
        })
    );
  } else {
    // Default: try network first, then cache
    event.respondWith(
      fetch(event.request)
        .then(response => {
          // Don't cache non-successful responses
          if (!response.ok) {
            return response;
          }
          
          // Clone the response
          const responseToCache = response.clone();
          
          // Cache the successful response
          caches.open(DYNAMIC_CACHE)
            .then(cache => {
              cache.put(event.request, responseToCache);
            });
          
          return response;
        })
        .catch(() => caches.match(event.request))
    );
  }
});

// Helper function to extract auth params from URL
function getParamsFromURL(url) {
  try {
    const urlObj = new URL(url);
    const params = {};
    urlObj.searchParams.forEach((value, key) => {
      params[key] = value;
    });
    return params;
  } catch (e) {
    return {};
  }
}

// Activate event - clean up old caches
self.addEventListener('activate', (event) => {
  console.log('[SW] Activating new version:', VERSION);
  
  const expectedCacheNames = [
    STATIC_CACHE,
    DYNAMIC_CACHE,
    API_CACHE,
    OFFLINE_FALLBACKS,
    AUTH_CACHE
  ];

  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames.map((cacheName) => {
          if (!expectedCacheNames.includes(cacheName)) {
            console.log('Deleting out-of-date cache:', cacheName);
            return caches.delete(cacheName);
          }
        })
      );
    }).then(() => {
      console.log(`${CACHE_NAME} now ready to handle fetches!`);
      // Tell the active service worker to take control of the page immediately
      return self.clients.claim();
    }).then(() => {
      // Client notifications disabled - silent updates
      return Promise.resolve();
    })
  );
});

// Background sync events
self.addEventListener('sync', (event) => {
  if (event.tag === 'sync-attendance') {
    event.waitUntil(syncAttendanceData());
  }
  
  if (event.tag === 'sync-forms') {
    event.waitUntil(syncFormData());
  }
});

// Function to perform background sync for offline data
function doBackgroundSync() {
  return Promise.all([
    syncAttendanceData(),
    syncFormData()
  ]);
}

// Function to sync offline attendance records
function syncAttendanceData() {
  return getDataFromIndexedDB('attendance_records')
    .then(offlineRecords => {
      if (!offlineRecords || offlineRecords.length === 0) {
        return Promise.resolve('No offline attendance records to sync');
      }
      
      return Promise.all(offlineRecords.map(record => {
        return fetch('/smart/api/sync-attendance.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify(record)
        })
        .then(response => {
          if (!response.ok) {
            throw new Error('Failed to sync attendance record');
          }
          return response.json();
        })
        .then(data => {
          if (data.success) {
            // Remove successfully synced record from IndexedDB
            return removeItemsFromIndexedDB('attendance_records', [record.id]);
          }
          throw new Error('Failed to sync attendance record: ' + (data.message || 'Unknown error'));
        })
        .catch(error => {
          console.error('Error syncing attendance record:', error);
          return Promise.resolve(); // Continue with next record even if one fails
        });
      }));
    });
}

// Function to sync offline form submissions
function syncFormData() {
  return getDataFromIndexedDB('form_submissions')
    .then(offlineForms => {
      if (!offlineForms || offlineForms.length === 0) {
        return Promise.resolve('No offline forms to sync');
      }
      
      return Promise.all(offlineForms.map(form => {
        return fetch(`/smart/api/sync-${form.form_type}.php`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify(form.data)
        })
        .then(response => {
          if (!response.ok) {
            throw new Error(`Failed to sync ${form.form_type} form`);
          }
          return response.json();
        })
        .then(data => {
          if (data.success) {
            // Remove successfully synced form from IndexedDB
            return removeItemsFromIndexedDB('form_submissions', [form.id]);
          }
          throw new Error(`Failed to sync ${form.form_type} form: ` + (data.message || 'Unknown error'));
        })
        .catch(error => {
          console.error(`Error syncing ${form.form_type} form:`, error);
          return Promise.resolve(); // Continue with next form even if one fails
        });
      }));
    });
}

// Function to get data from IndexedDB
function getDataFromIndexedDB(storeName) {
  return new Promise((resolve, reject) => {
    const dbRequest = indexedDB.open('kes-smart-offline-data', 3);
    
    dbRequest.onerror = (event) => {
      reject('Could not open IndexedDB');
    };
    
    dbRequest.onsuccess = (event) => {
      const db = event.target.result;
      
      if (!db.objectStoreNames.contains(storeName)) {
        resolve([]);
        return;
      }
      
      const transaction = db.transaction(storeName, 'readonly');
      const store = transaction.objectStore(storeName);
      const request = store.getAll();
      
      request.onsuccess = (event) => {
        resolve(event.target.result);
      };
      
      request.onerror = (event) => {
        reject('Error fetching data from IndexedDB');
      };
    };
  });
}

// Function to clear data from IndexedDB
function clearDataFromIndexedDB(storeName) {
  return new Promise((resolve, reject) => {
    const dbRequest = indexedDB.open('kes-smart-offline-data', 3);
    
    dbRequest.onerror = (event) => {
      reject('Could not open IndexedDB');
    };
    
    dbRequest.onsuccess = (event) => {
      const db = event.target.result;
      
      if (!db.objectStoreNames.contains(storeName)) {
        resolve();
        return;
      }
      
      const transaction = db.transaction(storeName, 'readwrite');
      const store = transaction.objectStore(storeName);
      const request = store.clear();
      
      request.onsuccess = (event) => {
        resolve();
      };
      
      request.onerror = (event) => {
        reject('Error clearing data from IndexedDB');
      };
    };
  });
}

// Function to remove specific items from IndexedDB
function removeItemsFromIndexedDB(storeName, ids) {
  return new Promise((resolve, reject) => {
    const dbRequest = indexedDB.open('kes-smart-offline-data', 3);
    
    dbRequest.onerror = (event) => {
      reject('Could not open IndexedDB');
    };
    
    dbRequest.onsuccess = (event) => {
      const db = event.target.result;
      
      if (!db.objectStoreNames.contains(storeName)) {
        resolve();
        return;
      }
      
      const transaction = db.transaction(storeName, 'readwrite');
      const store = transaction.objectStore(storeName);
      
      let completed = 0;
      let errors = 0;
      
      for (const id of ids) {
        const request = store.delete(id);
        
        request.onsuccess = (event) => {
          completed++;
          if (completed + errors === ids.length) {
            resolve();
          }
        };
        
        request.onerror = (event) => {
          console.error('Error deleting item from IndexedDB:', id, event.target.error);
          errors++;
          if (completed + errors === ids.length) {
            resolve();
          }
        };
      }
      
      if (ids.length === 0) {
        resolve();
      }
    };
  });
}

// Listen for messages from clients
self.addEventListener('message', (event) => {
  if (event.data === 'skipWaiting') {
    self.skipWaiting();
  }
  
  // Handle auto-update requests
  if (event.data && event.data.type === 'CHECK_FOR_UPDATES') {
    event.waitUntil(checkForUpdates());
  }
  
  // Handle cache management commands
  if (event.data && event.data.type === 'CACHE_CLEANUP') {
    console.log('[SW] Manual cache cleanup requested');
    event.waitUntil(
      performAutomaticCacheCleanup()
        .then(result => {
          // Notify client of completion
          if (event.ports && event.ports[0]) {
            event.ports[0].postMessage({
              type: 'CACHE_CLEANUP_COMPLETE',
              result: result
            });
          }
        })
        .catch(error => {
          if (event.ports && event.ports[0]) {
            event.ports[0].postMessage({
              type: 'CACHE_CLEANUP_ERROR',
              error: error.message
            });
          }
        })
    );
  }
  
  if (event.data && event.data.type === 'CACHE_HEALTH_CHECK') {
    console.log('[SW] Cache health check requested');
    event.waitUntil(
      performCacheHealthCheck()
        .then(result => {
          if (event.ports && event.ports[0]) {
            event.ports[0].postMessage({
              type: 'CACHE_HEALTH_RESULT',
              result: result
            });
          }
        })
        .catch(error => {
          if (event.ports && event.ports[0]) {
            event.ports[0].postMessage({
              type: 'CACHE_HEALTH_ERROR',
              error: error.message
            });
          }
        })
    );
  }
});

// Cache management integration
let cacheCleanupPromise = null;

// Automatic cache cleanup check
async function performCacheHealthCheck() {
  try {
    // Check cache sizes
    let totalCacheSize = 0;
    for (const cacheName of [STATIC_CACHE, DYNAMIC_CACHE, API_CACHE, OFFLINE_FALLBACKS, AUTH_CACHE]) {
      try {
        const cache = await caches.open(cacheName);
        const keys = await cache.keys();
        
        for (const request of keys) {
          try {
            const response = await cache.match(request);
            if (response) {
              const blob = await response.blob();
              totalCacheSize += blob.size;
            }
          } catch (e) {
            // Remove corrupted cache entries
            await cache.delete(request);
          }
        }
      } catch (e) {
        console.warn('[SW] Error checking cache:', cacheName, e);
      }
    }
    
    // If cache is too large (>100MB), perform cleanup
    const MAX_CACHE_SIZE = 100 * 1024 * 1024; // 100MB
    if (totalCacheSize > MAX_CACHE_SIZE) {
      console.warn('[SW] Cache size exceeded limit, performing cleanup');
      await performAutomaticCacheCleanup();
    }
    
    return { totalSize: totalCacheSize, cleaned: totalCacheSize > MAX_CACHE_SIZE };
  } catch (error) {
    console.error('[SW] Cache health check failed:', error);
    return { error: error.message };
  }
}

// Automatic cache cleanup
async function performAutomaticCacheCleanup() {
  if (cacheCleanupPromise) {
    console.log('[SW] Cache cleanup already in progress');
    return cacheCleanupPromise;
  }
  
  cacheCleanupPromise = (async () => {
    try {
      console.log('[SW] Starting automatic cache cleanup');
      let totalCleaned = 0;
      
      // Clean old cache entries
      const maxAge = 7 * 24 * 60 * 60 * 1000; // 7 days
      const cutoffTime = Date.now() - maxAge;
      
      for (const cacheName of [DYNAMIC_CACHE, API_CACHE]) {
        try {
          const cache = await caches.open(cacheName);
          const requests = await cache.keys();
          
          for (const request of requests) {
            try {
              const response = await cache.match(request);
              if (response) {
                const dateHeader = response.headers.get('date');
                if (dateHeader && new Date(dateHeader).getTime() < cutoffTime) {
                  await cache.delete(request);
                  totalCleaned++;
                }
              }
            } catch (e) {
              // Remove corrupted entries
              await cache.delete(request);
              totalCleaned++;
            }
          }
        } catch (e) {
          console.warn('[SW] Error cleaning cache:', cacheName, e);
        }
      }
      
      // Clean IndexedDB if available
      try {
        await cleanOldIndexedDBRecords();
      } catch (e) {
        console.warn('[SW] Error cleaning IndexedDB:', e);
      }
      
      console.log(`[SW] Automatic cleanup completed, removed ${totalCleaned} entries`);
      return { cleaned: totalCleaned };
      
    } catch (error) {
      console.error('[SW] Automatic cache cleanup failed:', error);
      throw error;
    } finally {
      cacheCleanupPromise = null;
    }
  })();
  
  return cacheCleanupPromise;
}

// Clean old IndexedDB records
async function cleanOldIndexedDBRecords() {
  return new Promise((resolve) => {
    try {
      const request = indexedDB.open('kes-smart-offline-data', 1);
      
      request.onsuccess = async (event) => {
        const db = event.target.result;
        const storeNames = ['login_attempts', 'attendance_records', 'form_submissions', 'sync_queue'];
        let totalCleaned = 0;
        
        for (const storeName of storeNames) {
          if (db.objectStoreNames.contains(storeName)) {
            try {
              const cleaned = await cleanStoreOldRecords(db, storeName);
              totalCleaned += cleaned;
            } catch (e) {
              console.warn('[SW] Error cleaning store:', storeName, e);
            }
          }
        }
        
        db.close();
        console.log(`[SW] Cleaned ${totalCleaned} old IndexedDB records`);
        resolve(totalCleaned);
      };
      
      request.onerror = () => resolve(0);
    } catch (e) {
      resolve(0);
    }
  });
}

// Clean old records from a specific store
function cleanStoreOldRecords(db, storeName) {
  return new Promise((resolve) => {
    try {
      const maxAge = 30 * 24 * 60 * 60 * 1000; // 30 days
      const syncedMaxAge = 7 * 24 * 60 * 60 * 1000; // 7 days for synced
      const cutoffTime = Date.now() - maxAge;
      const syncedCutoffTime = Date.now() - syncedMaxAge;
      let cleaned = 0;
      
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
          
          if (shouldDelete) {
            cursor.delete();
            cleaned++;
          }
          
          cursor.continue();
        } else {
          resolve(cleaned);
        }
      };
      
      request.onerror = () => resolve(cleaned);
    } catch (e) {
      resolve(0);
    }
  });
}

// Periodic cache health check (every 30 minutes)
setInterval(() => {
  performCacheHealthCheck().catch(error => {
    console.error('[SW] Periodic cache health check failed:', error);
  });
}, 30 * 60 * 1000);

// Automatic update checking (every 5 minutes)
setInterval(() => {
  checkForUpdates().catch(error => {
    console.error('[SW] Periodic update check failed:', error);
  });
}, 5 * 60 * 1000);

// Initial cache health check after 5 minutes
setTimeout(() => {
  performCacheHealthCheck().catch(error => {
    console.error('[SW] Initial cache health check failed:', error);
  });
}, 5 * 60 * 1000);

// Initial update check after 2 minutes
setTimeout(() => {
  checkForUpdates().catch(error => {
    console.error('[SW] Initial update check failed:', error);
  });
}, 2 * 60 * 1000);
