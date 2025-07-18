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

    <!-- QR Code Section -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-lg-6 mx-auto">
            <div class="card shadow-sm rounded-3">
                <div class="card-header bg-transparent py-3 text-center">
                    <h5 class="card-title h6 fw-bold mb-0">
                        <i class="fas fa-qrcode me-2"></i>My QR Code
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

<?php if ($user_role == 'student'): ?>
// Generate QR Code for student
function generateStudentQR() {
    const qrData = '<?php echo $qr_data; ?>';
    const qrContainer = document.getElementById('qr-code');
    
    if (!qrContainer) return;
    
    console.log('QR Data:', qrData);
    
    // Use QR Server API for reliable QR generation
    const qrSize = 250;
    const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=${qrSize}x${qrSize}&data=${encodeURIComponent(qrData)}&format=png&margin=10`;
    
    const img = document.createElement('img');
    img.src = qrUrl;
    img.alt = 'My QR Code';
    img.style.border = '3px solid #007bff';
    img.style.borderRadius = '15px';
    img.style.boxShadow = '0 4px 6px rgba(0, 0, 0, 0.1)';
    img.style.maxWidth = '100%';
    img.style.height = 'auto';
    img.style.width = '250px';
    
    img.onload = function() {
        qrContainer.innerHTML = '';
        qrContainer.appendChild(img);
        console.log('QR Code generated successfully');
    };
    
    img.onerror = function() {
        console.error('QR Code generation failed');
        qrContainer.innerHTML = `
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                <strong>QR Code Data:</strong><br>
                <div class="mt-2 p-2 bg-white rounded border">
                    <code style="word-break: break-all;">${qrData}</code>
                </div>
                <small class="d-block mt-2">Use a QR code scanner app to scan this data manually.</small>
            </div>
        `;
    };
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

<?php include 'footer.php'; ?>
