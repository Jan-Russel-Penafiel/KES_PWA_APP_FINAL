<?php
/**
 * KES-SMART Server-Side Cache Management
 * Handles server-side cache cleanup and management
 * Prevents system crashes by managing server resources
 */

class ServerCacheManager {
    private $config;
    private $logFile;
    
    public function __construct() {
        $this->config = [
            'temp_dir' => sys_get_temp_dir() . '/kes_smart/',
            'log_dir' => __DIR__ . '/logs/',
            'upload_dir' => __DIR__ . '/uploads/',
            'cache_dir' => __DIR__ . '/cache/',
            
            // Maximum ages in seconds
            'temp_file_max_age' => 24 * 60 * 60, // 24 hours
            'log_file_max_age' => 7 * 24 * 60 * 60, // 7 days
            'session_max_age' => 2 * 60 * 60, // 2 hours
            'cache_max_age' => 30 * 24 * 60 * 60, // 30 days
            
            // Size limits in bytes
            'max_temp_size' => 100 * 1024 * 1024, // 100MB
            'max_log_size' => 50 * 1024 * 1024, // 50MB
            'max_upload_size' => 200 * 1024 * 1024, // 200MB
            'max_cache_size' => 100 * 1024 * 1024, // 100MB
            
            // Cleanup thresholds
            'cleanup_threshold' => 0.8, // Clean when 80% full
            'emergency_threshold' => 0.95, // Emergency clean when 95% full
        ];
        
        $this->logFile = $this->config['log_dir'] . 'cache_manager.log';
        $this->ensureDirectories();
    }
    
    /**
     * Ensure required directories exist
     */
    private function ensureDirectories() {
        foreach (['temp_dir', 'log_dir', 'cache_dir'] as $dir) {
            if (!is_dir($this->config[$dir])) {
                mkdir($this->config[$dir], 0755, true);
            }
        }
    }
    
    /**
     * Log message to file
     */
    private function log($message, $level = 'INFO') {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] [$level] $message" . PHP_EOL;
        file_put_contents($this->logFile, $logMessage, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Get directory size in bytes
     */
    private function getDirectorySize($directory) {
        if (!is_dir($directory)) {
            return 0;
        }
        
        $size = 0;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }
        
        return $size;
    }
    
    /**
     * Clean old files from directory
     */
    private function cleanOldFiles($directory, $maxAge, $pattern = '*') {
        if (!is_dir($directory)) {
            return ['cleaned' => 0, 'errors' => 0];
        }
        
        $cleaned = 0;
        $errors = 0;
        $cutoffTime = time() - $maxAge;
        
        $files = glob($directory . '/' . $pattern);
        
        foreach ($files as $file) {
            if (is_file($file)) {
                $fileTime = filemtime($file);
                if ($fileTime < $cutoffTime) {
                    if (unlink($file)) {
                        $cleaned++;
                    } else {
                        $errors++;
                    }
                }
            }
        }
        
        return ['cleaned' => $cleaned, 'errors' => $errors];
    }
    
    /**
     * Clean files by size (remove oldest first)
     */
    private function cleanFilesBySize($directory, $maxSize) {
        if (!is_dir($directory)) {
            return ['cleaned' => 0, 'size_freed' => 0];
        }
        
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $files[] = [
                    'path' => $file->getPathname(),
                    'size' => $file->getSize(),
                    'time' => $file->getMTime()
                ];
            }
        }
        
        // Sort by modification time (oldest first)
        usort($files, function($a, $b) {
            return $a['time'] - $b['time'];
        });
        
        $currentSize = array_sum(array_column($files, 'size'));
        $cleaned = 0;
        $sizeFreed = 0;
        
        foreach ($files as $file) {
            if ($currentSize <= $maxSize) {
                break;
            }
            
            if (unlink($file['path'])) {
                $currentSize -= $file['size'];
                $sizeFreed += $file['size'];
                $cleaned++;
            }
        }
        
        return ['cleaned' => $cleaned, 'size_freed' => $sizeFreed];
    }
    
    /**
     * Get disk usage statistics
     */
    public function getDiskUsage() {
        $stats = [];
        
        foreach (['temp_dir', 'log_dir', 'upload_dir', 'cache_dir'] as $dir) {
            $path = $this->config[$dir];
            $size = $this->getDirectorySize($path);
            $maxSize = $this->config['max_' . str_replace('_dir', '_size', $dir)] ?? 0;
            
            $stats[$dir] = [
                'path' => $path,
                'size' => $size,
                'max_size' => $maxSize,
                'percentage' => $maxSize > 0 ? ($size / $maxSize) * 100 : 0,
                'size_formatted' => $this->formatBytes($size),
                'max_size_formatted' => $this->formatBytes($maxSize)
            ];
        }
        
        // Add total disk space info
        $diskTotal = disk_total_space('.');
        $diskFree = disk_free_space('.');
        $diskUsed = $diskTotal - $diskFree;
        
        $stats['disk'] = [
            'total' => $diskTotal,
            'used' => $diskUsed,
            'free' => $diskFree,
            'percentage' => ($diskUsed / $diskTotal) * 100,
            'total_formatted' => $this->formatBytes($diskTotal),
            'used_formatted' => $this->formatBytes($diskUsed),
            'free_formatted' => $this->formatBytes($diskFree)
        ];
        
        return $stats;
    }
    
    /**
     * Format bytes to human readable format
     */
    private function formatBytes($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
    
    /**
     * Perform routine cleanup
     */
    public function performRoutineCleanup() {
        $this->log('Starting routine cleanup');
        $results = [];
        
        try {
            // Clean temp files
            $tempResult = $this->cleanOldFiles(
                $this->config['temp_dir'],
                $this->config['temp_file_max_age']
            );
            $results['temp_files'] = $tempResult;
            $this->log("Cleaned {$tempResult['cleaned']} temp files");
            
            // Clean old logs
            $logResult = $this->cleanOldFiles(
                $this->config['log_dir'],
                $this->config['log_file_max_age'],
                '*.log'
            );
            $results['log_files'] = $logResult;
            $this->log("Cleaned {$logResult['cleaned']} log files");
            
            // Clean old cache files
            $cacheResult = $this->cleanOldFiles(
                $this->config['cache_dir'],
                $this->config['cache_max_age']
            );
            $results['cache_files'] = $cacheResult;
            $this->log("Cleaned {$cacheResult['cleaned']} cache files");
            
            // Clean old sessions
            $this->cleanOldSessions();
            $results['sessions'] = ['cleaned' => 'processed'];
            
            // Check for size-based cleanup
            $usage = $this->getDiskUsage();
            foreach ($usage as $dir => $stats) {
                if ($dir === 'disk') continue;
                
                if ($stats['percentage'] > $this->config['cleanup_threshold'] * 100) {
                    $maxSize = $stats['max_size'] * 0.7; // Clean to 70% of max
                    $sizeResult = $this->cleanFilesBySize($stats['path'], $maxSize);
                    $results[$dir . '_size_cleanup'] = $sizeResult;
                    $this->log("Size cleanup for {$dir}: freed {$this->formatBytes($sizeResult['size_freed'])}");
                }
            }
            
            $this->log('Routine cleanup completed successfully');
            
        } catch (Exception $e) {
            $this->log('Routine cleanup failed: ' . $e->getMessage(), 'ERROR');
            $results['error'] = $e->getMessage();
        }
        
        return $results;
    }
    
    /**
     * Perform emergency cleanup
     */
    public function performEmergencyCleanup() {
        $this->log('Starting emergency cleanup', 'WARN');
        $results = [];
        
        try {
            // More aggressive cleanup with shorter retention
            $emergencyMaxAge = 6 * 60 * 60; // 6 hours
            
            // Clean temp files aggressively
            $tempResult = $this->cleanOldFiles(
                $this->config['temp_dir'],
                $emergencyMaxAge
            );
            $results['temp_files'] = $tempResult;
            
            // Clean cache files aggressively
            $cacheResult = $this->cleanOldFiles(
                $this->config['cache_dir'],
                $emergencyMaxAge
            );
            $results['cache_files'] = $cacheResult;
            
            // Size-based emergency cleanup (clean to 50% of max)
            $usage = $this->getDiskUsage();
            foreach ($usage as $dir => $stats) {
                if ($dir === 'disk') continue;
                
                $targetSize = $stats['max_size'] * 0.5;
                $sizeResult = $this->cleanFilesBySize($stats['path'], $targetSize);
                $results[$dir . '_emergency_cleanup'] = $sizeResult;
            }
            
            // Clean all sessions
            $this->cleanAllSessions();
            $results['sessions'] = ['cleaned' => 'all'];
            
            $this->log('Emergency cleanup completed', 'WARN');
            
        } catch (Exception $e) {
            $this->log('Emergency cleanup failed: ' . $e->getMessage(), 'ERROR');
            $results['error'] = $e->getMessage();
        }
        
        return $results;
    }
    
    /**
     * Clean old PHP sessions
     */
    private function cleanOldSessions() {
        $sessionPath = session_save_path() ?: sys_get_temp_dir();
        $maxAge = $this->config['session_max_age'];
        
        $result = $this->cleanOldFiles($sessionPath, $maxAge, 'sess_*');
        $this->log("Cleaned {$result['cleaned']} session files");
        
        return $result;
    }
    
    /**
     * Clean all PHP sessions (emergency)
     */
    private function cleanAllSessions() {
        $sessionPath = session_save_path() ?: sys_get_temp_dir();
        $files = glob($sessionPath . '/sess_*');
        $cleaned = 0;
        
        foreach ($files as $file) {
            if (unlink($file)) {
                $cleaned++;
            }
        }
        
        $this->log("Emergency cleaned {$cleaned} session files", 'WARN');
        return $cleaned;
    }
    
    /**
     * Check if cleanup is needed
     */
    public function checkCleanupNeeded() {
        $usage = $this->getDiskUsage();
        $needsCleanup = false;
        $needsEmergency = false;
        
        foreach ($usage as $dir => $stats) {
            if ($dir === 'disk') {
                if ($stats['percentage'] > $this->config['emergency_threshold'] * 100) {
                    $needsEmergency = true;
                } elseif ($stats['percentage'] > $this->config['cleanup_threshold'] * 100) {
                    $needsCleanup = true;
                }
                continue;
            }
            
            if ($stats['percentage'] > $this->config['emergency_threshold'] * 100) {
                $needsEmergency = true;
            } elseif ($stats['percentage'] > $this->config['cleanup_threshold'] * 100) {
                $needsCleanup = true;
            }
        }
        
        return [
            'needs_cleanup' => $needsCleanup,
            'needs_emergency' => $needsEmergency,
            'usage' => $usage
        ];
    }
    
    /**
     * Get cleanup schedule recommendations
     */
    public function getCleanupSchedule() {
        $lastCleanup = $this->getLastCleanupTime();
        $timeSinceCleanup = time() - $lastCleanup;
        
        $recommendations = [];
        
        if ($timeSinceCleanup > 24 * 60 * 60) { // 24 hours
            $recommendations[] = 'Routine cleanup is overdue';
        }
        
        $usage = $this->getDiskUsage();
        if ($usage['disk']['percentage'] > 85) {
            $recommendations[] = 'Disk usage is high - immediate cleanup recommended';
        }
        
        return [
            'last_cleanup' => date('Y-m-d H:i:s', $lastCleanup),
            'time_since_cleanup' => $this->formatTime($timeSinceCleanup),
            'recommendations' => $recommendations
        ];
    }
    
    /**
     * Get last cleanup time
     */
    private function getLastCleanupTime() {
        $file = $this->config['cache_dir'] . 'last_cleanup.txt';
        if (file_exists($file)) {
            return (int)file_get_contents($file);
        }
        return 0;
    }
    
    /**
     * Update last cleanup time
     */
    private function updateLastCleanupTime() {
        $file = $this->config['cache_dir'] . 'last_cleanup.txt';
        file_put_contents($file, time());
    }
    
    /**
     * Format time duration
     */
    private function formatTime($seconds) {
        if ($seconds < 60) {
            return $seconds . ' seconds';
        } elseif ($seconds < 3600) {
            return floor($seconds / 60) . ' minutes';
        } elseif ($seconds < 86400) {
            return floor($seconds / 3600) . ' hours';
        } else {
            return floor($seconds / 86400) . ' days';
        }
    }
    
    /**
     * Auto cleanup based on current conditions
     */
    public function autoCleanup() {
        $check = $this->checkCleanupNeeded();
        
        if ($check['needs_emergency']) {
            $result = $this->performEmergencyCleanup();
            $this->updateLastCleanupTime();
            return ['type' => 'emergency', 'result' => $result];
        } elseif ($check['needs_cleanup']) {
            $result = $this->performRoutineCleanup();
            $this->updateLastCleanupTime();
            return ['type' => 'routine', 'result' => $result];
        }
        
        return ['type' => 'none', 'message' => 'No cleanup needed'];
    }
}

// API endpoint handling
if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    $manager = new ServerCacheManager();
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    try {
        switch ($action) {
            case 'status':
                echo json_encode([
                    'success' => true,
                    'usage' => $manager->getDiskUsage(),
                    'schedule' => $manager->getCleanupSchedule()
                ]);
                break;
                
            case 'cleanup':
                $result = $manager->performRoutineCleanup();
                echo json_encode([
                    'success' => true,
                    'type' => 'routine',
                    'result' => $result
                ]);
                break;
                
            case 'emergency':
                $result = $manager->performEmergencyCleanup();
                echo json_encode([
                    'success' => true,
                    'type' => 'emergency',
                    'result' => $result
                ]);
                break;
                
            case 'auto':
                $result = $manager->autoCleanup();
                echo json_encode([
                    'success' => true,
                    'result' => $result
                ]);
                break;
                
            case 'check':
                $check = $manager->checkCleanupNeeded();
                echo json_encode([
                    'success' => true,
                    'check' => $check
                ]);
                break;
                
            default:
                echo json_encode([
                    'success' => false,
                    'error' => 'Invalid action'
                ]);
        }
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
} else {
    // Return basic status for GET requests
    header('Content-Type: application/json');
    $manager = new ServerCacheManager();
    echo json_encode([
        'success' => true,
        'message' => 'Server Cache Manager is running',
        'usage' => $manager->getDiskUsage()
    ]);
}
?>