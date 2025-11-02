<?php
/**
 * KES-SMART Automated Cache Cleanup Cron Job
 * Run this script periodically to automatically clean cache and prevent crashes
 * 
 * Usage:
 * - Add to crontab: 0 */6 * * * php /path/to/smart/cron/cache_cleanup.php
 * - Or run via Windows Task Scheduler every 6 hours
 */

// Set script timeout and memory limit
set_time_limit(300); // 5 minutes
ini_set('memory_limit', '256M');

// Include the server cache manager
require_once dirname(__DIR__) . '/api/server-cache-manager.php';

class AutoCacheCleanup {
    private $manager;
    private $logFile;
    private $config;
    
    public function __construct() {
        $this->manager = new ServerCacheManager();
        $this->logFile = dirname(__DIR__) . '/logs/auto_cache_cleanup.log';
        $this->config = [
            'force_cleanup_age' => 24 * 60 * 60, // Force cleanup if last was >24h ago
            'emergency_disk_threshold' => 90, // Emergency if disk >90% full
            'notification_email' => '', // Set email for notifications
            'max_log_size' => 10 * 1024 * 1024, // 10MB max log file
        ];
        
        $this->ensureLogDirectory();
    }
    
    private function ensureLogDirectory() {
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }
    
    private function log($message, $level = 'INFO') {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] [AUTO-CLEANUP] [$level] $message" . PHP_EOL;
        
        // Rotate log if it's too large
        if (file_exists($this->logFile) && filesize($this->logFile) > $this->config['max_log_size']) {
            $this->rotateLog();
        }
        
        file_put_contents($this->logFile, $logMessage, FILE_APPEND | LOCK_EX);
        
        // Also output to console if running from command line
        if (php_sapi_name() === 'cli') {
            echo $logMessage;
        }
    }
    
    private function rotateLog() {
        $rotatedFile = $this->logFile . '.' . date('Y-m-d-H-i-s');
        rename($this->logFile, $rotatedFile);
        
        // Keep only last 5 rotated logs
        $pattern = dirname($this->logFile) . '/auto_cache_cleanup.log.*';
        $files = glob($pattern);
        if (count($files) > 5) {
            sort($files);
            $filesToDelete = array_slice($files, 0, count($files) - 5);
            foreach ($filesToDelete as $file) {
                unlink($file);
            }
        }
    }
    
    private function sendNotification($subject, $message) {
        if (empty($this->config['notification_email'])) {
            return;
        }
        
        $headers = 'From: KES-SMART System <noreply@kes-smart.local>' . "\r\n" .
                   'Reply-To: noreply@kes-smart.local' . "\r\n" .
                   'X-Mailer: PHP/' . phpversion();
        
        mail($this->config['notification_email'], $subject, $message, $headers);
    }
    
    public function run() {
        $this->log('Starting automated cache cleanup check');
        
        try {
            // Get current status
            $check = $this->manager->checkCleanupNeeded();
            $usage = $check['usage'];
            
            $this->log('Current disk usage: ' . number_format($usage['disk']['percentage'], 2) . '%');
            
            $actionTaken = false;
            $cleanupType = '';
            $cleanupResult = null;
            
            // Check for emergency conditions
            if ($usage['disk']['percentage'] > $this->config['emergency_disk_threshold']) {
                $this->log('EMERGENCY: Disk usage critically high, performing emergency cleanup', 'WARN');
                $cleanupResult = $this->manager->performEmergencyCleanup();
                $cleanupType = 'emergency';
                $actionTaken = true;
                
                $this->sendNotification(
                    'KES-SMART Emergency Cache Cleanup',
                    "Emergency cache cleanup was performed due to high disk usage ({$usage['disk']['percentage']}%).\n\n" .
                    "Results: " . json_encode($cleanupResult, JSON_PRETTY_PRINT)
                );
            }
            // Check if routine cleanup is needed
            elseif ($check['needs_cleanup'] || $check['needs_emergency']) {
                $this->log('Cleanup needed, performing routine cleanup');
                $cleanupResult = $this->manager->performRoutineCleanup();
                $cleanupType = 'routine';
                $actionTaken = true;
            }
            // Check if it's been too long since last cleanup
            else {
                $schedule = $this->manager->getCleanupSchedule();
                $lastCleanupFile = dirname(__DIR__) . '/cache/last_cleanup.txt';
                
                if (file_exists($lastCleanupFile)) {
                    $lastCleanup = (int)file_get_contents($lastCleanupFile);
                    $timeSinceCleanup = time() - $lastCleanup;
                    
                    if ($timeSinceCleanup > $this->config['force_cleanup_age']) {
                        $this->log('Forcing cleanup due to age (last cleanup: ' . date('Y-m-d H:i:s', $lastCleanup) . ')');
                        $cleanupResult = $this->manager->performRoutineCleanup();
                        $cleanupType = 'scheduled';
                        $actionTaken = true;
                    }
                } else {
                    // No record of previous cleanup, perform one
                    $this->log('No previous cleanup record found, performing initial cleanup');
                    $cleanupResult = $this->manager->performRoutineCleanup();
                    $cleanupType = 'initial';
                    $actionTaken = true;
                }
            }
            
            if ($actionTaken) {
                $this->log("Cleanup completed - Type: {$cleanupType}");
                $this->log("Results: " . json_encode($cleanupResult));
                
                // Update last cleanup time
                $lastCleanupFile = dirname(__DIR__) . '/cache/last_cleanup.txt';
                file_put_contents($lastCleanupFile, time());
                
                // Log final disk usage
                $finalUsage = $this->manager->getDiskUsage();
                $this->log('Final disk usage: ' . number_format($finalUsage['disk']['percentage'], 2) . '%');
                
                // Send notification for significant cleanups
                if ($cleanupType === 'emergency' || 
                    ($cleanupType === 'routine' && $usage['disk']['percentage'] > 80)) {
                    
                    $freed = $usage['disk']['used'] - $finalUsage['disk']['used'];
                    $this->sendNotification(
                        "KES-SMART Cache Cleanup Report",
                        "Cache cleanup completed successfully.\n\n" .
                        "Type: {$cleanupType}\n" .
                        "Disk usage before: {$usage['disk']['percentage']}%\n" .
                        "Disk usage after: {$finalUsage['disk']['percentage']}%\n" .
                        "Space freed: " . $this->formatBytes($freed) . "\n\n" .
                        "Results: " . json_encode($cleanupResult, JSON_PRETTY_PRINT)
                    );
                }
            } else {
                $this->log('No cleanup needed at this time');
            }
            
            // Check for and log any warnings
            $this->checkAndLogWarnings($usage);
            
            $this->log('Automated cache cleanup check completed');
            
            return [
                'success' => true,
                'action_taken' => $actionTaken,
                'cleanup_type' => $cleanupType,
                'result' => $cleanupResult,
                'usage' => $usage
            ];
            
        } catch (Exception $e) {
            $this->log('Automated cleanup failed: ' . $e->getMessage(), 'ERROR');
            
            $this->sendNotification(
                'KES-SMART Cache Cleanup Error',
                "Automated cache cleanup failed with error:\n\n" . $e->getMessage() . "\n\n" .
                "Stack trace:\n" . $e->getTraceAsString()
            );
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    private function checkAndLogWarnings($usage) {
        $warnings = [];
        
        // Check disk usage
        if ($usage['disk']['percentage'] > 85) {
            $warnings[] = "High disk usage: {$usage['disk']['percentage']}%";
        }
        
        // Check individual directories
        foreach ($usage as $dir => $stats) {
            if ($dir === 'disk') continue;
            
            if ($stats['percentage'] > 90) {
                $warnings[] = "High {$dir} usage: {$stats['percentage']}%";
            }
        }
        
        // Check available memory
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = $this->parseSize(ini_get('memory_limit'));
        if ($memoryLimit > 0) {
            $memoryPercentage = ($memoryUsage / $memoryLimit) * 100;
            if ($memoryPercentage > 80) {
                $warnings[] = "High memory usage: {$memoryPercentage}%";
            }
        }
        
        foreach ($warnings as $warning) {
            $this->log($warning, 'WARN');
        }
    }
    
    private function parseSize($size) {
        $unit = preg_replace('/[^bkmgtpezy]/i', '', $size);
        $size = preg_replace('/[^0-9\.]/', '', $size);
        
        if ($unit) {
            return round($size * pow(1024, stripos('bkmgtpezy', $unit[0])));
        }
        
        return round($size);
    }
    
    private function formatBytes($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
}

// Run the cleanup if called directly
if (php_sapi_name() === 'cli' || !isset($_SERVER['HTTP_HOST'])) {
    // Running from command line or direct call
    $cleanup = new AutoCacheCleanup();
    $result = $cleanup->run();
    
    if (php_sapi_name() === 'cli') {
        echo "Cleanup completed with status: " . ($result['success'] ? 'SUCCESS' : 'FAILED') . "\n";
        if (!$result['success']) {
            echo "Error: " . $result['error'] . "\n";
            exit(1);
        }
        exit(0);
    } else {
        header('Content-Type: application/json');
        echo json_encode($result);
    }
}
?>