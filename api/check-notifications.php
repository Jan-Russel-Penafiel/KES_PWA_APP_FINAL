<?php
require_once '../config.php';

// Set JSON content type
header('Content-Type: application/json');

try {
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['hasNew' => false, 'notifications' => []]);
        exit;
    }
    
    $user_id = $_SESSION['user_id'];
    $current_time = date('Y-m-d H:i:s');
    
    // For now, return a simple response indicating no new notifications
    // This can be expanded later to check for actual notifications from the database
    $response = [
        'hasNew' => false,
        'notifications' => [],
        'timestamp' => $current_time
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    // Return error response in JSON format
    $response = [
        'hasNew' => false,
        'notifications' => [],
        'error' => $e->getMessage()
    ];
    
    echo json_encode($response);
}
?>
