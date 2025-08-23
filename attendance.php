<?php
require_once 'config.php';

// Check if user is logged in and has permission
requireRole(['admin', 'teacher', 'student', 'parent']);

$current_user = getCurrentUser($pdo);
$user_role = $_SESSION['role'];

// Get filter parameters
$student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : null;
$subject_id = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : null;
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01'); // First day of current month
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-t'); // Last day of current month
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

$page_title = 'Attendance Records';
include 'header.php';

// Build query based on user role and filters
try {
    $where_conditions = ['a.attendance_date BETWEEN ? AND ?'];
    $query_params = [$date_from, $date_to];
    
    // If a specific student ID was provided, use that
    if ($student_id) {
        $where_conditions[] = 'a.student_id = ?';
        $query_params[] = $student_id;
        
        // For non-admin/teacher roles, verify they can access this student's data
        if ($user_role == 'student' && $student_id != $current_user['id']) {
            $_SESSION['error'] = 'Access denied.';
            redirect('dashboard.php');
        } elseif ($user_role == 'parent') {
            // Check if this student is linked to the parent
            $check_stmt = $pdo->prepare("SELECT 1 FROM student_parents WHERE parent_id = ? AND student_id = ?");
            $check_stmt->execute([$current_user['id'], $student_id]);
            if (!$check_stmt->fetch()) {
                $_SESSION['error'] = 'Access denied.';
                redirect('dashboard.php');
            }
        }
        // Admin and teachers can view any student's attendance when student_id is specified
    } else {
        // If no specific student ID, use role-based filters
        if ($user_role == 'student') {
            $where_conditions[] = 'a.student_id = ?';
            $query_params[] = $current_user['id'];
        } elseif ($user_role == 'parent') {
            $where_conditions[] = 'a.student_id IN (SELECT student_id FROM student_parents WHERE parent_id = ?)';
            $query_params[] = $current_user['id'];
        } elseif ($user_role == 'teacher') {
            $where_conditions[] = 'subj.teacher_id = ?';
            $query_params[] = $current_user['id'];
        }
        // Admin can see all records when no student_id is specified
    }
    
    if ($subject_id) {
        $where_conditions[] = 'a.subject_id = ?';
        $query_params[] = $subject_id;
    }
    
    if ($status_filter) {
        $where_conditions[] = 'a.status = ?';
        $query_params[] = $status_filter;
    }
    
    $attendance_query = "
        SELECT a.*, 
               u.full_name as student_name, 
               u.username as student_username,
               u.lrn as student_lrn,
               subj.subject_name,
               subj.subject_code,
               t.full_name as teacher_name
        FROM attendance a
        JOIN users u ON a.student_id = u.id
        LEFT JOIN subjects subj ON a.subject_id = subj.id
        JOIN users t ON a.teacher_id = t.id
        WHERE " . implode(' AND ', $where_conditions) . "
        ORDER BY a.attendance_date DESC, a.created_at DESC
    ";
    
    $stmt = $pdo->prepare($attendance_query);
    $stmt->execute($query_params);
    $attendance_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get students for filter (based on user role)
    if ($user_role == 'admin') {
        $students = $pdo->query("SELECT id, full_name, username FROM users WHERE role = 'student' AND status = 'active' ORDER BY full_name")->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($user_role == 'teacher') {
        $students_stmt = $pdo->prepare("
            SELECT DISTINCT u.id, u.full_name, u.username 
            FROM users u 
            JOIN attendance a ON u.id = a.student_id 
            JOIN subjects subj ON a.subject_id = subj.id 
            WHERE u.role = 'student' AND u.status = 'active' AND subj.teacher_id = ? 
            ORDER BY u.full_name
        ");
        $students_stmt->execute([$current_user['id']]);
        $students = $students_stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($user_role == 'parent') {
        $students_stmt = $pdo->prepare("SELECT u.id, u.full_name, u.username FROM users u JOIN student_parents sp ON u.id = sp.student_id WHERE sp.parent_id = ? ORDER BY u.full_name");
        $students_stmt->execute([$current_user['id']]);
        $students = $students_stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $students = [];
    }
    
    // Get subjects for filter
    if ($user_role == 'admin') {
        $subjects = $pdo->query("SELECT id, subject_name, subject_code, grade_level FROM subjects WHERE status = 'active' ORDER BY subject_name")->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($user_role == 'teacher') {
        $subjects_stmt = $pdo->prepare("SELECT id, subject_name, subject_code, grade_level FROM subjects WHERE teacher_id = ? AND status = 'active' ORDER BY subject_name");
        $subjects_stmt->execute([$current_user['id']]);
        $subjects = $subjects_stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $subjects = [];
    }
    
    // Calculate summary statistics
    $total_records = count($attendance_records);
    $present_count = count(array_filter($attendance_records, function($r) { return $r['status'] == 'present'; }));
    $absent_count = count(array_filter($attendance_records, function($r) { return $r['status'] == 'absent'; }));
    $late_count = count(array_filter($attendance_records, function($r) { return $r['status'] == 'late'; }));
    $out_count = count(array_filter($attendance_records, function($r) { return $r['status'] == 'out'; }));
    
    // Calculate effective present count (including out status)
    $effective_present_count = $present_count + $out_count;
    
    // Group records by date for display
    $records_by_date = [];
    foreach ($attendance_records as $record) {
        $date = $record['attendance_date'];
        if (!isset($records_by_date[$date])) {
            $records_by_date[$date] = [];
        }
        $records_by_date[$date][] = $record;
    }
    // Sort by date (newest first)
    krsort($records_by_date);
    
} catch(PDOException $e) {
    $attendance_records = [];
    $students = [];
    $subjects = [];
    $total_records = $present_count = $absent_count = $late_count = $out_count = $effective_present_count = 0;
    $records_by_date = [];
}
?>

<!-- Header Section -->
<div class="row">
    <div class="col-12">
        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3 mb-4">
            <div>
                <h1 class="h3 fw-bold text-primary">
                    <i class="fas fa-calendar-check me-2"></i>Attendance Records
                </h1>
                <p class="text-muted mb-0">
                    <?php 
                    if ($user_role == 'student') echo 'Your attendance history';
                    elseif ($user_role == 'parent') echo 'Your children\'s attendance';
                    else echo 'Student attendance records';
                    ?>
                </p>
            </div>
            <div class="text-sm-end">
                <?php if ($user_role == 'admin' || $user_role == 'teacher'): ?>
                <a href="reports.php?type=attendance" class="btn btn-outline-primary w-100 w-sm-auto">
                    <i class="fas fa-download me-2"></i>Export
                </a>
                <?php endif; ?>
                <div class="small text-muted mt-1">
                    <?php echo date('M j', strtotime($date_from)); ?> - <?php echo date('M j, Y', strtotime($date_to)); ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">
            <i class="fas fa-filter me-2"></i>Filters
        </h5>
        <button class="btn btn-link btn-sm d-md-none p-0" type="button" data-bs-toggle="collapse" data-bs-target="#filterCollapse">
            <i class="fas fa-chevron-down"></i>
        </button>
    </div>
    <div class="card-body collapse d-md-block" id="filterCollapse">
        <form method="GET" action="">
            <div class="row g-3">
                <?php if (!empty($students) && ($user_role != 'student')): ?>
                    <div class="col-12 col-md-6 col-lg-4">
                        <label for="student_id" class="form-label">Student</label>
                        <select class="form-select select2" id="student_id" name="student_id">
                            <option value="">All Students</option>
                            <?php foreach ($students as $student): ?>
                                <option value="<?php echo $student['id']; ?>" <?php echo $student_id == $student['id'] ? 'selected' : ''; ?>>
                                    <?php echo $student['full_name']; ?> (<?php echo $student['username']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($subjects)): ?>
                    <div class="col-12 col-md-6 col-lg-4">
                        <label for="subject_id" class="form-label">Subject</label>
                        <select class="form-select select2" id="subject_id" name="subject_id">
                            <option value="">All Subjects</option>
                            <?php foreach ($subjects as $subject): ?>
                                <option value="<?php echo $subject['id']; ?>" <?php echo $subject_id == $subject['id'] ? 'selected' : ''; ?>>
                                    <?php echo $subject['subject_name']; ?> (<?php echo $subject['subject_code']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
                
                <div class="col-6 col-md-4 col-lg-2">
                    <label for="date_from" class="form-label">From Date</label>
                    <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo $date_from; ?>">
                </div>
                
                <div class="col-6 col-md-4 col-lg-2">
                    <label for="date_to" class="form-label">To Date</label>
                    <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo $date_to; ?>">
                </div>
                
                <div class="col-12 col-md-4 col-lg-2">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">All Status</option>
                        <option value="present" <?php echo $status_filter == 'present' ? 'selected' : ''; ?>>Present</option>
                        <option value="absent" <?php echo $status_filter == 'absent' ? 'selected' : ''; ?>>Absent</option>
                        <option value="late" <?php echo $status_filter == 'late' ? 'selected' : ''; ?>>Late</option>
                        <option value="out" <?php echo $status_filter == 'out' ? 'selected' : ''; ?>>Out</option>
                    </select>
                </div>
            </div>
            
            <div class="d-flex flex-column flex-sm-row gap-2 mt-3">
                <button type="submit" class="btn btn-primary w-100 w-sm-auto">
                    <i class="fas fa-search me-2"></i>Apply Filters
                </button>
                <a href="attendance.php" class="btn btn-outline-secondary w-100 w-sm-auto">
                    <i class="fas fa-times me-2"></i>Clear
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Summary Statistics -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card bg-primary text-white h-100">
            <div class="card-body text-center p-3">
                <i class="fas fa-list fa-2x mb-2"></i>
                <h4 class="fw-bold mb-0"><?php echo $total_records; ?></h4>
                <p class="small mb-0">Total Records</p>
            </div>
        </div>
    </div>
    
    <div class="col-6 col-lg-3">
        <div class="card bg-success text-white h-100">
            <div class="card-body text-center p-3">
                <i class="fas fa-check fa-2x mb-2"></i>
                <h4 class="fw-bold mb-0"><?php echo $effective_present_count; ?></h4>
                <p class="small mb-0">Present</p>
                <?php if ($out_count > 0): ?>
                <div class="mt-1 small">
                    <span class="badge bg-white text-success"><?php echo $present_count; ?> in</span>
                    <span class="badge bg-info text-white"><?php echo $out_count; ?> out</span>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-6 col-lg-3">
        <div class="card bg-danger text-white h-100">
            <div class="card-body text-center p-3">
                <i class="fas fa-times fa-2x mb-2"></i>
                <h4 class="fw-bold mb-0"><?php echo $absent_count; ?></h4>
                <p class="small mb-0">Absent</p>
            </div>
        </div>
    </div>
    
    <div class="col-6 col-lg-3">
        <div class="card bg-warning text-dark h-100">
            <div class="card-body text-center p-3">
                <i class="fas fa-clock fa-2x mb-2"></i>
                <h4 class="fw-bold mb-0"><?php echo $late_count; ?></h4>
                <p class="small mb-0">Late</p>
            </div>
        </div>
    </div>
</div>

<!-- Additional Statistics Row -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card bg-info text-white h-100">
            <div class="card-body text-center p-3">
                <i class="fas fa-sign-out-alt fa-2x mb-2"></i>
                <h4 class="fw-bold mb-0"><?php echo $out_count; ?></h4>
                <p class="small mb-0">Out</p>
            </div>
        </div>
    </div>
    
    <div class="col-6 col-lg-3">
        <div class="card bg-secondary text-white h-100">
            <div class="card-body text-center p-3">
                <i class="fas fa-percentage fa-2x mb-2"></i>
                <h4 class="fw-bold mb-0"><?php echo $total_records > 0 ? round(($effective_present_count / $total_records) * 100, 1) : 0; ?>%</h4>
                <p class="small mb-0">Attendance Rate</p>
            </div>
        </div>
    </div>
    
    <div class="col-6 col-lg-3">
        <div class="card bg-dark text-white h-100">
            <div class="card-body text-center p-3">
                <i class="fas fa-chart-line fa-2x mb-2"></i>
                <h4 class="fw-bold mb-0"><?php echo $total_records > 0 ? round((($effective_present_count + $late_count) / $total_records) * 100, 1) : 0; ?>%</h4>
                <p class="small mb-0">Participation Rate</p>
            </div>
        </div>
    </div>
    
    <div class="col-6 col-lg-3">
        <div class="card bg-light text-dark border h-100">
            <div class="card-body text-center p-3">
                <i class="fas fa-calendar-day fa-2x mb-2 text-muted"></i>
                <h4 class="fw-bold mb-0"><?php echo (new DateTime($date_to))->diff(new DateTime($date_from))->days + 1; ?></h4>
                <p class="small mb-0">Days Period</p>
            </div>
        </div>
    </div>
</div>

<!-- Attendance Records -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">
            <i class="fas fa-table me-2"></i>Attendance Records
            <span class="badge bg-secondary ms-2"><?php echo $total_records; ?></span>
        </h5>
        <div class="d-flex gap-2">
            <button class="btn btn-link btn-sm p-0 d-md-none" type="button" data-bs-toggle="collapse" data-bs-target="#recordsCollapse">
                <i class="fas fa-chevron-down"></i>
            </button>
        </div>
    </div>
    <div class="card-body p-0 p-sm-3 collapse d-md-block" id="recordsCollapse">
        <?php if (empty($attendance_records)): ?>
            <div class="text-center py-5">
                <i class="fas fa-calendar-times fa-4x text-muted mb-3"></i>
                <h4 class="text-muted mb-2">No Attendance Records Found</h4>
                <p class="text-muted mb-3">No attendance records match your current filters.</p>
                <a href="attendance.php" class="btn btn-outline-primary">
                    <i class="fas fa-refresh me-2"></i>Reset Filters
                </a>
            </div>
        <?php else: ?>
            <div class="attendance-timeline">
                <?php foreach ($records_by_date as $date => $day_records): ?>
                    <div class="date-group mb-4">
                        <div class="date-header mb-3">
                            <h6 class="fw-bold text-primary mb-1">
                                <i class="fas fa-calendar me-2"></i>
                                <?php echo date('l, F j, Y', strtotime($date)); ?>
                            </h6>
                            <small class="text-muted">
                                <?php echo count($day_records); ?> record(s)
                            </small>
                        </div>
                        
                        <div class="row g-3">
                            <?php foreach ($day_records as $record): ?>
                                <div class="col-12 col-sm-6 col-lg-4">
                                    <div class="card border-0 shadow-sm attendance-card h-100 <?php echo $record['status']; ?>">
                                        <div class="card-body p-3">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <div class="d-flex align-items-center">
                                                    <div class="profile-avatar me-2" style="width: 40px; height: 40px; background: linear-gradient(45deg, 
                                                        <?php 
                                                        echo $record['status'] == 'present' ? '#28a745, #20c997' : 
                                                             ($record['status'] == 'late' ? '#ffc107, #fd7e14' : 
                                                              ($record['status'] == 'absent' ? '#dc3545, #e83e8c' : 
                                                               ($record['status'] == 'out' ? '#17a2b8, #20c997' : '#6c757d, #adb5bd'))); 
                                                        ?>); display: flex; align-items: center; justify-content: center; border-radius: 50%; color: white; font-weight: bold; font-size: 0.9rem;">
                                                        <?php echo strtoupper(substr($record['student_name'], 0, 1)); ?>
                                                    </div>
                                                    <div class="overflow-hidden">
                                                        <h6 class="fw-bold mb-0 text-truncate"><?php echo $record['student_name']; ?></h6>
                                                        <small class="text-muted d-block text-truncate"><?php echo $record['student_username']; ?></small>
                                                        <?php if ($record['student_lrn']): ?>
                                                            <small class="text-muted d-block font-monospace text-truncate">LRN: <?php echo $record['student_lrn']; ?></small>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                
                                                <span class="badge bg-<?php 
                                                    echo $record['status'] == 'present' ? 'success' : 
                                                         ($record['status'] == 'late' ? 'warning' : 
                                                          ($record['status'] == 'absent' ? 'danger' : 
                                                           ($record['status'] == 'out' ? 'info' : 'secondary'))); 
                                                ?>">
                                                    <?php echo ucfirst($record['status']); ?>
                                                </span>
                                            </div>
                                            
                                            <div class="record-details small">
                                                <p class="mb-2">
                                                    <i class="fas fa-book me-2 text-primary"></i>
                                                    <span class="text-truncate d-inline-block" style="max-width: 200px;">
                                                        <?php echo $record['subject_name'] ? $record['subject_name'] . ' (' . $record['subject_code'] . ')' : 'No Subject'; ?>
                                                    </span>
                                                </p>
                                                
                                                <?php if ($record['time_in']): ?>
                                                    <p class="mb-2">
                                                        <i class="fas fa-clock me-2 text-info"></i>
                                                        Time In: <?php echo date('g:i A', strtotime($record['time_in'])); ?>
                                                    </p>
                                                <?php endif; ?>
                                                
                                                <?php if ($record['time_out']): ?>
                                                    <p class="mb-2">
                                                        <i class="fas fa-sign-out-alt me-2 text-warning"></i>
                                                        Time Out: <?php echo date('g:i A', strtotime($record['time_out'])); ?>
                                                    </p>
                                                <?php endif; ?>
                                                
                                                <?php if ($user_role == 'admin' || $user_role == 'teacher'): ?>
                                                    <p class="mb-2">
                                                        <i class="fas fa-user-tie me-2 text-secondary"></i>
                                                        <span class="text-truncate d-inline-block" style="max-width: 200px;">By: <?php echo $record['teacher_name']; ?></span>
                                                    </p>
                                                <?php endif; ?>
                                                
                                                <?php if ($record['qr_scanned']): ?>
                                                    <p class="mb-2 text-success">
                                                        <i class="fas fa-qrcode me-2"></i>
                                                        QR Code Scanned
                                                    </p>
                                                <?php endif; ?>
                                                
                                                <?php if ($record['remarks']): ?>
                                                    <p class="mb-0">
                                                        <i class="fas fa-comment me-2 text-muted"></i>
                                                        <span class="text-truncate d-inline-block" style="max-width: 200px;"><?php echo $record['remarks']; ?></span>
                                                    </p>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <div class="mt-3 pt-2 border-top text-center">
                                                <small class="text-muted">
                                                    Recorded: <?php echo date('g:i A', strtotime($record['created_at'])); ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="text-center mt-4">
                <small class="text-muted">
                    Showing <?php echo $total_records; ?> records from <?php echo date('M j', strtotime($date_from)); ?> to <?php echo date('M j, Y', strtotime($date_to)); ?>
                </small>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Student-specific attendance chart for single student view -->
<?php if ($student_id && count($attendance_records) > 0): ?>
    <div class="card mt-4">
        <div class="card-header">
            <h5 class="card-title mb-0">
                <i class="fas fa-chart-line me-2"></i>Attendance Trend
            </h5>
        </div>
        <div class="card-body">
            <canvas id="attendanceChart" height="100"></canvas>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Prepare chart data
        const attendanceData = <?php echo json_encode($attendance_records); ?>;
        const chartLabels = attendanceData.map(record => {
            const date = new Date(record.attendance_date);
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        }).reverse();
        
        const chartData = attendanceData.map(record => {
            switch(record.status) {
                case 'present': return 1;
                case 'late': return 0.7;
                case 'out': return 0.3;
                case 'absent': return 0;
                default: return 0;
            }
        }).reverse();
        
        // Create chart
        const ctx = document.getElementById('attendanceChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: chartLabels,
                datasets: [{
                    label: 'Attendance Score',
                    data: chartData,
                    borderColor: '#007bff',
                    backgroundColor: 'rgba(0, 123, 255, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 1,
                        ticks: {
                            callback: function(value) {
                                switch(value) {
                                    case 1: return 'Present';
                                    case 0.7: return 'Late';
                                    case 0.3: return 'Out';
                                    case 0: return 'Absent';
                                    default: return '';
                                }
                            }
                        }
                    },
                    x: {
                        ticks: {
                            maxTicksLimit: 10
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const record = attendanceData[attendanceData.length - 1 - context.dataIndex];
                                return `Status: ${record.status.charAt(0).toUpperCase() + record.status.slice(1)}`;
                            }
                        }
                    }
                }
            }
        });
    </script>
<?php endif; ?>

<script>
// Auto-refresh every 5 minutes if viewing today's data
<?php if ($date_from <= date('Y-m-d') && $date_to >= date('Y-m-d')): ?>
    setInterval(function() {
        if (document.visibilityState === 'visible') {
            location.reload();
        }
    }, 300000);
<?php endif; ?>

// Quick date range buttons
document.addEventListener('DOMContentLoaded', function() {
    // Add quick date range buttons
    const filterCard = document.querySelector('.card-body form');
    const quickRangeDiv = document.createElement('div');
    quickRangeDiv.className = 'mb-3';
    quickRangeDiv.innerHTML = `
        <label class="form-label">Quick Ranges</label><br>
        <div class="btn-group btn-group-sm" role="group">
            <button type="button" class="btn btn-outline-primary" onclick="setDateRange('today')">Today</button>
            <button type="button" class="btn btn-outline-primary" onclick="setDateRange('week')">This Week</button>
            <button type="button" class="btn btn-outline-primary" onclick="setDateRange('month')">This Month</button>
            <button type="button" class="btn btn-outline-primary" onclick="setDateRange('quarter')">This Quarter</button>
        </div>
    `;
    filterCard.insertBefore(quickRangeDiv, filterCard.lastElementChild);
});

function setDateRange(range) {
    const today = new Date();
    const dateFrom = document.getElementById('date_from');
    const dateTo = document.getElementById('date_to');
    
    switch(range) {
        case 'today':
            const todayStr = today.toISOString().split('T')[0];
            dateFrom.value = todayStr;
            dateTo.value = todayStr;
            break;
        case 'week':
            const weekStart = new Date(today.setDate(today.getDate() - today.getDay()));
            const weekEnd = new Date(today.setDate(today.getDate() - today.getDay() + 6));
            dateFrom.value = weekStart.toISOString().split('T')[0];
            dateTo.value = weekEnd.toISOString().split('T')[0];
            break;
        case 'month':
            const monthStart = new Date(today.getFullYear(), today.getMonth(), 1);
            const monthEnd = new Date(today.getFullYear(), today.getMonth() + 1, 0);
            dateFrom.value = monthStart.toISOString().split('T')[0];
            dateTo.value = monthEnd.toISOString().split('T')[0];
            break;
        case 'quarter':
            const quarter = Math.floor(today.getMonth() / 3);
            const quarterStart = new Date(today.getFullYear(), quarter * 3, 1);
            const quarterEnd = new Date(today.getFullYear(), quarter * 3 + 3, 0);
            dateFrom.value = quarterStart.toISOString().split('T')[0];
            dateTo.value = quarterEnd.toISOString().split('T')[0];
            break;
    }
}
</script>

<script>
// Initialize offline storage for attendance records
document.addEventListener('DOMContentLoaded', function() {
    // Check if we're in offline mode
    if (!navigator.onLine) {
        showOfflineMode();
        loadOfflineAttendanceRecords();
    }
    
    // Listen for online/offline events
    window.addEventListener('online', handleOnlineStatusChange);
    window.addEventListener('offline', handleOnlineStatusChange);
    
    // Initialize IndexedDB if available
    if (typeof initOfflineDB === 'function') {
        initOfflineDB().catch(error => console.error('Failed to initialize offline storage:', error));
    }
});

// Handle online/offline status changes
function handleOnlineStatusChange() {
    if (navigator.onLine) {
        // Back online
        hideOfflineMode();
        
        // Try to sync data
        if (typeof syncOfflineData === 'function') {
            syncOfflineData().then(() => {
                // Reload the page to get fresh data
                window.location.reload();
            }).catch(error => {
                console.error('Error syncing offline data:', error);
            });
        }
    } else {
        // Went offline
        showOfflineMode();
        loadOfflineAttendanceRecords();
    }
}

// Show offline mode UI
function showOfflineMode() {
    // Show offline indicator
    const offlineIndicator = document.getElementById('offline-mode-indicator');
    if (offlineIndicator) {
        offlineIndicator.classList.remove('d-none');
    } else {
        // Create and insert offline indicator
        const indicator = document.createElement('div');
        indicator.id = 'offline-mode-indicator';
        indicator.className = 'alert alert-warning mb-3';
        indicator.innerHTML = `
            <i class="fas fa-wifi-slash me-2"></i>
            <strong>You are offline.</strong> Viewing cached attendance records. Some features may be limited.
        `;
        
        const container = document.querySelector('main.container');
        if (container && container.firstChild) {
            container.insertBefore(indicator, container.firstChild);
        }
    }
    
    // Disable filter form elements
    const formElements = document.querySelectorAll('form select, form input, form button');
    formElements.forEach(el => {
        el.disabled = true;
    });
    
    // Add offline badge to filter section
    const filterCard = document.querySelector('.card-header');
    if (filterCard) {
        if (!filterCard.querySelector('.badge.bg-warning')) {
            const badge = document.createElement('span');
            badge.className = 'badge bg-warning text-dark ms-2';
            badge.textContent = 'Offline';
            filterCard.appendChild(badge);
        }
    }
}

// Hide offline mode UI
function hideOfflineMode() {
    // Hide offline indicator
    const offlineIndicator = document.getElementById('offline-mode-indicator');
    if (offlineIndicator) {
        offlineIndicator.classList.add('d-none');
    }
    
    // Enable filter form elements
    const formElements = document.querySelectorAll('form select, form input, form button');
    formElements.forEach(el => {
        el.disabled = false;
    });
    
    // Remove offline badge from filter section
    const filterBadge = document.querySelector('.card-header .badge.bg-warning');
    if (filterBadge) {
        filterBadge.remove();
    }
}

// Load attendance records from IndexedDB when offline
function loadOfflineAttendanceRecords() {
    // Check if we have the necessary functions
    if (typeof db === 'undefined' || !db) {
        console.warn('IndexedDB not initialized');
        showOfflineMessage('No offline attendance data available');
        return;
    }
    
    try {
        // Try to get attendance records from IndexedDB
        const transaction = db.transaction([STORE_NAMES.ATTENDANCE], 'readonly');
        const store = transaction.objectStore(STORE_NAMES.ATTENDANCE);
        const request = store.getAll();
        
        request.onsuccess = (event) => {
            const records = event.target.result;
            
            if (records && records.length > 0) {
                // Filter records based on user role
                const userRole = '<?php echo $user_role; ?>';
                const userId = <?php echo $current_user['id']; ?>;
                
                let filteredRecords = records;
                
                if (userRole === 'teacher') {
                    // Teachers should only see their own records
                    filteredRecords = records.filter(record => record.teacher_id === userId);
                } else if (userRole === 'student') {
                    // Students should only see their own records
                    filteredRecords = records.filter(record => record.student_id === userId);
                }
                
                if (filteredRecords.length > 0) {
                    displayOfflineAttendanceRecords(filteredRecords);
                } else {
                    showOfflineMessage('No offline attendance records found for your account');
                }
            } else {
                showOfflineMessage('No offline attendance records available');
            }
        };
        
        request.onerror = (event) => {
            console.error('Error loading offline attendance records:', event.target.error);
            showOfflineMessage('Error loading offline attendance records');
        };
    } catch (error) {
        console.error('Error accessing IndexedDB:', error);
        showOfflineMessage('Error accessing offline attendance data');
    }
}

// Display offline attendance records
function displayOfflineAttendanceRecords(records) {
    // Group records by date
    const recordsByDate = {};
    records.forEach(record => {
        const date = new Date(record.timestamp).toISOString().split('T')[0];
        if (!recordsByDate[date]) {
            recordsByDate[date] = [];
        }
        recordsByDate[date].push(record);
    });
    
    // Get the container for attendance records
    const attendanceContainer = document.querySelector('main.container');
    if (!attendanceContainer) {
        console.error('Attendance container not found');
        return;
    }
    
    // Remove existing records
    const existingRecords = document.querySelectorAll('.attendance-record-card');
    existingRecords.forEach(el => el.remove());
    
    // Sort dates (newest first)
    const sortedDates = Object.keys(recordsByDate).sort().reverse();
    
    // Create a container for the new records
    const recordsContainer = document.createElement('div');
    recordsContainer.className = 'offline-attendance-records';
    
    // Add a header for offline records
    const header = document.createElement('div');
    header.className = 'row mb-3';
    header.innerHTML = `
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <h4 class="text-warning">
                    <i class="fas fa-wifi-slash me-2"></i>Offline Attendance Records
                </h4>
                <span class="badge bg-warning text-dark">Cached Data</span>
            </div>
            <p class="text-muted small mb-0">
                These records will be synchronized when you're back online.
            </p>
        </div>
    `;
    recordsContainer.appendChild(header);
    
    // Add records for each date
    sortedDates.forEach(date => {
        const dateRecords = recordsByDate[date];
        
        // Create a card for this date
        const card = document.createElement('div');
        card.className = 'card mb-4 attendance-record-card';
        
        // Format the date
        const dateObj = new Date(date);
        const formattedDate = dateObj.toLocaleDateString('en-US', { 
            weekday: 'long', 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric' 
        });
        
        card.innerHTML = `
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="fas fa-calendar-day me-2"></i>${formattedDate}
                </h5>
                <span class="badge bg-warning text-dark">Offline</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Time</th>
                                <th>Type</th>
                                <th>Location</th>
                                <th>Status</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody id="offline-records-${date}">
                        </tbody>
                    </table>
                </div>
            </div>
        `;
        
        recordsContainer.appendChild(card);
        
        // Get the tbody element
        const tbody = document.getElementById(`offline-records-${date}`);
        
        // Sort records by timestamp (newest first)
        dateRecords.sort((a, b) => new Date(b.timestamp) - new Date(a.timestamp));
        
        // Add records to the table
        dateRecords.forEach(record => {
            const tr = document.createElement('tr');
            
            // Format the timestamp
            const timestamp = new Date(record.timestamp);
            const formattedTime = timestamp.toLocaleTimeString();
            
            tr.innerHTML = `
                <td>${formattedTime}</td>
                <td>${record.qr_data ? 'QR Scan' : (record.lrn ? 'LRN Entry' : 'Manual Entry')}</td>
                <td>${record.scan_location || 'N/A'}</td>
                <td><span class="badge bg-warning text-dark">Pending</span></td>
                <td>${record.scan_notes || '-'}</td>
            `;
            
            tbody.appendChild(tr);
        });
    });
    
    // Insert the records container after the filters
    const filterCard = document.querySelector('.card');
    if (filterCard && filterCard.nextSibling) {
        attendanceContainer.insertBefore(recordsContainer, filterCard.nextSibling);
    } else {
        attendanceContainer.appendChild(recordsContainer);
    }
}

// Show a message when no offline data is available
function showOfflineMessage(message) {
    // Get the container for attendance records
    const attendanceContainer = document.querySelector('main.container');
    if (!attendanceContainer) {
        console.error('Attendance container not found');
        return;
    }
    
    // Remove existing records
    const existingRecords = document.querySelectorAll('.attendance-record-card');
    existingRecords.forEach(el => el.remove());
    
    // Create a card for the message
    const card = document.createElement('div');
    card.className = 'card mb-4 attendance-record-card';
    card.innerHTML = `
        <div class="card-body text-center p-5">
            <i class="fas fa-wifi-slash fa-3x text-muted mb-3"></i>
            <h5>${message}</h5>
            <p class="text-muted">Attendance records will be available when you're back online.</p>
        </div>
    `;
    
    // Insert the card after the filters
    const filterCard = document.querySelector('.card');
    if (filterCard && filterCard.nextSibling) {
        attendanceContainer.insertBefore(card, filterCard.nextSibling);
    } else {
        attendanceContainer.appendChild(card);
    }
}
</script>

<style>
/* Mobile-friendly styles */
@media (max-width: 576px) {
    .card-body {
        padding: 0.75rem !important;
    }
    
    .nav-pills .nav-link {
        padding: 0.5rem;
        font-size: 0.875rem;
    }
    
    .form-control,
    .form-select {
        font-size: 0.875rem;
        padding: 0.375rem 0.75rem;
    }
    
    .form-text {
        font-size: 0.75rem;
    }
    
    .btn-sm {
        padding: 0.25rem 0.5rem;
        font-size: 0.75rem;
    }
    
    .table {
        font-size: 0.875rem;
    }
    
    .table td {
        padding: 0.5rem;
    }
    
    .badge {
        font-size: 0.75rem;
    }
    
    h6 {
        font-size: 0.875rem;
    }
    
    .small {
        font-size: 0.75rem;
    }
    
    .date-header {
        position: relative;
        top: auto;
        padding: 0.5rem 0;
    }
    
    .attendance-card {
        margin-bottom: 0;
    }
    
    .profile-avatar {
        width: 32px !important;
        height: 32px !important;
        font-size: 0.8rem !important;
    }
    
    .card-title {
        font-size: 1rem;
    }
    
    .h3 {
        font-size: 1.5rem;
    }
    
    .h4 {
        font-size: 1.25rem;
    }
}

/* Custom styles for attendance cards */
.attendance-card.present {
    border-left: 4px solid #28a745 !important;
}

.attendance-card.absent {
    border-left: 4px solid #dc3545 !important;
}

.attendance-card.late {
    border-left: 4px solid #ffc107 !important;
}

.attendance-card.out {
    border-left: 4px solid #17a2b8 !important;
}

.attendance-card {
    transition: transform 0.2s ease-in-out;
}

.attendance-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.1) !important;
}

.date-header {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    z-index: 10;
    border-radius: 8px;
    border-bottom: 2px solid #e9ecef;
}

/* Status-based text colors */
.text-status-present { color: #28a745 !important; }
.text-status-absent { color: #dc3545 !important; }
.text-status-late { color: #ffc107 !important; }
.text-status-out { color: #17a2b8 !important; }

/* Animation for statistics cards */
.card {
    transition: transform 0.2s ease-in-out;
}

.card:hover {
    transform: translateY(-1px);
}

/* Quick range buttons responsiveness */
.btn-group-sm .btn {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
}

@media (max-width: 576px) {
    .btn-group {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 0.5rem;
    }
    
    .btn-group .btn {
        border-radius: 0.25rem !important;
        margin: 0 !important;
    }
}

/* Chart responsiveness */
@media (max-width: 768px) {
    canvas {
        max-height: 200px !important;
    }
}
</style>

<?php include 'footer.php'; ?>
