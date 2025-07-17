<?php
header('Content-Type: application/json');
header('Cache-Control: no-cache');

// Current version of the service worker
$version = '1.0.1';

// Return the current version in JSON format
echo json_encode([
    'version' => $version,
    'timestamp' => time()
]);
?>
