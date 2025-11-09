# Enhanced Offline Authentication - Quick Reference

## Overview
✅ Role-based offline authentication  
✅ No redirect looping  
✅ Automatic session sync  
✅ 7-day session validity  

## Key Components

### 1. offline-auth.js
Main authentication handler
```javascript
// Auto-loads on pages with data-check-offline-auth="true"
window.offlineAuth.checkSession()  // Check session
window.offlineAuth.clearSession()  // Clear session
window.offlineAuth.syncSession()   // Sync with server
```

### 2. Session Data Structure
```javascript
localStorage.getItem('kes_smart_session')
{
  user_id: number,
  username: string,
  full_name: string,
  role: 'admin'|'teacher'|'student'|'parent',
  section_id: number,
  offline_mode: true,
  timestamp: number  // For expiration check
}
```

### 3. Credential Cache (IndexedDB)
Database: `kes-smart-offline-auth`  
Store: `credentials`  
Key: `username`

## Usage

### Enable Offline Auth on Page
```php
// In PHP file (e.g., dashboard.php)
$check_offline_auth = !isLoggedIn();

// In header section
<script src="assets/js/offline-auth.js"></script>
<script>
document.body.dataset.checkOfflineAuth = '<?php echo $check_offline_auth ? 'true' : 'false'; ?>';
</script>
```

### Check Session Status
```javascript
const session = localStorage.getItem('kes_smart_session');
if (session) {
  const user = JSON.parse(session);
  console.log('User:', user.username, 'Role:', user.role);
}
```

### Manual Clear Session
```javascript
// Clear everything
window.offlineAuth.clearSession();

// Or manually:
localStorage.removeItem('kes_smart_session');
sessionStorage.clear();
```

## Redirect Loop Prevention

### How It Works
1. Tracks attempts in sessionStorage
2. Max 3 attempts before error
3. 2-second delay between redirects
4. Clear error message after 3 attempts

### Debug Redirect Issues
```javascript
// Check redirect attempts
console.log('Attempts:', sessionStorage.getItem('redirect_attempts'));

// Reset counter
sessionStorage.setItem('redirect_attempts', '0');
```

## Connection Status

### Listen for Changes
```javascript
window.addEventListener('online', () => {
  console.log('Back online!');
  // Session auto-syncs
});

window.addEventListener('offline', () => {
  console.log('Went offline');
  // Offline indicator shows
});
```

### Check Current Status
```javascript
if (navigator.onLine) {
  console.log('Currently online');
} else {
  console.log('Currently offline');
}
```

## Role-Based Access

### Check Role
```javascript
const session = JSON.parse(localStorage.getItem('kes_smart_session'));
if (session) {
  switch(session.role) {
    case 'admin':
      // Show admin features
      break;
    case 'teacher':
      // Show teacher features
      break;
    case 'student':
      // Show student features
      break;
    case 'parent':
      // Show parent features
      break;
  }
}
```

## Common Issues & Solutions

### Issue: Can't login offline
**Solution**: Must login online first to cache credentials
```javascript
// Check if credentials cached
indexedDB.open('kes-smart-offline-auth').onsuccess = (e) => {
  const db = e.target.result;
  console.log('Has credentials store:', 
    db.objectStoreNames.contains('credentials'));
};
```

### Issue: Session expired
**Solution**: Clear and re-login
```javascript
const session = JSON.parse(localStorage.getItem('kes_smart_session'));
const age = Date.now() - session.timestamp;
const maxAge = 7 * 24 * 60 * 60 * 1000; // 7 days
if (age > maxAge) {
  console.log('Session expired');
  window.offlineAuth.clearSession();
}
```

### Issue: Redirect loop
**Solution**: Check attempts and reset
```javascript
const attempts = sessionStorage.getItem('redirect_attempts');
console.log('Redirect attempts:', attempts);
if (parseInt(attempts) >= 3) {
  sessionStorage.setItem('redirect_attempts', '0');
  window.offlineAuth.clearSession();
}
```

## Testing Checklist

- [ ] Login online successfully
- [ ] Credentials cached in IndexedDB
- [ ] Session stored in localStorage
- [ ] Disconnect internet
- [ ] Logout
- [ ] Login offline with same credentials
- [ ] Dashboard loads with cached data
- [ ] Offline indicator shows
- [ ] Reconnect internet
- [ ] "Back Online" indicator shows
- [ ] Session syncs automatically
- [ ] No redirect loops
- [ ] Session expires after 7 days

## Files Modified

1. ✅ `assets/js/offline-auth.js` - New offline auth handler
2. ✅ `dashboard.php` - Enhanced session check
3. ✅ `login.php` - Improved credential caching
4. ✅ `offline-auth.php` - Updated session storage
5. ✅ `config.php` - Enhanced isLoggedIn()

## Browser Support

| Feature | Chrome | Firefox | Safari | Edge |
|---------|--------|---------|--------|------|
| IndexedDB | ✅ | ✅ | ✅ | ✅ |
| localStorage | ✅ | ✅ | ✅ | ✅ |
| Service Worker | ✅ | ✅ | ✅ | ✅ |
| Offline Detection | ✅ | ✅ | ✅ | ✅ |

## Performance Tips

1. **Minimize localStorage writes**
   - Update session only when needed
   - Use in-memory cache for frequent reads

2. **Lazy load cached data**
   - Load stats/data only when needed
   - Don't load all cached data on page load

3. **Optimize IndexedDB queries**
   - Use indexes for faster lookups
   - Batch operations when possible

## Security Notes

🔒 **Important**: 
- Never store passwords in localStorage
- Use HTTPS in production
- Validate roles on server-side
- Clear sessions on logout
- Set appropriate cookie expiration

## Quick Commands

```javascript
// Check if logged in
const isLoggedIn = !!localStorage.getItem('kes_smart_session');

// Get current user
const user = JSON.parse(localStorage.getItem('kes_smart_session') || '{}');

// Check role
const isAdmin = user.role === 'admin';
const isTeacher = user.role === 'teacher';

// Check if offline
const isOffline = user.offline_mode === true;

// Force sync
if (navigator.onLine && user.username) {
  window.offlineAuth.syncSession(user);
}

// Emergency clear
localStorage.clear();
sessionStorage.clear();
indexedDB.deleteDatabase('kes-smart-offline-auth');
```

## Support

🐛 **Report Issues**: Check browser console first  
📖 **Full Docs**: See `ENHANCED_OFFLINE_AUTH_GUIDE.md`  
🔍 **Debug**: Enable verbose logging in offline-auth.js  

---

**Version**: 1.1  
**Last Updated**: November 2025
