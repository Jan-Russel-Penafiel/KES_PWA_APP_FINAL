<?php
require_once 'config.php';

// Simple session status checker (for debugging)
header('Content-Type: application/json');

$response = [
    'logged_in' => isLoggedIn(),
    'session_id' => session_id(),
    'user_id' => $_SESSION['user_id'] ?? 'none',
    'role' => $_SESSION['role'] ?? 'none',
    'username' => $_SESSION['username'] ?? 'none',
    'last_activity' => $_SESSION['LAST_ACTIVITY'] ?? 'not set',
    'current_time' => time(),
    'session_age' => isset($_SESSION['LAST_ACTIVITY']) ? (time() - $_SESSION['LAST_ACTIVITY']) : 'unknown'
];

// Update activity if user is logged in
if (isLoggedIn()) {
    updateSessionActivity();
    $response['activity_updated'] = true;
} else {
    $response['activity_updated'] = false;
}

echo json_encode($response, JSON_PRETTY_PRINT);
?>