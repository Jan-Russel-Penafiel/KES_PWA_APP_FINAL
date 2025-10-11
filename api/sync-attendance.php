<?php
require_once '../config.php';
require_once '../sms_functions.php';

// Set response header to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode([
        'success' => false,
        'message' => 'Authentication required'
    ]);
    exit;
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Only POST requests are allowed'
    ]);
    exit;
}

// Get current user info
$current_user = getCurrentUser($pdo);
$user_role = $_SESSION['role'];

// Only teachers and admins can sync attendance
if (!in_array($user_role, ['teacher', 'admin'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Access denied. Only teachers and administrators can sync attendance.'
    ]);
    exit;
}

// Get JSON data from request
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

// Log sync attempt
error_log("Sync attendance request from user: {$current_user['username']} at " . date('Y-m-d H:i:s'));

// Check JSON parsing errors
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid JSON data: ' . json_last_error_msg()
    ]);
    exit;
}

// Check if we have offline data
if (!isset($data['offlineData']) || !is_array($data['offlineData']) || empty($data['offlineData'])) {
    echo json_encode([
        'success' => false,
        'message' => 'No offline attendance data provided'
    ]);
    exit;
}

// Validate batch size
if (count($data['offlineData']) > 100) {
    echo json_encode([
        'success' => false,
        'message' => 'Batch size too large. Maximum 100 records per request.'
    ]);
    exit;
}

// Process each offline attendance record
$results = [];
$success_count = 0;
$error_count = 0;

foreach ($data['offlineData'] as $record) {
    try {
        // Validate required fields
        if (!isset($record['student_id']) || empty($record['student_id'])) {
            throw new Exception('Student ID is required');
        }
        
        $student_id = $record['student_id'];
        $action = $record['action'] ?? 'scan';
        $timestamp = $record['timestamp'] ?? date('Y-m-d H:i:s');
        $location = $record['location'] ?? 'Unknown';
        $notes = $record['notes'] ?? '';
        
        // Parse timestamp
        $attendance_date = date('Y-m-d', strtotime($timestamp));
        $attendance_time = date('H:i:s', strtotime($timestamp));
        
        // Find student by ID or QR code
        $stmt = $pdo->prepare("SELECT id, username, full_name, lrn, section_id FROM users WHERE (id = ? OR qr_code = ?) AND role = 'student'");
        $stmt->execute([$student_id, $student_id]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$student) {
            throw new Exception('Student not found: ' . $student_id);
        }
        
        // Check if teacher has permission to scan this student
        if ($user_role == 'teacher') {
            $check_stmt = $pdo->prepare("SELECT 1 FROM users u JOIN sections s ON u.section_id = s.id WHERE u.id = ? AND s.teacher_id = ?");
            $check_stmt->execute([$student['id'], $current_user['id']]);
            if (!$check_stmt->fetch()) {
                throw new Exception('You can only record attendance for students in your section');
            }
        }
        
        // Define time boundaries
        $late_threshold = '07:15:00';  // 7:15 AM
        $absent_cutoff = '16:15:00';   // 4:15 PM
        
        // Check if attendance already recorded for this date
        $check_attendance = $pdo->prepare("SELECT id, status, time_in, time_out FROM attendance WHERE student_id = ? AND attendance_date = ?");
        $check_attendance->execute([$student['id'], $attendance_date]);
        $existing_attendance = $check_attendance->fetch(PDO::FETCH_ASSOC);
        
        // Determine status based on time
        if ($attendance_time <= $late_threshold) {
            $attendance_status = 'present';
        } else if ($attendance_time <= $absent_cutoff) {
            $attendance_status = 'late';
        } else {
            $attendance_status = 'absent';
        }
        
        // Process attendance record
        if ($existing_attendance) {
            // Update existing record
            if (!$existing_attendance['time_in']) {
                // No time_in yet
                $update_stmt = $pdo->prepare("UPDATE attendance SET status = ?, time_in = ?, teacher_id = ?, remarks = CONCAT(IFNULL(remarks, ''), ' | Offline sync: ', ?) WHERE id = ?");
                $update_stmt->execute([$attendance_status, $timestamp, $current_user['id'], $location . ($notes ? ' - ' . $notes : ''), $existing_attendance['id']]);
            } else if (!$existing_attendance['time_out'] && $attendance_time > strtotime($existing_attendance['time_in'])) {
                // Has time_in but no time_out yet
                $update_stmt = $pdo->prepare("UPDATE attendance SET time_out = ?, teacher_id = ?, remarks = CONCAT(IFNULL(remarks, ''), ' | Offline checkout: ', ?) WHERE id = ?");
                $update_stmt->execute([$timestamp, $current_user['id'], $location . ($notes ? ' - ' . $notes : ''), $existing_attendance['id']]);
            }
            
            $attendance_id = $existing_attendance['id'];
        } else {
            // Create new attendance record
            $insert_stmt = $pdo->prepare("INSERT INTO attendance (student_id, teacher_id, section_id, attendance_date, time_in, status, remarks, qr_scanned) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $insert_stmt->execute([
                $student['id'], 
                $current_user['id'], 
                $student['section_id'], 
                $attendance_date, 
                $timestamp, 
                $attendance_status, 
                'Offline sync: ' . $location . ($notes ? ' - ' . $notes : ''), 
                $action == 'scan' ? 1 : 0
            ]);
            $attendance_id = $pdo->lastInsertId();
        }
        
        // Send SMS notification if needed
        $current_date = date('F j, Y', strtotime($attendance_date));
        $current_time_formatted = date('g:i A', strtotime($timestamp));
        
        // Get student's section name
        $section_name = 'Unknown Section';
        if ($student['section_id']) {
            $section_stmt = $pdo->prepare("SELECT section_name FROM sections WHERE id = ?");
            $section_stmt->execute([$student['section_id']]);
            $section_result = $section_stmt->fetch(PDO::FETCH_ASSOC);
            if ($section_result) {
                $section_name = $section_result['section_name'];
            }
        }
        
        // Check if SMS notification already sent today for this student
        $sms_check = $pdo->prepare("
            SELECT COUNT(*) as sms_count 
            FROM sms_logs 
            WHERE phone_number IN (
                SELECT u.phone 
                FROM users u 
                JOIN student_parents sp ON u.id = sp.parent_id 
                WHERE sp.student_id = ? AND sp.is_primary = 1 AND u.phone IS NOT NULL
            ) 
            AND notification_type = 'attendance' 
            AND status = 'sent' 
            AND DATE(sent_at) = ?
        ");
        $sms_check->execute([$student['id'], $attendance_date]);
        $sms_already_sent = $sms_check->fetchColumn() > 0;
        
        $sms_result = ['success' => true, 'message' => 'SMS already sent today'];
        $is_checkout = isset($existing_attendance) && $existing_attendance['time_in'] && !$existing_attendance['time_out'];
        
        // Send SMS based on scan type
        if ($is_checkout) {
            // Checkout SMS - always send regardless of previous SMS
            if ($attendance_status == 'out') {
                $sms_message = "Hi! Your child {$student['full_name']} has left school early at {$current_time_formatted} on {$current_date}. Section: {$section_name}. - KES-SMART";
            } else {
                $sms_message = "Hi! Your child {$student['full_name']} has left school at {$current_time_formatted} on {$current_date}. Section: {$section_name}. - KES-SMART";
            }
            $sms_result = sendSMSNotificationToParent($student['id'], $sms_message, 'checkout');
        } elseif (!$sms_already_sent) {
            // Check-in SMS - only if not already sent today
            $status_text = ($attendance_status == 'late') ? 'arrived late at' : 'arrived at';
            $sms_message = "Hi! Your child {$student['full_name']} has {$status_text} school at {$current_time_formatted} on {$current_date}. Section: {$section_name}. - KES-SMART";
            $sms_result = sendSMSNotificationToParent($student['id'], $sms_message, 'attendance');
        }
        
        // Add to results
        $results[] = [
            'success' => true,
            'student_id' => $student['id'],
            'student_name' => $student['full_name'],
            'attendance_id' => $attendance_id,
            'attendance_date' => $attendance_date,
            'time' => date('g:i A', strtotime($timestamp)),
            'status' => $attendance_status,
            'sms_sent' => $sms_result['success'],
            'sms_message' => $is_checkout ? 
                ($sms_result['success'] ? 'Checkout SMS sent successfully' : $sms_result['message']) :
                ($sms_already_sent ? 'SMS notification already sent today' : $sms_result['message'])
        ];
        
        $success_count++;
    } catch (Exception $e) {
        $results[] = [
            'success' => false,
            'student_id' => $student_id ?? 'unknown',
            'error' => $e->getMessage()
        ];
        
        $error_count++;
    }
}

// Log sync completion
error_log("Sync completed: {$success_count} success, {$error_count} errors");

// Return response
echo json_encode([
    'success' => $success_count > 0,
    'message' => "Processed {$success_count} attendance records with {$error_count} errors",
    'total' => count($data['offlineData']),
    'success_count' => $success_count,
    'error_count' => $error_count,
    'results' => $results,
    'timestamp' => date('Y-m-d H:i:s'),
    'synced_by' => $current_user['username']
]);
exit; 