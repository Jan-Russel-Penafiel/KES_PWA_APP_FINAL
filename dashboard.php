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

/* QR Code Generator Styles */
.qr-display-container {
    transition: all 0.3s ease;
}

.qr-display-container:hover {
    transform: scale(1.02);
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
        
        $my_subjects = $pdo->prepare("SELECT COUNT(*) FROM subjects WHERE teacher_id = ? AND status = 'active'");
        $my_subjects->execute([$current_user['id']]);
        $total_subjects = $my_subjects->fetchColumn();
        
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
        <div class="col-6 col-md-3">
            <div class="card bg-primary text-white h-100 shadow-sm rounded-3">
                <div class="card-body p-3 text-center">
                    <div class="icon-bg rounded-circle bg-white bg-opacity-25 p-2 mx-auto mb-2" style="width: fit-content;">
                        <i class="fas fa-user-graduate text-white"></i>
                    </div>
                    <h4 class="h5 fw-bold mb-1"><?php echo number_format($total_students); ?></h4>
                    <p class="mb-0 small">Students</p>
                </div>
            </div>
        </div>
        
        <div class="col-6 col-md-3">
            <div class="card bg-success text-white h-100 shadow-sm rounded-3">
                <div class="card-body p-3 text-center">
                    <div class="icon-bg rounded-circle bg-white bg-opacity-25 p-2 mx-auto mb-2" style="width: fit-content;">
                        <i class="fas fa-chalkboard-teacher text-white"></i>
                    </div>
                    <h4 class="h5 fw-bold mb-1"><?php echo number_format($total_teachers); ?></h4>
                    <p class="mb-0 small">Teachers</p>
                </div>
            </div>
        </div>
        
        <div class="col-6 col-md-3">
            <div class="card bg-info text-white h-100 shadow-sm rounded-3">
                <div class="card-body p-3 text-center">
                    <div class="icon-bg rounded-circle bg-white bg-opacity-25 p-2 mx-auto mb-2" style="width: fit-content;">
                        <i class="fas fa-heart text-white"></i>
                    </div>
                    <h4 class="h5 fw-bold mb-1"><?php echo number_format($total_parents); ?></h4>
                    <p class="mb-0 small">Parents</p>
                </div>
            </div>
        </div>
        
        <div class="col-6 col-md-3">
            <div class="card bg-warning text-white h-100 shadow-sm rounded-3">
                <div class="card-body p-3 text-center">
                    <div class="icon-bg rounded-circle bg-white bg-opacity-25 p-2 mx-auto mb-2" style="width: fit-content;">
                        <i class="fas fa-school text-white"></i>
                    </div>
                    <h4 class="h5 fw-bold mb-1"><?php echo number_format($total_sections); ?></h4>
                    <p class="mb-0 small">Sections</p>
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
        <div class="col-6 col-md-6 col-lg-3">
            <div class="card bg-primary text-white h-100 shadow-sm rounded-3">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="h5 fw-bold mb-1"><?php echo number_format($total_students); ?></h4>
                            <p class="mb-0 small">My Students</p>
                        </div>
                        <div class="icon-bg rounded-circle bg-white bg-opacity-25 p-2">
                            <i class="fas fa-users text-white"></i>
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
                            <h4 class="h5 fw-bold mb-1"><?php echo number_format($total_sections); ?></h4>
                            <p class="mb-0 small">My Sections</p>
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
        
        <div class="col-6 col-md-6 col-lg-3">
            <div class="card bg-info text-white h-100 shadow-sm rounded-3">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="h5 fw-bold mb-1"><?php echo number_format($total_subjects); ?></h4>
                            <p class="mb-0 small">My Subjects</p>
                        </div>
                        <div class="icon-bg rounded-circle bg-white bg-opacity-25 p-2">
                            <i class="fas fa-book text-white"></i>
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
        
        <div class="col-6 col-md-6 col-lg-3">
            <div class="card bg-warning text-white h-100 shadow-sm rounded-3">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="h5 fw-bold mb-1"><?php echo number_format($total_scans); ?></h4>
                            <p class="mb-0 small">Today's Scans</p>
                        </div>
                        <div class="icon-bg rounded-circle bg-white bg-opacity-25 p-2">
                            <i class="fas fa-qrcode text-white"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-transparent border-0 py-2">
                    <a href="attendance.php" class="text-white text-decoration-none small stretched-link">
                        View Details <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Teacher Absent Notification Section -->
    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="card shadow-sm rounded-3">
                <div class="card-header bg-transparent py-3 text-center">
                    <h5 class="card-title h6 fw-bold mb-0">
                        <i class="fas fa-user-times me-2"></i>Teacher Absence Notification
                    </h5>
                </div>
                <div class="card-body p-3">
                    <div class="alert alert-warning mb-3">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Send Absence Notification</strong><br>
                        Use this feature to notify all parents of your students that you are absent today. This will send SMS notifications to all parents whose children are in your classes.
                    </div>
                    
                    <div class="text-center">
                        <button id="teacherAbsentBtn" class="btn btn-warning btn-lg">
                            <i class="fas fa-user-times me-2"></i>Mark Teacher Absent & Notify Parents
                        </button>
                        
                        <!-- Loading state -->
                        <div id="teacherAbsentLoading" class="d-none">
                            <div class="spinner-border text-warning me-2" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            Sending notifications...
                        </div>
                        
                        <!-- Result display -->
                        <div id="teacherAbsentResult" class="mt-3"></div>
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
                                <h6 class="mb-0 fw-bold">TAC SCHOOL</h6>
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
                    <h6 class="school-name">TAC SCHOOL</h6>
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
            <h1 style="color: #007bff; margin-bottom: 10px;">TAC-SMART</h1>
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
                text: 'My TAC-SMART attendance QR code',
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
// Student dashboard functionality (QR scanner removed)
<?php endif; ?>

<?php if ($user_role == 'teacher'): ?>
// Teacher Absent Notification Functionality
function initTeacherAbsentButton() {
    const absentBtn = document.getElementById('teacherAbsentBtn');
    const loadingDiv = document.getElementById('teacherAbsentLoading');
    const resultDiv = document.getElementById('teacherAbsentResult');
    
    if (absentBtn) {
        absentBtn.addEventListener('click', function() {
            // Confirm action
            if (!confirm('Are you sure you want to mark yourself absent today and notify all parents of your students?')) {
                return;
            }
            
            // Double confirmation for safety
            if (!confirm('This will send SMS notifications to all parents. This action cannot be undone. Continue?')) {
                return;
            }
            
            // Show loading state
            absentBtn.classList.add('d-none');
            loadingDiv.classList.remove('d-none');
            resultDiv.innerHTML = '';
            
            // Call teacher absent API
            sendTeacherAbsentNotification();
        });
    }
}

// Send teacher absent notification
async function sendTeacherAbsentNotification() {
    try {
        const response = await fetch('api/teacher-absent.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            }
        });
        
        const data = await response.json();
        
        // Hide loading state
        const absentBtn = document.getElementById('teacherAbsentBtn');
        const loadingDiv = document.getElementById('teacherAbsentLoading');
        const resultDiv = document.getElementById('teacherAbsentResult');
        
        loadingDiv.classList.add('d-none');
        
        if (data.success) {
            // Show success message
            const result = data.data;
            let successHtml = `
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i>
                    <strong>Notification Sent Successfully!</strong><br>
                    <small>
                        Teacher: ${result.teacher_name}<br>
                        Date: ${result.date} at ${result.time}<br>
                        Students notified: ${result.total_students}<br>
                        SMS sent: <span class="text-success">${result.sms_sent}</span> | 
                        SMS failed: <span class="text-danger">${result.sms_failed}</span>
                    </small>
                </div>
            `;
            
            // Add details if needed
            if (result.details && result.details.length > 0) {
                successHtml += `
                    <div class="card mt-2">
                        <div class="card-header bg-transparent">
                            <h6 class="mb-0">Notification Details</h6>
                        </div>
                        <div class="card-body p-2" style="max-height: 200px; overflow-y: auto;">
                            <div class="row g-2 text-small">
                `;
                
                result.details.forEach((detail, index) => {
                    const statusColor = detail.sms_status === 'sent' ? 'text-success' : 'text-danger';
                    const statusIcon = detail.sms_status === 'sent' ? 'fa-check' : 'fa-times';
                    successHtml += `
                        <div class="col-12 border-bottom py-1">
                            <small>
                                <i class="fas ${statusIcon} ${statusColor} me-1"></i>
                                <strong>${detail.student_name}</strong> - ${detail.subject}<br>
                                <span class="${statusColor}">${detail.sms_message}</span>
                            </small>
                        </div>
                    `;
                });
                
                successHtml += `
                            </div>
                        </div>
                    </div>
                `;
            }
            
            resultDiv.innerHTML = successHtml;
            
            // Disable button for today
            absentBtn.innerHTML = '<i class="fas fa-check me-2"></i>Absent Notification Already Sent Today';
            absentBtn.classList.add('btn-secondary');
            absentBtn.classList.remove('btn-warning');
            absentBtn.disabled = true;
            
            console.log('Teacher absent notification sent successfully:', data);
            showToast(`Teacher absent notification sent to ${result.total_students} parents`, 'success');
            
        } else {
            // Show error message
            let errorHtml = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Failed to Send Notification</strong><br>
                    <small>${data.message}</small>
                </div>
            `;
            
            resultDiv.innerHTML = errorHtml;
            
            // Re-enable button if not already sent today
            if (!data.message.includes('already sent today')) {
                absentBtn.classList.remove('d-none');
            } else {
                // If already sent today, disable button
                absentBtn.innerHTML = '<i class="fas fa-check me-2"></i>Absent Notification Already Sent Today';
                absentBtn.classList.add('btn-secondary');
                absentBtn.classList.remove('btn-warning');
                absentBtn.disabled = true;
                absentBtn.classList.remove('d-none');
            }
            
            console.error('Teacher absent notification failed:', data);
            showToast('Failed to send teacher absent notification: ' + data.message, 'danger');
        }
        
    } catch (error) {
        // Hide loading state and show error
        const absentBtn = document.getElementById('teacherAbsentBtn');
        const loadingDiv = document.getElementById('teacherAbsentLoading');
        const resultDiv = document.getElementById('teacherAbsentResult');
        
        loadingDiv.classList.add('d-none');
        absentBtn.classList.remove('d-none');
        
        resultDiv.innerHTML = `
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>Connection Error</strong><br>
                <small>Unable to send notification. Please check your internet connection and try again.</small>
            </div>
        `;
        
        console.error('Teacher absent notification error:', error);
        showToast('Connection error while sending teacher absent notification', 'danger');
    }
}

// Check if teacher already sent absent notification today
async function checkTeacherAbsentStatus() {
    try {
        const today = new Date().toISOString().split('T')[0];
        
        // This would require a separate API endpoint to check status
        // For now, we'll rely on the POST response to handle "already sent" cases
        
    } catch (error) {
        console.error('Error checking teacher absent status:', error);
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

// Initialize dashboard functionality on page load
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($user_role == 'teacher'): ?>
    // Initialize teacher absent button
    initTeacherAbsentButton();
    
    // Check if teacher already sent absent notification today
    checkTeacherAbsentStatus();
    <?php endif; ?>
});
</script>



<!-- Toast Container -->
<!-- Toast container removed - notifications disabled -->

<?php include 'footer.php'; ?>
