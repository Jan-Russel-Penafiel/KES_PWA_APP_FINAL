# Enhanced Offline Authentication System

## Overview

The KES-SMART application now features a robust offline authentication system that supports role-based access without redirect looping issues. This system allows users to log in and access cached content even when the internet connection is unavailable.

## Key Features

### 1. **Role-Based Authentication**
- Supports all user roles: Admin, Teacher, Student, and Parent
- Each role has appropriate access to cached data and features
- Role verification happens both online and offline

### 2. **No Redirect Loops**
- Intelligent redirect prevention with attempt tracking
- Graceful error handling when authentication fails
- Clear user feedback for session issues

### 3. **Seamless Online/Offline Transition**
- Automatic detection of connection status
- Smooth synchronization when coming back online
- Visual indicators for connection state

### 4. **Session Management**
- 7-day offline session validity
- Automatic session expiration handling
- Secure credential caching with IndexedDB

## How It Works

### Login Flow

#### Online Login
1. User enters username and role on login page
2. System validates credentials against database
3. PHP session is created on server
4. User data is cached in IndexedDB for offline use
5. Session data is stored in localStorage with timestamp
6. User is redirected to dashboard

#### Offline Login
1. User enters username and role on login page
2. System detects no internet connection
3. Credentials are retrieved from IndexedDB
4. If credentials match, session is created in localStorage
5. User is redirected to offline-auth.php for processing
6. User is then redirected to dashboard with cached data

### Dashboard Access

#### Online Mode
- Standard PHP session check
- Live data from database
- All features available

#### Offline Mode
- JavaScript checks localStorage for session
- Displays cached user data and statistics
- Limited features (read-only mode)
- Shows offline indicator badge

### Session Synchronization

When connection is restored:
1. System detects online status
2. Verifies offline session with server
3. Updates PHP session if valid
4. Shows "Back Online" indicator
5. Gradually enables full features

## File Structure

### Core Files

1. **assets/js/offline-auth.js**
   - Main offline authentication handler
   - Session validation and management
   - Connection status monitoring
   - Redirect loop prevention

2. **dashboard.php**
   - Enhanced with offline session check
   - Conditional content display
   - Role-based UI updates

3. **login.php**
   - IndexedDB credential storage
   - Offline login detection
   - Credential caching on successful login

4. **offline-auth.php**
   - Processes offline login attempts
   - Sets localStorage session
   - Redirects to dashboard

5. **config.php**
   - Enhanced isLoggedIn() function
   - Offline mode detection
   - Cookie-based session check

## Implementation Details

### LocalStorage Structure

```javascript
{
  "kes_smart_session": {
    "user_id": 123,
    "username": "student01",
    "full_name": "John Doe",
    "role": "student",
    "section_id": 5,
    "offline_mode": true,
    "offline_login": true,
    "timestamp": 1699363200000
  }
}
```

### IndexedDB Structure

**Database**: `kes-smart-offline-auth`
**Store**: `credentials`

```javascript
{
  "username": "student01",
  "role": "student",
  "userData": {
    "id": 123,
    "username": "student01",
    "full_name": "John Doe",
    "role": "student",
    "section_id": 5,
    "cached_at": 1699363200000
  },
  "timestamp": 1699363200000
}
```

### Session Validation

Sessions are validated based on:
- Presence of required fields (user_id, username, role)
- Timestamp (max age: 7 days)
- Role-specific permissions

## Redirect Loop Prevention

### Mechanisms

1. **Attempt Counter**
   - Tracks redirect attempts in sessionStorage
   - Maximum 3 attempts before showing error
   - Resets after successful authentication

2. **Delayed Redirects**
   - 2-second delay before redirect
   - Allows user to read error messages
   - Prevents instant redirect loops

3. **Error State**
   - After 3 failed attempts, shows detailed error
   - Provides "Clear Session Data" button
   - Manual navigation to login page

### Code Example

```javascript
function redirectToLogin() {
    if (window.location.pathname.includes('login.php')) {
        return; // Already on login page
    }
    
    const redirectAttempts = parseInt(sessionStorage.getItem('redirect_attempts') || '0');
    
    if (redirectAttempts < 3) {
        sessionStorage.setItem('redirect_attempts', (redirectAttempts + 1).toString());
        
        // Show message and delay redirect
        setTimeout(() => {
            window.location.href = 'login.php';
        }, 2000);
    } else {
        // Show error with manual clear option
        showSessionError();
    }
}
```

## User Experience

### Visual Indicators

1. **Offline Mode Badge**
   ```html
   <span class="badge bg-warning text-dark">
       <i class="fas fa-wifi-slash me-1"></i>Offline Mode
   </span>
   ```

2. **Back Online Badge**
   ```html
   <span class="badge bg-success">
       <i class="fas fa-wifi me-1"></i>Back Online - Synced
   </span>
   ```

3. **Alert Notifications**
   - Yellow alert for offline mode
   - Green success when synced
   - Red error for failures

### Error Messages

1. **No Offline Session**
   - "No offline session found"
   - Redirects to login after 2 seconds

2. **Invalid Session Data**
   - "Invalid offline session data"
   - Provides clear session option

3. **Session Expired**
   - "Offline session expired"
   - Clears cached data automatically

4. **Too Many Redirects**
   - "Unable to verify your session"
   - Shows clear session button
   - Prevents further redirects

## Role-Specific Features

### Admin
- View cached statistics
- Limited user management
- Read-only reports

### Teacher
- View assigned sections
- Cached student lists
- Read-only attendance

### Student
- View attendance summary
- Personal QR code (cached)
- Read-only profile

### Parent
- View children's data
- Cached attendance reports
- Read-only access

## Testing Scenarios

### Test 1: Offline Login
1. Log in online successfully
2. Disconnect internet
3. Log out
4. Try logging in with same credentials
5. **Expected**: Successful offline login

### Test 2: Session Persistence
1. Log in offline
2. Navigate to dashboard
3. Refresh page
4. **Expected**: Remain logged in

### Test 3: Online Sync
1. Log in offline
2. Browse dashboard
3. Reconnect internet
4. **Expected**: See "Back Online" badge, session syncs

### Test 4: Redirect Prevention
1. Clear localStorage
2. Try accessing dashboard directly
3. **Expected**: Redirect to login once, no loops

### Test 5: Session Expiration
1. Log in offline
2. Wait 8 days (or modify timestamp)
3. Try accessing dashboard
4. **Expected**: Redirected to login, session cleared

## Troubleshooting

### Issue: Redirect Loop
**Solution**: Clear session data
```javascript
localStorage.removeItem('kes_smart_session');
sessionStorage.clear();
```

### Issue: Offline Login Fails
**Cause**: Credentials not cached
**Solution**: Log in online at least once to cache credentials

### Issue: Session Not Syncing
**Cause**: API endpoint not reachable
**Solution**: Check network connection and server status

### Issue: Role Mismatch
**Cause**: Stale cached data
**Solution**: Clear localStorage and re-login

## Best Practices

1. **Always Cache on First Login**
   - Log in online first to cache credentials
   - Ensures offline functionality works

2. **Clear Old Sessions**
   - Automatic cleanup after 7 days
   - Manual clear option available

3. **Monitor Connection Status**
   - Visual indicators always visible
   - Automatic detection and handling

4. **Graceful Degradation**
   - Full features online
   - Read-only features offline
   - Clear user communication

## API Reference

### JavaScript Functions

#### `checkOfflineSession()`
Validates and loads offline session from localStorage

**Returns**: void

**Side Effects**:
- Shows dashboard content if valid
- Redirects to login if invalid
- Updates UI with user data

#### `syncSessionWithServer(userData)`
Synchronizes offline session with server when online

**Parameters**:
- `userData` (Object): User session data

**Returns**: Promise<void>

#### `clearSessionData()`
Clears all session data (localStorage, sessionStorage, cookies)

**Returns**: void

**Side Effects**:
- Removes all cached data
- Redirects to login page

### PHP Functions

#### `isLoggedIn()`
Checks if user is logged in (online or offline)

**Returns**: boolean

**Checks**:
1. PHP session
2. Offline mode cookie
3. Client-side session indicator

## Security Considerations

1. **Data Encryption**
   - Sensitive data not stored in plain text
   - Use HTTPS for all connections

2. **Session Timeout**
   - 7-day maximum for offline sessions
   - Automatic cleanup of expired sessions

3. **Role Validation**
   - Server-side validation when online
   - Cached role checked offline

4. **XSS Prevention**
   - All user data sanitized
   - No eval() or innerHTML with user data

## Future Enhancements

1. **Biometric Authentication**
   - Fingerprint/Face ID for mobile
   - Enhanced security for offline mode

2. **Progressive Data Sync**
   - Sync attendance records when online
   - Queue offline actions for later

3. **Multi-Device Support**
   - Sync session across devices
   - Cloud-based credential storage

4. **Enhanced Caching**
   - More data cached for offline use
   - Smarter cache invalidation

## Support

For issues or questions:
- Check browser console for error messages
- Verify IndexedDB is enabled in browser
- Ensure cookies are enabled
- Try clearing session data as first step

## Changelog

### Version 1.1 (Current)
- Enhanced offline authentication
- Added redirect loop prevention
- Improved session validation
- Added connection status indicators
- Implemented automatic session sync

### Version 1.0
- Initial offline authentication
- Basic session management
- IndexedDB credential storage
