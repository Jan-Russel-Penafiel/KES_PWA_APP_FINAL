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
        
        if ($action == 'add_parent' && $user_role == 'admin') {
            $username = sanitize_input($_POST['username']);
            $full_name = sanitize_input($_POST['full_name']);
            $email = sanitize_input($_POST['email']);
            $phone = sanitize_input($_POST['phone']);
            
            try {
                // Check if username exists
                $check_stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                $check_stmt->execute([$username]);
                if ($check_stmt->fetch()) {
                    $_SESSION['error'] = 'Username already exists.';
                    redirect('parents.php');
                }
                
                // Check if email exists (if provided)
                if (!empty($email)) {
                    $check_email_stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                    $check_email_stmt->execute([$email]);
                    if ($check_email_stmt->fetch()) {
                        $_SESSION['error'] = 'Email already exists.';
                        redirect('parents.php');
                    }
                    
                    // Validate email format
                    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $_SESSION['error'] = 'Please enter a valid email address.';
                        redirect('parents.php');
                    }
                }
                
                // Validate phone format (if provided)
                if (!empty($phone) && !preg_match('/^(09|\+639)\d{9}$/', $phone)) {
                    $_SESSION['error'] = 'Please enter a valid Philippine phone number (09XXXXXXXXX or +639XXXXXXXXX).';
                    redirect('parents.php');
                }
                
                $stmt = $pdo->prepare("INSERT INTO users (username, full_name, email, phone, role, status) VALUES (?, ?, ?, ?, 'parent', 'active')");
                $stmt->execute([$username, $full_name, $email ?: null, $phone ?: null]);
                
                $_SESSION['success'] = 'Parent added successfully!';
            } catch(PDOException $e) {
                $_SESSION['error'] = 'Failed to add parent: ' . $e->getMessage();
            }
            
        } elseif ($action == 'update_parent' && $user_role == 'admin') {
            $parent_id = intval($_POST['parent_id']);
            $full_name = sanitize_input($_POST['full_name']);
            $email = sanitize_input($_POST['email']);
            $phone = sanitize_input($_POST['phone']);
            $status = sanitize_input($_POST['status']);
            
            try {
                // Check if email exists for other users (if provided)
                if (!empty($email)) {
                    $check_email_stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                    $check_email_stmt->execute([$email, $parent_id]);
                    if ($check_email_stmt->fetch()) {
                        $_SESSION['error'] = 'Email already exists for another user.';
                        redirect('parents.php');
                    }
                    
                    // Validate email format
                    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $_SESSION['error'] = 'Please enter a valid email address.';
                        redirect('parents.php');
                    }
                }
                
                // Validate phone format (if provided)
                if (!empty($phone) && !preg_match('/^(09|\+639)\d{9}$/', $phone)) {
                    $_SESSION['error'] = 'Please enter a valid Philippine phone number (09XXXXXXXXX or +639XXXXXXXXX).';
                    redirect('parents.php');
                }
                
                $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, phone = ?, status = ? WHERE id = ? AND role = 'parent'");
                $stmt->execute([$full_name, $email ?: null, $phone ?: null, $status, $parent_id]);
                
                $_SESSION['success'] = 'Parent updated successfully!';
            } catch(PDOException $e) {
                $_SESSION['error'] = 'Failed to update parent: ' . $e->getMessage();
            }
            
        } elseif ($action == 'delete_parent' && $user_role == 'admin') {
            $parent_id = intval($_POST['parent_id']);
            
            try {
                // Check if parent has any children assigned
                $children_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM student_parents WHERE parent_id = ?");
                $children_stmt->execute([$parent_id]);
                $children_count = $children_stmt->fetchColumn();
                
                if ($children_count > 0) {
                    $_SESSION['error'] = 'Cannot delete parent. Please remove or reassign their children first.';
                    redirect('parents.php');
                }
                
                // Check if parent has any SMS logs
                $sms_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM sms_logs WHERE phone_number = (SELECT phone FROM users WHERE id = ? AND role = 'parent')");
                $sms_stmt->execute([$parent_id]);
                $sms_count = $sms_stmt->fetchColumn();
                
                if ($sms_count > 0) {
                    // Instead of deleting, mark as inactive to preserve SMS history
                    $stmt = $pdo->prepare("UPDATE users SET status = 'inactive' WHERE id = ? AND role = 'parent'");
                    $stmt->execute([$parent_id]);
                    $_SESSION['success'] = 'Parent marked as inactive to preserve SMS records.';
                } else {
                    // Safe to delete
                    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'parent'");
                    $stmt->execute([$parent_id]);
                    $_SESSION['success'] = 'Parent deleted successfully!';
                }
                
            } catch(PDOException $e) {
                $_SESSION['error'] = 'Failed to delete parent: ' . $e->getMessage();
            }
        }
    }
    
    redirect('parents.php');
}

// Get parents data
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? sanitize_input($_GET['status']) : '';
$records_per_page = 10;
$offset = ($page - 1) * $records_per_page;

// Build search query
$where_conditions = ["role = 'parent'"];
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
    
    // Get parents with pagination
    $parents_query = "
        SELECT u.*, 
               (SELECT COUNT(*) FROM student_parents sp WHERE sp.parent_id = u.id) as children_count,
               (SELECT COUNT(*) FROM sms_logs sl WHERE sl.phone_number = u.phone AND sl.status = 'sent') as sms_count,
               (SELECT GROUP_CONCAT(DISTINCT s.full_name SEPARATOR ', ') 
                FROM student_parents sp 
                JOIN users s ON sp.student_id = s.id 
                WHERE sp.parent_id = u.id) as children_names
        FROM users u 
        WHERE $where_clause 
        ORDER BY u.created_at DESC, u.full_name ASC 
        LIMIT $records_per_page OFFSET $offset
    ";
    
    $parents_stmt = $pdo->prepare($parents_query);
    $parents_stmt->execute($search_params);
    $parents = $parents_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total statistics
    $stats_query = "SELECT 
        COUNT(*) as total_parents,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_parents,
        SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive_parents,
        (SELECT COUNT(*) FROM student_parents) as total_relationships
    FROM users WHERE role = 'parent'";
    $stats_stmt = $pdo->query($stats_query);
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    $parents = [];
    $total_records = 0;
    $total_pages = 0;
    $stats = ['total_parents' => 0, 'active_parents' => 0, 'inactive_parents' => 0, 'total_relationships' => 0];
}

$page_title = 'Manage Parents';
include 'header.php';
?>

<!-- Header Section -->
<div class="row">
    <div class="col-12">
        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3 mb-4">
            <div>
                <h1 class="h3 fw-bold text-primary">
                    <i class="fas fa-users me-2"></i>Manage Parents
                </h1>
                <p class="text-muted mb-0">Add, edit, and manage parent accounts</p>
            </div>
            <div class="text-sm-end">
                <button type="button" class="btn btn-primary w-100 w-sm-auto" data-bs-toggle="modal" data-bs-target="#addParentModal">
                    <i class="fas fa-plus me-2"></i>Add Parent
                </button>
                <div class="small text-muted mt-1">
                    Total: <?php echo $stats['total_parents']; ?> parents
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
                <i class="fas fa-users fa-2x mb-2"></i>
                <h4 class="fw-bold mb-0"><?php echo $stats['total_parents']; ?></h4>
                <p class="small mb-0">Total Parents</p>
            </div>
        </div>
    </div>
    
    <div class="col-6 col-lg-3">
        <div class="card bg-success text-white h-100">
            <div class="card-body text-center p-3">
                <i class="fas fa-check-circle fa-2x mb-2"></i>
                <h4 class="fw-bold mb-0"><?php echo $stats['active_parents']; ?></h4>
                <p class="small mb-0">Active</p>
            </div>
        </div>
    </div>
    
    <div class="col-6 col-lg-3">
        <div class="card bg-secondary text-white h-100">
            <div class="card-body text-center p-3">
                <i class="fas fa-pause-circle fa-2x mb-2"></i>
                <h4 class="fw-bold mb-0"><?php echo $stats['inactive_parents']; ?></h4>
                <p class="small mb-0">Inactive</p>
            </div>
        </div>
    </div>
    
    <div class="col-6 col-lg-3">
        <div class="card bg-info text-white h-100">
            <div class="card-body text-center p-3">
                <i class="fas fa-link fa-2x mb-2"></i>
                <h4 class="fw-bold mb-0"><?php echo $stats['total_relationships']; ?></h4>
                <p class="small mb-0">Relationships</p>
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
                        <a href="parents.php" class="btn btn-outline-secondary flex-fill">
                            <i class="fas fa-times me-2"></i>Clear
                        </a>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Parents Table -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">
            <i class="fas fa-table me-2"></i>Parents List
            <span class="badge bg-secondary ms-2"><?php echo $total_records; ?></span>
        </h5>
        <small class="text-muted">Page <?php echo $page; ?> of <?php echo $total_pages; ?></small>
    </div>
    <div class="card-body p-0">
        <?php if (empty($parents)): ?>
            <div class="text-center py-5">
                <i class="fas fa-users fa-4x text-muted mb-3"></i>
                <h4 class="text-muted mb-2">No Parents Found</h4>
                <p class="text-muted mb-3">
                    <?php if (!empty($search) || !empty($status_filter)): ?>
                        No parents match your search criteria.
                    <?php else: ?>
                        Start by adding your first parent.
                    <?php endif; ?>
                </p>
                <?php if (empty($search) && empty($status_filter)): ?>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addParentModal">
                        <i class="fas fa-plus me-2"></i>Add First Parent
                    </button>
                <?php else: ?>
                    <a href="parents.php" class="btn btn-outline-primary">
                        <i class="fas fa-times me-2"></i>Clear Filters
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="row g-3 p-3">
                <?php foreach ($parents as $parent): ?>
                    <div class="col-12 col-md-6 col-lg-4">
                        <div class="card h-100 parent-card border-0 shadow-sm">
                            <div class="card-body p-3">
                                <!-- Parent Header -->
                                <div class="d-flex align-items-center mb-3">
                                    <div class="profile-avatar me-3" style="width: 55px; height: 55px; background: linear-gradient(45deg, #28a745, #20c997); display: flex; align-items: center; justify-content: center; border-radius: 50%; color: white; font-weight: bold; font-size: 1.3rem; box-shadow: 0 2px 8px rgba(40,167,69,0.3);">
                                        <?php echo strtoupper(substr($parent['full_name'], 0, 1)); ?>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h6 class="fw-bold mb-1 text-success"><?php echo htmlspecialchars($parent['full_name']); ?></h6>
                                        <small class="text-muted d-block">@<?php echo htmlspecialchars($parent['username']); ?></small>
                                        <div class="d-flex align-items-center mt-1">
                                            <span class="badge bg-<?php echo $parent['status'] == 'active' ? 'success' : 'secondary'; ?> me-2">
                                                <?php echo ucfirst($parent['status']); ?>
                                            </span>
                                            <small class="text-muted">
                                                <i class="fas fa-calendar-plus me-1"></i>
                                                <?php echo date('M j, Y', strtotime($parent['created_at'])); ?>
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
                                        <?php if ($parent['email']): ?>
                                            <div class="mb-1 d-flex align-items-center">
                                                <i class="fas fa-envelope me-2 text-success" style="width: 16px;"></i>
                                                <small class="text-truncate" title="<?php echo htmlspecialchars($parent['email']); ?>">
                                                    <?php echo htmlspecialchars($parent['email']); ?>
                                                </small>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($parent['phone']): ?>
                                            <div class="mb-1 d-flex align-items-center">
                                                <i class="fas fa-phone me-2 text-info" style="width: 16px;"></i>
                                                <small><?php echo htmlspecialchars($parent['phone']); ?></small>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!$parent['email'] && !$parent['phone']): ?>
                                            <small class="text-muted fst-italic">
                                                <i class="fas fa-info-circle me-1"></i>No contact information available
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Children Section -->
                                <div class="mb-3">
                                    <h6 class="text-secondary mb-2">
                                        <i class="fas fa-child me-2"></i>Children
                                    </h6>
                                    <?php if ($parent['children_count'] > 0): ?>
                                        <div class="children-info bg-light rounded p-2">
                                            <div class="d-flex align-items-center mb-1">
                                                <span class="badge bg-primary me-2"><?php echo $parent['children_count']; ?></span>
                                                <small class="text-muted">Child<?php echo $parent['children_count'] != 1 ? 'ren' : ''; ?></small>
                                            </div>
                                            <?php if ($parent['children_names']): ?>
                                                <small class="text-muted d-block">
                                                    <?php echo htmlspecialchars($parent['children_names']); ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <small class="text-muted fst-italic">
                                            <i class="fas fa-info-circle me-1"></i>No children assigned
                                        </small>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- SMS Statistics -->
                                <div class="mb-3">
                                    <h6 class="text-secondary mb-2">
                                        <i class="fas fa-sms me-2"></i>Communication
                                    </h6>
                                    <div class="row g-2 text-center">
                                        <div class="col-6">
                                            <div class="communication-stat bg-info bg-opacity-10 rounded p-2">
                                                <div class="fw-bold text-info"><?php echo $parent['sms_count']; ?></div>
                                                <small class="text-muted">SMS Sent</small>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="communication-stat bg-success bg-opacity-10 rounded p-2">
                                                <div class="fw-bold text-success">
                                                    <?php echo $parent['phone'] ? '<i class="fas fa-check"></i>' : '<i class="fas fa-times"></i>'; ?>
                                                </div>
                                                <small class="text-muted">SMS Ready</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Quick Stats -->
                                <div class="mb-3">
                                    <div class="row g-2">
                                        <div class="col-12">
                                            <div class="d-flex align-items-center bg-light rounded p-2">
                                                <i class="fas fa-family me-2 text-success"></i>
                                                <div>
                                                    <small class="text-muted d-block">Family Status</small>
                                                    <span class="fw-bold"><?php echo $parent['children_count'] > 0 ? 'Connected' : 'Pending'; ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Card Footer with Actions -->
                            <div class="card-footer bg-transparent border-top-0 pt-0 pb-3 px-3">
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-outline-success btn-sm flex-fill" 
                                            onclick="editParent(<?php echo htmlspecialchars(json_encode($parent)); ?>)" 
                                            data-bs-toggle="tooltip" title="Edit Parent">
                                        <i class="fas fa-edit me-2"></i>Edit
                                    </button>
                                    <button type="button" class="btn btn-outline-danger btn-sm flex-fill" 
                                            onclick="deleteParent(<?php echo $parent['id']; ?>, '<?php echo htmlspecialchars($parent['full_name']); ?>')" 
                                            data-bs-toggle="tooltip" title="Delete Parent">
                                        <i class="fas fa-trash me-2"></i>Delete
                                    </button>
                                </div>
                                
                                <!-- Additional Quick Actions -->
                                <div class="d-flex gap-1 mt-2">
                                    <?php if ($parent['children_count'] > 0): ?>
                                        <a href="students.php?parent_id=<?php echo $parent['id']; ?>" 
                                           class="btn btn-link btn-sm p-1 text-decoration-none" 
                                           data-bs-toggle="tooltip" title="View Children">
                                            <i class="fas fa-child"></i>
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($parent['email']): ?>
                                        <a href="mailto:<?php echo htmlspecialchars($parent['email']); ?>" 
                                           class="btn btn-link btn-sm p-1 text-decoration-none" 
                                           data-bs-toggle="tooltip" title="Send Email">
                                            <i class="fas fa-envelope"></i>
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($parent['phone']): ?>
                                        <a href="tel:<?php echo htmlspecialchars($parent['phone']); ?>" 
                                           class="btn btn-link btn-sm p-1 text-decoration-none" 
                                           data-bs-toggle="tooltip" title="Call">
                                            <i class="fas fa-phone"></i>
                                        </a>
                                    <?php endif; ?>
                                    <a href="attendance.php?parent_id=<?php echo $parent['id']; ?>" 
                                       class="btn btn-link btn-sm p-1 text-decoration-none" 
                                       data-bs-toggle="tooltip" title="View Children's Attendance">
                                        <i class="fas fa-calendar-check"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="d-flex justify-content-center mt-4">
                    <nav aria-label="Parents pagination">
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
                        <?php echo $total_records; ?> parents
                    </small>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Add Parent Modal -->
<div class="modal fade" id="addParentModal" tabindex="-1" aria-labelledby="addParentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="addParentModalLabel">
                    <i class="fas fa-plus me-2"></i>Add New Parent
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_parent">
                    
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
                            <label for="add_email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="add_email" name="email" 
                                   placeholder="Enter email address">
                            <div class="form-text">Optional but recommended</div>
                        </div>
                        
                        <div class="col-12 col-md-6">
                            <label for="add_phone" class="form-label">Phone Number</label>
                            <input type="text" class="form-control" id="add_phone" name="phone" 
                                   placeholder="09XXXXXXXXX or +639XXXXXXXXX">
                            <div class="form-text">Required for SMS notifications</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-plus me-2"></i>Add Parent
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Parent Modal -->
<div class="modal fade" id="editParentModal" tabindex="-1" aria-labelledby="editParentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title" id="editParentModalLabel">
                    <i class="fas fa-edit me-2"></i>Edit Parent
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_parent">
                    <input type="hidden" name="parent_id" id="edit_parent_id">
                    
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
                        <i class="fas fa-save me-2"></i>Update Parent
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Parent Modal -->
<div class="modal fade" id="deleteParentModal" tabindex="-1" aria-labelledby="deleteParentModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteParentModalLabel">
                    <i class="fas fa-trash me-2"></i>Delete Parent
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="delete_parent">
                    <input type="hidden" name="parent_id" id="delete_parent_id">
                    
                    <div class="text-center">
                        <i class="fas fa-exclamation-triangle fa-4x text-warning mb-3"></i>
                        <h5>Are you sure?</h5>
                        <p class="text-muted">
                            You are about to delete parent <strong id="delete_parent_name"></strong>.
                            This action cannot be undone.
                        </p>
                        <div class="alert alert-warning">
                            <i class="fas fa-info-circle me-2"></i>
                            If the parent has SMS records, they will be marked as inactive instead of deleted to preserve data integrity.
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash me-2"></i>Delete Parent
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Edit parent function
function editParent(parent) {
    document.getElementById('edit_parent_id').value = parent.id;
    document.getElementById('edit_username').value = parent.username;
    document.getElementById('edit_full_name').value = parent.full_name;
    document.getElementById('edit_email').value = parent.email || '';
    document.getElementById('edit_phone').value = parent.phone || '';
    document.getElementById('edit_status').value = parent.status;
    
    const editModal = new bootstrap.Modal(document.getElementById('editParentModal'));
    editModal.show();
}

// Enhanced delete parent function with confirmation animation
function deleteParent(parentId, parentName) {
    // Add shake animation to the card being deleted
    const parentCards = document.querySelectorAll('.parent-card');
    parentCards.forEach(card => {
        const cardButtons = card.querySelectorAll('button');
        cardButtons.forEach(button => {
            if (button.onclick && button.onclick.toString().includes(parentId)) {
                card.style.animation = 'shake 0.5s ease-in-out';
            }
        });
    });
    
    document.getElementById('delete_parent_id').value = parentId;
    document.getElementById('delete_parent_name').textContent = parentName;
    
    const deleteModal = new bootstrap.Modal(document.getElementById('deleteParentModal'));
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

// Initialize tooltips
document.addEventListener('DOMContentLoaded', function() {
    var tooltips = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltips.map(function(el) {
        return new bootstrap.Tooltip(el);
    });
    
    // Add staggered animation to cards
    const cards = document.querySelectorAll('.parent-card');
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
</script>

<style>
/* Mobile-friendly styles */
@media (max-width: 576px) {
    .card-body {
        padding: 0.75rem !important;
    }
    
    .parent-card {
        margin-bottom: 1rem;
    }
    
    .profile-avatar {
        width: 45px !important;
        height: 45px !important;
        font-size: 1rem !important;
    }
    
    .communication-stat {
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

/* Parent Card Styles */
.parent-card {
    transition: all 0.3s ease-in-out;
    border: 1px solid rgba(0,0,0,0.08) !important;
}

.parent-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(40,167,69,0.15) !important;
    border-color: rgba(40,167,69,0.2) !important;
}

/* Profile Avatar */
.profile-avatar {
    transition: all 0.3s ease-in-out;
    border: 3px solid rgba(255,255,255,0.9);
}

.parent-card:hover .profile-avatar {
    transform: scale(1.05);
    box-shadow: 0 4px 15px rgba(40,167,69,0.4) !important;
}

/* Communication Stats */
.communication-stat {
    transition: all 0.2s ease-in-out;
    border: 1px solid transparent;
}

.communication-stat:hover {
    transform: translateY(-2px);
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

/* Contact Info */
.contact-info {
    background: rgba(248,249,250,0.8);
    border-radius: 6px;
    padding: 0.5rem;
    border-left: 3px solid #28a745;
}

/* Children Info */
.children-info {
    background: rgba(13,110,253,0.05) !important;
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
.btn-outline-success,
.btn-outline-danger {
    transition: all 0.2s ease-in-out;
    border-width: 1.5px;
}

.btn-outline-success:hover {
    transform: translateY(-1px);
    box-shadow: 0 3px 8px rgba(40,167,69,0.3);
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
    background-color: rgba(40,167,69,0.1) !important;
    transform: scale(1.1);
}

/* Card Sections */
.parent-card .card-body {
    background: linear-gradient(145deg, #ffffff 0%, #fafbfc 100%);
}

.parent-card .card-footer {
    background: rgba(248,249,250,0.7) !important;
    border-top: 1px solid rgba(40,167,69,0.1) !important;
}

/* Loading Animation for Cards */
.parent-card {
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
    color: #28a745;
}

.pagination .page-item.active .page-link {
    background: linear-gradient(45deg, #28a745, #20c997);
    border-color: #28a745;
    box-shadow: 0 2px 4px rgba(40,167,69,0.3);
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

/* Quick Stats Hover Effects */
.bg-light:hover {
    background-color: rgba(40,167,69,0.05) !important;
    border-left: 3px solid #28a745;
    transform: translateX(2px);
}
</style>

<?php include 'footer.php'; ?>