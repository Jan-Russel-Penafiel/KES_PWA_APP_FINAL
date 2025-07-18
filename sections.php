<?php
require_once 'config.php';

// Check if user is logged in and is admin or teacher
if (!isLoggedIn() || ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'teacher')) {
    redirect('login.php');
}

$current_user = getCurrentUser($pdo);
$user_role = $_SESSION['role']; // Get user role for conditional checks

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        // Add new section
        if ($_POST['action'] == 'add') {
            $section_name = trim($_POST['section_name']);
            $teacher_id = $_POST['teacher_id'];
            $grade_level = $_POST['grade_level'];
            
            try {
                $stmt = $pdo->prepare("INSERT INTO sections (section_name, teacher_id, grade_level, status) VALUES (?, ?, ?, 'active')");
                $stmt->execute([$section_name, $teacher_id, $grade_level]);
                
                $_SESSION['success'] = "Section added successfully.";
                redirect('sections.php');
            } catch (PDOException $e) {
                $_SESSION['error'] = "Error adding section: " . $e->getMessage();
            }
        }
        
        // Edit section
        else if ($_POST['action'] == 'edit') {
            $section_id = $_POST['section_id'];
            $section_name = trim($_POST['section_name']);
            $teacher_id = $_POST['teacher_id'];
            $grade_level = $_POST['grade_level'];
            $status = $_POST['status'];
            
            try {
                $stmt = $pdo->prepare("UPDATE sections SET section_name = ?, teacher_id = ?, grade_level = ?, status = ? WHERE id = ?");
                $stmt->execute([$section_name, $teacher_id, $grade_level, $status, $section_id]);
                
                $_SESSION['success'] = "Section updated successfully.";
                redirect('sections.php');
            } catch (PDOException $e) {
                $_SESSION['error'] = "Error updating section: " . $e->getMessage();
            }
        }
        
        // Delete section
        else if ($_POST['action'] == 'delete') {
            $section_id = $_POST['section_id'];
            
            try {
                // Check if section has students
                $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE section_id = ? AND status = 'active'");
                $check_stmt->execute([$section_id]);
                $student_count = $check_stmt->fetchColumn();
                
                if ($student_count > 0) {
                    $_SESSION['error'] = "Cannot delete section with active students. Please reassign students first.";
                } else {
                    // Set section status to inactive instead of deleting
                    $stmt = $pdo->prepare("UPDATE sections SET status = 'inactive' WHERE id = ?");
                    $stmt->execute([$section_id]);
                    
                    $_SESSION['success'] = "Section deleted successfully.";
                }
                redirect('sections.php');
            } catch (PDOException $e) {
                $_SESSION['error'] = "Error deleting section: " . $e->getMessage();
            }
        }
    }
}

// Get all teachers for dropdown
try {
    $teacher_stmt = $pdo->query("SELECT id, full_name FROM users WHERE role = 'teacher' AND status = 'active' ORDER BY full_name");
    $teachers = $teacher_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $teachers = [];
}

// Get sections based on user role
try {
    if ($user_role == 'admin') {
        // Admin sees all sections
        $section_stmt = $pdo->query("
            SELECT s.*, u.full_name as teacher_name 
            FROM sections s 
            LEFT JOIN users u ON s.teacher_id = u.id 
            ORDER BY s.grade_level ASC, s.section_name ASC
        ");
        $sections = $section_stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Teacher only sees their own sections
        $section_stmt = $pdo->prepare("
            SELECT s.*, u.full_name as teacher_name 
            FROM sections s 
            LEFT JOIN users u ON s.teacher_id = u.id 
            WHERE s.teacher_id = ?
            ORDER BY s.grade_level ASC, s.section_name ASC
        ");
        $section_stmt->execute([$current_user['id']]);
        $sections = $section_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $sections = [];
}

$page_title = 'Manage Sections';
// Add DataTables CSS
$additional_css = '
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
<style>
    .section-card .card {
        transition: all 0.3s ease;
        overflow: hidden;
    }
    
    .section-card .card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important;
    }
    
    .section-card .card-header {
        position: relative;
        overflow: hidden;
    }
    
    .section-card .card-header::before {
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
    
    .section-card .card:hover .card-header::before {
        transform: translateX(100%);
    }
    
    .filter-btn.active {
        background-color: var(--primary-color);
        color: white;
        border-color: var(--primary-color);
    }
</style>
';

// Add custom scripts to prevent jQuery loading twice
$additional_scripts = '
<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    // Search functionality
    $("#sectionSearch").on("keyup", function() {
        var value = $(this).val().toLowerCase();
        $("#sectionCards .section-card").filter(function() {
            var sectionName = $(this).find(".card-header h5").text().toLowerCase();
            var teacherName = $(this).find(".card-body h6").first().text().toLowerCase();
            var gradeLevel = $(this).find(".card-header .badge").text().toLowerCase();
            
            return (sectionName.indexOf(value) > -1 || 
                   teacherName.indexOf(value) > -1 || 
                   gradeLevel.indexOf(value) > -1);
        }).show();
        
        $("#sectionCards .section-card").filter(function() {
            var sectionName = $(this).find(".card-header h5").text().toLowerCase();
            var teacherName = $(this).find(".card-body h6").first().text().toLowerCase();
            var gradeLevel = $(this).find(".card-header .badge").text().toLowerCase();
            
            return !(sectionName.indexOf(value) > -1 || 
                    teacherName.indexOf(value) > -1 || 
                    gradeLevel.indexOf(value) > -1);
        }).hide();
    });
    
    // Filter functionality
    $(".filter-btn").on("click", function() {
        var filter = $(this).data("filter");
        
        // Update active button
        $(".filter-btn").removeClass("active");
        $(this).addClass("active");
        
        if (filter === "all") {
            $("#sectionCards .section-card").show();
        } else {
            $("#sectionCards .section-card").hide();
            $("#sectionCards .section-card[data-status=\'" + filter + "\']").show();
        }
    });
    
    // Edit Section
    $(".edit-section").on("click", function() {
        const id = $(this).data("id");
        const name = $(this).data("name");
        const teacher = $(this).data("teacher");
        const grade = $(this).data("grade");
        const status = $(this).data("status");
        
        $("#edit_section_id").val(id);
        $("#edit_section_name").val(name);
        $("#edit_grade_level").val(grade);
        $("#edit_teacher_id").val(teacher);
        $("#edit_status").val(status);
        
        const editModal = new bootstrap.Modal(document.getElementById("editSectionModal"));
        editModal.show();
    });
    
    // Delete Section
    $(".delete-section").on("click", function() {
        const id = $(this).data("id");
        const name = $(this).data("name");
        
        $("#delete_section_id").val(id);
        $("#delete_section_name").text(name);
        
        const deleteModal = new bootstrap.Modal(document.getElementById("deleteSectionModal"));
        deleteModal.show();
    });
    
    // Animation for cards
    $("#sectionCards .card").each(function(index) {
        const card = $(this);
        setTimeout(function() {
            card.addClass("animate__animated animate__fadeInUp");
        }, index * 100);
    });
});
</script>
';

include 'header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 fw-bold text-primary">
                    <i class="fas fa-school me-2"></i>Manage Sections
                </h1>
                <p class="text-muted mb-0">
                    <?php if ($user_role == 'admin'): ?>
                    Add, edit, or delete class sections
                    <?php else: ?>
                    View your assigned sections
                    <?php endif; ?>
                </p>
            </div>
            <?php if ($user_role == 'admin'): ?>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSectionModal">
                <i class="fas fa-plus me-1"></i> Add New Section
            </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Alert Messages -->
<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?php 
        echo $_SESSION['success']; 
        unset($_SESSION['success']);
        ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php 
        echo $_SESSION['error']; 
        unset($_SESSION['error']);
        ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- Sections Table -->
<div class="card">
    <div class="card-body">
        <?php if (empty($sections)): ?>
            <div class="text-center py-5">
                <i class="fas fa-school fa-3x text-muted mb-3"></i>
                <p class="text-muted">
                    <?php if ($user_role == 'admin'): ?>
                    No sections found. Click "Add New Section" to create one.
                    <?php else: ?>
                    You have no assigned sections.
                    <?php endif; ?>
                </p>
            </div>
        <?php else: ?>
            <!-- Search and Filter -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-0">
                            <i class="fas fa-search text-muted"></i>
                        </span>
                        <input type="text" id="sectionSearch" class="form-control border-0 bg-light" placeholder="Search sections...">
                    </div>
                </div>
                <div class="col-md-6 text-md-end mt-3 mt-md-0">
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-outline-secondary filter-btn active" data-filter="all">All</button>
                        <button type="button" class="btn btn-outline-secondary filter-btn" data-filter="active">Active</button>
                        <button type="button" class="btn btn-outline-secondary filter-btn" data-filter="inactive">Inactive</button>
                    </div>
                </div>
            </div>
            
            <!-- Section Cards -->
            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4" id="sectionCards">
                <?php foreach ($sections as $section): ?>
                    <?php 
                    // Count students in this section
                    try {
                        $student_count_stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE section_id = ? AND role = 'student' AND status = 'active'");
                        $student_count_stmt->execute([$section['id']]);
                        $student_count = $student_count_stmt->fetchColumn();
                    } catch (PDOException $e) {
                        $student_count = 0;
                    }
                    
                    // Determine card color based on grade level
                    $grade_num = intval(str_replace('Grade ', '', $section['grade_level']));
                    $colors = ['primary', 'success', 'info', 'warning', 'danger', 'secondary'];
                    $color_index = ($grade_num - 7) % count($colors);
                    $card_color = $colors[$color_index];
                    ?>
                    <div class="col section-card" data-status="<?php echo $section['status']; ?>">
                        <div class="card h-100 border-0 shadow-sm">
                            <div class="card-header bg-<?php echo $card_color; ?> text-white">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0"><?php echo htmlspecialchars($section['section_name']); ?></h5>
                                    <span class="badge bg-light text-dark"><?php echo htmlspecialchars($section['grade_level']); ?></span>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="d-flex justify-content-between mb-3">
                                    <div>
                                        <p class="text-muted mb-1">Teacher</p>
                                        <h6><?php echo htmlspecialchars($section['teacher_name'] ?? 'Not Assigned'); ?></h6>
                                    </div>
                                    <div class="text-end">
                                        <p class="text-muted mb-1">Students</p>
                                        <h6>
                                            <a href="students.php?section_id=<?php echo $section['id']; ?>" class="text-decoration-none">
                                                <i class="fas fa-users me-1"></i> <?php echo $student_count; ?>
                                            </a>
                                        </h6>
                                    </div>
                                </div>
                                
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="badge bg-<?php echo $section['status'] == 'active' ? 'success' : 'danger'; ?>">
                                        <?php echo ucfirst($section['status']); ?>
                                    </span>
                                    <div>
                                        <?php if ($user_role == 'admin'): ?>
                                        <button type="button" class="btn btn-sm btn-outline-primary edit-section" 
                                                data-id="<?php echo $section['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($section['section_name']); ?>"
                                                data-teacher="<?php echo $section['teacher_id']; ?>"
                                                data-grade="<?php echo htmlspecialchars($section['grade_level']); ?>"
                                                data-status="<?php echo $section['status']; ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger delete-section"
                                                data-id="<?php echo $section['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($section['section_name']); ?>">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        <?php else: ?>
                                        <a href="students.php?section_id=<?php echo $section['id']; ?>" class="btn btn-sm btn-outline-info">
                                            <i class="fas fa-users"></i> View Students
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add Section Modal -->
<div class="modal fade" id="addSectionModal" tabindex="-1" aria-labelledby="addSectionModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="addSectionModalLabel">Add New Section</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="sections.php" method="post">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label for="section_name" class="form-label">Section Name</label>
                        <input type="text" class="form-control" id="section_name" name="section_name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="grade_level" class="form-label">Grade Level</label>
                        <select class="form-select" id="grade_level" name="grade_level" required>
                            <option value="">Select Grade Level</option>
                            <?php for ($i = 7; $i <= 12; $i++): ?>
                                <option value="Grade <?php echo $i; ?>">Grade <?php echo $i; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="teacher_id" class="form-label">Teacher</label>
                        <select class="form-select" id="teacher_id" name="teacher_id">
                            <option value="">Select Teacher</option>
                            <?php foreach ($teachers as $teacher): ?>
                                <option value="<?php echo $teacher['id']; ?>">
                                    <?php echo htmlspecialchars($teacher['full_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Section</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Section Modal -->
<div class="modal fade" id="editSectionModal" tabindex="-1" aria-labelledby="editSectionModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="editSectionModalLabel">Edit Section</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="sections.php" method="post">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="section_id" id="edit_section_id">
                    
                    <div class="mb-3">
                        <label for="edit_section_name" class="form-label">Section Name</label>
                        <input type="text" class="form-control" id="edit_section_name" name="section_name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_grade_level" class="form-label">Grade Level</label>
                        <select class="form-select" id="edit_grade_level" name="grade_level" required>
                            <option value="">Select Grade Level</option>
                            <?php for ($i = 7; $i <= 12; $i++): ?>
                                <option value="Grade <?php echo $i; ?>">Grade <?php echo $i; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_teacher_id" class="form-label">Teacher</label>
                        <select class="form-select" id="edit_teacher_id" name="teacher_id">
                            <option value="">Select Teacher</option>
                            <?php foreach ($teachers as $teacher): ?>
                                <option value="<?php echo $teacher['id']; ?>">
                                    <?php echo htmlspecialchars($teacher['full_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_status" class="form-label">Status</label>
                        <select class="form-select" id="edit_status" name="status" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Section Modal -->
<div class="modal fade" id="deleteSectionModal" tabindex="-1" aria-labelledby="deleteSectionModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteSectionModalLabel">Delete Section</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete the section: <span id="delete_section_name" class="fw-bold"></span>?</p>
                <p class="text-danger mb-0">
                    <i class="fas fa-exclamation-triangle me-1"></i>
                    This action cannot be undone. If students are assigned to this section, the deletion will fail.
                </p>
            </div>
            <form action="sections.php" method="post">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="section_id" id="delete_section_id">
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete Section</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?> 