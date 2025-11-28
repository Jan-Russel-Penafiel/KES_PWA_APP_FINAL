<?php
// Set timezone to Manila for the entire application
date_default_timezone_set('Asia/Manila');

// Set a flag to track if we're running in offline mode
$GLOBALS['is_offline_mode'] = false;

// Database Configuration
$db_host = 'localhost';
$db_name = 'kes_smart';
$db_user = 'root';
$db_pass = '';

// Create database connection
try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    // Set the offline mode flag instead of dying
    $GLOBALS['is_offline_mode'] = true;
    // Create a dummy PDO object for offline mode that won't throw errors
    $pdo = new stdClass();
    // Add a method to make offline PDO calls fail gracefully
    $pdo->query = $pdo->prepare = function() {
        // Create a dummy statement that returns empty results
        $stmt = new stdClass();
        $stmt->execute = function() { return false; };
        $stmt->fetch = $stmt->fetchAll = $stmt->fetchColumn = function() { return false; };
        return $stmt;
    };
    
    // Log the error rather than displaying it
    error_log("Database connection failed (offline mode): " . $e->getMessage());
}

// Site Configuration
$site_name = "TAC-QR";
$site_description = "Student Monitoring Application with Real-time QR and SMS Notifications";

// Get SMS API configuration from database
function getSMSConfig($pdo) {
    if ($GLOBALS['is_offline_mode']) return false;
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM sms_config WHERE id = 1 LIMIT 1");
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        return false;
    }
}

// Session management
if (session_status() === PHP_SESSION_NONE) {
    // Set session timeout to 1 hour (3600 seconds)
    ini_set('session.gc_maxlifetime', 3600);
    
    // Set session cookie parameters (compatible format)
    // Lifetime: 3600 seconds (1 hour), Path: '/', Domain: '', Secure: false, HttpOnly: true
    session_set_cookie_params(3600, '/', '', false, true);
    
    // Start the session
    session_start();
    
    // Regenerate session ID periodically to prevent session fixation
    // But only if we have a valid session and it's been more than 5 minutes
    if (isset($_SESSION['user_id']) && (!isset($_SESSION['CREATED']) || (time() - $_SESSION['CREATED']) > 300)) {
        session_regenerate_id(true);
        $_SESSION['CREATED'] = time();
    }
    
    // Initialize session creation time if not set
    if (!isset($_SESSION['CREATED'])) {
        $_SESSION['CREATED'] = time();
    }
}

// Simple session activity update function
function updateSessionActivity() {
    if (isset($_SESSION['user_id'])) {
        $_SESSION['LAST_ACTIVITY'] = time();
    }
}

// Check session timeout function (only for manual checking)
function checkSessionTimeout() {
    // Only check timeout if explicitly called and all conditions are met
    if (isset($_SESSION['user_id']) && isset($_SESSION['LAST_ACTIVITY'])) {
        if ((time() - $_SESSION['LAST_ACTIVITY']) > 3600) {
            // Last request was more than 1 hour ago
            return false;
        }
    }
    return true;
}

// Helper functions
function sanitize_input($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function redirect($url) {
    header("Location: $url");
    exit();
}

function isLoggedIn() {
    // Simple session check - just verify user_id exists and is valid
    if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id']) && is_numeric($_SESSION['user_id'])) {
        // Update last activity on each check to keep session alive
        if (!isset($_SESSION['LAST_ACTIVITY'])) {
            $_SESSION['LAST_ACTIVITY'] = time();
        }
        return true;
    }
    
    // Check for offline mode login via cookie (only if database is offline)
    if ($GLOBALS['is_offline_mode'] && isset($_COOKIE['kes_smart_offline_logged_in']) && $_COOKIE['kes_smart_offline_logged_in'] === '1') {
        return true;
    }
    
    return false;
}

function requireRole($allowed_roles) {
    if (!isLoggedIn()) {
        redirect('login.php');
    }
    
    // Simply update session activity without forcing timeout check
    updateSessionActivity();
    
    $user_role = $_SESSION['role'] ?? '';
    
    if (is_string($allowed_roles)) {
        $allowed_roles = [$allowed_roles];
    }
    
    if (!in_array($user_role, $allowed_roles)) {
        $_SESSION['error'] = 'Access denied. You do not have permission to access this page.';
        redirect('dashboard.php');
    }
}

function checkPageAccess($page_name) {
    if (!isLoggedIn()) {
        return false;
    }
    
    $user_role = $_SESSION['role'] ?? '';
    
    // Define page access rules
    $page_access = [
        'admin' => ['dashboard.php', 'students.php',  'teachers.php', 'parents.php','reports.php', 'users.php', 'sections.php', 'sms-config.php', 'profile.php', 'settings.php'],
        'teacher' => ['dashboard.php', 'qr-scanner.php', 'attendance.php', 'qr-code.php', 'students.php', 'sections.php', 'reports.php', 'profile.php'],
        'student' => ['dashboard.php', 'profile.php'],
        'parent' => ['dashboard.php', 'attendance.php', 'profile.php']
    ];
    
    return isset($page_access[$user_role]) && in_array($page_name, $page_access[$user_role]);
}

function getCurrentUser($pdo) {
    if (!isLoggedIn()) return false;
    
    // Always check if we have basic session data first
    if (!isset($_SESSION['user_id'])) return false;
    
    if ($GLOBALS['is_offline_mode']) {
        // For offline mode, return session data
        return [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'] ?? '',
            'full_name' => $_SESSION['full_name'] ?? 'Offline User',
            'role' => $_SESSION['role'] ?? '',
            'phone' => '',
            'status' => 'active',
            'offline_mode' => true,
            'section_id' => $_SESSION['section_id'] ?? null,
            'created_at' => '',
            'updated_at' => ''
        ];
    }
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // If database query fails, fall back to session data
        if (!$user) {
            return [
                'id' => $_SESSION['user_id'],
                'username' => $_SESSION['username'] ?? '',
                'full_name' => $_SESSION['full_name'] ?? 'User',
                'role' => $_SESSION['role'] ?? '',
                'phone' => '',
                'status' => 'active',
                'section_id' => $_SESSION['section_id'] ?? null,
                'created_at' => '',
                'updated_at' => ''
            ];
        }
        
        return $user;
    } catch(PDOException $e) {
        // In case of database error, return session data
        error_log("Error getting current user: " . $e->getMessage());
        return [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'] ?? '',
            'full_name' => $_SESSION['full_name'] ?? 'Unknown User',
            'role' => $_SESSION['role'] ?? '',
            'phone' => '',
            'status' => 'active',
            'section_id' => $_SESSION['section_id'] ?? null,
            'created_at' => '',
            'updated_at' => ''
        ];
    }
}

function hasRole($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

// QR Code generation helper
function generateStudentQR($student_id) {
    return base64_encode("TAC-QR-STUDENT-" . $student_id . "-" . date('Y'));
}

// Include SMS functions
require_once 'sms_functions.php';

// Include QR helper functions
if (file_exists(__DIR__ . '/qr_helpers.php')) {
    require_once 'qr_helpers.php';
}

// SMS Notification function
function sendSMSNotification($pdo, $phone_number, $message) {
    if ($GLOBALS['is_offline_mode']) {
        // Store in offline queue for later sending
        return ['success' => false, 'message' => 'Device is offline, message queued for later'];
    }
    
    $sms_config = getSMSConfig($pdo);
    if (!$sms_config || $sms_config['status'] != 'active') {
        return false;
    }

    $api_key = $sms_config['api_key'];
    if (empty($api_key)) {
        return false;
    }

    // Use the new IPROG SMS implementation
    $result = sendSMSUsingIPROG($phone_number, $message, $api_key);
    
    // Log the result in sms_logs table for compatibility
    try {
        $status = $result['success'] ? 'sent' : 'failed';
        $stmt = $pdo->prepare("INSERT INTO sms_logs (phone_number, message, response, status, sent_at) VALUES (?, ?, ?, ?, NOW())");
        $log_response = $result['message'];
        $stmt->execute([$phone_number, $message, $log_response, $status]);
    } catch(PDOException $e) {
        // Log error but don't fail the SMS send
        error_log('Failed to log SMS: ' . $e->getMessage());
    }
    
    return $result['success'];
}

// Auto-evaluation function
function evaluateAttendance($pdo, $student_id, $month = null, $year = null) {
    if ($GLOBALS['is_offline_mode']) {
        // Return placeholder data in offline mode
        return [
            'total_days' => 0,
            'present_days' => 0,
            'late_days' => 0,
            'absent_days' => 0,
            'attendance_rate' => 0,
            'evaluation' => 'Unavailable Offline'
        ];
    }
    
    if (!$month) $month = date('m');
    if (!$year) $year = date('Y');
    
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total_days,
                   SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_days,
                   SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_days,
                   SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_days
            FROM attendance 
            WHERE student_id = ? 
            AND MONTH(attendance_date) = ? 
            AND YEAR(attendance_date) = ?
        ");
        $stmt->execute([$student_id, $month, $year]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $attendance_rate = $result['total_days'] > 0 ? ($result['present_days'] / $result['total_days']) * 100 : 0;
        
        $evaluation = 'Poor';
        if ($attendance_rate >= 95) $evaluation = 'Excellent';
        elseif ($attendance_rate >= 85) $evaluation = 'Good';
        elseif ($attendance_rate >= 75) $evaluation = 'Fair';
        
        return [
            'total_days' => $result['total_days'],
            'present_days' => $result['present_days'],
            'late_days' => $result['late_days'],
            'absent_days' => $result['absent_days'],
            'attendance_rate' => round($attendance_rate, 2),
            'evaluation' => $evaluation
        ];
    } catch(PDOException $e) {
        error_log("Error evaluating attendance: " . $e->getMessage());
        return [
            'total_days' => 0,
            'present_days' => 0,
            'late_days' => 0,
            'absent_days' => 0,
            'attendance_rate' => 0,
            'evaluation' => 'Error'
        ];
    }
}

// Simple wrapper function for sendSMS (for backward compatibility)
function sendSMS($phone_number, $message, $pdo) {
    return sendSMSNotification($pdo, $phone_number, $message);
}

// Helper to check if we're in offline mode
function isOfflineMode() {
    return $GLOBALS['is_offline_mode'];
}
?>
