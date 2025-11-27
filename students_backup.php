<?php
require_once 'config.php';

// Check if user is logged in and has permission
requireRole(['admin', 'teacher']);

$current_user = getCurrentUser($pdo);
$user_role = $_SESSION['role'];

// Handle different operations
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if ($action == 'add_student' && ($user_role == 'admin' || $user_role == 'teacher')) {
            $username = sanitize_input($_POST['username']);
            $full_name = sanitize_input($_POST['full_name']);
            $password = $_POST['password'] ?? '';
            $phone = sanitize_input($_POST['phone']);
            $lrn = sanitize_input($_POST['lrn']);
            $section_id = intval($_POST['section_id']);
            
            // Validate password
            if (empty($password)) {
                $_SESSION['error'] = 'Password is required.';
                redirect('students.php');
            }
            
            if (strlen($password) < 6) {
                $_SESSION['error'] = 'Password must be at least 6 characters long.';
                redirect('students.php');
            }
            
            try {
                // Check if username exists
                $check_stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                $check_stmt->execute([$username]);
                if ($check_stmt->fetch()) {
                    $_SESSION['error'] = 'Username already exists.';
                    redirect('students.php');
                }
                
                // Check if LRN exists (if provided)
                if (!empty($lrn)) {
                    $check_lrn_stmt = $pdo->prepare("SELECT id FROM users WHERE lrn = ?");
                    $check_lrn_stmt->execute([$lrn]);
                    if ($check_lrn_stmt->fetch()) {
                        $_SESSION['error'] = 'LRN already exists.';
                        redirect('students.php');
                    }
                    
                    // Validate LRN format (12 digits)
                    if (!preg_match('/^\d{12}$/', $lrn)) {
                        $_SESSION['error'] = 'LRN must be exactly 12 digits.';
                        redirect('students.php');
                    }
                }
                
                // Hash the password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Insert student first to get the ID
                $stmt = $pdo->prepare("INSERT INTO users (username, full_name, password, phone, lrn, role, section_id) VALUES (?, ?, ?, ?, ?, 'student', ?)");
                $stmt->execute([$username, $full_name, $hashed_password, $phone, $lrn ?: null, $section_id]);
                
                // Get the inserted student ID
                $student_id = $pdo->lastInsertId();
                
                // Generate QR code data using the student ID
                $qr_code = generateStudentQR($student_id);
                
                // Update the student record with the QR code
                $update_stmt = $pdo->prepare("UPDATE users SET qr_code = ? WHERE id = ?");
                $update_stmt->execute([$qr_code, $student_id]);
                
                $_SESSION['success'] = 'Student added successfully!';
            } catch(PDOException $e) {
                $_SESSION['error'] = 'Failed to add student: ' . $e->getMessage();
            }
            
        } elseif ($action == 'update_student' && ($user_role == 'admin' || $user_role == 'teacher')) {
            $student_id = intval($_POST['student_id']);
            $full_name = sanitize_input($_POST['full_name']);
            $password = $_POST['password'] ?? '';
            $phone = sanitize_input($_POST['phone']);
            $lrn = sanitize_input($_POST['lrn']);
            $section_id = intval($_POST['section_id']);
            
            try {
                // Check if LRN exists for other students (if provided)
                if (!empty($lrn)) {
                    $check_lrn_stmt = $pdo->prepare("SELECT id FROM users WHERE lrn = ? AND id != ?");
                    $check_lrn_stmt->execute([$lrn, $student_id]);
                    if ($check_lrn_stmt->fetch()) {
                        $_SESSION['error'] = 'LRN already exists for another student.';
                        redirect('students.php');
                    }
                    
                    // Validate LRN format (12 digits)
                    if (!preg_match('/^\d{12}$/', $lrn)) {
                        $_SESSION['error'] = 'LRN must be exactly 12 digits.';
                        redirect('students.php');
                    }
                }
                
                // Prepare update query
                if (!empty($password)) {
                    // Validate password if provided
                    if (strlen($password) < 6) {
                        $_SESSION['error'] = 'Password must be at least 6 characters long.';
                        redirect('students.php');
                    }
                    
                    // Hash the password and update
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET full_name = ?, password = ?, phone = ?, lrn = ?, section_id = ? WHERE id = ? AND role = 'student'");
                    $stmt->execute([$full_name, $hashed_password, $phone, $lrn ?: null, $section_id, $student_id]);
                } else {
                    // Update without changing password
                    $stmt = $pdo->prepare("UPDATE users SET full_name = ?, phone = ?, lrn = ?, section_id = ? WHERE id = ? AND role = 'student'");
                    $stmt->execute([$full_name, $phone, $lrn ?: null, $section_id, $student_id]);
                }
                
                // Handle subject enrollments
                if (isset($_POST['enrolled_subjects']) && is_array($_POST['enrolled_subjects'])) {
                    // First, remove all existing enrollments for this student
                    $delete_stmt = $pdo->prepare("DELETE FROM student_subjects WHERE student_id = ?");
                    $delete_stmt->execute([$student_id]);
                    
                    // Then add the new enrollments
                    $insert_stmt = $pdo->prepare("INSERT INTO student_subjects (student_id, subject_id, enrolled_date, status) VALUES (?, ?, CURDATE(), 'enrolled')");
                    foreach ($_POST['enrolled_subjects'] as $subject_id) {
                        $subject_id = intval($subject_id);
                        if ($subject_id > 0) {
                            $insert_stmt->execute([$student_id, $subject_id]);
                        }
                    }
                }
                
                $_SESSION['success'] = 'Student updated successfully!';
            } catch(PDOException $e) {
                $_SESSION['error'] = 'Failed to update student: ' . $e->getMessage();
            }
            
        }
    }
    
    redirect('students.php');
}

$page_title = 'Students';

// Add DataTables CSS and consistent styling
$additional_css = '
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
<style>
    .student-card .card {
        transition: all 0.3s ease;
        overflow: hidden;
        min-height: auto;
    }
    
    .student-card .card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important;
    }
    
    .student-card .card-header {
        position: relative;
        overflow: hidden;
    }
    
    .student-card .card-header::before {
        content: "";
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(255,255,255,0.1);
        transform: translateX(-100%);
        transition: transform 0.5s ease;
    }
    
    .student-card .card:hover .card-header::before {
        transform: translateX(100%);
    }
    
    .filter-btn.active {
        background-color: var(--primary-color);
        color: white;
        border-color: var(--primary-color);
    }
    
    .student-card {
        margin-bottom: 0.75rem;
    }
    
    .student-card .card-body {
        padding: 1rem !important;
    }
    
    .student-info {
        margin-top: 0.5rem !important;
    }
    
    .student-info .d-flex {
        margin-bottom: 0.5rem !important;
    }
    
    /* Mobile-specific styles */
    @media (max-width: 576px) {
        .btn-group {
            display: flex !important;
            width: 100%;
        }
        
        .btn-group .btn {
            flex: 1;
            font-size: 0.875rem;
            padding: 0.75rem 0.5rem;
            white-space: nowrap;
            min-height: 44px; /* Touch-friendly height */
        }
        
        .btn-group .btn i {
            font-size: 0.875rem;
        }
        
        /* Ensure header content stacks properly */
        .d-flex.flex-column.flex-md-row {
            gap: 1rem !important;
        }
        
        /* Make sure buttons do not overflow */
        .btn {
            min-width: 0;
            text-overflow: ellipsis;
            overflow: hidden;
        }
        
        /* Success button full width on mobile */
        .btn-primary {
            min-height: 44px;
            padding: 0.75rem 1rem;
        }
        
        /* Adjust card spacing for mobile */
        .card {
            margin-bottom: 1rem;
        }
        
        /* Responsive font sizes */
        .h3 {
            font-size: 1.5rem !important;
        }
        
        .text-muted {
            font-size: 0.875rem;
        }
        
        /* Improve touch targets */
        .dropdown-item {
            min-height: 44px;
            display: flex;
            align-items: center;
        }
    }
    
    @media (max-width: 768px) {
        .btn-group .btn {
            font-size: 0.9rem;
            padding: 0.6rem 1rem;
            min-height: 42px;
        }
        
        .btn-primary {
            min-height: 42px;
        }
    }
</style>
';

include 'header.php';

// Get students based on user role
try {
    if ($user_role == 'admin') {
        $students_query = "
            SELECT u.*, s.section_name, s.grade_level,
                   GROUP_CONCAT(CONCAT(p.full_name, ' (', sp.relationship, ')') SEPARATOR ', ') as parents,
                   COUNT(DISTINCT ss.id) as enrolled_subjects_count
            FROM users u 
            LEFT JOIN sections s ON u.section_id = s.id 
            LEFT JOIN student_parents sp ON u.id = sp.student_id
            LEFT JOIN users p ON sp.parent_id = p.id
            LEFT JOIN student_subjects ss ON u.id = ss.student_id AND ss.status = 'enrolled'
            WHERE u.role = 'student' AND u.status = 'active'
            GROUP BY u.id
            ORDER BY u.full_name
        ";
        $stmt = $pdo->query($students_query);
        
    } elseif ($user_role == 'teacher') {
        $students_query = "
            SELECT u.*, s.section_name, s.grade_level,
                   GROUP_CONCAT(CONCAT(p.full_name, ' (', sp.relationship, ')') SEPARATOR ', ') as parents,
                   COUNT(DISTINCT ss.id) as enrolled_subjects_count
            FROM users u 
            LEFT JOIN sections s ON u.section_id = s.id 
            LEFT JOIN student_parents sp ON u.id = sp.student_id
            LEFT JOIN users p ON sp.parent_id = p.id
            LEFT JOIN student_subjects ss ON u.id = ss.student_id AND ss.status = 'enrolled'
            WHERE u.role = 'student' AND u.status = 'active' 
            AND u.section_id IN (SELECT id FROM sections WHERE teacher_id = ?)
            GROUP BY u.id
            ORDER BY u.full_name
        ";
        $stmt = $pdo->prepare($students_query);
        $stmt->execute([$current_user['id']]);
        
    } else {
        // Students and parents can only see their own/children's records
        if ($user_role == 'student') {
            $students_query = "
                SELECT u.*, s.section_name, s.grade_level,
                       GROUP_CONCAT(CONCAT(p.full_name, ' (', sp.relationship, ')') SEPARATOR ', ') as parents,
                       COUNT(DISTINCT ss.id) as enrolled_subjects_count
                FROM users u 
                LEFT JOIN sections s ON u.section_id = s.id 
                LEFT JOIN student_parents sp ON u.id = sp.student_id
                LEFT JOIN users p ON sp.parent_id = p.id
                LEFT JOIN student_subjects ss ON u.id = ss.student_id AND ss.status = 'enrolled'
                WHERE u.id = ?
                GROUP BY u.id
            ";
            $stmt = $pdo->prepare($students_query);
            $stmt->execute([$current_user['id']]);
        } else { // parent
            $students_query = "
                SELECT u.*, s.section_name, s.grade_level,
                       GROUP_CONCAT(CONCAT(p.full_name, ' (', sp.relationship, ')') SEPARATOR ', ') as parents,
                       COUNT(DISTINCT ss.id) as enrolled_subjects_count
                FROM users u 
                LEFT JOIN sections s ON u.section_id = s.id 
                LEFT JOIN student_parents sp ON u.id = sp.student_id
                LEFT JOIN users p ON sp.parent_id = p.id
                LEFT JOIN student_subjects ss ON u.id = ss.student_id AND ss.status = 'enrolled'
                WHERE u.id IN (SELECT student_id FROM student_parents WHERE parent_id = ?)
                GROUP BY u.id
                ORDER BY u.full_name
            ";
            $stmt = $pdo->prepare($students_query);
            $stmt->execute([$current_user['id']]);
        }
    }
    
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get sections for dropdowns
    if ($user_role == 'admin') {
        $sections = $pdo->query("SELECT * FROM sections WHERE status = 'active' ORDER BY section_name")->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($user_role == 'teacher') {
        $sections_stmt = $pdo->prepare("SELECT * FROM sections WHERE teacher_id = ? AND status = 'active' ORDER BY section_name");
        $sections_stmt->execute([$current_user['id']]);
        $sections = $sections_stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $sections = [];
    }
    
    // Get subjects for enrollment (with section info for filtering)
    if ($user_role == 'admin' || $user_role == 'teacher') {
        if ($user_role == 'admin') {
            // Admins can see all subjects
            $subjects = $pdo->query("SELECT s.*, sec.section_name FROM subjects s LEFT JOIN sections sec ON s.section_id = sec.id WHERE s.status = 'active' ORDER BY s.subject_name")->fetchAll(PDO::FETCH_ASSOC);
        } else {
            // Teachers can only see subjects they own
            $stmt = $pdo->prepare("SELECT s.*, sec.section_name FROM subjects s LEFT JOIN sections sec ON s.section_id = sec.id WHERE s.status = 'active' AND s.teacher_id = ? ORDER BY s.subject_name");
            $stmt->execute([$_SESSION['user_id']]);
            $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } else {
        $subjects = [];
    }
    
} catch(PDOException $e) {
    $students = [];
    $sections = [];
    $subjects = [];
}
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
            <div class="flex-grow-1">
                <h1 class="h3 fw-bold text-primary mb-1">
                    <i class="fas fa-users me-2"></i>Students
                </h1>
                <p class="text-muted mb-0">
                    <?php 
                    if ($user_role == 'admin') echo 'Manage all students in the system';
                    elseif ($user_role == 'teacher') echo 'Manage students in your sections';
                    elseif ($user_role == 'student') echo 'View your information and records';
                    else echo 'View your children\'s information';
                    ?>
                </p>
            </div>
            <div class="d-flex flex-column flex-sm-row gap-2 w-100 w-md-auto">
                <?php if ($user_role == 'admin' || $user_role == 'teacher'): ?>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStudentModal">
                        <i class="fas fa-plus me-2"></i>Add Student
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Search and Filter -->
<div class="card mb-4 shadow-sm border-0">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-2">
        <h5 class="card-title mb-0">
            <i class="fas fa-filter me-2"></i>Search & Filter
        </h5>
        <button class="btn btn-link btn-sm p-0 text-muted" type="button" data-bs-toggle="collapse" data-bs-target="#filterCollapse">
            <i class="fas fa-chevron-down"></i>
        </button>
    </div>
    <div class="card-body collapse show" id="filterCollapse">
        <div class="row g-3">
            <div class="col-12 col-md-6">
                <div class="input-group">
                    <span class="input-group-text bg-light border-0"><i class="fas fa-search text-muted"></i></span>
                    <input type="text" id="student-search" class="form-control bg-light border-0" placeholder="Search students...">
                </div>
            </div>
            <div class="col-12 col-md-6">
                <select id="section-filter" class="form-select bg-light">
                    <option value="">All Sections</option>
                    <?php foreach ($sections as $section): ?>
                        <option value="<?php echo $section['id']; ?>"><?php echo $section['section_name']; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>
</div>

<!-- Students Cards -->
<div class="row g-3" id="students-container">
    <?php if (empty($students)): ?>
        <div class="col-12">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body text-center py-4">
                    <i class="fas fa-user-graduate fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted mb-2">No Students Found</h5>
                    <p class="text-muted small mb-3">
                        <?php if ($user_role == 'admin' || $user_role == 'teacher'): ?>
                            Start by adding students to the system.
                        <?php else: ?>
                            No student records available.
                        <?php endif; ?>
                    </p>
                    <?php if ($user_role == 'admin' || $user_role == 'teacher'): ?>
                        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addStudentModal">
                            <i class="fas fa-plus me-2"></i>Add First Student
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($students as $student): ?>
            <div class="col-12 col-sm-6 col-lg-4 student-card" 
                 data-name="<?php echo strtolower($student['full_name']); ?>" 
                 data-username="<?php echo strtolower($student['username']); ?>"
                 data-section="<?php echo $student['section_id']; ?>">
                <div class="card border-0 shadow-sm h-100 animate__animated animate__fadeIn">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div class="d-flex align-items-center w-100">
                                <div class="profile-avatar me-2 flex-shrink-0" style="width: 40px; height: 40px; background: linear-gradient(45deg, #007bff, #0056b3); display: flex; align-items: center; justify-content: center; border-radius: 50%; color: white; font-weight: bold; font-size: 0.9rem;">
                                    <?php echo strtoupper(substr($student['full_name'], 0, 1)); ?>
                                </div>
                                <div class="flex-grow-1 min-w-0 me-2">
                                    <h6 class="fw-bold mb-0 text-truncate" style="font-size: 0.95rem;"><?php echo $student['full_name']; ?></h6>
                                    <p class="text-muted small mb-0 text-truncate" style="font-size: 0.8rem;"><?php echo $student['username']; ?></p>
                                </div>
                                <div class="dropdown me-2">
                                    <button class="btn btn-sm btn-link text-muted p-2 rounded-circle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <ul class="dropdown-menu shadow-sm">
                                        <li><a class="dropdown-item d-flex align-items-center py-2" href="qr-code.php?student_id=<?php echo $student['id']; ?>">
                                            <i class="fas fa-qrcode me-2 text-primary"></i>View QR Code
                                        </a></li>
                                        <li><a class="dropdown-item d-flex align-items-center py-2" href="attendance.php?student_id=<?php echo $student['id']; ?>">
                                            <i class="fas fa-calendar-check me-2 text-success"></i>View Attendance
                                        </a></li>
                                        <?php if ($user_role == 'admin' || $user_role == 'teacher'): ?>
                                            <li><a class="dropdown-item d-flex align-items-center py-2" href="#" onclick="editStudent(<?php echo htmlspecialchars(json_encode($student)); ?>)">
                                                <i class="fas fa-edit me-2 text-info"></i>Edit
                                            </a></li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        
                        <div class="student-info small">
                            <?php if ($student['lrn']): ?>
                                <div class="d-flex align-items-center mb-2">
                                    <i class="fas fa-id-card me-2 text-warning flex-shrink-0"></i>
                                    <span class="fw-semibold me-1">LRN:</span> 
                                    <span class="font-monospace text-truncate"><?php echo $student['lrn']; ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($student['section_name']): ?>
                                <div class="d-flex align-items-center mb-2">
                                    <i class="fas fa-school me-2 text-primary flex-shrink-0"></i>
                                    <span class="fw-semibold text-truncate"><?php echo $student['section_name']; ?></span>
                                    <?php if ($student['grade_level']): ?>
                                        <span class="text-muted ms-1 text-truncate">- <?php echo $student['grade_level']; ?></span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($student['email']): ?>
                                <div class="d-flex align-items-center mb-2">
                                    <i class="fas fa-envelope me-2 text-info flex-shrink-0"></i>
                                    <span class="text-truncate"><?php echo $student['email']; ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($student['phone']): ?>
                                <div class="d-flex align-items-center mb-2">
                                    <i class="fas fa-phone me-2 text-success flex-shrink-0"></i>
                                    <span class="text-truncate"><?php echo $student['phone']; ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($student['parents']): ?>
                                <div class="d-flex align-items-start mb-2">
                                    <i class="fas fa-heart me-2 text-danger mt-1 flex-shrink-0"></i>
                                    <span class="text-wrap text-break small"><?php echo $student['parents']; ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Attendance Summary -->
                        <?php 
                        $attendance_summary = evaluateAttendance($pdo, $student['id']);
                        ?>
                        <div class="mt-2 pt-2 border-top">
                            <div class="row g-1 text-center">
                                <div class="col-4">
                                    <div class="p-1 rounded-3 bg-light">
                                        <div class="text-<?php echo $attendance_summary['evaluation'] == 'Excellent' ? 'success' : ($attendance_summary['evaluation'] == 'Good' ? 'info' : ($attendance_summary['evaluation'] == 'Fair' ? 'warning' : 'danger')); ?>">
                                            <small><strong><?php echo $attendance_summary['attendance_rate']; ?>%</strong></small>
                                        </div>
                                        <small class="text-muted d-block" style="font-size: 0.7rem;">Attendance</small>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="p-1 rounded-3 bg-light">
                                        <div class="text-primary">
                                            <small><strong><?php echo $attendance_summary['present_days']; ?>/<?php echo $attendance_summary['total_days']; ?></strong></small>
                                        </div>
                                        <small class="text-muted d-block" style="font-size: 0.7rem;">Present Days</small>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="p-1 rounded-3 bg-light">
                                        <div class="text-info">
                                            <small><strong><?php echo $student['enrolled_subjects_count'] ?? 0; ?></strong></small>
                                        </div>
                                        <small class="text-muted d-block" style="font-size: 0.7rem;">Subjects</small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mt-1">
                                <span class="badge bg-<?php echo $attendance_summary['evaluation'] == 'Excellent' ? 'success' : ($attendance_summary['evaluation'] == 'Good' ? 'info' : ($attendance_summary['evaluation'] == 'Fair' ? 'warning' : 'danger')); ?> w-100 py-1" style="font-size: 0.7rem;">
                                    <?php echo $attendance_summary['evaluation']; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php if ($user_role == 'admin' || $user_role == 'teacher'): ?>
    <!-- Add Student Modal -->
    <div class="modal fade" id="addStudentModal" tabindex="-1" aria-labelledby="addStudentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="addStudentModalLabel">
                        <i class="fas fa-user-plus me-2"></i>Add New Student
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_student">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="username" class="form-label">Username *</label>
                                    <input type="text" class="form-control" id="username" name="username" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="full_name" class="form-label">Full Name *</label>
                                    <input type="text" class="form-control" id="full_name" name="full_name" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="lrn" class="form-label">LRN (12 digits)</label>
                                    <input type="text" class="form-control" id="lrn" name="lrn" maxlength="12" placeholder="123456789012">
                                    <div class="form-text">Learner Reference Number (optional)</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="section_id" class="form-label">Section *</label>
                                    <select class="form-select" id="section_id" name="section_id" required>
                                        <option value="">Select Section</option>
                                        <?php foreach ($sections as $section): ?>
                                            <option value="<?php echo $section['id']; ?>">
                                                <?php echo $section['section_name'] . ' - ' . $section['grade_level']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email" name="email">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="phone" class="form-label">Phone</label>
                                    <input type="text" class="form-control" id="phone" name="phone">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>Cancel
                        </button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save me-2"></i>Add Student
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Student Modal -->
    <div class="modal fade" id="editStudentModal" tabindex="-1" aria-labelledby="editStudentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title" id="editStudentModalLabel">
                        <i class="fas fa-edit me-2"></i>Edit Student
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_student">
                        <input type="hidden" name="student_id" id="edit_student_id">
                        
                        <div class="mb-3">
                            <label for="edit_full_name" class="form-label">Full Name *</label>
                            <input type="text" class="form-control form-control-lg" id="edit_full_name" name="full_name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_lrn" class="form-label">LRN (Learner Reference Number)</label>
                            <input type="text" class="form-control form-control-lg" id="edit_lrn" name="lrn" 
                                   pattern="[0-9]{12}" maxlength="12" placeholder="123456789012"
                                   title="LRN must be exactly 12 digits">
                            <div class="form-text">Optional. Must be exactly 12 digits if provided.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_email" class="form-label">Email</label>
                            <input type="email" class="form-control form-control-lg" id="edit_email" name="email">
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_phone" class="form-label">Phone</label>
                            <input type="text" class="form-control form-control-lg" id="edit_phone" name="phone">
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_section_id" class="form-label">Section</label>
                            <select class="form-select form-select-lg select2" id="edit_section_id" name="section_id" onchange="updateSubjectsList()">
                                <option value="">Select Section</option>
                                <?php foreach ($sections as $section): ?>
                                    <option value="<?php echo $section['id']; ?>">
                                        <?php echo $section['section_name']; ?> - <?php echo $section['grade_level']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">
                                <i class="fas fa-book me-2"></i>Subject Enrollment
                            </label>
                            <div class="card border-light bg-light">
                                <div class="card-header bg-primary text-white py-2">
                                    <h6 class="mb-0">
                                        <i class="fas fa-graduation-cap me-2"></i>Select Subjects to Enroll
                                    </h6>
                                </div>
                                <div class="card-body p-3">
                                    <div class="row" id="edit_subjects_list">
                                        <div class="col-12">
                                            <div class="alert alert-info mb-0">
                                                <i class="fas fa-info-circle me-2"></i>
                                                Please select a section first to see available subjects.
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mt-2">
                                        <small class="text-muted">
                                            <i class="fas fa-info-circle me-1"></i>
                                            Select the subjects this student should be enrolled in from their assigned section. Teachers can only enroll students in subjects they teach.
                                        </small>
                                    </div>
                                    <div class="mt-2">
                                        <button type="button" class="btn btn-sm btn-outline-primary me-2" onclick="selectAllSubjects(true)">
                                            <i class="fas fa-check-square me-1"></i>Select All
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="selectAllSubjects(false)">
                                            <i class="fas fa-square me-1"></i>Clear All
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer d-flex justify-content-between">
                        <button type="button" class="btn btn-lg btn-secondary flex-fill me-2" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-lg btn-primary flex-fill">
                            <i class="fas fa-save me-2"></i>Update Student
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Add custom scripts to prevent jQuery loading twice -->
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>

<script>
// Search and filter functionality
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('student-search');
    const sectionFilter = document.getElementById('section-filter');
    const studentCards = document.querySelectorAll('.student-card');
    
    function filterStudents() {
        const searchTerm = searchInput.value.toLowerCase();
        const selectedSection = sectionFilter.value;
        
        let visibleCount = 0;
        
        studentCards.forEach(card => {
            const name = card.dataset.name;
            const username = card.dataset.username;
            const section = card.dataset.section;
            
            const matchesSearch = !searchTerm || name.includes(searchTerm) || username.includes(searchTerm);
            const matchesSection = !selectedSection || section === selectedSection;
            
            if (matchesSearch && matchesSection) {
                card.style.display = 'block';
                card.classList.add('fade-in');
                visibleCount++;
            } else {
                card.style.display = 'none';
                card.classList.remove('fade-in');
            }
        });
        
        // Update results count
        const countElement = document.querySelector('.small.text-muted');
        if (countElement) {
            countElement.textContent = `Showing: ${visibleCount} of ${studentCards.length}`;
        }
    }
    
    if (searchInput) {
        searchInput.addEventListener('input', filterStudents);
    }
    
    if (sectionFilter) {
        sectionFilter.addEventListener('change', filterStudents);
    }
    
    // Add animation for initial load
    studentCards.forEach((card, index) => {
        setTimeout(() => {
            card.classList.add('animate__animated', 'animate__fadeIn');
        }, index * 50);
    });
});

// Edit student function
function editStudent(student) {
    document.getElementById('edit_student_id').value = student.id;
    document.getElementById('edit_full_name').value = student.full_name;
    document.getElementById('edit_lrn').value = student.lrn || '';
    document.getElementById('edit_password').value = ''; // Always start empty
    document.getElementById('edit_phone').value = student.phone || '';
    document.getElementById('edit_section_id').value = student.section_id || '';
    
    // Update subjects list based on selected section
    updateSubjectsList();
    
    new bootstrap.Modal(document.getElementById('editStudentModal')).show();
}

// Load student's current subject enrollments
async function loadStudentSubjects(studentId) {
    try {
        const response = await fetch(`api/get-student-subjects.php?student_id=${studentId}`);
        const data = await response.json();
        
        if (data.success && data.subjects) {
            // Check the subjects the student is enrolled in
            data.subjects.forEach(function(subjectId) {
                const checkbox = document.getElementById('edit_subject_' + subjectId);
                if (checkbox) {
                    checkbox.checked = true;
                }
            });
        }
    } catch (error) {
        console.error('Error loading student subjects:', error);
    }
}

// Update subjects list based on selected section
async function updateSubjectsList() {
    const sectionId = document.getElementById('edit_section_id').value;
    const subjectsList = document.getElementById('edit_subjects_list');
    
    if (!sectionId) {
        // If no section selected, show message
        subjectsList.innerHTML = `
            <div class="col-12">
                <div class="alert alert-info mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    Please select a section first to see available subjects.
                </div>
            </div>
        `;
        return;
    }
    
    // Show loading spinner
    subjectsList.innerHTML = `
        <div class="col-12 text-center">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2 mb-0">Loading subjects for this section...</p>
        </div>
    `;
    
    try {
        const response = await fetch(`api/get-subjects-by-section.php?section_id=${sectionId}`);
        const data = await response.json();
        
        if (data.success && data.subjects) {
            if (data.subjects.length > 0) {
                let html = '';
                data.subjects.forEach(function(subject) {
                    html += `
                        <div class="col-12 col-md-6 mb-2">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" 
                                       value="${subject.id}" 
                                       id="edit_subject_${subject.id}"
                                       name="enrolled_subjects[]">
                                <label class="form-check-label" for="edit_subject_${subject.id}">
                                    <strong>${subject.subject_name}</strong>
                                    ${subject.subject_code ? `<span class="text-muted small">(${subject.subject_code})</span>` : ''}
                                    ${subject.grade_level ? `<br><span class="text-muted small">Grade ${subject.grade_level}</span>` : ''}
                                </label>
                            </div>
                        </div>
                    `;
                });
                subjectsList.innerHTML = html;
                
                // Show filter info if teacher is logged in
                if (data.filter_info && data.filter_info.teacher_filtered) {
                    subjectsList.innerHTML += `
                        <div class="col-12 mt-2">
                            <div class="alert alert-info mb-0 small">
                                <i class="fas fa-user-tie me-1"></i>
                                Showing only subjects you teach in this section.
                            </div>
                        </div>
                    `;
                }
                
                // Reload student's current enrollments if we have a student ID
                const studentId = document.getElementById('edit_student_id').value;
                if (studentId) {
                    loadStudentSubjects(studentId);
                }
            } else {
                let message = 'No subjects found for this section.';
                if (data.filter_info && data.filter_info.teacher_filtered) {
                    message = 'No subjects found that you teach in this section.';
                }
                
                subjectsList.innerHTML = `
                    <div class="col-12">
                        <div class="alert alert-warning mb-0">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            ${message}
                        </div>
                    </div>
                `;
            }
        } else {
            subjectsList.innerHTML = `
                <div class="col-12">
                    <div class="alert alert-danger mb-0">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        Error loading subjects: ${data.message || 'Unknown error'}
                    </div>
                </div>
            `;
        }
    } catch (error) {
        console.error('Error loading subjects:', error);
        subjectsList.innerHTML = `
            <div class="col-12">
                <div class="alert alert-danger mb-0">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    Error loading subjects. Please try again.
                </div>
            </div>
        `;
    }
}

// Select or deselect all subjects
function selectAllSubjects(selectAll) {
    const checkboxes = document.querySelectorAll('#edit_subjects_list input[type="checkbox"]');
    checkboxes.forEach(function(checkbox) {
        checkbox.checked = selectAll;
    });
}

// Auto-generate username from full name
document.getElementById('full_name')?.addEventListener('input', function() {
    const fullName = this.value.toLowerCase().replace(/\s+/g, '').replace(/[^a-z0-9]/g, '');
    const usernameField = document.getElementById('username');
    if (!usernameField.value) {
        usernameField.value = fullName;
    }
});

// LRN validation for add modal
document.getElementById('lrn')?.addEventListener('input', function() {
    this.value = this.value.replace(/\D/g, ''); // Only allow digits
    if (this.value.length > 12) {
        this.value = this.value.slice(0, 12);
    }
});

// LRN validation for edit modal
document.getElementById('edit_lrn')?.addEventListener('input', function() {
    this.value = this.value.replace(/\D/g, ''); // Only allow digits
    if (this.value.length > 12) {
        this.value = this.value.slice(0, 12);
    }
});

// Initialize Select2 when modals are shown
document.addEventListener('shown.bs.modal', function(event) {
    if (typeof jQuery !== 'undefined' && typeof jQuery.fn.select2 !== 'undefined') {
        $('.select2').select2({
            theme: 'bootstrap-5',
            width: '100%',
            dropdownParent: $(event.target)
        });
    }
});
</script>

<?php include 'footer.php'; ?>

<style>
/* Fix Select2 in modals */
.select2-container--bootstrap-5 {
    width: 100% !important;
}

/* Fix layout in modals for mobile */
@media (max-width: 576px) {
    .select2-container--bootstrap-5 .select2-selection {
        height: calc(1.5em + 0.75rem + 2px);
        font-size: 0.875rem;
    }
    
    .modal-body {
        padding: 1rem;
    }
    
    .modal-footer {
        padding: 0.75rem 1rem;
        flex-wrap: nowrap;
    }
    
    .modal-footer .btn {
        flex: 1;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    
    .form-text {
        font-size: 0.7rem;
    }
    
    .profile-avatar {
        width: 40px !important;
        height: 40px !important;
    }
    
    .btn-sm {
        padding: 0.25rem 0.5rem;
        font-size: 0.75rem;
    }
}
</style>
