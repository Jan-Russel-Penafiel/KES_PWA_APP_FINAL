# KES-SMART Automatic Cache Management System

## Overview

The KES-SMART Automatic Cache Management System is a comprehensive solution designed to prevent system crashes by intelligently managing cache storage, IndexedDB, and localStorage. The system automatically monitors storage usage and performs cleanup operations to maintain optimal performance.

## Features

### 🔄 Automatic Cache Cleanup
- **Intelligent Monitoring**: Continuously monitors storage usage across all storage types
- **Threshold-Based Cleanup**: Automatically triggers cleanup when storage reaches configurable thresholds
- **Progressive Cleanup**: Performs routine cleanup at 80% capacity, emergency cleanup at 95%
- **Scheduled Maintenance**: Runs periodic cleanup operations every 6 hours

### 📊 Storage Management
- **Cache Storage**: Manages browser cache (100MB limit)
- **IndexedDB**: Manages offline data storage (50MB limit)
- **localStorage**: Manages temporary data (5MB limit)
- **Real-time Monitoring**: Tracks usage statistics and trends

### 🛠️ Multiple Cleanup Modes
1. **Quick Cleanup**: Fast cleanup of obviously old data
2. **Routine Cleanup**: Standard cleanup following retention policies
3. **Emergency Cleanup**: Aggressive cleanup for critical storage situations
4. **Manual Cleanup**: On-demand cleanup operations

### 🎯 Smart Retention Policies
- **Cache Files**: 7 days retention for dynamic content
- **Synced Data**: 7 days retention for synchronized records
- **Unsynced Data**: 30 days retention for pending sync
- **Session Data**: 2 hours retention for temporary sessions

## System Components

### Client-Side Components

#### 1. Cache Manager (`assets/js/cache-manager.js`)
The main client-side cache management engine that:
- Monitors storage usage in real-time
- Performs automatic cleanup operations
- Provides API for manual cache management
- Emits events for monitoring and logging

#### 2. Cache Clear Utilities (`assets/js/cache-clear.js`)
Simple interface functions for:
- Clearing all caches
- Clearing IndexedDB
- Clearing localStorage
- Quick cleanup operations

#### 3. Service Worker Integration (`sw.js`)
Enhanced service worker with:
- Automatic cache health checks
- Corrupted cache entry removal
- Periodic cleanup scheduling
- Cache size monitoring

### Server-Side Components

#### 1. Server Cache Manager (`api/server-cache-manager.php`)
Server-side cache management for:
- Temporary file cleanup
- Log file management
- Upload directory cleanup
- Session file cleanup
- Disk usage monitoring

#### 2. Automated Cleanup Cron Job (`cron/cache_cleanup.php`)
Scheduled task that:
- Performs automatic server-side cleanup
- Monitors disk usage
- Sends notification alerts
- Logs cleanup operations

#### 3. Windows Batch Script (`cron/run_cache_cleanup.bat`)
Windows-compatible script for:
- Easy manual execution
- Task Scheduler integration
- Error handling and logging

### Administration Interface

#### Cache Management Dashboard (`cache-management.php`)
Web-based administration interface featuring:
- Real-time storage usage statistics
- Visual progress indicators
- Manual cleanup controls
- System status monitoring
- Cleanup operation logs
- Recommendations and alerts

## Installation & Setup

### 1. Automatic Installation
The cache management system is automatically installed when you access any page in the KES-SMART application. The cache manager initializes itself and starts monitoring immediately.

### 2. Manual Installation
If needed, you can manually initialize the cache manager:

```javascript
// Initialize cache manager
const cacheManager = new CacheManager();

// Listen for events
cacheManager.on('cleanup-completed', (result) => {
    console.log('Cleanup completed:', result);
});
```

### 3. Server-Side Setup

#### For Windows (XAMPP/WAMP):
1. Use the provided batch file:
   ```batch
   cd C:\xampp\htdocs\smart\cron
   run_cache_cleanup.bat
   ```

2. Or set up Windows Task Scheduler:
   - Open Task Scheduler
   - Create Basic Task
   - Set trigger: Daily, every 6 hours
   - Set action: Start program `php.exe`
   - Add arguments: `C:\xampp\htdocs\smart\cron\cache_cleanup.php`

#### For Linux/Unix:
Add to crontab:
```bash
# Run cache cleanup every 6 hours
0 */6 * * * php /path/to/smart/cron/cache_cleanup.php
```

## Configuration

### Client-Side Configuration
The cache manager can be configured by modifying the config object in `cache-manager.js`:

```javascript
const config = {
    // Storage limits (in bytes)
    MAX_CACHE_SIZE: 100 * 1024 * 1024, // 100MB
    MAX_INDEXEDDB_SIZE: 50 * 1024 * 1024, // 50MB
    MAX_LOCALSTORAGE_SIZE: 5 * 1024 * 1024, // 5MB
    
    // Cleanup thresholds (percentage)
    CLEANUP_THRESHOLD: 0.8, // 80%
    EMERGENCY_THRESHOLD: 0.95, // 95%
    
    // Retention periods (milliseconds)
    CACHE_MAX_AGE: 7 * 24 * 60 * 60 * 1000, // 7 days
    INDEXEDDB_MAX_AGE: 30 * 24 * 60 * 60 * 1000, // 30 days
    SYNCED_DATA_MAX_AGE: 7 * 24 * 60 * 60 * 1000, // 7 days
};
```

### Server-Side Configuration
Server-side settings can be modified in `api/server-cache-manager.php`:

```php
$config = [
    'temp_file_max_age' => 24 * 60 * 60, // 24 hours
    'log_file_max_age' => 7 * 24 * 60 * 60, // 7 days
    'session_max_age' => 2 * 60 * 60, // 2 hours
    'max_temp_size' => 100 * 1024 * 1024, // 100MB
    'cleanup_threshold' => 0.8, // 80%
    'emergency_threshold' => 0.95, // 95%
];
```

## API Reference

### Client-Side API

#### CacheManager Class
```javascript
// Get current storage usage
const usage = await cacheManager.getStorageUsage();

// Perform manual cleanup
const result = await cacheManager.manualCleanup();

// Get cleanup statistics
const stats = await cacheManager.getCleanupStats();

// Get system status
const status = await cacheManager.getStatus();
```

#### Event Listeners
```javascript
// Listen for cleanup events
cacheManager.on('cleanup-completed', (data) => {
    console.log('Cleanup completed:', data.result);
});

cacheManager.on('cleanup-failed', (data) => {
    console.error('Cleanup failed:', data.error);
});

cacheManager.on('storage-check', (usage) => {
    console.log('Storage usage:', usage);
});
```

### Server-Side API

#### REST Endpoints
```php
// Get status
GET /api/server-cache-manager.php?action=status

// Perform routine cleanup
POST /api/server-cache-manager.php
Content-Type: application/json
{"action": "cleanup"}

// Perform emergency cleanup
POST /api/server-cache-manager.php
Content-Type: application/json
{"action": "emergency"}

// Auto cleanup
POST /api/server-cache-manager.php
Content-Type: application/json
{"action": "auto"}
```

## Monitoring & Alerts

### Real-Time Monitoring
- **Storage Usage**: Continuous monitoring of all storage types
- **Performance Impact**: Tracks cleanup performance and timing
- **Error Detection**: Identifies and logs cleanup failures
- **Trend Analysis**: Monitors storage usage patterns

### Alert System
The system provides multiple alert levels:

1. **Info Alerts**: Normal cleanup operations
2. **Warning Alerts**: High storage usage (>80%)
3. **Error Alerts**: Cleanup failures or critical issues
4. **Critical Alerts**: Emergency cleanup triggered (>95%)

### Notification Options
- **Browser Notifications**: In-app notifications for users
- **Email Alerts**: Server-side email notifications for administrators
- **Log Files**: Detailed logging for system administrators
- **Dashboard Alerts**: Visual indicators in the admin interface

## Troubleshooting

### Common Issues

#### 1. Cache Manager Not Loading
**Symptoms**: Cache management functions are undefined
**Solution**: 
- Ensure `cache-manager.js` is loaded before other scripts
- Check browser console for JavaScript errors
- Verify file permissions and accessibility

#### 2. Cleanup Not Working
**Symptoms**: Storage continues to grow despite cleanup
**Solution**:
- Check browser storage permissions
- Verify IndexedDB and localStorage access
- Review cleanup thresholds and retention policies

#### 3. Server-Side Cleanup Fails
**Symptoms**: Cron job errors or failed server cleanup
**Solution**:
- Check PHP execution permissions
- Verify file system write permissions
- Review error logs for specific issues

#### 4. High Storage Usage
**Symptoms**: Persistent high storage usage warnings
**Solution**:
- Lower cleanup thresholds temporarily
- Perform emergency cleanup
- Review data retention policies
- Check for corrupted data

### Debug Mode
Enable debug logging by setting:
```javascript
localStorage.setItem('cache-manager-debug', 'true');
```

### Recovery Options
If the system becomes unresponsive due to storage issues:

1. **Emergency Reset**:
   ```javascript
   // Clear everything and start fresh
   await cacheManager.clearAllCaches();
   await cacheManager.clearAllIndexedDB();
   localStorage.clear();
   ```

2. **Safe Mode**: Disable automatic cleanup temporarily:
   ```javascript
   window.cacheManager.config.CLEANUP_THRESHOLD = 1.0; // Disable auto cleanup
   ```

## Performance Considerations

### Resource Usage
- **Memory**: Minimal memory footprint (~2-5MB)
- **CPU**: Low impact background operations
- **Network**: No network requests for client-side operations
- **Storage**: Self-managing storage usage

### Optimization Tips
1. **Adjust Thresholds**: Tune cleanup thresholds based on usage patterns
2. **Schedule Cleanup**: Run intensive cleanup during off-peak hours
3. **Monitor Trends**: Track storage usage trends to predict needs
4. **Regular Maintenance**: Perform manual cleanup during maintenance windows

## Security Considerations

### Data Protection
- **No Sensitive Data**: Only manages cache and temporary data
- **Local Processing**: All cleanup operations are performed locally
- **Permission Respect**: Respects browser storage permissions
- **Error Handling**: Graceful failure without data corruption

### Access Control
- **Admin Only**: Cache management interface restricted to administrators
- **API Security**: Server-side API includes access validation
- **Audit Trail**: All operations are logged for security review

## Version History

### v1.0.0 (Current)
- Initial release with comprehensive cache management
- Automatic cleanup with configurable thresholds
- Real-time monitoring and alerting
- Web-based administration interface
- Cross-platform compatibility (Windows/Linux)
- Integration with existing KES-SMART PWA

## Support & Maintenance

### Regular Maintenance
- **Weekly**: Review cleanup logs and performance metrics
- **Monthly**: Adjust thresholds based on usage patterns
- **Quarterly**: Update retention policies as needed
- **Annually**: Review and optimize configuration

### Getting Help
1. Check the troubleshooting section above
2. Review browser console for error messages
3. Check server error logs for server-side issues
4. Consult the API reference for implementation details

### Best Practices
1. **Monitor Regularly**: Keep an eye on storage usage trends
2. **Test Changes**: Test configuration changes in development first
3. **Backup Settings**: Keep backups of custom configurations
4. **Document Changes**: Document any custom modifications
5. **Stay Updated**: Keep the system updated with latest improvements

---

**Note**: This cache management system is designed to be maintenance-free under normal operation. The automatic cleanup and monitoring features ensure optimal performance without manual intervention. However, the administration interface provides full control when needed.