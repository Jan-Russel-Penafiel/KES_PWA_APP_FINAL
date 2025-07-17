<?php
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
    die("Database connection failed: " . $e->getMessage());
}

// Site Configuration
$site_name = "KES-SMART";
$site_description = "KES Student Monitoring Application with Real-time QR and SMS Notifications";

// Get SMS API configuration from database
function getSMSConfig($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM sms_config WHERE id = 1 LIMIT 1");
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        return false;
    }
}

// Session management
session_start();

// Helper functions
function sanitize_input($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function redirect($url) {
    header("Location: $url");
    exit();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireRole($allowed_roles) {
    if (!isLoggedIn()) {
        redirect('login.php');
    }
    
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
        'admin' => ['dashboard.php', 'qr-scanner.php', 'students.php', 'reports.php', 'users.php', 'sections.php', 'sms-config.php', 'profile.php', 'settings.php'],
        'teacher' => ['dashboard.php', 'qr-scanner.php', 'students.php', 'sections.php', 'reports.php', 'profile.php'],
        'student' => ['dashboard.php', 'attendance.php', 'profile.php'],
        'parent' => ['dashboard.php', 'attendance.php', 'profile.php']
    ];
    
    return isset($page_access[$user_role]) && in_array($page_name, $page_access[$user_role]);
}

function getCurrentUser($pdo) {
    if (!isLoggedIn()) return false;
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function hasRole($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

// QR Code generation helper
function generateStudentQR($student_id) {
    return base64_encode("KES-SMART-STUDENT-" . $student_id . "-" . date('Y'));
}

// Include SMS functions
require_once 'sms_functions.php';

// SMS Notification function
function sendSMSNotification($pdo, $phone_number, $message) {
    $sms_config = getSMSConfig($pdo);
    if (!$sms_config || $sms_config['status'] != 'active') {
        return false;
    }

    $api_key = $sms_config['api_key'];
    if (empty($api_key)) {
        return false;
    }

    // Use the new PhilSMS implementation
    $result = sendSMSUsingPhilSMS($phone_number, $message, $api_key);
    
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
    if (!$month) $month = date('m');
    if (!$year) $year = date('Y');
    
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
}

// Simple wrapper function for sendSMS (for backward compatibility)
function sendSMS($phone_number, $message, $pdo) {
    return sendSMSNotification($pdo, $phone_number, $message);
}
?>
