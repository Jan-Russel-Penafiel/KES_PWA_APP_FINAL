<?php
// Simple test endpoint to verify API functionality
header('Content-Type: application/json');
ini_set('display_errors', 0);

// Test basic JSON response
$test_data = [
    'success' => true,
    'message' => 'API endpoint is working',
    'timestamp' => date('Y-m-d H:i:s'),
    'test' => true
];

echo json_encode($test_data);
exit;
?>
