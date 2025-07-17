<?php
require_once 'config.php';

// Check if user is logged in and is admin
requireRole('admin');

$current_user = getCurrentUser($pdo);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if ($action == 'add_user') {
            $username = sanitize_input($_POST['username']);
            $full_name = sanitize_input($_POST['full_name']);
            $email = sanitize_input($_POST['email']);
            $phone = sanitize_input($_POST['phone']);
            $role = sanitize_input($_POST['role']);
            $section_id = !empty($_POST['section_id']) ? intval($_POST['section_id']) : null;
            
            try {
                // Check if username exists
                $check_stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                $check_stmt->execute([$username]);
                if ($check_stmt->fetch()) {
                    $_SESSION['error'] = 'Username already exists.';
                } else {
                    $qr_code = null;
                    if ($role == 'student') {
                        $qr_code = generateStudentQR($username);
                    }
                    
                    $stmt = $pdo->prepare("INSERT INTO users (username, full_name, email, phone, role, section_id, qr_code) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$username, $full_name, $email, $phone, $role, $section_id, $qr_code]);
                    
                    $_SESSION['success'] = 'User added successfully!';
                }
            } catch(PDOException $e) {
                $_SESSION['error'] = 'Failed to add user.';
            }
            
        } elseif ($action == 'update_user') {
            $user_id = intval($_POST['user_id']);
            $full_name = sanitize_input($_POST['full_name']);
            $email = sanitize_input($_POST['email']);
            $phone = sanitize_input($_POST['phone']);
            $role = sanitize_input($_POST['role']);
            $section_id = !empty($_POST['section_id']) ? intval($_POST['section_id']) : null;
            $status = sanitize_input($_POST['status']);
            
            try {
                $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, phone = ?, role = ?, section_id = ?, status = ? WHERE id = ?");
                $stmt->execute([$full_name, $email, $phone, $role, $section_id, $status, $user_id]);
                
                $_SESSION['success'] = 'User updated successfully!';
            } catch(PDOException $e) {
                $_SESSION['error'] = 'Failed to update user.';
            }
            
        } elseif ($action == 'add_section') {
            $section_name = sanitize_input($_POST['section_name']);
            $grade_level = sanitize_input($_POST['grade_level']);
            $teacher_id = !empty($_POST['teacher_id']) ? intval($_POST['teacher_id']) : null;
            $description = sanitize_input($_POST['description']);
            
            try {
                $stmt = $pdo->prepare("INSERT INTO sections (section_name, grade_level, teacher_id, description) VALUES (?, ?, ?, ?)");
                $stmt->execute([$section_name, $grade_level, $teacher_id, $description]);
                
                $_SESSION['success'] = 'Section added successfully!';
            } catch(PDOException $e) {
                $_SESSION['error'] = 'Failed to add section.';
            }
        }
    }
    
    redirect('users.php');
}

// Get filter parameters
$role_filter = isset($_GET['role']) ? $_GET['role'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$section_filter = isset($_GET['section']) ? intval($_GET['section']) : 0;

$page_title = 'User Management';
include 'header.php';

// Get users with filters
try {
    $where_conditions = ['1=1'];
    $query_params = [];
    
    if ($role_filter) {
        $where_conditions[] = 'u.role = ?';
        $query_params[] = $role_filter;
    }
    
    if ($status_filter) {
        $where_conditions[] = 'u.status = ?';
        $query_params[] = $status_filter;
    }
    
    if ($section_filter) {
        $where_conditions[] = 'u.section_id = ?';
        $query_params[] = $section_filter;
    }
    
    $users_query = "
        SELECT u.*, s.section_name, s.grade_level,
               COUNT(sp.student_id) as children_count
        FROM users u 
        LEFT JOIN sections s ON u.section_id = s.id 
        LEFT JOIN student_parents sp ON u.id = sp.parent_id
        WHERE " . implode(' AND ', $where_conditions) . "
        GROUP BY u.id
        ORDER BY u.role, u.full_name
    ";
    
    $stmt = $pdo->prepare($users_query);
    $stmt->execute($query_params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get sections for dropdowns
    $sections = $pdo->query("SELECT * FROM sections WHERE status = 'active' ORDER BY section_name")->fetchAll(PDO::FETCH_ASSOC);
    
    // Get teachers for section assignment
    $teachers = $pdo->query("SELECT * FROM users WHERE role = 'teacher' AND status = 'active' ORDER BY full_name")->fetchAll(PDO::FETCH_ASSOC);
    
    // Get user statistics
    $user_stats = [
        'total' => $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'active'")->fetchColumn(),
        'admin' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 'active'")->fetchColumn(),
        'teacher' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'teacher' AND status = 'active'")->fetchColumn(),
        'student' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student' AND status = 'active'")->fetchColumn(),
        'parent' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'parent' AND status = 'active'")->fetchColumn(),
    ];
    
} catch(PDOException $e) {
    $users = [];
    $sections = [];
    $teachers = [];
    $user_stats = ['total' => 0, 'admin' => 0, 'teacher' => 0, 'student' => 0, 'parent' => 0];
}
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 fw-bold text-primary">
                    <i class="fas fa-users-cog me-2"></i>User Management
                </h1>
                <p class="text-muted mb-0">Manage system users and sections</p>
            </div>
            <div class="text-end">
                <div class="btn-group me-2">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                        <i class="fas fa-user-plus me-2"></i>Add User
                    </button>
                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addSectionModal">
                        <i class="fas fa-plus me-2"></i>Add Section
                    </button>
                </div>
                <div class="small text-muted mt-1">Total Users: <?php echo count($users); ?></div>
            </div>
        </div>
    </div>
</div>

<!-- User Statistics -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-2">
        <div class="card bg-primary text-white h-100">
            <div class="card-body text-center p-3">
                <h4 class="fw-bold mb-1"><?php echo $user_stats['total']; ?></h4>
                <p class="mb-0 small">Total Users</p>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="card bg-danger text-white h-100">
            <div class="card-body text-center p-3">
                <h4 class="fw-bold mb-1"><?php echo $user_stats['admin']; ?></h4>
                <p class="mb-0 small">Admins</p>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="card bg-success text-white h-100">
            <div class="card-body text-center p-3">
                <h4 class="fw-bold mb-1"><?php echo $user_stats['teacher']; ?></h4>
                <p class="mb-0 small">Teachers</p>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="card bg-info text-white h-100">
            <div class="card-body text-center p-3">
                <h4 class="fw-bold mb-1"><?php echo $user_stats['student']; ?></h4>
                <p class="mb-0 small">Students</p>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="card bg-warning text-white h-100">
            <div class="card-body text-center p-3">
                <h4 class="fw-bold mb-1"><?php echo $user_stats['parent']; ?></h4>
                <p class="mb-0 small">Parents</p>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="card bg-secondary text-white h-100">
            <div class="card-body text-center p-3">
                <h4 class="fw-bold mb-1"><?php echo count($sections); ?></h4>
                <p class="mb-0 small">Sections</p>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="fas fa-filter me-2"></i>Filters
        </h5>
    </div>
    <div class="card-body">
        <form method="GET" action="">
            <div class="row g-3">
                <div class="col-12 col-sm-6 col-lg-3">
                    <label for="role" class="form-label">Role</label>
                    <select class="form-select" id="role" name="role">
                        <option value="">All Roles</option>
                        <option value="admin" <?php echo $role_filter == 'admin' ? 'selected' : ''; ?>>Admin</option>
                        <option value="teacher" <?php echo $role_filter == 'teacher' ? 'selected' : ''; ?>>Teacher</option>
                        <option value="student" <?php echo $role_filter == 'student' ? 'selected' : ''; ?>>Student</option>
                        <option value="parent" <?php echo $role_filter == 'parent' ? 'selected' : ''; ?>>Parent</option>
                    </select>
                </div>
                
                <div class="col-12 col-sm-6 col-lg-3">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">All Status</option>
                        <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $status_filter == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
                
                <div class="col-12 col-sm-6 col-lg-3">
                    <label for="section" class="form-label">Section</label>
                    <select class="form-select" id="section" name="section">
                        <option value="">All Sections</option>
                        <?php foreach ($sections as $section): ?>
                            <option value="<?php echo $section['id']; ?>" <?php echo $section_filter == $section['id'] ? 'selected' : ''; ?>>
                                <?php echo $section['section_name']; ?> - <?php echo $section['grade_level']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-12 col-sm-6 col-lg-3">
                    <label class="form-label d-none d-sm-block">&nbsp;</label>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search me-2"></i>Apply Filters
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Users List -->
<div class="card">
    <div class="card-header">
        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2">
            <h5 class="card-title mb-0">
                <i class="fas fa-users me-2"></i>Users
                <span class="badge bg-secondary ms-2"><?php echo count($users); ?></span>
            </h5>
            <div class="input-group" style="max-width: 300px;">
                <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                <input type="text" id="user-search" class="form-control border-start-0 ps-0" placeholder="Search users...">
            </div>
        </div>
    </div>
    <div class="card-body p-0 p-sm-3">
        <?php if (empty($users)): ?>
            <div class="text-center py-5">
                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                <h5 class="text-muted mb-2">No Users Found</h5>
                <p class="text-muted mb-3">No users match your current filters.</p>
                <a href="users.php" class="btn btn-outline-primary btn-sm">
                    <i class="fas fa-refresh me-2"></i>Reset Filters
                </a>
            </div>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($users as $user): ?>
                    <div class="col-12 col-sm-6 col-lg-4">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body p-3">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div class="d-flex align-items-center w-100">
                                        <div class="profile-avatar me-2" style="width: 45px; height: 45px; background: linear-gradient(45deg, 
                                            <?php 
                                            echo $user['role'] == 'admin' ? '#dc3545, #c82333' : 
                                                 ($user['role'] == 'teacher' ? '#28a745, #20c997' : 
                                                  ($user['role'] == 'student' ? '#007bff, #0056b3' : '#ffc107, #fd7e14')); 
                                            ?>); display: flex; align-items: center; justify-content: center; border-radius: 50%; color: white; font-weight: bold;">
                                            <i class="fas fa-<?php 
                                                echo $user['role'] == 'admin' ? 'user-shield' : 
                                                     ($user['role'] == 'teacher' ? 'chalkboard-teacher' : 
                                                      ($user['role'] == 'student' ? 'user-graduate' : 'heart')); 
                                            ?> fa-sm"></i>
                                        </div>
                                        <div class="flex-grow-1 min-w-0">
                                            <h6 class="fw-bold mb-1 text-truncate"><?php echo $user['full_name']; ?></h6>
                                            <p class="text-muted small mb-0 text-truncate">@<?php echo $user['username']; ?></p>
                                        </div>
                                        <div class="dropdown ms-2">
                                            <button class="btn btn-sm btn-link text-muted p-0" type="button" data-bs-toggle="dropdown">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end">
                                                <li><a class="dropdown-item d-flex align-items-center" href="#" onclick="editUser(<?php echo htmlspecialchars(json_encode($user)); ?>)">
                                                    <i class="fas fa-edit me-2 text-info"></i>Edit
                                                </a></li>
                                                <?php if ($user['role'] == 'student'): ?>
                                                    <li><a class="dropdown-item d-flex align-items-center" href="qr-code.php?student_id=<?php echo $user['id']; ?>">
                                                        <i class="fas fa-qrcode me-2 text-primary"></i>View QR Code
                                                    </a></li>
                                                <?php endif; ?>
                                                <li><a class="dropdown-item d-flex align-items-center" href="attendance.php?student_id=<?php echo $user['id']; ?>">
                                                    <i class="fas fa-calendar-check me-2 text-success"></i>View Records
                                                </a></li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li><a class="dropdown-item d-flex align-items-center text-<?php echo $user['status'] == 'active' ? 'warning' : 'success'; ?>" href="#" onclick="toggleUserStatus(<?php echo $user['id']; ?>, '<?php echo $user['status']; ?>')">
                                                    <i class="fas fa-<?php echo $user['status'] == 'active' ? 'pause' : 'play'; ?> me-2"></i>
                                                    <?php echo $user['status'] == 'active' ? 'Deactivate' : 'Activate'; ?>
                                                </a></li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <span class="badge bg-<?php 
                                        echo $user['role'] == 'admin' ? 'danger' : 
                                             ($user['role'] == 'teacher' ? 'success' : 
                                              ($user['role'] == 'student' ? 'primary' : 'warning')); 
                                    ?> me-2">
                                        <?php echo ucfirst($user['role']); ?>
                                    </span>
                                    
                                    <span class="badge bg-<?php echo $user['status'] == 'active' ? 'success' : 'secondary'; ?>">
                                        <?php echo ucfirst($user['status']); ?>
                                    </span>
                                </div>
                                
                                <div class="user-info small">
                                    <?php if ($user['email']): ?>
                                        <div class="d-flex align-items-center mb-2">
                                            <i class="fas fa-envelope me-2 text-info"></i>
                                            <span class="text-truncate"><?php echo $user['email']; ?></span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($user['phone']): ?>
                                        <div class="d-flex align-items-center mb-2">
                                            <i class="fas fa-phone me-2 text-success"></i>
                                            <span><?php echo $user['phone']; ?></span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($user['section_name']): ?>
                                        <div class="d-flex align-items-center mb-2">
                                            <i class="fas fa-school me-2 text-primary"></i>
                                            <span class="text-truncate">
                                                <?php echo $user['section_name']; ?>
                                                <?php if ($user['grade_level']): ?>
                                                    - <?php echo $user['grade_level']; ?>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($user['role'] == 'parent' && $user['children_count'] > 0): ?>
                                        <div class="d-flex align-items-center mb-2">
                                            <i class="fas fa-users me-2 text-warning"></i>
                                            <span><?php echo $user['children_count']; ?> child(ren)</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="mt-3 pt-3 border-top">
                                    <small class="text-muted">
                                        <i class="fas fa-clock me-1"></i>
                                        Joined: <?php echo date('M j, Y', strtotime($user['created_at'])); ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-user-plus me-2"></i>Add New User
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_user">
                    
                    <div class="mb-3">
                        <label for="username" class="form-label">Username *</label>
                        <input type="text" class="form-control" id="username" name="username" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="full_name" class="form-label">Full Name *</label>
                        <input type="text" class="form-control" id="full_name" name="full_name" required>
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
                        <label for="role" class="form-label">Role *</label>
                        <select class="form-select" id="role" name="role" required onchange="toggleSectionField(this.value)">
                            <option value="">Select Role</option>
                            <option value="admin">Admin</option>
                            <option value="teacher">Teacher</option>
                            <option value="student">Student</option>
                            <option value="parent">Parent</option>
                        </select>
                    </div>
                    
                    <div class="mb-3" id="sectionField" style="display: none;">
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
                        <i class="fas fa-save me-2"></i>Add User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-edit me-2"></i>Edit User
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_user">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <p class="form-control-plaintext" id="edit_username"></p>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_full_name" class="form-label">Full Name *</label>
                        <input type="text" class="form-control" id="edit_full_name" name="full_name" required>
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
                        <label for="edit_role" class="form-label">Role *</label>
                        <select class="form-select" id="edit_role" name="role" required onchange="toggleEditSectionField(this.value)">
                            <option value="admin">Admin</option>
                            <option value="teacher">Teacher</option>
                            <option value="student">Student</option>
                            <option value="parent">Parent</option>
                        </select>
                    </div>
                    
                    <div class="mb-3" id="editSectionField">
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
                    
                    <div class="mb-3">
                        <label for="edit_status" class="form-label">Status</label>
                        <select class="form-select" id="edit_status" name="status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Update User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Section Modal -->
<div class="modal fade" id="addSectionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-school me-2"></i>Add New Section
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_section">
                    
                    <div class="mb-3">
                        <label for="section_name" class="form-label">Section Name *</label>
                        <input type="text" class="form-control" id="section_name" name="section_name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="grade_level" class="form-label">Grade Level *</label>
                        <input type="text" class="form-control" id="grade_level" name="grade_level" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="teacher_id" class="form-label">Assign Teacher</label>
                        <select class="form-select select2" id="teacher_id" name="teacher_id">
                            <option value="">Select Teacher</option>
                            <?php foreach ($teachers as $teacher): ?>
                                <option value="<?php echo $teacher['id']; ?>">
                                    <?php echo $teacher['full_name']; ?> (<?php echo $teacher['username']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save me-2"></i>Add Section
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Show/hide section field based on role
function toggleSectionField(role) {
    const sectionField = document.getElementById('sectionField');
    if (role === 'student' || role === 'teacher') {
        sectionField.style.display = 'block';
    } else {
        sectionField.style.display = 'none';
    }
}

function toggleEditSectionField(role) {
    const sectionField = document.getElementById('editSectionField');
    if (role === 'student' || role === 'teacher') {
        sectionField.style.display = 'block';
    } else {
        sectionField.style.display = 'none';
    }
}

// Edit user function
function editUser(user) {
    document.getElementById('edit_user_id').value = user.id;
    document.getElementById('edit_username').textContent = user.username;
    document.getElementById('edit_full_name').value = user.full_name;
    document.getElementById('edit_email').value = user.email || '';
    document.getElementById('edit_phone').value = user.phone || '';
    document.getElementById('edit_role').value = user.role;
    document.getElementById('edit_section_id').value = user.section_id || '';
    document.getElementById('edit_status').value = user.status;
    
    toggleEditSectionField(user.role);
    
    new bootstrap.Modal(document.getElementById('editUserModal')).show();
}

// Toggle user status
function toggleUserStatus(userId, currentStatus) {
    const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
    const action = newStatus === 'active' ? 'activate' : 'deactivate';
    
    if (confirm(`Are you sure you want to ${action} this user?`)) {
        // Create a form to submit the status change
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="update_user">
            <input type="hidden" name="user_id" value="${userId}">
            <input type="hidden" name="status" value="${newStatus}">
        `;
        
        // Get current user data and populate form
        const userCard = event.target.closest('.card');
        const userInfo = JSON.parse(userCard.dataset.user || '{}');
        
        // You would need to add the current values here
        // For now, we'll reload the page
        document.body.appendChild(form);
        form.submit();
    }
}

// Auto-generate username from full name
document.getElementById('full_name')?.addEventListener('input', function() {
    const fullName = this.value.toLowerCase().replace(/\s+/g, '').replace(/[^a-z0-9]/g, '');
    const usernameField = document.getElementById('username');
    if (!usernameField.value) {
        usernameField.value = fullName;
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

// Search functionality
document.addEventListener('DOMContentLoaded', function() {
    // Add search input
    const cardHeader = document.querySelector('.card .card-header');
    const searchDiv = document.createElement('div');
    searchDiv.className = 'mt-2';
    searchDiv.innerHTML = `
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-search"></i></span>
            <input type="text" id="user-search" class="form-control" placeholder="Search users...">
        </div>
    `;
    cardHeader.appendChild(searchDiv);
    
    // Search functionality
    const searchInput = document.getElementById('user-search');
    const userCards = document.querySelectorAll('.card-body .card');
    
    searchInput?.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        
        userCards.forEach(card => {
            const text = card.textContent.toLowerCase();
            if (text.includes(searchTerm)) {
                card.parentElement.style.display = 'block';
            } else {
                card.parentElement.style.display = 'none';
            }
        });
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
    
    .user-info {
        font-size: 0.8125rem;
    }
    
    .dropdown-menu {
        font-size: 0.875rem;
    }
    
    .btn-sm {
        padding: 0.25rem 0.5rem;
        font-size: 0.75rem;
    }
    
    .card-header .badge {
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

.card {
    transition: transform 0.2s;
}

.card:active {
    transform: scale(0.98);
}
</style>
