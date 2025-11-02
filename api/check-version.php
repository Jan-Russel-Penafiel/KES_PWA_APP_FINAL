<?php
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Current version of the service worker - increment this when you make changes
$version = '1.0.4'; // Updated to trigger automatic update

// You can also check for actual file changes or database version
$appVersion = [
    'version' => $version,
    'timestamp' => time(),
    'build' => date('Y-m-d H:i:s'), // Build timestamp
    'features' => [
        'automatic_updates' => true,
        'offline_mode' => true,
        'background_sync' => true,
        'cache_management' => true
    ]
];

// Return the current version in JSON format
echo json_encode($appVersion);
?>
