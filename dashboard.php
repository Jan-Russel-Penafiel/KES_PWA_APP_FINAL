<?php
require_once 'config.php';

// Check if user is logged in through PHP session
if (!isLoggedIn()) {
    // If no PHP session, we'll check for client-side session in JavaScript
    // This allows for offline authentication
    // The actual check happens in the JavaScript below
    $check_offline_auth = true;
} else {
    $check_offline_auth = false;
}

// Initialize variables for both online and offline mode
$user_role = isset($_SESSION['role']) ? $_SESSION['role'] : '';
$current_user = [];

// If we have a PHP session, get user data from database
if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
    $current_user = getCurrentUser($pdo);
    $user_role = $_SESSION['role'];
}

$page_title = 'Dashboard';
include 'header.php';
?>

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
        <p>You need to log in to access this page. It appears you're currently offline.</p>
        <hr>
        <p class="mb-0">
            <a href="login.php" class="btn btn-primary btn-sm">Go to Login</a>
        </p>
    </div>
</div>

<div id="dashboard-content">
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

    <!-- Teacher QR Code for Attendance -->
    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="card shadow-sm rounded-3">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-qrcode me-2"></i>Generate Attendance QR Code</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8">
                            <form id="attendance-qr-form">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="qr-section" class="form-label">Section</label>
                                        <select class="form-select" id="qr-section" name="section_id" required>
                                            <option value="">Select Section</option>
                                            <?php
                                            try {
                                                $teacher_sections_stmt = $pdo->prepare("SELECT id, section_name, grade_level FROM sections WHERE teacher_id = ? AND status = 'active' ORDER BY section_name");
                                                $teacher_sections_stmt->execute([$current_user['id']]);
                                                $teacher_sections = $teacher_sections_stmt->fetchAll(PDO::FETCH_ASSOC);
                                                
                                                foreach ($teacher_sections as $section) {
                                                    echo "<option value='{$section['id']}'>{$section['section_name']} - Grade {$section['grade_level']}</option>";
                                                }
                                            } catch (PDOException $e) {
                                                echo "<option value=''>Error loading sections</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="qr-subject" class="form-label">Subject</label>
                                        <select class="form-select" id="qr-subject" name="subject_id" required>
                                            <option value="">Select Subject</option>
                                            <?php
                                            try {
                                                $teacher_subjects_stmt = $pdo->prepare("SELECT id, subject_name, subject_code FROM subjects WHERE teacher_id = ? AND status = 'active' ORDER BY subject_name");
                                                $teacher_subjects_stmt->execute([$current_user['id']]);
                                                $teacher_subjects = $teacher_subjects_stmt->fetchAll(PDO::FETCH_ASSOC);
                                                
                                                foreach ($teacher_subjects as $subject) {
                                                    echo "<option value='{$subject['id']}'>{$subject['subject_name']} ({$subject['subject_code']})</option>";
                                                }
                                            } catch (PDOException $e) {
                                                echo "<option value=''>Error loading subjects</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-qrcode me-2"></i>Generate QR Code
                                </button>
                                <button type="button" class="btn btn-secondary ms-2" onclick="clearQRCode()">
                                    <i class="fas fa-times me-2"></i>Clear
                                </button>
                            </form>
                        </div>
                        <div class="col-md-4">
                            <div class="text-center">
                                <div id="teacher-qr-code" class="border rounded p-3" style="min-height: 200px; display: flex; align-items: center; justify-content: center; background-color: #f8f9fa;">
                                    <div class="text-muted">
                                        <i class="fas fa-qrcode fa-3x mb-2"></i>
                                        <p class="mb-0">Select section and subject to generate QR code</p>
                                    </div>
                                </div>
                                <div id="qr-actions" class="mt-3 d-none">
                                    <button type="button" class="btn btn-outline-primary btn-sm me-2" onclick="downloadTeacherQR()">
                                        <i class="fas fa-download me-1"></i>Download
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="printTeacherQR()">
                                        <i class="fas fa-print me-1"></i>Print
                                    </button>
                                </div>
                                <div id="qr-info" class="mt-2 small text-muted d-none">
                                    <p class="mb-1"><strong>Instructions:</strong></p>
                                    <p class="mb-0">Students scan this QR code with their phones to mark attendance</p>
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

    <!-- QR Scanner Section -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-lg-8 mx-auto">
            <div class="card shadow-sm rounded-3">
                <div class="card-header bg-success text-white text-center">
                    <h5 class="mb-0"><i class="fas fa-qrcode me-2"></i>Scan Attendance QR Code</h5>
                </div>
                <div class="card-body text-center p-4">
                    <p class="text-muted mb-3">Scan your teacher's QR code to mark your attendance</p>
                    <button type="button" class="btn btn-success btn-lg" data-bs-toggle="modal" data-bs-target="#qrScannerModal">
                        <i class="fas fa-camera me-2"></i>Open QR Scanner
                    </button>
                    <div class="mt-3">
                        <small class="text-muted">
                            <i class="fas fa-info-circle me-1"></i>
                            Ask your teacher to show the attendance QR code
                        </small>
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
                            <button onclick="printStudentID()" class="btn btn-primary">
                                <i class="fas fa-print me-2"></i>Print ID Card
                            </button>
                            <button onclick="downloadStudentID()" class="btn btn-outline-primary">
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

<?php if ($user_role != 'student'): ?>
<!-- Quick Actions -->
<div class="row g-3 mb-4">
    <div class="col-12">
        <div class="card shadow-sm rounded-3">
            <div class="card-header bg-transparent py-3">
                <h5 class="card-title h6 fw-bold mb-0">
                    <i class="fas fa-bolt me-2"></i>Quick Actions
                </h5>
            </div>
            <div class="card-body p-3">
                <div class="row g-2">
                    <?php if ($user_role == 'admin'): ?>
                        <div class="col-6 col-md-3">
                            <a href="users.php" class="card h-100 text-decoration-none border-0 shadow-sm rounded-3">
                                <div class="card-body p-3 text-center">
                                    <div class="icon-bg rounded-circle bg-primary bg-opacity-10 p-2 mx-auto mb-2" style="width: fit-content;">
                                        <i class="fas fa-users text-primary"></i>
                                    </div>
                                    <span class="small fw-medium text-dark">Manage Users</span>
                                </div>
                            </a>
                        </div>
                        <div class="col-6 col-md-3">
                            <a href="sections.php" class="card h-100 text-decoration-none border-0 shadow-sm rounded-3">
                                <div class="card-body p-3 text-center">
                                    <div class="icon-bg rounded-circle bg-success bg-opacity-10 p-2 mx-auto mb-2" style="width: fit-content;">
                                        <i class="fas fa-school text-success"></i>
                                    </div>
                                    <span class="small fw-medium text-dark">Manage Sections</span>
                                </div>
                            </a>
                        </div>
                        <div class="col-6 col-md-3">
                            <a href="sms-config.php" class="card h-100 text-decoration-none border-0 shadow-sm rounded-3">
                                <div class="card-body p-3 text-center">
                                    <div class="icon-bg rounded-circle bg-info bg-opacity-10 p-2 mx-auto mb-2" style="width: fit-content;">
                                        <i class="fas fa-sms text-info"></i>
                                    </div>
                                    <span class="small fw-medium text-dark">SMS Config</span>
                                </div>
                            </a>
                        </div>
                        <div class="col-6 col-md-3">
                            <a href="reports.php" class="card h-100 text-decoration-none border-0 shadow-sm rounded-3">
                                <div class="card-body p-3 text-center">
                                    <div class="icon-bg rounded-circle bg-warning bg-opacity-10 p-2 mx-auto mb-2" style="width: fit-content;">
                                        <i class="fas fa-chart-bar text-warning"></i>
                                    </div>
                                    <span class="small fw-medium text-dark">Reports</span>
                                </div>
                            </a>
                        </div>
                    <?php elseif ($user_role == 'teacher'): ?>
                        <div class="col-6 col-md-4">
                            <a href="qr-scanner.php" class="card h-100 text-decoration-none border-0 shadow-sm rounded-3">
                                <div class="card-body p-3 text-center">
                                    <div class="icon-bg rounded-circle bg-primary bg-opacity-10 p-2 mx-auto mb-2" style="width: fit-content;">
                                        <i class="fas fa-qrcode text-primary"></i>
                                    </div>
                                    <span class="small fw-medium text-dark">QR Scanner</span>
                                </div>
                            </a>
                        </div>
                        <div class="col-6 col-md-4">
                            <a href="students.php" class="card h-100 text-decoration-none border-0 shadow-sm rounded-3">
                                <div class="card-body p-3 text-center">
                                    <div class="icon-bg rounded-circle bg-success bg-opacity-10 p-2 mx-auto mb-2" style="width: fit-content;">
                                        <i class="fas fa-users text-success"></i>
                                    </div>
                                    <span class="small fw-medium text-dark">My Students</span>
                                </div>
                            </a>
                        </div>
                        <div class="col-12 col-md-4">
                            <a href="reports.php" class="card h-100 text-decoration-none border-0 shadow-sm rounded-3">
                                <div class="card-body p-3 text-center">
                                    <div class="icon-bg rounded-circle bg-info bg-opacity-10 p-2 mx-auto mb-2" style="width: fit-content;">
                                        <i class="fas fa-chart-bar text-info"></i>
                                    </div>
                                    <span class="small fw-medium text-dark">Reports</span>
                                </div>
                            </a>
                        </div>
                    <?php elseif ($user_role == 'parent'): ?>
                        <div class="col-6">
                            <a href="attendance.php" class="card h-100 text-decoration-none border-0 shadow-sm rounded-3">
                                <div class="card-body p-3 text-center">
                                    <div class="icon-bg rounded-circle bg-primary bg-opacity-10 p-2 mx-auto mb-2" style="width: fit-content;">
                                        <i class="fas fa-calendar-check text-primary"></i>
                                    </div>
                                    <span class="small fw-medium text-dark">Children's Attendance</span>
                                </div>
                            </a>
                        </div>
                        <div class="col-6">
                            <a href="reports.php" class="card h-100 text-decoration-none border-0 shadow-sm rounded-3">
                                <div class="card-body p-3 text-center">
                                    <div class="icon-bg rounded-circle bg-success bg-opacity-10 p-2 mx-auto mb-2" style="width: fit-content;">
                                        <i class="fas fa-chart-bar text-success"></i>
                                    </div>
                                    <span class="small fw-medium text-dark">Reports</span>
                                </div>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
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
        minute: '2-digit'
    };
    document.getElementById('current-datetime').textContent = now.toLocaleString('en-US', options);
}

// Check for offline session
function checkOfflineSession() {
    <?php if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])): ?>
    try {
        const sessionData = localStorage.getItem('kes_smart_session');
        if (sessionData) {
            const userData = JSON.parse(sessionData);
            
            // Update the UI with user data
            document.querySelector('.text-muted.small.mb-0').textContent = 'Welcome back, ' + userData.full_name + '!';
            
            // Set user role
            const roleBadge = document.querySelector('.badge.bg-primary');
            if (roleBadge) {
                roleBadge.textContent = userData.role.charAt(0).toUpperCase() + userData.role.slice(1);
            }
            
            // Add offline indicator to the page
            const offlineAlert = document.createElement('div');
            offlineAlert.className = 'alert alert-warning mb-3';
            offlineAlert.innerHTML = '<i class="fas fa-wifi-slash me-2"></i> You are browsing in offline mode. Some features may be limited.';
            
            const mainContainer = document.querySelector('main.container');
            if (mainContainer && mainContainer.firstChild) {
                mainContainer.insertBefore(offlineAlert, mainContainer.firstChild);
            }
            
            console.log('Using offline session data');
            
            // If we're back online, try to sync the session
            if (navigator.onLine) {
                // Attempt to sync session with the server
                fetch('api/auth.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'verify',
                        username: userData.username,
                        role: userData.role
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Refresh the page to get a proper PHP session
                        window.location.reload();
                    }
                })
                .catch(error => {
                    console.error('Error syncing session:', error);
                });
            }
        } else {
            // No offline session, redirect to login
            window.location.href = 'login.php';
        }
    } catch (error) {
        console.error('Error checking offline session:', error);
        window.location.href = 'login.php';
    }
    <?php endif; ?>
}

// Animate dashboard elements
document.addEventListener('DOMContentLoaded', function() {
    // Check for offline session first
    checkOfflineSession();
    
    // Update date and time
    updateDateTime();
    setInterval(updateDateTime, 60000);
    
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

<?php if ($user_role == 'teacher'): ?>
// Teacher QR Code Generation Functions
document.addEventListener('DOMContentLoaded', function() {
    const qrForm = document.getElementById('attendance-qr-form');
    if (qrForm) {
        qrForm.addEventListener('submit', function(e) {
            e.preventDefault();
            generateTeacherQR();
        });
    }
});

function generateTeacherQR() {
    const sectionId = document.getElementById('qr-section').value;
    const subjectId = document.getElementById('qr-subject').value;
    
    if (!sectionId || !subjectId) {
        alert('Please select both section and subject');
        return;
    }
    
    // Generate teacher QR data: KES-SMART-TEACHER-{teacher_id}-{section_id}-{subject_id}
    const teacherId = <?php echo $current_user['id']; ?>;
    const qrData = btoa(`KES-SMART-TEACHER-${teacherId}-${sectionId}-${subjectId}`);
    
    const qrContainer = document.getElementById('teacher-qr-code');
    const qrActions = document.getElementById('qr-actions');
    const qrInfo = document.getElementById('qr-info');
    
    // Show loading
    qrContainer.innerHTML = '<div class="text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div><p class="mt-2 text-muted">Generating QR Code...</p></div>';
    
    // Generate QR code using API
    const qrSize = 200;
    const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=${qrSize}x${qrSize}&data=${encodeURIComponent(qrData)}&format=png&margin=10`;
    
    const img = document.createElement('img');
    img.src = qrUrl;
    img.alt = 'Teacher QR Code';
    img.style.border = '3px solid #007bff';
    img.style.borderRadius = '15px';
    img.style.boxShadow = '0 4px 6px rgba(0, 0, 0, 0.1)';
    img.style.maxWidth = '100%';
    img.style.height = 'auto';
    img.style.width = qrSize + 'px';
    img.dataset.qrData = qrData;
    
    img.onload = function() {
        qrContainer.innerHTML = '';
        qrContainer.appendChild(img);
        qrActions.classList.remove('d-none');
        qrInfo.classList.remove('d-none');
        
        // Store current QR info for actions
        window.currentTeacherQR = {
            data: qrData,
            sectionId: sectionId,
            subjectId: subjectId,
            sectionName: document.getElementById('qr-section').selectedOptions[0].text,
            subjectName: document.getElementById('qr-subject').selectedOptions[0].text
        };
        
        console.log('Teacher QR Code generated successfully');
    };
    
    img.onerror = function() {
        qrContainer.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> Failed to generate QR code. Please try again.</div>';
        console.error('Teacher QR Code generation failed');
    };
}

function clearQRCode() {
    const qrContainer = document.getElementById('teacher-qr-code');
    const qrActions = document.getElementById('qr-actions');
    const qrInfo = document.getElementById('qr-info');
    
    qrContainer.innerHTML = `
        <div class="text-muted">
            <i class="fas fa-qrcode fa-3x mb-2"></i>
            <p class="mb-0">Select section and subject to generate QR code</p>
        </div>
    `;
    qrActions.classList.add('d-none');
    qrInfo.classList.add('d-none');
    
    // Reset form
    document.getElementById('attendance-qr-form').reset();
    window.currentTeacherQR = null;
}

function downloadTeacherQR() {
    const img = document.querySelector('#teacher-qr-code img');
    
    if (img && window.currentTeacherQR) {
        const link = document.createElement('a');
        link.download = `teacher_qr_${window.currentTeacherQR.sectionName.replace(/\s+/g, '_')}_${window.currentTeacherQR.subjectName.replace(/\s+/g, '_')}.png`;
        link.href = img.src;
        link.click();
    } else {
        alert('No QR code to download. Please generate a QR code first.');
    }
}

function printTeacherQR() {
    const img = document.querySelector('#teacher-qr-code img');
    
    if (img && window.currentTeacherQR) {
        const printWindow = window.open('', '_blank');
        const qrElement = `<img src="${img.src}" style="border: 3px solid #000; border-radius: 15px; max-width: 300px;">`;
        
        const teacherInfo = `
            <div style="text-align: center; padding: 20px; font-family: Arial, sans-serif;">
                <h1 style="color: #007bff; margin-bottom: 10px;">KES-SMART</h1>
                <h2 style="margin-bottom: 20px;">Attendance QR Code</h2>
                <p style="margin-bottom: 5px;"><strong>Teacher:</strong> <?php echo $current_user['full_name']; ?></p>
                <p style="margin-bottom: 5px;"><strong>Section:</strong> ${window.currentTeacherQR.sectionName}</p>
                <p style="margin-bottom: 20px;"><strong>Subject:</strong> ${window.currentTeacherQR.subjectName}</p>
                <div style="margin: 20px 0;">
                    ${qrElement}
                </div>
                <p style="margin-top: 20px; font-size: 12px; color: #666;">
                    Students should scan this QR code to mark their attendance.<br>
                    Generated on ${new Date().toLocaleDateString()} at ${new Date().toLocaleTimeString()}
                </p>
            </div>
        `;
        
        printWindow.document.write(`
            <html>
            <head>
                <title>Attendance QR Code - ${window.currentTeacherQR.sectionName}</title>
                <style>
                    body { margin: 0; padding: 20px; }
                    @media print { body { padding: 0; } }
                </style>
            </head>
            <body>
                ${teacherInfo}
            </body>
            </html>
        `);
        printWindow.document.close();
        printWindow.print();
    } else {
        alert('No QR code to print. Please generate a QR code first.');
    }
}
<?php endif; ?>

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
    
    const idCardHtml = `
        <!DOCTYPE html>
        <html>
        <head>
            <title>Student ID Card - <?php echo htmlspecialchars($current_user['full_name']); ?></title>
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
                            <p class="info-value student-name"><?php echo strtoupper(htmlspecialchars($current_user['full_name'])); ?></p>
                        </div>
                        <div class="info-item">
                            <p class="info-label">STUDENT ID</p>
                            <p class="info-value"><?php echo htmlspecialchars($current_user['username']); ?></p>
                        </div>
                        <?php if ($current_user['lrn']): ?>
                        <div class="info-item">
                            <p class="info-label">LRN</p>
                            <p class="info-value"><?php echo htmlspecialchars($current_user['lrn']); ?></p>
                        </div>
                        <?php endif; ?>
                        <?php if (isset($section_info) && $section_info): ?>
                        <div class="info-item">
                            <p class="info-label">SECTION</p>
                            <p class="info-value"><?php echo htmlspecialchars($section_info['section_name']); ?></p>
                        </div>
                        <div class="info-item">
                            <p class="info-label">GRADE</p>
                            <p class="info-value"><?php echo htmlspecialchars($section_info['grade_level']); ?></p>
                        </div>
                        <?php endif; ?>
                        <div class="info-item">
                            <p class="info-label">SCHOOL YEAR</p>
                            <p class="info-value"><?php echo date('Y') . '-' . (date('Y') + 1); ?></p>
                        </div>
                    </div>
                    
                    <div class="qr-section">
                        ${qrHtml}
                        <p class="qr-label">SCAN FOR<br>ATTENDANCE</p>
                    </div>
                </div>
                
                <div class="card-footer-print">
                    <p class="footer-text">Valid Until: <?php echo date('M Y', strtotime('+1 year')); ?></p>
                    <p class="footer-text">Emergency: <?php echo $current_user['phone'] ?? 'N/A'; ?></p>
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
</script>

<!-- QR Scanner Modal -->
<div class="modal fade" id="qrScannerModal" tabindex="-1" aria-labelledby="qrScannerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="qrScannerModalLabel">
                    <i class="fas fa-qrcode me-2"></i>QR Code Scanner
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-8">
                        <div id="qr-scanner-container">
                            <div id="qr-scanner" style="width: 100%;"></div>
                            <div id="scanner-loading" class="text-center p-4">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Loading camera...</span>
                                </div>
                                <p class="mt-2 text-muted">Initializing camera...</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">Instructions</h6>
                            </div>
                            <div class="card-body">
                                <ol class="small">
                                    <li>Ask your teacher to show the attendance QR code</li>
                                    <li>Point your camera at the QR code</li>
                                    <li>Wait for automatic scanning</li>
                                    <li>Your attendance will be marked automatically</li>
                                </ol>
                                
                                <div class="alert alert-info small mt-3">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Make sure to scan the teacher's QR code, not another student's QR code
                                </div>
                            </div>
                        </div>
                        
                        <div id="scan-result" class="mt-3 d-none">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0">Scan Result</h6>
                                </div>
                                <div class="card-body" id="scan-result-content">
                                    <!-- Scan result will be displayed here -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="toggleCamera()" id="camera-toggle-btn">
                    <i class="fas fa-camera me-1"></i>Restart Camera
                </button>
            </div>
        </div>
    </div>
</div>

<!-- QR Scanner JavaScript -->
<?php if ($user_role == 'student'): ?>
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
let html5QrcodeScanner = null;
let isScanning = false;

document.addEventListener('DOMContentLoaded', function() {
    // Initialize scanner when modal is shown
    const qrModal = document.getElementById('qrScannerModal');
    qrModal.addEventListener('shown.bs.modal', function() {
        initializeScanner();
    });
    
    // Stop scanner when modal is hidden
    qrModal.addEventListener('hidden.bs.modal', function() {
        stopScanner();
    });
});

function initializeScanner() {
    if (html5QrcodeScanner) {
        stopScanner();
    }
    
    document.getElementById('scanner-loading').style.display = 'block';
    document.getElementById('qr-scanner').style.display = 'none';
    
    html5QrcodeScanner = new Html5QrcodeScanner(
        "qr-scanner", 
        { 
            fps: 10, 
            qrbox: {width: 250, height: 250},
            aspectRatio: 1.0
        },
        /* verbose= */ false
    );
    
    html5QrcodeScanner.render(onScanSuccess, onScanFailure);
    
    // Hide loading after a delay
    setTimeout(() => {
        document.getElementById('scanner-loading').style.display = 'none';
        document.getElementById('qr-scanner').style.display = 'block';
    }, 2000);
    
    isScanning = true;
}

function onScanSuccess(decodedText, decodedResult) {
    if (!isScanning) return;
    
    console.log(`QR Code scanned: ${decodedText}`);
    
    // Stop scanning to prevent multiple scans
    stopScanner();
    
    // Show scan result
    showScanResult('Processing...', 'info');
    
    // Validate QR code format
    try {
        const qrData = atob(decodedText);
        console.log('Decoded QR data:', qrData);
        
        if (qrData.startsWith('KES-SMART-TEACHER-')) {
            // This is a teacher QR code for attendance
            processAttendanceQR(decodedText);
        } else if (qrData.startsWith('KES-SMART-STUDENT-')) {
            showScanResult('This is a student QR code. Please scan your teacher\'s attendance QR code instead.', 'warning');
        } else {
            showScanResult('Invalid QR code. Please scan a KES-SMART attendance QR code.', 'danger');
        }
    } catch (error) {
        console.error('Error decoding QR code:', error);
        showScanResult('Invalid QR code format. Please scan a valid KES-SMART QR code.', 'danger');
    }
}

function onScanFailure(error) {
    // Ignore scan failures - they happen frequently during normal operation
    console.debug(`QR Code scan error: ${error}`);
}

function processAttendanceQR(teacherQRData) {
    const studentQRData = '<?php echo $qr_data; ?>';
    
    // Send both QR codes to the server for processing
    const requestData = {
        teacher_qr_data: teacherQRData,
        student_qr_data: studentQRData
    };
    
    fetch('api/process-attendance-qr.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(requestData)
    })
    .then(response => response.json())
    .then(data => {
        console.log('Attendance processing result:', data);
        
        if (data.success) {
            showScanResult(
                `<div class="text-success">
                    <i class="fas fa-check-circle me-2"></i>
                    <strong>Attendance Marked Successfully!</strong>
                </div>
                <div class="mt-2 small">
                    <strong>Subject:</strong> ${data.attendance_data.subject_name}<br>
                    <strong>Section:</strong> ${data.attendance_data.section_name}<br>
                    <strong>Time:</strong> ${data.attendance_data.time_in}<br>
                    <strong>Date:</strong> ${data.attendance_data.date}
                </div>`,
                'success'
            );
            
            // Auto-close modal after 3 seconds
            setTimeout(() => {
                const modal = bootstrap.Modal.getInstance(document.getElementById('qrScannerModal'));
                modal.hide();
                
                // Refresh the page to update attendance data
                location.reload();
            }, 3000);
            
        } else {
            showScanResult(
                `<div class="text-danger">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <strong>Error:</strong> ${data.message}
                </div>`,
                'danger'
            );
        }
    })
    .catch(error => {
        console.error('Error processing attendance:', error);
        showScanResult(
            `<div class="text-danger">
                <i class="fas fa-exclamation-circle me-2"></i>
                <strong>Error:</strong> Failed to process attendance. Please try again.
            </div>`,
            'danger'
        );
    });
}

function showScanResult(message, type) {
    const resultContainer = document.getElementById('scan-result');
    const resultContent = document.getElementById('scan-result-content');
    
    resultContent.innerHTML = `<div class="alert alert-${type} mb-0">${message}</div>`;
    resultContainer.classList.remove('d-none');
}

function stopScanner() {
    if (html5QrcodeScanner) {
        html5QrcodeScanner.clear().catch(error => {
            console.error("Failed to clear scanner:", error);
        });
        html5QrcodeScanner = null;
    }
    isScanning = false;
}

function toggleCamera() {
    if (isScanning) {
        stopScanner();
        document.getElementById('camera-toggle-btn').innerHTML = '<i class="fas fa-camera me-1"></i>Start Camera';
    } else {
        initializeScanner();
        document.getElementById('camera-toggle-btn').innerHTML = '<i class="fas fa-stop me-1"></i>Stop Camera';
    }
}
</script>
<?php endif; ?>

<?php include 'footer.php'; ?>
