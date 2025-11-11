<?php
require_once 'config.php';

// Enhanced authentication check supporting both online and offline modes
$check_offline_auth = false;
$user_role = '';
$current_user = [];

// Check if user is logged in through PHP session (online mode)
if (isLoggedIn() && isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
    // Simply update session activity without forced timeout check
    updateSessionActivity();
    $current_user = getCurrentUser($pdo);
    $user_role = $_SESSION['role'];
} else {
    // Mark that we need to check offline authentication
    $check_offline_auth = true;
}

$page_title = 'Dashboard';
include 'header.php';
?>

<!-- Offline Authentication Script -->
<script src="assets/js/offline-auth.js"></script>
<script>
// Set data attribute for offline auth check
document.body.dataset.checkOfflineAuth = '<?php echo $check_offline_auth ? 'true' : 'false'; ?>';
</script>

<!-- Student ID Card Styles -->
<style>
.student-id-card .card {
    font-family: 'Arial', sans-serif;
}

.photo-frame {
    position: relative;
    overflow: hidden;
    transition: all 0.3s ease;
}

.photo-frame:hover {
    transform: scale(1.02);
}

.student-info .fw-bold {
    letter-spacing: 0.5px;
}

.qr-code-section {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}

#qr-code-small {
    border: 2px solid #007bff;
    border-radius: 8px;
    padding: 4px;
    background: white;
}

.student-id-card .card-body {
    background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
}

/* Time Display Styles */
.current-time-display {
    background: rgba(255, 255, 255, 0.8);
    border-radius: 8px;
    padding: 10px;
    border: 1px solid #e0e0e0;
}

#currentTimeDisplay {
    font-family: 'Courier New', monospace;
    font-size: 1.1rem;
    color: #333;
}

#attendanceStatusDisplay {
    font-weight: bold;
    text-shadow: 0 1px 1px rgba(0,0,0,0.1);
}

.alert-warning .current-time-display {
    background: rgba(255, 255, 255, 0.9);
}

@media (max-width: 768px) {
    .student-id-card {
        max-width: 100% !important;
    }
    
    .photo-frame {
        width: 100px !important;
        height: 120px !important;
    }
    
    .student-info .fw-bold {
        font-size: 0.8rem !important;
    }
    
    .current-time-display {
        margin-top: 10px;
        text-align: center !important;
    }
    
    #qr-code-small {
        width: 70px !important;
        height: 70px !important;
    }
}

.object-fit-cover {
    object-fit: cover;
}

/* Print styles for ID card */
@media print {
    .student-id-card {
        page-break-inside: avoid;
        margin: 0 !important;
        max-width: none !important;
    }
    
    .btn, .alert, .navbar, .footer {
        display: none !important;
    }
}

/* QR Scanner and Generator Styles */
.scanning {
    position: relative;
    overflow: hidden;
}

.scanning::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(0, 123, 255, 0.2), transparent);
    animation: scan 2s infinite;
    z-index: 10;
}

@keyframes scan {
    0% { left: -100%; }
    100% { left: 100%; }
}

.qr-display-container {
    transition: all 0.3s ease;
}

.qr-display-container:hover {
    transform: scale(1.02);
}

.scanner-container {
    position: relative;
}

.session-item {
    transition: all 0.3s ease;
}

.session-item:hover {
    background-color: rgba(0, 123, 255, 0.1);
    border-radius: 5px;
}

.info-item {
    border-bottom: 1px solid #eee;
    padding-bottom: 0.5rem;
}

.info-item:last-child {
    border-bottom: none;
}

/* Mobile optimizations for QR scanner */
@media (max-width: 768px) {
    #student-qr-reader video,
    #teacher-qr-container img {
        max-width: 100% !important;
        height: auto !important;
    }
    
    .scanner-container {
        padding: 0.5rem;
    }
    
    .qr-display-container h6 {
        font-size: 0.9rem;
    }
    
    /* Optimize QR scanner for mobile */
    #student-scan-region {
        min-height: 250px !important;
    }
    
    #student-qr-reader {
        border-radius: 10px;
        overflow: hidden;
    }
    
    #student-qr-reader video {
        border-radius: 10px;
        object-fit: cover;
    }
    
    /* Camera controls styling for mobile */
    .btn-group-mobile {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        justify-content: center;
    }
    
    .btn-group-mobile .btn {
        flex: 1;
        min-width: 100px;
    }
}

/* Scanner video styling */
#student-qr-reader video {
    border-radius: 8px;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

/* Camera info styling */
#studentCameraInfo {
    background: rgba(0, 123, 255, 0.1);
    border-radius: 15px;
    padding: 5px 10px;
    display: inline-block;
}

/* Improved button styling for scanner controls */
#startStudentScanBtn:hover {
    background-color: #218838;
    transform: scale(1.05);
}

#stopStudentScanBtn:hover {
    background-color: #c82333;
    transform: scale(1.05);
}

#switchStudentCameraBtn:hover {
    background-color: #0056b3;
    color: white;
    transform: scale(1.05);
}

/* Loading animation for QR generation */
.qr-loading {
    display: inline-block;
    width: 20px;
    height: 20px;
    border: 3px solid #f3f3f3;
    border-top: 3px solid #007bff;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
</style>

<?php

// Get dashboard statistics based on user role
try {
    if ($user_role == 'admin') {
        $total_students = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student' AND status = 'active'")->fetchColumn();
        $total_teachers = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'teacher' AND status = 'active'")->fetchColumn();
        $total_parents = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'parent' AND status = 'active'")->fetchColumn();
        $total_sections = $pdo->query("SELECT COUNT(*) FROM sections WHERE status = 'active'")->fetchColumn();
        
        $today_attendance = $pdo->query("SELECT COUNT(*) FROM attendance WHERE attendance_date = CURDATE()")->fetchColumn();
        $present_today = $pdo->query("SELECT COUNT(*) FROM attendance WHERE attendance_date = CURDATE() AND status = 'present'")->fetchColumn();
        $absent_today = $pdo->query("SELECT COUNT(*) FROM attendance WHERE attendance_date = CURDATE() AND status = 'absent'")->fetchColumn();
        $late_today = $pdo->query("SELECT COUNT(*) FROM attendance WHERE attendance_date = CURDATE() AND status = 'late'")->fetchColumn();
        
    } elseif ($user_role == 'teacher') {
        $my_sections = $pdo->prepare("SELECT COUNT(*) FROM sections WHERE teacher_id = ? AND status = 'active'");
        $my_sections->execute([$current_user['id']]);
        $total_sections = $my_sections->fetchColumn();
        
        $my_students = $pdo->prepare("SELECT COUNT(*) FROM users WHERE section_id IN (SELECT id FROM sections WHERE teacher_id = ?) AND role = 'student' AND status = 'active'");
        $my_students->execute([$current_user['id']]);
        $total_students = $my_students->fetchColumn();
        
        $today_scans = $pdo->prepare("SELECT COUNT(*) FROM qr_scans WHERE teacher_id = ? AND DATE(scan_time) = CURDATE()");
        $today_scans->execute([$current_user['id']]);
        $total_scans = $today_scans->fetchColumn();
        
    } elseif ($user_role == 'student') {
        $my_attendance = evaluateAttendance($pdo, $current_user['id']);
        
        $total_days = $my_attendance['total_days'];
        $present_days = $my_attendance['present_days'];
        $attendance_rate = $my_attendance['attendance_rate'];
        $evaluation = $my_attendance['evaluation'];
        
        // Generate or get QR code data for student
        $qr_data = $current_user['qr_code'] ?? generateStudentQR($current_user['id']);
        
        // Update QR code in database if it doesn't exist
        if (!$current_user['qr_code']) {
            $update_stmt = $pdo->prepare("UPDATE users SET qr_code = ? WHERE id = ?");
            $update_stmt->execute([$qr_data, $current_user['id']]);
        }
        
    } elseif ($user_role == 'parent') {
        $children_stmt = $pdo->prepare("SELECT u.* FROM users u JOIN student_parents sp ON u.id = sp.student_id WHERE sp.parent_id = ?");
        $children_stmt->execute([$current_user['id']]);
        $children = $children_stmt->fetchAll(PDO::FETCH_ASSOC);
        $total_children = count($children);
    }
    
} catch(PDOException $e) {
    // Set default values on error
    $total_students = $total_teachers = $total_parents = $total_sections = 0;
    $today_attendance = $present_today = $absent_today = $late_today = 0;
}

// Get SMS configuration status
$sms_config = null;
try {
    $sms_config = $pdo->query("SELECT * FROM sms_config WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $sms_config = null;
}
?>

<!-- Header Section -->
<div class="row g-3 mb-4">
    <div class="col-12">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2">
            <div>
                <h1 class="h4 fw-bold text-primary mb-0">
                    <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                </h1>
                <p class="text-muted small mb-0" id="welcome-message">Welcome back!</p>
            </div>
            <div class="text-start text-md-end">
                <span class="badge bg-primary" id="role-badge"><?php echo ucfirst($user_role); ?></span>
                <div class="small text-muted mt-1" id="current-datetime"></div>
                <div id="offline-indicator" class="d-none">
                    <span class="badge bg-warning text-dark mt-1">
                        <i class="fas fa-wifi-slash me-1"></i>Offline Mode
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="offline-auth-required" class="d-none">
    <div class="alert alert-warning" role="alert">
        <h4 class="alert-heading"><i class="fas fa-exclamation-triangle me-2"></i>Authentication Required</h4>
        <p>You need to log in to access this page.</p>
        <hr>
        <p class="mb-0">
            <a href="login.php" class="btn btn-primary btn-sm">Go to Login</a>
        </p>
    </div>
</div>

<div id="dashboard-content" <?php if ($check_offline_auth): ?>style="display: none;"<?php endif; ?>>
<?php if ($user_role == 'admin'): ?>
    <!-- Admin Dashboard -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-6 col-lg-3">
            <div class="card bg-primary text-white h-100 shadow-sm rounded-3">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="h5 fw-bold mb-1"><?php echo number_format($total_students); ?></h4>
                            <p class="mb-0 small">Students</p>
                        </div>
                        <div class="icon-bg rounded-circle bg-white bg-opacity-25 p-2">
                            <i class="fas fa-user-graduate text-white"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-transparent border-0 py-2">
                    <a href="students.php" class="text-white text-decoration-none small stretched-link">
                        View Details <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
        
        <div class="col-6 col-md-6 col-lg-3">
            <div class="card bg-success text-white h-100 shadow-sm rounded-3">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="h5 fw-bold mb-1"><?php echo number_format($total_teachers); ?></h4>
                            <p class="mb-0 small">Teachers</p>
                        </div>
                        <div class="icon-bg rounded-circle bg-white bg-opacity-25 p-2">
                            <i class="fas fa-chalkboard-teacher text-white"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-transparent border-0 py-2">
                    <a href="users.php?role=teacher" class="text-white text-decoration-none small stretched-link">
                        View Details <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
        
        <div class="col-6 col-md-6 col-lg-3">
            <div class="card bg-info text-white h-100 shadow-sm rounded-3">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="h5 fw-bold mb-1"><?php echo number_format($total_parents); ?></h4>
                            <p class="mb-0 small">Parents</p>
                        </div>
                        <div class="icon-bg rounded-circle bg-white bg-opacity-25 p-2">
                            <i class="fas fa-heart text-white"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-transparent border-0 py-2">
                    <a href="users.php?role=parent" class="text-white text-decoration-none small stretched-link">
                        View Details <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
        
        <div class="col-6 col-md-6 col-lg-3">
            <div class="card bg-warning text-white h-100 shadow-sm rounded-3">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="h5 fw-bold mb-1"><?php echo number_format($total_sections); ?></h4>
                            <p class="mb-0 small">Sections</p>
                        </div>
                        <div class="icon-bg rounded-circle bg-white bg-opacity-25 p-2">
                            <i class="fas fa-school text-white"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-transparent border-0 py-2">
                    <a href="sections.php" class="text-white text-decoration-none small stretched-link">
                        View Details <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Today's Attendance Overview -->
    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="card shadow-sm rounded-3">
                <div class="card-header bg-transparent py-3">
                    <h5 class="card-title h6 fw-bold mb-0">
                        <i class="fas fa-calendar-check me-2"></i>Today's Attendance Overview
                    </h5>
                </div>
                <div class="card-body p-3">
                    <div class="row g-3 text-center">
                        <div class="col-6 col-md-3">
                            <div class="border rounded-3 p-3 h-100 bg-light bg-opacity-50">
                                <h4 class="text-success h5 fw-bold mb-1"><?php echo $present_today; ?></h4>
                                <small class="text-muted">Present</small>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="border rounded-3 p-3 h-100 bg-light bg-opacity-50">
                                <h4 class="text-danger h5 fw-bold mb-1"><?php echo $absent_today; ?></h4>
                                <small class="text-muted">Absent</small>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="border rounded-3 p-3 h-100 bg-light bg-opacity-50">
                                <h4 class="text-warning h5 fw-bold mb-1"><?php echo $late_today; ?></h4>
                                <small class="text-muted">Late</small>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="border rounded-3 p-3 h-100 bg-light bg-opacity-50">
                                <h4 class="text-primary h5 fw-bold mb-1"><?php echo $today_attendance; ?></h4>
                                <small class="text-muted">Total</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php elseif ($user_role == 'teacher'): ?>
    <!-- Teacher Dashboard -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-4">
            <div class="card bg-primary text-white h-100 shadow-sm rounded-3">
                <div class="card-body p-3 text-center">
                    <div class="icon-bg rounded-circle bg-white bg-opacity-25 p-2 mx-auto mb-2" style="width: fit-content;">
                        <i class="fas fa-users text-white"></i>
                    </div>
                    <h4 class="h5 fw-bold mb-1"><?php echo number_format($total_students); ?></h4>
                    <p class="mb-0 small">My Students</p>
                </div>
            </div>
        </div>
        
        <div class="col-6 col-md-4">
            <div class="card bg-success text-white h-100 shadow-sm rounded-3">
                <div class="card-body p-3 text-center">
                    <div class="icon-bg rounded-circle bg-white bg-opacity-25 p-2 mx-auto mb-2" style="width: fit-content;">
                        <i class="fas fa-school text-white"></i>
                    </div>
                    <h4 class="h5 fw-bold mb-1"><?php echo number_format($total_sections); ?></h4>
                    <p class="mb-0 small">My Sections</p>
                </div>
            </div>
        </div>
        
        <div class="col-12 col-md-4">
            <div class="card bg-info text-white h-100 shadow-sm rounded-3">
                <div class="card-body p-3 text-center">
                    <div class="icon-bg rounded-circle bg-white bg-opacity-25 p-2 mx-auto mb-2" style="width: fit-content;">
                        <i class="fas fa-qrcode text-white"></i>
                    </div>
                    <h4 class="h5 fw-bold mb-1"><?php echo number_format($total_scans); ?></h4>
                    <p class="mb-0 small">Today's Scans</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Teacher QR Code Generator Section -->
    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="card shadow-sm rounded-3">
                <div class="card-header bg-transparent py-3 text-center">
                    <h5 class="card-title h6 fw-bold mb-0">
                        <i class="fas fa-qrcode me-2"></i>Attendance QR Code Generator
                    </h5>
                </div>
                <div class="card-body p-3">
                    <div class="alert alert-info mb-3">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Generate QR Code for Attendance</strong><br>
                        Create separate QR codes for Time In and Time Out. Students must scan the appropriate QR code based on their attendance action. Once a student scans a Time In QR code, they must scan a Time Out QR code to complete their attendance record.
                    </div>
                    
                    <!-- Subject and Section Selection -->
                    <div class="row g-3 mb-4">
                        <div class="col-12 col-md-6">
                            <label for="teacher-subject-select" class="form-label fw-semibold">Subject <span class="text-danger">*</span></label>
                            <select class="form-select" id="teacher-subject-select" required>
                                <option value="">Choose a subject...</option>
                                <?php
                                // Get teacher's subjects
                                if ($user_role == 'teacher') {
                                    try {
                                        $subjects_stmt = $pdo->prepare("SELECT id, subject_name, subject_code, grade_level FROM subjects WHERE teacher_id = ? AND status = 'active' ORDER BY subject_name");
                                        $subjects_stmt->execute([$current_user['id']]);
                                        $teacher_subjects = $subjects_stmt->fetchAll(PDO::FETCH_ASSOC);
                                        
                                        foreach ($teacher_subjects as $subject) {
                                            echo '<option value="' . $subject['id'] . '">' . 
                                                htmlspecialchars($subject['subject_name']) . ' (' . 
                                                htmlspecialchars($subject['subject_code']) . ') - Grade ' . 
                                                htmlspecialchars($subject['grade_level']) . '</option>';
                                        }
                                    } catch (PDOException $e) {
                                        // Handle error silently
                                    }
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="teacher-section-select" class="form-label fw-semibold">Section <span class="text-danger">*</span></label>
                            <select class="form-select" id="teacher-section-select" required>
                                <option value="">Choose a section...</option>
                                <?php
                                // Get teacher's sections
                                if ($user_role == 'teacher') {
                                    try {
                                        $sections_stmt = $pdo->prepare("SELECT id, section_name, grade_level FROM sections WHERE teacher_id = ? AND status = 'active' ORDER BY section_name");
                                        $sections_stmt->execute([$current_user['id']]);
                                        $teacher_sections = $sections_stmt->fetchAll(PDO::FETCH_ASSOC);
                                        
                                        foreach ($teacher_sections as $section) {
                                            echo '<option value="' . $section['id'] . '">' . 
                                                htmlspecialchars($section['section_name']) . ' - Grade ' . 
                                                htmlspecialchars($section['grade_level']) . '</option>';
                                        }
                                    } catch (PDOException $e) {
                                        // Handle error silently
                                    }
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    
                    <!-- QR Generation Controls -->
                    <div class="row g-3">
                        <div class="col-12 col-lg-8">
                            <!-- QR Code Display -->
                            <div class="text-center">
                                <div id="teacher-qr-container" class="border rounded-3 p-4 bg-light" style="min-height: 350px;">
                                    <div class="py-4">
                                        <i class="fas fa-qrcode fa-3x text-muted mb-3"></i>
                                        <h5 class="text-muted">Select Subject & Section</h5>
                                        <p class="text-muted small">Choose subject and section above, then select attendance type to generate QR code</p>
                                    </div>
                                </div>
                                
                                <!-- Attendance Type Selection -->
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Attendance Type</label>
                                    <div class="d-flex justify-content-center gap-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="attendanceType" id="attendanceTypeIn" value="in" checked>
                                            <label class="form-check-label" for="attendanceTypeIn">
                                                <i class="fas fa-sign-in-alt text-success me-1"></i>Time In
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="attendanceType" id="attendanceTypeOut" value="out">
                                            <label class="form-check-label" for="attendanceTypeOut">
                                                <i class="fas fa-sign-out-alt text-danger me-1"></i>Time Out
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- QR Controls -->
                                <div class="d-flex justify-content-center gap-2 mt-3">
                                    <button id="generateTeacherQRBtn" class="btn btn-primary" disabled>
                                        <i class="fas fa-qrcode me-1"></i>Generate QR Code
                                    </button>
                                    <button id="downloadTeacherQRBtn" class="btn btn-outline-success" style="display: none;">
                                        <i class="fas fa-download me-1"></i>Download
                                    </button>
                                    <button id="printTeacherQRBtn" class="btn btn-outline-secondary" style="display: none;">
                                        <i class="fas fa-print me-1"></i>Print
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-12 col-lg-4">
                            <!-- QR Code Info -->
                            <div class="card border-0 bg-light">
                                <div class="card-header bg-transparent">
                                    <h6 class="card-title mb-0">
                                        <i class="fas fa-info-circle me-2"></i>QR Code Info
                                    </h6>
                                </div>
                                <div class="card-body" id="teacherQRInfo">
                                    <div class="text-center text-muted py-3">
                                        <i class="fas fa-info fa-2x mb-2"></i>
                                        <p class="mb-0">Generate QR code to see details</p>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Active Sessions -->
                            <div class="card border-0 bg-light mt-3">
                                <div class="card-header bg-transparent">
                                    <h6 class="card-title mb-0">
                                        <i class="fas fa-clock me-2"></i>Active Sessions
                                    </h6>
                                </div>
                                <div class="card-body" id="activeSessions">
                                    <div class="text-center text-muted py-3">
                                        <i class="fas fa-clock fa-2x mb-2"></i>
                                        <p class="mb-0">No active sessions</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php elseif ($user_role == 'student'): ?>
    <!-- Student Dashboard -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-lg-8 mx-auto">
            <div class="card shadow-sm rounded-3">
                <div class="card-header bg-transparent py-3 text-center">
                    <h5 class="card-title h6 fw-bold mb-0">
                        <i class="fas fa-chart-line me-2"></i>My Attendance Summary
                    </h5>
                </div>
                <div class="card-body p-3">
                    <div class="text-center mb-4">
                        <div class="attendance-rate-circle mx-auto mb-3 position-relative" style="width: 120px; height: 120px;">
                            <div class="circle-bg" style="background: conic-gradient(#28a745 0deg <?php echo $attendance_rate * 3.6; ?>deg, #e9ecef <?php echo $attendance_rate * 3.6; ?>deg 360deg); border-radius: 50%; width: 100%; height: 100%;"></div>
                            <div class="circle-content position-absolute top-50 start-50 translate-middle bg-white rounded-circle d-flex align-items-center justify-content-center" style="width: 90px; height: 90px;">
                                <div>
                                    <h3 class="h4 fw-bold text-primary mb-0"><?php echo $attendance_rate; ?>%</h3>
                                    <small class="text-muted">Attendance</small>
                                </div>
                            </div>
                        </div>
                        <h4 class="h6 fw-bold text-<?php echo $evaluation == 'Excellent' ? 'success' : ($evaluation == 'Good' ? 'info' : ($evaluation == 'Fair' ? 'warning' : 'danger')); ?>">
                            <?php echo $evaluation; ?>
                        </h4>
                    </div>
                    
                    <div class="row g-2 text-center">
                        <div class="col-3">
                            <div class="border rounded-3 p-2 h-100 bg-light bg-opacity-50">
                                <h5 class="h6 text-primary fw-bold mb-0"><?php echo $total_days; ?></h5>
                                <small class="text-muted d-block text-truncate">Total Days</small>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="border rounded-3 p-2 h-100 bg-light bg-opacity-50">
                                <h5 class="h6 text-success fw-bold mb-0"><?php echo $present_days; ?></h5>
                                <small class="text-muted d-block text-truncate">Present</small>
                            </div>
                        </div>
                        <div class="col-2">
                            <div class="border rounded-3 p-2 h-100 bg-light bg-opacity-50">
                                <h5 class="h6 text-warning fw-bold mb-0"><?php echo $my_attendance['late_days']; ?></h5>
                                <small class="text-muted d-block text-truncate">Late</small>
                            </div>
                        </div>
                        <div class="col-2">
                            <div class="border rounded-3 p-2 h-100 bg-light bg-opacity-50">
                                <h5 class="h6 text-info fw-bold mb-0"><?php echo $my_attendance['out_days'] ?? 0; ?></h5>
                                <small class="text-muted d-block text-truncate">Out</small>
                            </div>
                        </div>
                        <div class="col-2">
                            <div class="border rounded-3 p-2 h-100 bg-light bg-opacity-50">
                                <h5 class="h6 text-danger fw-bold mb-0"><?php echo $my_attendance['absent_days']; ?></h5>
                                <small class="text-muted d-block text-truncate">Absent</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Student QR Scanner Section -->
    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="card shadow-sm rounded-3">
                <div class="card-header bg-transparent py-3 text-center">
                    <h5 class="card-title h6 fw-bold mb-0">
                        <i class="fas fa-qrcode me-2"></i>QR Attendance Scanner
                    </h5>
                </div>
                <div class="card-body p-3">
                    <div class="alert alert-info mb-3">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Scan Your Teacher's QR Code</strong><br>
                        Ask your teacher to show their attendance QR code for your subject, then scan it below to record your attendance.
                    </div>
                    
                    <!-- Time Rules Alert -->
                    <div class="alert alert-warning mb-3">
                        <div class="row align-items-center">
                            <div class="col-12 col-md-8">
                                <i class="fas fa-clock me-2"></i>
                                <strong>Attendance Time Rules:</strong>
                                <ul class="mb-0 mt-1 small">
                                    <li><span class="text-success">Before 7:15 AM:</span> Present</li>
                                    <li><span class="text-warning">7:15 AM - 4:31 PM:</span> Late</li>
                                    <li><span class="text-danger">After 4:31 PM:</span> Cannot scan (Absent)</li>
                                </ul>
                            </div>
                            <div class="col-12 col-md-4 text-end">
                                <div class="current-time-display">
                                    <div class="text-muted small">Current Time</div>
                                    <div id="currentTimeDisplay" class="fw-bold"></div>
                                    <div id="attendanceStatusDisplay" class="small mt-1"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- SMS Notification Status -->
                    <div class="alert alert-<?php echo ($sms_config && $sms_config['status'] == 'active') ? 'success' : 'secondary'; ?> mb-3">
                        <div class="row align-items-center">
                            <div class="col-12 col-sm-8">
                                <i class="fas fa-sms me-2"></i>
                                <strong>SMS Notifications:</strong>
                                <?php if ($sms_config && $sms_config['status'] == 'active'): ?>
                                    <span class="text-success">Active</span>
                                    <small class="d-block mt-1">Your parent will receive SMS notifications for attendance updates.</small>
                                <?php else: ?>
                                    <span class="text-muted">Inactive</span>
                                    <small class="d-block mt-1">SMS notifications are currently not available.</small>
                                <?php endif; ?>
                            </div>
                            <div class="col-12 col-sm-4 text-sm-end mt-2 mt-sm-0">
                                <span class="badge bg-<?php echo ($sms_config && $sms_config['status'] == 'active') ? 'success' : 'secondary'; ?>">
                                    <i class="fas fa-<?php echo ($sms_config && $sms_config['status'] == 'active') ? 'check' : 'times'; ?> me-1"></i>
                                    <?php echo ($sms_config && $sms_config['status'] == 'active') ? 'SMS Ready' : 'SMS Disabled'; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Scanner Interface -->
                    <div class="row g-3">
                        <div class="col-12 col-lg-8">
                            <div class="scanner-container">
                                <div id="student-scan-region" class="border rounded-3 p-3 bg-light text-center" style="min-height: 300px;">
                                    <div class="py-4">
                                        <i class="fas fa-camera fa-3x text-muted mb-3"></i>
                                        <h5 class="text-muted">QR Scanner Ready</h5>
                                        <p class="text-muted small">Click "Start Scanner" to scan teacher's QR code</p>
                                    </div>
                                </div>
                                
                                <!-- Scanner Controls -->
                                <div class="btn-group-mobile mt-3">
                                    <button id="startStudentScanBtn" class="btn btn-success">
                                        <i class="fas fa-camera me-1"></i>
                                        <span class="d-none d-sm-inline">Start </span>Scanner
                                    </button>
                                    <button id="stopStudentScanBtn" class="btn btn-danger" style="display: none;">
                                        <i class="fas fa-stop me-1"></i>
                                        <span class="d-none d-sm-inline">Stop </span>Scanner
                                    </button>
                                    <button id="switchStudentCameraBtn" class="btn btn-outline-primary" style="display: none;">
                                        <i class="fas fa-sync-alt me-1"></i>
                                        <span class="d-none d-sm-inline">Switch </span>Camera
                                    </button>
                                </div>
                                
                                <!-- Camera Info -->
                                <div id="studentCameraInfo" class="text-center mt-2" style="display: none;">
                                    <small class="text-muted">
                                        <i class="fas fa-camera me-1"></i>
                                        <span id="currentCameraLabel">Back Camera</span>
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Student ID Card Section -->
    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="card shadow-sm rounded-3">
                <div class="card-header bg-transparent py-3 text-center">
                    <h5 class="card-title h6 fw-bold mb-0">
                        <i class="fas fa-id-card me-2"></i>My Student ID Card
                    </h5>
                </div>
                <div class="card-body p-3">
                    <!-- Student ID Card Design -->
                    <div class="student-id-card mx-auto" id="student-id-card" style="max-width: 500px;">
                        <div class="card border border-2 border-primary shadow-lg" style="background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);">
                            <!-- Card Header -->
                            <div class="card-header bg-transparent border-0 text-white text-center py-2">
                                <h6 class="mb-0 fw-bold">KES SCHOOL</h6>
                                <small class="opacity-75">STUDENT IDENTIFICATION CARD</small>
                            </div>
                            
                            <!-- Card Body -->
                            <div class="card-body bg-white p-3">
                                <div class="row align-items-center">
                                    <!-- Student Photo -->
                                    <div class="col-4 text-center">
                                        <div class="student-photo-container position-relative">
                                            <div class="photo-frame border border-2 border-primary rounded-3 overflow-hidden mx-auto" style="width: 120px; height: 140px; background: #f8f9fa;">
                                                <?php if (!empty($current_user['profile_image_path']) && file_exists($current_user['profile_image_path'])): ?>
                                                    <img src="<?php echo htmlspecialchars($current_user['profile_image_path']); ?>" 
                                                         alt="Student Photo" 
                                                         class="w-100 h-100 object-fit-cover"
                                                         id="student-photo-display">
                                                <?php else: ?>
                                                    <div class="d-flex align-items-center justify-content-center h-100 text-muted">
                                                        <div class="text-center">
                                                            <i class="fas fa-user fa-3x mb-2"></i>
                                                            <div class="small">No Photo</div>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <!-- Photo Upload Button -->
                                            <div class="mt-2">
                                                <label for="photo-upload" class="btn btn-sm btn-outline-primary w-100" style="font-size: 0.7rem;">
                                                    <i class="fas fa-camera me-1"></i>Upload Photo
                                                </label>
                                                <input type="file" id="photo-upload" accept="image/*" class="d-none">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Student Information -->
                                    <div class="col-5">
                                        <div class="student-info">
                                            <div class="mb-2">
                                                <label class="small text-muted mb-0">STUDENT NAME</label>
                                                <div class="fw-bold text-primary" style="font-size: 0.9rem;"><?php echo strtoupper(htmlspecialchars($current_user['full_name'])); ?></div>
                                            </div>
                                            
                                            <div class="mb-2">
                                                <label class="small text-muted mb-0">STUDENT ID</label>
                                                <div class="fw-bold" style="font-size: 0.8rem;"><?php echo htmlspecialchars($current_user['username']); ?></div>
                                            </div>
                                            
                                            <?php if ($current_user['lrn']): ?>
                                            <div class="mb-2">
                                                <label class="small text-muted mb-0">LRN</label>
                                                <div class="fw-bold" style="font-size: 0.8rem;"><?php echo htmlspecialchars($current_user['lrn']); ?></div>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <?php 
                                            // Get section information
                                            if ($current_user['section_id']) {
                                                try {
                                                    $section_stmt = $pdo->prepare("SELECT section_name, grade_level FROM sections WHERE id = ?");
                                                    $section_stmt->execute([$current_user['section_id']]);
                                                    $section_info = $section_stmt->fetch(PDO::FETCH_ASSOC);
                                                } catch (PDOException $e) {
                                                    $section_info = null;
                                                }
                                            }
                                            ?>
                                            
                                            <?php if (isset($section_info) && $section_info): ?>
                                            <div class="mb-2">
                                                <label class="small text-muted mb-0">SECTION</label>
                                                <div class="fw-bold" style="font-size: 0.8rem;"><?php echo htmlspecialchars($section_info['section_name']); ?></div>
                                            </div>
                                            
                                            <div class="mb-2">
                                                <label class="small text-muted mb-0">GRADE LEVEL</label>
                                                <div class="fw-bold" style="font-size: 0.8rem;"><?php echo htmlspecialchars($section_info['grade_level']); ?></div>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <div class="mb-1">
                                                <label class="small text-muted mb-0">SCHOOL YEAR</label>
                                                <div class="fw-bold" style="font-size: 0.8rem;"><?php echo date('Y') . '-' . (date('Y') + 1); ?></div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- QR Code -->
                                    <div class="col-3 text-center">
                                        <div class="qr-code-section">
                                            <div id="qr-code-small" class="mx-auto mb-2" style="width: 80px; height: 80px;"></div>
                                            <div class="small text-muted" style="font-size: 0.6rem;">SCAN FOR ATTENDANCE</div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Card Footer -->
                                <div class="mt-3 pt-2 border-top">
                                    <div class="row align-items-center">
                                        <div class="col-6">
                                            <div class="small text-muted">
                                                <strong>Valid Until:</strong> <?php echo date('M Y', strtotime('+1 year')); ?>
                                            </div>
                                        </div>
                                        <div class="col-6 text-end">
                                            <div class="small text-muted">
                                                <strong>Emergency:</strong> <?php echo $current_user['phone'] ?? 'N/A'; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="text-center mt-4">
                        <div class="d-flex flex-wrap justify-content-center gap-2">
                            <button onclick="downloadStudentID()" class="btn btn-primary">
                                <i class="fas fa-download me-2"></i>Download ID Card
                            </button>
                            <button onclick="shareStudentID()" class="btn btn-outline-info">
                                <i class="fas fa-share-alt me-2"></i>Share ID Card
                            </button>
                        </div>
                        
                        <div class="mt-3">
                            <small class="text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                Your student ID card contains your QR code for attendance scanning. Keep it safe and present it when required.
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Separate QR Code Section for Quick Access -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-lg-6 mx-auto">
            <div class="card shadow-sm rounded-3">
                <div class="card-header bg-transparent py-3 text-center">
                    <h5 class="card-title h6 fw-bold mb-0">
                        <i class="fas fa-qrcode me-2"></i>Quick QR Access
                    </h5>
                </div>
                <div class="card-body p-3 text-center">
                    <div class="qr-code-container" id="qr-container">
                        <div id="qr-code" class="mx-auto mb-3"></div>
                        <p class="text-muted small mb-3">
                            Show this QR code to your teacher for attendance scanning
                        </p>
                        
                        <!-- QR Code Data -->
                        <div class="bg-light p-3 rounded-3 mb-3">
                            <small class="text-muted d-block mb-1">QR Code Data:</small>
                            <code class="small text-break"><?php echo $qr_data; ?></code>
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="d-flex flex-wrap justify-content-center gap-2">
                            <button onclick="downloadQR()" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-download me-1"></i>Download
                            </button>
                            <button onclick="printQR()" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-print me-1"></i>Print
                            </button>
                            <button onclick="shareQR()" class="btn btn-sm btn-outline-info">
                                <i class="fas fa-share-alt me-1"></i>Share
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php elseif ($user_role == 'parent'): ?>
    <!-- Parent Dashboard -->
    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="card shadow-sm rounded-3">
                <div class="card-header bg-transparent py-3">
                    <h5 class="card-title h6 fw-bold mb-0">
                        <i class="fas fa-heart me-2"></i>My Children
                    </h5>
                </div>
                <div class="card-body p-3">
                    <?php if (empty($children)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-info-circle fa-2x text-muted mb-2"></i>
                            <p class="text-muted small mb-0">No children linked to your account yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="row g-3">
                            <?php foreach ($children as $child): ?>
                                <?php $child_attendance = evaluateAttendance($pdo, $child['id']); ?>
                                <div class="col-12 col-sm-6 col-lg-4">
                                    <div class="card h-100 border-0 shadow-sm rounded-3">
                                        <div class="card-body p-3 text-center">
                                            <div class="profile-avatar mx-auto mb-2" style="width: 60px; height: 60px; background: linear-gradient(135deg, #007bff, #0056b3); display: flex; align-items: center; justify-content: center; border-radius: 50%; color: white; font-size: 1.5rem;">
                                                <?php echo strtoupper(substr($child['full_name'], 0, 1)); ?>
                                            </div>
                                            <h6 class="fw-bold mb-1"><?php echo $child['full_name']; ?></h6>
                                            <p class="text-muted small mb-2"><?php echo $child['username']; ?></p>
                                            
                                            <div class="mb-2">
                                                <span class="badge bg-<?php echo $child_attendance['evaluation'] == 'Excellent' ? 'success' : ($child_attendance['evaluation'] == 'Good' ? 'info' : ($child_attendance['evaluation'] == 'Fair' ? 'warning' : 'danger')); ?>">
                                                    <?php echo $child_attendance['attendance_rate']; ?>% - <?php echo $child_attendance['evaluation']; ?>
                                                </span>
                                            </div>
                                            
                                            <a href="attendance.php?student_id=<?php echo $child['id']; ?>" class="btn btn-sm btn-outline-primary stretched-link">
                                                <i class="fas fa-eye me-1"></i>View Details
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Recent Activity -->
<div class="row g-3">
    <div class="col-12">
        <div class="card shadow-sm rounded-3">
            <div class="card-header bg-transparent py-3">
                <h5 class="card-title h6 fw-bold mb-0">
                    <i class="fas fa-clock me-2"></i>Recent Activity
                </h5>
            </div>
            <div class="card-body p-3">
                <?php
                try {
                    if ($user_role == 'admin' || $user_role == 'teacher') {
                        $activity_query = "
                            SELECT a.*, u.full_name as student_name, s.section_name 
                            FROM attendance a 
                            JOIN users u ON a.student_id = u.id 
                            JOIN sections s ON a.section_id = s.id 
                            WHERE a.attendance_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAYS)
                            ORDER BY a.created_at DESC 
                            LIMIT 10
                        ";
                        if ($user_role == 'teacher') {
                            $activity_query = "
                                SELECT a.*, u.full_name as student_name, s.section_name 
                                FROM attendance a 
                                JOIN users u ON a.student_id = u.id 
                                JOIN sections s ON a.section_id = s.id 
                                WHERE a.teacher_id = ? AND a.attendance_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAYS)
                                ORDER BY a.created_at DESC 
                                LIMIT 10
                            ";
                            $stmt = $pdo->prepare($activity_query);
                            $stmt->execute([$current_user['id']]);
                        } else {
                            $stmt = $pdo->query($activity_query);
                        }
                    } else {
                        $activity_query = "
                            SELECT a.*, s.section_name 
                            FROM attendance a 
                            JOIN sections s ON a.section_id = s.id 
                            WHERE a.student_id = ? AND a.attendance_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAYS)
                            ORDER BY a.created_at DESC 
                            LIMIT 10
                        ";
                        $stmt = $pdo->prepare($activity_query);
                        $stmt->execute([$user_role == 'student' ? $current_user['id'] : $children[0]['id'] ?? 0]);
                    }
                    
                    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } catch(PDOException $e) {
                    $activities = [];
                }
                ?>
                
                <?php if (empty($activities)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-clock fa-2x text-muted mb-2"></i>
                        <p class="text-muted small mb-0">No recent activity found.</p>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($activities as $activity): ?>
                            <div class="list-group-item border-bottom py-3 px-0">
                                <div class="d-flex align-items-start gap-2">
                                    <div class="activity-icon">
                                        <span class="badge rounded-pill p-2 bg-<?php 
                                            echo $activity['status'] == 'present' ? 'success' : 
                                                ($activity['status'] == 'late' ? 'warning' : 
                                                ($activity['status'] == 'out' ? 'info' : 'danger')); 
                                        ?>">
                                            <i class="fas fa-<?php 
                                                echo $activity['status'] == 'present' ? 'check' : 
                                                    ($activity['status'] == 'late' ? 'clock' : 
                                                    ($activity['status'] == 'out' ? 'sign-out-alt' : 'times')); 
                                            ?> fa-fw"></i>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 min-width-0">
                                        <div class="d-flex justify-content-between align-items-start mb-1">
                                            <h6 class="mb-0 text-truncate">
                                                <?php 
                                                if ($user_role == 'admin' || $user_role == 'teacher') {
                                                    echo $activity['student_name'];
                                                } else {
                                                    echo 'Attendance Record';
                                                }
                                                ?>
                                            </h6>
                                            <small class="text-muted ms-2"><?php echo date('M j', strtotime($activity['attendance_date'])); ?></small>
                                        </div>
                                        <p class="mb-0 small text-muted text-truncate">
                                            <?php echo ucfirst($activity['status']); ?> - <?php echo $activity['section_name']; ?>
                                            <?php if ($activity['time_in']): ?>
                                                <span class="ms-1">(<?php echo date('g:i A', strtotime($activity['time_in'])); ?>)</span>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="text-center mt-3">
                        <a href="attendance.php" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                            <i class="fas fa-eye me-1"></i>View All
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-refresh dashboard data every 5 minutes
setInterval(function() {
    if (document.visibilityState === 'visible') {
        location.reload();
    }
}, 300000);

// Update current date and time
function updateDateTime() {
    const now = new Date();
    const options = { 
        weekday: 'short', 
        year: 'numeric', 
        month: 'short', 
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        hour12: true,
        timeZone: 'Asia/Manila'
    };
    document.getElementById('current-datetime').textContent = now.toLocaleString('en-US', options);
}

// Handle bottom navigation visibility for students and parents when offline
function updateBottomNavVisibility() {
    const isOnline = navigator.onLine;
    const userRole = '<?php echo $user_role; ?>';
    const bottomNavs = document.querySelectorAll('.bottom-nav');
    
    // Only hide bottom nav for students and parents when offline
    if (userRole === 'student' || userRole === 'parent') {
        bottomNavs.forEach(nav => {
            if (isOnline) {
                // Online - show bottom nav
                nav.style.display = '';
                nav.classList.remove('d-none');
            } else {
                // Offline - hide bottom nav
                nav.style.display = 'none';
                nav.classList.add('d-none');
            }
        });
    }
}

// Note: Offline authentication is handled by assets/js/offline-auth.js

// Animate dashboard elements
document.addEventListener('DOMContentLoaded', function() {
    // Offline auth check is handled by offline-auth.js automatically
    
    // Update date and time
    updateDateTime();
    setInterval(updateDateTime, 60000);
    
    // Initialize bottom nav visibility based on online/offline status
    updateBottomNavVisibility();
    
    // Listen for online/offline events
    window.addEventListener('online', updateBottomNavVisibility);
    window.addEventListener('offline', updateBottomNavVisibility);
    
    // Animate cards with stagger effect
    const cards = document.querySelectorAll('.card');
    cards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        setTimeout(() => {
            card.style.transition = 'all 0.4s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, 100 + (index * 50));
    });
    
    // Animate attendance rate circle for students
    const circle = document.querySelector('.attendance-rate-circle');
    if (circle) {
        circle.style.opacity = '0';
        circle.style.transform = 'scale(0.8)';
        setTimeout(() => {
            circle.style.transition = 'all 0.6s ease';
            circle.style.opacity = '1';
            circle.style.transform = 'scale(1)';
        }, 300);
    }
    
    <?php if ($user_role == 'student'): ?>
    // Generate QR Code
    generateStudentQR();
    <?php endif; ?>
});

<?php if ($user_role == 'student'): ?>
// Generate QR Code for student
function generateStudentQR() {
    const qrData = '<?php echo $qr_data; ?>';
    const qrContainer = document.getElementById('qr-code');
    const qrContainerSmall = document.getElementById('qr-code-small');
    
    console.log('QR Data:', qrData);
    
    // Generate QR codes for both containers
    generateQRForContainer(qrData, qrContainer, 250);
    generateQRForContainer(qrData, qrContainerSmall, 80);
}

function generateQRForContainer(qrData, container, size) {
    if (!container) return;
    
    // Use QR Server API for reliable QR generation
    const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=${size}x${size}&data=${encodeURIComponent(qrData)}&format=png&margin=10`;
    
    const img = document.createElement('img');
    img.src = qrUrl;
    img.alt = 'QR Code';
    img.style.border = '2px solid #007bff';
    img.style.borderRadius = '8px';
    img.style.boxShadow = '0 2px 4px rgba(0, 0, 0, 0.1)';
    img.style.maxWidth = '100%';
    img.style.height = 'auto';
    img.style.width = size + 'px';
    
    img.onload = function() {
        container.innerHTML = '';
        container.appendChild(img);
        console.log('QR Code generated successfully for container');
    };
    
    img.onerror = function() {
        console.error('QR Code generation failed for container');
        if (size > 100) {
            container.innerHTML = `
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    <strong>QR Code Data:</strong><br>
                    <div class="mt-2 p-2 bg-white rounded border">
                        <code style="word-break: break-all;">${qrData}</code>
                    </div>
                    <small class="d-block mt-2">Use a QR code scanner app to scan this data manually.</small>
                </div>
            `;
        } else {
            container.innerHTML = `
                <div class="alert alert-warning small">
                    <i class="fas fa-exclamation-triangle"></i>
                    QR Code failed to load
                </div>
            `;
        }
    };
}

// Photo Upload Functionality
document.addEventListener('DOMContentLoaded', function() {
    const photoUpload = document.getElementById('photo-upload');
    
    if (photoUpload) {
        photoUpload.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                // Validate file type
                if (!file.type.startsWith('image/')) {
                    alert('Please select a valid image file.');
                    return;
                }
                
                // Validate file size (max 5MB)
                if (file.size > 5 * 1024 * 1024) {
                    alert('File size should not exceed 5MB.');
                    return;
                }
                
                // Create FormData to upload the file
                const formData = new FormData();
                formData.append('photo', file);
                formData.append('action', 'upload_photo');
                
                // Show loading
                const photoContainer = document.querySelector('.photo-frame');
                photoContainer.innerHTML = `
                    <div class="d-flex align-items-center justify-content-center h-100">
                        <div class="text-center">
                            <div class="spinner-border spinner-border-sm text-primary mb-2" role="status"></div>
                            <div class="small text-muted">Uploading...</div>
                        </div>
                    </div>
                `;
                
                // Upload the file
                fetch('upload-photo.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update the photo display
                        photoContainer.innerHTML = `
                            <img src="${data.photo_path}" 
                                 alt="Student Photo" 
                                 class="w-100 h-100 object-fit-cover"
                                 id="student-photo-display">
                        `;
                        
                        // Show success message
                        showAlert('Photo uploaded successfully!', 'success');
                    } else {
                        // Show error and revert
                        showAlert(data.message || 'Failed to upload photo.', 'danger');
                        revertPhotoDisplay();
                    }
                })
                .catch(error => {
                    console.error('Upload error:', error);
                    showAlert('An error occurred while uploading the photo.', 'danger');
                    revertPhotoDisplay();
                });
            }
        });
    }
    
    function revertPhotoDisplay() {
        const photoContainer = document.querySelector('.photo-frame');
        <?php if (!empty($current_user['profile_image_path']) && file_exists($current_user['profile_image_path'])): ?>
        photoContainer.innerHTML = `
            <img src="<?php echo htmlspecialchars($current_user['profile_image_path']); ?>" 
                 alt="Student Photo" 
                 class="w-100 h-100 object-fit-cover"
                 id="student-photo-display">
        `;
        <?php else: ?>
        photoContainer.innerHTML = `
            <div class="d-flex align-items-center justify-content-center h-100 text-muted">
                <div class="text-center">
                    <i class="fas fa-user fa-3x mb-2"></i>
                    <div class="small">No Photo</div>
                </div>
            </div>
        `;
        <?php endif; ?>
    }
    
    function showAlert(message, type) {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show mt-3`;
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        const cardBody = document.querySelector('#student-id-card').closest('.card-body');
        cardBody.insertBefore(alertDiv, cardBody.firstChild);
        
        // Auto dismiss after 5 seconds
        setTimeout(() => {
            if (alertDiv && alertDiv.parentNode) {
                alertDiv.remove();
            }
        }, 5000);
    }
});

// Print Student ID Card
function printStudentID() {
    const idCard = document.getElementById('student-id-card');
    
    if (!idCard) {
        alert('ID card not found.');
        return;
    }
    
    const printWindow = window.open('', '_blank');
    
    // Get the current photo
    const photoImg = idCard.querySelector('img[alt="Student Photo"]');
    let photoHtml = '';
    if (photoImg) {
        photoHtml = `<img src="${photoImg.src}" style="width: 120px; height: 140px; object-fit: cover; border: 2px solid #007bff; border-radius: 8px;">`;
    } else {
        photoHtml = `
            <div style="width: 120px; height: 140px; border: 2px solid #007bff; border-radius: 8px; display: flex; align-items: center; justify-content: center; background: #f8f9fa;">
                <div style="text-align: center; color: #6c757d;">
                    <div style="font-size: 2rem; margin-bottom: 5px;">👤</div>
                    <div style="font-size: 0.7rem;">No Photo</div>
                </div>
            </div>
        `;
    }
    
    // Get the QR code
    const qrImg = idCard.querySelector('#qr-code-small img');
    let qrHtml = '';
    if (qrImg) {
        qrHtml = `<img src="${qrImg.src}" style="width: 80px; height: 80px; border: 2px solid #007bff; border-radius: 8px;">`;
    }
    
    // Get all student information from the PHP session
    const studentName = '<?php echo strtoupper(htmlspecialchars($current_user['full_name'])); ?>';
    const studentId = '<?php echo htmlspecialchars($current_user['username']); ?>';
    
    // Get LRN if available
    let lrnHtml = '';
    <?php if ($current_user['lrn']): ?>
    const lrnValue = '<?php echo htmlspecialchars($current_user['lrn']); ?>';
    lrnHtml = `
        <div class="info-item">
            <p class="info-label">LRN</p>
            <p class="info-value">${lrnValue}</p>
        </div>`;
    <?php endif; ?>
    
    // Get Section and Grade information
    let sectionGradeHtml = '';
    <?php if (isset($section_info) && $section_info): ?>
    const sectionName = '<?php echo htmlspecialchars($section_info['section_name']); ?>';
    const gradeLevel = '<?php echo htmlspecialchars($section_info['grade_level']); ?>';
    sectionGradeHtml = `
        <div class="info-item">
            <p class="info-label">SECTION</p>
            <p class="info-value">${sectionName}</p>
        </div>
        <div class="info-item">
            <p class="info-label">GRADE</p>
            <p class="info-value">${gradeLevel}</p>
        </div>`;
    <?php endif; ?>
    
    const schoolYear = '<?php echo date('Y') . '-' . (date('Y') + 1); ?>';
    const validUntil = '<?php echo date('M Y', strtotime('+1 year')); ?>';
    const emergencyPhone = '<?php echo $current_user['phone'] ?? 'N/A'; ?>';
    
    const idCardHtml = `
        <!DOCTYPE html>
        <html>
        <head>
            <title>Student ID Card - ${studentName}</title>
            <style>
                @page {
                    size: A4;
                    margin: 1in;
                }
                
                body {
                    font-family: Arial, sans-serif;
                    margin: 0;
                    padding: 20px;
                    background: #f5f5f5;
                }
                
                .id-card-print {
                    width: 85.6mm;
                    height: 54mm;
                    background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
                    border-radius: 10px;
                    margin: 20px auto;
                    overflow: hidden;
                    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
                    border: 2px solid #007bff;
                }
                
                .card-header-print {
                    background: transparent;
                    color: white;
                    text-align: center;
                    padding: 5px;
                    border-bottom: 1px solid rgba(255,255,255,0.3);
                }
                
                .school-name {
                    font-size: 11px;
                    font-weight: bold;
                    margin: 0;
                }
                
                .card-type {
                    font-size: 7px;
                    margin: 0;
                    opacity: 0.8;
                }
                
                .card-body-print {
                    background: white;
                    padding: 8px;
                    display: flex;
                    align-items: flex-start;
                    gap: 8px;
                }
                
                .photo-section {
                    flex-shrink: 0;
                }
                
                .info-section {
                    flex: 1;
                    min-width: 0;
                }
                
                .qr-section {
                    flex-shrink: 0;
                    text-align: center;
                }
                
                .info-item {
                    margin-bottom: 3px;
                }
                
                .info-label {
                    font-size: 6px;
                    color: #6c757d;
                    margin: 0;
                    line-height: 1;
                }
                
                .info-value {
                    font-size: 7px;
                    font-weight: bold;
                    color: #333;
                    margin: 0;
                    line-height: 1.2;
                }
                
                .student-name {
                    color: #007bff;
                    font-size: 8px;
                }
                
                .qr-label {
                    font-size: 5px;
                    color: #6c757d;
                    margin-top: 2px;
                    line-height: 1;
                }
                
                .card-footer-print {
                    background: white;
                    border-top: 1px solid #dee2e6;
                    padding: 4px 8px;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                }
                
                .footer-text {
                    font-size: 5px;
                    color: #6c757d;
                    margin: 0;
                }
                
                .print-instructions {
                    text-align: center;
                    margin: 20px 0;
                    color: #666;
                    font-size: 12px;
                }
                
                @media print {
                    body {
                        background: white;
                        padding: 0;
                    }
                    
                    .print-instructions {
                        display: none;
                    }
                }
            </style>
        </head>
        <body>
            <div class="print-instructions">
                <p><strong>Printing Instructions:</strong></p>
                <p>• Set printer to actual size (100%)<br>
                • Use high-quality paper for best results<br>
                • Consider laminating for durability</p>
            </div>
            
            <div class="id-card-print">
                <div class="card-header-print">
                    <h6 class="school-name">KES SCHOOL</h6>
                    <p class="card-type">STUDENT IDENTIFICATION CARD</p>
                </div>
                
                <div class="card-body-print">
                    <div class="photo-section">
                        ${photoHtml}
                    </div>
                    
                    <div class="info-section">
                        <div class="info-item">
                            <p class="info-label">STUDENT NAME</p>
                            <p class="info-value student-name">${studentName}</p>
                        </div>
                        <div class="info-item">
                            <p class="info-label">STUDENT ID</p>
                            <p class="info-value">${studentId}</p>
                        </div>
                        ${lrnHtml}
                        ${sectionGradeHtml}
                        <div class="info-item">
                            <p class="info-label">SCHOOL YEAR</p>
                            <p class="info-value">${schoolYear}</p>
                        </div>
                    </div>
                    
                    <div class="qr-section">
                        ${qrHtml}
                        <p class="qr-label">SCAN FOR<br>ATTENDANCE</p>
                    </div>
                </div>
                
                <div class="card-footer-print">
                    <p class="footer-text">Valid Until: ${validUntil}</p>
                    <p class="footer-text">Emergency: ${emergencyPhone}</p>
                </div>
            </div>
        </body>
        </html>
    `;
    
    printWindow.document.write(idCardHtml);
    printWindow.document.close();
    printWindow.print();
}

// Download Student ID Card
function downloadStudentID() {
    // Use the print function and suggest PDF save
    if (confirm('This will open the print dialog. Choose "Save as PDF" to download the ID card as a file.')) {
        printStudentID();
    }
}

// Share Student ID Card
async function shareStudentID() {
    if (navigator.share) {
        try {
            await navigator.share({
                title: 'My Student ID Card',
                text: 'Student ID Card for <?php echo htmlspecialchars($current_user['full_name']); ?>',
                url: window.location.href
            });
        } catch (error) {
            console.error('Error sharing:', error);
            fallbackShareID();
        }
    } else {
        fallbackShareID();
    }
}

function fallbackShareID() {
    const url = window.location.href;
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(url).then(() => {
            alert('Page link copied to clipboard! You can share this with others.');
        });
    } else {
        alert('Sharing not supported on this device. You can copy the current page URL to share.');
    }
}

// Download QR Code
function downloadQR() {
    const img = document.querySelector('#qr-code img');
    
    if (img) {
        const link = document.createElement('a');
        link.download = '<?php echo $current_user['username']; ?>_qr_code.png';
        link.href = img.src;
        link.target = '_blank';
        link.click();
    } else {
        alert('QR code not found. Please wait for it to load or refresh the page.');
    }
}

// Print QR Code
function printQR() {
    const img = document.querySelector('#qr-code img');
    
    const printWindow = window.open('', '_blank');
    let qrElement = '';
    
    if (img) {
        qrElement = `<img src="${img.src}" style="border: 3px solid #000; border-radius: 15px; max-width: 300px;">`;
    } else {
        qrElement = '<p>QR Code could not be loaded</p>';
    }
    
    const studentInfo = `
        <div style="text-align: center; padding: 20px; font-family: Arial, sans-serif;">
            <h1 style="color: #007bff; margin-bottom: 10px;">KES-SMART</h1>
            <h2 style="margin-bottom: 20px;"><?php echo $current_user['full_name']; ?></h2>
            <p style="margin-bottom: 5px;"><strong>Student ID:</strong> <?php echo $current_user['username']; ?></p>
            <?php if ($current_user['lrn']): ?>
            <p style="margin-bottom: 20px;"><strong>LRN:</strong> <?php echo $current_user['lrn']; ?></p>
            <?php endif; ?>
            <div style="margin: 20px 0;">
                ${qrElement}
            </div>
            <p style="margin-top: 20px; font-size: 12px; color: #666;">
                Show this QR code to your teacher for attendance scanning
            </p>
            <p style="font-size: 10px; color: #999;">
                Generated on: ${new Date().toLocaleDateString()}
            </p>
        </div>
    `;
    
    printWindow.document.write(`
        <html>
            <head>
                <title>QR Code - <?php echo $current_user['full_name']; ?></title>
                <style>
                    @media print {
                        body { margin: 0; }
                        img { border: 2px solid #000; border-radius: 10px; }
                    }
                </style>
            </head>
            <body>
                ${studentInfo}
            </body>
        </html>
    `);
    printWindow.document.close();
    printWindow.print();
}

// Share QR Code
async function shareQR() {
    const img = document.querySelector('#qr-code img');
    
    if (img && navigator.share) {
        try {
            const response = await fetch(img.src);
            const blob = await response.blob();
            const file = new File([blob], '<?php echo $current_user['username']; ?>_qr_code.png', { type: 'image/png' });
            await navigator.share({
                title: 'My QR Code - <?php echo $current_user['full_name']; ?>',
                text: 'My KES-SMART attendance QR code',
                files: [file]
            });
        } catch (error) {
            console.error('Error sharing:', error);
            fallbackShare();
        }
    } else {
        fallbackShare();
    }
}

function fallbackShare() {
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText('<?php echo $qr_data; ?>').then(() => {
            alert('QR code data copied to clipboard!');
        });
    } else {
        const textarea = document.createElement('textarea');
        textarea.value = '<?php echo $qr_data; ?>';
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        alert('QR code data copied to clipboard!');
    }
}
<?php endif; ?>

<?php if ($user_role == 'student'): ?>
// Student QR Scanner Functionality
let studentScanner = null;
let availableCameras = [];
let currentCameraIndex = 0;

// Update current time and attendance status display
function updateTimeDisplay() {
    const now = new Date();
    const timeDisplay = document.getElementById('currentTimeDisplay');
    const statusDisplay = document.getElementById('attendanceStatusDisplay');
    
    if (timeDisplay && statusDisplay) {
        // Format current time
        const timeString = now.toLocaleTimeString('en-US', {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: true,
            timeZone: 'Asia/Manila'
        });
        timeDisplay.textContent = timeString;
        
        // Calculate attendance status using Manila timezone
        const manilaTimeString = now.toLocaleString("en-US", {timeZone: "Asia/Manila", hour12: false});
        const manilaDateParts = manilaTimeString.split(', ');
        const manilaTimePart = manilaDateParts[1]; // Gets "HH:MM:SS"
        const [hours, minutes] = manilaTimePart.split(':').map(Number);
        const currentTime = hours * 100 + minutes;
        
        // Debug log to verify correct time calculation
        console.log('Manila Time Debug:', {
            manilaTimeString: manilaTimeString,
            manilaTimePart: manilaTimePart,
            hours: hours,
            minutes: minutes,
            currentTime: currentTime,
            formattedTime: `${hours}:${minutes.toString().padStart(2, '0')}`
        });
        
        const lateThreshold = 715; // 7:15 AM
        const absentCutoff = 1631; // 4:31 PM
        
        let statusText = '';
        let statusClass = '';
        
        if (currentTime <= lateThreshold) {
            statusText = '✓ On Time (Present)';
            statusClass = 'text-success';
        } else if (currentTime > lateThreshold && currentTime <= absentCutoff) {
            statusText = '⚠ Late Period';
            statusClass = 'text-warning';
        } else {
            statusText = '✗ Too Late (Auto-Absent)';
            statusClass = 'text-danger';
        }
        
        statusDisplay.textContent = statusText;
        statusDisplay.className = `small mt-1 fw-bold ${statusClass}`;
        
        // Check if scanner should be disabled and disable it if running
        checkAndDisableScanner(currentTime, absentCutoff);
    }
}

// Check and disable scanner if past cutoff time
function checkAndDisableScanner(currentTime, absentCutoff) {
    const startBtn = document.getElementById('startStudentScanBtn');
    const stopBtn = document.getElementById('stopStudentScanBtn');
    const switchBtn = document.getElementById('switchStudentCameraBtn');
    const cameraInfo = document.getElementById('studentCameraInfo');
    const scanRegion = document.getElementById('student-scan-region');
    
    if (currentTime > absentCutoff) {
        // Time has passed 4:31 PM, disable scanner
        if (studentScanner && studentScanner.isScanning) {
            // Stop the scanner if it's running
            stopStudentScanner();
            console.log('Scanner automatically stopped at 4:31 PM');
        }
        
        // Hide scanner controls and show disabled message
        if (startBtn) {
            startBtn.style.display = 'none';
            startBtn.disabled = true;
        }
        if (stopBtn) {
            stopBtn.style.display = 'none';
        }
        if (switchBtn) {
            switchBtn.style.display = 'none';
        }
        if (cameraInfo) {
            cameraInfo.style.display = 'none';
        }
        
        // Show disabled scanner message
        if (scanRegion) {
            scanRegion.innerHTML = `
                <div class="py-4">
                    <i class="fas fa-clock fa-3x text-danger mb-3"></i>
                    <h5 class="text-danger">Scanner Disabled</h5>
                    <p class="text-muted small">QR code scanning is no longer available after 4:31 PM.<br>Students who haven't scanned will be marked absent.</p>
                </div>
            `;
        }
    } else {
        // Before cutoff time, ensure scanner is available
        if (startBtn && startBtn.disabled) {
            startBtn.disabled = false;
            startBtn.style.display = 'inline-block';
            
            // Restore original scanner interface if it was disabled
            if (scanRegion && scanRegion.innerHTML.includes('Scanner Disabled')) {
                scanRegion.innerHTML = `
                    <div id="student-qr-reader" style="width: 100%"></div>
                    <div class="py-4">
                        <i class="fas fa-camera fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">QR Scanner Ready</h5>
                        <p class="text-muted small">Click "Start Scanner" to scan teacher's QR code</p>
                    </div>
                `;
            }
        }
    }
}

// Initialize student QR scanner
function initStudentQRScanner() {
    try {
        const scanRegion = document.getElementById('student-scan-region');
        if (!scanRegion) return;

        // Create QR scanner element
        scanRegion.innerHTML = '<div id="student-qr-reader" style="width: 100%"></div>';
        
        const html5QrCode = new Html5Qrcode("student-qr-reader");
        studentScanner = html5QrCode;
        
        // Get scanner controls
        const startBtn = document.getElementById('startStudentScanBtn');
        const stopBtn = document.getElementById('stopStudentScanBtn');
        const switchBtn = document.getElementById('switchStudentCameraBtn');
        const cameraInfo = document.getElementById('studentCameraInfo');
        
        if (startBtn && stopBtn) {
            startBtn.addEventListener('click', function() {
                // Check time restrictions before starting scanner
                const now = new Date();
                const manilaTimeString = now.toLocaleString("en-US", {timeZone: "Asia/Manila", hour12: false});
                const manilaDateParts = manilaTimeString.split(', ');
                const manilaTimePart = manilaDateParts[1]; // Gets "HH:MM:SS"
                const [hours, minutes] = manilaTimePart.split(':').map(Number);
                const currentTime = hours * 100 + minutes;
                const absentCutoff = 1631; // 4:31 PM
                const lateThreshold = 715; // 7:15 AM
                
                // Prevent scanning after cutoff time
                if (currentTime > absentCutoff) {
                    showToast('Cannot scan QR code after 4:31 PM. This time will be marked as absent.', 'danger');
                    return;
                }
                
                // Show confirmation for late scanning
                if (currentTime > lateThreshold) {
                    if (!confirm('You are scanning after 7:15 AM. This will be marked as LATE. Do you want to continue?')) {
                        return;
                    }
                }
                
                startStudentScanner();
                this.style.display = 'none';
                stopBtn.style.display = 'inline-block';
                if (switchBtn) switchBtn.style.display = 'inline-block';
                if (cameraInfo) cameraInfo.style.display = 'block';
            });
            
            stopBtn.addEventListener('click', function() {
                stopStudentScanner();
                this.style.display = 'none';
                startBtn.style.display = 'inline-block';
                if (switchBtn) switchBtn.style.display = 'none';
                if (cameraInfo) cameraInfo.style.display = 'none';
            });
        }
        
        if (switchBtn) {
            switchBtn.addEventListener('click', function() {
                switchStudentCamera();
            });
        }
        
    } catch (error) {
        console.error('Error initializing student QR scanner:', error);
    }
}

// Start student scanner
function startStudentScanner() {
    if (!studentScanner) return;
    
    Html5Qrcode.getCameras().then(devices => {
        if (devices && devices.length) {
            availableCameras = devices;
            
            // Try to find the back camera first
            let selectedCameraIndex = 0; // fallback to first camera
            
            // Look for back/rear camera
            for (let i = 0; i < devices.length; i++) {
                const label = devices[i].label.toLowerCase();
                if (label.includes('back') || label.includes('rear') || label.includes('environment')) {
                    selectedCameraIndex = i;
                    console.log('Using back camera:', devices[i].label);
                    break;
                }
            }
            
            // If no back camera found by label, try to use the last camera (usually back camera)
            if (selectedCameraIndex === 0 && devices.length > 1) {
                selectedCameraIndex = devices.length - 1;
                console.log('Using last camera (likely back camera):', devices[selectedCameraIndex].label);
            }
            
            currentCameraIndex = selectedCameraIndex;
            updateCameraLabel();
            
            const config = {
                fps: 10,
                qrbox: { width: 280, height: 280 },
                aspectRatio: 1.0,
                disableFlip: false,
                videoConstraints: {
                    facingMode: { ideal: "environment" }, // Prefer back camera
                    width: { ideal: 1280 },
                    height: { ideal: 720 }
                },
                experimentalFeatures: {
                    useBarCodeDetectorIfSupported: true
                }
            };
            
            studentScanner.start(
                devices[currentCameraIndex].id,
                config,
                handleStudentScanResult,
                handleStudentScanError
            ).catch(err => {
                console.error('Error starting student scanner with back camera:', err);
                // Fallback: try with simpler config and environment facing mode
                const fallbackConfig = {
                    fps: 10,
                    qrbox: { width: 250, height: 250 },
                    aspectRatio: 1.0,
                    videoConstraints: {
                        facingMode: "environment" // Force back camera
                    }
                };
                
                return studentScanner.start(
                    devices[currentCameraIndex].id,
                    fallbackConfig,
                    handleStudentScanResult,
                    handleStudentScanError
                );
            }).catch(err => {
                console.error('Error starting student scanner with fallback:', err);
                showToast('Error starting camera: ' + err.message, 'danger');
            });
        }
    }).catch(err => {
        console.error('Error getting cameras:', err);
        showToast('No cameras found. Please allow camera access.', 'danger');
    });
}

// Switch student camera
function switchStudentCamera() {
    if (!studentScanner || !availableCameras || availableCameras.length <= 1) {
        showToast('Only one camera available', 'info');
        return;
    }
    
    // Stop current scanner
    if (studentScanner.isScanning) {
        studentScanner.stop().then(() => {
            // Switch to next camera
            currentCameraIndex = (currentCameraIndex + 1) % availableCameras.length;
            updateCameraLabel();
            
            // Start with new camera
            const config = {
                fps: 10,
                qrbox: { width: 280, height: 280 },
                aspectRatio: 1.0,
                disableFlip: false,
                videoConstraints: {
                    width: { ideal: 1280 },
                    height: { ideal: 720 }
                }
            };
            
            studentScanner.start(
                availableCameras[currentCameraIndex].id,
                config,
                handleStudentScanResult,
                handleStudentScanError
            ).catch(err => {
                console.error('Error switching camera:', err);
                showToast('Error switching camera: ' + err.message, 'danger');
            });
        }).catch(err => {
            console.error('Error stopping scanner for camera switch:', err);
        });
    }
}

// Update camera label
function updateCameraLabel() {
    const labelElement = document.getElementById('currentCameraLabel');
    if (labelElement && availableCameras && availableCameras[currentCameraIndex]) {
        const cameraLabel = availableCameras[currentCameraIndex].label;
        const isBackCamera = cameraLabel.toLowerCase().includes('back') || 
                            cameraLabel.toLowerCase().includes('rear') || 
                            cameraLabel.toLowerCase().includes('environment');
        
        labelElement.textContent = isBackCamera ? 'Back Camera' : 
                                  (cameraLabel.toLowerCase().includes('front') || 
                                   cameraLabel.toLowerCase().includes('user') ? 'Front Camera' : 
                                   `Camera ${currentCameraIndex + 1}`);
    }
}

// Stop student scanner
function stopStudentScanner() {
    if (studentScanner && studentScanner.isScanning) {
        studentScanner.stop().then(() => {
            console.log('Student scanner stopped');
        }).catch(err => {
            console.error('Error stopping student scanner:', err);
        });
    }
}

// Handle student scan result
function handleStudentScanResult(qrCodeMessage) {
    console.log('Student scanned QR:', qrCodeMessage);
    
    // Stop scanner temporarily
    if (studentScanner && studentScanner.isScanning) {
        studentScanner.pause();
    }
    
    // Play success sound
    playBeepSound();
    
    // Process teacher QR code
    processTeacherQRCode(qrCodeMessage);
}

// Handle student scan error
function handleStudentScanError(err) {
    // Only log meaningful errors
    if (err && typeof err === 'string' && !err.includes('No MultiFormat Readers')) {
        console.warn('Student QR scan error:', err);
    }
}

// Process teacher QR code
function processTeacherQRCode(qrData) {
    try {
        // Parse the teacher QR data
        let teacherData;
        try {
            teacherData = JSON.parse(qrData);
        } catch (e) {
            throw new Error('Invalid QR code format');
        }
        
        // Validate required fields
        if (!teacherData.teacher_id || !teacherData.subject_id || !teacherData.section_id) {
            throw new Error('Invalid teacher QR code - missing required data');
        }
        
        // Check if attendance_type is specified (new QR codes will have this)
        if (!teacherData.attendance_type) {
            throw new Error('This QR code is outdated. Please ask your teacher to generate a new QR code with attendance type (IN/OUT).');
        }
        
        // Check time restrictions on client side
        const now = new Date();
        const manilaTimeString = now.toLocaleString("en-US", {timeZone: "Asia/Manila", hour12: false});
        const manilaDateParts = manilaTimeString.split(', ');
        const manilaTimePart = manilaDateParts[1]; // Gets "HH:MM:SS"
        const [hours, minutes] = manilaTimePart.split(':').map(Number);
        const currentTime = hours * 100 + minutes; // Convert to HHMM format
        const lateThreshold = 715; // 7:15 AM
        const absentCutoff = 1631; // 4:31 PM (16:31)
        
        // Check if it's past the attendance cutoff time for TIME IN
        if (teacherData.attendance_type === 'in' && currentTime > absentCutoff) {
            throw new Error('Time IN recording period has ended. Students cannot scan Time IN QR codes after 4:31 PM.');
        }
        
        // Show time-based warning messages for TIME IN
        let timeWarning = '';
        if (teacherData.attendance_type === 'in') {
            if (currentTime > lateThreshold && currentTime <= absentCutoff) {
                timeWarning = 'Warning: You are scanning after 7:15 AM. This will be marked as LATE.';
                showToast(timeWarning, 'warning');
            } else if (currentTime <= lateThreshold) {
                timeWarning = 'Good! You are on time. This will be marked as PRESENT.';
                showToast(timeWarning, 'success');
            }
        } else if (teacherData.attendance_type === 'out') {
            showToast('Scanning TIME OUT QR code...', 'info');
        }
        
        // Send attendance request
        const formData = new FormData();
        formData.append('action', 'record_attendance');
        formData.append('teacher_id', teacherData.teacher_id);
        formData.append('subject_id', teacherData.subject_id);
        formData.append('section_id', teacherData.section_id);
        formData.append('attendance_session_id', teacherData.session_id || '');
        formData.append('attendance_type', teacherData.attendance_type); // Include attendance type
        
        fetch('api/student-scan-attendance.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            // Check if response is ok
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            // Get response text first to check if it's valid JSON
            return response.text();
        })
        .then(text => {
            // Try to parse JSON
            let data;
            try {
                data = JSON.parse(text);
            } catch (parseError) {
                console.error('Invalid JSON response:', text);
                throw new Error('Server returned invalid response. Please check server logs.');
            }
            
            if (data.success) {
                console.log('QR Scan Success Response:', data); // Debug log
                
                // Log SMS status for debugging
                if (data.sms_sent !== undefined) {
                    console.log('SMS Status:', {
                        sent: data.sms_sent,
                        status: data.sms_status,
                        message: data.sms_message
                    });
                }
                
                showStudentScanResult(data, 'success');
            } else {
                console.log('QR Scan Error Response:', data); // Debug log
                
                // Check if this is an SMS-related error but attendance was recorded
                if (data.attendance_recorded === true && data.sms_sent === false) {
                    // Attendance was successful but SMS failed
                    data.message = (data.message || 'Attendance recorded successfully') + ' (SMS notification failed)';
                    showStudentScanResult(data, 'warning'); // Use warning instead of danger
                } else {
                    showStudentScanResult(data, 'danger');
                }
            }
            
            // Resume scanning after delay
            setTimeout(() => {
                if (studentScanner && studentScanner.isPaused) {
                    studentScanner.resume();
                }
            }, 3000);
        })
        .catch(error => {
            console.error('Error recording attendance:', error);
            showToast('Error recording attendance: ' + error.message, 'danger');
            
            // Resume scanning after delay
            setTimeout(() => {
                if (studentScanner && studentScanner.isPaused) {
                    studentScanner.resume();
                }
            }, 2000);
        });
        
    } catch (error) {
        console.error('Error processing teacher QR code:', error);
        showToast(error.message, 'danger');
        
        // Resume scanning after delay
        setTimeout(() => {
            if (studentScanner && studentScanner.isPaused) {
                studentScanner.resume();
            }
        }, 2000);
    }
}

// Show student scan result
function showStudentScanResult(data, type) {
    const alertClass = type === 'success' ? 'alert-success' : 
                      (type === 'warning' ? 'alert-warning' : 'alert-danger');
    const icon = type === 'success' ? 'fa-check-circle' : 
                 (type === 'warning' ? 'fa-exclamation-triangle' : 'fa-times-circle');
    const titleText = type === 'success' ? 'Success!' : 
                     (type === 'warning' ? 'Partial Success!' : 'Error!');
    
    // Build detailed message with attendance type information
    let detailedMessage = data.message;
    
    // Add attendance type context if available
    if (data.attendance_type && data.attendance_action) {
        const typeIcon = data.attendance_type === 'in' ? '→' : '←';
        const typeColor = data.attendance_type === 'in' ? 'success' : 'primary';
        detailedMessage += `<br><span class="badge bg-${typeColor} mt-2"><i class="fas fa-${data.attendance_type === 'in' ? 'sign-in-alt' : 'sign-out-alt'} me-1"></i>${typeIcon} ${data.attendance_action}</span>`;
    }
    
    // Add SMS status information if available
    if (data.sms_message) {
        let smsIcon = '';
        let smsClass = '';
        let smsLabel = '';
        
        if (data.sms_sent === true || data.sms_status === 'sent' || data.sms_status === 'checkout_sent') {
            smsIcon = '<i class="fas fa-check text-success me-1"></i>';
            smsClass = 'text-success';
            smsLabel = 'SMS Sent';
        } else if (data.sms_status === 'already_sent') {
            smsIcon = '<i class="fas fa-info-circle text-info me-1"></i>';
            smsClass = 'text-info';
            smsLabel = 'SMS Already Sent Today';
        } else if (data.sms_status === 'failed') {
            smsIcon = '<i class="fas fa-times text-danger me-1"></i>';
            smsClass = 'text-danger';
            smsLabel = 'SMS Failed';
        } else {
            smsIcon = '<i class="fas fa-exclamation-triangle text-warning me-1"></i>';
            smsClass = 'text-warning';
            smsLabel = 'SMS Status';
        }
        
        detailedMessage += `<br><small class="${smsClass}">${smsIcon}${smsLabel}: ${data.sms_message}</small>`;
    } else if (data.sms_sent === false) {
        // Show SMS not sent status even if no message is provided
        detailedMessage += `<br><small class="text-muted"><i class="fas fa-mobile-alt me-1"></i>SMS: Not configured or unavailable</small>`;
    }
    
    const alertHtml = `
        <div class="alert ${alertClass} alert-dismissible fade show">
            <i class="fas ${icon} me-2"></i>
            <strong>${titleText}</strong> ${detailedMessage}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    // Show alert above scanner
    const scannerContainer = document.querySelector('.scanner-container');
    if (scannerContainer) {
        const existingAlert = scannerContainer.querySelector('.alert');
        if (existingAlert) {
            existingAlert.remove();
        }
        scannerContainer.insertAdjacentHTML('afterbegin', alertHtml);
    }
    
    // Show basic message in toast
    const basicMessage = data.attendance_action ? `${data.attendance_action}: ${data.message}` : data.message;
    showToast(basicMessage, type);
    
    // Show enhanced SMS notification feedback
    if (data.sms_sent === true && (data.sms_status === 'sent' || data.sms_status === 'checkout_sent')) {
        // Success notification with more details
        setTimeout(() => {
            const parentMsg = data.student_name ? `📱 SMS notification sent to ${data.student_name}'s parent` : '📱 SMS notification sent to parent';
            showToast(parentMsg, 'info');
        }, 1000);
    } else if (data.sms_status === 'already_sent') {
        // Already sent notification
        setTimeout(() => {
            showToast('📱 SMS already sent today - no duplicate message sent', 'info');
        }, 1000);
    } else if (data.sms_sent === false && data.sms_message) {
        // Failed notification with reason
        setTimeout(() => {
            const errorMsg = data.sms_message.includes('not configured') ? 
                '📱 SMS not configured - parent not notified' : 
                '📱 SMS notification failed - ' + data.sms_message;
            showToast(errorMsg, 'warning');
        }, 1000);
    }
    
    // Auto-hide alert after 10 seconds for better UX
    if (scannerContainer) {
        setTimeout(() => {
            const alert = scannerContainer.querySelector('.alert');
            if (alert) {
                alert.classList.remove('show');
                setTimeout(() => alert.remove(), 300);
            }
        }, 10000);
    }
}

// Function to check and trigger auto-absent at 4:31 PM
// Function to check and trigger auto-absent after 4:31 PM
function checkAutoAbsent() {
    const now = new Date();
    const manilaTimeString = now.toLocaleString("en-US", {timeZone: "Asia/Manila", hour12: false});
    const manilaDateParts = manilaTimeString.split(', ');
    const manilaTimePart = manilaDateParts[1]; // Gets "HH:MM:SS"
    const [hours, minutes] = manilaTimePart.split(':').map(Number);
    const currentTime = hours * 100 + minutes;
    const absentCutoff = 1631; // 4:31 PM
    
    // Get day of week for Manila time
    const manilaDateString = now.toLocaleDateString("en-US", {timeZone: "Asia/Manila"});
    const manilaDate = new Date(manilaDateString);
    const dayOfWeek = manilaDate.getDay(); // 0 = Sunday, 1 = Monday, ..., 6 = Saturday
    
    // Only trigger auto-absent after 4:31 PM on weekdays (Monday to Friday)
    if (currentTime >= absentCutoff && dayOfWeek >= 1 && dayOfWeek <= 5) {
        // Check if we haven't already triggered it today
        const today = now.toISOString().split('T')[0];
        const lastAutoAbsentDate = localStorage.getItem('lastAutoAbsentDate');
        
        if (lastAutoAbsentDate !== today) {
            console.log('Dashboard: Triggering auto-absent marking after 4:31 PM...');
            triggerAutoAbsent();
            localStorage.setItem('lastAutoAbsentDate', today);
        }
    }
}

// Function to trigger auto-absent API
async function triggerAutoAbsent() {
    try {
        console.log('Dashboard: Calling auto-absent API...');
        
        const response = await fetch('api/auto-absent.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                trigger_source: 'dashboard'
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            console.log('Dashboard: Auto-absent completed:', data);
            
            // Show notification if students were marked absent
            if (data.data && data.data.total_students_marked > 0) {
                const studentsCount = data.data.total_students_marked;
                const recordsCount = data.data.total_attendance_records;
                const message = `Auto-absent completed: ${studentsCount} student${studentsCount > 1 ? 's' : ''} marked absent across ${recordsCount} subject record${recordsCount > 1 ? 's' : ''} after 4:30 PM`;
                
                showToast(message, 'info');
                
                // Show details of processed students in console
                if (data.data.processed_students && data.data.processed_students.length > 0) {
                    console.log('Dashboard: Students marked absent:');
                    data.data.processed_students.forEach(student => {
                        const subjectsText = student.absent_subjects ? 
                            student.absent_subjects.map(s => s.subject_name).join(', ') : 
                            'Unknown subjects';
                        console.log(`- ${student.name} (${student.username}) from ${student.section}: ${subjectsText}`);
                    });
                }
            } else {
                console.log('Dashboard: Auto-absent check completed - no students to mark absent');
            }
        } else {
            console.log('Dashboard: Auto-absent API response:', data.message);
            
            // Only show error toast for actual errors, not expected scenarios
            if (!data.data || (!data.data.already_processed && !data.data.is_weekend)) {
                showToast('Auto-absent check failed: ' + data.message, 'warning');
            }
        }
    } catch (error) {
        console.error('Dashboard: Error calling auto-absent API:', error);
        showToast('Failed to check auto-absent status', 'danger');
    }
}
<?php endif; ?>

<?php if ($user_role == 'teacher'): ?>
// Teacher QR Generator Functionality
let currentTeacherQRData = null;

// Initialize teacher QR generator
function initTeacherQRGenerator() {
    const subjectSelect = document.getElementById('teacher-subject-select');
    const sectionSelect = document.getElementById('teacher-section-select');
    const attendanceTypeIn = document.getElementById('attendanceTypeIn');
    const attendanceTypeOut = document.getElementById('attendanceTypeOut');
    const generateBtn = document.getElementById('generateTeacherQRBtn');
    const downloadBtn = document.getElementById('downloadTeacherQRBtn');
    const printBtn = document.getElementById('printTeacherQRBtn');
    
    if (subjectSelect && sectionSelect && generateBtn && attendanceTypeIn && attendanceTypeOut) {
        // Enable generate button when both subject and section are selected
        function checkSelection() {
            const canGenerate = subjectSelect.value && sectionSelect.value;
            generateBtn.disabled = !canGenerate;
            
            if (!canGenerate) {
                // Hide QR code and reset container
                const container = document.getElementById('teacher-qr-container');
                if (container) {
                    container.innerHTML = `
                        <div class="py-4">
                            <i class="fas fa-qrcode fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">Select Subject & Section</h5>
                            <p class="text-muted small">Choose subject and section above, then select attendance type to generate QR code</p>
                        </div>
                    `;
                }
                
                // Hide download/print buttons
                if (downloadBtn) downloadBtn.style.display = 'none';
                if (printBtn) printBtn.style.display = 'none';
                
                // Clear QR info
                updateTeacherQRInfo(null);
            }
        }
        
        subjectSelect.addEventListener('change', checkSelection);
        sectionSelect.addEventListener('change', checkSelection);
        
        // Listen for attendance type changes to reset QR code when switched
        attendanceTypeIn.addEventListener('change', function() {
            if (this.checked && currentTeacherQRData) {
                // Clear current QR when switching types
                const container = document.getElementById('teacher-qr-container');
                if (container) {
                    container.innerHTML = `
                        <div class="py-4">
                            <i class="fas fa-qrcode fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">Ready to Generate TIME IN QR</h5>
                            <p class="text-muted small">Click "Generate QR Code" to create Time In QR code</p>
                        </div>
                    `;
                }
                updateTeacherQRInfo(null);
                if (downloadBtn) downloadBtn.style.display = 'none';
                if (printBtn) printBtn.style.display = 'none';
            }
        });
        
        attendanceTypeOut.addEventListener('change', function() {
            if (this.checked && currentTeacherQRData) {
                // Clear current QR when switching types
                const container = document.getElementById('teacher-qr-container');
                if (container) {
                    container.innerHTML = `
                        <div class="py-4">
                            <i class="fas fa-qrcode fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">Ready to Generate TIME OUT QR</h5>
                            <p class="text-muted small">Click "Generate QR Code" to create Time Out QR code</p>
                        </div>
                    `;
                }
                updateTeacherQRInfo(null);
                if (downloadBtn) downloadBtn.style.display = 'none';
                if (printBtn) printBtn.style.display = 'none';
            }
        });
        
        // Generate QR code
        generateBtn.addEventListener('click', generateTeacherQRCode);
        
        // Download QR code
        if (downloadBtn) {
            downloadBtn.addEventListener('click', downloadTeacherQRCode);
        }
        
        // Print QR code
        if (printBtn) {
            printBtn.addEventListener('click', printTeacherQRCode);
        }
    }
}

// Generate teacher QR code
function generateTeacherQRCode() {
    const subjectSelect = document.getElementById('teacher-subject-select');
    const sectionSelect = document.getElementById('teacher-section-select');
    const attendanceTypeIn = document.getElementById('attendanceTypeIn');
    const attendanceTypeOut = document.getElementById('attendanceTypeOut');
    
    if (!subjectSelect.value || !sectionSelect.value) {
        showToast('Please select both subject and section', 'warning');
        return;
    }
    
    // Get selected attendance type
    const attendanceType = attendanceTypeIn.checked ? 'in' : 'out';
    
    // Create QR data with attendance type
    const sessionId = 'session_' + Date.now();
    const qrData = {
        teacher_id: <?php echo $current_user['id']; ?>,
        teacher_name: '<?php echo addslashes($current_user['full_name']); ?>',
        subject_id: parseInt(subjectSelect.value),
        subject_name: subjectSelect.options[subjectSelect.selectedIndex].text,
        section_id: parseInt(sectionSelect.value),
        section_name: sectionSelect.options[sectionSelect.selectedIndex].text,
        session_id: sessionId,
        attendance_type: attendanceType, // Add attendance type
        created_at: new Date().toISOString(),
        expires_at: new Date(Date.now() + 24 * 60 * 60 * 1000).toISOString() // 24 hours
    };
    
    currentTeacherQRData = qrData;
    
    // Generate QR code with visual distinction for type
    const qrDataString = JSON.stringify(qrData);
    const qrContainer = document.getElementById('teacher-qr-container');
    
    if (qrContainer) {
        // Use QR Server API to generate QR code
        const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=${encodeURIComponent(qrDataString)}&format=png&margin=10`;
        
        // Color coding for attendance type
        const typeColor = attendanceType === 'in' ? '#28a745' : '#dc3545'; // Green for IN, Red for OUT
        const typeIcon = attendanceType === 'in' ? 'sign-in-alt' : 'sign-out-alt';
        const typeText = attendanceType === 'in' ? 'TIME IN' : 'TIME OUT';
        
        qrContainer.innerHTML = `
            <div class="qr-display-container">
                <div class="text-center mb-3">
                    <span class="badge fs-6 px-3 py-2" style="background-color: ${typeColor}; color: white;">
                        <i class="fas fa-${typeIcon} me-2"></i>${typeText}
                    </span>
                </div>
                <img src="${qrUrl}" 
                     alt="Teacher QR Code - ${typeText}" 
                     class="img-fluid border rounded shadow" 
                     style="max-width: 300px; height: auto; border: 3px solid ${typeColor} !important;"
                     onload="this.style.opacity=1" 
                     style="opacity: 0; transition: opacity 0.3s;"
                     onerror="handleQRGenerationError(this)">
                <div class="mt-3">
                    <h6 class="text-primary fw-bold">${qrData.subject_name}</h6>
                    <p class="text-muted mb-0">${qrData.section_name}</p>
                    <small class="text-muted">Session ID: ${sessionId}</small>
                </div>
            </div>
        `;
        
        // Show download/print buttons
        const downloadBtn = document.getElementById('downloadTeacherQRBtn');
        const printBtn = document.getElementById('printTeacherQRBtn');
        if (downloadBtn) downloadBtn.style.display = 'inline-block';
        if (printBtn) printBtn.style.display = 'inline-block';
        
        // Update QR info
        updateTeacherQRInfo(qrData);
        
        // Add to active sessions
        addToActiveSessions(qrData);
        
        showToast('QR code generated successfully!', 'success');
    }
}

// Handle QR generation error
function handleQRGenerationError(img) {
    console.error('QR generation failed');
    img.parentElement.innerHTML = `
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle me-2"></i>
            QR Code generation failed. Please try again.
        </div>
    `;
}

// Update teacher QR info
function updateTeacherQRInfo(qrData) {
    const infoContainer = document.getElementById('teacherQRInfo');
    if (!infoContainer) return;
    
    if (!qrData) {
        infoContainer.innerHTML = `
            <div class="text-center text-muted py-3">
                <i class="fas fa-info fa-2x mb-2"></i>
                <p class="mb-0">Generate QR code to see details</p>
            </div>
        `;
        return;
    }
    
    const typeColor = qrData.attendance_type === 'in' ? '#28a745' : '#dc3545';
    const typeIcon = qrData.attendance_type === 'in' ? 'sign-in-alt' : 'sign-out-alt';
    const typeText = qrData.attendance_type === 'in' ? 'TIME IN' : 'TIME OUT';
    
    infoContainer.innerHTML = `
        <div class="qr-info">
            <div class="info-item mb-3">
                <span class="badge fs-6 px-3 py-2" style="background-color: ${typeColor}; color: white;">
                    <i class="fas fa-${typeIcon} me-2"></i>${typeText}
                </span>
            </div>
            <div class="info-item mb-2">
                <small class="text-muted">Subject</small>
                <div class="fw-semibold">${qrData.subject_name}</div>
            </div>
            <div class="info-item mb-2">
                <small class="text-muted">Section</small>
                <div class="fw-semibold">${qrData.section_name}</div>
            </div>
            <div class="info-item mb-2">
                <small class="text-muted">Session ID</small>
                <div class="fw-semibold small">${qrData.session_id}</div>
            </div>
            <div class="info-item mb-2">
                <small class="text-muted">Created</small>
                <div class="small">${new Date(qrData.created_at).toLocaleString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true, timeZone: 'Asia/Manila' })}</div>
            </div>
            <div class="info-item">
                <small class="text-muted">Expires</small>
                <div class="small">${new Date(qrData.expires_at).toLocaleString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true, timeZone: 'Asia/Manila' })}</div>
            </div>
        </div>
    `;
}

// Add to active sessions
function addToActiveSessions(qrData) {
    const sessionsContainer = document.getElementById('activeSessions');
    if (!sessionsContainer) return;
    
    // Remove placeholder if present
    const placeholder = sessionsContainer.querySelector('.text-muted.py-3');
    if (placeholder) {
        sessionsContainer.innerHTML = '';
    }
    
    const typeColor = qrData.attendance_type === 'in' ? '#28a745' : '#dc3545';
    const typeIcon = qrData.attendance_type === 'in' ? 'sign-in-alt' : 'sign-out-alt';
    const typeText = qrData.attendance_type === 'in' ? 'IN' : 'OUT';
    
    // Create session item
    const sessionItem = document.createElement('div');
    sessionItem.className = 'session-item border-start border-4 ps-2 mb-2';
    sessionItem.style.borderColor = typeColor + ' !important';
    sessionItem.id = 'session-' + qrData.session_id;
    sessionItem.innerHTML = `
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <div class="d-flex align-items-center gap-2 mb-1">
                    <strong class="small">${qrData.subject_name}</strong>
                    <span class="badge badge-sm" style="background-color: ${typeColor}; color: white; font-size: 0.7rem;">
                        <i class="fas fa-${typeIcon} me-1"></i>${typeText}
                    </span>
                </div>
                <div class="text-muted small">${qrData.section_name}</div>
                <div class="text-muted small">${new Date(qrData.created_at).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true, timeZone: 'Asia/Manila' })}</div>
            </div>
            <button class="btn btn-sm btn-outline-danger" onclick="endSession('${qrData.session_id}')">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
    
    // Add to container
    sessionsContainer.insertBefore(sessionItem, sessionsContainer.firstChild);
}

// End session
function endSession(sessionId) {
    const sessionElement = document.getElementById('session-' + sessionId);
    if (sessionElement) {
        sessionElement.remove();
        showToast('Session ended', 'info');
        
        // If no more sessions, show placeholder
        const sessionsContainer = document.getElementById('activeSessions');
        if (sessionsContainer && sessionsContainer.children.length === 0) {
            sessionsContainer.innerHTML = `
                <div class="text-center text-muted py-3">
                    <i class="fas fa-clock fa-2x mb-2"></i>
                    <p class="mb-0">No active sessions</p>
                </div>
            `;
        }
    }
}

// Download teacher QR code
function downloadTeacherQRCode() {
    if (!currentTeacherQRData) {
        showToast('No QR code to download', 'warning');
        return;
    }
    
    const img = document.querySelector('#teacher-qr-container img');
    if (img) {
        const typeText = currentTeacherQRData.attendance_type === 'in' ? 'TimeIn' : 'TimeOut';
        const link = document.createElement('a');
        link.download = `attendance_qr_${typeText}_${currentTeacherQRData.subject_name}_${currentTeacherQRData.section_name}.png`;
        link.href = img.src;
        link.target = '_blank';
        link.click();
        showToast('QR code downloaded', 'success');
    }
}

// Print teacher QR code
function printTeacherQRCode() {
    if (!currentTeacherQRData) {
        showToast('No QR code to print', 'warning');
        return;
    }
    
    const img = document.querySelector('#teacher-qr-container img');
    if (img) {
        const typeColor = currentTeacherQRData.attendance_type === 'in' ? '#28a745' : '#dc3545';
        const typeText = currentTeacherQRData.attendance_type === 'in' ? 'TIME IN' : 'TIME OUT';
        const typeIcon = currentTeacherQRData.attendance_type === 'in' ? '→' : '←';
        
        const printWindow = window.open('', '_blank');
        const qrInfo = `
            <div style="text-align: center; padding: 20px; font-family: Arial, sans-serif;">
                <h1 style="color: #007bff; margin-bottom: 10px;">KES-SMART Attendance</h1>
                <div style="background-color: ${typeColor}; color: white; padding: 10px 20px; border-radius: 15px; display: inline-block; margin-bottom: 20px; font-size: 18px; font-weight: bold;">
                    ${typeIcon} ${typeText}
                </div>
                <h2 style="margin-bottom: 10px;">${currentTeacherQRData.subject_name}</h2>
                <h3 style="margin-bottom: 20px; color: #666;">${currentTeacherQRData.section_name}</h3>
                <div style="margin: 20px 0;">
                    <img src="${img.src}" style="border: 3px solid ${typeColor}; border-radius: 10px; max-width: 300px;">
                </div>
                <p style="margin-top: 20px; font-size: 14px; color: #666;">
                    Students: Scan this QR code to record your ${typeText.toLowerCase()}
                </p>
                <p style="font-size: 12px; color: #999;">
                    Session ID: ${currentTeacherQRData.session_id}<br>
                    Generated: ${new Date(currentTeacherQRData.created_at).toLocaleString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true, timeZone: 'Asia/Manila' })}<br>
                    Teacher: ${currentTeacherQRData.teacher_name}
                </p>
            </div>
        `;
        
        printWindow.document.write(`
            <html>
                <head>
                    <title>Attendance QR Code - ${typeText} - ${currentTeacherQRData.subject_name}</title>
                    <style>
                        @media print {
                            body { margin: 0; }
                            img { border: 3px solid ${typeColor}; border-radius: 10px; }
                        }
                    </style>
                </head>
                <body>
                    ${qrInfo}
                </body>
            </html>
        `);
        printWindow.document.close();
        printWindow.print();
    }
}
<?php endif; ?>

// Toast notification function
// Toast notifications disabled - messages logged to console instead
function showToast(message, type = 'info') {
    console.log(`[${type.toUpperCase()}] ${message}`);
}

// Play beep sound for successful scans
function playBeepSound() {
    // Check if AudioContext is supported
    if (typeof AudioContext !== 'undefined' || typeof webkitAudioContext !== 'undefined') {
        try {
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
            gainNode.gain.value = 0.3;
            
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
        } catch (error) {
            console.log('Audio not available:', error);
        }
    }
}

// Initialize appropriate scanner/generator on page load
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($user_role == 'student'): ?>
    // Initialize student QR scanner
    if (typeof Html5Qrcode !== 'undefined') {
        initStudentQRScanner();
    } else {
        console.error('Html5Qrcode library not loaded');
    }
    
    // Initialize time display for attendance rules
    updateTimeDisplay();
    // Update time every second
    setInterval(updateTimeDisplay, 1000);
    
    // Auto-check for 4:31 PM every minute to trigger auto-absent
    setInterval(checkAutoAbsent, 60000);
    
    // Also check immediately after page loads (with delay)
    setTimeout(() => {
        checkAutoAbsent();
        console.log('Dashboard auto-absent check initialized - will trigger at 4:31 PM');
    }, 3000);
    <?php endif; ?>
    
    <?php if ($user_role == 'teacher'): ?>
    // Initialize teacher QR generator
    initTeacherQRGenerator();
    <?php endif; ?>
});
</script>

<!-- QR Scanner Library -->
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>

<!-- Toast Container -->
<!-- Toast container removed - notifications disabled -->

<?php include 'footer.php'; ?>
