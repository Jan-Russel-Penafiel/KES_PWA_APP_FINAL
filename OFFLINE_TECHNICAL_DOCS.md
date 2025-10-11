# KES-SMART PWA - Technical Offline Implementation Documentation

## 🏗️ Architecture Overview

```
┌─────────────────────────────────────────────────────────────┐
│                     USER INTERFACE                           │
│  (index.php, dashboard.php, qr-scanner.php, etc.)           │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────┐
│                  SERVICE WORKER (sw.js)                      │
│  ┌─────────────┐  ┌──────────────┐  ┌──────────────┐      │
│  │   Static    │  │   Dynamic    │  │     API      │       │
│  │   Cache     │  │   Cache      │  │    Cache     │       │
│  └─────────────┘  └──────────────┘  └──────────────┘       │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────┐
│              OFFLINE DATA LAYER                              │
│  ┌──────────────────────────────────────────────────┐      │
│  │            IndexedDB Storage                      │      │
│  │  ┌──────────────┐ ┌───────────────┐ ┌─────────┐ │      │
│  │  │Login Attempts│ │Attendance Recs│ │  Forms  │ │      │
│  │  └──────────────┘ └───────────────┘ └─────────┘ │      │
│  └──────────────────────────────────────────────────┘      │
│  ┌──────────────────────────────────────────────────┐      │
│  │            localStorage                           │      │
│  │  - User session data                              │      │
│  │  - Authentication tokens                          │      │
│  │  - App preferences                                │      │
│  └──────────────────────────────────────────────────┘      │
└─────────────────────────────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────┐
│              BACKGROUND SYNC                                 │
│  (Syncs data when connection restored)                      │
└─────────────────────────────────────────────────────────────┘
```

---

## 📦 File Structure

```
smart/
├── sw.js                          # Service Worker (main offline logic)
├── manifest.json                  # PWA manifest
├── offline.html                   # Offline fallback page
├── offline-auth.php              # Offline authentication handler
│
├── api/
│   ├── sync-attendance.php       # Sync attendance records
│   ├── check-version.php         # Version checking for updates
│   ├── offline-data.json         # Offline data fallback
│   └── auth.php                  # Authentication API
│
└── assets/
    └── js/
        ├── offline-forms.js      # IndexedDB management
        ├── sw-updater.js         # Service Worker updates
        └── cache-clear.js        # Cache management
```

---

## 🔧 Service Worker Implementation (sw.js)

### Cache Configuration

```javascript
const CACHE_NAME = 'kes-smart-v1.0.3';
const VERSION = '1.0.3';

// Cache types
const STATIC_CACHE = 'kes-smart-static-v1';    // Core app files
const DYNAMIC_CACHE = 'kes-smart-dynamic-v1';  // User-loaded content
const API_CACHE = 'kes-smart-api-v1';          // API responses
const OFFLINE_FALLBACKS = 'kes-smart-offline-v1'; // Fallback pages
const AUTH_CACHE = 'kes-smart-auth-v1';        // Auth data
```

### Caching Strategies

#### 1. **Cache-First Strategy** (Static Assets)
```javascript
// For CSS, JS, images, fonts
if (isStaticAsset(requestUrl)) {
    event.respondWith(
        caches.match(event.request)
            .then(cachedResponse => {
                if (cachedResponse) return cachedResponse;
                return fetch(event.request).then(response => {
                    const responseToCache = response.clone();
                    caches.open(DYNAMIC_CACHE)
                        .then(cache => cache.put(event.request, responseToCache));
                    return response;
                });
            })
    );
}
```

**Use Case**: Fast loading of static resources  
**Pros**: Fastest response time  
**Cons**: May serve stale content

#### 2. **Network-First Strategy** (API Requests)
```javascript
// For API calls
if (isApiRequest(event.request.url)) {
    event.respondWith(
        fetch(event.request)
            .then(response => {
                const responseToCache = response.clone();
                caches.open(API_CACHE)
                    .then(cache => cache.put(event.request, responseToCache));
                return response;
            })
            .catch(() => caches.match(event.request))
    );
}
```

**Use Case**: Fresh data when online, cached when offline  
**Pros**: Always fresh data when online  
**Cons**: Slower when network is slow

#### 3. **Stale-While-Revalidate** (Index Page)
```javascript
// For index.php
if (isIndexPage(requestUrl)) {
    event.respondWith(
        fetch(event.request)
            .then(response => {
                const responseToCache = response.clone();
                caches.open(STATIC_CACHE)
                    .then(cache => cache.put(event.request, responseToCache));
                return response;
            })
            .catch(() => caches.match(event.request))
    );
}
```

**Use Case**: Homepage always available  
**Pros**: Fast and fresh  
**Cons**: May show stale content briefly

---

## 💾 IndexedDB Implementation (offline-forms.js)

### Database Schema

```javascript
const DB_NAME = 'kes-smart-offline-data';
const DB_VERSION = 1;

// Object Stores
STORE_NAMES = {
    LOGIN: 'login_attempts',      // Login attempts when offline
    ATTENDANCE: 'attendance_records', // QR scan attendance
    FORMS: 'form_submissions'     // Form data
};
```

### Data Structure

#### Login Attempts
```javascript
{
    id: 1,                    // Auto-increment
    username: 'teacher1',
    role: 'teacher',
    timestamp: 1697025600000,
    synced: false
}
```

#### Attendance Records
```javascript
{
    id: 1,
    student_id: 123,
    status: 'present',
    date: '2025-10-11',
    timestamp: 1697025600000,
    synced: false
}
```

#### Form Submissions
```javascript
{
    id: 1,
    form_type: 'student',     // student, attendance, user, etc.
    data: { /* form fields */ },
    timestamp: 1697025600000,
    synced: false
}
```

### Key Functions

#### Store Data Offline
```javascript
// Store attendance when offline
async function storeOfflineAttendance(studentId, status, date) {
    const transaction = db.transaction(['attendance_records'], 'readwrite');
    const store = transaction.objectStore('attendance_records');
    
    const record = {
        student_id: studentId,
        status: status,
        date: date || new Date().toISOString().split('T')[0],
        timestamp: new Date().getTime(),
        synced: false
    };
    
    return store.add(record);
}
```

#### Sync Data When Online
```javascript
async function syncOfflineData() {
    if (!navigator.onLine) return;
    
    const attendanceRecords = await getUnsyncedData('attendance_records');
    
    for (const record of attendanceRecords) {
        const response = await fetch('api/sync-attendance.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(record)
        });
        
        if (response.ok) {
            await markAsSynced('attendance_records', record.id);
        }
    }
}
```

---

## 🔄 Background Sync

### Sync Events

```javascript
// In sw.js
self.addEventListener('sync', (event) => {
    if (event.tag === 'sync-attendance') {
        event.waitUntil(syncAttendanceData());
    }
    
    if (event.tag === 'sync-forms') {
        event.waitUntil(syncFormData());
    }
});
```

### Triggering Background Sync

```javascript
// Request background sync
if ('serviceWorker' in navigator && 'sync' in window.ServiceWorkerRegistration.prototype) {
    navigator.serviceWorker.ready.then(registration => {
        return registration.sync.register('sync-attendance');
    });
}
```

### Automatic Sync on Reconnection

```javascript
window.addEventListener('online', () => {
    console.log('Connection restored, syncing...');
    syncOfflineData();
});
```

---

## 🔐 Offline Authentication

### Session Storage (localStorage)

```javascript
// Store session when offline
const sessionData = {
    user_id: userData.id,
    username: userData.username,
    full_name: userData.full_name,
    role: userData.role,
    section_id: userData.section_id,
    offline_mode: true,
    offline_login: true
};

localStorage.setItem('kes_smart_session', JSON.stringify(sessionData));
```

### Authentication Flow

```
Online Login                     Offline Login
    │                                 │
    ├─ POST to api/auth.php          ├─ Check localStorage
    │                                 │
    ├─ Store in localStorage         ├─ Validate stored token
    │                                 │
    └─ Redirect to dashboard         └─ Redirect to dashboard
```

---

## 📱 PWA Manifest (manifest.json)

```json
{
    "name": "KES-SMART: Student Monitoring Application",
    "short_name": "KES-SMART",
    "start_url": "/smart/",
    "display": "standalone",      // Fullscreen app experience
    "theme_color": "#007bff",
    "background_color": "#ffffff",
    "orientation": "portrait",
    "icons": [ /* ... */ ],
    "shortcuts": [ /* ... */ ]
}
```

### Key Properties

- **`display: standalone`**: App opens without browser UI
- **`start_url`**: Entry point when app launches
- **`shortcuts`**: Quick actions on home screen
- **`icons`**: App icons for various sizes

---

## 🎯 Caching Priorities

### Priority 1: Critical Assets (Always Cached)
- index.php
- sw.js
- manifest.json
- Core CSS/JS files
- offline.html

### Priority 2: Important Pages (Cache on Visit)
- dashboard.php
- qr-scanner.php
- attendance.php
- login.php

### Priority 3: Dynamic Content (Cache on Demand)
- Student profiles
- Reports
- API responses
- User-uploaded images

### Never Cached
- check-version.php (always fresh)
- Large files (> 5MB)
- Real-time data endpoints

---

## 🛠️ Development Tools

### Testing Offline Mode

#### Chrome DevTools
1. Open DevTools (F12)
2. Go to **Application** tab
3. Select **Service Workers**
4. Check "Offline" checkbox

#### Network Throttling
1. Go to **Network** tab
2. Select throttling profile
3. Choose "Offline" or "Slow 3G"

### Debugging Service Workers

```javascript
// In sw.js - Add logging
console.log('[SW] Installing service worker...');
console.log('[SW] Fetch event:', event.request.url);
console.log('[SW] Cache hit:', cachedResponse ? 'YES' : 'NO');
```

### Clear Cache (For Testing)

```javascript
// Clear all caches
async function clearAllCaches() {
    const cacheNames = await caches.keys();
    await Promise.all(
        cacheNames.map(cacheName => caches.delete(cacheName))
    );
    console.log('All caches cleared');
}
```

---

## 📊 Performance Metrics

### Cache Storage Limits

| Browser | Storage Limit | Eviction Policy |
|---------|---------------|-----------------|
| Chrome | ~60% of disk space | LRU (Least Recently Used) |
| Firefox | ~50% of disk space | LRU |
| Safari | ~1GB | LRU |
| Edge | ~60% of disk space | LRU |

### IndexedDB Limits

| Browser | Storage Limit | Quota Management |
|---------|---------------|------------------|
| Chrome | ~60% disk space | Prompt when >50% |
| Firefox | Unlimited* | Prompt at 50MB |
| Safari | ~1GB | Prompt at 200MB |
| Edge | ~60% disk space | Prompt when >50% |

*Subject to available disk space

---

## 🔍 Troubleshooting

### Common Issues

#### 1. Service Worker Not Registering
```javascript
// Check registration
navigator.serviceWorker.getRegistration()
    .then(registration => {
        if (registration) {
            console.log('SW registered:', registration.scope);
        } else {
            console.log('No SW registration found');
        }
    });
```

#### 2. Cache Not Updating
```javascript
// Force update
navigator.serviceWorker.getRegistration()
    .then(registration => registration.update());

// Or skip waiting
self.addEventListener('install', event => {
    self.skipWaiting();
});
```

#### 3. IndexedDB Errors
```javascript
// Check if IndexedDB is available
if (!('indexedDB' in window)) {
    console.error('IndexedDB not supported');
}

// Handle quota exceeded
window.addEventListener('error', event => {
    if (event.message.includes('QuotaExceededError')) {
        console.error('Storage quota exceeded');
        // Clear old data
    }
});
```

---

## 🚀 Optimization Tips

### 1. Selective Caching
```javascript
// Don't cache everything
const urlsToCache = [
    'index.php',        // ✅ Cache
    'dashboard.php',    // ✅ Cache
    'api/large-data.php' // ❌ Don't cache
];
```

### 2. Cache Expiration
```javascript
// Add timestamp to cache
const CACHE_MAX_AGE = 24 * 60 * 60 * 1000; // 24 hours

async function getCachedResponse(request) {
    const cache = await caches.open(STATIC_CACHE);
    const response = await cache.match(request);
    
    if (response) {
        const cachedTime = response.headers.get('sw-cached-time');
        if (Date.now() - cachedTime > CACHE_MAX_AGE) {
            // Cache expired, fetch new
            return fetch(request);
        }
        return response;
    }
}
```

### 3. Compress Data
```javascript
// Compress before storing in IndexedDB
function compressData(data) {
    return LZString.compress(JSON.stringify(data));
}

function decompressData(compressed) {
    return JSON.parse(LZString.decompress(compressed));
}
```

---

## 📋 Best Practices

### ✅ Do's
- Cache critical resources first
- Implement proper error handling
- Use versioning for cache names
- Clean up old caches on activate
- Test offline functionality thoroughly
- Monitor storage usage
- Implement sync retry logic
- Show offline indicators to users

### ❌ Don'ts
- Don't cache everything
- Don't ignore cache size limits
- Don't forget to handle sync failures
- Don't cache sensitive data without encryption
- Don't block the main thread
- Don't ignore service worker updates
- Don't cache user-specific data globally

---

## 🔄 Update Strategy

### Version Management

```javascript
const VERSION = '1.0.3';

// Check for updates
async function checkForUpdates() {
    const response = await fetch('/smart/api/check-version.php', {
        cache: 'no-cache'
    });
    const data = await response.json();
    
    if (data.version !== VERSION) {
        console.log('New version available:', data.version);
        self.registration.update();
    }
}
```

### Force Update
```javascript
// Skip waiting and activate immediately
self.addEventListener('install', event => {
    self.skipWaiting();
});

self.addEventListener('activate', event => {
    event.waitUntil(clients.claim());
});
```

---

## 📞 API Endpoints

### Sync Endpoints

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/api/sync-attendance.php` | POST | Sync attendance records |
| `/api/sync-student.php` | POST | Sync student form data |
| `/api/sync-user.php` | POST | Sync user form data |
| `/api/check-version.php` | GET | Check for app updates |
| `/api/auth.php` | POST | Authentication & sync login |

---

## 📚 References

- [MDN Service Worker API](https://developer.mozilla.org/en-US/docs/Web/API/Service_Worker_API)
- [MDN IndexedDB API](https://developer.mozilla.org/en-US/docs/Web/API/IndexedDB_API)
- [Google PWA Documentation](https://web.dev/progressive-web-apps/)
- [Service Worker Cookbook](https://serviceworke.rs/)

---

**Last Updated**: October 11, 2025  
**Version**: 1.0.3  
**Author**: KES-SMART Development Team
