<?php
require_once 'config.php';
require_once 'sms_functions.php';

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
    $scan_location = sanitize_input($_POST['scan_location'] ?? 'Main Gate');
    $scan_notes = sanitize_input($_POST['scan_notes'] ?? '');
    
    try {
        // Validate QR data format (should be student ID or username)
        if (empty($qr_data)) {
            throw new Exception('Invalid QR code data.');
        }
        
        // Find student by QR code
        $stmt = $pdo->prepare("SELECT id, username, full_name, lrn, section_id FROM users WHERE qr_code = ? AND role = 'student'");
        $stmt->execute([$qr_data]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$student) {
            throw new Exception('Student not found for this QR code.');
        }
        
        // Process attendance for the found student
        $result = processStudentAttendance($pdo, $student, $current_user, $user_role, $scan_location, $scan_notes, true);
        
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
        
        // Find student by LRN
        $stmt = $pdo->prepare("SELECT id, username, full_name, lrn, section_id FROM users WHERE lrn = ? AND role = 'student' AND status = 'active'");
        $stmt->execute([$lrn]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$student) {
            throw new Exception('Student not found with LRN: ' . $lrn);
        }
        
        // Process attendance for the found student
        $result = processStudentAttendance($pdo, $student, $current_user, $user_role, $scan_location, $scan_notes, false);
        
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
function processStudentAttendance($pdo, $student, $current_user, $user_role, $scan_location, $scan_notes, $is_qr_scan) {
    // Check if teacher has permission to scan this student
    if ($user_role == 'teacher') {
        $check_stmt = $pdo->prepare("SELECT 1 FROM users u JOIN sections s ON u.section_id = s.id WHERE u.id = ? AND s.teacher_id = ?");
        $check_stmt->execute([$student['id'], $current_user['id']]);
        if (!$check_stmt->fetch()) {
            throw new Exception('You can only scan students in your section.');
        }
    }
    
    // Check if attendance already recorded for today
    $today = date('Y-m-d');
    $current_time = date('H:i:s');
    $current_time_formatted = date('g:i A');
    
    // Define time boundaries
    $late_threshold = '07:15:00';  // 7:15 AM
    $absent_cutoff = '16:15:00';   // 4:15 PM
    
    $check_attendance = $pdo->prepare("SELECT id, status, time_in, time_out FROM attendance WHERE student_id = ? AND attendance_date = ?");
    $check_attendance->execute([$student['id'], $today]);
    $existing_attendance = $check_attendance->fetch(PDO::FETCH_ASSOC);
    
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
                // Normal checkout after 4:15 PM
                $update_stmt = $pdo->prepare("UPDATE attendance SET time_out = NOW(), teacher_id = ?, remarks = CONCAT(IFNULL(remarks, ''), ' | Checkout: ', ?) WHERE id = ?");
                $update_stmt->execute([$current_user['id'], $scan_location . ($scan_notes ? ' - ' . $scan_notes : ''), $existing_attendance['id']]);
                $attendance_status = $existing_attendance['status']; // Keep original status
            }
            $attendance_id = $existing_attendance['id'];
            $is_checkout = true;
        } elseif ($existing_attendance['time_out']) {
            // Student has already checked in and out today
            $student_name = $student['full_name'];
            $time_in = date('g:i A', strtotime($existing_attendance['time_in']));
            $time_out = date('g:i A', strtotime($existing_attendance['time_out']));
            $status = ucfirst($existing_attendance['status']);
            
            return [
                'success' => false,
                'message' => "Student {$student_name} has already checked in and out today. Check-in: {$time_in}, Check-out: {$time_out}",
                'student_id' => $student['id'],
                'student_name' => $student_name,
                'student_lrn' => $student['lrn'] ?? null,
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
        // No existing attendance record
        if ($current_time > $absent_cutoff) {
            throw new Exception('Attendance recording period has ended. Students cannot check in after 4:15 PM.');
        }
        
        // Determine status based on time
        if ($current_time <= $late_threshold) {
            $attendance_status = 'present';
        } else {
            $attendance_status = 'late';
        }
        
        // Create new attendance record (check-in only)
        $insert_stmt = $pdo->prepare("INSERT INTO attendance (student_id, teacher_id, section_id, attendance_date, time_in, status, remarks, qr_scanned) VALUES (?, ?, ?, ?, NOW(), ?, ?, ?)");
        $insert_stmt->execute([$student['id'], $current_user['id'], $student['section_id'], $today, $attendance_status, $scan_location . ($scan_notes ? ' - ' . $scan_notes : ''), $is_qr_scan ? 1 : 0]);
        $attendance_id = $pdo->lastInsertId();
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
    
    // Prepare response
    return [
        'success' => true,
        'student_name' => $student['full_name'],
        'student_id' => $student['username'],
        'student_lrn' => $student['lrn'] ?? null,
        'section' => $section_name,
        'time' => $current_time_formatted,
        'date' => $current_date,
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

// Get SMS configuration status
$sms_config = null;
try {
    $sms_config = $pdo->query("SELECT * FROM sms_config WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    // SMS config not available
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
                    SMS Status: 
                    <span class="badge bg-<?php echo ($sms_config && $sms_config['status'] == 'active') ? 'success' : 'danger'; ?>">
                        <?php echo ($sms_config && $sms_config['status'] == 'active') ? 'Active' : 'Inactive'; ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Scanner Status -->
<div class="row g-3 mb-4">
    <div class="col-12">
        <div class="alert alert-info" id="scannerStatus">
            <i class="fas fa-info-circle me-2"></i>
            <strong>Ready to scan:</strong> Click "Start Scanner" to begin scanning or use manual LRN entry below.
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
                <div id="scan-region" class="position-relative mobile-optimized mb-3">
                    <!-- QR Scanner will be rendered here -->
                    <div class="text-center py-4">
                        <i class="fas fa-camera fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">Scanner Ready</h5>
                    </div>
                </div>
                
                <!-- Scanner Controls -->
                <div class="mb-3 d-flex justify-content-center">
                    <div class="btn-group">
                        <button id="flash-toggle" class="btn btn-outline-secondary">
                            <i class="fas fa-bolt me-1"></i>Flash
                        </button>
                    </div>
                </div>
            </div>
        </div>
                
        <!-- Manual LRN Input -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-light">
                <h6 class="card-title mb-0">
                    <i class="fas fa-keyboard me-2"></i>Manual LRN Entry
                </h6>
            </div>
            <div class="card-body p-3">
                <form id="lrn-form">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0"><i class="fas fa-id-card text-muted"></i></span>
                        <input type="text" class="form-control border-start-0 ps-0" id="lrn-input" placeholder="Enter student LRN...">
                        <button class="btn btn-primary" type="submit">
                            <i class="fas fa-user-check me-1 d-none d-lg-inline"></i>
                            <span class="d-none d-sm-inline">Check </span>In/Out
                        </button>
                    </div>
                    <small class="text-muted d-block mt-2">
                        <i class="fas fa-info-circle me-1"></i>
                        Enter the student's LRN to record attendance manually
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
<div class="toast-container position-fixed bottom-0 end-0 p-3" id="toast-container"></div>

<!-- Include Google QR Scanner Library -->
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>

<!-- QR Scanner Script -->
<script>
    let scanner = null;
    let cameras = [];
    let selectedCamera = 0;
    
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
            const flashToggle = document.getElementById('flash-toggle');
            const camSwitch = document.getElementById('cam-switch');
            
            if (!camList || !flashToggle || !camSwitch) {
                console.error('One or more scanner control elements not found');
            }
            
            // Create HTML5 QR scanner element
            scanRegion.innerHTML = '<div id="qr-reader" style="width: 100%"></div>';
            
            // Initialize the scanner with HTML5QrCode library
            const html5QrCode = new Html5Qrcode("qr-reader");
            scanner = html5QrCode;
            
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
                    
                    // Start scanner with default camera
                    startScanner();
                } else {
                    // No cameras available
                    scanRegion.innerHTML = '<div class="alert alert-danger">No cameras found. Please allow camera access.</div>';
                }
            }).catch(err => {
                console.error('Error listing cameras:', err);
                scanRegion.innerHTML = '<div class="alert alert-danger">Error accessing camera: ' + err.message + '</div>';
            });
            
            // Camera switch button
            if (camSwitch) {
                camSwitch.addEventListener('click', () => {
                    selectedCamera = (selectedCamera + 1) % cameras.length;
                    if (camList) {
                        camList.value = cameras[selectedCamera].id;
                    }
                    startScanner();
                });
            }
            
            // Camera selection change
            if (camList) {
                camList.addEventListener('change', () => {
                    const cameraId = camList.value;
                    selectedCamera = cameras.findIndex(cam => cam.id === cameraId);
                    if (selectedCamera === -1) selectedCamera = 0;
                    startScanner();
                });
            }
            
            // Flash toggle - HTML5QrCode handles this differently
            if (flashToggle) {
                flashToggle.addEventListener('click', () => {
                    try {
                        scanner.toggleFlash()
                            .then(() => {
                                const icon = flashToggle.querySelector('i');
                                if (icon) {
                                    icon.classList.toggle('fa-bolt');
                                    icon.classList.toggle('fa-bolt-slash');
                                }
                            })
                            .catch(err => {
                                console.error('Flash error:', err);
                                showToast('Flash not available on this device', 'warning');
                            });
                    } catch (err) {
                        console.error('Flash toggle error:', err);
                        showToast('Flash not available on this device', 'warning');
                    }
                });
            }
            
            // Start/Stop scanner buttons
            const startScanBtn = document.getElementById('startScanBtn');
            const stopScanBtn = document.getElementById('stopScanBtn');
            const scannerStatus = document.getElementById('scannerStatus');
            
            if (startScanBtn && stopScanBtn && scannerStatus) {
                startScanBtn.addEventListener('click', function() {
                    this.style.display = 'none';
                    stopScanBtn.style.display = 'inline-block';
                    scannerStatus.innerHTML = '<i class="fas fa-camera me-2"></i><strong>Scanner active:</strong> Point your camera at a student QR code.';
                    scannerStatus.className = 'alert alert-success';
                    startScanner();
                });
                
                stopScanBtn.addEventListener('click', function() {
                    this.style.display = 'none';
                    startScanBtn.style.display = 'inline-block';
                    scannerStatus.innerHTML = '<i class="fas fa-info-circle me-2"></i><strong>Scanner stopped:</strong> Click "Start Scanner" to begin scanning.';
                    scannerStatus.className = 'alert alert-info';
                    if (scanner && scanner.isScanning) {
                        scanner.stop();
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
    
        try {
            // Stop any existing scan
            if (scanner.isScanning) {
                scanner.stop()
                    .then(() => startNewScan())
                    .catch(err => {
                        console.error('Error stopping scanner:', err);
                        startNewScan();
                    });
            } else {
                startNewScan();
            }
        } catch (error) {
            console.error('Error starting scanner:', error);
            showToast('Error starting scanner: ' + error.message, 'danger');
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
            qrbox: { width: 250, height: 250 },
            aspectRatio: 1.0
        };
        
        scanner.start(
            cameraId, 
            config,
            handleScanResult,
            handleScanError
        )
        .catch(err => {
            console.error('Error starting camera:', err);
            showToast('Error starting camera: ' + err.message, 'danger');
        });
    }
    
    // Handle scan error
    function handleScanError(err) {
        console.error('QR scan error:', err);
        // Don't show error to user for every frame
    }
    
    // Handle scan result
    function handleScanResult(qrCodeMessage) {
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
        
        // Check if we're online or offline
        if (!navigator.onLine) {
            // Handle offline scanning
            handleOfflineScan(qrCodeMessage, scanLocation, scanNotes);
        return;
    }
    
        // Process QR scan online
        const formData = new FormData();
        formData.append('action', 'scan_qr');
        formData.append('qr_data', qrCodeMessage);
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
    
    // Handle offline scan
    function handleOfflineScan(qrData, scanLocation, scanNotes) {
        try {
            // Extract student ID from QR code (assuming format contains student ID)
            let studentId = null;
            
            // Try to parse the QR data (could be JSON or plain text)
            try {
                const qrJson = JSON.parse(qrData);
                studentId = qrJson.id || qrJson.student_id;
        } catch (e) {
                // Not JSON, try to extract ID from string
                // Assuming QR code contains student ID in some format
                studentId = qrData;
            }
            
            if (!studentId) {
                throw new Error('Could not extract student ID from QR code');
            }
            
            // Use the offline utilities to save attendance data
            if (typeof window.offlineUtils !== 'undefined') {
                const offlineData = {
                    student_id: studentId,
                    action: 'scan',
                    timestamp: new Date().toISOString(),
                    location: scanLocation,
                    notes: scanNotes,
                    device_info: {
                        userAgent: navigator.userAgent,
                        platform: navigator.platform
                    }
                };
                
                // Save to IndexedDB
                window.offlineUtils.saveAttendanceData(offlineData)
                    .then(() => {
                        // Register sync when back online
                        if ('serviceWorker' in navigator && 'SyncManager' in window) {
                            navigator.serviceWorker.ready
                                .then(registration => {
                                    return registration.sync.register('sync-attendance');
                                })
                                .catch(err => {
                                    console.error('Sync registration failed:', err);
                                });
                        }
                        
                        // Show success notification
                                    const offlineResultData = {
                success: true,
                message: 'Attendance recorded for offline processing',
                student_id: studentId,
                student_name: 'Student',
                attendance_date: new Date().toISOString().split('T')[0],
                time_in: new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit', hour12: true}),
                            status: 'pending',
                            offline: true
                        };
                        
                        showScanResult(offlineResultData, 'warning');
                    })
                    .catch(error => {
                        console.error('Error saving offline attendance:', error);
                        showToast('Error saving offline attendance: ' + error.message, 'danger');
                    })
                    .finally(() => {
                        // Resume scanning after delay
                        setTimeout(() => {
                            if (scanner && scanner.isPaused) {
                                scanner.resume();
                            }
                        }, 2000);
                    });
            } else {
                throw new Error('Offline utilities not available');
                }
            } catch (error) {
            console.error('Error processing offline scan:', error);
            showToast('Error processing offline scan: ' + error.message, 'danger');
            
            // Resume scanning after delay
                setTimeout(() => {
                if (scanner && scanner.isPaused) {
                    scanner.resume();
                }
            }, 2000);
        }
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
                        <h5 class="card-title">${data.message || 'Scan processed'}</h5>
            `;
        
            if (data.success || data.offline) {
                // Add student details for successful scans
                resultHtml += `
                        <div class="row mt-3">
            <div class="col-6">
                                <p class="mb-1"><strong>Student:</strong> ${data.student_name || 'Unknown'}</p>
                                <p class="mb-1"><strong>Date:</strong> ${data.attendance_date || new Date().toLocaleDateString()}</p>
            </div>
            <div class="col-6">
                                <p class="mb-1"><strong>Time:</strong> ${data.time_in || new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit', hour12: true})}</p>
                                <p class="mb-1"><strong>Status:</strong> ${data.status ? data.status.charAt(0).toUpperCase() + data.status.slice(1) : 'Pending'}</p>
            </div>
        </div>
                `;
            }
            
            resultHtml += `
            </div>
                    <div class="card-footer text-muted">
                        <small>${new Date().toLocaleString()}</small>
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
                        resultHtml += `
                                <div class="row mt-3">
                                    <div class="col-6">
                                        <p class="mb-1"><strong>Student:</strong> ${data.student_name || 'Unknown'}</p>
                                        <p class="mb-1"><strong>Date:</strong> ${data.attendance_date}</p>
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
                                <small>${new Date(data.timestamp).toLocaleString()}</small>
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
                    time_in: data.time_in || new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}),
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
    
    // Show toast notification
    function showToast(message, type = 'info') {
        try {
            const toastContainer = document.getElementById('toast-container');
            if (!toastContainer) {
                console.error('Toast container not found');
                return;
            }
            
            const toast = document.createElement('div');
            toast.className = `toast align-items-center text-white bg-${type} border-0`;
            toast.setAttribute('role', 'alert');
            toast.setAttribute('aria-live', 'assertive');
            toast.setAttribute('aria-atomic', 'true');
            
            toast.innerHTML = `
                <div class="d-flex">
                    <div class="toast-body">
                        ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            `;
            
            toastContainer.appendChild(toast);
            
            // Use Bootstrap's Toast component if available
            if (typeof bootstrap !== 'undefined' && bootstrap.Toast) {
                const bsToast = new bootstrap.Toast(toast, {
                    autohide: true,
                    delay: 3000
                });
                
                bsToast.show();
                
                // Remove toast after it's hidden
                toast.addEventListener('hidden.bs.toast', () => {
                    if (toastContainer.contains(toast)) {
                        toastContainer.removeChild(toast);
                    }
                });
            } else {
                // Fallback if Bootstrap JS is not loaded
                toast.style.display = 'block';
                setTimeout(() => {
                    if (toastContainer.contains(toast)) {
                        toastContainer.removeChild(toast);
                    }
                }, 3000);
            }
        } catch (error) {
            console.error('Error showing toast:', error);
        }
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
        
        // Handle manual LRN form submission
        document.getElementById('lrn-form').addEventListener('submit', function(event) {
            event.preventDefault();
            
            const lrn = document.getElementById('lrn-input').value;
            const scanLocation = document.getElementById('scan-location').value;
            const scanNotes = document.getElementById('scan-notes').value;
            
            // Check if we're online or offline
            if (!navigator.onLine) {
                // Handle offline LRN entry
                try {
                    if (typeof window.offlineUtils !== 'undefined') {
                        const handled = window.offlineUtils.handleOfflineAttendance(lrn, 'manual', {
                            location: scanLocation,
                            notes: scanNotes
                        });
                        
                        if (handled) {
                            // Show offline success notification
                            const offlineData = {
                                success: true,
                                message: 'LRN recorded for offline processing',
                                student_id: lrn,
                                student_name: 'Student',
                                attendance_date: new Date().toISOString().split('T')[0],
                                time_in: new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit', hour12: true}),
                                status: 'pending',
                                offline: true
                            };
                            
                            showScanResult(offlineData, 'warning');
                            document.getElementById('lrn-input').value = '';
                        }
                    } else {
                        throw new Error('Offline utilities not available');
                    }
                } catch (error) {
                    console.error('Error processing offline LRN:', error);
                    showToast('Error processing offline LRN: ' + error.message, 'danger');
                }
                return;
            }
            
            // Process LRN online
            const formData = new FormData();
            formData.append('action', 'scan_lrn');
            formData.append('lrn', lrn);
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
                    document.getElementById('lrn-input').value = '';
            } else {
                    // Show error notification
                    showScanResult(data, 'danger');
            }
        })
        .catch(error => {
                console.error('Error processing LRN:', error);
                showToast('Error processing LRN. Please try again.', 'danger');
            });
        });
    });
    
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
#scanner-container {
    min-height: 300px;
    width: 100%;
    position: relative;
    background: #f8f9fa;
    border: 2px dashed #dee2e6;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
}

#scanner-container.scanning {
    border: 2px solid #007bff;
    background: transparent;
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
}

/* Override QR scanner default styles for mobile */
#scanner-container .html5-qrcode-element {
    width: 100% !important;
}

/* Ensure QR reader and video are properly visible */
#qr-reader {
    width: 100% !important;
    min-height: 300px !important;
    overflow: visible !important;
    position: relative !important;
    border: 1px solid #ddd;
    border-radius: 8px;
}

#qr-reader video {
    width: 100% !important;
    height: auto !important;
    min-height: 300px !important;
    border-radius: 8px;
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
                
                const qrData = document.getElementById('qr_data').value;
                const scanLocation = document.getElementById('scan_location').value;
                const scanNotes = document.getElementById('scan_notes').value;
                
                // Store the scan in offline storage
                handleOfflineQrScan(qrData, scanLocation, scanNotes);
            }
        });
    }
    
    // Add offline mode handling to the LRN form
    const lrnForm = document.getElementById('lrn-form');
    if (lrnForm) {
        lrnForm.addEventListener('submit', function(event) {
            // If offline, intercept the form submission
            if (!navigator.onLine) {
                event.preventDefault();
                
                const lrn = document.getElementById('lrn').value;
                const scanLocation = document.getElementById('lrn_scan_location').value;
                const scanNotes = document.getElementById('lrn_scan_notes').value;
                
                // Store the scan in offline storage
                handleOfflineLrnScan(lrn, scanLocation, scanNotes);
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
        
        // Clear the form
        document.getElementById('qr_data').value = '';
        
        // Add to the recent scans table
        addToRecentScansTable({
            student_name: 'Pending sync...',
            status: 'pending',
            time: new Date().toLocaleTimeString(),
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
        
        // Clear the form
        document.getElementById('lrn').value = '';
        
        // Add to the recent scans table
        addToRecentScansTable({
            student_name: 'Pending sync (LRN: ' + lrn + ')',
            status: 'pending',
            time: new Date().toLocaleTimeString(),
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

// Update UI based on online/offline status
function updateOfflineStatus() {
    const isOnline = navigator.onLine;
    const offlineIndicator = document.getElementById('offline-mode-indicator');
    
    if (offlineIndicator) {
        if (!isOnline) {
            offlineIndicator.classList.remove('d-none');
        } else {
            offlineIndicator.classList.add('d-none');
            
            // Try to sync data when back online
            if (typeof syncOfflineData === 'function') {
                syncOfflineData().then(() => {
                    showOfflineAlert('success', 'Offline attendance records synced successfully.');
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
            button.innerHTML = '<i class="fas fa-wifi-slash me-2"></i>' + button.innerHTML;
            button.classList.add('btn-warning');
            button.classList.remove('btn-primary');
        } else {
            button.innerHTML = button.innerHTML.replace('<i class="fas fa-wifi-slash me-2"></i>', '');
            button.classList.add('btn-primary');
            button.classList.remove('btn-warning');
        }
    });
}
</script>

<!-- Add offline mode indicator -->
<div id="offline-mode-indicator" class="alert alert-warning d-none mb-3">
    <i class="fas fa-wifi-slash me-2"></i>
    <strong>You are offline.</strong> Attendance records will be stored locally and synced when you're back online.
</div>

<!-- Add container for offline alerts -->
<div id="offline-alert-container" class="position-fixed bottom-0 end-0 p-3" style="z-index: 1050;"></div>