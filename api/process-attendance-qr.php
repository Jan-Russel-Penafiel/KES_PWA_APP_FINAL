<?php
require_once '../config.php';
require_once '../qr_helpers.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit;
}

try {
    // Get the raw POST data
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    if (empty($input['teacher_qr_data']) || empty($input['student_qr_data'])) {
        throw new Exception('Missing required QR code data');
    }
    
    $teacher_qr_data = $input['teacher_qr_data'];
    $student_qr_data = $input['student_qr_data'];
    
    // Validate the QR codes using helper function
    $validation_result = validateAttendanceQRScan($pdo, $teacher_qr_data, $student_qr_data);
    
    if (!$validation_result['success']) {
        throw new Exception($validation_result['message']);
    }
    
    $teacher = $validation_result['teacher'];
    $student = $validation_result['student'];
    $section = $validation_result['section'];
    $subject = $validation_result['subject'];
    $teacher_qr = $validation_result['teacher_qr'];
    $student_qr = $validation_result['student_qr'];
    
    // Check cooldown period
    if (!canStudentScan($pdo, $student['id'], $subject['id'])) {
        throw new Exception('Please wait before scanning again for this subject');
    }
    
    // Check if student is enrolled in the subject
    $enrollment_stmt = $pdo->prepare("SELECT 1 FROM student_subjects WHERE student_id = ? AND subject_id = ? AND status = 'enrolled'");
    $enrollment_stmt->execute([$student['id'], $subject['id']]);
    
    if (!$enrollment_stmt->fetch()) {
        // Auto-enroll student if not already enrolled
        $auto_enroll_stmt = $pdo->prepare("INSERT INTO student_subjects (student_id, subject_id, enrolled_date, status) VALUES (?, ?, CURDATE(), 'enrolled')");
        $auto_enroll_stmt->execute([$student['id'], $subject['id']]);
    }
    
    $today = date('Y-m-d');
    $current_time = date('H:i:s');
    
    // Check if attendance already exists for today for this subject
    $existing_stmt = $pdo->prepare("
        SELECT id, status, time_in 
        FROM attendance 
        WHERE student_id = ? AND attendance_date = ? AND subject_id = ?
    ");
    $existing_stmt->execute([$student['id'], $today, $subject['id']]);
    $existing_attendance = $existing_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing_attendance) {
        // If already marked as present, don't allow duplicate
        if ($existing_attendance['status'] === 'present') {
            // Log the duplicate scan attempt
            logQRScan($pdo, $student['id'], $teacher['id'], 'attendance', 'duplicate', 
                     $section['section_name'] . ' - ' . $subject['subject_name'], 
                     $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown Device');
            
            echo json_encode([
                'success' => false, 
                'message' => 'Student already marked present for this subject today',
                'attendance_data' => [
                    'student_name' => $student['full_name'],
                    'subject_name' => $subject['subject_name'],
                    'time_in' => $existing_attendance['time_in'],
                    'status' => 'already_present'
                ]
            ]);
            exit;
        }
        
        // Update existing attendance record
        $update_stmt = $pdo->prepare("
            UPDATE attendance 
            SET status = 'present', time_in = ?, teacher_id = ?, qr_scanned = 1, 
                attendance_source = 'qr_scan', scan_location = ?
            WHERE id = ?
        ");
        $scan_location = $section['section_name'] . ' - ' . $subject['subject_name'];
        $update_stmt->execute([$current_time, $teacher['id'], $scan_location, $existing_attendance['id']]);
        $action = 'updated';
    } else {
        // Create new attendance record
        $insert_stmt = $pdo->prepare("
            INSERT INTO attendance (student_id, teacher_id, section_id, subject_id, attendance_date, 
                                  time_in, status, qr_scanned, attendance_source, scan_location) 
            VALUES (?, ?, ?, ?, ?, ?, 'present', 1, 'qr_scan', ?)
        ");
        $scan_location = $section['section_name'] . ' - ' . $subject['subject_name'];
        $insert_stmt->execute([$student['id'], $teacher['id'], $section['section_id'], $subject['id'], 
                              $today, $current_time, $scan_location]);
        $action = 'created';
    }
    
    // Log the successful QR scan
    logQRScan($pdo, $student['id'], $teacher['id'], 'attendance', 'success', 
             $section['section_name'] . ' - ' . $subject['subject_name'], 
             $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown Device');
    
    // Send SMS notification to parent if enabled
    try {
        $settings_stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_name = 'auto_sms_notifications'");
        $settings_stmt->execute();
        $sms_enabled = $settings_stmt->fetchColumn();
        
        if ($sms_enabled) {
            // Get parent phone number
            $parent_stmt = $pdo->prepare("
                SELECT p.phone, p.full_name 
                FROM users p 
                JOIN student_parents sp ON p.id = sp.parent_id 
                WHERE sp.student_id = ? AND sp.is_primary = 1 AND p.status = 'active'
            ");
            $parent_stmt->execute([$student['id']]);
            $parent = $parent_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($parent && !empty($parent['phone'])) {
                $message = "Hi! Your child " . $student['full_name'] . " has been marked present for " . $subject['subject_name'] . " at " . date('g:i A') . " on " . date('F j, Y') . ". Section: " . $section['section_name'] . ". - KES-SMART";
                
                sendSMSNotification($pdo, $parent['phone'], $message);
            }
        }
    } catch (Exception $sms_error) {
        // Log SMS error but don't fail the attendance marking
        error_log("SMS notification error: " . $sms_error->getMessage());
    }
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Attendance marked successfully',
        'action' => $action,
        'attendance_data' => [
            'student_id' => $student['id'],
            'student_name' => $student['full_name'],
            'teacher_name' => $teacher['full_name'],
            'section_name' => $section['section_name'],
            'subject_name' => $subject['subject_name'],
            'time_in' => $current_time,
            'date' => $today,
            'status' => 'present'
        ]
    ]);
    
} catch (PDOException $e) {
    error_log("Database error in process-attendance-qr.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}
?>
