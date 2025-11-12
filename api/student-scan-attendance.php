<?php
// Start output buffering to prevent any accidental output
ob_start();

// Set proper content type
header('Content-Type: application/json');

// Disable error display to prevent HTML errors in JSON response
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once '../config.php';

// Check if SMS functions file exists before including
if (file_exists('../sms_functions.php')) {
    require_once '../sms_functions.php';
} else {
    // Define a fallback function if sms_functions.php doesn't exist
    function sendSMSNotificationToParent($student_id, $message, $notification_type = 'attendance') {
        return ['success' => false, 'message' => 'SMS service not available - functions file not found'];
    }
}

// Clear any output that might have been generated
ob_clean();

// Check if user is logged in as student
if (!isLoggedIn() || $_SESSION['role'] !== 'student') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Handle POST request for attendance recording
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'record_attendance') {
    
    try {
        // Clear any previous output
        ob_clean();
        
        $teacher_id = intval($_POST['teacher_id'] ?? 0);
        $subject_id = intval($_POST['subject_id'] ?? 0);
        $session_id = isset($_POST['attendance_session_id']) ? sanitize_input($_POST['attendance_session_id']) : '';
        $attendance_type = isset($_POST['attendance_type']) ? sanitize_input($_POST['attendance_type']) : '';
        
        // Validate attendance type
        if (!in_array($attendance_type, ['in', 'out'])) {
            throw new Exception('Invalid attendance type. QR code must specify either "in" or "out" type.');
        }
        
        // Get current student
        $student_id = $_SESSION['user_id'];
        
        // Check if database connection is available
        if ($GLOBALS['is_offline_mode'] || !$pdo) {
            throw new Exception('Database connection not available. Please try again later.');
        }
        
        $current_user = getCurrentUser($pdo);
        
        if (!$current_user) {
            throw new Exception('Student not found');
        }
        
        // Validate required parameters
        if (!$teacher_id || !$subject_id) {
            throw new Exception('Invalid QR code data - missing required information');
        }
        
        // Verify teacher exists and is active
        $teacher_check = $pdo->prepare("SELECT id, full_name FROM users WHERE id = ? AND role = 'teacher' AND status = 'active'");
        $teacher_check->execute([$teacher_id]);
        $teacher = $teacher_check->fetch(PDO::FETCH_ASSOC);
        
        if (!$teacher) {
            throw new Exception('Teacher not found or inactive');
        }
        
        // Verify subject exists and teacher is assigned
        $subject_check = $pdo->prepare("SELECT id, subject_name, subject_code, teacher_id FROM subjects WHERE id = ? AND status = 'active'");
        $subject_check->execute([$subject_id]);
        $subject = $subject_check->fetch(PDO::FETCH_ASSOC);
        
        if (!$subject) {
            throw new Exception('Subject not found or inactive');
        }
        
        if ($subject['teacher_id'] != $teacher_id) {
            throw new Exception('Teacher is not assigned to this subject');
        }
        
        // Check if student is enrolled in this subject
        $enrollment_check = null;
        try {
            $enrollment_check = $pdo->prepare("SELECT 1 FROM student_subjects WHERE student_id = ? AND subject_id = ? AND status = 'enrolled'");
            $enrollment_check->execute([$student_id, $subject_id]);
            
            if (!$enrollment_check->fetch()) {
                throw new Exception('You are not enrolled in this subject');
            }
        } catch (PDOException $e) {
            // If student_subjects table doesn't exist, skip enrollment check
            if (strpos($e->getMessage(), "doesn't exist") !== false || strpos($e->getMessage(), "Table") !== false) {
                error_log('student_subjects table not found, skipping enrollment check');
                // Continue without enrollment check
            } else {
                throw new Exception('Database error checking enrollment: ' . $e->getMessage());
            }
        }
        
        // Check if attendance already recorded today for this subject
        $today = date('Y-m-d');
        $current_time = date('H:i:s');
        $current_time_formatted = date('g:i A');
        
        // Define time boundaries
        $late_threshold = '07:15:00';  // 7:15 AM
        $absent_cutoff = '16:31:00';   // 4:31 PM
        
        $existing_attendance_check = $pdo->prepare("
            SELECT id, status, time_in, time_out 
            FROM attendance 
            WHERE student_id = ? AND subject_id = ? AND attendance_date = ?
        ");
        $existing_attendance_check->execute([$student_id, $subject_id, $today]);
        $existing_attendance = $existing_attendance_check->fetch(PDO::FETCH_ASSOC);
        
        $attendance_id = null;
        $is_checkout = false;
        
        if ($existing_attendance) {
            // Check attendance type against current status
            if ($attendance_type === 'in') {
                // Student is scanning TIME IN QR code
                if ($existing_attendance['time_in']) {
                    // Student already has time_in recorded
                    if ($existing_attendance['time_out']) {
                        // Student has both time_in and time_out - full day completed
                        $time_in = date('g:i A', strtotime($existing_attendance['time_in']));
                        $time_out = date('g:i A', strtotime($existing_attendance['time_out']));
                        throw new Exception("You have already completed attendance for {$subject['subject_name']} today. Check-in: {$time_in}, Check-out: {$time_out}. Cannot scan TIME IN QR code again.");
                    } else {
                        // Student has time_in but no time_out
                        $time_in = date('g:i A', strtotime($existing_attendance['time_in']));
                        throw new Exception("You are already checked in for {$subject['subject_name']} at {$time_in}. Please scan a TIME OUT QR code to check out.");
                    }
                } else {
                    // No time_in recorded yet, this is valid for TIME IN scan
                    if ($current_time > $absent_cutoff) {
                        throw new Exception('Check-in period has ended. Students cannot check in after 4:31 PM');
                    }
                    
                    // Determine status based on time
                    if ($current_time <= $late_threshold) {
                        $attendance_status = 'present';
                    } else {
                        $attendance_status = 'late';
                    }
                    
                    $update_stmt = $pdo->prepare("
                        UPDATE attendance 
                        SET status = ?, time_in = NOW(), teacher_id = ?,
                            remarks = 'Student self-scan attendance (TIME IN)'
                        WHERE id = ?
                    ");
                    $update_stmt->execute([$attendance_status, $teacher_id, $existing_attendance['id']]);
                    $attendance_id = $existing_attendance['id'];
                    $is_checkout = false;
                }
            } else {
                // Student is scanning TIME OUT QR code
                if (!$existing_attendance['time_in']) {
                    // Student hasn't checked in yet
                    throw new Exception("You must check in first before checking out for {$subject['subject_name']}. Please scan a TIME IN QR code first.");
                } elseif ($existing_attendance['time_out']) {
                    // Student has already checked out
                    $time_in = date('g:i A', strtotime($existing_attendance['time_in']));
                    $time_out = date('g:i A', strtotime($existing_attendance['time_out']));
                    throw new Exception("You have already checked out of {$subject['subject_name']} at {$time_out}. Check-in: {$time_in}, Check-out: {$time_out}.");
                } else {
                    // Valid checkout - student has time_in but no time_out
                    if ($current_time < $absent_cutoff) {
                        // Early checkout - mark as 'out'
                        $update_stmt = $pdo->prepare("
                            UPDATE attendance 
                            SET status = 'out', time_out = NOW(), 
                                remarks = CONCAT(IFNULL(remarks, ''), ' | Early checkout via student scan (TIME OUT)')
                            WHERE id = ?
                        ");
                        $update_stmt->execute([$existing_attendance['id']]);
                        $attendance_status = 'out';
                    } else {
                        // Normal checkout after 4:31 PM
                        $update_stmt = $pdo->prepare("
                            UPDATE attendance 
                            SET time_out = NOW(),
                                remarks = CONCAT(IFNULL(remarks, ''), ' | Checkout via student scan (TIME OUT)')
                            WHERE id = ?
                        ");
                        $update_stmt->execute([$existing_attendance['id']]);
                        $attendance_status = $existing_attendance['status']; // Keep original status
                    }
                    $attendance_id = $existing_attendance['id'];
                    $is_checkout = true;
                }
            }
        } else {
            // No existing attendance record for this subject
            if ($attendance_type === 'out') {
                throw new Exception("You cannot check out without checking in first for {$subject['subject_name']}. Please scan a TIME IN QR code first.");
            }
            
            // TIME IN scan for new attendance record
            if ($current_time > $absent_cutoff) {
                throw new Exception('Check-in period has ended. Students cannot check in after 4:31 PM');
            }
            
            // Determine status based on time
            if ($current_time <= $late_threshold) {
                $attendance_status = 'present';
            } else {
                $attendance_status = 'late';
            }
            
            // Create new attendance record (check-in only)
            $insert_stmt = $pdo->prepare("
                INSERT INTO attendance 
                (student_id, teacher_id, subject_id, attendance_date, time_in, status, remarks, qr_scanned) 
                VALUES (?, ?, ?, ?, NOW(), ?, 'Student self-scan attendance (TIME IN)', 1)
            ");
            $insert_stmt->execute([$student_id, $teacher_id, $subject_id, $today, $attendance_status]);
            $attendance_id = $pdo->lastInsertId();
            $is_checkout = false;
        }
        
        // Send SMS notification to parent (only for check-in or checkout)
        $current_date = date('F j, Y');
        $sms_result = ['success' => true, 'message' => 'SMS not configured'];
        $sms_already_sent = false;
        
        // Check if sendSMSNotificationToParent function exists
        if (function_exists('sendSMSNotificationToParent')) {
            // Send SMS based on scan type
            if ($is_checkout) {
                // Checkout SMS - always send regardless of previous SMS
                if ($attendance_status == 'out') {
                    $sms_message = "Hi! Your child {$current_user['full_name']} has left {$subject['subject_name']} class early at {$current_time_formatted} on {$current_date}. - KES-SMART";
                } else {
                    $sms_message = "Hi! Your child {$current_user['full_name']} has finished {$subject['subject_name']} class at {$current_time_formatted} on {$current_date}. - KES-SMART";
                }
                $sms_result = sendSMSNotificationToParent($student_id, $sms_message, 'checkout');
                
                // Log SMS result for debugging
                error_log("Checkout SMS result for student {$student_id}: " . json_encode($sms_result));
                
            } else {
                // Check-in SMS - only if not already sent today
                try {
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
                    $sms_check->execute([$student_id, $today]);
                    $sms_already_sent = $sms_check->fetchColumn() > 0;
                } catch (PDOException $e) {
                    // If sms_logs table doesn't exist, proceed to send SMS
                    error_log('SMS logs table check failed: ' . $e->getMessage());
                    $sms_already_sent = false;
                }
                
                if (!$sms_already_sent) {
                    $status_text = ($attendance_status == 'late') ? 'arrived late to' : 'arrived at';
                    $sms_message = "Hi! Your child {$current_user['full_name']} has {$status_text} {$subject['subject_name']} class at {$current_time_formatted} on {$current_date}. - KES-SMART";
                    $sms_result = sendSMSNotificationToParent($student_id, $sms_message, 'attendance');
                    
                    // Log SMS result for debugging
                    error_log("Check-in SMS result for student {$student_id}: " . json_encode($sms_result));
                } else {
                    $sms_result = ['success' => true, 'message' => 'SMS already sent today'];
                    error_log("SMS already sent today for student {$student_id}");
                }
            }
        } else {
            $sms_result = ['success' => false, 'message' => 'SMS service not available - function not found'];
            error_log('sendSMSNotificationToParent function not found');
        }
        
        // Log QR scan
        try {
            $scan_log_stmt = $pdo->prepare("
                INSERT INTO qr_scans (student_id, teacher_id, scan_time, location, device_info) 
                VALUES (?, ?, NOW(), 'Student Self-Scan', ?)
            ");
            $device_info = json_encode([
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                'session_id' => $session_id
            ]);
            $scan_log_stmt->execute([$student_id, $teacher_id, $device_info]);
        } catch (Exception $e) {
            // Log error but don't fail the attendance recording
            error_log('Failed to log QR scan: ' . $e->getMessage());
        }
        
        // Prepare success response
        $operation_message = '';
        $check_in_time = null;
        if ($is_checkout && isset($existing_attendance) && $existing_attendance && $existing_attendance['time_in']) {
            $check_in_time = date('g:i A', strtotime($existing_attendance['time_in']));
        }

        $attendance_action = $attendance_type === 'in' ? 'checked in' : 'checked out';
        $attendance_action_caps = $attendance_type === 'in' ? 'Check-in' : 'Check-out';
        
        if ($is_checkout) {
            $check_in_display = $check_in_time ? " (Originally checked in at {$check_in_time})" : "";
            if ($attendance_status === 'out') {
                $operation_message = "Student checked out early at {$current_time_formatted}{$check_in_display}. Status: Early Checkout";
            } else {
                $operation_message = "Student checked out at {$current_time_formatted}{$check_in_display}. Final status: " . ucfirst($attendance_status);
            }
        } else {
            $operation_message = "Student checked in at {$current_time_formatted}. Status: " . ucfirst($attendance_status);
        }
        
        $response = [
            'success' => true,
            'message' => $operation_message,
            'student_name' => $current_user['full_name'],
            'student_id' => $current_user['username'],
            'student_lrn' => $current_user['lrn'] ?? null,
            'section' => $section_name,
            'subject' => $subject['subject_name'],
            'time' => $current_time_formatted,
            'time_in' => $is_checkout ? ($check_in_time) : $current_time_formatted,
            'time_out' => $is_checkout ? $current_time_formatted : null,
            'date' => $current_date,
            'attendance_date' => $current_date,
            'attendance_id' => $attendance_id,
            'status' => $attendance_status,
            'attendance_type' => $attendance_type, // Include scanned attendance type
            'attendance_action' => $attendance_action_caps, // Human readable action
            'is_checkout' => $is_checkout,
            'scan_method' => 'Student Self-Scan',
            'sms_sent' => $sms_result['success'],
            'sms_message' => $is_checkout ? 
                ($sms_result['success'] ? 'Checkout SMS sent successfully' : $sms_result['message']) :
                ($sms_already_sent ? 'SMS notification already sent today' : $sms_result['message']),
            'sms_status' => $is_checkout ? 
                ($sms_result['success'] ? 'checkout_sent' : 'failed') :
                ($sms_already_sent ? 'already_sent' : ($sms_result['success'] ? 'sent' : 'failed')),
            'teacher_name' => $teacher['full_name'],
            'session_id' => $session_id
        ];
        
        // Clear any output and send JSON response
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
        
    } catch (Exception $e) {
        // Log the error for debugging
        error_log('Attendance recording error: ' . $e->getMessage());
        
        $response = [
            'success' => false,
            'message' => $e->getMessage(),
            'error_code' => 'attendance_error'
        ];
        
        // Clear any output and send JSON response
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    } catch (Error $e) {
        // Handle fatal errors
        error_log('Fatal error in attendance recording: ' . $e->getMessage());
        
        $response = [
            'success' => false,
            'message' => 'A system error occurred. Please try again.',
            'error_code' => 'system_error'
        ];
        
        // Clear any output and send JSON response
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
    
} else {
    // Invalid request method or action
    ob_clean();
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}
?>
