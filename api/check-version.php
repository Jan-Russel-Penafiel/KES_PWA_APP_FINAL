<?php
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Enhanced version checking with multiple triggers
$currentVersion = '1.0.7'; // Auto-increment this for updates - notifications disabled

// Get file modification times to detect changes
$importantFiles = [
    'sw.js' => '../sw.js',
    'profile.php' => '../profile.php',
    'dashboard.php' => '../dashboard.php',
    'config.php' => '../config.php',
    'pwa.css' => '../assets/css/pwa.css'
];

$latestModification = 0;
$changedFiles = [];

foreach ($importantFiles as $name => $path) {
    if (file_exists($path)) {
        $mtime = filemtime($path);
        if ($mtime > $latestModification) {
            $latestModification = $mtime;
        }
        
        // Check if file was modified in the last 5 minutes
        if ($mtime > (time() - 300)) {
            $changedFiles[] = $name;
        }
    }
}

// Create a dynamic version based on file changes
$fileBasedVersion = $currentVersion . '.' . date('YmdHi', $latestModification);

// Determine if an update is needed
$shouldUpdate = false;
$updateReason = '';

if (!empty($changedFiles)) {
    $shouldUpdate = true;
    $updateReason = 'Recent file changes detected: ' . implode(', ', $changedFiles);
}

$appVersion = [
    'version' => $fileBasedVersion,
    'base_version' => $currentVersion,
    'timestamp' => time(),
    'build_time' => date('Y-m-d H:i:s'),
    'last_modified' => date('Y-m-d H:i:s', $latestModification),
    'update_available' => $shouldUpdate,
    'update_reason' => $updateReason,
    'changed_files' => $changedFiles,
    'features' => [
        'automatic_updates' => true,
        'offline_mode' => true,
        'background_sync' => true,
        'cache_management' => true,
        'profile_improvements' => true,
        'enhanced_mobile_ui' => true
    ],
    'update_triggers' => [
        'file_changes' => true,
        'version_bump' => true,
        'periodic_check' => true,
        'user_navigation' => true
    ]
];

// Log update checks for debugging
$logFile = '../logs/update_checks.log';
if (is_writable(dirname($logFile))) {
    $logEntry = date('Y-m-d H:i:s') . " - Version check: {$fileBasedVersion}, Update needed: " . ($shouldUpdate ? 'Yes' : 'No') . "\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

// Return the version information
echo json_encode($appVersion, JSON_PRETTY_PRINT);
?>
