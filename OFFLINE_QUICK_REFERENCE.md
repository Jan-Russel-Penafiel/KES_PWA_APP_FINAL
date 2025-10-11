# 🚀 KES-SMART Offline Quick Reference

## ⚡ Quick Start

### For Users

**Install the App:**
1. Open in browser
2. Click "Install App" button
3. Find app icon on home screen
4. Launch and use like native app

**Use Offline:**
- QR scanning works offline ✅
- View cached pages ✅
- Submit forms (syncs later) ✅
- Check attendance history ✅

---

## 🔧 For Developers

### Service Worker Quick Commands

```javascript
// Register service worker
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('sw.js');
}

// Force update
navigator.serviceWorker.getRegistration()
    .then(reg => reg.update());

// Unregister (for testing)
navigator.serviceWorker.getRegistration()
    .then(reg => reg.unregister());
```

### IndexedDB Quick Access

```javascript
// Store data
await storeOfflineAttendance(studentId, 'present', '2025-10-11');

// Sync data
await syncOfflineData();

// Clear data
await clearDataFromIndexedDB('attendance_records');
```

### Cache Management

```javascript
// Clear all caches
caches.keys().then(names => {
    names.forEach(name => caches.delete(name));
});

// Check cache size
navigator.storage.estimate().then(estimate => {
    console.log(`Using ${estimate.usage} of ${estimate.quota} bytes`);
});
```

---

## 🎯 Common Tasks

### Add Page to Cache
```javascript
// In sw.js, add to urlsToCache array
const urlsToCache = [
    'index.php',
    'new-page.php',  // ← Add here
];
```

### Store Form Data Offline
```javascript
// In your form submission
if (!navigator.onLine) {
    await storeOfflineFormSubmission('student', formData);
    showMessage('Saved offline. Will sync when online.');
}
```

### Check Online Status
```javascript
if (navigator.onLine) {
    // Online: send to server
} else {
    // Offline: store locally
}

// Listen for changes
window.addEventListener('online', handleOnline);
window.addEventListener('offline', handleOffline);
```

---

## 🐛 Debugging

### Chrome DevTools
1. **F12** → **Application** tab
2. **Service Workers**: Check registration
3. **Cache Storage**: View cached files
4. **IndexedDB**: View stored data
5. **Network**: Test offline mode

### Console Commands
```javascript
// Check if SW is active
navigator.serviceWorker.controller

// Get all cached items
caches.open('kes-smart-static-v1')
    .then(cache => cache.keys())
    .then(keys => console.log(keys));

// Check storage usage
navigator.storage.estimate()
    .then(console.log);
```

---

## 📊 Storage Cheat Sheet

| Storage Type | Size Limit | Best For |
|--------------|------------|----------|
| Cache API | ~50-100MB | Static files, pages |
| IndexedDB | ~50MB+ | Structured data |
| localStorage | ~5-10MB | Simple key-value |
| sessionStorage | ~5-10MB | Temporary data |

---

## ⚠️ Common Issues

### Issue: SW Not Updating
**Fix:** 
```javascript
// Force update
self.skipWaiting();
clients.claim();
```

### Issue: Cache Too Large
**Fix:** Implement cache expiration
```javascript
const CACHE_MAX_ITEMS = 50;
const CACHE_MAX_AGE = 24 * 60 * 60 * 1000; // 24 hours
```

### Issue: Data Not Syncing
**Fix:** Check network and retry
```javascript
let retries = 0;
const MAX_RETRIES = 3;

async function syncWithRetry() {
    try {
        await syncOfflineData();
    } catch (error) {
        if (retries < MAX_RETRIES) {
            retries++;
            setTimeout(syncWithRetry, 1000 * retries);
        }
    }
}
```

---

## 🔐 Security Notes

- ✅ Always validate synced data on server
- ✅ Encrypt sensitive data before storing
- ✅ Use HTTPS for service workers
- ✅ Implement auth token expiration
- ❌ Don't cache authentication credentials
- ❌ Don't store sensitive data in localStorage

---

## 📱 Testing Checklist

- [ ] Test offline mode in Chrome DevTools
- [ ] Verify caching of critical pages
- [ ] Test QR scanning offline
- [ ] Verify data sync when back online
- [ ] Check cache size doesn't exceed limits
- [ ] Test on actual mobile device
- [ ] Verify PWA installation
- [ ] Test background sync
- [ ] Check offline indicators show correctly
- [ ] Verify form submissions queue properly

---

## 🚀 Performance Tips

**Cache Strategy Selection:**
- **Static assets** (CSS, JS): Cache-first
- **API calls**: Network-first
- **Pages**: Stale-while-revalidate
- **Images**: Cache-first with size limit

**Optimization:**
```javascript
// Lazy load non-critical resources
if ('requestIdleCallback' in window) {
    requestIdleCallback(() => {
        // Cache additional resources
    });
}
```

---

## 📞 Support Commands

```bash
# Check service worker registration
chrome://serviceworker-internals/

# View cache storage
chrome://cache/

# Check storage quota
chrome://settings/content/all
```

---

## 🎓 Learning Resources

- **MDN Web Docs**: [Service Workers](https://developer.mozilla.org/en-US/docs/Web/API/Service_Worker_API)
- **web.dev**: [Progressive Web Apps](https://web.dev/progressive-web-apps/)
- **Google Developers**: [Workbox](https://developers.google.com/web/tools/workbox)

---

**Version**: 1.0.3  
**Last Updated**: October 11, 2025
