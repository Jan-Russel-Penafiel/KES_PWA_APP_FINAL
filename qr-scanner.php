<?php
require_once 'config.php';
require_once 'sms_functions.php';

// Set timezone to Philippines
date_default_timezone_set('Asia/Manila');

// Check if user is logged in and has permission
requireRole(['admin', 'teacher']);

$current_user = getCurrentUser($pdo);
$user_role = $_SESSION['role'];

// Only teachers and admins can scan QR codes
if (!in_array($user_role, ['teacher', 'admin'])) {
    $_SESSION['error'] = 'Access denied. Only teachers and administrators can scan QR codes.';
    redirect('dashboard.php');
}

// Handle QR code scan submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'scan_qr') {
    $qr_data = sanitize_input($_POST['qr_data']);
    $subject_id = intval($_POST['subject_id'] ?? 0);
    $scan_location = sanitize_input($_POST['scan_location'] ?? 'Main Gate');
    $scan_notes = sanitize_input($_POST['scan_notes'] ?? '');
    
    try {
        // Validate QR data format (should be student ID or username)
        if (empty($qr_data)) {
            throw new Exception('Invalid QR code data.');
        }
        
        // Validate subject selection
        if (!$subject_id) {
            throw new Exception('Please select a subject for attendance recording.');
        }
        
        // Find student by QR code
        $stmt = $pdo->prepare("SELECT id, username, full_name, lrn, section_id FROM users WHERE qr_code = ? AND role = 'student'");
        $stmt->execute([$qr_data]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$student) {
            throw new Exception('Student not found for this QR code.');
        }
        
        // Process attendance for the found student
        $result = processStudentAttendance($pdo, $student, $current_user, $user_role, $subject_id, $scan_location, $scan_notes, true);
        
        header('Content-Type: application/json');
        echo json_encode($result);
        exit;
        
    } catch (Exception $e) {
        $response = [
            'success' => false,
            'message' => $e->getMessage()
        ];
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
}

// Handle manual LRN input submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'scan_lrn') {
    $lrn = sanitize_input($_POST['lrn']);
    $subject_id = intval($_POST['subject_id'] ?? 0);
    $scan_location = sanitize_input($_POST['scan_location'] ?? 'Main Gate');
    $scan_notes = sanitize_input($_POST['scan_notes'] ?? '');
    
    try {
        // Validate LRN format (12 digits)
        if (empty($lrn)) {
            throw new Exception('LRN is required.');
        }
        
        if (!preg_match('/^\d{12}$/', $lrn)) {
            throw new Exception('LRN must be exactly 12 digits.');
        }
        
        // Validate subject selection
        if (!$subject_id) {
            throw new Exception('Please select a subject for attendance recording.');
        }
        
        // Find student by LRN
        $stmt = $pdo->prepare("SELECT id, username, full_name, lrn, section_id FROM users WHERE lrn = ? AND role = 'student' AND status = 'active'");
        $stmt->execute([$lrn]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$student) {
            throw new Exception('Student not found with LRN: ' . $lrn);
        }
        
        // Process attendance for the found student
        $result = processStudentAttendance($pdo, $student, $current_user, $user_role, $subject_id, $scan_location, $scan_notes, false);
        
        header('Content-Type: application/json');
        echo json_encode($result);
        exit;
        
    } catch (Exception $e) {
        $response = [
            'success' => false,
            'message' => $e->getMessage()
        ];
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
}

// Handle manual student selection submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'scan_manual') {
    $student_id = intval($_POST['student_id'] ?? 0);
    $subject_id = intval($_POST['subject_id'] ?? 0);
    $scan_location = sanitize_input($_POST['scan_location'] ?? 'Main Gate');
    $scan_notes = sanitize_input($_POST['scan_notes'] ?? '');
    
    try {
        // Validate student ID
        if (!$student_id) {
            throw new Exception('Student ID is required.');
        }
        
        // Validate subject selection
        if (!$subject_id) {
            throw new Exception('Please select a subject for attendance recording.');
        }
        
        // Find student by ID
        $stmt = $pdo->prepare("SELECT id, username, full_name, lrn, section_id FROM users WHERE id = ? AND role = 'student' AND status = 'active'");
        $stmt->execute([$student_id]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$student) {
            throw new Exception('Student not found or inactive.');
        }
        
        // Process attendance for the found student
        $result = processStudentAttendance($pdo, $student, $current_user, $user_role, $subject_id, $scan_location, $scan_notes, false);
        
        header('Content-Type: application/json');
        echo json_encode($result);
        exit;
        
    } catch (Exception $e) {
        $response = [
            'success' => false,
            'message' => $e->getMessage()
        ];
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
}

// Function to process student attendance (shared by QR and LRN)
function processStudentAttendance($pdo, $student, $current_user, $user_role, $subject_id, $scan_location, $scan_notes, $is_qr_scan) {
    // Validate subject exists and teacher has permission
    $subject_check = $pdo->prepare("SELECT id, subject_name, teacher_id FROM subjects WHERE id = ? AND status = 'active'");
    $subject_check->execute([$subject_id]);
    $subject = $subject_check->fetch(PDO::FETCH_ASSOC);
    
    if (!$subject) {
        throw new Exception('Selected subject not found or inactive.');
    }
    
    // Check if teacher has permission to record attendance for this subject
    if ($user_role == 'teacher') {
        if ($subject['teacher_id'] != $current_user['id']) {
            throw new Exception('You can only record attendance for subjects you teach.');
        }
        
        // Also check if student is enrolled in this subject
        $enrollment_check = $pdo->prepare("SELECT 1 FROM student_subjects WHERE student_id = ? AND subject_id = ? AND status = 'enrolled'");
        $enrollment_check->execute([$student['id'], $subject_id]);
        if (!$enrollment_check->fetch()) {
            throw new Exception('Student is not enrolled in this subject.');
        }
    }
    
    // Check if attendance already recorded for today for this subject
    $today = date('Y-m-d');
    $current_time = date('H:i:s');
    $current_time_formatted = date('g:i A');
    
    // Define time boundaries
    $late_threshold = '07:15:00';  // 7:15 AM
    $absent_cutoff = '16:30:00';   // 4:30 PM
    $checkin_start = '06:00:00';   // 6:00 AM - earliest check-in time
    
    // Check if current time is within allowed scanning hours
    if ($current_time < $checkin_start) {
        throw new Exception('Attendance scanning is not available before 6:00 AM.');
    }
    
    if ($current_time > $absent_cutoff) {
        throw new Exception('Attendance scanning is closed. School hours end at 4:30 PM. Students who haven\'t checked in will be marked as absent.');
    }
    
    $check_attendance = $pdo->prepare("SELECT id, status, time_in, time_out FROM attendance WHERE student_id = ? AND subject_id = ? AND attendance_date = ?");
    $check_attendance->execute([$student['id'], $subject_id, $today]);
    $existing_attendance = $check_attendance->fetch(PDO::FETCH_ASSOC);
    
    // Also check if there's a record without subject_id (old format) to handle migration
    $legacy_check = null;
    if (!$existing_attendance) {
        $legacy_stmt = $pdo->prepare("SELECT id, status, time_in, time_out, subject_id FROM attendance WHERE student_id = ? AND attendance_date = ? AND subject_id IS NULL");
        $legacy_stmt->execute([$student['id'], $today]);
        $legacy_check = $legacy_stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    $attendance_id = null;
    $is_checkout = false;
    
    if ($existing_attendance) {
        // Check if this is a checkout scan
        if ($existing_attendance['time_in'] && !$existing_attendance['time_out']) {
            // Student has checked in, now checking out
            if ($current_time < $absent_cutoff) {
                // Early checkout - mark as 'out'
                $update_stmt = $pdo->prepare("UPDATE attendance SET status = 'out', time_out = NOW(), teacher_id = ?, remarks = CONCAT(IFNULL(remarks, ''), ' | Early checkout: ', ?) WHERE id = ?");
                $update_stmt->execute([$current_user['id'], $scan_location . ($scan_notes ? ' - ' . $scan_notes : ''), $existing_attendance['id']]);
                $attendance_status = 'out';
            } else {
                // Normal checkout after 4:30 PM
                $update_stmt = $pdo->prepare("UPDATE attendance SET time_out = NOW(), teacher_id = ?, remarks = CONCAT(IFNULL(remarks, ''), ' | Checkout: ', ?) WHERE id = ?");
                $update_stmt->execute([$current_user['id'], $scan_location . ($scan_notes ? ' - ' . $scan_notes : ''), $existing_attendance['id']]);
                $attendance_status = $existing_attendance['status']; // Keep original status
            }
            $attendance_id = $existing_attendance['id'];
            $is_checkout = true;
        } elseif ($existing_attendance['time_out']) {
            // Student has already checked in and out today for this subject
            $student_name = $student['full_name'];
            $time_in = date('g:i A', strtotime($existing_attendance['time_in']));
            $time_out = date('g:i A', strtotime($existing_attendance['time_out']));
            $status = ucfirst($existing_attendance['status']);
            
            return [
                'success' => false,
                'message' => "Student {$student_name} has already checked in and out today for {$subject['subject_name']}. Check-in: {$time_in}, Check-out: {$time_out}",
                'student_id' => $student['id'],
                'student_name' => $student_name,
                'student_lrn' => $student['lrn'] ?? null,
                'subject_name' => $subject['subject_name'],
                'attendance_date' => $today,
                'time_in' => $time_in,
                'time_out' => $time_out,
                'status' => $status,
                'error_code' => 'already_checked_in'
            ];
        } else {
            // No time_in recorded yet, this shouldn't happen but handle it
            if ($current_time > $absent_cutoff) {
                throw new Exception('Attendance recording period has ended for the day.');
            }
            
            // Determine status based on time
            if ($current_time <= $late_threshold) {
                $attendance_status = 'present';
            } else {
                $attendance_status = 'late';
            }
            
            $update_stmt = $pdo->prepare("UPDATE attendance SET status = ?, time_in = NOW(), teacher_id = ?, remarks = ? WHERE id = ?");
            $update_stmt->execute([$attendance_status, $current_user['id'], $scan_location . ($scan_notes ? ' - ' . $scan_notes : ''), $existing_attendance['id']]);
            $attendance_id = $existing_attendance['id'];
        }
    } else {
        // Check if we have a legacy record without subject_id that we should update
        if ($legacy_check) {
            // Update the legacy record to include subject_id and new attendance data
            if ($current_time <= $late_threshold) {
                $attendance_status = 'present';
            } else {
                $attendance_status = 'late';
            }
            
            $update_legacy_stmt = $pdo->prepare("
                UPDATE attendance 
                SET subject_id = ?, status = ?, time_in = NOW(), teacher_id = ?, 
                    remarks = CONCAT(IFNULL(remarks, ''), IF(remarks IS NULL OR remarks = '', '', ' | '), ?), 
                    qr_scanned = ?
                WHERE id = ?
            ");
            $update_legacy_stmt->execute([
                $subject_id, 
                $attendance_status, 
                $current_user['id'], 
                $scan_location . ($scan_notes ? ' - ' . $scan_notes : ''), 
                $is_qr_scan ? 1 : 0,
                $legacy_check['id']
            ]);
            $attendance_id = $legacy_check['id'];
        } else {
            // No existing attendance record for this subject - create new one
            // Determine status based on time
            if ($current_time <= $late_threshold) {
                $attendance_status = 'present';
            } else {
                $attendance_status = 'late';
            }
            
            // Create new attendance record (check-in only)
            $insert_stmt = $pdo->prepare("INSERT INTO attendance (student_id, teacher_id, section_id, subject_id, attendance_date, time_in, status, remarks, qr_scanned, attendance_source) VALUES (?, ?, ?, ?, ?, NOW(), ?, ?, ?, 'qr_scan')");
            $insert_stmt->execute([
                $student['id'], 
                $current_user['id'], 
                $student['section_id'], 
                $subject_id, 
                $today, 
                $attendance_status, 
                $scan_location . ($scan_notes ? ' - ' . $scan_notes : ''), 
                $is_qr_scan ? 1 : 0
            ]);
            $attendance_id = $pdo->lastInsertId();
        }
    }
    
    // Send SMS notification to parent (only if not already sent today and not a checkout)
    $current_date = date('F j, Y');
    
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
    $sms_check->execute([$student['id'], $today]);
    $sms_already_sent = $sms_check->fetchColumn() > 0;
    
    $sms_result = ['success' => true, 'message' => 'SMS already sent today'];
    
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
    
    // Send SMS based on scan type
    if ($is_checkout) {
        // Checkout SMS - always send regardless of previous SMS
        if ($attendance_status == 'out') {
            $sms_message = "Hi! Your child {$student['full_name']} has left {$subject['subject_name']} class early at {$current_time_formatted} on {$current_date}. Section: {$section_name}. - KES-SMART";
        } else {
            $sms_message = "Hi! Your child {$student['full_name']} has left {$subject['subject_name']} class at {$current_time_formatted} on {$current_date}. Section: {$section_name}. - KES-SMART";
        }
        $sms_result = sendSMSNotificationToParent($student['id'], $sms_message, 'checkout');
    } elseif (!$sms_already_sent) {
        // Check-in SMS - only if not already sent today
        $status_text = ($attendance_status == 'late') ? 'arrived late to' : 'arrived at';
        $sms_message = "Hi! Your child {$student['full_name']} has {$status_text} {$subject['subject_name']} class at {$current_time_formatted} on {$current_date}. Section: {$section_name}. - KES-SMART";
        $sms_result = sendSMSNotificationToParent($student['id'], $sms_message, 'attendance');
    }
    
    // Prepare response
    $operation_message = '';
    $check_in_time = null;
    if ($is_checkout && $existing_attendance && $existing_attendance['time_in']) {
        $check_in_time = date('g:i A', strtotime($existing_attendance['time_in']));
    }
    
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
    
    return [
        'success' => true,
        'message' => $operation_message,
        'student_name' => $student['full_name'],
        'student_id' => $student['username'],
        'student_lrn' => $student['lrn'] ?? null,
        'section' => $section_name,
        'subject' => $subject['subject_name'],
        'time' => $current_time_formatted,
        'time_in' => $is_checkout ? ($existing_attendance['time_in'] ? date('g:i A', strtotime($existing_attendance['time_in'])) : null) : $current_time_formatted,
        'time_out' => $is_checkout ? $current_time_formatted : null,
        'date' => $current_date,
        'attendance_date' => $current_date,
        'attendance_id' => $attendance_id,
        'status' => $attendance_status,
        'is_checkout' => $is_checkout,
        'scan_method' => $is_qr_scan ? 'QR Code' : 'Manual LRN',
        'sms_sent' => $sms_result['success'],
        'sms_message' => $is_checkout ? 
            ($sms_result['success'] ? 'Checkout SMS sent successfully' : $sms_result['message']) :
            ($sms_already_sent ? 'SMS notification already sent today' : $sms_result['message']),
        'sms_status' => $is_checkout ? 
            ($sms_result['success'] ? 'checkout_sent' : 'failed') :
            ($sms_already_sent ? 'already_sent' : ($sms_result['success'] ? 'sent' : 'failed'))
    ];
}

// Handle attendance records request
if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['action']) && $_GET['action'] == 'get_attendance') {
    try {
        $today = date('Y-m-d');
        
        // Base query for attendance records
        $query = "
            SELECT 
                a.id,
                a.time_in,
                a.time_out,
                a.status,
                a.remarks,
                a.qr_scanned,
                u.username,
                u.full_name as student_name,
                s.section_name,
                t.full_name as teacher_name
            FROM attendance a
            JOIN users u ON a.student_id = u.id
            LEFT JOIN sections s ON a.section_id = s.id
            LEFT JOIN users t ON a.teacher_id = t.id
            WHERE a.attendance_date = ?
        ";
        
        $params = [$today];
        
        // Filter by teacher's section if user is a teacher
        if ($user_role == 'teacher') {
            $query .= " AND s.teacher_id = ?";
            $params[] = $current_user['id'];
        }
        
        $query .= " ORDER BY a.time_in DESC LIMIT 20";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format time_in and time_out to 12-hour format
        foreach ($records as &$record) {
            if (!empty($record['time_in'])) {
                $record['time_in'] = date('g:i A', strtotime($record['time_in']));
            }
            if (!empty($record['time_out'])) {
                $record['time_out'] = date('g:i A', strtotime($record['time_out']));
            }
        }
        
        $response = [
            'success' => true,
            'records' => $records,
            'total' => count($records),
            'date' => date('F j, Y')
        ];
        
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
        
    } catch (Exception $e) {
        $response = [
            'success' => false,
            'message' => $e->getMessage()
        ];
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
}

// Handle calendar attendance request
if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['action']) && $_GET['action'] == 'get_calendar_attendance') {
    try {
        $date = $_GET['date'] ?? date('Y-m-d');
        $month = $_GET['month'] ?? null;
        $year = $_GET['year'] ?? null;
        
        if ($month && $year) {
            // Get monthly summary
            $query = "
                SELECT 
                    a.attendance_date,
                    COUNT(*) as total_records,
                    COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_count,
                    COUNT(CASE WHEN a.status = 'late' THEN 1 END) as late_count,
                    COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as absent_count
                FROM attendance a
                JOIN users u ON a.student_id = u.id
                LEFT JOIN sections s ON a.section_id = s.id
                WHERE YEAR(a.attendance_date) = ? AND MONTH(a.attendance_date) = ?
            ";
            
            $params = [$year, $month];
            
            // Filter by teacher's section if user is a teacher
            if ($user_role == 'teacher') {
                $query .= " AND s.teacher_id = ?";
                $params[] = $current_user['id'];
            }
            
            $query .= " GROUP BY a.attendance_date ORDER BY a.attendance_date";
            
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $response = [
                'success' => true,
                'type' => 'monthly',
                'records' => $records,
                'month' => $month,
                'year' => $year
            ];
        } else {
            // Get specific date details
            $query = "
                SELECT 
                    a.id,
                    a.time_in,
                    a.time_out,
                    a.status,
                    a.remarks,
                    a.qr_scanned,
                    a.attendance_date,
                    u.username,
                    u.full_name as student_name,
                    s.section_name,
                    t.full_name as teacher_name
                FROM attendance a
                JOIN users u ON a.student_id = u.id
                LEFT JOIN sections s ON a.section_id = s.id
                LEFT JOIN users t ON a.teacher_id = t.id
                WHERE a.attendance_date = ?
            ";
            
            $params = [$date];
            
            // Filter by teacher's section if user is a teacher
            if ($user_role == 'teacher') {
                $query .= " AND s.teacher_id = ?";
                $params[] = $current_user['id'];
            }
            
            $query .= " ORDER BY a.time_in DESC";
            
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Format time_in and time_out to 12-hour format
            foreach ($records as &$record) {
                if (!empty($record['time_in'])) {
                    $record['time_in'] = date('g:i A', strtotime($record['time_in']));
                }
                if (!empty($record['time_out'])) {
                    $record['time_out'] = date('g:i A', strtotime($record['time_out']));
                }
            }
            
            $response = [
                'success' => true,
                'type' => 'daily',
                'records' => $records,
                'date' => $date,
                'total' => count($records)
            ];
        }
        
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
        
    } catch (Exception $e) {
        $response = [
            'success' => false,
            'message' => $e->getMessage()
        ];
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
}

$page_title = 'QR Code Scanner';
include 'header.php';

// Add data attributes for offline JS
echo '<script>document.body.dataset.teacherId = "' . $current_user['id'] . '"; document.body.dataset.teacherName = "' . addslashes($current_user['full_name']) . '";</script>';

// Get SMS configuration status
$sms_config = null;
try {
    $sms_config = $pdo->query("SELECT * FROM sms_config WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    // SMS config not available
}

// Get subjects for current teacher
$subjects = [];
try {
    if ($user_role == 'admin') {
        $subjects_stmt = $pdo->query("SELECT id, subject_name, subject_code, grade_level FROM subjects WHERE status = 'active' ORDER BY subject_name");
        $subjects = $subjects_stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($user_role == 'teacher') {
        $subjects_stmt = $pdo->prepare("SELECT id, subject_name, subject_code, grade_level FROM subjects WHERE teacher_id = ? AND status = 'active' ORDER BY subject_name");
        $subjects_stmt->execute([$current_user['id']]);
        $subjects = $subjects_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch(PDOException $e) {
    // Handle database error silently
    $subjects = [];
}
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 fw-bold text-primary">
                    <i class="fas fa-qrcode me-2"></i>QR Code Scanner
                </h1>
                <p class="text-muted mb-0">
                    Scan student QR codes or enter LRN for attendance
                </p>
            </div>
            <div class="text-end">
                <div class="btn-group">
                    <button id="startScanBtn" class="btn btn-primary">
                        <i class="fas fa-camera me-1"></i>
                        <span class="d-none d-sm-inline">Start Scanner</span>
                    </button>
                    <button id="stopScanBtn" class="btn btn-danger" style="display: none;">
                        <i class="fas fa-stop me-1"></i>
                        <span class="d-none d-sm-inline">Stop Scanner</span>
                    </button>
                    <button id="calendarBtn" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#calendarModal">
                        <i class="fas fa-calendar me-1"></i>
                        <span class="d-none d-sm-inline">Calendar</span>
                    </button>
                </div>
                <div class="small text-muted mt-1">
                    <div>Current Time: <strong><span class="current-time-display"><?php echo date('g:i A'); ?></span></strong></div>
                    <div>SMS Status: 
                    <span class="badge bg-<?php echo ($sms_config && $sms_config['status'] == 'active') ? 'success' : 'danger'; ?>">
                        <?php echo ($sms_config && $sms_config['status'] == 'active') ? 'Active' : 'Inactive'; ?>
                    </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Scanner Status -->
<div class="row g-3 mb-4">
    <div class="col-12">
        <!-- Time Status Alert -->
        <div class="alert" id="timeStatus">
            <i class="fas fa-clock me-2"></i>
            <strong id="timeStatusText">Checking time status...</strong>
        </div>
        
        <div class="alert alert-info" id="scannerStatus">
            <i class="fas fa-info-circle me-2"></i>
            <strong>Ready to scan:</strong> Select a subject below and click "Start Scanner" to begin scanning or use manual LRN entry.
        </div>
        <!-- Sync Status Bar (Hidden by default, shown when there are pending syncs) -->
        <div class="alert alert-warning d-none align-items-center justify-content-between" id="syncStatusBar" style="display: none;">
            <div>
                <i class="fas fa-cloud-upload-alt me-2"></i>
                <strong id="pendingSyncText">0 scans pending sync</strong>
            </div>
            <button type="button" class="btn btn-sm btn-primary" onclick="syncNow()" id="syncNowBtn">
                <i class="fas fa-sync me-1"></i>Sync Now
            </button>
        </div>
    </div>
</div>

<!-- Subject Selection -->
<div class="row g-3 mb-4">
    <div class="col-12">
        <div class="card bg-light border-primary">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0">
                    <i class="fas fa-book me-2"></i>Select Subject for Attendance
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($subjects)): ?>
                    <div class="alert alert-warning mb-0">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>No subjects assigned.</strong> 
                        <?php if ($user_role == 'teacher'): ?>
                            Please contact the administrator to assign subjects to your account.
                        <?php else: ?>
                            No active subjects found in the system.
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="row g-3">
                        <div class="col-12">
                            <label for="subject-select" class="form-label fw-semibold">Subject <span class="text-danger">*</span></label>
                            <select class="form-select form-select-lg" id="subject-select" name="subject_id" required>
                                <option value="">Choose a subject...</option>
                                <?php foreach ($subjects as $subject): ?>
                                    <option value="<?php echo $subject['id']; ?>">
                                        <?php echo htmlspecialchars($subject['subject_name']); ?> 
                                        (<?php echo htmlspecialchars($subject['subject_code']); ?>) - 
                                        Grade <?php echo htmlspecialchars($subject['grade_level']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">
                                <i class="fas fa-info-circle me-1"></i>
                                You must select a subject before recording attendance. Only students enrolled in the selected subject can be marked present.
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Manual Auto-Absent Trigger -->
<div class="row g-3 mb-4">
    <div class="col-12">
        <div class="card bg-light border-warning">
            <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="card-title mb-0">
                        <i class="fas fa-user-times me-2"></i>Manual Auto-Absent Trigger
                    </h6>
                </div>
                <small class="text-muted">For students who haven't signed attendance</small>
            </div>
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-12 col-md-8">
                        <div class="d-flex align-items-center mb-2">
                            <div class="me-3">
                                <i class="fas fa-clock text-warning" style="font-size: 1.2rem;"></i>
                            </div>
                            <div>
                                <p class="mb-1">
                                    <strong>Mark absent students who haven't checked in</strong>
                                </p>
                                <small class="text-muted">
                                    This will mark all students as absent if they don't have attendance records for today across all their subjects.
                                </small>
                            </div>
                        </div>
                        <div id="manualTriggerStatus" class="alert alert-info py-2 mb-0" style="display: none;">
                            <i class="fas fa-info-circle me-2"></i>
                            <span id="manualTriggerStatusText">Ready to trigger...</span>
                        </div>
                    </div>
                    <div class="col-12 col-md-4 text-md-end mt-2 mt-md-0">
                        <button 
                            type="button" 
                            id="manualAutoAbsentBtn" 
                            class="btn btn-warning btn-lg w-100"
                            onclick="showManualTriggerConfirmation()"
                        >
                            <i class="fas fa-user-times me-2"></i>
                            <span class="d-inline d-sm-none">Mark Absent</span>
                            <span class="d-none d-sm-inline">Mark Absent Students</span>
                        </button>
                    </div>
                </div>
                
                <!-- Progress indicator (hidden by default) -->
                <div id="manualTriggerProgress" class="mt-3" style="display: none;">
                    <div class="progress">
                        <div 
                            class="progress-bar progress-bar-striped progress-bar-animated" 
                            role="progressbar" 
                            style="width: 0%"
                            id="manualTriggerProgressBar"
                        ></div>
                    </div>
                    <small class="text-muted mt-1 d-block" id="manualTriggerProgressText">
                        Processing...
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Scanner Interface -->
<div class="row g-3">
    <div class="col-12 col-lg-8">
        <!-- Camera Selection Section -->
        <div class="card border-0 shadow-sm mb-3" id="cameraSelectionCard">
            <div class="card-header bg-light">
                <h6 class="card-title mb-0">
                    <i class="fas fa-video me-2"></i>Camera Selection
                </h6>
            </div>
            <div class="card-body p-3">
                <div class="row g-2 align-items-center">
                    <div class="col-12 col-sm-8">
                        <select class="form-select" id="cam-list">
                            <option value="">Loading cameras...</option>
                        </select>
                    </div>
                    <div class="col-12 col-sm-4">
                        <button class="btn btn-outline-primary btn-sm w-100" id="cam-switch">
                            <i class="fas fa-sync-alt me-1"></i>
                            <span class="d-none d-sm-inline">Switch </span>Camera
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-light">
                <h6 class="card-title mb-0">
                    <i class="fas fa-camera me-2"></i>QR Code Scanner
                </h6>
            </div>
            <div class="card-body p-3">
                <div class="alert alert-info mb-3">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Scanning Tips:</strong>
                    <ul class="mb-0 mt-2">
                        <li>Hold the QR code steady within the scanning area</li>
                        <li>Ensure good lighting for better scan quality</li>
                        <li>If scanning fails, try moving the QR code closer or further away</li>
                        <li>Use manual LRN entry below if QR scanning doesn't work</li>
                    </ul>
                </div>
                
                <div id="scan-region" class="position-relative mobile-optimized mb-3">
                    <!-- QR Scanner will be rendered here -->
                    <div class="text-center py-4">
                        <i class="fas fa-camera fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">Scanner Ready</h5>
                        <p class="text-muted small">Click "Start Scanner" to begin</p>
                    </div>
                </div>
                

            </div>
        </div>
                
        <!-- Manual LRN Input -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-light">
                <h6 class="card-title mb-0">
                    <i class="fas fa-keyboard me-2"></i>Manual Student Selection
                </h6>
            </div>
            <div class="card-body p-3">
                <form id="lrn-form">
                    <div class="mb-3">
                        <label for="student-select" class="form-label">Select Student</label>
                        <select class="form-select select2" id="student-select" style="width: 100%;">
                            <option value="">Search by name or LRN...</option>
                            <?php
                            // Fetch students from database when online
                            if (!isset($students_data)) {
                                $students_data = [];
                                try {
                                    $stmt = $pdo->prepare("SELECT id, username, full_name, lrn, section_id, qr_code FROM users WHERE role = 'student' AND status = 'active' ORDER BY full_name");
                                    $stmt->execute();
                                    $students_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                } catch (PDOException $e) {
                                    // Handle database error silently
                                }
                                
                                // Convert to JSON for offline use
                                echo "<script>const studentsData = " . json_encode($students_data) . "; window.studentsData = studentsData;</script>";
                            }
                            
                            // Display students in dropdown
                            foreach ($students_data as $student) {
                                echo '<option value="' . htmlspecialchars($student['lrn']) . '" data-id="' . $student['id'] . '">' . 
                                    htmlspecialchars($student['full_name']) . ' - LRN: ' . htmlspecialchars($student['lrn']) . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <div class="d-grid">
                        <button class="btn btn-primary" type="submit">
                            <i class="fas fa-user-check me-1"></i>
                            Record Attendance
                        </button>
                    </div>
                    <small class="text-muted d-block mt-2">
                        <i class="fas fa-info-circle me-1"></i>
                        Search for a student by name or LRN to record attendance
                    </small>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-12 col-lg-4">
        <!-- Scan Settings -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-light">
                <h6 class="card-title mb-0">
                    <i class="fas fa-cog me-2"></i>Scan Settings
                </h6>
            </div>
            <div class="card-body p-3">
                <div class="mb-3">
                    <label for="scan-location" class="form-label">Scan Location</label>
                    <select class="form-select" id="scan-location">
                        <option value="Main Gate">Main Gate</option>
                        <option value="Side Gate">Side Gate</option>
                        <option value="Classroom">Classroom</option>
                        <option value="Library">Library</option>
                        <option value="Cafeteria">Cafeteria</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label for="scan-notes" class="form-label">Notes (Optional)</label>
                    <textarea class="form-control" id="scan-notes" rows="2" placeholder="Additional notes..."></textarea>
                </div>
                
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="autoSMS" checked>
                    <label class="form-check-label" for="autoSMS">
                        Send SMS notification automatically
                    </label>
                </div>
                
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="playSound" checked>
                    <label class="form-check-label" for="playSound">
                        Play sound on successful scan
                    </label>
                </div>
            </div>
        </div>
        
        <!-- Recent Scans -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h6 class="card-title mb-0">
                    <i class="fas fa-history me-2"></i>Recent Scans
                </h6>
                <button class="btn btn-sm btn-outline-danger" onclick="clearRecentScans()">
                    <i class="fas fa-trash-alt"></i>
                </button>
            </div>
            <div class="card-body p-3">
                <div id="recentScans">
                    <div class="text-center text-muted py-3">
                        <i class="fas fa-qrcode fa-2x mb-2"></i>
                        <p class="mb-0">No recent scans</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Scan Results -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h6 class="card-title mb-0">
                    <i class="fas fa-clipboard-check me-2"></i>Scan Results
                </h6>
                <button class="btn btn-sm btn-outline-danger" onclick="clearScanResults()">
                    <i class="fas fa-trash-alt"></i>
                </button>
            </div>
            <div class="card-body p-3">
                <div id="scan-result">
                    <div class="text-center text-muted py-3">
                        <i class="fas fa-qrcode fa-2x mb-2"></i>
                        <p class="mb-0">No scan results yet</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Card Navigation -->

</div>

<style>
.fade-in {
    animation: fadeIn 0.3s ease-in;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.card {
    transition: transform 0.2s;
}

.card:hover {
    transform: translateY(-3px);
}

.card:active {
    transform: scale(0.98);
}

@media (max-width: 576px) {
    .profile-avatar {
        width: 35px !important;
        height: 35px !important;
    }
    
    .card-body {
        padding: 0.75rem !important;
    }
    
    .student-info {
        font-size: 0.8125rem;
    }
    
    h6 {
        font-size: 0.9rem;
    }
    
    .small {
        font-size: 0.75rem;
    }
    
    .btn-sm {
        padding: 0.25rem 0.5rem;
        font-size: 0.75rem;
    }
}
</style>

<!-- Bottom Navigation is handled by footer.php -->

<!-- Scan Result Modal -->
<div class="modal fade" id="scanResultModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-check-circle me-2"></i>Scan Result
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="scanResultContent">
                    <!-- Content will be populated by JavaScript -->
                </div>
            </div>
            <div class="modal-footer flex-column flex-sm-row">
                <button type="button" class="btn btn-secondary w-100 w-sm-auto mb-2 mb-sm-0" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary w-100 w-sm-auto" id="printResultBtn">
                    <i class="fas fa-print me-2"></i>Print Receipt
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Error Modal -->
<div class="modal fade" id="errorModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" id="errorModalHeader">
                <h5 class="modal-title text-danger" id="errorModalTitle">
                    <i class="fas fa-exclamation-triangle me-2" id="errorModalIcon"></i>Scan Error
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="errorContent">
                    <!-- Content will be populated by JavaScript -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary w-100 w-sm-auto" data-bs-dismiss="modal">Close</button>
                <a href="students.php" id="viewStudentRecordBtn" class="btn btn-primary w-100 w-sm-auto" style="display: none;">
                    <i class="fas fa-user me-1"></i> View Student Record
                </a>
            </div>
        </div>
    </div>
</div>

<!-- HTTPS/Camera Access Modal -->
<div class="modal fade" id="httpsWarningModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title text-dark">
                    <i class="fas fa-lock me-2"></i>Camera Access Requires HTTPS
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Camera access is only allowed over a secure HTTPS connection or on localhost.</strong><br>
                    <br>
                    <strong>For Development:</strong><br>
                    • Use <code>localhost</code> or <code>127.0.0.1</code> in your browser<br>
                    • Example: <code>http://localhost/smart/qr_scanner.php</code><br>
                    <br>
                    <strong>For Production:</strong><br>
                    • Set up HTTPS on your server<br>
                    • Use a valid SSL certificate<br>
                    <br>
                    <small>If you are the site administrator, see the <a href="setup-https.md" target="_blank">HTTPS setup guide</a> for instructions.</small>
                </div>
                <div class="d-grid gap-2 d-md-flex justify-content-center">
                    <button class="btn btn-primary flex-fill flex-md-grow-0" onclick="window.location.href='http://localhost<?php echo $_SERVER['REQUEST_URI']; ?>'">
                        <i class="fas fa-home me-2"></i>Try localhost
                    </button>
                    <a href="https://<?php echo $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']; ?>" class="btn btn-success flex-fill flex-md-grow-0">
                        <i class="fas fa-lock me-2"></i>Switch to HTTPS
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Calendar Modal -->
<div class="modal fade" id="calendarModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-fullscreen-md-down">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="fas fa-calendar-alt me-2"></i>Attendance Calendar
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <!-- Calendar Header -->
                <div class="calendar-header bg-light border-bottom">
                    <!-- Mobile Layout -->
                    <div class="d-block d-md-none p-3">
                        <!-- Month Navigation - Mobile -->
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <button id="prevMonth" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-chevron-left"></i>
                            </button>
                            <h6 id="currentMonthYear" class="mb-0 fw-bold text-center flex-grow-1">July 2025</h6>
                            <button id="nextMonth" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-chevron-right"></i>
                            </button>
                        </div>
                        
                        <!-- Controls - Mobile -->
                        <div class="row g-2">
                            <div class="col-6">
                                <button id="todayBtn" class="btn btn-primary btn-sm w-100">
                                    <i class="fas fa-calendar-day me-1"></i>Today
                                </button>
                            </div>
                            <div class="col-6">
                                <button class="btn btn-outline-secondary btn-sm w-100" type="button" data-bs-toggle="collapse" data-bs-target="#mobileSearchCollapse">
                                    <i class="fas fa-search me-1"></i>Search
                                </button>
                            </div>
                        </div>
                        
                        <!-- Collapsible Search - Mobile -->
                        <div class="collapse mt-3" id="mobileSearchCollapse">
                            <div class="input-group mobile-input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-search"></i>
                                </span>
                                <input type="text" id="studentSearch" class="form-control" placeholder="Search students..." style="font-size: 16px;">
                                <button class="btn btn-outline-secondary" type="button" onclick="document.getElementById('studentSearch').value = ''; filterAttendanceRecords('');">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Desktop Layout -->
                    <div class="d-none d-md-block p-3">
                        <div class="row align-items-center">
                            <div class="col-md-4">
                                <div class="d-flex align-items-center justify-content-start">
                                    <button id="prevMonthDesktop" class="btn btn-sm btn-outline-primary me-2">
                                        <i class="fas fa-chevron-left"></i>
                                    </button>
                                    <h6 id="currentMonthYearDesktop" class="mb-0 mx-2 text-nowrap">July 2025</h6>
                                    <button id="nextMonthDesktop" class="btn btn-sm btn-outline-primary ms-2">
                                        <i class="fas fa-chevron-right"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <button id="todayBtnDesktop" class="btn btn-sm btn-primary w-100">
                                    <i class="fas fa-calendar-day me-1"></i>Today
                                </button>
                            </div>
                            <div class="col-md-4">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">
                                        <i class="fas fa-search"></i>
                                    </span>
                                    <input type="text" id="studentSearchDesktop" class="form-control" placeholder="Search students...">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Calendar Grid -->
                <div class="calendar-container p-3">
                    <div class="calendar-grid">
                        <!-- Day headers -->
                        <div class="calendar-days-header d-none d-sm-grid">
                            <div class="day-header text-center fw-bold text-muted">Sun</div>
                            <div class="day-header text-center fw-bold text-muted">Mon</div>
                            <div class="day-header text-center fw-bold text-muted">Tue</div>
                            <div class="day-header text-center fw-bold text-muted">Wed</div>
                            <div class="day-header text-center fw-bold text-muted">Thu</div>
                            <div class="day-header text-center fw-bold text-muted">Fri</div>
                            <div class="day-header text-center fw-bold text-muted">Sat</div>
                        </div>
                        
                        <!-- Mobile day headers -->
                        <div class="calendar-days-header-mobile d-grid d-sm-none">
                            <div class="day-header text-center fw-bold text-muted">S</div>
                            <div class="day-header text-center fw-bold text-muted">M</div>
                            <div class="day-header text-center fw-bold text-muted">T</div>
                            <div class="day-header text-center fw-bold text-muted">W</div>
                            <div class="day-header text-center fw-bold text-muted">T</div>
                            <div class="day-header text-center fw-bold text-muted">F</div>
                            <div class="day-header text-center fw-bold text-muted">S</div>
                        </div>

                        <!-- Calendar days -->
                        <div id="calendarDays" class="calendar-days">
                            <!-- Days will be generated by JavaScript -->
                            <div class="text-center py-5 w-100" id="calendarLoading">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                                <p class="mt-2 text-muted">Loading calendar...</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Calendar Legend -->
                    <div class="calendar-legend mt-3 d-none d-sm-flex">
                        <div class="legend-item">
                            <div class="legend-color bg-success"></div>
                            <span>High Attendance (80%+)</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color bg-warning"></div>
                            <span>Medium Attendance (60-79%)</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color bg-danger"></div>
                            <span>Low Attendance (&lt;60%)</span>
                        </div>
                    </div>
                    
                    <!-- Mobile Legend -->
                    <div class="calendar-legend mt-3 d-flex d-sm-none flex-column">
                        <div class="d-flex justify-content-around">
                            <div class="legend-item">
                                <div class="legend-color bg-success"></div>
                                <span>High</span>
                            </div>
                            <div class="legend-item">
                                <div class="legend-color bg-warning"></div>
                                <span>Medium</span>
                            </div>
                            <div class="legend-item">
                                <div class="legend-color bg-danger"></div>
                                <span>Low</span>
                            </div>
                        </div>
                        <small class="text-muted text-center mt-1">Attendance Rates</small>
                    </div>
                </div>

                <!-- Selected Date Attendance -->
                <div class="selected-date-section border-top" style="display: none;" id="selectedDateSection">
                    <div class="p-3 bg-light border-bottom">
                        <h6 class="mb-0" id="selectedDateTitle">
                            <i class="fas fa-calendar-check me-2"></i>
                            <span id="selectedDateText">Select a date</span>
                        </h6>
                    </div>
                    <div class="p-3" style="max-height: 300px; overflow-y: auto;">
                        <div id="selectedDateAttendance">
                            <!-- Attendance records will be loaded here -->
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Toast Container -->
<!-- Toast container removed - notifications disabled -->

<!-- Include Google QR Scanner Library -->
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>

<!-- Include Enhanced Cache Manager (fixes IndexedDB issues) -->
<script src="assets/js/enhanced-cache-manager.js"></script>

<!-- Include Offline QR Scanner Enhancement -->
<script src="assets/js/offline-qr-scanner.js"></script>

<!-- QR Scanner Script -->
<script>
    let scanner = null;
    let cameras = [];
    let selectedCamera = 0;
    let isTransitioning = false; // State management for scanner transitions
    let scannerState = 'stopped'; // 'stopped', 'starting', 'running', 'stopping'
    
    // Function to play beep sound
    function playBeepSound() {
        // Check if AudioContext is supported
        if (typeof AudioContext !== 'undefined' || typeof webkitAudioContext !== 'undefined') {
            // Create audio context
            const AudioContextClass = window.AudioContext || window.webkitAudioContext;
            const audioContext = new AudioContextClass();
            
            // Create oscillator
            const oscillator = audioContext.createOscillator();
            const gainNode = audioContext.createGain();
            
            // Configure oscillator
            oscillator.type = 'sine';
            oscillator.frequency.value = 1000; // Frequency in Hz
            
            // Configure gain (volume)
            gainNode.gain.value = 0.5;
            
            // Connect nodes
            oscillator.connect(gainNode);
            gainNode.connect(audioContext.destination);
            
            // Start and stop the sound
            const now = audioContext.currentTime;
            oscillator.start(now);
            oscillator.stop(now + 0.2); // 200ms duration
            
            // Clean up
            setTimeout(() => {
                audioContext.close();
            }, 300);
        }
    }
    
    // Initialize QR scanner
    function initQRScanner() {
        try {
            const scanRegion = document.getElementById('scan-region');
            if (!scanRegion) {
                console.error('Scan region element not found');
                return;
            }
            
            const camList = document.getElementById('cam-list');
            const camSwitch = document.getElementById('cam-switch');
            
            if (!camList || !camSwitch) {
                console.error('One or more scanner control elements not found');
            }
            
            // Create HTML5 QR scanner element
            scanRegion.innerHTML = '<div id="qr-reader" style="width: 100%"></div>';
            
            // Initialize the scanner with HTML5QrCode library
            const html5QrCode = new Html5Qrcode("qr-reader");
            scanner = html5QrCode;
            
            // Initialize state
            isTransitioning = false;
            scannerState = 'stopped';
            
            console.log('Scanner initialized successfully');
            
            // Get available cameras
            Html5Qrcode.getCameras().then(devices => {
                cameras = devices;
                
                if (cameras.length > 0) {
                    // Populate camera list
                    if (camList) {
                        camList.innerHTML = '';
                        cameras.forEach((camera, i) => {
                            const option = document.createElement('option');
                            option.value = camera.id;
                            option.text = camera.label || `Camera ${i + 1}`;
                            camList.appendChild(option);
                        });
                    }
                    
                    // Show camera switch button if multiple cameras
                    if (cameras.length > 1 && camSwitch) {
                        camSwitch.classList.remove('d-none');
                    }
                    
                    // Don't auto-start scanner - wait for user to select subject and click start
                    console.log('Scanner initialized successfully. Waiting for user input.');
                } else {
                    // No cameras available
                    scanRegion.innerHTML = '<div class="alert alert-danger">No cameras found. Please allow camera access.</div>';
                }
            }).catch(err => {
                console.error('Error listing cameras:', err);
                scanRegion.innerHTML = '<div class="alert alert-danger">Error accessing camera: ' + err.message + '</div>';
            });
            
            // Camera switch button with debounce
            if (camSwitch) {
                let camSwitchTimeout;
                camSwitch.addEventListener('click', () => {
                    if (camSwitchTimeout) {
                        clearTimeout(camSwitchTimeout);
                    }
                    camSwitchTimeout = setTimeout(() => {
                        selectedCamera = (selectedCamera + 1) % cameras.length;
                        if (camList) {
                            camList.value = cameras[selectedCamera].id;
                        }
                        startScanner();
                    }, 300); // 300ms debounce
                });
            }
            
            // Camera selection change with debounce
            if (camList) {
                let camListTimeout;
                camList.addEventListener('change', () => {
                    if (camListTimeout) {
                        clearTimeout(camListTimeout);
                    }
                    camListTimeout = setTimeout(() => {
                        const cameraId = camList.value;
                        selectedCamera = cameras.findIndex(cam => cam.id === cameraId);
                        if (selectedCamera === -1) selectedCamera = 0;
                        startScanner();
                    }, 300); // 300ms debounce
                });
            }
            
            // Start/Stop scanner buttons
            const startScanBtn = document.getElementById('startScanBtn');
            const stopScanBtn = document.getElementById('stopScanBtn');
            const scannerStatus = document.getElementById('scannerStatus');
            
            if (startScanBtn && stopScanBtn && scannerStatus) {
                startScanBtn.addEventListener('click', function() {
                    const subjectSelect = document.getElementById('subject-select');
                    if (!subjectSelect || !subjectSelect.value) {
                        showToast('Please select a subject before starting the scanner', 'warning');
                        return;
                    }
                    
                    this.style.display = 'none';
                    stopScanBtn.style.display = 'inline-block';
                    scannerStatus.innerHTML = '<i class="fas fa-camera me-2"></i><strong>Scanner active:</strong> Point your camera at a student QR code.';
                    scannerStatus.className = 'alert alert-success';
                    
                    // Add scanning animation
                    const scanRegion = document.getElementById('scan-region');
                    if (scanRegion) {
                        scanRegion.classList.add('scanning');
                    }
                    
                    startScanner();
                });
                
                stopScanBtn.addEventListener('click', function() {
                    this.style.display = 'none';
                    startScanBtn.style.display = 'inline-block';
                    scannerStatus.innerHTML = '<i class="fas fa-info-circle me-2"></i><strong>Scanner stopped:</strong> Select a subject and click "Start Scanner" to begin scanning.';
                    scannerStatus.className = 'alert alert-info';
                    
                    // Remove scanning animation
                    const scanRegion = document.getElementById('scan-region');
                    if (scanRegion) {
                        scanRegion.classList.remove('scanning');
                    }
                    
                    stopScanner();
                });
        }
        
        // Add subject selection change handler
        const subjectSelect = document.getElementById('subject-select');
        if (subjectSelect) {
            subjectSelect.addEventListener('change', function() {
                const scannerStatus = document.getElementById('scanner-status');
                if (scannerStatus) {
                    if (this.value) {
                        scannerStatus.innerHTML = '<i class="fas fa-check-circle me-2"></i><strong>Subject selected:</strong> ' + this.options[this.selectedIndex].text + '. Click "Start Scanner" to begin scanning.';
                        scannerStatus.className = 'alert alert-info';
                    } else {
                        scannerStatus.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i><strong>No subject selected:</strong> Please select a subject before starting the scanner.';
                        scannerStatus.className = 'alert alert-warning';
                    }
                }
            });
        }
    } catch (error) {
            console.error('Error initializing QR scanner:', error);
            showToast('Error initializing QR scanner: ' + error.message, 'danger');
    }
}

// Start scanner with selected camera
function startScanner() {
        if (!scanner) {
            console.error('Scanner not initialized');
            return;
        }
        
        // Prevent multiple transitions with timeout protection
        if (isTransitioning) {
            // Check if we've been transitioning too long (stuck state)
            const now = Date.now();
            if (!window.lastTransitionStart) {
                window.lastTransitionStart = now;
            }
            
            const transitionDuration = now - window.lastTransitionStart;
            if (transitionDuration > 10000) { // 10 seconds timeout
                console.warn('⚠ Scanner transition timeout detected, forcing reset...');
                isTransitioning = false;
                scannerState = 'stopped';
                window.lastTransitionStart = null;
            } else {
                console.log('Scanner is already transitioning, ignoring request (duration: ' + Math.round(transitionDuration/1000) + 's)');
                return;
            }
        }
        
        // If already running and working properly, don't restart
        if (scannerState === 'running' && scanner.isScanning) {
            console.log('Scanner is already running and working properly');
            return;
        }
        
        // Reset transition timeout
        window.lastTransitionStart = Date.now();
        
        try {
            isTransitioning = true;
            scannerState = 'starting';
            
            // Stop any existing scan first
            if (scanner.isScanning) {
                console.log('Stopping existing scanner before restart...');
                scannerState = 'stopping';
                scanner.stop()
                    .then(() => {
                        console.log('Scanner stopped successfully, starting new scan...');
                        startNewScan();
                    })
                    .catch(err => {
                        console.error('Error stopping scanner:', err);
                        // Try to start anyway after a delay
                        setTimeout(() => {
                            startNewScan();
                        }, 500);
                    });
            } else {
                startNewScan();
            }
        } catch (error) {
            console.error('Error starting scanner:', error);
            showToast('Error starting scanner: ' + error.message, 'danger');
            isTransitioning = false;
            scannerState = 'stopped';
            window.lastTransitionStart = null;
        }
    }
    
    // Stop scanner with proper state management
    function stopScanner() {
        if (!scanner) {
            console.log('Scanner not initialized');
            return Promise.resolve();
        }
        
        // Prevent multiple stop attempts
        if (isTransitioning && scannerState === 'stopping') {
            console.log('Scanner is already stopping, ignoring request');
            return Promise.resolve();
        }
        
        // If already stopped, don't try to stop again
        if (scannerState === 'stopped' && !scanner.isScanning) {
            console.log('Scanner is already stopped');
            return Promise.resolve();
        }
        
        try {
            isTransitioning = true;
            scannerState = 'stopping';
            
            if (scanner.isScanning) {
                console.log('Stopping scanner...');
                return scanner.stop()
                    .then(() => {
                        console.log('Scanner stopped successfully');
                        isTransitioning = false;
                        scannerState = 'stopped';
                    })
                    .catch(err => {
                        console.error('Error stopping scanner:', err);
                        isTransitioning = false;
                        scannerState = 'stopped'; // Assume stopped even if error
                    });
            } else {
                console.log('Scanner was not scanning');
                isTransitioning = false;
                scannerState = 'stopped';
                return Promise.resolve();
            }
        } catch (error) {
            console.error('Exception in stopScanner:', error);
            isTransitioning = false;
            scannerState = 'stopped';
            return Promise.resolve();
        }
    }
    
    // Start a new scan with the selected camera
    function startNewScan() {
        if (!cameras || cameras.length === 0 || selectedCamera >= cameras.length) {
            console.error('No cameras available or invalid camera selected');
            return;
        }
        
        const cameraId = cameras[selectedCamera].id;
        const config = {
            fps: 10,
            qrbox: { width: 300, height: 300 },
            aspectRatio: 1.0,
            disableFlip: false,
            rememberLastUsedCamera: true,
            supportedScanTypes: [
                Html5QrcodeScanType.SCAN_TYPE_CAMERA
            ],
            experimentalFeatures: {
                useBarCodeDetectorIfSupported: true
            },
            videoConstraints: {
                facingMode: "environment",
                advanced: [{
                    focusMode: "continuous",
                    torch: false
                }]
            },
            formatsToSupport: [
                Html5QrcodeSupportedFormats.QR_CODE,
                Html5QrcodeSupportedFormats.AZTEC,
                Html5QrcodeSupportedFormats.CODABAR,
                Html5QrcodeSupportedFormats.CODE_39,
                Html5QrcodeSupportedFormats.CODE_93,
                Html5QrcodeSupportedFormats.CODE_128,
                Html5QrcodeSupportedFormats.DATA_MATRIX,
                Html5QrcodeSupportedFormats.MAXICODE,
                Html5QrcodeSupportedFormats.ITF,
                Html5QrcodeSupportedFormats.EAN_13,
                Html5QrcodeSupportedFormats.EAN_8,
                Html5QrcodeSupportedFormats.PDF_417,
                Html5QrcodeSupportedFormats.RSS_14,
                Html5QrcodeSupportedFormats.RSS_EXPANDED,
                Html5QrcodeSupportedFormats.UPC_A,
                Html5QrcodeSupportedFormats.UPC_E,
                Html5QrcodeSupportedFormats.UPC_EAN_EXTENSION
            ]
        };
        
        scanner.start(
            cameraId, 
            config,
            handleScanResult,
            handleScanError
        )
        .then(() => {
            console.log('Scanner started successfully');
            isTransitioning = false;
            scannerState = 'running';
            window.lastTransitionStart = null;
        })
        .catch(err => {
            console.error('Error starting camera with advanced config:', err);
            
            // Try with simplified configuration as fallback
            const simpleConfig = {
                fps: 10,
                qrbox: { width: 250, height: 250 },
                aspectRatio: 1.0,
                rememberLastUsedCamera: true
            };
            
            return scanner.start(
                cameraId, 
                simpleConfig,
                handleScanResult,
                handleScanError
            );
        })
        .then(() => {
            console.log('Scanner started successfully with simple config');
            isTransitioning = false;
            scannerState = 'running';
            window.lastTransitionStart = null;
        })
        .catch(err => {
            console.error('Error starting camera with simple config:', err);
            showToast('Error starting camera: ' + err.message, 'danger');
            isTransitioning = false;
            scannerState = 'stopped';
            window.lastTransitionStart = null;
            
            // Show manual LRN entry as alternative
            const alertHtml = `
                <div class="alert alert-warning">
                    <h6><i class="fas fa-exclamation-triangle me-2"></i>Camera Error</h6>
                    <p class="mb-2">Unable to start camera: ${err.message}</p>
                    <p class="mb-0">Please use the manual student selection below instead.</p>
                </div>
            `;
            document.getElementById('scan-region').innerHTML = alertHtml;
        });
    }
    
    // Handle scan error
    function handleScanError(err) {
        // Only log detailed errors, don't spam the console for every failed scan attempt
        if (err && typeof err === 'string' && !err.includes('No MultiFormat Readers')) {
            console.warn('QR scan error:', err);
        }
        
        // For specific errors, we might want to take action
        if (err && typeof err === 'string') {
            if (err.includes('No MultiFormat Readers')) {
                // This is a common error when no valid QR code is detected
                // Don't show to user as it happens frequently during scanning
                return;
            } else if (err.includes('Camera not accessible')) {
                showToast('Camera access lost. Please refresh the page.', 'warning');
            } else if (err.includes('Permission denied')) {
                showToast('Camera permission denied. Please allow camera access.', 'danger');
            }
        }
    }
    
    // Handle scan result
    function handleScanResult(qrCodeMessage) {
        // Debug: Log the QR code content
        console.log('QR Code detected:', qrCodeMessage);
        
        // Validate QR code content
        if (!qrCodeMessage || qrCodeMessage.trim() === '') {
            console.warn('Empty QR code detected');
            showToast('Empty QR code detected. Please try again.', 'warning');
            return;
        }
        
        // Stop scanner temporarily
        if (scanner && scanner.isScanning) {
            scanner.pause();
        }
        
        // Play success sound
        playBeepSound();
        
        // Get scan location and notes
        const scanLocationEl = document.getElementById('scan-location');
        const scanNotesEl = document.getElementById('scan-notes');
        
        const scanLocation = scanLocationEl ? scanLocationEl.value : 'Main Gate';
        const scanNotes = scanNotesEl ? scanNotesEl.value : '';
        
        // Check time restrictions before processing
        const currentTime = new Date();
        const currentHour = currentTime.getHours();
        const currentMinute = currentTime.getMinutes();
        const currentTimeString = currentTime.toLocaleTimeString([], {hour: '2-digit', minute: '2-digit', hour12: true});
        
        // Check if before 6:00 AM
        if (currentHour < 6) {
            showToast('Attendance scanning is not available before 6:00 AM.', 'warning');
            setTimeout(() => {
                if (scanner && scanner.isPaused) {
                    scanner.resume();
                }
            }, 2000);
            return;
        }
        
        // Check if after 4:30 PM
        if (currentHour > 16 || (currentHour === 16 && currentMinute > 30)) {
            showToast('Attendance scanning is closed. School hours end at 4:30 PM. Students who haven\'t checked in will be marked as absent.', 'danger');
            setTimeout(() => {
                if (scanner && scanner.isPaused) {
                    scanner.resume();
                }
            }, 2000);
            return;
        }
        
        // Show time status message
        let timeStatus = '';
        if (currentHour < 7 || (currentHour === 7 && currentMinute <= 15)) {
            timeStatus = 'Student will be marked as PRESENT';
        } else {
            timeStatus = 'Student will be marked as LATE';
        }
        
        console.log(`Current time: ${currentTimeString} - ${timeStatus}`);
        
        // Check if we're online or offline
        if (!navigator.onLine) {
            // Handle offline scanning with enhanced function
            if (typeof handleEnhancedOfflineQrScan === 'function') {
                handleEnhancedOfflineQrScan(qrCodeMessage, scanLocation, scanNotes);
                // Resume scanning after delay
                setTimeout(() => {
                    if (scanner && scanner.isPaused) {
                        scanner.resume();
                    }
                }, 2000);
            } else {
                console.error('Enhanced offline scan function not available');
                showToast('Offline scan not available', 'danger');
            }
        return;
    }
    
        // Process QR scan online
        const subjectSelect = document.getElementById('subject-select');
        if (!subjectSelect || !subjectSelect.value) {
            showScanResult({
                success: false,
                message: 'Please select a subject before scanning QR codes.'
            }, 'danger');
            // Resume scanning after delay
            setTimeout(() => {
                if (scanner && scanner.isPaused) {
                    scanner.resume();
                }
            }, 2000);
            return;
        }
        
        const formData = new FormData();
        formData.append('action', 'scan_qr');
        formData.append('qr_data', qrCodeMessage);
        formData.append('subject_id', subjectSelect.value);
        formData.append('scan_location', scanLocation);
        formData.append('scan_notes', scanNotes);
        
        fetch('qr-scanner.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Show success notification
                showScanResult(data, 'success');
            } else {
                // Show error notification
                showScanResult(data, 'danger');
            }
            
            // Resume scanning after delay
            setTimeout(() => {
                if (scanner && scanner.isPaused) {
                    scanner.resume();
                }
            }, 2000);
        })
        .catch(error => {
            console.error('Error processing QR code:', error);
            showToast('Error processing QR code. Please try again.', 'danger');
            
            // Resume scanning after delay
            setTimeout(() => {
                if (scanner && scanner.isPaused) {
                    scanner.resume();
                }
            }, 2000);
        });
    }
    
    // Show scan result
    function showScanResult(data, type) {
        try {
            const resultContainer = document.getElementById('scan-result');
            if (!resultContainer) {
                console.error('Scan result container not found');
                // Still show toast notification
                showToast(data.message || 'Scan processed', type);
        return;
    }
    
            // Create result card
            let cardClass = 'border-success';
            let statusClass = 'bg-success';
            let icon = 'fa-check-circle';
            let headerText = 'Success';
            
            // Handle different scan types
            if (data.is_checkout) {
                cardClass = 'border-info';
                statusClass = 'bg-info';
                icon = 'fa-sign-out-alt';
                headerText = data.status === 'out' ? 'Early Checkout' : 'Checkout';
            } else if (type === 'danger') {
                cardClass = 'border-danger';
                statusClass = 'bg-danger';
                icon = 'fa-times-circle';
                headerText = 'Error';
            } else if (type === 'warning' || data.offline) {
                cardClass = 'border-warning';
                statusClass = 'bg-warning';
                icon = 'fa-exclamation-triangle';
                headerText = 'Warning';
            }
            
            let resultHtml = `
                <div class="card mb-3 ${cardClass} shadow-sm">
                    <div class="card-header ${statusClass} text-white">
                        <i class="fas ${icon} me-2"></i>
                        ${headerText}
                        ${data.offline ? ' (Offline Mode)' : ''}
            </div>
                    <div class="card-body">
                        <h5 class="card-title">${data.message || 'Scan processed'}</h5>
            `;
        
            if (data.success || data.offline) {
                // Add student details for successful scans
                let timeDisplay = '';
                if (data.is_checkout && data.time_in && data.time_out) {
                    timeDisplay = `
                                <p class="mb-1"><strong>Check-in:</strong> ${data.time_in}</p>
                                <p class="mb-1"><strong>Checkout:</strong> ${data.time_out}</p>
                    `;
                } else if (data.time_out) {
                    timeDisplay = `<p class="mb-1"><strong>Checkout Time:</strong> ${data.time_out}</p>`;
                } else {
                    const displayTime = data.time_in || data.time || new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit', hour12: true});
                    timeDisplay = `<p class="mb-1"><strong>Check-in Time:</strong> ${displayTime}</p>`;
                }
                
                // Format date properly - if it's in YYYY-MM-DD format, convert it
                let formattedDate = data.attendance_date || new Date().toLocaleDateString();
                if (formattedDate.match(/^\d{4}-\d{2}-\d{2}$/)) {
                    const dateObj = new Date(formattedDate + 'T00:00:00');
                    formattedDate = dateObj.toLocaleDateString('en-US', { 
                        year: 'numeric', 
                        month: 'long', 
                        day: 'numeric' 
                    });
                }
                
                resultHtml += `
                        <div class="row mt-3">
            <div class="col-6">
                                <p class="mb-1"><strong>Student:</strong> ${data.student_name || 'Unknown'}</p>
                                <p class="mb-1"><strong>Subject:</strong> ${data.subject || 'Unknown Subject'}</p>
                                <p class="mb-1"><strong>Date:</strong> ${formattedDate}</p>
            </div>
            <div class="col-6">
                                ${timeDisplay}
                                <p class="mb-1"><strong>Status:</strong> ${data.status ? data.status.charAt(0).toUpperCase() + data.status.slice(1) : 'Pending'}</p>
            </div>
        </div>
                `;
            }
            
            resultHtml += `
            </div>
                    <div class="card-footer text-muted">
                        <small>${new Date().toLocaleString([], {hour12: true})}</small>
        </div>
        </div>
    `;
    
            // Add to result container
            resultContainer.innerHTML = resultHtml + resultContainer.innerHTML;
            
            // Limit number of results shown
            const resultCards = resultContainer.querySelectorAll('.card');
            if (resultCards.length > 5) {
                resultContainer.removeChild(resultCards[resultCards.length - 1]);
            }
            
            // Save to localStorage
            saveScanResultToStorage(data, type);
            
            // Show toast notification
            showToast(data.message || 'Scan processed', type);
            
            // Also update recent scans section
            updateRecentScans(data, type);
        } catch (error) {
            console.error('Error showing scan result:', error);
            // Fallback to simple toast
            showToast(data?.message || 'Scan processed', type);
        }
    }
    
    // Save scan result to localStorage
    function saveScanResultToStorage(data, type) {
        try {
            // Get existing results from localStorage
            let scanResults = JSON.parse(localStorage.getItem('scanResults') || '[]');
            
            // Add new result at the beginning
            scanResults.unshift({
                data: {
                    student_name: data.student_name || 'Student',
                    student_id: data.student_id || '',
                    time_in: data.time_in || new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit', hour12: true}),
                    attendance_date: data.attendance_date || new Date().toLocaleDateString(),
                    status: data.status || 'Pending',
                    message: data.message || 'Scan processed',
                    success: data.success || false,
                    offline: data.offline || false,
                    timestamp: new Date().toISOString()
                },
                type: type
            });
            
            // Keep only the last 10 results
            if (scanResults.length > 10) {
                scanResults = scanResults.slice(0, 10);
            }
            
            // Save back to localStorage
            localStorage.setItem('scanResults', JSON.stringify(scanResults));
        } catch (error) {
            console.error('Error saving scan result to storage:', error);
        }
    }
    
    // Load scan results from localStorage
    function loadScanResultsFromStorage() {
        try {
            const resultContainer = document.getElementById('scan-result');
            if (!resultContainer) return;
            
            // Get results from localStorage
            const savedResults = JSON.parse(localStorage.getItem('scanResults') || '[]');
            
            if (savedResults.length > 0) {
                // Clear placeholder
                resultContainer.innerHTML = '';
                
                // Add saved results
                savedResults.forEach(result => {
                    const data = result.data;
                    const type = result.type;
                    
                    // Create result card
                    let cardClass = 'border-success';
                    let statusClass = 'bg-success';
                    let icon = 'fa-check-circle';
                    
                    if (type === 'danger') {
                        cardClass = 'border-danger';
                        statusClass = 'bg-danger';
                        icon = 'fa-times-circle';
                    } else if (type === 'warning' || data.offline) {
                        cardClass = 'border-warning';
                        statusClass = 'bg-warning';
                        icon = 'fa-exclamation-triangle';
                    }
                    
                    let resultHtml = `
                        <div class="card mb-3 ${cardClass} shadow-sm">
                            <div class="card-header ${statusClass} text-white">
                                <i class="fas ${icon} me-2"></i>
                                ${data.success ? 'Success' : 'Error'}
                                ${data.offline ? ' (Offline Mode)' : ''}
            </div>
                            <div class="card-body">
                                <h5 class="card-title">${data.message}</h5>
                    `;
                    
                    if (data.success || data.offline) {
                        // Add student details for successful scans
                        // Format date properly - if it's in YYYY-MM-DD format, convert it
                        let formattedDate = data.attendance_date;
                        if (formattedDate && formattedDate.match(/^\d{4}-\d{2}-\d{2}$/)) {
                            const dateObj = new Date(formattedDate + 'T00:00:00');
                            formattedDate = dateObj.toLocaleDateString('en-US', { 
                                year: 'numeric', 
                                month: 'long', 
                                day: 'numeric' 
                            });
                        }
                        
                        resultHtml += `
                                <div class="row mt-3">
                                    <div class="col-6">
                                        <p class="mb-1"><strong>Student:</strong> ${data.student_name || 'Unknown'}</p>
                                        <p class="mb-1"><strong>Date:</strong> ${formattedDate}</p>
                    </div>
                                    <div class="col-6">
                                        <p class="mb-1"><strong>Time:</strong> ${data.time_in}</p>
                                        <p class="mb-1"><strong>Status:</strong> ${data.status.charAt(0).toUpperCase() + data.status.slice(1)}</p>
                </div>
                    </div>
                        `;
                    }
                    
                    resultHtml += `
                            </div>
                            <div class="card-footer text-muted">
                                <small>${new Date(data.timestamp).toLocaleString([], {hour12: true})}</small>
                </div>
            </div>
        `;
                    
                    // Add to result container
                    resultContainer.innerHTML += resultHtml;
                });
        }
    } catch (error) {
            console.error('Error loading scan results from storage:', error);
        }
    }
    
    // Clear scan results
    function clearScanResults() {
        try {
            // Clear from localStorage
            localStorage.removeItem('scanResults');
            
            // Clear from UI
            const resultContainer = document.getElementById('scan-result');
            if (resultContainer) {
                resultContainer.innerHTML = `
            <div class="text-center text-muted py-3">
                        <i class="fas fa-qrcode fa-2x mb-2"></i>
                        <p class="mb-0">No scan results yet</p>
            </div>
        `;
            }
            
            showToast('Scan results cleared', 'info');
        } catch (error) {
            console.error('Error clearing scan results:', error);
        }
    }
    
    // Update recent scans section
    function updateRecentScans(data, type) {
        try {
            const recentScans = document.getElementById('recentScans');
            if (!recentScans) return;
            
            // Create a recent scan item
            const scanItem = document.createElement('div');
            scanItem.className = `recent-scan-item border-start border-4 border-${type === 'danger' ? 'danger' : data.success ? 'success' : 'warning'} ps-2 mb-2`;
            
            // Compose extra info
            const lrn = data.student_lrn || data.lrn || '';
            const attendanceDate = data.attendance_date || new Date().toLocaleDateString();
            const scanLocation = data.location || data.scan_location || '';
            const offlineTag = data.offline ? '<span class="badge bg-secondary ms-2">Offline</span>' : '';
            
            scanItem.innerHTML = `
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <strong>${data.student_name || 'Student'}</strong> ${offlineTag}
                        <div class="text-muted small mb-1">${attendanceDate} &bull; ${data.time_in || new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit', hour12: true})}</div>
                        ${lrn ? `<div class='text-muted small'>LRN: <span class='fw-semibold'>${lrn}</span></div>` : ''}
                        ${scanLocation ? `<div class='text-muted small'>Location: <span class='fw-semibold'>${scanLocation}</span></div>` : ''}
                    </div>
                    <span class="badge bg-${type === 'danger' ? 'danger' : data.success ? 'success' : 'warning'} align-self-start">
                        ${data.status ? data.status.charAt(0).toUpperCase() + data.status.slice(1) : 'Pending'}
                    </span>
                </div>
            `;
            
            // Add to recent scans container
            if (recentScans.querySelector('.text-muted.py-3')) {
                // Remove placeholder
                recentScans.innerHTML = '';
            }
            
            // Add at the top
            recentScans.insertBefore(scanItem, recentScans.firstChild);
            
            // Limit number of recent scans shown
            const scanItems = recentScans.querySelectorAll('.recent-scan-item');
            if (scanItems.length > 5) {
                recentScans.removeChild(scanItems[scanItems.length - 1]);
            }
            
            // Save to localStorage
            saveRecentScanToStorage(data, type);
        } catch (error) {
            console.error('Error updating recent scans:', error);
        }
    }
    
    // Save recent scan to localStorage
    function saveRecentScanToStorage(data, type) {
        try {
            // Get existing scans from localStorage
            let recentScans = JSON.parse(localStorage.getItem('recentScans') || '[]');
            
            // Add new scan at the beginning
            recentScans.unshift({
                data: {
                    student_name: data.student_name || 'Student',
                    student_id: data.student_id || '',
                    time_in: data.time_in || new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit', hour12: true}),
                    status: data.status || 'Pending',
                    success: data.success || false,
                    offline: data.offline || false,
                    timestamp: new Date().toISOString()
                },
                type: type
            });
            
            // Keep only the last 10 scans
            if (recentScans.length > 10) {
                recentScans = recentScans.slice(0, 10);
            }
            
            // Save back to localStorage
            localStorage.setItem('recentScans', JSON.stringify(recentScans));
        } catch (error) {
            console.error('Error saving recent scan to storage:', error);
        }
    }
    
    // Load recent scans from localStorage
    function loadRecentScansFromStorage() {
        try {
            const recentScans = document.getElementById('recentScans');
            if (!recentScans) return;
            
            // Get scans from localStorage
            const savedScans = JSON.parse(localStorage.getItem('recentScans') || '[]');
            
            if (savedScans.length > 0) {
                // Clear placeholder
                recentScans.innerHTML = '';
                
                // Add saved scans
                savedScans.forEach(scan => {
                    const d = scan.data;
                    const type = scan.type;
                    const lrn = d.student_lrn || d.lrn || '';
                    const attendanceDate = d.attendance_date || new Date().toLocaleDateString();
                    const scanLocation = d.location || d.scan_location || '';
                    const offlineTag = d.offline ? '<span class="badge bg-secondary ms-2">Offline</span>' : '';
                    
                    const scanItem = document.createElement('div');
                    scanItem.className = `recent-scan-item border-start border-4 border-${type === 'danger' ? 'danger' : d.success ? 'success' : 'warning'} ps-2 mb-2`;
                    scanItem.innerHTML = `
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <strong>${d.student_name || 'Student'}</strong> ${offlineTag}
                                <div class="text-muted small mb-1">${attendanceDate} &bull; ${d.time_in}</div>
                                ${lrn ? `<div class='text-muted small'>LRN: <span class='fw-semibold'>${lrn}</span></div>` : ''}
                                ${scanLocation ? `<div class='text-muted small'>Location: <span class='fw-semibold'>${scanLocation}</span></div>` : ''}
                            </div>
                            <span class="badge bg-${type === 'danger' ? 'danger' : d.success ? 'success' : 'warning'} align-self-start">
                                ${d.status ? d.status.charAt(0).toUpperCase() + d.status.slice(1) : 'Pending'}
                            </span>
                        </div>
                    `;
                    recentScans.appendChild(scanItem);
                });
            }
        } catch (error) {
            console.error('Error loading recent scans from storage:', error);
        }
    }
    
    // Clear recent scans
    function clearRecentScans() {
        try {
            // Clear from localStorage
            localStorage.removeItem('recentScans');
            
            // Clear from UI
            const recentScans = document.getElementById('recentScans');
            if (recentScans) {
                recentScans.innerHTML = `
        <div class="text-center text-muted py-3">
                        <i class="fas fa-qrcode fa-2x mb-2"></i>
                        <p class="mb-0">No recent scans</p>
        </div>
    `;
            }
            
            showToast('Recent scan history cleared', 'info');
    } catch (error) {
            console.error('Error clearing recent scans:', error);
        }
    }
    
    // Toast notifications disabled - messages logged to console instead
    function showToast(message, type = 'info') {
        console.log(`[${type.toUpperCase()}] ${message}`);
    }
    
    // Initialize when DOM is loaded
    document.addEventListener('DOMContentLoaded', () => {
        // Initialize QR scanner
        initQRScanner();
        
        // Show camera selection card
        document.getElementById('cameraSelectionCard').style.display = 'block';
        
        // Load recent scans from localStorage
        loadRecentScansFromStorage();
        
        // Load scan results from localStorage
        loadScanResultsFromStorage();
        
        // Initialize Select2 for student dropdown
        initStudentSelect();
        
        // Check online/offline status
        updateOfflineStatus();
        
        // Initialize time status
        updateTimeStatus();
        
        // Update current time display
        updateCurrentTimeDisplay();
        
        // Update time status every minute
        setInterval(updateTimeStatus, 60000);
        
        // Update current time display every second
        setInterval(updateCurrentTimeDisplay, 1000);
        
        // Listen for online/offline events
        window.addEventListener('online', updateOfflineStatus);
        window.addEventListener('offline', updateOfflineStatus);
        
        // Handle manual LRN form submission
        document.getElementById('lrn-form').addEventListener('submit', function(event) {
            event.preventDefault();
            
            const subjectSelect = document.getElementById('subject-select');
            const studentSelect = $('#student-select');
            const selectedOption = studentSelect.find('option:selected');
            const lrn = studentSelect.val();
            const studentId = selectedOption.data('id');
            const scanLocation = document.getElementById('scan-location').value || 'Main Gate';
            const scanNotes = document.getElementById('scan-notes').value || '';
            
            if (!subjectSelect || !subjectSelect.value) {
                showToast('Please select a subject before recording attendance', 'warning');
                return;
            }
            
            if (!lrn || !studentId) {
                showToast('Please select a student', 'warning');
                return;
            }
            
            // Check time restrictions before processing (same as server-side)
            const currentTime = new Date();
            const currentHour = currentTime.getHours();
            const currentMinute = currentTime.getMinutes();
            const currentTimeString = currentTime.toLocaleTimeString([], {hour: '2-digit', minute: '2-digit', hour12: true});
            
            // Check if before 6:00 AM
            if (currentHour < 6) {
                showToast('Attendance recording is not available before 6:00 AM.', 'warning');
                return;
            }
            
            // Check if after 4:30 PM (16:30)
            if (currentHour > 16 || (currentHour === 16 && currentMinute > 30)) {
                showToast('Attendance recording is closed. School hours end at 4:30 PM. Students who haven\'t checked in will be marked as absent.', 'danger');
                return;
            }
            
            // Show time status message
            let timeStatus = '';
            if (currentHour < 7 || (currentHour === 7 && currentMinute <= 15)) {
                timeStatus = 'Student will be marked as PRESENT';
            } else {
                timeStatus = 'Student will be marked as LATE';
            }
            
            console.log(`[Manual Selection] Current time: ${currentTimeString} (${currentHour}:${currentMinute}) - ${timeStatus}`);
            console.log(`[Manual Selection] Time restrictions check: hour=${currentHour}, minute=${currentMinute}, restricted=${currentHour < 6 || currentHour > 16 || (currentHour === 16 && currentMinute > 15)}`);
            console.log(`[Manual Selection] Selected student ID: ${studentId}, LRN: ${lrn}, Subject: ${subjectSelect.value}`);
            
            // Check if we're online or offline
            if (!navigator.onLine) {
                // Handle offline using enhanced function
                if (typeof handleEnhancedOfflineManualSelection === 'function') {
                    handleEnhancedOfflineManualSelection(lrn, scanLocation, scanNotes);
                } else {
                    console.error('Enhanced offline function not available');
                    showToast('Offline attendance not available', 'danger');
                }
                return;
            }
            
            // Use manual student selection with student ID for better accuracy
            const formData = new FormData();
            formData.append('action', 'scan_manual');
            formData.append('student_id', studentId);
            formData.append('subject_id', subjectSelect.value);
            formData.append('scan_location', scanLocation);
            formData.append('scan_notes', scanNotes);
            
            fetch('qr-scanner.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success notification
                    showScanResult(data, 'success');
                    // Reset the select
                    studentSelect.val('').trigger('change');
                } else {
                    // Show error notification
                    showScanResult(data, 'danger');
                }
            })
            .catch(error => {
                console.error('Error processing manual student selection:', error);
                showToast('Error processing student selection. Please try again.', 'danger');
            });
        });
    });
    
    // Initialize Select2 dropdown with offline support
    function initStudentSelect() {
        // Initialize Select2
        $('#student-select').select2({
            theme: 'bootstrap-5',
            width: '100%',
            placeholder: 'Search by name or LRN...',
            allowClear: true,
            templateResult: formatStudentOption,
            templateSelection: formatStudentSelection
        });
        
        // Check if we're offline and need to load data from cache
        if (!navigator.onLine && typeof studentsData !== 'undefined') {
            // We already have the data from the initial page load
            console.log('Using cached student data for offline mode');
        } else if (!navigator.onLine && typeof studentsData === 'undefined') {
            // Try to load from localStorage if we're offline and don't have data
            try {
                const cachedData = localStorage.getItem('studentsData');
                if (cachedData) {
                    window.studentsData = JSON.parse(cachedData);
                    populateStudentSelectFromCache();
                } else {
                    showToast('No cached student data available for offline use', 'warning');
                }
            } catch (error) {
                console.error('Error loading cached student data:', error);
                showToast('Error loading cached student data', 'danger');
            }
        } else {
            // We're online, cache the data for offline use
            if (typeof studentsData !== 'undefined') {
                try {
                    localStorage.setItem('studentsData', JSON.stringify(studentsData));
                } catch (error) {
                    console.error('Error caching student data:', error);
                }
            }
        }
    }
    
    // Format student option for Select2
    function formatStudentOption(student) {
        if (!student.id) {
            return student.text;
        }
        
        // Extract name and LRN from the option text
        const parts = student.text.split(' - LRN: ');
        const name = parts[0];
        const lrn = parts[1] || '';
        
        // Create a formatted option with name and LRN
        const $option = $(
            '<div class="d-flex justify-content-between align-items-center">' +
                '<div>' +
                    '<div class="fw-bold">' + name + '</div>' +
                    '<div class="text-muted small">LRN: ' + lrn + '</div>' +
                '</div>' +
                '<div class="ms-3">' +
                    '<span class="badge bg-primary">Student</span>' +
                '</div>' +
            '</div>'
        );
        
        return $option;
    }
    
    // Format student selection for Select2
    function formatStudentSelection(student) {
        if (!student.id) {
            return student.text;
        }
        
        // Extract name and LRN from the option text
        const parts = student.text.split(' - LRN: ');
        const name = parts[0];
        const lrn = parts[1] || '';
        
        return name + ' (' + lrn + ')';
    }
    
    // Populate student select from cached data
    function populateStudentSelectFromCache() {
        if (!window.studentsData || !Array.isArray(window.studentsData)) {
            return;
        }
        
        const select = document.getElementById('student-select');
        if (!select) return;
        
        // Clear existing options except the placeholder
        while (select.options.length > 1) {
            select.remove(1);
        }
        
        // Add options from cached data
        window.studentsData.forEach(student => {
            const option = document.createElement('option');
            option.value = student.lrn;
            option.dataset.id = student.id;
            option.textContent = student.full_name + ' - LRN: ' + student.lrn;
            select.appendChild(option);
        });
        
        // Refresh Select2
        $('#student-select').trigger('change');
    }
    
    // Calendar functionality
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize calendar variables
        let currentDate = new Date();
        let currentMonth = currentDate.getMonth();
        let currentYear = currentDate.getFullYear();
        let selectedDate = null;
        let monthAttendanceData = {};
        let allAttendanceRecords = [];
        
        // Calendar button click handler
        document.getElementById('calendarBtn').addEventListener('click', function() {
            // Initialize calendar when modal is opened
            generateCalendar(currentMonth, currentYear);
            fetchMonthlyAttendance(currentMonth + 1, currentYear);
        });
        
        // Calendar navigation buttons
        document.getElementById('prevMonth').addEventListener('click', function() {
            navigateMonth(-1);
        });
        
        document.getElementById('nextMonth').addEventListener('click', function() {
            navigateMonth(1);
        });
        
        document.getElementById('prevMonthDesktop').addEventListener('click', function() {
            navigateMonth(-1);
        });
        
        document.getElementById('nextMonthDesktop').addEventListener('click', function() {
            navigateMonth(1);
        });
        
        // Today button
        document.getElementById('todayBtn').addEventListener('click', function() {
            const today = new Date();
            currentMonth = today.getMonth();
            currentYear = today.getFullYear();
            generateCalendar(currentMonth, currentYear);
            fetchMonthlyAttendance(currentMonth + 1, currentYear);
        });
        
        document.getElementById('todayBtnDesktop').addEventListener('click', function() {
            const today = new Date();
            currentMonth = today.getMonth();
            currentYear = today.getFullYear();
            generateCalendar(currentMonth, currentYear);
            fetchMonthlyAttendance(currentMonth + 1, currentYear);
        });
        
        // Search functionality
        document.getElementById('studentSearch').addEventListener('input', function() {
            filterAttendanceRecords(this.value.toLowerCase());
        });
        
        document.getElementById('studentSearchDesktop').addEventListener('input', function() {
            filterAttendanceRecords(this.value.toLowerCase());
        });
        
        // Generate calendar for a given month and year
        function generateCalendar(month, year) {
            const calendarDays = document.getElementById('calendarDays');
            const monthYearText = document.getElementById('currentMonthYear');
            const monthYearTextDesktop = document.getElementById('currentMonthYearDesktop');
            
            // Clear previous calendar
            calendarDays.innerHTML = '';
            
            // Show loading indicator
            calendarDays.innerHTML = `
                <div class="text-center py-5 w-100" id="calendarLoading">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2 text-muted">Loading calendar...</p>
                </div>
            `;
            
            // Update month and year display
            const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
            monthYearText.textContent = `${monthNames[month]} ${year}`;
            monthYearTextDesktop.textContent = `${monthNames[month]} ${year}`;
            
            // Get first day of month and total days
            const firstDay = new Date(year, month, 1).getDay();
            const daysInMonth = new Date(year, month + 1, 0).getDate();
            
            // Get days from previous month
            const daysInPrevMonth = new Date(year, month, 0).getDate();
            
            // Create calendar grid
            let dayCount = 1;
            let nextMonthDay = 1;
            
            // Calculate how many rows we need (5 or 6)
            const totalDays = firstDay + daysInMonth;
            const rows = Math.ceil(totalDays / 7);
            
            // Clear loading indicator and prepare to add calendar days
            calendarDays.innerHTML = '';
            
            // Add days from previous month, current month, and next month
            for (let i = 0; i < rows * 7; i++) {
                let dayElement = document.createElement('div');
                dayElement.className = 'calendar-day';
                
                // Previous month days
                if (i < firstDay) {
                    const prevMonthDate = daysInPrevMonth - (firstDay - i - 1);
                    dayElement.className = 'calendar-day other-month';
                    dayElement.innerHTML = `<div class="day-number">${prevMonthDate}</div>`;
                    
                    // Create date for previous month
                    const prevMonth = month === 0 ? 11 : month - 1;
                    const prevYear = month === 0 ? year - 1 : year;
                    const dateStr = `${prevYear}-${String(prevMonth + 1).padStart(2, '0')}-${String(prevMonthDate).padStart(2, '0')}`;
                    
                    dayElement.addEventListener('click', function() {
                        selectDate(dateStr);
                    });
                }
                // Current month days
                else if (i < firstDay + daysInMonth) {
                    const date = i - firstDay + 1;
                    dayElement.innerHTML = `<div class="day-number">${date}</div>`;
                    
                    // Check if it's today
                    const today = new Date();
                    if (date === today.getDate() && month === today.getMonth() && year === today.getFullYear()) {
                        dayElement.classList.add('today');
                    }
                    
                    // Create date string for data attribute
                    const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(date).padStart(2, '0')}`;
                    dayElement.dataset.date = dateStr;
                    
                    // Add click event
                    dayElement.addEventListener('click', function() {
                        selectDate(dateStr);
                    });
                }
                // Next month days
                else {
                    dayElement.className = 'calendar-day other-month';
                    dayElement.innerHTML = `<div class="day-number">${nextMonthDay}</div>`;
                    
                    // Create date for next month
                    const nextMonth = month === 11 ? 0 : month + 1;
                    const nextYear = month === 11 ? year + 1 : year;
                    const dateStr = `${nextYear}-${String(nextMonth + 1).padStart(2, '0')}-${String(nextMonthDay).padStart(2, '0')}`;
                    
                    dayElement.addEventListener('click', function() {
                        selectDate(dateStr);
                    });
                    
                    nextMonthDay++;
                }
                
                calendarDays.appendChild(dayElement);
            }
        }
        
        // Navigate to previous or next month
        function navigateMonth(direction) {
            currentMonth += direction;
            
            if (currentMonth < 0) {
                currentMonth = 11;
                currentYear--;
            } else if (currentMonth > 11) {
                currentMonth = 0;
                currentYear++;
            }
            
            generateCalendar(currentMonth, currentYear);
            fetchMonthlyAttendance(currentMonth + 1, currentYear);
        }
        
        // Fetch monthly attendance data
        function fetchMonthlyAttendance(month, year) {
            // Show loading state in calendar
            const calendarDays = document.querySelectorAll('.calendar-day:not(.other-month)');
            calendarDays.forEach(day => {
                // Remove any existing indicators
                const indicators = day.querySelectorAll('.attendance-indicator');
                indicators.forEach(indicator => indicator.remove());
            });
            
            // Fetch data from server
            fetch(`qr-scanner.php?action=get_calendar_attendance&month=${month}&year=${year}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        monthAttendanceData = {};
                        
                        // Process attendance data
                        data.records.forEach(record => {
                            monthAttendanceData[record.attendance_date] = {
                                total: parseInt(record.total_records),
                                present: parseInt(record.present_count),
                                late: parseInt(record.late_count),
                                absent: parseInt(record.absent_count)
                            };
                        });
                        
                        // Update calendar with attendance data
                        updateCalendarWithAttendance();
                    }
                })
                .catch(error => {
                    console.error('Error fetching attendance data:', error);
                });
        }
        
        // Update calendar with attendance indicators
        function updateCalendarWithAttendance() {
            const calendarDays = document.querySelectorAll('.calendar-day');
            
            calendarDays.forEach(day => {
                const dateStr = day.dataset.date;
                if (!dateStr) return;
                
                if (monthAttendanceData[dateStr]) {
                    const data = monthAttendanceData[dateStr];
                    const total = data.total;
                    const present = data.present + data.late; // Count late as present for attendance rate
                    const rate = Math.round((present / total) * 100);
                    
                    let indicatorClass = '';
                    if (rate >= 80) {
                        indicatorClass = 'attendance-high';
                    } else if (rate >= 60) {
                        indicatorClass = 'attendance-medium';
                    } else {
                        indicatorClass = 'attendance-low';
                    }
                    
                    // Create attendance indicator
                    const indicator = document.createElement('div');
                    indicator.className = `attendance-indicator ${indicatorClass}`;
                    indicator.textContent = total;
                    day.appendChild(indicator);
                }
            });
        }
        
        // Select a date and show attendance details
        function selectDate(dateStr) {
            // Remove selection from previously selected date
            const previouslySelected = document.querySelector('.calendar-day.selected');
            if (previouslySelected) {
                previouslySelected.classList.remove('selected');
            }
            
            // Add selection to new date
            const selectedDay = document.querySelector(`.calendar-day[data-date="${dateStr}"]`);
            if (selectedDay) {
                selectedDay.classList.add('selected');
            }
            
            // Update selected date
            selectedDate = dateStr;
            
            // Show selected date section
            const selectedDateSection = document.getElementById('selectedDateSection');
            selectedDateSection.style.display = 'block';
            
            // Update title
            const selectedDateText = document.getElementById('selectedDateText');
            const formattedDate = new Date(dateStr + 'T00:00:00').toLocaleDateString('en-US', { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            });
            selectedDateText.textContent = formattedDate;
            
            // Show loading in attendance section
            const selectedDateAttendance = document.getElementById('selectedDateAttendance');
            selectedDateAttendance.innerHTML = `
                <div class="text-center py-3">
                    <div class="spinner-border spinner-border-sm text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mb-0 mt-2 text-muted">Loading attendance records...</p>
                </div>
            `;
            
            // Fetch attendance records for selected date
            fetch(`qr-scanner.php?action=get_calendar_attendance&date=${dateStr}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        allAttendanceRecords = data.records;
                        displayAttendanceRecords(data.records);
                        
                        // Reset search fields
                        document.getElementById('studentSearch').value = '';
                        document.getElementById('studentSearchDesktop').value = '';
                    } else {
                        selectedDateAttendance.innerHTML = `
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                Failed to load attendance records.
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error fetching attendance records:', error);
                    selectedDateAttendance.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            Error loading attendance records.
                        </div>
                    `;
                });
        }
        
        // Filter attendance records by search term
        function filterAttendanceRecords(searchTerm) {
            if (!allAttendanceRecords || allAttendanceRecords.length === 0) {
                return;
            }
            
            if (!searchTerm) {
                // If search term is empty, show all records
                displayAttendanceRecords(allAttendanceRecords);
                return;
            }
            
            // Filter records based on search term
            const filteredRecords = allAttendanceRecords.filter(record => {
                return (
                    record.student_name.toLowerCase().includes(searchTerm) || 
                    (record.section_name && record.section_name.toLowerCase().includes(searchTerm)) ||
                    (record.teacher_name && record.teacher_name.toLowerCase().includes(searchTerm)) ||
                    record.status.toLowerCase().includes(searchTerm)
                );
            });
            
            // Display filtered records
            displayAttendanceRecords(filteredRecords, true);
        }
        
        // Display attendance records for selected date
        function displayAttendanceRecords(records, isFiltered = false) {
            const selectedDateAttendance = document.getElementById('selectedDateAttendance');
            
            if (records.length === 0) {
                selectedDateAttendance.innerHTML = `
                    <div class="text-center py-3">
                        <i class="fas fa-calendar-times fa-2x text-muted mb-2"></i>
                        <p class="mb-0">${isFiltered ? 'No matching records found.' : 'No attendance records for this date.'}</p>
                    </div>
                `;
                return;
            }
            
            // Count attendance by status
            const statusCounts = {
                present: records.filter(r => r.status === 'present').length,
                late: records.filter(r => r.status === 'late').length,
                absent: records.filter(r => r.status === 'absent').length,
                out: records.filter(r => r.status === 'out').length
            };
            
            // Create summary
            let html = `
                <div class="mb-3">
                    <div class="row g-2 text-center">
                        <div class="col-3">
                            <div class="p-2 rounded bg-success bg-opacity-10 text-success">
                                <strong>${statusCounts.present}</strong>
                                <small class="d-block">Present</small>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="p-2 rounded bg-warning bg-opacity-10 text-warning">
                                <strong>${statusCounts.late}</strong>
                                <small class="d-block">Late</small>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="p-2 rounded bg-danger bg-opacity-10 text-danger">
                                <strong>${statusCounts.absent}</strong>
                                <small class="d-block">Absent</small>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="p-2 rounded bg-info bg-opacity-10 text-info">
                                <strong>${statusCounts.out}</strong>
                                <small class="d-block">Out</small>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            if (isFiltered) {
                html += `
                    <div class="alert alert-info mb-3">
                        <i class="fas fa-filter me-2"></i>
                        Showing ${records.length} of ${allAttendanceRecords.length} records
                    </div>
                `;
            }
            
            html += '<div class="list-group">';
            
            // Add records
            records.forEach(record => {
                let statusClass = '';
                let statusIcon = '';
                
                switch(record.status) {
                    case 'present':
                        statusClass = 'success';
                        statusIcon = 'check-circle';
                        break;
                    case 'late':
                        statusClass = 'warning';
                        statusIcon = 'clock';
                        break;
                    case 'absent':
                        statusClass = 'danger';
                        statusIcon = 'times-circle';
                        break;
                    case 'out':
                        statusClass = 'info';
                        statusIcon = 'sign-out-alt';
                        break;
                    default:
                        statusClass = 'secondary';
                        statusIcon = 'question-circle';
                }
                
                html += `
                    <div class="list-group-item list-group-item-action">
                        <div class="d-flex w-100 justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1">${record.student_name}</h6>
                                <small class="text-muted">${record.section_name || 'No Section'}</small>
                            </div>
                            <span class="badge bg-${statusClass}">
                                <i class="fas fa-${statusIcon} me-1"></i>
                                ${record.status.charAt(0).toUpperCase() + record.status.slice(1)}
                            </span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-2">
                            <small class="text-muted">
                                <i class="fas fa-user-check me-1"></i>
                                ${record.teacher_name || 'Unknown'}
                            </small>
                            <small class="text-muted">
                                ${record.time_in ? `<i class="fas fa-sign-in-alt me-1"></i>${record.time_in}` : ''}
                                ${record.time_out ? `<i class="fas fa-sign-out-alt ms-2 me-1"></i>${record.time_out}` : ''}
                            </small>
                        </div>
                    </div>
                `;
            });
            
            html += '</div>';
            selectedDateAttendance.innerHTML = html;
        }
    });
</script>

<style>
/* Manual LRN Input Styles */
.card.bg-light.border-primary {
    border-width: 2px !important;
    box-shadow: 0 2px 8px rgba(0, 123, 255, 0.15);
}

.card.bg-light.border-primary .card-header {
    background: linear-gradient(135deg, #007bff 0%, #0056b3 100%) !important;
    border-color: #007bff;
}

.input-group-lg .form-control {
    font-size: 1.1rem;
    padding: 0.75rem 1rem;
}

.input-group-lg .input-group-text {
    font-size: 1.1rem;
    padding: 0.75rem 1rem;
    background-color: #f8f9fa;
    border-color: #007bff;
    color: #007bff;
}

.input-group-lg .btn {
    font-size: 1rem;
    padding: 0.75rem 1.5rem;
    font-weight: 500;
}

@media (max-width: 576px) {
    .input-group-lg {
        flex-direction: row !important;
        align-items: center;
    }
    
    .input-group-lg .input-group-text {
        border-radius: 0.5rem 0 0 0.5rem !important;
        border-right: none;
        justify-content: center;
        flex: 0 0 auto;
    }
    
    .input-group-lg .form-control {
        border-radius: 0 !important;
        border-left: none;
        border-right: none;
        flex: 1 1 auto;
        min-width: 0;
    }
    
    .input-group-lg .btn {
        border-radius: 0 0.5rem 0.5rem 0 !important;
        border-left: none;
        flex: 0 0 auto;
    }
}

/* Google QR Scanner Styles */
#scanner-container {
    min-height: 300px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
    overflow: hidden;
}

#scanner-container.mobile-optimized {
    width: 100%;
    max-width: 100%;
}

#scanner-container > div {
    width: 100%;
    max-width: 100%;
}

/* Scanner frame styling */
#scanner-container video {
    border-radius: 8px;
    border: 2px solid #007bff;
    max-width: 100%;
    height: auto;
}

/* Camera Selection Styles */
#cameraSelectionCard {
    border: 1px solid #dee2e6;
    border-radius: 8px;
}

#cameraSelectionCard .card-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
}

/* Mobile optimizations */
@media (max-width: 768px) {
    #scanner-container {
        min-height: 250px;
        margin: 0 -15px;
        border-radius: 0;
    }
    
    #scanner-container.mobile-optimized {
        padding: 10px;
        margin: 0;
        border-radius: 8px;
    }
    
    #scanner-container > div {
        max-width: 100%;
    }
    
    .card-body {
        padding: 1rem 0.75rem;
    }
    
    /* Make scanner controls more touch-friendly */
    .btn {
        min-height: 44px;
        font-size: 0.9rem;
    }
    
    .form-control, .form-select {
        min-height: 44px;
        font-size: 16px; /* Prevents zoom on iOS */
    }
    
    /* Reduce icon sizes on mobile */
    .fa-4x {
        font-size: 2.5em !important;
    }
    
    .fa-3x {
        font-size: 2em !important;
    }
    
    /* Optimize modal for mobile */
    .modal-dialog {
        margin: 0.5rem;
    }
    
    /* Responsive text */
    .h3 {
        font-size: 1.5rem;
    }
    
    .h5 {
        font-size: 1.1rem;
    }
    
    /* Hide unnecessary text on small screens */
    .d-sm-inline {
        display: none !important;
    }
}

@media (max-width: 576px) {
    #scanner-container {
        min-height: 200px;
    }
    
    /* Stack form elements vertically on very small screens */
    .input-group {
        flex-direction: column;
    }
    
    /* Exception: Keep calendar search horizontal */
    #mobileSearchCollapse .input-group {
        flex-direction: row !important;
    }
    
    /* Exception: Keep Manual LRN Entry horizontal */
    .input-group-lg {
        flex-direction: row !important;
    }
    
    .input-group .form-control {
        border-radius: 0.375rem !important;
        margin-bottom: 0.5rem;
    }
    
    /* Exception: Calendar search form control */
    #mobileSearchCollapse .input-group .form-control {
        border-radius: 0 !important;
        margin-bottom: 0 !important;
    }
    
    /* Exception: Manual LRN Entry form control */
    .input-group-lg .form-control {
        border-radius: 0 !important;
        margin-bottom: 0 !important;
        border-right: 0;
    }
    
    .input-group .btn {
        border-radius: 0.375rem !important;
    }
    
    /* Exception: Calendar search button */
    #mobileSearchCollapse .input-group .btn {
        border-radius: 0 0.375rem 0.375rem 0 !important;
    }
    
    /* Exception: Manual LRN Entry button */
    .input-group-lg .btn {
        border-radius: 0 0.375rem 0.375rem 0 !important;
        border-left: 0;
    }
    
    /* Adjust camera selection for small screens */
    #cameraSelectionCard .row > .col-12 {
        margin-bottom: 0.5rem;
    }
    
    #cameraSelectionCard .row > .col-12:last-child {
        margin-bottom: 0;
    }
}

/* Google QR Scanner custom styling */
#scan-region {
    min-height: 350px;
    width: 100%;
    position: relative;
    background: #f8f9fa;
    border: 2px dashed #dee2e6;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
}

#scan-region.scanning {
    border: 2px solid #007bff;
    background: transparent;
}

#qr-reader {
    width: 100% !important;
    min-height: 350px !important;
    border-radius: 8px;
    overflow: hidden;
}

.html5-qrcode-element {
    border-radius: 8px !important;
    border: 2px solid #007bff !important;
    max-width: 100% !important;
    width: 100% !important;
}

.html5-qrcode-element video {
    border-radius: 6px !important;
    max-width: 100% !important;
    height: auto !important;
    width: 100% !important;
    object-fit: cover;
}

/* QR Scanner select camera styling */
#html5-qrcode-select-camera {
    display: none !important; /* Hide default camera selector */
}

#html5-qrcode-button-camera-permission,
#html5-qrcode-button-camera-start,
#html5-qrcode-button-camera-stop {
    display: none !important; /* Hide default buttons */
}

/* Improve QR box visibility */
.html5-qrcode-element canvas {
    border-radius: 8px !important;
}

/* QR Scanner scanning animation */
@keyframes scanningPulse {
    0% { box-shadow: 0 0 0 0 rgba(0, 123, 255, 0.7); }
    70% { box-shadow: 0 0 0 10px rgba(0, 123, 255, 0); }
    100% { box-shadow: 0 0 0 0 rgba(0, 123, 255, 0); }
}

#scan-region.scanning {
    animation: scanningPulse 2s infinite;
}

/* Success animation */
@keyframes scanSuccess {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}

.scan-success {
    animation: scanSuccess 0.5s ease-in-out;
}

/* Attendance Records Styles */
.attendance-cards, .calendar-attendance-cards {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 15px;
}

@media (max-width: 768px) {
    .attendance-cards, .calendar-attendance-cards {
        grid-template-columns: 1fr;
    }
}

.attendance-card, .calendar-attendance-card {
    transition: all 0.3s ease;
    border: none;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    overflow: hidden;
}

.attendance-card:hover, .calendar-attendance-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.12);
}

.attendance-card .card-header, .calendar-attendance-card .card-header {
    border-bottom: none;
    padding: 0.6rem 1rem;
}

.attendance-icon-container {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

#attendanceRecords, #selectedDateAttendance {
    max-height: 500px;
    overflow-y: auto;
    padding-right: 5px;
}

/* Custom scrollbar for attendance records */
#attendanceRecords::-webkit-scrollbar, 
#selectedDateAttendance::-webkit-scrollbar {
    width: 6px;
}

#attendanceRecords::-webkit-scrollbar-track,
#selectedDateAttendance::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 3px;
}

#attendanceRecords::-webkit-scrollbar-thumb,
#selectedDateAttendance::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 3px;
}

#attendanceRecords::-webkit-scrollbar-thumb:hover,
#selectedDateAttendance::-webkit-scrollbar-thumb:hover {
    background: #a8a8a8;
}

/* Responsive adjustments for better mobile experience */
@media (orientation: landscape) and (max-height: 500px) {
    #scanner-container {
        min-height: 150px;
    }
    
    .modal-dialog {
        max-height: 90vh;
        overflow-y: auto;
    }
}

/* Touch-friendly buttons */
.btn-sm {
    min-height: 38px;
}

/* Improve readability on mobile */
.small {
    font-size: 0.8rem;
}

.text-muted {
    opacity: 0.8;
}

/* Better spacing for mobile cards */
@media (max-width: 768px) {
    .card {
        margin-bottom: 1rem;
    }
    
    .card-body {
        padding: 1rem;
    }
    
    .mb-4 {
        margin-bottom: 1.5rem !important;
    }
}

/* Calendar Styles */
.calendar-grid {
    width: 100%;
}

.calendar-days-header,
.calendar-days-header-mobile {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 1px;
    margin-bottom: 1px;
    background-color: #f8f9fa;
    padding: 8px 0;
    border-radius: 8px;
}

.calendar-days {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 1px;
    background-color: #dee2e6;
    border-radius: 8px;
    overflow: hidden;
}

.calendar-day {
    background-color: white;
    min-height: 60px;
    padding: 8px;
    cursor: pointer;
    transition: all 0.2s ease;
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: flex-start;
}

.calendar-day:hover {
    background-color: #f8f9fa;
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.calendar-day.selected {
    background-color: #007bff;
    color: white;
}

.calendar-day.selected:hover {
    background-color: #0056b3;
}

.calendar-day.today {
    border: 2px solid #007bff;
    font-weight: bold;
}

.calendar-day.today.selected {
    border-color: white;
}

.calendar-day.other-month {
    background-color: #f8f9fa;
    color: #6c757d;
}

.calendar-day.other-month:hover {
    background-color: #e9ecef;
}

.day-number {
    font-size: 0.9rem;
    font-weight: 500;
    margin-bottom: 4px;
}

.attendance-indicator {
    position: absolute;
    bottom: 4px;
    right: 4px;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.7rem;
    font-weight: bold;
    color: white;
}

.attendance-indicator.attendance-high {
    background-color: #28a745;
}

.attendance-indicator.attendance-medium {
    background-color: #ffc107;
    color: #212529;
}

.attendance-indicator.attendance-low {
    background-color: #dc3545;
}

.calendar-header {
    border-bottom: 1px solid #dee2e6;
}

.selected-date-section {
    background-color: #f8f9fa;
}

.calendar-legend {
    display: flex;
    justify-content: center;
    gap: 1rem;
    margin-top: 1rem;
    flex-wrap: wrap;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.8rem;
}

.legend-color {
    width: 12px;
    height: 12px;
    border-radius: 50%;
}

/* Calendar mobile optimizations */
@media (max-width: 768px) {
    .calendar-container {
        padding: 1rem;
    }
    
    .calendar-day {
        min-height: 50px;
        padding: 4px;
    }
    
    .day-number {
        font-size: 0.8rem;
    }
    
    .attendance-indicator {
        width: 16px;
        height: 16px;
        font-size: 0.6rem;
    }
    
    .calendar-legend {
        gap: 0.5rem;
    }
    
    .legend-item {
        font-size: 0.75rem;
    }
    
    .legend-color {
        width: 10px;
        height: 10px;
    }
    
    .modal-fullscreen-md-down {
        margin: 0;
    }
    
    .modal-fullscreen-md-down .modal-content {
        border-radius: 0;
    }
}

@media (max-width: 576px) {
    .calendar-container {
        padding: 0.5rem;
    }
    
    .calendar-day {
        min-height: 40px;
        padding: 2px;
    }
    
    .day-number {
        font-size: 0.75rem;
        margin-bottom: 2px;
    }
    
    .attendance-indicator {
        width: 14px;
        height: 14px;
        font-size: 0.55rem;
        bottom: 2px;
        right: 2px;
    }
    
    .calendar-days-header-mobile {
        padding: 4px 0;
    }
    
    .calendar-days {
        gap: 0.5px;
    }
    
    #currentMonthYear {
        font-size: 1rem;
    }
    
    .legend-item {
        font-size: 0.7rem;
    }
    
    .legend-color {
        width: 8px;
        height: 8px;
    }
    
    .modal-header {
        padding: 0.75rem 1rem;
    }
    
    .modal-footer {
        padding: 0.75rem 1rem;
    }
    
    .selected-date-section .p-3 {
        padding: 0.75rem !important;
    }
}

/* Select2 Custom Styling */
.select2-container--bootstrap-5 .select2-selection {
    border-radius: 8px;
    padding: 0.375rem 0.75rem;
    height: auto;
    min-height: 42px;
    border: 1px solid #ced4da;
    transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
}

.select2-container--bootstrap-5 .select2-selection:focus {
    border-color: #86b7fe;
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}

.select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
    padding: 0;
    font-weight: 500;
}

.select2-container--bootstrap-5 .select2-dropdown {
    border-radius: 8px;
    border: 1px solid #ced4da;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
    overflow: hidden;
}

.select2-container--bootstrap-5 .select2-results__option {
    padding: 0.5rem 0.75rem;
    transition: background-color 0.15s ease-in-out;
}

.select2-container--bootstrap-5 .select2-results__option--highlighted {
    background-color: #f8f9fa;
    color: #212529;
}

.select2-container--bootstrap-5 .select2-results__option--selected {
    background-color: #e9ecef;
    color: #212529;
}

.select2-container--bootstrap-5 .select2-search--dropdown .select2-search__field {
    border-radius: 4px;
    padding: 0.5rem;
    border: 1px solid #ced4da;
}

.select2-container--bootstrap-5 .select2-search--dropdown .select2-search__field:focus {
    border-color: #86b7fe;
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.1);
    outline: none;
}

/* Mobile optimizations for Select2 */
@media (max-width: 576px) {
    .select2-container--bootstrap-5 .select2-selection {
        font-size: 16px; /* Prevent zoom on iOS */
        min-height: 44px; /* Better touch target */
    }
    
    .select2-container--bootstrap-5 .select2-search--dropdown .select2-search__field {
        font-size: 16px; /* Prevent zoom on iOS */
        padding: 0.5rem;
    }
    
    .select2-container--bootstrap-5 .select2-results__option {
        padding: 0.75rem;
    }
}

/* Offline mode styling for Select2 */
body.offline-mode .select2-container--bootstrap-5 .select2-selection {
    background-color: #f8f9fa;
    border-color: #ffc107;
}

body.offline-mode .select2-container--bootstrap-5 .select2-selection__rendered::after {
    content: " (Offline)";
    color: #ffc107;
    font-style: italic;
    font-size: 0.875em;
}

@media (max-width: 576px) {
    .calendar-container {
        padding: 0.5rem;
    }
    
    .calendar-day {
        min-height: 40px;
        padding: 2px;
    }
    
    .day-number {
        font-size: 0.75rem;
        margin-bottom: 2px;
    }
    
    .attendance-indicator {
        width: 14px;
        height: 14px;
        font-size: 0.55rem;
        bottom: 2px;
        right: 2px;
    }
    
    .calendar-days-header-mobile {
        padding: 4px 0;
    }
    
    .calendar-days {
        gap: 0.5px;
    }
    
    #currentMonthYear {
        font-size: 1rem;
    }
    
    .legend-item {
        font-size: 0.7rem;
    }
    
    .legend-color {
        width: 8px;
        height: 8px;
    }
    
    .modal-header {
        padding: 0.75rem 1rem;
    }
    
    .modal-footer {
        padding: 0.75rem 1rem;
    }
    
    .selected-date-section .p-3 {
        padding: 0.75rem !important;
    }
}
</style>

<?php include 'footer.php'; ?>

<script>
// Initialize offline storage for attendance records
document.addEventListener('DOMContentLoaded', function() {
    // Check if the offline-forms.js script has been loaded
    if (typeof initOfflineDB !== 'function') {
        console.error('Offline forms module not loaded');
        return;
    }
    
    // Initialize IndexedDB
    initOfflineDB().catch(error => console.error('Failed to initialize offline storage:', error));
    
    // Add offline mode handling to the QR scanner form
    const qrForm = document.getElementById('qr-form');
    if (qrForm) {
        qrForm.addEventListener('submit', function(event) {
            // If offline, intercept the form submission
            if (!navigator.onLine) {
                event.preventDefault();
                
                // Safely get form values using utility functions
                const qrData = safeGetElementValue('qr_data');
                const scanLocation = safeGetElementValue('scan_location', 'Main Gate');
                const scanNotes = safeGetElementValue('scan_notes');
                
                // Store the scan in offline storage
                if (typeof handleOfflineQrScan === 'function') {
                    handleOfflineQrScan(qrData, scanLocation, scanNotes);
                } else {
                    console.error('handleOfflineQrScan function not found');
                    showToast('Offline QR scan function not available', 'danger');
                }
            }
        });
    } else {
        console.log('QR form not found - this is normal if using direct QR scanning instead of form submission');
    }
    
    // Add offline mode handling to the LRN form
    const lrnForm = document.getElementById('lrn-form');
    if (lrnForm) {
        lrnForm.addEventListener('submit', function(event) {
            // If offline, intercept the form submission
            if (!navigator.onLine) {
                event.preventDefault();
                
                // Get values the same way as the online form handler
                const subjectSelect = document.getElementById('subject-select');
                const studentSelect = $('#student-select');
                const studentValue = studentSelect.val();
                const scanLocation = safeGetElementValue('scan-location', 'Main Gate');
                const scanNotes = safeGetElementValue('scan-notes');
                
                // Validate subject selection (same as online form)
                if (!subjectSelect || !subjectSelect.value) {
                    showToast('Please select a subject before recording attendance', 'warning');
                    return;
                }
                
                // Validate student selection
                if (!studentValue) {
                    showToast('Please select a student', 'warning');
                    return;
                }
                
                console.log('Offline form submission - Subject:', subjectSelect.value, 'Student:', studentValue);
                
                // Store the scan in offline storage using the same method as online
                if (typeof handleEnhancedOfflineManualSelection === 'function') {
                    handleEnhancedOfflineManualSelection(studentValue, scanLocation, scanNotes);
                } else {
                    console.error('Enhanced offline manual selection function not available');
                    showToast('Offline attendance functionality not available', 'danger');
                }
            }
        });
    }
    
    // Check connection status on page load
    updateOfflineStatus();
    
    // Listen for online/offline events
    window.addEventListener('online', updateOfflineStatus);
    window.addEventListener('offline', updateOfflineStatus);
});

// Handle offline QR code scan
function handleOfflineQrScan(qrData, scanLocation, scanNotes) {
    if (!qrData) {
        showOfflineAlert('error', 'QR data is required');
        return;
    }
    
    // Store the attendance record for syncing later
    storeOfflineAttendance('qr', {
        qr_data: qrData,
        scan_location: scanLocation || 'Main Gate',
        scan_notes: scanNotes || '',
        timestamp: new Date().toISOString(),
        teacher_id: <?php echo $current_user['id']; ?>,
        teacher_name: "<?php echo addslashes($current_user['full_name']); ?>"
    })
    .then(() => {
        showOfflineAlert('success', 'Attendance recorded offline. Will sync when online.');
        
        // Clear the form using safe utility function
        safeSetElementValue('qr_data', '');
        
        // Add to the recent scans table
        addToRecentScansTable({
            student_name: 'Pending sync...',
            status: 'pending',
            time: new Date().toLocaleTimeString([], {hour: '2-digit', minute: '2-digit', hour12: true}),
            offline: true
        });
        
        // Reset the scanner if it's active
        if (typeof resetScanner === 'function') {
            resetScanner();
        }
    })
    .catch(error => {
        showOfflineAlert('error', 'Failed to store offline attendance: ' + error.message);
    });
}

// Handle offline LRN scan
function handleOfflineLrnScan(lrn, scanLocation, scanNotes) {
    if (!lrn) {
        showOfflineAlert('error', 'LRN is required');
        return;
    }
    
    if (!/^\d{12}$/.test(lrn)) {
        showOfflineAlert('error', 'LRN must be exactly 12 digits');
        return;
    }
    
    // Store the attendance record for syncing later
    storeOfflineAttendance('lrn', {
        lrn: lrn,
        scan_location: scanLocation || 'Main Gate',
        scan_notes: scanNotes || '',
        timestamp: new Date().toISOString(),
        teacher_id: <?php echo $current_user['id']; ?>,
        teacher_name: "<?php echo addslashes($current_user['full_name']); ?>"
    })
    .then(() => {
        showOfflineAlert('success', 'LRN attendance recorded offline. Will sync when online.');
        
        // Clear the form using safe utility function
        safeSetElementValue('lrn', '');
        
        // Add to the recent scans table
        addToRecentScansTable({
            student_name: 'Pending sync (LRN: ' + lrn + ')',
            status: 'pending',
            time: new Date().toLocaleTimeString([], {hour: '2-digit', minute: '2-digit', hour12: true}),
            offline: true
        });
    })
    .catch(error => {
        showOfflineAlert('error', 'Failed to store offline attendance: ' + error.message);
    });
}

// Show alert for offline mode
function showOfflineAlert(type, message) {
    const alertContainer = document.getElementById('offline-alert-container');
    if (!alertContainer) return;
    
    const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
    const icon = type === 'success' ? 'check-circle' : 'exclamation-circle';
    
    const alert = document.createElement('div');
    alert.className = `alert ${alertClass} alert-dismissible fade show`;
    alert.innerHTML = `
        <i class="fas fa-${icon} me-2"></i>
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    alertContainer.appendChild(alert);
    
    // Auto-dismiss after 5 seconds
    setTimeout(() => {
        alert.classList.remove('show');
        setTimeout(() => alert.remove(), 300);
    }, 5000);
}

// Add a record to the recent scans table
function addToRecentScansTable(data) {
    const recentScansTable = document.getElementById('recent-scans-table');
    if (!recentScansTable) return;
    
    const tbody = recentScansTable.querySelector('tbody');
    if (!tbody) return;
    
    const tr = document.createElement('tr');
    tr.className = data.offline ? 'table-warning' : '';
    
    tr.innerHTML = `
        <td>${data.student_name}</td>
        <td>
            ${data.offline ? 
                '<span class="badge bg-warning text-dark">Pending</span>' : 
                `<span class="badge bg-${data.status === 'present' ? 'success' : (data.status === 'late' ? 'warning' : 'danger')}">${data.status.charAt(0).toUpperCase() + data.status.slice(1)}</span>`
            }
        </td>
        <td>${data.time}</td>
        <td>${data.offline ? '<i class="fas fa-wifi-slash text-warning"></i> Offline' : '<i class="fas fa-check-circle text-success"></i>'}</td>
    `;
    
    // Insert at the top
    if (tbody.firstChild) {
        tbody.insertBefore(tr, tbody.firstChild);
    } else {
        tbody.appendChild(tr);
    }
    
    // Limit to 10 rows
    while (tbody.children.length > 10) {
        tbody.removeChild(tbody.lastChild);
    }
}

// Update current time display in header
function updateCurrentTimeDisplay() {
    // Update any current time displays in the interface
    const currentTimeElements = document.querySelectorAll('.current-time-display');
    const currentTime = new Date();
    const timeString = currentTime.toLocaleTimeString([], {hour: '2-digit', minute: '2-digit', hour12: true});
    
    currentTimeElements.forEach(element => {
        element.textContent = timeString;
    });
    
    // Also update the title with current time for easy reference
    const timeDisplays = document.querySelectorAll('[data-time-display="current"]');
    timeDisplays.forEach(element => {
        element.textContent = timeString;
    });
}

// Update time status display
function updateTimeStatus() {
    const timeStatusElement = document.getElementById('timeStatus');
    const timeStatusTextElement = document.getElementById('timeStatusText');
    
    if (!timeStatusElement || !timeStatusTextElement) return;
    
    const currentTime = new Date();
    const currentHour = currentTime.getHours();
    const currentMinute = currentTime.getMinutes();
    const currentTimeString = currentTime.toLocaleTimeString([], {hour: '2-digit', minute: '2-digit', hour12: true});
    
    let statusClass = 'alert-info';
    let statusIcon = 'fa-clock';
    let statusText = '';
    
    // Check current time status
    if (currentHour < 6) {
        statusClass = 'alert-secondary';
        statusIcon = 'fa-moon';
        statusText = `Current time: ${currentTimeString} - Attendance recording not yet available (starts at 6:00 AM)`;
    } else if (currentHour < 7 || (currentHour === 7 && currentMinute <= 15)) {
        statusClass = 'alert-success';
        statusIcon = 'fa-check-circle';
        statusText = `Current time: ${currentTimeString} - Students will be marked as PRESENT`;
    } else if (currentHour < 16 || (currentHour === 16 && currentMinute <= 15)) {
        statusClass = 'alert-warning';
        statusIcon = 'fa-exclamation-triangle';
        statusText = `Current time: ${currentTimeString} - Students will be marked as LATE`;
    } else {
        statusClass = 'alert-danger';
        statusIcon = 'fa-times-circle';
        statusText = `Current time: ${currentTimeString} - Attendance recording closed. Auto-absent marking will run soon.`;
    }
    
    console.log(`[Time Status Update] ${currentTimeString} (${currentHour}:${currentMinute}) - Status: ${statusClass.replace('alert-', '')}`);
    
    // Update the status display
    timeStatusElement.className = `alert ${statusClass}`;
    timeStatusTextElement.innerHTML = `<strong>${statusText}</strong>`;
    
    // Also disable/enable scanner buttons based on time
    const startScannerBtn = document.getElementById('startScannerBtn');
    const lrnSubmitBtn = document.querySelector('#lrn-form button[type="submit"]');
    
    // Use the same logic as server-side (allow 6:00 AM to 4:30 PM)
    const isTimeRestricted = currentHour < 6 || currentHour > 16 || (currentHour === 16 && currentMinute > 15);
    
    if (startScannerBtn) {
        startScannerBtn.disabled = isTimeRestricted;
        if (isTimeRestricted) {
            startScannerBtn.innerHTML = '<i class="fas fa-times me-2"></i>Scanner Disabled';
        } else {
            startScannerBtn.innerHTML = '<i class="fas fa-camera me-2"></i>Start Scanner';
        }
    }
    
    if (lrnSubmitBtn) {
        lrnSubmitBtn.disabled = isTimeRestricted;
        console.log(`[Button Status] Manual selection button disabled: ${isTimeRestricted} (time: ${currentHour}:${currentMinute})`);
        if (isTimeRestricted) {
            lrnSubmitBtn.innerHTML = '<i class="fas fa-times me-2"></i>Recording Disabled';
            lrnSubmitBtn.title = 'Attendance recording is only available from 6:00 AM to 4:30 PM';
        } else {
            lrnSubmitBtn.innerHTML = '<i class="fas fa-user-check me-2"></i>Record Attendance';
            lrnSubmitBtn.title = 'Record student attendance';
        }
    }
}

// Update UI based on online/offline status
function updateOfflineStatus() {
    const isOnline = navigator.onLine;
    const offlineIndicator = document.getElementById('offline-mode-indicator');
    
    if (offlineIndicator) {
        if (!isOnline) {
            offlineIndicator.classList.remove('d-none');
            document.body.classList.add('offline-mode');
        } else {
            offlineIndicator.classList.add('d-none');
            document.body.classList.remove('offline-mode');
            
            // Try to sync data when back online
            if (typeof syncOfflineData === 'function') {
                syncOfflineData().then(() => {
                    console.log('Offline attendance records synced successfully.');
                }).catch(error => {
                    console.error('Error syncing offline data:', error);
                });
            }
        }
    }
    
    // Update form buttons
    const submitButtons = document.querySelectorAll('button[type="submit"]');
    submitButtons.forEach(button => {
        if (!isOnline) {
            if (!button.innerHTML.includes('fa-wifi-slash')) {
                button.innerHTML = '<i class="fas fa-wifi-slash me-2"></i>' + button.innerHTML;
            }
            button.classList.add('btn-warning');
            button.classList.remove('btn-primary');
        } else {
            button.innerHTML = button.innerHTML.replace('<i class="fas fa-wifi-slash me-2"></i>', '');
            button.classList.add('btn-primary');
            button.classList.remove('btn-warning');
        }
    });
    
    // Update Select2 dropdown
    const studentSelect = $('#student-select');
    if (studentSelect.length) {
        if (!isOnline) {
            // Add offline indicator to Select2
            studentSelect.next('.select2-container').find('.select2-selection').addClass('offline-select');
            
            // Make sure we have offline data available
            if (typeof studentsData === 'undefined') {
                try {
                    const cachedData = localStorage.getItem('studentsData');
                    if (cachedData) {
                        window.studentsData = JSON.parse(cachedData);
                        populateStudentSelectFromCache();
                    }
                } catch (error) {
                    console.error('Error loading cached student data:', error);
                }
            }
        } else {
            // Remove offline indicator from Select2
            studentSelect.next('.select2-container').find('.select2-selection').removeClass('offline-select');
        }
    }
}
</script>

<!-- Add offline mode indicator -->
<div id="offline-mode-indicator" class="alert alert-warning d-none mb-3">
    <i class="fas fa-wifi-slash me-2"></i>
    <strong>You are offline.</strong> Attendance records will be stored locally and synced when you're back online.
</div>

<!-- Add container for offline alerts -->
<div id="offline-alert-container" class="position-fixed bottom-0 end-0 p-3" style="z-index: 1050;"></div>

<!-- Offline utilities -->
<script>
// Utility function to safely get element values
function safeGetElementValue(elementId, defaultValue = '') {
    const element = document.getElementById(elementId);
    const value = element ? element.value : defaultValue;
    
    // Debug logging for troubleshooting
    if (!element) {
        console.warn(`⚠ Element not found: ${elementId}`);
    } else if (!element.value && elementId.includes('lrn')) {
        console.log(`ℹ Element '${elementId}' found but empty`);
    }
    
    return value;
}

// Utility function to safely set element values
function safeSetElementValue(elementId, value) {
    const element = document.getElementById(elementId);
    if (element) {
        element.value = value;
        return true;
    } else {
        console.warn(`Element with ID '${elementId}' not found`);
        return false;
    }
}

// Initialize offline utilities
window.offlineUtils = {
    // Save attendance data for offline processing
    saveAttendanceData: function(data) {
        return new Promise((resolve, reject) => {
            if (!window.indexedDB) {
                reject(new Error('IndexedDB not supported in this browser'));
                return;
            }
            
            // Open IndexedDB
            const request = indexedDB.open('kes-smart-offline', 1);
            
            request.onerror = function(event) {
                reject(new Error('Failed to open database: ' + event.target.errorCode));
            };
            
            request.onupgradeneeded = function(event) {
                const db = event.target.result;
                
                // Create object store for attendance data if it doesn't exist
                if (!db.objectStoreNames.contains('attendance')) {
                    const store = db.createObjectStore('attendance', { keyPath: 'id', autoIncrement: true });
                    store.createIndex('timestamp', 'timestamp', { unique: false });
                    store.createIndex('synced', 'synced', { unique: false });
                }
            };
            
            request.onsuccess = function(event) {
                const db = event.target.result;
                
                // Add data to attendance store
                const transaction = db.transaction(['attendance'], 'readwrite');
                const store = transaction.objectStore('attendance');
                
                // Add SMS notification flag to data
                data.sms_pending = true;
                data.synced = false;
                
                const addRequest = store.add(data);
                
                addRequest.onsuccess = function() {
                    resolve({ success: true, id: addRequest.result });
                };
                
                addRequest.onerror = function(event) {
                    reject(new Error('Failed to store attendance data: ' + event.target.errorCode));
                };
                
                transaction.oncomplete = function() {
                    db.close();
                };
            };
        });
    },
    
    // Get all unsynced attendance data
    getUnsyncedAttendance: function() {
        return new Promise((resolve, reject) => {
            if (!window.indexedDB) {
                reject(new Error('IndexedDB not supported in this browser'));
                return;
            }
            
            const request = indexedDB.open('kes-smart-offline', 1);
            
            request.onerror = function(event) {
                reject(new Error('Failed to open database: ' + event.target.errorCode));
            };
            
            request.onsuccess = function(event) {
                const db = event.target.result;
                const transaction = db.transaction(['attendance'], 'readonly');
                const store = transaction.objectStore('attendance');
                const index = store.index('synced');
                
                const getRequest = index.getAll(IDBKeyRange.only(false));
                
                getRequest.onsuccess = function() {
                    resolve(getRequest.result);
                };
                
                getRequest.onerror = function(event) {
                    reject(new Error('Failed to get unsynced attendance data: ' + event.target.errorCode));
                };
                
                transaction.oncomplete = function() {
                    db.close();
                };
            };
        });
    },
    
    // Mark attendance data as synced
    markAttendanceSynced: function(id) {
        return new Promise((resolve, reject) => {
            if (!window.indexedDB) {
                reject(new Error('IndexedDB not supported in this browser'));
                return;
            }
            
            const request = indexedDB.open('kes-smart-offline', 1);
            
            request.onerror = function(event) {
                reject(new Error('Failed to open database: ' + event.target.errorCode));
            };
            
            request.onsuccess = function(event) {
                const db = event.target.result;
                const transaction = db.transaction(['attendance'], 'readwrite');
                const store = transaction.objectStore('attendance');
                
                const getRequest = store.get(id);
                
                getRequest.onsuccess = function() {
                    const data = getRequest.result;
                    if (data) {
                        data.synced = true;
                        store.put(data);
                        resolve(true);
                    } else {
                        reject(new Error('Attendance record not found'));
                    }
                };
                
                getRequest.onerror = function(event) {
                    reject(new Error('Failed to get attendance data: ' + event.target.errorCode));
                };
                
                transaction.oncomplete = function() {
                    db.close();
                };
            };
        });
    },
    
    // Sync all unsynced attendance data
    syncAttendanceData: function() {
        return new Promise((resolve, reject) => {
            if (!navigator.onLine) {
                reject(new Error('Cannot sync while offline'));
                return;
            }
            
            this.getUnsyncedAttendance()
                .then(records => {
                    if (records.length === 0) {
                        resolve({ success: true, message: 'No records to sync', count: 0 });
                        return;
                    }
                    
                    // Send records to server
                    return fetch('api/sync-attendance.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({ offlineData: records })
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Server responded with status: ' + response.status);
                        }
                        return response.json();
                    })
                    .then(result => {
                        if (result.success) {
                            // Mark records as synced
                            const markPromises = records.map(record => this.markAttendanceSynced(record.id));
                            return Promise.all(markPromises)
                                .then(() => {
                                    resolve({
                                        success: true,
                                        message: `Synced ${result.success_count} records successfully`,
                                        count: result.success_count,
                                        results: result.results
                                    });
                                });
                        } else {
                            throw new Error(result.message || 'Sync failed');
                        }
                    });
                })
                .catch(error => {
                    reject(error);
                });
        });
    }
};

// Register sync event
if ('serviceWorker' in navigator && 'SyncManager' in window) {
    navigator.serviceWorker.ready.then(registration => {
        // Register sync handler
        navigator.serviceWorker.addEventListener('message', event => {
            if (event.data && event.data.type === 'sync-attendance') {
                window.offlineUtils.syncAttendanceData()
                    .then(result => {
                        console.log('Background sync completed:', result);
                    })
                    .catch(error => {
                        console.error('Background sync failed:', error);
                    });
            }
        });
    });
}

// Database troubleshooting functions
window.fixOfflineDatabase = function() {
    console.log('🔧 Starting database troubleshooting...');
    
    if (typeof window.resetEnhancedCacheDB === 'function') {
        console.log('🔄 Using enhanced cache manager reset...');
        return window.resetEnhancedCacheDB()
            .then(() => {
                console.log('✅ Database reset successful!');
                alert('Database reset successful! The page will now reload.');
                window.location.reload();
            })
            .catch(error => {
                console.error('❌ Enhanced reset failed:', error);
                alert('Database reset failed: ' + error.message);
            });
    }
    
    // Fallback reset method
    console.log('🔄 Using fallback reset method...');
    
    // Close any existing connections
    if (window.db) {
        window.db.close();
        window.db = null;
    }
    
    // Delete the database
    const deleteRequest = indexedDB.deleteDatabase('kes-smart-offline-data');
    
    deleteRequest.onsuccess = () => {
        console.log('✅ Database deleted successfully!');
        alert('Database reset successful! The page will now reload.');
        window.location.reload();
    };
    
    deleteRequest.onerror = (event) => {
        console.error('❌ Failed to delete database:', event.target.error);
        alert('Failed to reset database: ' + event.target.error);
    };
    
    deleteRequest.onblocked = () => {
        console.warn('⚠ Database deletion blocked');
        alert('Database reset is blocked. Please close all other tabs of this website and try again.');
    };
};

// Add database troubleshooting info to console
console.log('📚 DATABASE TROUBLESHOOTING COMMANDS:');
console.log('- fixOfflineDatabase() - Reset the offline database if you\'re having issues');
console.log('- window.db - Current database connection');
console.log('- window.STORE_NAMES - Available database stores');

// Auto-fix if there are critical database errors
if (typeof window.addEventListener === 'function') {
    window.addEventListener('error', function(event) {
        const errorMessage = event.message || '';
        if (errorMessage.includes('object stores was not found') || 
            errorMessage.includes('IDBDatabase') ||
            errorMessage.includes('IndexedDB')) {
            console.error('🚨 Critical database error detected:', errorMessage);
            console.log('💡 Try running fixOfflineDatabase() to resolve the issue');
            
            // Show user-friendly error message
            if (typeof showToast === 'function') {
                showToast('Database error detected. Please refresh the page or contact support.', 'danger');
            }
        }
    });
}

// Cleanup scanner on page unload to prevent state issues
window.addEventListener('beforeunload', function() {
    if (scanner && scanner.isScanning) {
        try {
            scanner.stop();
            console.log('Scanner stopped on page unload');
        } catch (error) {
            console.log('Error stopping scanner on unload:', error);
        }
    }
});

// Also handle visibility change (when tab becomes hidden)
document.addEventListener('visibilitychange', function() {
    if (document.hidden && scanner && scanner.isScanning) {
        try {
            console.log('Page hidden, stopping scanner to prevent issues');
            stopScanner();
        } catch (error) {
            console.log('Error stopping scanner on visibility change:', error);
        }
    }
});

// Enhanced error handling for null reference errors and IndexedDB issues
window.addEventListener('error', function(event) {
    const errorMessage = event.message || '';
    const errorLine = event.lineno || 0;
    const errorFile = event.filename || '';
    
    if (errorMessage.includes('Cannot read properties of null') || 
        errorMessage.includes('reading \'value\'')) {
        console.error('🚨 NULL REFERENCE ERROR DETECTED:');
        console.error('  Message:', errorMessage);
        console.error('  Line:', errorLine);
        console.error('  File:', errorFile);
        console.error('  This is likely a missing form element. Check your HTML for missing IDs.');
        
        // Show user-friendly message
        if (typeof showToast === 'function') {
            showToast('Form element not found. Please refresh the page.', 'warning');
        }
        
        // Prevent further errors
        event.preventDefault();
        return true;
    }
    
    // Handle IndexedDB "not a valid key" errors
    if (errorMessage.includes('not a valid key') || errorMessage.includes('DataError')) {
        console.warn('🛡 IndexedDB error detected, attempting automatic fix...');
        
        // Try to clean corrupted records
        if (typeof window.cleanCorruptedAttendanceRecords === 'function') {
            window.cleanCorruptedAttendanceRecords().then(result => {
                if (result.cleaned > 0) {
                    console.log(`✅ Auto-fixed ${result.cleaned} corrupted records`);
                    showToast('Database issues fixed automatically!', 'success');
                } else {
                    console.log('ℹ️ No corrupted records found to fix');
                }
            }).catch(error => {
                console.warn('⚠️ Auto-fix failed:', error.message);
                showToast('Database error detected. Please refresh the page.', 'warning');
            });
        }
        
        // Prevent further errors
        event.preventDefault();
        return true;
    }
});

// Function to reset scanner state (for debugging stuck states)
window.resetScannerState = function() {
    console.log('🔄 Manually resetting scanner state...');
    isTransitioning = false;
    scannerState = 'stopped';
    window.lastTransitionStart = null;
    console.log('✅ Scanner state reset. Try startScanner() again.');
};

// Function to show current scanner state
window.getScannerState = function() {
    console.log('📊 SCANNER STATE INFO:');
    console.log('  - isTransitioning:', isTransitioning);
    console.log('  - scannerState:', scannerState);
    console.log('  - scanner.isScanning:', scanner ? scanner.isScanning : 'scanner not initialized');
    console.log('  - lastTransitionStart:', window.lastTransitionStart);
    if (window.lastTransitionStart) {
        const duration = Date.now() - window.lastTransitionStart;
        console.log('  - transition duration:', Math.round(duration/1000) + 's');
    }
};

console.log('QR Scanner state management initialized');
console.log('Available commands: startScanner(), stopScanner(), fixOfflineDatabase()');
console.log('Debug commands: resetScannerState(), getScannerState()');
console.log('Time debugging: testTimeRestriction(), checkCurrentTime()');
console.log('Enhanced null reference protection enabled');
console.log('');
console.log('TROUBLESHOOTING:');
console.log('   If you see "not a valid key" errors, try:');
console.log('   - window.cleanCorruptedAttendanceRecords()');
console.log('   - window.resetEnhancedCacheDB()');
console.log('   - Or just refresh the page (auto-fix enabled)');

// Auto-check for 4:30 PM every minute to trigger auto-absent
setInterval(checkAutoAbsent, 60000); // Check every minute

// Also check immediately when page loads (after delay)
setTimeout(() => {
    checkAutoAbsent();
    console.log('Auto-absent check initialized - will trigger at 4:30 PM');
}, 2000);

// Function to test time restrictions manually
window.testTimeRestriction = function(testHour, testMinute) {
    console.log(`Testing time restriction for ${testHour}:${testMinute}`);
    const isRestricted = testHour < 6 || testHour > 16 || (testHour === 16 && testMinute > 15);
    console.log(`Time ${testHour}:${testMinute} - Restricted: ${isRestricted}`);
    return !isRestricted;
};

// Function to check current time and restrictions
window.checkCurrentTime = function() {
    const now = new Date();
    const hour = now.getHours();
    const minute = now.getMinutes();
    const timeString = now.toLocaleTimeString([], {hour: '2-digit', minute: '2-digit', hour12: true});
    const isRestricted = hour < 6 || hour > 16 || (hour === 16 && minute > 30);
    
    console.log('=== CURRENT TIME STATUS ===');
    console.log(`Current time: ${timeString} (${hour}:${minute})`);
    console.log(`Time restricted: ${isRestricted}`);
    console.log(`Should allow recording: ${!isRestricted}`);
    console.log('=== BUTTON STATUS ===');
    const btn = document.querySelector('#lrn-form button[type="submit"]');
    if (btn) {
        console.log(`Button disabled: ${btn.disabled}`);
        console.log(`Button text: ${btn.textContent}`);
    } else {
        console.log('Button not found!');
    }
    
    // Check if we should trigger auto-absent
    checkAutoAbsent();
    
    return !isRestricted;
};

// Function to check and trigger auto-absent after 4:30 PM
function checkAutoAbsent() {
    const now = new Date();
    const currentTime = now.getHours() * 100 + now.getMinutes();
    const absentCutoff = 1630; // 4:30 PM
    const dayOfWeek = now.getDay(); // 0 = Sunday, 1 = Monday, ..., 6 = Saturday
    
    // Only trigger auto-absent after 4:30 PM on weekdays (Monday to Friday)
    if (currentTime >= absentCutoff && dayOfWeek >= 1 && dayOfWeek <= 5) {
        // Check if we haven't already triggered it today
        const today = now.toISOString().split('T')[0];
        const lastAutoAbsentDate = localStorage.getItem('lastAutoAbsentDate');
        
        if (lastAutoAbsentDate !== today) {
            console.log('Triggering auto-absent marking after 4:30 PM...');
            triggerAutoAbsent();
            localStorage.setItem('lastAutoAbsentDate', today);
        }
    }
}

// Function to trigger auto-absent API
async function triggerAutoAbsent() {
    try {
        console.log('QR Scanner: Calling auto-absent API...');
        
        const response = await fetch('api/auto-absent.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                trigger_source: 'qr_scanner'
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            console.log('QR Scanner: Auto-absent completed:', data);
            
            // Show notification if students were marked absent
            if (data.data && data.data.total_students_marked > 0) {
                const studentsCount = data.data.total_students_marked;
                const recordsCount = data.data.total_attendance_records;
                const message = `Auto-absent completed: ${studentsCount} student${studentsCount > 1 ? 's' : ''} marked absent across ${recordsCount} subject record${recordsCount > 1 ? 's' : ''} after 4:30 PM`;
                
                showToast(message, 'info');
                
                // Show details of processed students in console
                if (data.data.processed_students && data.data.processed_students.length > 0) {
                    console.log('QR Scanner: Students marked absent:');
                    data.data.processed_students.forEach(student => {
                        const subjectsText = student.absent_subjects ? 
                            student.absent_subjects.map(s => s.subject_name).join(', ') : 
                            'Unknown subjects';
                        console.log(`- ${student.name} (${student.username}) from ${student.section}: ${subjectsText}`);
                    });
                }
            } else {
                console.log('QR Scanner: Auto-absent check completed - no students to mark absent');
            }
        } else {
            console.log('QR Scanner: Auto-absent API response:', data.message);
            
            // Only show error toast for actual errors, not expected scenarios
            if (!data.data || (!data.data.already_processed && !data.data.is_weekend)) {
                showToast('Auto-absent check failed: ' + data.message, 'warning');
            }
        }
    } catch (error) {
        console.error('QR Scanner: Error calling auto-absent API:', error);
        showToast('Failed to check auto-absent status', 'danger');
    }
}

// Manual Auto-Absent Trigger Functions
function showManualTriggerConfirmation() {
    // Create confirmation modal
    const modalHtml = `
        <div class="modal fade" id="manualTriggerModal" tabindex="-1" aria-labelledby="manualTriggerModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-warning">
                        <h5 class="modal-title text-dark" id="manualTriggerModalLabel">
                            <i class="fas fa-exclamation-triangle me-2"></i>Manual Auto-Absent Confirmation
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-warning">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>This action will:</strong>
                        </div>
                        <ul class="list-unstyled">
                            <li><i class="fas fa-check text-success me-2"></i>Mark all students as <strong>absent</strong> who haven't checked in today</li>
                            <li><i class="fas fa-check text-success me-2"></i>Create absent records for <strong>all subjects</strong> they should attend</li>
                            <li><i class="fas fa-check text-success me-2"></i>Send <strong>SMS notifications</strong> to parents</li>
                            <li><i class="fas fa-check text-success me-2"></i>Apply only to <strong>weekdays</strong> (Monday-Friday)</li>
                        </ul>
                        <div class="alert alert-info">
                            <i class="fas fa-calendar-check me-2"></i>
                            <strong>Today:</strong> <span id="confirmationDate">${new Date().toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}</span>
                        </div>
                        <p class="text-muted mb-0">
                            <small>This process cannot be undone. Please make sure this is the correct action to take.</small>
                        </p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>Cancel
                        </button>
                        <button type="button" class="btn btn-warning" onclick="executeManualTrigger()">
                            <i class="fas fa-user-times me-2"></i>Mark Students Absent
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Remove existing modal if present
    const existingModal = document.getElementById('manualTriggerModal');
    if (existingModal) {
        existingModal.remove();
    }
    
    // Add modal to body
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    
    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('manualTriggerModal'));
    modal.show();
    
    // Remove modal from DOM when hidden
    document.getElementById('manualTriggerModal').addEventListener('hidden.bs.modal', function() {
        this.remove();
    });
}

async function executeManualTrigger() {
    const modal = bootstrap.Modal.getInstance(document.getElementById('manualTriggerModal'));
    const btn = document.getElementById('manualAutoAbsentBtn');
    const statusDiv = document.getElementById('manualTriggerStatus');
    const statusText = document.getElementById('manualTriggerStatusText');
    const progressDiv = document.getElementById('manualTriggerProgress');
    const progressBar = document.getElementById('manualTriggerProgressBar');
    const progressText = document.getElementById('manualTriggerProgressText');
    
    try {
        // Close modal
        modal.hide();
        
        // Disable button and show progress
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing...';
        
        // Show status
        statusDiv.style.display = 'block';
        statusDiv.className = 'alert alert-info py-2 mb-0';
        statusText.textContent = 'Initiating manual auto-absent process...';
        
        // Show progress bar
        progressDiv.style.display = 'block';
        progressBar.style.width = '25%';
        progressText.textContent = 'Calling auto-absent API...';
        
        console.log('QR Scanner: Manual auto-absent trigger initiated...');
        
        // Call the auto-absent API
        const response = await fetch('api/auto-absent.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                trigger_source: 'manual_qr_scanner',
                manual_trigger: true
            })
        });
        
        // Update progress
        progressBar.style.width = '75%';
        progressText.textContent = 'Processing response...';
        
        const data = await response.json();
        
        // Complete progress
        progressBar.style.width = '100%';
        progressText.textContent = 'Complete!';
        
        if (data.success) {
            console.log('QR Scanner: Manual auto-absent completed:', data);
            
            // Show success status
            statusDiv.className = 'alert alert-success py-2 mb-0';
            
            if (data.data && data.data.total_students_marked > 0) {
                const studentsCount = data.data.total_students_marked;
                const recordsCount = data.data.total_attendance_records;
                const smsCount = data.data.sms_sent;
                
                statusText.innerHTML = `
                    <strong>✅ Success!</strong> ${studentsCount} student${studentsCount > 1 ? 's' : ''} marked absent 
                    across ${recordsCount} subject record${recordsCount > 1 ? 's' : ''}. 
                    ${smsCount} SMS notification${smsCount > 1 ? 's' : ''} sent.
                `;
                
                // Show detailed results in console
                console.log('Manual auto-absent results:', {
                    students_marked: studentsCount,
                    attendance_records: recordsCount,
                    sms_sent: smsCount,
                    processed_students: data.data.processed_students
                });
                
                // Show detailed student list
                if (data.data.processed_students && data.data.processed_students.length > 0) {
                    console.log('Students marked absent:');
                    data.data.processed_students.forEach(student => {
                        const subjectsText = student.absent_subjects ? 
                            student.absent_subjects.map(s => s.subject_name).join(', ') : 
                            'Unknown subjects';
                        console.log(`- ${student.name} (${student.username}): ${subjectsText}`);
                    });
                }
                
                // Show success toast
                showToast(`Manual auto-absent completed! ${studentsCount} students marked absent.`, 'success');
                
                // Auto-hide status after 10 seconds
                setTimeout(() => {
                    statusDiv.style.display = 'none';
                    progressDiv.style.display = 'none';
                }, 10000);
            } else {
                // No students to mark absent
                statusText.innerHTML = `<strong>ℹ️ No Action Needed:</strong> ${data.message}`;
                
                if (data.data && data.data.already_processed) {
                    statusText.innerHTML += ' Auto-absent has already been processed today.';
                }
                
                console.log('QR Scanner: Manual auto-absent - no students to process');
                
                // Auto-hide status after 5 seconds
                setTimeout(() => {
                    statusDiv.style.display = 'none';
                    progressDiv.style.display = 'none';
                }, 5000);
            }
        } else {
            // Handle API errors
            statusDiv.className = 'alert alert-warning py-2 mb-0';
            statusText.innerHTML = `<strong>⚠️ Notice:</strong> ${data.message}`;
            
            console.log('QR Scanner: Manual auto-absent API response:', data.message);
            
            // Don't show error toast for expected scenarios (weekend, already processed, too early)
            if (data.data && (data.data.is_weekend || data.data.already_processed)) {
                // These are expected scenarios, not errors
                statusText.innerHTML += ' This is normal.';
            } else {
                // Show warning for other issues
                showToast('Auto-absent notice: ' + data.message, 'warning');
            }
            
            // Auto-hide status after 7 seconds
            setTimeout(() => {
                statusDiv.style.display = 'none';
                progressDiv.style.display = 'none';
            }, 7000);
        }
    } catch (error) {
        console.error('QR Scanner: Manual auto-absent error:', error);
        
        // Show error status
        statusDiv.className = 'alert alert-danger py-2 mb-0';
        statusText.innerHTML = `<strong>❌ Error:</strong> Failed to process manual auto-absent. Please try again.`;
        
        showToast('Failed to execute manual auto-absent', 'danger');
        
        // Auto-hide status after 7 seconds
        setTimeout(() => {
            statusDiv.style.display = 'none';
            progressDiv.style.display = 'none';
        }, 7000);
    } finally {
        // Re-enable button
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-user-times me-2"></i><span class="d-none d-sm-inline">Mark Absent </span>Students';
        
        // Hide progress after delay
        setTimeout(() => {
            progressDiv.style.display = 'none';
        }, 3000);
    }
}
</script>