<?php
require_once 'config.php';

// Check if user is logged in and has permission
requireRole(['admin']);

$current_user = getCurrentUser($pdo);
$user_role = $_SESSION['role'];

// Handle different operations
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if ($action == 'add_teacher' && $user_role == 'admin') {
            $username = sanitize_input($_POST['username']);
            $full_name = sanitize_input($_POST['full_name']);
            $password = $_POST['password'];
            $phone = sanitize_input($_POST['phone']);
            
            try {
                // Check if username exists
                $check_stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                $check_stmt->execute([$username]);
                if ($check_stmt->fetch()) {
                    $_SESSION['error'] = 'Username already exists.';
                    redirect('teachers.php');
                }
                
                // Validate password
                if (strlen($password) < 6) {
                    $_SESSION['error'] = 'Password must be at least 6 characters long.';
                    redirect('teachers.php');
                }
                
                // Validate phone format (if provided)
                if (!empty($phone) && !preg_match('/^(09|\+639)\d{9}$/', $phone)) {
                    $_SESSION['error'] = 'Please enter a valid Philippine phone number (09XXXXXXXXX or +639XXXXXXXXX).';
                    redirect('teachers.php');
                }
                
                $stmt = $pdo->prepare("INSERT INTO users (username, full_name, password, phone, role, status) VALUES (?, ?, ?, ?, 'teacher', 'active')");
                $stmt->execute([$username, $full_name, $password, $phone ?: null]);
                
                $_SESSION['success'] = 'Teacher added successfully!';
            } catch(PDOException $e) {
                $_SESSION['error'] = 'Failed to add teacher: ' . $e->getMessage();
            }
            
        } elseif ($action == 'update_teacher' && $user_role == 'admin') {
            $teacher_id = intval($_POST['teacher_id']);
            $full_name = sanitize_input($_POST['full_name']);
            $phone = sanitize_input($_POST['phone']);
            $status = sanitize_input($_POST['status']);
            
            try {
                // Validate phone format (if provided)
                if (!empty($phone) && !preg_match('/^(09|\+639)\d{9}$/', $phone)) {
                    $_SESSION['error'] = 'Please enter a valid Philippine phone number (09XXXXXXXXX or +639XXXXXXXXX).';
                    redirect('teachers.php');
                }
                
                $stmt = $pdo->prepare("UPDATE users SET full_name = ?, phone = ?, status = ? WHERE id = ? AND role = 'teacher'");
                $stmt->execute([$full_name, $phone ?: null, $status, $teacher_id]);
                
                $_SESSION['success'] = 'Teacher updated successfully!';
            } catch(PDOException $e) {
                $_SESSION['error'] = 'Failed to update teacher: ' . $e->getMessage();
            }
            
        } elseif ($action == 'delete_teacher' && $user_role == 'admin') {
            $teacher_id = intval($_POST['teacher_id']);
            
            try {
                // Check if teacher has any subjects assigned
                $subjects_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM subjects WHERE teacher_id = ? AND status = 'active'");
                $subjects_stmt->execute([$teacher_id]);
                $subjects_count = $subjects_stmt->fetchColumn();
                
                if ($subjects_count > 0) {
                    $_SESSION['error'] = 'Cannot delete teacher. Please reassign or remove their subjects first.';
                    redirect('teachers.php');
                }
                
                // Check if teacher has any sections assigned
                $sections_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM sections WHERE teacher_id = ? AND status = 'active'");
                $sections_stmt->execute([$teacher_id]);
                $sections_count = $sections_stmt->fetchColumn();
                
                if ($sections_count > 0) {
                    $_SESSION['error'] = 'Cannot delete teacher. Please reassign or remove their sections first.';
                    redirect('teachers.php');
                }
                
                // Check if teacher has recorded any attendance
                $attendance_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM attendance WHERE teacher_id = ?");
                $attendance_stmt->execute([$teacher_id]);
                $attendance_count = $attendance_stmt->fetchColumn();
                
                if ($attendance_count > 0) {
                    // Instead of deleting, mark as inactive to preserve attendance history
                    $stmt = $pdo->prepare("UPDATE users SET status = 'inactive' WHERE id = ? AND role = 'teacher'");
                    $stmt->execute([$teacher_id]);
                    $_SESSION['success'] = 'Teacher marked as inactive to preserve attendance records.';
                } else {
                    // Safe to delete
                    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'teacher'");
                    $stmt->execute([$teacher_id]);
                    $_SESSION['success'] = 'Teacher deleted successfully!';
                }
                
            } catch(PDOException $e) {
                $_SESSION['error'] = 'Failed to delete teacher: ' . $e->getMessage();
            }
        }
    }
    
    redirect('teachers.php');
}

// Get teachers data
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? sanitize_input($_GET['status']) : '';
$records_per_page = 10;
$offset = ($page - 1) * $records_per_page;

// Build search query
$where_conditions = ["role = 'teacher'"];
$search_params = [];

if (!empty($search)) {
    $where_conditions[] = "(full_name LIKE ? OR username LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $search_term = "%$search%";
    $search_params = array_merge($search_params, [$search_term, $search_term, $search_term, $search_term]);
}

if (!empty($status_filter)) {
    $where_conditions[] = "status = ?";
    $search_params[] = $status_filter;
}

$where_clause = implode(' AND ', $where_conditions);

try {
    // Get total count for pagination
    $count_query = "SELECT COUNT(*) FROM users WHERE $where_clause";
    $count_stmt = $pdo->prepare($count_query);
    $count_stmt->execute($search_params);
    $total_records = $count_stmt->fetchColumn();
    $total_pages = ceil($total_records / $records_per_page);
    
    // Get teachers with pagination
    $teachers_query = "
        SELECT u.*, 
               (SELECT COUNT(*) FROM subjects WHERE teacher_id = u.id AND status = 'active') as subjects_count,
               (SELECT COUNT(*) FROM sections WHERE teacher_id = u.id AND status = 'active') as sections_count,
               (SELECT COUNT(*) FROM attendance WHERE teacher_id = u.id) as attendance_count
        FROM users u 
        WHERE $where_clause 
        ORDER BY u.created_at DESC, u.full_name ASC 
        LIMIT $records_per_page OFFSET $offset
    ";
    
    $teachers_stmt = $pdo->prepare($teachers_query);
    $teachers_stmt->execute($search_params);
    $teachers = $teachers_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total statistics
    $stats_query = "SELECT 
        COUNT(*) as total_teachers,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_teachers,
        SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive_teachers
    FROM users WHERE role = 'teacher'";
    $stats_stmt = $pdo->query($stats_query);
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    $teachers = [];
    $total_records = 0;
    $total_pages = 0;
    $stats = ['total_teachers' => 0, 'active_teachers' => 0, 'inactive_teachers' => 0];
}

$page_title = 'Manage Teachers';
include 'header.php';
?>

<!-- Header Section -->
<div class="row">
    <div class="col-12">
        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3 mb-4">
            <div>
                <h1 class="h3 fw-bold text-primary">
                    <i class="fas fa-chalkboard-teacher me-2"></i>Manage Teachers
                </h1>
                <p class="text-muted mb-0">Add, edit, and manage teacher accounts</p>
            </div>
            <div class="text-sm-end">
                <button type="button" class="btn btn-primary w-100 w-sm-auto" data-bs-toggle="modal" data-bs-target="#addTeacherModal">
                    <i class="fas fa-plus me-2"></i>Add Teacher
                </button>
                <div class="small text-muted mt-1">
                    Total: <?php echo $stats['total_teachers']; ?> teachers
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card bg-primary text-white h-100">
            <div class="card-body text-center p-3">
                <i class="fas fa-chalkboard-teacher fa-2x mb-2"></i>
                <h4 class="fw-bold mb-0"><?php echo $stats['total_teachers']; ?></h4>
                <p class="small mb-0">Total Teachers</p>
            </div>
        </div>
    </div>
    
    <div class="col-6 col-lg-3">
        <div class="card bg-success text-white h-100">
            <div class="card-body text-center p-3">
                <i class="fas fa-check-circle fa-2x mb-2"></i>
                <h4 class="fw-bold mb-0"><?php echo $stats['active_teachers']; ?></h4>
                <p class="small mb-0">Active</p>
            </div>
        </div>
    </div>
    
    <div class="col-6 col-lg-3">
        <div class="card bg-secondary text-white h-100">
            <div class="card-body text-center p-3">
                <i class="fas fa-pause-circle fa-2x mb-2"></i>
                <h4 class="fw-bold mb-0"><?php echo $stats['inactive_teachers']; ?></h4>
                <p class="small mb-0">Inactive</p>
            </div>
        </div>
    </div>
    
    <div class="col-6 col-lg-3">
        <div class="card bg-info text-white h-100">
            <div class="card-body text-center p-3">
                <i class="fas fa-percentage fa-2x mb-2"></i>
                <h4 class="fw-bold mb-0"><?php echo $stats['total_teachers'] > 0 ? round(($stats['active_teachers'] / $stats['total_teachers']) * 100, 1) : 0; ?>%</h4>
                <p class="small mb-0">Active Rate</p>
            </div>
        </div>
    </div>
</div>

<!-- Search and Filter -->
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">
            <i class="fas fa-search me-2"></i>Search & Filter
        </h5>
        <button class="btn btn-link btn-sm d-md-none p-0" type="button" data-bs-toggle="collapse" data-bs-target="#searchCollapse">
            <i class="fas fa-chevron-down"></i>
        </button>
    </div>
    <div class="card-body collapse d-md-block" id="searchCollapse">
        <form method="GET" action="">
            <div class="row g-3">
                <div class="col-12 col-md-6 col-lg-4">
                    <label for="search" class="form-label">Search</label>
                    <input type="text" class="form-control" id="search" name="search" 
                           value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="Search by name, username, email, or phone">
                </div>
                
                <div class="col-12 col-md-6 col-lg-3">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">All Status</option>
                        <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $status_filter == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
                
                <div class="col-12 col-md-12 col-lg-5">
                    <label class="form-label d-block">&nbsp;</label>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-fill">
                            <i class="fas fa-search me-2"></i>Search
                        </button>
                        <a href="teachers.php" class="btn btn-outline-secondary flex-fill">
                            <i class="fas fa-times me-2"></i>Clear
                        </a>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Teachers Table -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">
            <i class="fas fa-table me-2"></i>Teachers List
            <span class="badge bg-secondary ms-2"><?php echo $total_records; ?></span>
        </h5>
        <small class="text-muted">Page <?php echo $page; ?> of <?php echo $total_pages; ?></small>
    </div>
    <div class="card-body p-0">
        <?php if (empty($teachers)): ?>
            <div class="text-center py-5">
                <i class="fas fa-chalkboard-teacher fa-4x text-muted mb-3"></i>
                <h4 class="text-muted mb-2">No Teachers Found</h4>
                <p class="text-muted mb-3">
                    <?php if (!empty($search) || !empty($status_filter)): ?>
                        No teachers match your search criteria.
                    <?php else: ?>
                        Start by adding your first teacher.
                    <?php endif; ?>
                </p>
                <?php if (empty($search) && empty($status_filter)): ?>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTeacherModal">
                        <i class="fas fa-plus me-2"></i>Add First Teacher
                    </button>
                <?php else: ?>
                    <a href="teachers.php" class="btn btn-outline-primary">
                        <i class="fas fa-times me-2"></i>Clear Filters
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="row g-3 p-3">
                <?php foreach ($teachers as $teacher): ?>
                    <div class="col-12 col-md-6 col-lg-4">
                        <div class="card h-100 teacher-card border-0 shadow-sm">
                            <div class="card-body p-3">
                                <!-- Teacher Header -->
                                <div class="d-flex align-items-center mb-3">
                                    <div class="profile-avatar me-3" style="width: 55px; height: 55px; background: linear-gradient(45deg, #007bff, #0056b3); display: flex; align-items: center; justify-content: center; border-radius: 50%; color: white; font-weight: bold; font-size: 1.3rem; box-shadow: 0 2px 8px rgba(0,123,255,0.3);">
                                        <?php echo strtoupper(substr($teacher['full_name'], 0, 1)); ?>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h6 class="fw-bold mb-1 text-primary"><?php echo htmlspecialchars($teacher['full_name']); ?></h6>
                                        <small class="text-muted d-block">@<?php echo htmlspecialchars($teacher['username']); ?></small>
                                        <div class="d-flex align-items-center mt-1">
                                            <span class="badge bg-<?php echo $teacher['status'] == 'active' ? 'success' : 'secondary'; ?> me-2">
                                                <?php echo ucfirst($teacher['status']); ?>
                                            </span>
                                            <small class="text-muted">
                                                <i class="fas fa-calendar-plus me-1"></i>
                                                <?php echo date('M j, Y', strtotime($teacher['created_at'])); ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Contact Information -->
                                <div class="mb-3">
                                    <h6 class="text-secondary mb-2">
                                        <i class="fas fa-address-book me-2"></i>Contact Details
                                    </h6>
                                    <div class="contact-info">
                                        <?php if ($teacher['phone']): ?>
                                            <div class="mb-1 d-flex align-items-center">
                                                <i class="fas fa-phone me-2 text-success" style="width: 16px;"></i>
                                                <small><?php echo htmlspecialchars($teacher['phone']); ?></small>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!$teacher['phone']): ?>
                                            <small class="text-muted fst-italic">
                                                <i class="fas fa-info-circle me-1"></i>No contact information available
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Assignments Section -->
                                <div class="mb-3">
                                    <h6 class="text-secondary mb-2">
                                        <i class="fas fa-tasks me-2"></i>Assignments
                                    </h6>
                                    <div class="row g-2 text-center">
                                        <div class="col-4">
                                            <div class="assignment-stat bg-primary bg-opacity-10 rounded p-2">
                                                <div class="fw-bold text-primary"><?php echo $teacher['subjects_count']; ?></div>
                                                <small class="text-muted">Subject<?php echo $teacher['subjects_count'] != 1 ? 's' : ''; ?></small>
                                            </div>
                                        </div>
                                        <div class="col-4">
                                            <div class="assignment-stat bg-info bg-opacity-10 rounded p-2">
                                                <div class="fw-bold text-info"><?php echo $teacher['sections_count']; ?></div>
                                                <small class="text-muted">Section<?php echo $teacher['sections_count'] != 1 ? 's' : ''; ?></small>
                                            </div>
                                        </div>
                                        <div class="col-4">
                                            <div class="assignment-stat bg-success bg-opacity-10 rounded p-2">
                                                <div class="fw-bold text-success"><?php echo $teacher['attendance_count']; ?></div>
                                                <small class="text-muted">Records</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Quick Stats -->
                                <div class="mb-3">
                                    <div class="row g-2">
                                        <div class="col-12">
                                            <div class="d-flex align-items-center bg-light rounded p-2">
                                                <i class="fas fa-chalkboard-teacher me-2 text-primary"></i>
                                                <div>
                                                    <small class="text-muted d-block">Total Load</small>
                                                    <span class="fw-bold"><?php echo $teacher['subjects_count'] + $teacher['sections_count']; ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Card Footer with Actions -->
                            <div class="card-footer bg-transparent border-top-0 pt-0 pb-3 px-3">
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-outline-primary btn-sm flex-fill" 
                                            onclick="editTeacher(<?php echo htmlspecialchars(json_encode($teacher)); ?>)" 
                                            data-bs-toggle="tooltip" title="Edit Teacher">
                                        <i class="fas fa-edit me-2"></i>Edit
                                    </button>
                                    <button type="button" class="btn btn-outline-danger btn-sm flex-fill" 
                                            onclick="deleteTeacher(<?php echo $teacher['id']; ?>, '<?php echo htmlspecialchars($teacher['full_name']); ?>')" 
                                            data-bs-toggle="tooltip" title="Delete Teacher">
                                        <i class="fas fa-trash me-2"></i>Delete
                                    </button>
                                </div>
                                
                                <!-- Additional Quick Actions -->
                                <div class="d-flex gap-1 mt-2">
                                    <?php if ($teacher['subjects_count'] > 0): ?>
                                        <a href="subjects.php?teacher_id=<?php echo $teacher['id']; ?>" 
                                           class="btn btn-link btn-sm p-1 text-decoration-none" 
                                           data-bs-toggle="tooltip" title="View Subjects">
                                            <i class="fas fa-book"></i>
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($teacher['sections_count'] > 0): ?>
                                        <a href="sections.php?teacher_id=<?php echo $teacher['id']; ?>" 
                                           class="btn btn-link btn-sm p-1 text-decoration-none" 
                                           data-bs-toggle="tooltip" title="View Sections">
                                            <i class="fas fa-school"></i>
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($teacher['attendance_count'] > 0): ?>
                                        <a href="attendance.php?teacher_id=<?php echo $teacher['id']; ?>" 
                                           class="btn btn-link btn-sm p-1 text-decoration-none" 
                                           data-bs-toggle="tooltip" title="View Attendance Records">
                                            <i class="fas fa-calendar-check"></i>
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($teacher["phone"]): ?>
                                        <a href="tel:<?php echo htmlspecialchars($teacher["phone"]); ?>" 
                                           class="btn btn-link btn-sm p-1 text-decoration-none" 
                                           data-bs-toggle="tooltip" title="Call">
                                            <i class="fas fa-phone"></i>
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($teacher['phone']): ?>
                                        <a href="tel:<?php echo htmlspecialchars($teacher['phone']); ?>" 
                                           class="btn btn-link btn-sm p-1 text-decoration-none" 
                                           data-bs-toggle="tooltip" title="Call">
                                            <i class="fas fa-phone"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="d-flex justify-content-center mt-4">
                    <nav aria-label="Teachers pagination">
                        <ul class="pagination pagination-sm mb-0">
                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>
                            
                            <?php
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            
                            for ($i = $start_page; $i <= $end_page; $i++):
                            ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                </div>
                <div class="text-center mt-2">
                    <small class="text-muted">
                        Showing <?php echo (($page - 1) * $records_per_page) + 1; ?> to 
                        <?php echo min($page * $records_per_page, $total_records); ?> of 
                        <?php echo $total_records; ?> teachers
                    </small>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Add Teacher Modal -->
<div class="modal fade" id="addTeacherModal" tabindex="-1" aria-labelledby="addTeacherModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="addTeacherModalLabel">
                    <i class="fas fa-plus me-2"></i>Add New Teacher
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_teacher">
                    
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label for="add_username" class="form-label">Username <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="add_username" name="username" required 
                                   placeholder="Enter username">
                            <div class="form-text">Unique username for login</div>
                        </div>
                        
                        <div class="col-12 col-md-6">
                            <label for="add_full_name" class="form-label">Full Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="add_full_name" name="full_name" required 
                                   placeholder="Enter full name">
                        </div>
                        
                        <div class="col-12 col-md-6">
                            <label for="add_password" class="form-label">Password</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0">
                                    <i class="fas fa-lock text-primary"></i>
                                </span>
                                <input type="password" 
                                       class="form-control border-start-0 border-end-0" 
                                       id="add_password" 
                                       name="password" 
                                       required 
                                       placeholder="Enter password"
                                       minlength="6">
                                <button class="btn btn-outline-secondary border-start-0" type="button" id="toggleAddPassword">
                                    <i class="fas fa-eye" id="toggleAddIcon"></i>
                                </button>
                            </div>
                            <div class="form-text">Minimum 6 characters required</div>
                        </div>
                        
                        <div class="col-12 col-md-6">
                            <label for="add_phone" class="form-label">Phone Number</label>
                            <input type="text" class="form-control" id="add_phone" name="phone" 
                                   placeholder="09XXXXXXXXX or +639XXXXXXXXX">
                            <div class="form-text">Philippine phone number format</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Add Teacher
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Teacher Modal -->
<div class="modal fade" id="editTeacherModal" tabindex="-1" aria-labelledby="editTeacherModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title" id="editTeacherModalLabel">
                    <i class="fas fa-edit me-2"></i>Edit Teacher
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_teacher">
                    <input type="hidden" name="teacher_id" id="edit_teacher_id">
                    
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control" id="edit_username" readonly>
                            <div class="form-text">Username cannot be changed</div>
                        </div>
                        
                        <div class="col-12 col-md-6">
                            <label for="edit_full_name" class="form-label">Full Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_full_name" name="full_name" required>
                        </div>
                        
                        <div class="col-12 col-md-6">
                            <label for="edit_status" class="form-label">Status <span class="text-danger">*</span></label>
                            <select class="form-select" id="edit_status" name="status" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        
                        <div class="col-12 col-md-6">
                            <label for="edit_email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="edit_email" name="email">
                        </div>
                        
                        <div class="col-12 col-md-6">
                            <label for="edit_phone" class="form-label">Phone Number</label>
                            <input type="text" class="form-control" id="edit_phone" name="phone">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-save me-2"></i>Update Teacher
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Teacher Modal -->
<div class="modal fade" id="deleteTeacherModal" tabindex="-1" aria-labelledby="deleteTeacherModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteTeacherModalLabel">
                    <i class="fas fa-trash me-2"></i>Delete Teacher
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="delete_teacher">
                    <input type="hidden" name="teacher_id" id="delete_teacher_id">
                    
                    <div class="text-center">
                        <i class="fas fa-exclamation-triangle fa-4x text-warning mb-3"></i>
                        <h5>Are you sure?</h5>
                        <p class="text-muted">
                            You are about to delete teacher <strong id="delete_teacher_name"></strong>.
                            This action cannot be undone.
                        </p>
                        <div class="alert alert-warning">
                            <i class="fas fa-info-circle me-2"></i>
                            If the teacher has attendance records, they will be marked as inactive instead of deleted to preserve data integrity.
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash me-2"></i>Delete Teacher
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Edit teacher function
function editTeacher(teacher) {
    document.getElementById('edit_teacher_id').value = teacher.id;
    document.getElementById('edit_username').value = teacher.username;
    document.getElementById('edit_full_name').value = teacher.full_name;
    document.getElementById('edit_email').value = teacher.email || '';
    document.getElementById('edit_phone').value = teacher.phone || '';
    document.getElementById('edit_status').value = teacher.status;
    
    const editModal = new bootstrap.Modal(document.getElementById('editTeacherModal'));
    editModal.show();
}

// Enhanced delete teacher function with confirmation animation
function deleteTeacher(teacherId, teacherName) {
    // Add shake animation to the card being deleted
    const teacherCards = document.querySelectorAll('.teacher-card');
    teacherCards.forEach(card => {
        const cardButtons = card.querySelectorAll('button');
        cardButtons.forEach(button => {
            if (button.onclick && button.onclick.toString().includes(teacherId)) {
                card.style.animation = 'shake 0.5s ease-in-out';
            }
        });
    });
    
    document.getElementById('delete_teacher_id').value = teacherId;
    document.getElementById('delete_teacher_name').textContent = teacherName;
    
    const deleteModal = new bootstrap.Modal(document.getElementById('deleteTeacherModal'));
    deleteModal.show();
}

// Add shake animation keyframes via JavaScript
const style = document.createElement('style');
style.textContent = `
    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        25% { transform: translateX(-5px); }
        75% { transform: translateX(5px); }
    }
`;
document.head.appendChild(style);

// Phone number validation
document.getElementById('add_phone').addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, '');
    if (value.length >= 10) {
        if (value.startsWith('63')) {
            e.target.value = '+' + value;
        } else if (value.startsWith('9')) {
            e.target.value = '0' + value;
        }
    }
});

document.getElementById('edit_phone').addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, '');
    if (value.length >= 10) {
        if (value.startsWith('63')) {
            e.target.value = '+' + value;
        } else if (value.startsWith('9')) {
            e.target.value = '0' + value;
        }
    }
});

// Password toggle functionality
function togglePasswordVisibility(inputId, toggleButton) {
    const passwordInput = document.getElementById(inputId);
    const toggleIcon = toggleButton.querySelector('i') || document.getElementById('toggleAddIcon');
    
    if (passwordInput && toggleIcon) {
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            toggleIcon.className = 'fas fa-eye-slash';
        } else {
            passwordInput.type = 'password';
            toggleIcon.className = 'fas fa-eye';
        }
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const toggleAddPassword = document.getElementById('toggleAddPassword');
    const addPasswordField = document.getElementById('add_password');
    const toggleAddIcon = document.getElementById('toggleAddIcon');

    if (toggleAddPassword && addPasswordField && toggleAddIcon) {
        // Remove any existing event listeners
        toggleAddPassword.replaceWith(toggleAddPassword.cloneNode(true));
        const newToggleAddPassword = document.getElementById('toggleAddPassword');
        
        newToggleAddPassword.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            togglePasswordVisibility('add_password', this);
        });
    }
});

// Initialize tooltips
document.addEventListener('DOMContentLoaded', function() {
    var tooltips = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltips.map(function(el) {
        return new bootstrap.Tooltip(el);
    });
    
    // Add staggered animation to cards
    const cards = document.querySelectorAll('.teacher-card');
    cards.forEach((card, index) => {
        card.style.animationDelay = `${index * 0.1}s`;
    });
    
    // Add click handler for card expansion (optional)
    cards.forEach(card => {
        card.addEventListener('click', function(e) {
            // Don't trigger on button clicks
            if (!e.target.closest('button') && !e.target.closest('a')) {
                this.classList.toggle('expanded');
            }
        });
    });
});

// Enhanced edit teacher function with loading state
function editTeacher(teacher) {
    // Show loading state
    const editButton = event.target.closest('button');
    const originalContent = editButton.innerHTML;
    editButton.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Loading...';
    editButton.disabled = true;
    
    // Simulate brief loading (can be removed in production)
    setTimeout(() => {
        document.getElementById('edit_teacher_id').value = teacher.id;
        document.getElementById('edit_username').value = teacher.username;
        document.getElementById('edit_full_name').value = teacher.full_name;
        document.getElementById('edit_email').value = teacher.email || '';
        document.getElementById('edit_phone').value = teacher.phone || '';
        document.getElementById('edit_status').value = teacher.status;
        
        const editModal = new bootstrap.Modal(document.getElementById('editTeacherModal'));
        editModal.show();
        
        // Restore button state
        editButton.innerHTML = originalContent;
        editButton.disabled = false;
    }, 300);
}

// Auto-refresh page every 5 minutes to keep data current
setInterval(function() {
    if (document.visibilityState === 'visible') {
        const currentParams = new URLSearchParams(window.location.search);
        window.location.href = 'teachers.php?' + currentParams.toString();
    }
}, 300000);
</script>

<style>
/* Password toggle styles to match login */
#toggleAddPassword {
    cursor: pointer;
    user-select: none;
    border-radius: 0 0.375rem 0.375rem 0 !important;
}

#toggleAddPassword:hover {
    background-color: #e9ecef !important;
    border-color: #b0b7bf !important;
}

#toggleAddPassword:active {
    background-color: #dee2e6 !important;
    border-color: #9ca3af !important;
}

#toggleAddPassword:focus {
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25) !important;
}

.input-group .form-control.border-end-0 {
    border-radius: 0;
}

/* Mobile-friendly styles */
@media (max-width: 576px) {
    .card-body {
        padding: 0.75rem !important;
    }
    
    .teacher-card {
        margin-bottom: 1rem;
    }
    
    .profile-avatar {
        width: 45px !important;
        height: 45px !important;
        font-size: 1rem !important;
    }
    
    .assignment-stat {
        padding: 0.5rem !important;
    }
    
    .btn-sm {
        padding: 0.375rem 0.75rem;
        font-size: 0.875rem;
    }
    
    .badge {
        font-size: 0.7rem;
    }
    
    .modal-lg {
        max-width: 95%;
    }
}

/* Teacher Card Styles */
.teacher-card {
    transition: all 0.3s ease-in-out;
    border: 1px solid rgba(0,0,0,0.08) !important;
}

.teacher-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,123,255,0.15) !important;
    border-color: rgba(0,123,255,0.2) !important;
}

/* Profile Avatar */
.profile-avatar {
    transition: all 0.3s ease-in-out;
    border: 3px solid rgba(255,255,255,0.9);
}

.teacher-card:hover .profile-avatar {
    transform: scale(1.05);
    box-shadow: 0 4px 15px rgba(0,123,255,0.4) !important;
}

/* Assignment Stats */
.assignment-stat {
    transition: all 0.2s ease-in-out;
    border: 1px solid transparent;
}

.assignment-stat:hover {
    transform: translateY(-2px);
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

/* Contact Info */
.contact-info {
    background: rgba(248,249,250,0.8);
    border-radius: 6px;
    padding: 0.5rem;
    border-left: 3px solid #007bff;
}

/* Status Badges */
.badge {
    transition: all 0.2s ease-in-out;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.badge:hover {
    transform: scale(1.05);
}

/* Action Buttons */
.btn-outline-primary,
.btn-outline-danger {
    transition: all 0.2s ease-in-out;
    border-width: 1.5px;
}

.btn-outline-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 3px 8px rgba(0,123,255,0.3);
}

.btn-outline-danger:hover {
    transform: translateY(-1px);
    box-shadow: 0 3px 8px rgba(220,53,69,0.3);
}

/* Quick Action Links */
.btn-link {
    transition: all 0.2s ease-in-out;
    border-radius: 50%;
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.btn-link:hover {
    background-color: rgba(0,123,255,0.1) !important;
    transform: scale(1.1);
}

/* Card Sections */
.teacher-card .card-body {
    background: linear-gradient(145deg, #ffffff 0%, #fafbfc 100%);
}

.teacher-card .card-footer {
    background: rgba(248,249,250,0.7) !important;
    border-top: 1px solid rgba(0,123,255,0.1) !important;
}

/* Responsive Grid Adjustments */
@media (min-width: 768px) and (max-width: 991px) {
    .col-md-6 {
        flex: 0 0 50%;
        max-width: 50%;
    }
}

@media (min-width: 992px) {
    .col-lg-4 {
        flex: 0 0 33.333333%;
        max-width: 33.333333%;
    }
}

/* Statistics cards hover effect */
.row .card:hover {
    transform: translateY(-2px);
    transition: transform 0.2s ease-in-out;
}

/* Loading Animation for Cards */
.teacher-card {
    animation: fadeInUp 0.5s ease-out;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Enhanced Pagination */
.pagination .page-link {
    border-radius: 50%;
    margin: 0 2px;
    width: 35px;
    height: 35px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid #dee2e6;
    color: #007bff;
}

.pagination .page-item.active .page-link {
    background: linear-gradient(45deg, #007bff, #0056b3);
    border-color: #007bff;
    box-shadow: 0 2px 4px rgba(0,123,255,0.3);
}

.pagination .page-link:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

/* Status Indicator Glow */
.badge.bg-success {
    box-shadow: 0 0 10px rgba(40,167,69,0.3);
}

.badge.bg-secondary {
    box-shadow: 0 0 10px rgba(108,117,125,0.3);
}

/* Smooth Transitions */
* {
    transition: box-shadow 0.2s ease-in-out;
}

/* Empty State */
.text-center i.fa-4x {
    color: #e9ecef !important;
    margin-bottom: 1.5rem;
}

/* Quick Stats Hover Effects */
.bg-light:hover {
    background-color: rgba(0,123,255,0.05) !important;
    border-left: 3px solid #007bff;
    transform: translateX(2px);
}
</style>

<?php include 'footer.php'; ?>

