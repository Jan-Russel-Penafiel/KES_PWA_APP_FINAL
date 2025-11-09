# Offline QR Scanner Enhancement Guide

## Overview
This guide explains the enhanced offline functionality for the QR scanner system in KES-SMART. The system now properly handles QR scans, manual LRN entry, and manual student selection when offline, with automatic synchronization when the connection is restored.

## Features Implemented

### 1. Enhanced Offline Storage
- **IndexedDB Integration**: All offline scans are stored in IndexedDB with complete metadata
- **Multiple Scan Types**: Supports QR codes, manual LRN entry, and manual student selection
- **Timestamp Tracking**: Each scan includes timestamp for proper ordering and validation
- **Sync Status**: Tracks which records have been synced to the server

### 2. Automatic Synchronization
- **Connection Detection**: Automatically detects when device comes back online
- **Batch Sync**: Syncs all pending records in order
- **Error Handling**: Properly handles sync failures with retry capability
- **Progress Tracking**: Shows sync progress with success/failure counts

### 3. User Interface Enhancements
- **Offline Indicator**: Clear visual indicator when in offline mode
- **Pending Sync Badge**: Shows count of pending syncs with click-to-view details
- **Sync Status**: Real-time feedback on sync progress
- **Manual Sync Button**: Option to manually trigger sync when online

## Files Modified

### 1. assets/js/offline-qr-scanner.js (NEW)
**Purpose**: Main offline QR scanner enhancement module

**Key Functions**:
- `storeOfflineQrAttendance(scanType, scanData)` - Stores offline scans in IndexedDB
- `handleEnhancedOfflineQrScan(qrData, scanLocation, scanNotes)` - Handles QR scans offline
- `handleEnhancedOfflineLrnScan(lrn, scanLocation, scanNotes)` - Handles manual LRN entry offline
- `handleEnhancedOfflineManualSelection(studentId, scanLocation, scanNotes)` - Handles manual student selection offline
- `syncQrAttendanceRecords()` - Syncs all pending attendance records
- `updatePendingSyncCount()` - Updates UI badge with pending sync count
- `showPendingSyncs()` - Shows modal with pending sync details

### 2. qr-scanner.php
**Purpose**: QR scanner page with offline support

**Changes Made**:
1. Added `scan_manual` action handler for manual student selection
2. Added teacher data attributes to body element for offline JS access
3. Included `offline-qr-scanner.js` script
4. Updated offline handlers to use enhanced functions
5. Simplified LRN form offline handling

**New Action Handler** (Lines 109-150):
```php
// Handle manual student selection submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'scan_manual') {
    $student_id = intval($_POST['student_id'] ?? 0);
    $subject_id = intval($_POST['subject_id'] ?? 0);
    $scan_location = sanitize_input($_POST['scan_location'] ?? 'Main Gate');
    $scan_notes = sanitize_input($_POST['scan_notes'] ?? '');
    
    // Validation and processing...
    $result = processStudentAttendance($pdo, $student, $current_user, $user_role, $subject_id, $scan_location, $scan_notes, false);
    
    header('Content-Type: application/json');
    echo json_encode($result);
    exit;
}
```

## Data Structure

### Offline Attendance Record
```javascript
{
    scan_type: 'qr' | 'lrn' | 'manual',
    scan_data: {
        // For QR scans
        qr_data: string,
        
        // For LRN scans
        lrn: string,
        
        // For manual selection
        student_id: number,
        student_name: string,
        
        // Common fields
        subject_id: number,
        subject_name: string,
        scan_location: string,
        scan_notes: string,
        teacher_id: number,
        teacher_name: string,
        scan_method: string,
        timestamp: number
    },
    timestamp: number,
    date: string,
    synced: false,
    sync_attempts: 0
}
```

## Usage Examples

### 1. QR Code Scan (Offline)
When a teacher scans a QR code while offline:

```javascript
// Automatically handled by the scanner
handleEnhancedOfflineQrScan(qrCodeData, 'Main Gate', 'Regular scan');

// Result:
// - Stored in IndexedDB
// - Shows warning message with pending status
// - Updates pending sync count
// - Will auto-sync when connection restored
```

### 2. Manual LRN Entry (Offline)
When a teacher enters an LRN manually while offline:

```javascript
// Called from LRN form submission
handleEnhancedOfflineLrnScan('123456789012', 'Main Gate', '');

// Result:
// - Validates LRN format (12 digits)
// - Stores in IndexedDB
// - Shows success toast
// - Clears form
// - Updates pending sync count
```

### 3. Manual Student Selection (Offline)
When a teacher selects a student from dropdown while offline:

```javascript
// Called from student select form submission
handleEnhancedOfflineManualSelection(studentId, 'Main Gate', '');

// Result:
// - Gets student data from select option
// - Stores in IndexedDB with full details
// - Shows success toast
// - Clears selection
// - Updates pending sync count
```

### 4. View Pending Syncs
Click on the pending sync badge to view details:

```javascript
showPendingSyncs();

// Shows modal with:
// - List of all pending scans
// - Scan type (QR/LRN/Manual)
// - Student info
// - Subject
// - Timestamp
// - Sync Now button (if online)
```

### 5. Manual Sync
Trigger sync manually when online:

```javascript
syncNow();

// Actions:
// 1. Checks connection
// 2. Gets all unsynced records
// 3. Sends each to server
// 4. Marks successful syncs
// 5. Shows results toast
// 6. Updates pending count
```

## Sync Process Flow

### When Device Comes Back Online

1. **Connection Detected**
   ```javascript
   window.addEventListener('online', updateQrScannerOfflineStatus);
   ```

2. **Auto-Sync Triggered**
   ```javascript
   syncQrAttendanceRecords()
   ```

3. **For Each Pending Record**:
   - Create FormData with appropriate action (scan_qr/scan_lrn/scan_manual)
   - Add all scan data fields
   - Add offline_timestamp and offline_sync flags
   - POST to qr-scanner.php
   - Check response
   - Mark as synced if successful
   - Track errors if failed

4. **Show Results**:
   - Success count toast (green)
   - Failed count toast (yellow)
   - Update pending sync count
   - Console log full details

### Periodic Auto-Sync

```javascript
// Check every 30 seconds when online
setInterval(() => {
    if (navigator.onLine) {
        syncQrAttendanceRecords();
    }
}, 30000);
```

## UI Elements

### 1. Offline Mode Indicator
```html
<div id="offline-mode-indicator" class="alert alert-warning d-none mb-3">
    <i class="fas fa-wifi-slash me-2"></i>
    <strong>You are offline.</strong> Attendance records will be stored locally and synced when you're back online.
</div>
```

### 2. Scanner Status
```html
<div class="alert alert-warning" id="scannerStatus">
    <i class="fas fa-wifi-slash me-2"></i>
    <strong>Offline Mode:</strong> Scans will be stored locally and synced when connection is restored.
</div>
```

### 3. Pending Sync Badge
```html
<span id="pending-sync-badge" class="badge bg-warning text-dark ms-2" onclick="showPendingSyncs()">
    3 pending syncs
</span>
```

### 4. Scan Result (Offline)
```html
<div class="card mb-3 border-warning shadow-sm">
    <div class="card-header bg-warning text-dark">
        <i class="fas fa-exclamation-triangle me-2"></i>
        Success (Offline Mode)
    </div>
    <div class="card-body">
        <h5 class="card-title">Attendance recorded offline and will sync when connection is restored</h5>
        <div class="row mt-3">
            <div class="col-6">
                <p class="mb-1"><strong>Student:</strong> Juan Dela Cruz</p>
                <p class="mb-1"><strong>Subject:</strong> Mathematics</p>
                <p class="mb-1"><strong>Date:</strong> November 7, 2025</p>
            </div>
            <div class="col-6">
                <p class="mb-1"><strong>Time:</strong> 9:15 AM</p>
                <p class="mb-1"><strong>Status:</strong> Pending</p>
            </div>
        </div>
    </div>
</div>
```

## Testing Guide

### Test Scenario 1: QR Scan Offline
1. Open qr-scanner.php
2. Select a subject
3. Turn off WiFi/Data
4. Scan a student QR code
5. Verify:
   - Warning message appears
   - Pending sync badge shows "1 pending sync"
   - Scan appears in results with (Offline Mode)
6. Turn on WiFi/Data
7. Verify:
   - Auto-sync triggers
   - Success toast appears
   - Pending sync badge disappears
   - Record appears in database

### Test Scenario 2: Multiple Offline Scans
1. Turn off connection
2. Scan 3 different student QR codes
3. Enter 2 LRNs manually
4. Verify:
   - All 5 scans stored locally
   - Badge shows "5 pending syncs"
   - Click badge to view details modal
5. Turn on connection
6. Click "Sync Now" in modal
7. Verify:
   - Progress toast appears
   - Success count: 5
   - Failed count: 0
   - Modal closes
   - All records in database

### Test Scenario 3: Failed Sync Retry
1. Turn off connection
2. Scan a QR code
3. Turn on connection but block qr-scanner.php in firewall
4. Trigger sync
5. Verify:
   - Failed toast appears
   - Record remains unsynced
   - Badge still shows pending
6. Unblock qr-scanner.php
7. Click "Sync Now" again
8. Verify:
   - Successful sync
   - Record marked as synced

### Test Scenario 4: Mixed Scan Types
1. Turn off connection
2. Scan QR code (Student A)
3. Enter LRN (Student B)
4. Select from dropdown (Student C)
5. Verify all 3 stored with correct scan_type
6. Turn on connection
7. Verify all 3 sync correctly with proper attendance records

## Troubleshooting

### Issue: Pending syncs not showing
**Solution**: 
```javascript
// Check if IndexedDB is properly initialized
initOfflineDB().then(() => {
    console.log('Database initialized');
    updatePendingSyncCount();
});
```

### Issue: Sync fails with 403/401 error
**Problem**: Session expired
**Solution**: Redirect to login page, pending syncs preserved for next session

### Issue: Duplicate attendance records
**Problem**: Sync runs multiple times for same record
**Solution**: Check `markAsSynced()` is being called after successful sync

### Issue: Old pending syncs not clearing
**Solution**:
```javascript
// Clear synced records manually
getUnsyncedData(STORE_NAMES.ATTENDANCE).then(records => {
    records.forEach(record => {
        if (record.synced) {
            deleteRecord(STORE_NAMES.ATTENDANCE, record.id);
        }
    });
});
```

## Browser Compatibility

| Feature | Chrome | Firefox | Safari | Edge |
|---------|--------|---------|--------|------|
| IndexedDB | ✅ | ✅ | ✅ | ✅ |
| Online/Offline Events | ✅ | ✅ | ✅ | ✅ |
| Service Worker | ✅ | ✅ | ✅ | ✅ |
| QR Scanner | ✅ | ✅ | ⚠️* | ✅ |

*Safari requires HTTPS for camera access

## Performance Considerations

### Storage Limits
- **IndexedDB**: ~50MB minimum (browser dependent)
- **Recommendation**: Sync frequently to avoid hitting limits
- **Monitoring**: Log database size periodically

### Sync Optimization
- **Batch Size**: Sync all records in single session
- **Retry Logic**: Wait for user action instead of auto-retry
- **Network Check**: Only sync when navigator.onLine is true

### UI Performance
- **Lazy Loading**: Load pending syncs only when badge clicked
- **Throttling**: Update pending count max once per second
- **Caching**: Cache student data for offline dropdown

## Security Considerations

### Data Integrity
- ✅ Timestamp validation prevents old data from overwriting new
- ✅ Server-side validation of all synced data
- ✅ Teacher/subject permission checks maintained

### Data Privacy
- ✅ Offline data stored locally only, not transmitted elsewhere
- ✅ Cleared when user logs out (optional implementation)
- ✅ Encrypted if device encryption enabled

## Future Enhancements

1. **Conflict Resolution**: Handle cases where same student marked present/absent offline by different teachers
2. **Bulk Operations**: Allow teachers to mark multiple students at once offline
3. **Photo Capture**: Store student photos offline for verification
4. **GPS Location**: Add GPS coordinates to offline scans for verification
5. **Biometric Auth**: Require fingerprint before offline sync to prevent tampering

## Maintenance

### Regular Tasks
- Monitor IndexedDB size
- Clear old synced records (older than 30 days)
- Review sync failure logs
- Update offline data cache (student list, subjects)

### Database Cleanup
```javascript
// Run monthly
function cleanupOldRecords() {
    const thirtyDaysAgo = Date.now() - (30 * 24 * 60 * 60 * 1000);
    getUnsyncedData(STORE_NAMES.ATTENDANCE).then(records => {
        records.forEach(record => {
            if (record.synced && record.timestamp < thirtyDaysAgo) {
                deleteRecord(STORE_NAMES.ATTENDANCE, record.id);
            }
        });
    });
}
```

## Support

For issues or questions:
1. Check browser console for errors
2. Verify IndexedDB is enabled in browser settings
3. Test with incognito/private window
4. Clear browser cache and try again
5. Check network tab in DevTools for failed requests

## Summary

The enhanced offline QR scanner system provides:
- ✅ Complete offline functionality for all scan types
- ✅ Automatic synchronization when online
- ✅ Clear visual feedback and status indicators
- ✅ Robust error handling and recovery
- ✅ Pending sync tracking and manual sync option
- ✅ Proper data structure and validation

Teachers can now scan QR codes, enter LRNs, and select students manually while offline, with confidence that all attendance records will be properly synchronized when the connection is restored.
