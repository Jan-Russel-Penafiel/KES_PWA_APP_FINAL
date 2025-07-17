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
            $email = sanitize_input($_POST['email']);
            $phone = sanitize_input($_POST['phone']);
            $lrn = sanitize_input($_POST['lrn']);
            $section_id = intval($_POST['section_id']);
            
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
                
                // Generate QR code data
                $qr_code = generateStudentQR($username);
                
                $stmt = $pdo->prepare("INSERT INTO users (username, full_name, email, phone, lrn, role, section_id, qr_code) VALUES (?, ?, ?, ?, ?, 'student', ?, ?)");
                $stmt->execute([$username, $full_name, $email, $phone, $lrn ?: null, $section_id, $qr_code]);
                
                $_SESSION['success'] = 'Student added successfully!';
            } catch(PDOException $e) {
                $_SESSION['error'] = 'Failed to add student: ' . $e->getMessage();
            }
            
        } elseif ($action == 'update_student' && ($user_role == 'admin' || $user_role == 'teacher')) {
            $student_id = intval($_POST['student_id']);
            $full_name = sanitize_input($_POST['full_name']);
            $email = sanitize_input($_POST['email']);
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
                
                $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, phone = ?, lrn = ?, section_id = ? WHERE id = ? AND role = 'student'");
                $stmt->execute([$full_name, $email, $phone, $lrn ?: null, $section_id, $student_id]);
                
                $_SESSION['success'] = 'Student updated successfully!';
            } catch(PDOException $e) {
                $_SESSION['error'] = 'Failed to update student.';
            }
            
        } elseif ($action == 'link_parent' && ($user_role == 'admin' || $user_role == 'teacher')) {
            $student_id = intval($_POST['student_id']);
            $parent_id = intval($_POST['parent_id']);
            $relationship = sanitize_input($_POST['relationship']);
            $is_primary = isset($_POST['is_primary']) ? 1 : 0;
            
            try {
                // If this is set as primary, remove primary status from other parents
                if ($is_primary) {
                    $stmt = $pdo->prepare("UPDATE student_parents SET is_primary = 0 WHERE student_id = ?");
                    $stmt->execute([$student_id]);
                }
                
                $stmt = $pdo->prepare("INSERT INTO student_parents (student_id, parent_id, relationship, is_primary) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE relationship = ?, is_primary = ?");
                $stmt->execute([$student_id, $parent_id, $relationship, $is_primary, $relationship, $is_primary]);
                
                $_SESSION['success'] = 'Parent linked successfully!';
            } catch(PDOException $e) {
                $_SESSION['error'] = 'Failed to link parent.';
            }
        }
    }
    
    redirect('students.php');
}

$page_title = 'Students';
include 'header.php';

// Get students based on user role
try {
    if ($user_role == 'admin') {
        $students_query = "
            SELECT u.*, s.section_name, s.grade_level,
                   GROUP_CONCAT(CONCAT(p.full_name, ' (', sp.relationship, ')') SEPARATOR ', ') as parents
            FROM users u 
            LEFT JOIN sections s ON u.section_id = s.id 
            LEFT JOIN student_parents sp ON u.id = sp.student_id
            LEFT JOIN users p ON sp.parent_id = p.id
            WHERE u.role = 'student' AND u.status = 'active'
            GROUP BY u.id
            ORDER BY u.full_name
        ";
        $stmt = $pdo->query($students_query);
        
    } elseif ($user_role == 'teacher') {
        $students_query = "
            SELECT u.*, s.section_name, s.grade_level,
                   GROUP_CONCAT(CONCAT(p.full_name, ' (', sp.relationship, ')') SEPARATOR ', ') as parents
            FROM users u 
            LEFT JOIN sections s ON u.section_id = s.id 
            LEFT JOIN student_parents sp ON u.id = sp.student_id
            LEFT JOIN users p ON sp.parent_id = p.id
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
                       GROUP_CONCAT(CONCAT(p.full_name, ' (', sp.relationship, ')') SEPARATOR ', ') as parents
                FROM users u 
                LEFT JOIN sections s ON u.section_id = s.id 
                LEFT JOIN student_parents sp ON u.id = sp.student_id
                LEFT JOIN users p ON sp.parent_id = p.id
                WHERE u.id = ?
                GROUP BY u.id
            ";
            $stmt = $pdo->prepare($students_query);
            $stmt->execute([$current_user['id']]);
        } else { // parent
            $students_query = "
                SELECT u.*, s.section_name, s.grade_level,
                       GROUP_CONCAT(CONCAT(p.full_name, ' (', sp.relationship, ')') SEPARATOR ', ') as parents
                FROM users u 
                LEFT JOIN sections s ON u.section_id = s.id 
                LEFT JOIN student_parents sp ON u.id = sp.student_id
                LEFT JOIN users p ON sp.parent_id = p.id
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
    
    // Get parents for linking
    if ($user_role == 'admin' || $user_role == 'teacher') {
        $parents = $pdo->query("SELECT * FROM users WHERE role = 'parent' AND status = 'active' ORDER BY full_name")->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $parents = [];
    }
    
} catch(PDOException $e) {
    $students = [];
    $sections = [];
    $parents = [];
}
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 fw-bold text-primary">
                    <i class="fas fa-users me-2"></i>Students
                </h1>
                <p class="text-muted mb-0">
                    <?php 
                    if ($user_role == 'admin') echo 'Manage all students';
                    elseif ($user_role == 'teacher') echo 'Manage your students';
                    elseif ($user_role == 'student') echo 'Your information';
                    else echo 'Your children';
                    ?>
                </p>
            </div>
            <div class="text-end">
                <?php if ($user_role == 'admin' || $user_role == 'teacher'): ?>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStudentModal">
                        <i class="fas fa-plus me-2"></i>Add Student
                    </button>
                <?php endif; ?>
                <div class="small text-muted mt-1">Total: <?php echo count($students); ?></div>
            </div>
        </div>
    </div>
</div>

<!-- Search and Filter -->
<div class="row g-3 mb-4">
    <div class="col-12 col-md-6">
        <div class="input-group">
            <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
            <input type="text" id="student-search" class="form-control border-start-0 ps-0" placeholder="Search students...">
        </div>
    </div>
    <div class="col-12 col-md-6">
        <select id="section-filter" class="form-select">
            <option value="">All Sections</option>
            <?php foreach ($sections as $section): ?>
                <option value="<?php echo $section['id']; ?>"><?php echo $section['section_name']; ?></option>
            <?php endforeach; ?>
        </select>
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
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div class="d-flex align-items-center w-100">
                                <div class="profile-avatar me-2" style="width: 45px; height: 45px; background: linear-gradient(45deg, #007bff, #0056b3); display: flex; align-items: center; justify-content: center; border-radius: 50%; color: white; font-weight: bold;">
                                    <?php echo strtoupper(substr($student['full_name'], 0, 1)); ?>
                                </div>
                                <div class="flex-grow-1 min-w-0">
                                    <h6 class="fw-bold mb-1 text-truncate"><?php echo $student['full_name']; ?></h6>
                                    <p class="text-muted small mb-0 text-truncate"><?php echo $student['username']; ?></p>
                                </div>
                                <div class="dropdown ms-2">
                                    <button class="btn btn-sm btn-link text-muted p-0" type="button" data-bs-toggle="dropdown">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li><a class="dropdown-item d-flex align-items-center" href="qr-code.php?student_id=<?php echo $student['id']; ?>">
                                            <i class="fas fa-qrcode me-2 text-primary"></i>View QR Code
                                        </a></li>
                                        <li><a class="dropdown-item d-flex align-items-center" href="attendance.php?student_id=<?php echo $student['id']; ?>">
                                            <i class="fas fa-calendar-check me-2 text-success"></i>View Attendance
                                        </a></li>
                                        <?php if ($user_role == 'admin' || $user_role == 'teacher'): ?>
                                            <li><hr class="dropdown-divider"></li>
                                            <li><a class="dropdown-item d-flex align-items-center" href="#" onclick="editStudent(<?php echo htmlspecialchars(json_encode($student)); ?>)">
                                                <i class="fas fa-edit me-2 text-info"></i>Edit
                                            </a></li>
                                            <li><a class="dropdown-item d-flex align-items-center" href="#" onclick="linkParent(<?php echo $student['id']; ?>, '<?php echo $student['full_name']; ?>')">
                                                <i class="fas fa-link me-2 text-warning"></i>Link Parent
                                            </a></li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        
                        <div class="student-info small">
                            <?php if ($student['lrn']): ?>
                                <div class="d-flex align-items-center mb-2">
                                    <i class="fas fa-id-card me-2 text-warning"></i>
                                    <span class="fw-semibold me-1">LRN:</span> 
                                    <span class="font-monospace text-truncate"><?php echo $student['lrn']; ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($student['section_name']): ?>
                                <div class="d-flex align-items-center mb-2">
                                    <i class="fas fa-school me-2 text-primary"></i>
                                    <span class="fw-semibold"><?php echo $student['section_name']; ?></span>
                                    <?php if ($student['grade_level']): ?>
                                        <span class="text-muted ms-1">- <?php echo $student['grade_level']; ?></span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($student['email']): ?>
                                <div class="d-flex align-items-center mb-2">
                                    <i class="fas fa-envelope me-2 text-info"></i>
                                    <span class="text-truncate"><?php echo $student['email']; ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($student['phone']): ?>
                                <div class="d-flex align-items-center mb-2">
                                    <i class="fas fa-phone me-2 text-success"></i>
                                    <span><?php echo $student['phone']; ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($student['parents']): ?>
                                <div class="d-flex align-items-start mb-2">
                                    <i class="fas fa-heart me-2 text-danger mt-1"></i>
                                    <span class="text-wrap"><?php echo $student['parents']; ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Attendance Summary -->
                        <?php 
                        $attendance_summary = evaluateAttendance($pdo, $student['id']);
                        ?>
                        <div class="mt-3 pt-3 border-top">
                            <div class="row g-2 text-center">
                                <div class="col-6">
                                    <div class="p-2 rounded-3 bg-light">
                                        <div class="text-<?php echo $attendance_summary['evaluation'] == 'Excellent' ? 'success' : ($attendance_summary['evaluation'] == 'Good' ? 'info' : ($attendance_summary['evaluation'] == 'Fair' ? 'warning' : 'danger')); ?>">
                                            <strong><?php echo $attendance_summary['attendance_rate']; ?>%</strong>
                                        </div>
                                        <small class="text-muted d-block">Attendance</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="p-2 rounded-3 bg-light">
                                        <div class="text-primary">
                                            <strong><?php echo $attendance_summary['present_days']; ?>/<?php echo $attendance_summary['total_days']; ?></strong>
                                        </div>
                                        <small class="text-muted d-block">Present Days</small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mt-2">
                                <span class="badge bg-<?php echo $attendance_summary['evaluation'] == 'Excellent' ? 'success' : ($attendance_summary['evaluation'] == 'Good' ? 'info' : ($attendance_summary['evaluation'] == 'Fair' ? 'warning' : 'danger')); ?> w-100">
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
    <div class="modal fade" id="addStudentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-user-plus me-2"></i>Add New Student
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_student">
                        
                        <div class="mb-3">
                            <label for="username" class="form-label">Username *</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="full_name" class="form-label">Full Name *</label>
                            <input type="text" class="form-control" id="full_name" name="full_name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="lrn" class="form-label">LRN (Learner Reference Number)</label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="lrn" name="lrn" 
                                       pattern="[0-9]{12}" maxlength="12" placeholder="Auto-generate or enter manually"
                                       title="LRN must be exactly 12 digits">
                                <button type="button" class="btn btn-outline-secondary" id="generate-lrn">
                                    <i class="fas fa-sync-alt"></i> Generate
                                </button>
                            </div>
                            <div class="form-text">Optional. Must be exactly 12 digits if provided.</div>
                            <script>
                                document.getElementById('generate-lrn').addEventListener('click', function() {
                                    // Generate random 12-digit number
                                    let randomLRN = '';
                                    for (let i = 0; i < 12; i++) {
                                        randomLRN += Math.floor(Math.random() * 10);
                                    }
                                    document.getElementById('lrn').value = randomLRN;
                                });
                            </script>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email">
                        </div>
                        
                        <div class="mb-3">
                            <label for="phone" class="form-label">Phone</label>
                            <input type="text" class="form-control" id="phone" name="phone">
                        </div>
                        
                        <div class="mb-3">
                            <label for="section_id" class="form-label">Section</label>
                            <select class="form-select select2" id="section_id" name="section_id">
                                <option value="">Select Section</option>
                                <?php foreach ($sections as $section): ?>
                                    <option value="<?php echo $section['id']; ?>">
                                        <?php echo $section['section_name']; ?> - <?php echo $section['grade_level']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Add Student
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Student Modal -->
    <div class="modal fade" id="editStudentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
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
                            <input type="text" class="form-control" id="edit_full_name" name="full_name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_lrn" class="form-label">LRN (Learner Reference Number)</label>
                            <input type="text" class="form-control" id="edit_lrn" name="lrn" 
                                   pattern="[0-9]{12}" maxlength="12" placeholder="123456789012"
                                   title="LRN must be exactly 12 digits">
                            <div class="form-text">Optional. Must be exactly 12 digits if provided.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="edit_email" name="email">
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_phone" class="form-label">Phone</label>
                            <input type="text" class="form-control" id="edit_phone" name="phone">
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_section_id" class="form-label">Section</label>
                            <select class="form-select select2" id="edit_section_id" name="section_id">
                                <option value="">Select Section</option>
                                <?php foreach ($sections as $section): ?>
                                    <option value="<?php echo $section['id']; ?>">
                                        <?php echo $section['section_name']; ?> - <?php echo $section['grade_level']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Update Student
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Link Parent Modal -->
    <div class="modal fade" id="linkParentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-link me-2"></i>Link Parent
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="link_parent">
                        <input type="hidden" name="student_id" id="link_student_id">
                        
                        <div class="mb-3">
                            <label class="form-label">Student</label>
                            <p class="form-control-plaintext fw-bold" id="link_student_name"></p>
                        </div>
                        
                        <div class="mb-3">
                            <label for="parent_id" class="form-label">Parent *</label>
                            <select class="form-select select2" id="parent_id" name="parent_id" required>
                                <option value="">Select Parent</option>
                                <?php foreach ($parents as $parent): ?>
                                    <option value="<?php echo $parent['id']; ?>">
                                        <?php echo $parent['full_name']; ?> (<?php echo $parent['username']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="relationship" class="form-label">Relationship *</label>
                            <select class="form-select" id="relationship" name="relationship" required>
                                <option value="">Select Relationship</option>
                                <option value="father">Father</option>
                                <option value="mother">Mother</option>
                                <option value="guardian">Guardian</option>
                            </select>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="is_primary" name="is_primary">
                            <label class="form-check-label" for="is_primary">
                                Primary contact (will receive SMS notifications)
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-link me-2"></i>Link Parent
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<script>
// Search and filter functionality
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('student-search');
    const sectionFilter = document.getElementById('section-filter');
    const studentCards = document.querySelectorAll('.student-card');
    
    function filterStudents() {
        const searchTerm = searchInput.value.toLowerCase();
        const selectedSection = sectionFilter.value;
        
        studentCards.forEach(card => {
            const name = card.dataset.name;
            const username = card.dataset.username;
            const section = card.dataset.section;
            
            const matchesSearch = !searchTerm || name.includes(searchTerm) || username.includes(searchTerm);
            const matchesSection = !selectedSection || section === selectedSection;
            
            if (matchesSearch && matchesSection) {
                card.style.display = 'block';
                card.classList.add('fade-in');
            } else {
                card.style.display = 'none';
                card.classList.remove('fade-in');
            }
        });
        
        // Update results count
        const visibleCards = document.querySelectorAll('.student-card[style*="block"], .student-card:not([style*="none"])').length;
        document.querySelector('.small.text-muted').textContent = `Showing: ${visibleCards} of ${studentCards.length}`;
    }
    
    searchInput.addEventListener('input', filterStudents);
    sectionFilter.addEventListener('change', filterStudents);
});

// Edit student function
function editStudent(student) {
    document.getElementById('edit_student_id').value = student.id;
    document.getElementById('edit_full_name').value = student.full_name;
    document.getElementById('edit_lrn').value = student.lrn || '';
    document.getElementById('edit_email').value = student.email || '';
    document.getElementById('edit_phone').value = student.phone || '';
    document.getElementById('edit_section_id').value = student.section_id || '';
    
    new bootstrap.Modal(document.getElementById('editStudentModal')).show();
}

// Link parent function
function linkParent(studentId, studentName) {
    document.getElementById('link_student_id').value = studentId;
    document.getElementById('link_student_name').textContent = studentName;
    
    new bootstrap.Modal(document.getElementById('linkParentModal')).show();
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
document.addEventListener('shown.bs.modal', function() {
    $('.select2').select2({
        theme: 'bootstrap-5',
        width: '100%',
        dropdownParent: $('.modal:visible')
    });
});
</script>

<?php include 'footer.php'; ?>

<style>
@media (max-width: 576px) {
    .profile-avatar {
        width: 40px !important;
        height: 40px !important;
    }
    
    .card-body {
        padding: 0.75rem !important;
    }
    
    .student-info {
        font-size: 0.8125rem;
    }
    
    .dropdown-menu {
        font-size: 0.875rem;
    }
    
    .btn-sm {
        padding: 0.25rem 0.5rem;
        font-size: 0.75rem;
    }
}

.fade-in {
    animation: fadeIn 0.3s ease-in;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.student-card {
    transition: transform 0.2s;
}

.student-card:active {
    transform: scale(0.98);
}
</style>
