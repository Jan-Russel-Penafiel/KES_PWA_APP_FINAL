# KES-SMART PWA Offline Operation Guide

## 🌐 Overview
KES-SMART is a Progressive Web App (PWA) with comprehensive offline capabilities, allowing users to continue working even without internet connectivity.

---

## 📱 Current Offline Features

### 1. **Service Worker Caching**
The app uses a sophisticated multi-layer caching strategy:

- **Static Cache**: Core files (HTML, CSS, JavaScript, icons)
- **Dynamic Cache**: User-loaded content and pages
- **API Cache**: Server responses for faster loading
- **Auth Cache**: Authentication data for offline login
- **Offline Fallbacks**: Special offline pages

### 2. **IndexedDB Storage**
Local database stores offline data in three categories:
- **Login Attempts**: Authentication data when offline
- **Attendance Records**: QR code scans saved for later sync
- **Form Submissions**: Any form data submitted while offline

### 3. **Offline Pages**
- `offline.html` - User-friendly offline notification page
- `offline-auth.php` - Handles authentication without server connection

### 4. **Background Synchronization**
Automatically syncs data when connection is restored:
- Attendance records → `api/sync-attendance.php`
- Form submissions → `api/sync-{form_type}.php`
- Login attempts → `api/auth.php`

---

## 🔄 How Offline Mode Works

### When Going Offline:

1. **Detection**: Service worker intercepts failed network requests
2. **Cache Serving**: Delivers cached versions of pages and assets
3. **Data Storage**: Saves new data to IndexedDB
4. **UI Notification**: Shows offline indicators to user

### When Back Online:

1. **Event Triggered**: Browser fires 'online' event
2. **Sync Process**: Background sync sends stored data to server
3. **Cache Update**: Fresh content downloaded and cached
4. **UI Refresh**: Offline indicators removed

---

## ✅ Features Available Offline

| Feature | Status | Notes |
|---------|--------|-------|
| View Dashboard | ✅ Working | Previously cached |
| QR Scanner | ✅ Working | Scans saved to IndexedDB |
| View Attendance | ✅ Working | Cached records shown |
| View Students | ✅ Working | Previously loaded data |
| View Reports | ✅ Working | Cached data displayed |
| Submit Forms | ✅ Working | Queued for sync |
| Profile View | ✅ Working | Cached profile data |
| Authentication | ✅ Working | Uses localStorage |

---

## 🚀 Best Practices for Users

### For Teachers:
1. **Pre-load Data**: Open pages while online to cache them
2. **Scan Offline**: QR scans work offline and sync automatically
3. **Check Sync Status**: Green badge indicates offline operations pending sync

### For Students:
1. **View QR Code**: Your QR code is always available offline
2. **Check Attendance**: Previous attendance records remain accessible
3. **Stay Updated**: App auto-syncs when connection returns

### For Admins:
1. **Monitor Sync**: Check logs for sync status
2. **Data Validation**: Verify offline submissions after sync
3. **Cache Management**: Clear cache if experiencing issues

---

## 🛠️ Technical Implementation

### Service Worker Registration
```javascript
// In header.php or footer.php
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('sw.js')
        .then(registration => {
            console.log('Service Worker registered');
        })
        .catch(error => {
            console.error('Service Worker registration failed:', error);
        });
}
```

### Offline Data Storage
```javascript
// Store attendance record offline
storeOfflineAttendance(studentId, status, date)
    .then(() => console.log('Saved offline'))
    .catch(error => console.error('Error:', error));
```

### Sync When Online
```javascript
// Automatic sync on reconnection
window.addEventListener('online', () => {
    syncOfflineData();
});
```

---

## 🔧 Troubleshooting

### Issue: App not working offline
**Solution**: 
1. Visit all pages while online first
2. Check if service worker is registered (DevTools → Application → Service Workers)
3. Clear cache and re-cache: Settings → Clear Cache

### Issue: Data not syncing
**Solution**:
1. Check network connection
2. Open DevTools → Console for sync errors
3. Manually trigger sync: Click "Sync Now" button

### Issue: Old content showing
**Solution**:
1. Force reload: Ctrl+Shift+R (Windows) or Cmd+Shift+R (Mac)
2. Clear cache: Settings → Clear Cache
3. Update service worker: Will happen automatically

---

## 📊 Offline Data Limits

| Storage Type | Capacity | Persistence |
|--------------|----------|-------------|
| Cache Storage | ~50MB - 100MB | Until cleared or updated |
| IndexedDB | ~50MB - unlimited* | Persistent across sessions |
| localStorage | ~5MB - 10MB | Permanent until cleared |

*Depends on device storage and browser

---

## 🔐 Security Considerations

1. **Offline Authentication**: Uses encrypted tokens in localStorage
2. **Data Encryption**: Sensitive data encrypted before storage
3. **Sync Validation**: Server validates all synced data
4. **Session Management**: Offline sessions have limited duration

---

## 📱 Installation Instructions

### Android/Chrome:
1. Open app in Chrome
2. Tap menu (⋮) → "Install app" or "Add to Home Screen"
3. Confirm installation
4. App icon appears on home screen

### iOS/Safari:
1. Open app in Safari
2. Tap Share button (□↑)
3. Scroll down and tap "Add to Home Screen"
4. Confirm and app is installed

### Desktop:
1. Open app in Chrome/Edge
2. Click install icon in address bar (💾)
3. Click "Install" in prompt
4. App opens in standalone window

---

## 🎯 Performance Tips

### Optimize Offline Experience:
1. **Pre-cache Important Pages**: Visit frequently used pages while online
2. **Limit Large Files**: Photos and documents may not cache automatically
3. **Regular Sync**: Connect to internet regularly to sync pending data
4. **Update App**: Keep app updated for latest offline features

### Battery Optimization:
- Disable background sync if not needed
- Reduce QR scanning frequency when offline
- Close app when not in use

---

## 📞 Support

If you experience issues with offline functionality:
1. Check this guide for troubleshooting steps
2. Clear cache and restart app
3. Contact system administrator
4. Check for app updates

---

## 🔄 Version History

**v1.0.3** (Current)
- Multi-layer caching strategy
- IndexedDB for offline data
- Background sync on reconnection
- Offline authentication support
- Enhanced offline UI/UX

---

## 📝 Notes

- Service worker updates automatically when new version is detected
- Offline data is persistent until synced successfully
- Network-dependent features (SMS, real-time notifications) require connection
- App works best when installed as PWA

---

**Last Updated**: October 11, 2025  
**App Version**: 1.0.3
