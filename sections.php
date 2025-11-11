<?php
require_once 'config.php';

// Check if user is logged in and is admin or teacher
if (!isLoggedIn() || ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'teacher')) {
    redirect('login.php');
}

// Handle AJAX requests for grade level filtering
if (isset($_GET['ajax']) && $_GET['ajax'] == 'get_by_grade') {
    header('Content-Type: application/json');
    
    $grade_level = $_GET['grade_level'] ?? '';
    
    if (empty($grade_level)) {
        echo json_encode(['teachers' => [], 'sections' => []]);
        exit;
    }
    
    try {
        // Get teachers who teach in this grade level (from subjects table)
        $teacher_stmt = $pdo->prepare("
            SELECT DISTINCT u.id, u.full_name 
            FROM users u
            INNER JOIN subjects s ON u.id = s.teacher_id
            WHERE u.role = 'teacher' AND u.status = 'active' 
            AND (s.grade_level = ? OR s.grade_level = ?)
            ORDER BY u.full_name
        ");
        $teacher_stmt->execute([$grade_level, str_replace('Grade ', '', $grade_level)]);
        $teachers = $teacher_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Also get teachers from sections table
        $section_teacher_stmt = $pdo->prepare("
            SELECT DISTINCT u.id, u.full_name 
            FROM users u
            INNER JOIN sections s ON u.id = s.teacher_id
            WHERE u.role = 'teacher' AND u.status = 'active' 
            AND (s.grade_level = ? OR s.grade_level = ?)
            ORDER BY u.full_name
        ");
        $section_teacher_stmt->execute([$grade_level, str_replace('Grade ', '', $grade_level)]);
        $section_teachers = $section_teacher_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Merge and remove duplicates
        $all_teachers = array_merge($teachers, $section_teachers);
        $unique_teachers = [];
        $teacher_ids = [];
        
        foreach ($all_teachers as $teacher) {
            if (!in_array($teacher['id'], $teacher_ids)) {
                $unique_teachers[] = $teacher;
                $teacher_ids[] = $teacher['id'];
            }
        }
        
        // Get sections for this grade level
        $section_stmt = $pdo->prepare("
            SELECT s.id, s.section_name, s.grade_level, s.teacher_id, u.full_name as teacher_name
            FROM sections s 
            LEFT JOIN users u ON s.teacher_id = u.id 
            WHERE s.status = 'active' 
            AND (s.grade_level = ? OR s.grade_level = ?)
            ORDER BY s.section_name ASC
        ");
        $section_stmt->execute([$grade_level, str_replace('Grade ', '', $grade_level)]);
        $sections = $section_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'teachers' => $unique_teachers,
            'sections' => $sections
        ]);
        exit;
        
    } catch (PDOException $e) {
        echo json_encode(['teachers' => [], 'sections' => [], 'error' => $e->getMessage()]);
        exit;
    }
}

$current_user = getCurrentUser($pdo);
$user_role = $_SESSION['role']; // Get user role for conditional checks

// Determine view mode (sections or subjects)
$view_mode = isset($_GET['view']) ? $_GET['view'] : 'subjects'; // Default to subjects
if (!in_array($view_mode, ['sections', 'subjects'])) {
    $view_mode = 'subjects';
}

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        // Section actions
        if ($_POST['action'] == 'add_section') {
            $section_name = trim($_POST['section_name']);
            $grade_level = $_POST['grade_level'];
            $teacher_id = ($user_role == 'admin' && !empty($_POST['teacher_id'])) ? $_POST['teacher_id'] : $current_user['id'];
            $description = trim($_POST['description']);
            
            try {
                // For teachers, they can only create sections for themselves
                if ($user_role == 'teacher') {
                    $teacher_id = $current_user['id']; // Force teacher to be themselves
                }
                
                // Validate teacher_id if provided (admin only)
                if ($user_role == 'admin' && $teacher_id !== null) {
                    $teacher_check = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'teacher' AND status = 'active'");
                    $teacher_check->execute([$teacher_id]);
                    if (!$teacher_check->fetch()) {
                        $_SESSION['error'] = "Invalid teacher selected. Please choose a valid teacher.";
                        redirect('sections.php?view=sections');
                        exit;
                    }
                }
                
                $stmt = $pdo->prepare("INSERT INTO sections (section_name, grade_level, teacher_id, description, status) VALUES (?, ?, ?, ?, 'active')");
                $stmt->execute([$section_name, $grade_level, $teacher_id, $description]);
                
                $_SESSION['success'] = "Section added successfully.";
                redirect('sections.php?view=sections');
            } catch (PDOException $e) {
                $_SESSION['error'] = "Error adding section: " . $e->getMessage();
            }
        }
        
        // Edit section
        else if ($_POST['action'] == 'edit_section') {
            $section_id = $_POST['section_id'];
            $section_name = trim($_POST['section_name']);
            $grade_level = $_POST['grade_level'];
            $teacher_id = ($user_role == 'admin' && !empty($_POST['teacher_id'])) ? $_POST['teacher_id'] : $current_user['id'];
            $description = trim($_POST['description']);
            $status = $_POST['status'];
            
            try {
                // For teachers, validate they can only edit their own sections
                if ($user_role == 'teacher') {
                    $teacher_id = $current_user['id']; // Force teacher to be themselves
                    $section_check = $pdo->prepare("SELECT id FROM sections WHERE id = ? AND teacher_id = ?");
                    $section_check->execute([$section_id, $current_user['id']]);
                    if (!$section_check->fetch()) {
                        $_SESSION['error'] = "You can only edit sections you manage.";
                        redirect('sections.php?view=sections');
                        exit;
                    }
                }
                
                // Validate teacher_id if provided (admin only)
                if ($user_role == 'admin' && $teacher_id !== null) {
                    $teacher_check = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'teacher' AND status = 'active'");
                    $teacher_check->execute([$teacher_id]);
                    if (!$teacher_check->fetch()) {
                        $_SESSION['error'] = "Invalid teacher selected. Please choose a valid teacher.";
                        redirect('sections.php?view=sections');
                        exit;
                    }
                }
                
                $stmt = $pdo->prepare("UPDATE sections SET section_name = ?, grade_level = ?, teacher_id = ?, description = ?, status = ? WHERE id = ?");
                $stmt->execute([$section_name, $grade_level, $teacher_id, $description, $status, $section_id]);
                
                $_SESSION['success'] = "Section updated successfully.";
                redirect('sections.php?view=sections');
            } catch (PDOException $e) {
                $_SESSION['error'] = "Error updating section: " . $e->getMessage();
            }
        }
        
        // Delete section
        else if ($_POST['action'] == 'delete_section') {
            $section_id = $_POST['section_id'];
            
            try {
                // For teachers, validate they can only delete their own sections
                if ($user_role == 'teacher') {
                    $section_check = $pdo->prepare("SELECT id FROM sections WHERE id = ? AND teacher_id = ?");
                    $section_check->execute([$section_id, $current_user['id']]);
                    if (!$section_check->fetch()) {
                        $_SESSION['error'] = "You can only delete sections you manage.";
                        redirect('sections.php?view=sections');
                        exit;
                    }
                }
                
                // Start transaction for data integrity
                $pdo->beginTransaction();
                
                // Check if section has students assigned
                $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE section_id = ? AND role = 'student' AND status = 'active'");
                $check_stmt->execute([$section_id]);
                $student_count = $check_stmt->fetchColumn();
                
                if ($student_count > 0) {
                    $pdo->rollBack();
                    $_SESSION['error'] = "Cannot delete section with assigned students. Please reassign students first.";
                } else {
                    // Permanent deletion: Delete all related data first for data integrity
                    
                    // 1. Delete all attendance records for this section
                    $delete_attendance = $pdo->prepare("DELETE FROM attendance WHERE section_id = ?");
                    $delete_attendance->execute([$section_id]);
                    
                    // 2. Delete all student-subject relationships for subjects in this section
                    $delete_student_subjects = $pdo->prepare("DELETE FROM student_subjects WHERE subject_id IN (SELECT id FROM subjects WHERE section_id = ?)");
                    $delete_student_subjects->execute([$section_id]);
                    
                    // 3. Delete all subjects in this section
                    $delete_subjects = $pdo->prepare("DELETE FROM subjects WHERE section_id = ?");
                    $delete_subjects->execute([$section_id]);
                    
                    // 4. Finally delete the section itself
                    $delete_section = $pdo->prepare("DELETE FROM sections WHERE id = ?");
                    $delete_section->execute([$section_id]);
                    
                    $pdo->commit();
                    $_SESSION['success'] = "Section and all related data deleted permanently.";
                }
                redirect('sections.php?view=sections');
            } catch (PDOException $e) {
                $pdo->rollBack();
                $_SESSION['error'] = "Error deleting section: " . $e->getMessage();
                redirect('sections.php?view=sections');
            }
        }
        
        // Subject actions (enhanced for teachers)
        // Add new subject - Allow teachers to create subjects for their sections
        if ($_POST['action'] == 'add') {
            $subject_name = trim($_POST['subject_name']);
            $subject_code = trim($_POST['subject_code']);
            $teacher_id = ($user_role == 'admin' && !empty($_POST['teacher_id'])) ? $_POST['teacher_id'] : $current_user['id'];
            $section_id = !empty($_POST['section_id']) ? $_POST['section_id'] : null;
            $grade_level = $_POST['grade_level'];
            $description = trim($_POST['description']);
            $schedule = trim($_POST['schedule']);
            
            try {
                // For teachers, validate they can only assign subjects to their own sections
                if ($user_role == 'teacher' && $section_id) {
                    $section_check = $pdo->prepare("SELECT id FROM sections WHERE id = ? AND teacher_id = ?");
                    $section_check->execute([$section_id, $current_user['id']]);
                    if (!$section_check->fetch()) {
                        $_SESSION['error'] = "You can only create subjects for sections you manage.";
                        redirect('sections.php');
                        exit;
                    }
                }
                
                // Validate teacher_id if provided (admin only)
                if ($user_role == 'admin' && $teacher_id !== null) {
                    $teacher_check = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'teacher' AND status = 'active'");
                    $teacher_check->execute([$teacher_id]);
                    if (!$teacher_check->fetch()) {
                        $_SESSION['error'] = "Invalid teacher selected. Please choose a valid teacher.";
                        redirect('sections.php');
                        exit;
                    }
                }
                
                $stmt = $pdo->prepare("INSERT INTO subjects (subject_name, subject_code, teacher_id, section_id, grade_level, description, schedule, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'active')");
                $stmt->execute([$subject_name, $subject_code, $teacher_id, $section_id, $grade_level, $description, $schedule]);
                
                $_SESSION['success'] = "Subject added successfully.";
                redirect('sections.php');
            } catch (PDOException $e) {
                $_SESSION['error'] = "Error adding subject: " . $e->getMessage();
            }
        }
        
        // Edit subject - Allow teachers to edit their own subjects
        else if ($_POST['action'] == 'edit') {
            $subject_id = $_POST['subject_id'];
            $subject_name = trim($_POST['subject_name']);
            $subject_code = trim($_POST['subject_code']);
            $teacher_id = ($user_role == 'admin' && !empty($_POST['teacher_id'])) ? $_POST['teacher_id'] : $current_user['id'];
            $section_id = !empty($_POST['section_id']) ? $_POST['section_id'] : null;
            $grade_level = $_POST['grade_level'];
            $description = trim($_POST['description']);
            $schedule = trim($_POST['schedule']);
            $status = $_POST['status'];
            
            try {
                // For teachers, validate they can only edit their own subjects
                if ($user_role == 'teacher') {
                    $subject_check = $pdo->prepare("SELECT id FROM subjects WHERE id = ? AND teacher_id = ?");
                    $subject_check->execute([$subject_id, $current_user['id']]);
                    if (!$subject_check->fetch()) {
                        $_SESSION['error'] = "You can only edit subjects you teach.";
                        redirect('sections.php');
                        exit;
                    }
                    
                    // Also validate section assignment if provided
                    if ($section_id) {
                        $section_check = $pdo->prepare("SELECT id FROM sections WHERE id = ? AND teacher_id = ?");
                        $section_check->execute([$section_id, $current_user['id']]);
                        if (!$section_check->fetch()) {
                            $_SESSION['error'] = "You can only assign subjects to sections you manage.";
                            redirect('sections.php');
                            exit;
                        }
                    }
                }
                
                // Validate teacher_id if provided (admin only)
                if ($user_role == 'admin' && $teacher_id !== null) {
                    $teacher_check = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'teacher' AND status = 'active'");
                    $teacher_check->execute([$teacher_id]);
                    if (!$teacher_check->fetch()) {
                        $_SESSION['error'] = "Invalid teacher selected. Please choose a valid teacher.";
                        redirect('sections.php');
                        exit;
                    }
                }
                
                $stmt = $pdo->prepare("UPDATE subjects SET subject_name = ?, subject_code = ?, teacher_id = ?, section_id = ?, grade_level = ?, description = ?, schedule = ?, status = ? WHERE id = ?");
                $stmt->execute([$subject_name, $subject_code, $teacher_id, $section_id, $grade_level, $description, $schedule, $status, $subject_id]);
                
                $_SESSION['success'] = "Subject updated successfully.";
                redirect('sections.php');
            } catch (PDOException $e) {
                $_SESSION['error'] = "Error updating subject: " . $e->getMessage();
            }
        }
        
        // Delete subject - Allow teachers to delete their own subjects (with restrictions)
        else if ($_POST['action'] == 'delete') {
            $subject_id = $_POST['subject_id'];
            
            try {
                // For teachers, validate they can only delete their own subjects
                if ($user_role == 'teacher') {
                    $subject_check = $pdo->prepare("SELECT subject_name FROM subjects WHERE id = ? AND teacher_id = ?");
                    $subject_check->execute([$subject_id, $current_user['id']]);
                    $subject_data = $subject_check->fetch();
                    
                    if (!$subject_data) {
                        $_SESSION['error'] = "You can only delete subjects you teach.";
                        redirect('sections.php');
                        exit;
                    }
                    $subject_name = $subject_data['subject_name'];
                } else {
                    // Get subject info for the success message
                    $subject_info = $pdo->prepare("SELECT subject_name FROM subjects WHERE id = ?");
                    $subject_info->execute([$subject_id]);
                    $subject_name = $subject_info->fetchColumn();
                }
                
                // Start transaction for data integrity
                $pdo->beginTransaction();
                
                // Permanent deletion: Delete all related data first for data integrity
                
                // 1. Delete attendance records for this subject
                $delete_attendance = $pdo->prepare("DELETE FROM attendance WHERE subject_id = ?");
                $delete_attendance->execute([$subject_id]);
                
                // 2. Delete all student-subject relationships
                $delete_student_subjects = $pdo->prepare("DELETE FROM student_subjects WHERE subject_id = ?");
                $delete_student_subjects->execute([$subject_id]);
                
                // 3. Now delete the subject itself
                $delete_subject = $pdo->prepare("DELETE FROM subjects WHERE id = ?");
                $delete_subject->execute([$subject_id]);
                
                $pdo->commit();
                $_SESSION['success'] = "Subject '{$subject_name}' and all related data deleted permanently.";
                
                redirect('sections.php');
            } catch (PDOException $e) {
                $pdo->rollBack();
                $_SESSION['error'] = "Error deleting subject: " . $e->getMessage();
                redirect('sections.php');
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

// Get all sections for dropdown
try {
    $all_sections_stmt = $pdo->query("
        SELECT s.id, s.section_name, s.grade_level, s.teacher_id, u.full_name as teacher_name
        FROM sections s 
        LEFT JOIN users u ON s.teacher_id = u.id 
        WHERE s.status = 'active' 
        ORDER BY s.grade_level ASC, s.section_name ASC
    ");
    $all_sections = $all_sections_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $all_sections = [];
}

// Get subjects based on user role
try {
    if ($user_role == 'admin') {
        // Admin sees all subjects
        $subject_stmt = $pdo->query("
            SELECT s.*, u.full_name as teacher_name
            FROM subjects s 
            LEFT JOIN users u ON s.teacher_id = u.id 
            ORDER BY s.grade_level ASC, s.subject_name ASC
        ");
        $subjects = $subject_stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Teacher only sees their own subjects
        $subject_stmt = $pdo->prepare("
            SELECT s.*, u.full_name as teacher_name
            FROM subjects s 
            LEFT JOIN users u ON s.teacher_id = u.id
            WHERE s.teacher_id = ?
            ORDER BY s.grade_level ASC, s.subject_name ASC
        ");
        $subject_stmt->execute([$current_user['id']]);
        $subjects = $subject_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $subjects = [];
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
        // Teacher sees their own sections AND sections where they teach subjects
        $section_stmt = $pdo->prepare("
            SELECT DISTINCT s.*, u.full_name as teacher_name 
            FROM sections s 
            LEFT JOIN users u ON s.teacher_id = u.id 
            LEFT JOIN subjects subj ON s.id = subj.section_id
            WHERE s.teacher_id = ? OR subj.teacher_id = ?
            ORDER BY s.grade_level ASC, s.section_name ASC
        ");
        $section_stmt->execute([$current_user['id'], $current_user['id']]);
        $sections = $section_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $sections = [];
}

$page_title = $view_mode == 'sections' ? 'Manage Sections' : 'Manage Subjects';
// Add DataTables CSS
$additional_css = '
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
<style>
    .subject-card .card {
        transition: all 0.3s ease;
        overflow: hidden;
    }
    
    .subject-card .card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important;
    }
    
    .subject-card .card-header {
        position: relative;
        overflow: hidden;
    }
    
    .subject-card .card-header::before {
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
    
    .subject-card .card:hover .card-header::before {
        transform: translateX(100%);
    }
    
    .filter-btn.active {
        background-color: var(--primary-color);
        color: white;
        border-color: var(--primary-color);
    }
    
    .subject-card {
        margin-bottom: 1rem;
    }
    
    .subject-code {
        font-family: "Courier New", monospace;
        font-weight: bold;
        color: #6c757d;
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
        .btn-success {
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
    }
    
    @media (max-width: 768px) {
        .btn-group .btn {
            font-size: 0.9rem;
            padding: 0.6rem 1rem;
            min-height: 42px;
        }
        
        .btn-success {
            min-height: 42px;
        }
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
    // Search functionality for subjects
    $("#subjectSearch").on("keyup", function() {
        var value = $(this).val().toLowerCase();
        $("#subjectCards .subject-card").filter(function() {
            var subjectName = $(this).find(".card-header h5").text().toLowerCase();
            var teacherName = $(this).find(".card-body h6").first().text().toLowerCase();
            var gradeLevel = $(this).find(".card-header .badge").text().toLowerCase();
            var subjectCode = $(this).find(".subject-code").text().toLowerCase();
            
            return (subjectName.indexOf(value) > -1 || 
                   teacherName.indexOf(value) > -1 || 
                   gradeLevel.indexOf(value) > -1 ||
                   subjectCode.indexOf(value) > -1);
        }).show();
        
        $("#subjectCards .subject-card").filter(function() {
            var subjectName = $(this).find(".card-header h5").text().toLowerCase();
            var teacherName = $(this).find(".card-body h6").first().text().toLowerCase();
            var gradeLevel = $(this).find(".card-header .badge").text().toLowerCase();
            var subjectCode = $(this).find(".subject-code").text().toLowerCase();
            
            return !(subjectName.indexOf(value) > -1 || 
                    teacherName.indexOf(value) > -1 || 
                    gradeLevel.indexOf(value) > -1 ||
                    subjectCode.indexOf(value) > -1);
        }).hide();
    });
    
    // Search functionality for sections
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
            $("#subjectCards .subject-card, #sectionCards .section-card").show();
        } else {
            $("#subjectCards .subject-card, #sectionCards .section-card").hide();
            $("#subjectCards .subject-card[data-status=\'" + filter + "\'], #sectionCards .section-card[data-status=\'" + filter + "\']").show();
        }
    });
    
    // Auto-select teacher when section is selected in Add Subject modal
    $("#section_id").on("change", function() {
        var selectedOption = $(this).find("option:selected");
        var teacherId = selectedOption.data("teacher-id");
        
        if (teacherId) {
            $("#teacher_id").val(teacherId);
        } else {
            $("#teacher_id").val("");
        }
    });
    
    // Auto-select teacher when section is selected in Edit Subject modal
    $("#edit_section_id").on("change", function() {
        var selectedOption = $(this).find("option:selected");
        var teacherId = selectedOption.data("teacher-id");
        
        if (teacherId) {
            $("#edit_teacher_id").val(teacherId);
        } else {
            $("#edit_teacher_id").val("");
        }
    });
    
    // Grade Level change handler for Add Subject modal
    $("#grade_level").on("change", function() {
        var gradeLevel = $(this).val();
        
        if (gradeLevel) {
            // Show loading indicators
            $("#teacher_id").html(\'<option value="">Loading teachers...</option>\');
            $("#section_id").html(\'<option value="">Loading sections...</option>\');
            
            // Make AJAX call to get teachers and sections for this grade level
            $.ajax({
                url: \'sections.php\',
                method: \'GET\',
                data: {
                    ajax: \'get_by_grade\',
                    grade_level: gradeLevel
                },
                dataType: \'json\',
                success: function(response) {
                    // Populate teachers dropdown
                    var teacherOptions = \'<option value="">Select Teacher</option>\';
                    $.each(response.teachers, function(index, teacher) {
                        teacherOptions += \'<option value="\' + teacher.id + \'">\' + teacher.full_name + \'</option>\';
                    });
                    $("#teacher_id").html(teacherOptions);
                    
                    // Populate sections dropdown
                    var sectionOptions = \'<option value="">Select Section</option>\';
                    $.each(response.sections, function(index, section) {
                        var teacherInfo = section.teacher_name ? \' (\' + section.teacher_name + \')\' : \'\';
                        sectionOptions += \'<option value="\' + section.id + \'" data-teacher-id="\' + (section.teacher_id || \'\') + \'">\' + 
                                         section.section_name + \' - \' + section.grade_level + teacherInfo + \'</option>\';
                    });
                    $("#section_id").html(sectionOptions);
                },
                error: function() {
                    $("#teacher_id").html(\'<option value="">Select Teacher</option>\');
                    $("#section_id").html(\'<option value="">Select Section</option>\');
                    alert(\'Error loading data for the selected grade level.\');
                }
            });
        } else {
            // Reset dropdowns to show all options
            $("#teacher_id").html(\'<option value="">Select Teacher</option><?php foreach ($teachers as $teacher): ?><option value="<?php echo $teacher[\\\'id\\\']; ?>"><?php echo htmlspecialchars($teacher[\\\'full_name\\\']); ?></option><?php endforeach; ?>\');
            $("#section_id").html(\'<option value="">Select Section</option><?php foreach ($all_sections as $section): ?><option value="<?php echo $section[\\\'id\\\']; ?>" data-teacher-id="<?php echo $section[\\\'teacher_id\\\'] ?? \\\'\\\'; ?>"><?php echo htmlspecialchars($section[\\\'section_name\\\'] . \\\' - \\\' . $section[\\\'grade_level\\\']); ?><?php if (!empty($section[\\\'teacher_name\\\'])): ?> (<?php echo htmlspecialchars($section[\\\'teacher_name\\\']); ?>)<?php endif; ?></option><?php endforeach; ?>\');
        }
    });
    
    // Grade Level change handler for Edit Subject modal
    $("#edit_grade_level").on("change", function() {
        var gradeLevel = $(this).val();
        
        if (gradeLevel) {
            // Show loading indicators
            $("#edit_teacher_id").html(\'<option value="">Loading teachers...</option>\');
            $("#edit_section_id").html(\'<option value="">Loading sections...</option>\');
            
            // Make AJAX call to get teachers and sections for this grade level
            $.ajax({
                url: \'sections.php\',
                method: \'GET\',
                data: {
                    ajax: \'get_by_grade\',
                    grade_level: gradeLevel
                },
                dataType: \'json\',
                success: function(response) {
                    // Populate teachers dropdown
                    var teacherOptions = \'<option value="">Select Teacher</option>\';
                    $.each(response.teachers, function(index, teacher) {
                        teacherOptions += \'<option value="\' + teacher.id + \'">\' + teacher.full_name + \'</option>\';
                    });
                    $("#edit_teacher_id").html(teacherOptions);
                    
                    // Populate sections dropdown
                    var sectionOptions = \'<option value="">Select Section</option>\';
                    $.each(response.sections, function(index, section) {
                        var teacherInfo = section.teacher_name ? \' (\' + section.teacher_name + \')\' : \'\';
                        sectionOptions += \'<option value="\' + section.id + \'" data-teacher-id="\' + (section.teacher_id || \'\') + \'">\' + 
                                         section.section_name + \' - \' + section.grade_level + teacherInfo + \'</option>\';
                    });
                    $("#edit_section_id").html(sectionOptions);
                },
                error: function() {
                    $("#edit_teacher_id").html(\'<option value="">Select Teacher</option>\');
                    $("#edit_section_id").html(\'<option value="">Select Section</option>\');
                    alert(\'Error loading data for the selected grade level.\');
                }
            });
        } else {
            // Reset dropdowns to show all options
            $("#edit_teacher_id").html(\'<option value="">Select Teacher</option><?php foreach ($teachers as $teacher): ?><option value="<?php echo $teacher[\\\'id\\\']; ?>"><?php echo htmlspecialchars($teacher[\\\'full_name\\\']); ?></option><?php endforeach; ?>\');
            $("#edit_section_id").html(\'<option value="">Select Section</option><?php foreach ($all_sections as $section): ?><option value="<?php echo $section[\\\'id\\\']; ?>" data-teacher-id="<?php echo $section[\\\'teacher_id\\\'] ?? \\\'\\\'; ?>"><?php echo htmlspecialchars($section[\\\'section_name\\\'] . \\\' - \\\' . $section[\\\'grade_level\\\']); ?><?php if (!empty($section[\\\'teacher_name\\\'])): ?> (<?php echo htmlspecialchars($section[\\\'teacher_name\\\']); ?>)<?php endif; ?></option><?php endforeach; ?>\');
        }
    });
    
    // Edit Subject
    $(".edit-subject").on("click", function() {
        const id = $(this).data("id");
        const name = $(this).data("name");
        const code = $(this).data("code");
        const teacher = $(this).data("teacher");
        const section = $(this).data("section");
        const grade = $(this).data("grade");
        const description = $(this).data("description");
        const schedule = $(this).data("schedule");
        const status = $(this).data("status");
        
        $("#edit_subject_id").val(id);
        $("#edit_subject_name").val(name);
        $("#edit_subject_code").val(code);
        $("#edit_grade_level").val(grade);
        $("#edit_teacher_id").val(teacher);
        $("#edit_section_id").val(section);
        $("#edit_description").val(description);
        $("#edit_schedule").val(schedule);
        $("#edit_status").val(status);
        
        const editModal = new bootstrap.Modal(document.getElementById("editSubjectModal"));
        editModal.show();
    });
    
    // Edit Section
    $(".edit-section").on("click", function() {
        const id = $(this).data("id");
        const name = $(this).data("name");
        const grade = $(this).data("grade");
        const teacher = $(this).data("teacher");
        const description = $(this).data("description");
        const status = $(this).data("status");
        
        $("#edit_section_id").val(id);
        $("#edit_section_name").val(name);
        $("#edit_section_grade_level").val(grade);
        $("#edit_section_teacher_id").val(teacher);
        $("#edit_section_description").val(description);
        $("#edit_section_status").val(status);
        
        const editModal = new bootstrap.Modal(document.getElementById("editSectionModal"));
        editModal.show();
    });
    
    // Delete Subject
    $(".delete-subject").on("click", function() {
        const id = $(this).data("id");
        const name = $(this).data("name");
        
        $("#delete_subject_id").val(id);
        $("#delete_subject_name").text(name);
        
        const deleteModal = new bootstrap.Modal(document.getElementById("deleteSubjectModal"));
        deleteModal.show();
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
    $("#subjectCards .card, #sectionCards .card").each(function(index) {
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
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
            <div class="flex-grow-1">
                <h1 class="h3 fw-bold text-primary">
                    <i class="fas fa-<?php echo $view_mode == 'sections' ? 'layer-group' : 'book'; ?> me-2"></i>
                    <?php echo $view_mode == 'sections' ? 'Manage Sections' : 'Manage Subjects'; ?>
                </h1>
                <p class="text-muted mb-0">
                    <?php if ($user_role == 'admin'): ?>
                        <?php if ($view_mode == 'sections'): ?>
                        Add, edit, or delete sections and assign teachers
                        <?php else: ?>
                        Add, edit, or delete subjects and assign teachers
                        <?php endif; ?>
                    <?php else: ?>
                        <?php if ($view_mode == 'sections'): ?>
                        View sections where you teach or serve as adviser
                        <?php else: ?>
                        Manage subjects you teach - add, edit, or archive your subjects
                        <?php endif; ?>
                    <?php endif; ?>
                </p>
            </div>
            <div class="d-flex flex-column flex-sm-row gap-2 w-100 w-md-auto">
                <!-- View Toggle Buttons -->
                <div class="btn-group w-100 w-sm-auto" role="group" aria-label="View Toggle">
                    <a href="sections.php?view=sections" class="btn btn-<?php echo $view_mode == 'sections' ? 'primary' : 'outline-primary'; ?> d-flex align-items-center justify-content-center">
                        <i class="fas fa-layer-group me-1"></i> 
                        <span>Sections</span>
                    </a>
                    <a href="sections.php?view=subjects" class="btn btn-<?php echo $view_mode == 'subjects' ? 'primary' : 'outline-primary'; ?> d-flex align-items-center justify-content-center">
                        <i class="fas fa-book me-1"></i> 
                        <span>Subjects</span>
                    </a>
                </div>
                
                <?php if ($user_role == 'admin' || $user_role == 'teacher'): ?>
                <?php if ($view_mode == 'sections'): ?>
                <button type="button" class="btn btn-success w-100 w-sm-auto" data-bs-toggle="modal" data-bs-target="#addSectionModal">
                    <i class="fas fa-plus me-1"></i> 
                    <span class="d-none d-sm-inline">Add New Section</span>
                    <span class="d-sm-none">Add Section</span>
                </button>
                <?php else: ?>
                <button type="button" class="btn btn-success w-100 w-sm-auto" data-bs-toggle="modal" data-bs-target="#addSubjectModal">
                    <i class="fas fa-plus me-1"></i> 
                    <span class="d-none d-sm-inline">Add New Subject</span>
                    <span class="d-sm-none">Add Subject</span>
                </button>
                <?php endif; ?>
                <?php endif; ?>
            </div>
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

<!-- Subjects Table -->
<div class="card">
    <div class="card-body">
        <?php if ($view_mode == 'sections'): ?>
            <!-- SECTIONS VIEW -->
            <?php if (empty($sections)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-layer-group fa-3x text-muted mb-3"></i>
                    <p class="text-muted">
                        <?php if ($user_role == 'admin'): ?>
                        No sections found. Click "Add New Section" to create one.
                        <?php else: ?>
                        You have no assigned sections.
                        <?php endif; ?>
                    </p>
                </div>
            <?php else: ?>
                <!-- Search and Filter for Sections -->
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
                        $grade_num = intval(str_replace(['Grade ', 'grade ', ' '], '', strtolower($section['grade_level'])));
                        $colors = ['primary', 'success', 'info', 'warning', 'danger', 'secondary'];
                        $color_index = ($grade_num - 7) % count($colors);
                        $card_color = $colors[max(0, $color_index)];
                        ?>
                        <div class="col section-card" data-status="<?php echo $section['status']; ?>">
                            <div class="card h-100 border-0 shadow-sm">
                                <div class="card-header bg-<?php echo $card_color; ?> text-white">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h5 class="mb-0"><?php echo htmlspecialchars($section['section_name']); ?></h5>
                                            <small class="text-light">Section</small>
                                        </div>
                                        <span class="badge bg-light text-dark"><?php echo htmlspecialchars($section['grade_level']); ?></span>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <p class="text-muted mb-1">Adviser</p>
                                        <h6><?php echo htmlspecialchars($section['teacher_name'] ?? 'Not Assigned'); ?></h6>
                                    </div>
                                    
                                    <?php if (!empty($section['description'])): ?>
                                    <div class="mb-3">
                                        <p class="text-muted mb-1">Description</p>
                                        <p class="small text-secondary"><?php echo htmlspecialchars(substr($section['description'], 0, 80)) . (strlen($section['description']) > 80 ? '...' : ''); ?></p>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <span class="badge bg-<?php echo $section['status'] == 'active' ? 'success' : 'danger'; ?>">
                                                <?php echo ucfirst($section['status']); ?>
                                            </span>
                                            <span class="badge bg-info ms-1">
                                                <i class="fas fa-users me-1"></i> <?php echo $student_count; ?> students
                                            </span>
                                        </div>
                                        <div>
                                            <?php if ($user_role == 'admin' || ($user_role == 'teacher' && $section['teacher_id'] == $current_user['id'])): ?>
                                            <button type="button" class="btn btn-sm btn-outline-primary edit-section" 
                                                    data-id="<?php echo $section['id']; ?>"
                                                    data-name="<?php echo htmlspecialchars($section['section_name']); ?>"
                                                    data-grade="<?php echo htmlspecialchars($section['grade_level']); ?>"
                                                    data-teacher="<?php echo $section['teacher_id']; ?>"
                                                    data-description="<?php echo htmlspecialchars($section['description']); ?>"
                                                    data-status="<?php echo $section['status']; ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-danger delete-section"
                                                    data-id="<?php echo $section['id']; ?>"
                                                    data-name="<?php echo htmlspecialchars($section['section_name']); ?>">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            <?php endif; ?>
                                            <a href="students.php?section_id=<?php echo $section['id']; ?>" class="btn btn-sm btn-outline-info">
                                                <i class="fas fa-users"></i> Students
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <!-- SUBJECTS VIEW -->
            <?php if (empty($subjects)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-book fa-3x text-muted mb-3"></i>
                    <p class="text-muted">
                        <?php if ($user_role == 'admin'): ?>
                        No subjects found. Click "Add New Subject" to create one.
                        <?php else: ?>
                        You have no assigned subjects.
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
                        <input type="text" id="subjectSearch" class="form-control border-0 bg-light" placeholder="Search subjects...">
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
            
            <!-- Subject Cards -->
            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4" id="subjectCards">
                <?php foreach ($subjects as $subject): ?>
                    <?php 
                    // Count students enrolled in this subject
                    try {
                        $student_count_stmt = $pdo->prepare("SELECT COUNT(*) FROM student_subjects WHERE subject_id = ? AND status = 'enrolled'");
                        $student_count_stmt->execute([$subject['id']]);
                        $student_count = $student_count_stmt->fetchColumn();
                    } catch (PDOException $e) {
                        $student_count = 0;
                    }
                    
                    // Determine card color based on grade level
                    $grade_num = intval(str_replace(['Grade ', 'grade '], '', strtolower($subject['grade_level'])));
                    $colors = ['primary', 'success', 'info', 'warning', 'danger', 'secondary'];
                    $color_index = ($grade_num - 7) % count($colors);
                    $card_color = $colors[max(0, $color_index)];
                    ?>
                    <div class="col subject-card" data-status="<?php echo $subject['status']; ?>">
                        <div class="card h-100 border-0 shadow-sm">
                            <div class="card-header bg-<?php echo $card_color; ?> text-white">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="mb-0"><?php echo htmlspecialchars($subject['subject_name']); ?></h5>
                                        <small class="subject-code text-light"><?php echo htmlspecialchars($subject['subject_code']); ?></small>
                                    </div>
                                    <span class="badge bg-light text-dark"><?php echo htmlspecialchars($subject['grade_level']); ?></span>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <p class="text-muted mb-1">Teacher</p>
                                    <h6><?php echo htmlspecialchars($subject['teacher_name'] ?? 'Not Assigned'); ?></h6>
                                </div>
                                
                                <?php if (!empty($subject['section_name'])): ?>
                                <div class="mb-3">
                                    <p class="text-muted mb-1">Section</p>
                                    <h6 class="text-info">
                                        <i class="fas fa-layer-group me-1"></i>
                                        <?php echo htmlspecialchars($subject['section_name']); ?>
                                        <small class="text-muted">(Grade <?php echo htmlspecialchars($subject['section_grade']); ?>)</small>
                                    </h6>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($subject['description'])): ?>
                                <div class="mb-3">
                                    <p class="text-muted mb-1">Description</p>
                                    <p class="small text-secondary"><?php echo htmlspecialchars(substr($subject['description'], 0, 80)) . (strlen($subject['description']) > 80 ? '...' : ''); ?></p>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($subject['schedule'])): ?>
                                <div class="mb-3">
                                    <p class="text-muted mb-1">Schedule</p>
                                    <p class="small text-info"><i class="fas fa-clock me-1"></i><?php echo htmlspecialchars($subject['schedule']); ?></p>
                                </div>
                                <?php endif; ?>
                                
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <span class="badge bg-<?php echo $subject['status'] == 'active' ? 'success' : 'danger'; ?>">
                                            <?php echo ucfirst($subject['status']); ?>
                                        </span>
                                        <span class="badge bg-info ms-1">
                                            <i class="fas fa-users me-1"></i> <?php echo $student_count; ?> enrolled
                                        </span>
                                    </div>
                                    <div>
                                        <?php if ($user_role == 'admin' || $subject['teacher_id'] == $current_user['id']): ?>
                                        <button type="button" class="btn btn-sm btn-outline-primary edit-subject" 
                                                data-id="<?php echo $subject['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($subject['subject_name']); ?>"
                                                data-code="<?php echo htmlspecialchars($subject['subject_code']); ?>"
                                                data-teacher="<?php echo $subject['teacher_id']; ?>"
                                                data-section="<?php echo $subject['section_id']; ?>"
                                                data-grade="<?php echo htmlspecialchars($subject['grade_level']); ?>"
                                                data-description="<?php echo htmlspecialchars($subject['description']); ?>"
                                                data-schedule="<?php echo htmlspecialchars($subject['schedule']); ?>"
                                                data-status="<?php echo $subject['status']; ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger delete-subject"
                                                data-id="<?php echo $subject['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($subject['subject_name']); ?>">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        <?php else: ?>
                                        <a href="attendance.php?subject_id=<?php echo $subject['id']; ?>" class="btn btn-sm btn-outline-info">
                                            <i class="fas fa-clipboard-check"></i> Attendance
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
        <?php endif; ?>
    </div>
</div>

<!-- Add Section Modal -->
<div class="modal fade" id="addSectionModal" tabindex="-1" aria-labelledby="addSectionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="addSectionModalLabel">Add New Section</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="sections.php?view=sections" method="post">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_section">
                    
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="section_name" class="form-label">Section Name</label>
                                <input type="text" class="form-control" id="section_name" name="section_name" required placeholder="e.g., St. Alpha">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="section_grade_level" class="form-label">Grade Level</label>
                                <select class="form-select" id="section_grade_level" name="grade_level" required>
                                    <option value="">Select Grade Level</option>
                                    <?php for ($i = 7; $i <= 12; $i++): ?>
                                        <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="section_teacher_id" class="form-label">Adviser Teacher</label>
                        <?php if ($user_role == 'admin'): ?>
                        <select class="form-select" id="section_teacher_id" name="teacher_id">
                            <option value="">Select Teacher</option>
                            <?php foreach ($teachers as $teacher): ?>
                                <option value="<?php echo $teacher['id']; ?>">
                                    <?php echo htmlspecialchars($teacher['full_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php else: ?>
                        <input type="hidden" name="teacher_id" value="<?php echo $current_user['id']; ?>">
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($current_user['full_name']); ?> (You)" readonly>
                        <small class="text-muted">Teachers can only create sections for themselves.</small>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-3">
                        <label for="section_description" class="form-label">Description</label>
                        <textarea class="form-control" id="section_description" name="description" rows="3" placeholder="Brief description of the section"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Add Section</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Subject Modal -->
<div class="modal fade" id="addSubjectModal" tabindex="-1" aria-labelledby="addSubjectModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="addSubjectModalLabel">Add New Subject</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="sections.php" method="post">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="subject_name" class="form-label">Subject Name</label>
                                <input type="text" class="form-control" id="subject_name" name="subject_name" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="subject_code" class="form-label">Subject Code</label>
                                <input type="text" class="form-control" id="subject_code" name="subject_code" required placeholder="e.g., MATH101">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="grade_level" class="form-label">Grade Level</label>
                                <select class="form-select" id="grade_level" name="grade_level" required>
                                    <option value="">Select Grade Level</option>
                                    <?php for ($i = 7; $i <= 12; $i++): ?>
                                        <option value="Grade <?php echo $i; ?>">Grade <?php echo $i; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>

                           
                   
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="teacher_id" class="form-label">Teacher</label>
                                <?php if ($user_role == 'admin'): ?>
                                <select class="form-select" id="teacher_id" name="teacher_id">
                                    <option value="">Select Teacher</option>
                                    <?php foreach ($teachers as $teacher): ?>
                                        <option value="<?php echo $teacher['id']; ?>">
                                            <?php echo htmlspecialchars($teacher['full_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php else: ?>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($current_user['full_name']); ?>" readonly>
                                <div class="form-text">You are automatically assigned as the teacher for subjects you create.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="section_id" class="form-label">Section</label>
                        <select class="form-select" id="section_id" name="section_id">
                            <option value="">Select Section</option>
                            <?php foreach ($all_sections as $section): ?>
                                <?php if ($user_role == 'admin' || $section['teacher_id'] == $current_user['id']): ?>
                                <option value="<?php echo $section['id']; ?>" data-teacher-id="<?php echo $section['teacher_id'] ?? ''; ?>">
                                    <?php echo htmlspecialchars($section['section_name'] . ' - Grade ' . $section['grade_level']); ?>
                                    <?php if (!empty($section['teacher_name']) && $user_role == 'admin'): ?>
                                        (<?php echo htmlspecialchars($section['teacher_name']); ?>)
                                    <?php endif; ?>
                                </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($user_role == 'teacher'): ?>
                        <div class="form-text">You can only assign subjects to sections you manage.</div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3" placeholder="Brief description of the subject"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="schedule" class="form-label">Schedule</label>
                        <input type="text" class="form-control" id="schedule" name="schedule" placeholder="e.g., Monday 8:00-9:00 AM, Wednesday 8:00-9:00 AM">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Subject</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Subject Modal -->
<div class="modal fade" id="editSubjectModal" tabindex="-1" aria-labelledby="editSubjectModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="editSubjectModalLabel">Edit Subject</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="sections.php" method="post">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="subject_id" id="edit_subject_id">
                    
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="edit_subject_name" class="form-label">Subject Name</label>
                                <input type="text" class="form-control" id="edit_subject_name" name="subject_name" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="edit_subject_code" class="form-label">Subject Code</label>
                                <input type="text" class="form-control" id="edit_subject_code" name="subject_code" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_grade_level" class="form-label">Grade Level</label>
                                <select class="form-select" id="edit_grade_level" name="grade_level" required>
                                    <option value="">Select Grade Level</option>
                                    <?php for ($i = 7; $i <= 12; $i++): ?>
                                        <option value="Grade <?php echo $i; ?>">Grade <?php echo $i; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_teacher_id" class="form-label">Teacher</label>
                                <?php if ($user_role == 'admin'): ?>
                                <select class="form-select" id="edit_teacher_id" name="teacher_id">
                                    <option value="">Select Teacher</option>
                                    <?php foreach ($teachers as $teacher): ?>
                                        <option value="<?php echo $teacher['id']; ?>">
                                            <?php echo htmlspecialchars($teacher['full_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php else: ?>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($current_user['full_name']); ?>" readonly>
                                <div class="form-text">You can only edit subjects you teach.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_section_id" class="form-label">Section</label>
                        <select class="form-select" id="edit_section_id" name="section_id">
                            <option value="">Select Section</option>
                            <?php foreach ($all_sections as $section): ?>
                                <?php if ($user_role == 'admin' || $section['teacher_id'] == $current_user['id']): ?>
                                <option value="<?php echo $section['id']; ?>" data-teacher-id="<?php echo $section['teacher_id'] ?? ''; ?>">
                                    <?php echo htmlspecialchars($section['section_name'] . ' - Grade ' . $section['grade_level']); ?>
                                    <?php if (!empty($section['teacher_name']) && $user_role == 'admin'): ?>
                                        (<?php echo htmlspecialchars($section['teacher_name']); ?>)
                                    <?php endif; ?>
                                </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($user_role == 'teacher'): ?>
                        <div class="form-text">You can only assign subjects to sections you manage.</div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_description" class="form-label">Description</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_schedule" class="form-label">Schedule</label>
                        <input type="text" class="form-control" id="edit_schedule" name="schedule">
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

<!-- Edit Section Modal -->
<div class="modal fade" id="editSectionModal" tabindex="-1" aria-labelledby="editSectionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="editSectionModalLabel">Edit Section</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="sections.php?view=sections" method="post">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit_section">
                    <input type="hidden" name="section_id" id="edit_section_id">
                    
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="edit_section_name" class="form-label">Section Name</label>
                                <input type="text" class="form-control" id="edit_section_name" name="section_name" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="edit_section_grade_level" class="form-label">Grade Level</label>
                                <select class="form-select" id="edit_section_grade_level" name="grade_level" required>
                                    <option value="">Select Grade Level</option>
                                    <?php for ($i = 7; $i <= 12; $i++): ?>
                                        <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_section_teacher_id" class="form-label">Adviser Teacher</label>
                        <?php if ($user_role == 'admin'): ?>
                        <select class="form-select" id="edit_section_teacher_id" name="teacher_id">
                            <option value="">Select Teacher</option>
                            <?php foreach ($teachers as $teacher): ?>
                                <option value="<?php echo $teacher['id']; ?>">
                                    <?php echo htmlspecialchars($teacher['full_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php else: ?>
                        <input type="hidden" name="teacher_id" value="<?php echo $current_user['id']; ?>">
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($current_user['full_name']); ?> (You)" readonly>
                        <small class="text-muted">Teachers can only edit their own sections.</small>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_section_description" class="form-label">Description</label>
                        <textarea class="form-control" id="edit_section_description" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_section_status" class="form-label">Status</label>
                        <select class="form-select" id="edit_section_status" name="status" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Subject Modal -->
<div class="modal fade" id="deleteSubjectModal" tabindex="-1" aria-labelledby="deleteSubjectModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteSubjectModalLabel">
                    <?php echo $user_role == 'admin' ? 'Permanently Delete Subject' : 'Delete/Archive Subject'; ?>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete the subject: <span id="delete_subject_name" class="fw-bold"></span>?</p>
                
                <?php if ($user_role == 'admin'): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-1"></i>
                    <strong>ADMIN WARNING: This action will permanently delete:</strong> 
                    <ul class="mb-0 mt-2">
                        <li>The subject and all its information</li>
                        <li>All student enrollments in this subject</li>
                        <li>All attendance records for this subject</li>
                        <li>Any other data related to this subject</li>
                    </ul>
                </div>
                <p class="text-danger mb-0">
                    <i class="fas fa-exclamation-triangle me-1"></i>
                    <strong>This action cannot be undone!</strong>
                </p>
                <?php else: ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-1"></i>
                    <strong>TEACHER WARNING: This action will permanently delete:</strong> 
                    <ul class="mb-0 mt-2">
                        <li>The subject and all its information</li>
                        <li>All student enrollments in this subject</li>
                        <li>All attendance records for this subject</li>
                        <li>You can only delete subjects you teach</li>
                    </ul>
                </div>
                <p class="text-danger mb-0">
                    <i class="fas fa-exclamation-triangle me-1"></i>
                    <strong>This action cannot be undone!</strong>
                </p>
                <?php endif; ?>
            </div>
            <form action="sections.php" method="post">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="subject_id" id="delete_subject_id">
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <?php echo $user_role == 'admin' ? 'Permanently Delete Subject' : 'Delete/Archive Subject'; ?>
                    </button>
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
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-1"></i>
                    <strong>WARNING: This action will permanently delete:</strong> 
                    <ul class="mb-0 mt-2">
                        <li>The section and all its information</li>
                        <li>All subjects within this section</li>
                        <li>All attendance records for this section</li>
                        <li>All student enrollments in subjects within this section</li>
                        <li>If students are currently assigned, deletion will be blocked</li>
                    </ul>
                </div>
                <p class="text-danger mb-0">
                    <i class="fas fa-exclamation-triangle me-1"></i>
                    <strong>This action cannot be undone!</strong>
                </p>
            </div>
            <form action="sections.php?view=sections" method="post">
                <input type="hidden" name="action" value="delete_section">
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