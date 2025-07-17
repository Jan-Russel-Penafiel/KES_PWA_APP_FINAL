<?php
require_once 'config.php';

// Check if user is logged in and is student
if (!isLoggedIn()) {
    redirect('login.php');
}

if (!hasRole('student')) {
    $_SESSION['error'] = 'Access denied. Student privileges required.';
    redirect('dashboard.php');
}

$current_user = getCurrentUser($pdo);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if ($action == 'update_profile') {
            $full_name = sanitize_input($_POST['full_name']);
            $email = sanitize_input($_POST['email']);
            $phone = sanitize_input($_POST['phone']);
            
            try {
                $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, phone = ? WHERE id = ?");
                $stmt->execute([$full_name, $email, $phone, $current_user['id']]);
                
                $_SESSION['success'] = 'Profile updated successfully!';
            } catch(PDOException $e) {
                $_SESSION['error'] = 'Failed to update profile.';
            }
            
        } elseif ($action == 'update_preferences') {
            $theme = sanitize_input($_POST['theme']);
            $notifications = isset($_POST['notifications']) ? 1 : 0;
            $auto_qr_scan = isset($_POST['auto_qr_scan']) ? 1 : 0;
            $show_attendance_charts = isset($_POST['show_attendance_charts']) ? 1 : 0;
            
            // Store preferences in session
            $_SESSION['student_preferences'] = [
                'theme' => $theme,
                'notifications' => $notifications,
                'auto_qr_scan' => $auto_qr_scan,
                'show_attendance_charts' => $show_attendance_charts
            ];
            
            $_SESSION['success'] = 'Preferences updated successfully!';
        }
    }
    
    redirect('student-profile.php');
}

$page_title = 'My Student Profile';
include 'header.php';

// Get student statistics
try {
    // Attendance statistics
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE student_id = ? AND MONTH(attendance_date) = MONTH(CURDATE()) AND YEAR(attendance_date) = YEAR(CURDATE())");
    $stmt->execute([$current_user['id']]);
    $attendance_this_month = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE student_id = ?");
    $stmt->execute([$current_user['id']]);
    $total_attendance = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE student_id = ? AND status = 'present' AND MONTH(attendance_date) = MONTH(CURDATE())");
    $stmt->execute([$current_user['id']]);
    $present_this_month = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE student_id = ? AND status = 'late' AND MONTH(attendance_date) = MONTH(CURDATE())");
    $stmt->execute([$current_user['id']]);
    $late_this_month = $stmt->fetchColumn();
    
    // Section information
    $section_info = null;
    if ($current_user['section_id']) {
        $stmt = $pdo->prepare("SELECT s.*, u.full_name as teacher_name FROM sections s LEFT JOIN users u ON s.teacher_id = u.id WHERE s.id = ?");
        $stmt->execute([$current_user['section_id']]);
        $section_info = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Recent attendance
    $stmt = $pdo->prepare("SELECT * FROM attendance WHERE student_id = ? ORDER BY attendance_date DESC LIMIT 10");
    $stmt->execute([$current_user['id']]);
    $recent_attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // QR scan statistics
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM qr_scans WHERE student_id = ? AND DATE(scan_time) = CURDATE()");
    $stmt->execute([$current_user['id']]);
    $scans_today = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM qr_scans WHERE student_id = ?");
    $stmt->execute([$current_user['id']]);
    $total_scans = $stmt->fetchColumn();
    
} catch(PDOException $e) {
    $attendance_this_month = 0;
    $total_attendance = 0;
    $present_this_month = 0;
    $late_this_month = 0;
    $section_info = null;
    $recent_attendance = [];
    $scans_today = 0;
    $total_scans = 0;
}

// Calculate attendance percentage
$attendance_percentage = $attendance_this_month > 0 ? round(($present_this_month / $attendance_this_month) * 100, 1) : 0;

// Get student preferences
$preferences = $_SESSION['student_preferences'] ?? [
    'theme' => 'light',
    'notifications' => 1,
    'auto_qr_scan' => 1,
    'show_attendance_charts' => 1
];
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 fw-bold text-primary">
                    <i class="fas fa-user-graduate me-2"></i>My Student Profile
                </h1>
                <p class="text-muted mb-0">View your academic information and attendance records</p>
            </div>
            <div class="text-end">
                <a href="qr-code.php?student_id=<?php echo $current_user['id']; ?>" class="btn btn-primary">
                    <i class="fas fa-qrcode me-2"></i>My QR Code
                </a>
                <div class="small text-muted mt-1">Student ID: <?php echo $current_user['username']; ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Student Profile Header -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm" style="background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);">
            <div class="card-body text-white">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <div class="profile-avatar" style="width: 80px; height: 80px; background: rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center; border-radius: 50%; color: white; font-size: 2rem; border: 3px solid rgba(255,255,255,0.3);">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                    </div>
                    <div class="col">
                        <h3 class="fw-bold mb-1"><?php echo htmlspecialchars($current_user['full_name']); ?></h3>
                        <p class="mb-2 opacity-75">Student ID: <?php echo htmlspecialchars($current_user['username']); ?></p>
                        <?php if ($current_user['lrn']): ?>
                            <p class="mb-2 opacity-75">LRN: <span class="font-monospace"><?php echo htmlspecialchars($current_user['lrn']); ?></span></p>
                        <?php endif; ?>
                        <?php if ($section_info): ?>
                            <div class="d-flex flex-wrap gap-3">
                                <span class="badge bg-light text-dark">
                                    <i class="fas fa-school me-1"></i><?php echo htmlspecialchars($section_info['section_name']); ?>
                                </span>
                                <span class="badge bg-light text-dark">
                                    <i class="fas fa-layer-group me-1"></i><?php echo htmlspecialchars($section_info['grade_level']); ?>
                                </span>
                                <?php if ($section_info['teacher_name']): ?>
                                    <span class="badge bg-light text-dark">
                                        <i class="fas fa-chalkboard-teacher me-1"></i><?php echo htmlspecialchars($section_info['teacher_name']); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-6 col-lg-3 mb-3">
        <div class="card text-center border-0 shadow-sm">
            <div class="card-body">
                <div class="mb-2">
                    <i class="fas fa-calendar-check fa-2x text-success"></i>
                </div>
                <h4 class="fw-bold text-success"><?php echo $present_this_month; ?></h4>
                <p class="mb-0 small text-muted">Present This Month</p>
            </div>
        </div>
    </div>
    
    <div class="col-md-6 col-lg-3 mb-3">
        <div class="card text-center border-0 shadow-sm">
            <div class="card-body">
                <div class="mb-2">
                    <i class="fas fa-clock fa-2x text-warning"></i>
                </div>
                <h4 class="fw-bold text-warning"><?php echo $late_this_month; ?></h4>
                <p class="mb-0 small text-muted">Late This Month</p>
            </div>
        </div>
    </div>
    
    <div class="col-md-6 col-lg-3 mb-3">
        <div class="card text-center border-0 shadow-sm">
            <div class="card-body">
                <div class="mb-2">
                    <i class="fas fa-percentage fa-2x text-info"></i>
                </div>
                <h4 class="fw-bold text-info"><?php echo $attendance_percentage; ?>%</h4>
                <p class="mb-0 small text-muted">Attendance Rate</p>
            </div>
        </div>
    </div>
    
    <div class="col-md-6 col-lg-3 mb-3">
        <div class="card text-center border-0 shadow-sm">
            <div class="card-body">
                <div class="mb-2">
                    <i class="fas fa-qrcode fa-2x text-primary"></i>
                </div>
                <h4 class="fw-bold text-primary"><?php echo $scans_today; ?></h4>
                <p class="mb-0 small text-muted">QR Scans Today</p>
            </div>
        </div>
    </div>
</div>

<!-- Profile Tabs -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <ul class="nav nav-tabs card-header-tabs" id="studentProfileTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="overview-tab" data-bs-toggle="tab" data-bs-target="#overview" type="button" role="tab">
                            <i class="fas fa-chart-line me-2"></i>Overview
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="attendance-tab" data-bs-toggle="tab" data-bs-target="#attendance" type="button" role="tab">
                            <i class="fas fa-calendar-alt me-2"></i>Attendance
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="info-tab" data-bs-toggle="tab" data-bs-target="#info" type="button" role="tab">
                            <i class="fas fa-user me-2"></i>Personal Info
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="settings-tab" data-bs-toggle="tab" data-bs-target="#settings" type="button" role="tab">
                            <i class="fas fa-cog me-2"></i>Settings
                        </button>
                    </li>
                </ul>
            </div>
            <div class="card-body">
                <div class="tab-content" id="studentProfileTabsContent">
                    <!-- Overview Tab -->
                    <div class="tab-pane fade show active" id="overview" role="tabpanel">
                        <div class="row">
                            <!-- Attendance Chart -->
                            <div class="col-lg-8 mb-4">
                                <div class="card h-100">
                                    <div class="card-header">
                                        <h6 class="card-title mb-0">
                                            <i class="fas fa-chart-bar me-2"></i>Monthly Attendance Overview
                                        </h6>
                                    </div>
                                    <div class="card-body">
                                        <canvas id="attendanceChart" height="300"></canvas>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Quick Stats -->
                            <div class="col-lg-4 mb-4">
                                <div class="card h-100">
                                    <div class="card-header">
                                        <h6 class="card-title mb-0">
                                            <i class="fas fa-tachometer-alt me-2"></i>Quick Stats
                                        </h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="row g-3">
                                            <div class="col-6">
                                                <div class="text-center p-3 bg-light rounded">
                                                    <h5 class="fw-bold text-primary mb-1"><?php echo $total_attendance; ?></h5>
                                                    <small class="text-muted">Total Days</small>
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <div class="text-center p-3 bg-light rounded">
                                                    <h5 class="fw-bold text-success mb-1"><?php echo $total_scans; ?></h5>
                                                    <small class="text-muted">QR Scans</small>
                                                </div>
                                            </div>
                                            <div class="col-12">
                                                <div class="text-center p-3 bg-light rounded">
                                                    <h5 class="fw-bold text-info mb-1"><?php echo date('M j, Y'); ?></h5>
                                                    <small class="text-muted">Today's Date</small>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <hr>
                                        
                                        <div class="text-center">
                                            <h6 class="mb-3">Quick Actions</h6>
                                            <div class="d-grid gap-2">
                                                <a href="qr-code.php?student_id=<?php echo $current_user['id']; ?>" class="btn btn-primary btn-sm">
                                                    <i class="fas fa-qrcode me-2"></i>View My QR Code
                                                </a>
                                                <a href="attendance.php?student_id=<?php echo $current_user['id']; ?>" class="btn btn-outline-primary btn-sm">
                                                    <i class="fas fa-history me-2"></i>Full Attendance History
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Recent Activity -->
                        <div class="card">
                            <div class="card-header">
                                <h6 class="card-title mb-0">
                                    <i class="fas fa-clock me-2"></i>Recent Activity
                                </h6>
                            </div>
                            <div class="card-body">
                                <?php if (empty($recent_attendance)): ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                                        <h6 class="text-muted">No Recent Activity</h6>
                                        <p class="text-muted mb-0">Your attendance records will appear here.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="timeline">
                                        <?php foreach (array_slice($recent_attendance, 0, 5) as $record): ?>
                                            <div class="timeline-item d-flex align-items-start mb-3">
                                                <div class="timeline-marker me-3">
                                                    <div class="bg-<?php echo $record['status'] == 'present' ? 'success' : ($record['status'] == 'late' ? 'warning' : 'danger'); ?> rounded-circle d-flex align-items-center justify-content-center" style="width: 30px; height: 30px;">
                                                        <i class="fas fa-<?php echo $record['status'] == 'present' ? 'check' : ($record['status'] == 'late' ? 'clock' : 'times'); ?> text-white small"></i>
                                                    </div>
                                                </div>
                                                <div class="timeline-content flex-grow-1">
                                                    <h6 class="mb-1">
                                                        <?php echo ucfirst($record['status']); ?> - <?php echo date('M j, Y', strtotime($record['attendance_date'])); ?>
                                                    </h6>
                                                    <p class="text-muted mb-1 small">
                                                        <?php if ($record['time_in']): ?>
                                                            Time In: <?php echo date('g:i A', strtotime($record['time_in'])); ?>
                                                        <?php endif; ?>
                                                        <?php if ($record['time_out']): ?>
                                                            | Time Out: <?php echo date('g:i A', strtotime($record['time_out'])); ?>
                                                        <?php endif; ?>
                                                    </p>
                                                    <?php if ($record['remarks']): ?>
                                                        <p class="text-muted mb-0 small">
                                                            <i class="fas fa-comment me-1"></i><?php echo htmlspecialchars($record['remarks']); ?>
                                                        </p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    
                                    <div class="text-center mt-3">
                                        <a href="attendance.php" class="btn btn-outline-primary btn-sm">
                                            <i class="fas fa-history me-2"></i>View All Attendance Records
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Attendance Tab -->
                    <div class="tab-pane fade" id="attendance" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="mb-0">Attendance Records</h6>
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-secondary active" data-period="month">This Month</button>
                                <button class="btn btn-outline-secondary" data-period="week">This Week</button>
                                <button class="btn btn-outline-secondary" data-period="all">All Time</button>
                            </div>
                        </div>
                        
                        <?php if (empty($recent_attendance)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-calendar-alt fa-4x text-muted mb-3"></i>
                                <h5 class="text-muted">No Attendance Records</h5>
                                <p class="text-muted">Your attendance records will appear here once you start attending classes.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Status</th>
                                            <th>Time In</th>
                                            <th>Time Out</th>
                                            <th>Remarks</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_attendance as $record): ?>
                                            <tr>
                                                <td><?php echo date('M j, Y', strtotime($record['attendance_date'])); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $record['status'] == 'present' ? 'success' : ($record['status'] == 'late' ? 'warning' : 'danger'); ?>">
                                                        <?php echo ucfirst($record['status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo $record['time_in'] ? date('g:i A', strtotime($record['time_in'])) : '-'; ?></td>
                                                <td><?php echo $record['time_out'] ? date('g:i A', strtotime($record['time_out'])) : '-'; ?></td>
                                                <td><?php echo htmlspecialchars($record['remarks'] ?: '-'); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Personal Information Tab -->
                    <div class="tab-pane fade" id="info" role="tabpanel">
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="update_profile">
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="username" class="form-label">Student ID</label>
                                    <input type="text" class="form-control" id="username" value="<?php echo htmlspecialchars($current_user['username']); ?>" readonly>
                                    <div class="form-text">Your student ID cannot be changed</div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="lrn" class="form-label">LRN (Learner Reference Number)</label>
                                    <input type="text" class="form-control font-monospace" id="lrn" 
                                           value="<?php echo htmlspecialchars($current_user['lrn'] ?? 'Not Set'); ?>" readonly>
                                    <div class="form-text">Contact your teacher to update your LRN</div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="full_name" class="form-label">Full Name *</label>
                                    <input type="text" class="form-control" id="full_name" name="full_name" 
                                           value="<?php echo htmlspecialchars($current_user['full_name']); ?>" required>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">Email Address</label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo htmlspecialchars($current_user['email'] ?? ''); ?>">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="phone" class="form-label">Phone Number</label>
                                    <input type="text" class="form-control" id="phone" name="phone" 
                                           value="<?php echo htmlspecialchars($current_user['phone'] ?? ''); ?>"
                                           placeholder="+639123456789">
                                </div>
                                
                                <?php if ($section_info): ?>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Section</label>
                                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($section_info['section_name']); ?>" readonly>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Grade Level</label>
                                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($section_info['grade_level']); ?>" readonly>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Account Status</label>
                                    <input type="text" class="form-control" value="<?php echo ucfirst($current_user['status']); ?>" readonly>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Member Since</label>
                                    <input type="text" class="form-control" value="<?php echo date('M j, Y', strtotime($current_user['created_at'])); ?>" readonly>
                                </div>
                            </div>
                            
                            <div class="text-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Update Information
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Settings Tab -->
                    <div class="tab-pane fade" id="settings" role="tabpanel">
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="update_preferences">
                            
                            <h6 class="mb-3">Display Preferences</h6>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="theme" class="form-label">Theme</label>
                                    <select class="form-select" id="theme" name="theme">
                                        <option value="light" <?php echo $preferences['theme'] == 'light' ? 'selected' : ''; ?>>Light Theme</option>
                                        <option value="dark" <?php echo $preferences['theme'] == 'dark' ? 'selected' : ''; ?>>Dark Theme</option>
                                        <option value="auto" <?php echo $preferences['theme'] == 'auto' ? 'selected' : ''; ?>>Auto (System)</option>
                                    </select>
                                </div>
                            </div>
                            
                            <hr>
                            
                            <h6 class="mb-3">Feature Preferences</h6>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="notifications" name="notifications" 
                                               <?php echo $preferences['notifications'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="notifications">
                                            <i class="fas fa-bell me-2"></i>Enable Notifications
                                        </label>
                                        <div class="form-text">Receive notifications about attendance and updates</div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="auto_qr_scan" name="auto_qr_scan" 
                                               <?php echo $preferences['auto_qr_scan'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="auto_qr_scan">
                                            <i class="fas fa-qrcode me-2"></i>Auto QR Scan
                                        </label>
                                        <div class="form-text">Automatically start QR scanner when available</div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="show_attendance_charts" name="show_attendance_charts" 
                                               <?php echo $preferences['show_attendance_charts'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="show_attendance_charts">
                                            <i class="fas fa-chart-bar me-2"></i>Show Attendance Charts
                                        </label>
                                        <div class="form-text">Display graphical attendance statistics</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="text-end">
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-save me-2"></i>Save Preferences
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Attendance Chart
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('attendanceChart');
    if (ctx) {
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Present', 'Late', 'Absent'],
                datasets: [{
                    label: 'This Month',
                    data: [<?php echo $present_this_month; ?>, <?php echo $late_this_month; ?>, <?php echo ($attendance_this_month - $present_this_month - $late_this_month); ?>],
                    backgroundColor: [
                        'rgba(40, 167, 69, 0.8)',
                        'rgba(255, 193, 7, 0.8)',
                        'rgba(220, 53, 69, 0.8)'
                    ],
                    borderColor: [
                        'rgba(40, 167, 69, 1)',
                        'rgba(255, 193, 7, 1)',
                        'rgba(220, 53, 69, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
    }
});

// Period filter for attendance
document.querySelectorAll('[data-period]').forEach(button => {
    button.addEventListener('click', function() {
        document.querySelectorAll('[data-period]').forEach(btn => btn.classList.remove('active'));
        this.classList.add('active');
        
        // Here you would implement AJAX to load different periods
        const period = this.dataset.period;
        console.log('Loading attendance for period:', period);
    });
});

// Theme switching
document.getElementById('theme')?.addEventListener('change', function() {
    const theme = this.value;
    const body = document.body;
    
    body.classList.remove('theme-light', 'theme-dark');
    
    if (theme === 'dark') {
        body.classList.add('theme-dark');
    } else if (theme === 'light') {
        body.classList.add('theme-light');
    } else {
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            body.classList.add('theme-dark');
        } else {
            body.classList.add('theme-light');
        }
    }
});

// Tab URL handling
document.addEventListener('DOMContentLoaded', function() {
    const hash = window.location.hash;
    if (hash) {
        const tabElement = document.querySelector(`[data-bs-target="${hash}"]`);
        if (tabElement) {
            new bootstrap.Tab(tabElement).show();
        }
    }
    
    document.querySelectorAll('[data-bs-toggle="tab"]').forEach(tab => {
        tab.addEventListener('shown.bs.tab', function(e) {
            const target = e.target.getAttribute('data-bs-target');
            history.replaceState(null, null, window.location.pathname + target);
        });
    });
});
</script>

<style>
.timeline-item {
    position: relative;
}

.timeline-item:not(:last-child)::after {
    content: '';
    position: absolute;
    left: 14px;
    top: 30px;
    bottom: -15px;
    width: 2px;
    background: #dee2e6;
}

.profile-avatar {
    transition: transform 0.3s ease;
}

.profile-avatar:hover {
    transform: scale(1.05);
}

.theme-dark {
    background-color: #1a1a1a;
    color: #ffffff;
}

.theme-dark .card {
    background-color: #2d2d2d;
    border-color: #404040;
}

.theme-dark .form-control,
.theme-dark .form-select {
    background-color: #2d2d2d;
    border-color: #404040;
    color: #ffffff;
}

.theme-dark .bg-light {
    background-color: #2d2d2d !important;
}
</style>

<?php include 'footer.php'; ?>
