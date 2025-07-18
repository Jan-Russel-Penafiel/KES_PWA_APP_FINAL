<?php
// Include database configuration
require_once '../config.php';

// Set header to JSON response
header('Content-Type: application/json');

// Default response
$response = [
    'success' => false,
    'message' => 'Invalid request'
];

// Check if method is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get action from request
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    
    // Handle different actions
    switch ($action) {
        case 'login':
            // Get username and role from request
            $username = isset($_POST['username']) ? sanitize_input($_POST['username']) : '';
            $role = isset($_POST['role']) ? sanitize_input($_POST['role']) : '';
            
            if (empty($username) || empty($role)) {
                $response['message'] = 'Username and role are required';
            } else {
                try {
                    // Check user credentials with role
                    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND role = ? AND status = 'active'");
                    $stmt->execute([$username, $role]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($user) {
                        // Remove sensitive data before sending to client
                        unset($user['created_at']);
                        unset($user['updated_at']);
                        
                        // Prepare response data
                        $response = [
                            'success' => true,
                            'message' => 'Login successful',
                            'user' => $user
                        ];
                    } else {
                        $response['message'] = 'Invalid username, role combination, or account is inactive.';
                    }
                } catch(PDOException $e) {
                    $response['message'] = 'Database error: ' . $e->getMessage();
                }
            }
            break;
            
        case 'verify':
            // Check if user is logged in
            if (isLoggedIn()) {
                $user_id = $_SESSION['user_id'];
                
                try {
                    // Get user data from database
                    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND status = 'active'");
                    $stmt->execute([$user_id]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($user) {
                        // Remove sensitive data before sending to client
                        unset($user['created_at']);
                        unset($user['updated_at']);
                        
                        // Prepare response data
                        $response = [
                            'success' => true,
                            'message' => 'User verified',
                            'user' => $user,
                            'is_offline' => isset($_SESSION['offline_mode']) && $_SESSION['offline_mode'] === true
                        ];
                    } else {
                        $response['message'] = 'User not found or inactive';
                    }
                } catch(PDOException $e) {
                    $response['message'] = 'Database error: ' . $e->getMessage();
                }
            } else {
                $response['message'] = 'Not logged in';
            }
            break;
            
        default:
            $response['message'] = 'Unknown action';
            break;
    }
} else {
    $response['message'] = 'Method not allowed';
}

// Send JSON response
echo json_encode($response);
?> 