<?php
require_once 'config.php';

// Check if user is logged in and has permission
requireRole(['admin', 'teacher']);

$current_user = getCurrentUser($pdo);
$user_role = $_SESSION['role'];

// Handle report generation
if (isset($_GET['generate']) && isset($_GET['type'])) {
    $report_type = $_GET['type'];
    $format = $_GET['format'] ?? 'html';
    
    // Generate report based on type and format
    generateReport($report_type, $format);
    exit;
}

$page_title = 'Reports';
include 'header.php';

// Add custom CSS for better mobile layout and card organization
echo '<style>
    /* Card organization and styling improvements */
    .card {
        transition: all 0.2s ease;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        border-radius: 8px;
        overflow: hidden;
    }
    .card:hover {
        transform: translateY(-3px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.12);
    }
    .card-header {
        padding: 0.75rem 1rem;
        font-weight: 500;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .card-body {
        padding: 1rem;
    }
    .avatar-circle {
        width: 80px;
        height: 80px;
        margin: 0 auto 1rem;
        border-radius: 50%;
        overflow: hidden;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 2px 6px rgba(0,0,0,0.1);
    }
    .list-group-flush {
        margin-top: 0.75rem;
    }
    .list-group-item {
        padding: 0.5rem 0;
        border-bottom: 1px solid rgba(0,0,0,0.05);
    }
    .list-group-item:last-child {
        border-bottom: none;
    }
    .badge {
        font-weight: 500;
        padding: 0.4em 0.6em;
    }
    
    /* Status colors */
    .status-present {color: #28a745;}
    .status-absent {color: #dc3545;}
    .status-late {color: #ffc107;}
    .status-info {color: #17a2b8;}
    
    /* Tab content organization */
    .tab-content {
        padding: 1rem;
        border: 1px solid #dee2e6;
        border-top: none;
        border-radius: 0 0 0.25rem 0.25rem;
    }
    .tab-pane {
        padding: 0.5rem 0;
    }
    
    /* Card grid spacing */
    .row-cols-1 > .col,
    .row-cols-sm-2 > .col,
    .row-cols-md-2 > .col,
    .row-cols-lg-3 > .col {
        padding: 0.75rem;
    }
    
    /* Mobile optimizations */
    @media (max-width: 576px) {
        .card {
            margin-bottom: 15px;
        }
        .card-body {
            padding: 0.75rem;
        }
        .list-group-item {
            padding: 0.5rem 0;
        }
        .avatar-circle {
            width: 60px !important;
            height: 60px !important;
        }
        .avatar-circle i {
            font-size: 1.25rem !important;
        }
        .card-title {
            font-size: 1.1rem;
        }
        .badge {
            font-size: 0.7rem;
        }
        .tab-content {
            padding: 0.75rem !important;
        }
        .tab-pane .mb-3 {
            margin-bottom: 0.75rem !important;
        }
        .tab-pane h5 {
            font-size: 1.1rem;
        }
        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
        }
        .col {
            padding-left: 8px;
            padding-right: 8px;
        }
        .row {
            margin-left: -8px;
            margin-right: -8px;
        }
    }
</style>';

// Add Select2 CSS and JS references
echo '<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
<style>
    /* Select2 custom styling */
    .select2-container--bootstrap-5 .select2-selection {
        border: 1px solid #dee2e6;
        padding: 0.375rem 0.75rem;
        height: auto;
        font-size: 1rem;
        border-radius: 0.25rem;
    }
    .select2-container--bootstrap-5 .select2-selection--single {
        height: calc(1.5em + 0.75rem + 2px);
    }
    .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
        padding-left: 0;
        color: #212529;
        line-height: 1.5;
    }
    .select2-container--bootstrap-5 .select2-selection--single .select2-selection__arrow {
        height: calc(1.5em + 0.75rem);
    }
    .select2-container--bootstrap-5 .select2-selection--multiple {
        min-height: calc(1.5em + 0.75rem + 2px);
    }
    .select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__rendered {
        display: block;
        padding: 0;
    }
    .select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__choice {
        background-color: #007bff;
        border: none;
        color: #fff;
        padding: 0.2rem 0.4rem;
        margin-top: 0.2rem;
        margin-right: 0.2rem;
        font-size: 0.875rem;
    }
    .select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__choice__remove {
        color: #fff;
        margin-right: 0.25rem;
    }
    .select2-container--bootstrap-5 .select2-search--dropdown .select2-search__field {
        border: 1px solid #ced4da;
        border-radius: 0.25rem;
        padding: 0.375rem 0.75rem;
    }
    .select2-container--bootstrap-5 .select2-results__option--highlighted[aria-selected] {
        background-color: #007bff;
    }
    .select2-container--bootstrap-5 .select2-results__group {
        padding: 0.5rem 0;
        font-weight: 500;
    }
    @media (max-width: 576px) {
        .select2-container {
            width: 100% !important;
        }
    }
    
    /* Format Selection Modal Styles */
    .format-option {
        transition: all 0.2s ease;
        border: 2px solid transparent !important;
    }
    .format-option:hover {
        border-color: #007bff !important;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,123,255,0.15);
    }
    .format-option.border-primary {
        border-color: #007bff !important;
        background-color: rgba(0,123,255,0.05) !important;
    }
    .format-option .card-body {
        padding: 1.5rem;
    }
    .format-option .card-title {
        font-weight: 600;
        margin-bottom: 0.5rem;
    }
    .format-option .card-text {
        margin-bottom: 0;
        color: #6c757d;
    }
    #formatSelectionModal .modal-body {
        padding: 1.5rem;
    }
    #formatSelectionModal .modal-title {
        font-weight: 600;
        color: #495057;
    }
</style>';

// Get available sections and students based on user role
try {
    if ($user_role == 'admin') {
        $sections = $pdo->query("SELECT id, section_name, grade_level FROM sections WHERE status = 'active' ORDER BY section_name")->fetchAll(PDO::FETCH_ASSOC);
        $students = $pdo->query("SELECT id, full_name, username FROM users WHERE role = 'student' AND status = 'active' ORDER BY full_name")->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($user_role == 'teacher') {
        $sections_stmt = $pdo->prepare("SELECT id, section_name, grade_level FROM sections WHERE teacher_id = ? AND status = 'active' ORDER BY section_name");
        $sections_stmt->execute([$current_user['id']]);
        $sections = $sections_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $students_stmt = $pdo->prepare("SELECT id, full_name, username FROM users WHERE role = 'student' AND status = 'active' AND section_id IN (SELECT id FROM sections WHERE teacher_id = ?) ORDER BY full_name");
        $students_stmt->execute([$current_user['id']]);
        $students = $students_stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($user_role == 'parent') {
        $sections = [];
        $students_stmt = $pdo->prepare("SELECT u.id, u.full_name, u.username FROM users u JOIN student_parents sp ON u.id = sp.student_id WHERE sp.parent_id = ? ORDER BY u.full_name");
        $students_stmt->execute([$current_user['id']]);
        $students = $students_stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $sections = [];
        $students = [$current_user];
    }
} catch(PDOException $e) {
    $sections = [];
    $students = [];
}

function generateReport($type, $format) {
    global $pdo, $current_user, $user_role;
    
    $filename = '';
    $data = [];
    
    switch($type) {
        case 'students_list':
            $filename = 'students_list_' . date('Y-m-d');
            $section_id = $_GET['section_id'] ?? '';
            
            $query = "SELECT u.*, s.section_name, s.grade_level FROM users u LEFT JOIN sections s ON u.section_id = s.id WHERE u.role = 'student' AND u.status = 'active'";
            $params = [];
            
            if ($section_id) {
                $query .= " AND u.section_id = ?";
                $params[] = $section_id;
            }
            
            if ($user_role == 'teacher') {
                $query .= " AND u.section_id IN (SELECT id FROM sections WHERE teacher_id = ?)";
                $params[] = $current_user['id'];
            }
            
            $query .= " ORDER BY u.full_name";
            
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
            
        case 'student_info':
            $student_id = $_GET['student_id'] ?? '';
            if (!$student_id) return;
            
            $filename = 'student_info_' . $student_id . '_' . date('Y-m-d');
            
            $stmt = $pdo->prepare("
                SELECT u.*, s.section_name, s.grade_level,
                       GROUP_CONCAT(CONCAT(p.full_name, ' (', sp.relationship, ')') SEPARATOR ', ') as parents
                FROM users u 
                LEFT JOIN sections s ON u.section_id = s.id 
                LEFT JOIN student_parents sp ON u.id = sp.student_id
                LEFT JOIN users p ON sp.parent_id = p.id
                WHERE u.id = ? AND u.role = 'student'
                GROUP BY u.id
            ");
            $stmt->execute([$student_id]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            break;
            
        case 'attendance_records':
            $filename = 'attendance_records_' . date('Y-m-d');
            $student_id = $_GET['student_id'] ?? '';
            $section_id = $_GET['section_id'] ?? '';
            $date_from = $_GET['date_from'] ?? date('Y-m-01');
            $date_to = $_GET['date_to'] ?? date('Y-m-t');
            
            $query = "
                SELECT a.*, u.full_name as student_name, u.username, s.section_name, t.full_name as teacher_name
                FROM attendance a
                JOIN users u ON a.student_id = u.id
                JOIN sections s ON a.section_id = s.id
                JOIN users t ON a.teacher_id = t.id
                WHERE a.attendance_date BETWEEN ? AND ?
            ";
            $params = [$date_from, $date_to];
            
            if ($student_id) {
                $query .= " AND a.student_id = ?";
                $params[] = $student_id;
            }
            
            if ($section_id) {
                $query .= " AND a.section_id = ?";
                $params[] = $section_id;
            }
            
            if ($user_role == 'teacher') {
                $query .= " AND a.teacher_id = ?";
                $params[] = $current_user['id'];
            }
            
            $query .= " ORDER BY a.attendance_date DESC, u.full_name";
            
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
            
        case 'students_per_section':
            $filename = 'students_per_section_' . date('Y-m-d');
            $section_id = $_GET['section_id'] ?? '';
            
            if (!$section_id) {
                // Get all sections with their students
                $query = "
                    SELECT s.id, s.section_name, s.grade_level, 
                           u.full_name as teacher_name,
                           (SELECT COUNT(*) FROM users WHERE section_id = s.id AND role = 'student' AND status = 'active') as student_count
                    FROM sections s
                    LEFT JOIN users u ON s.teacher_id = u.id
                    WHERE s.status = 'active'
                ";
                
                if ($user_role == 'teacher') {
                    $query .= " AND s.teacher_id = ?";
                    $params = [$current_user['id']];
                } else {
                    $params = [];
                }
                
                $query .= " ORDER BY s.grade_level, s.section_name";
                
                $stmt = $pdo->prepare($query);
                $stmt->execute($params);
                $sections = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // For each section, get the students
                foreach ($sections as &$section) {
                    $students_query = "
                        SELECT id, full_name, username, lrn, email, phone
                        FROM users 
                        WHERE section_id = ? AND role = 'student' AND status = 'active'
                        ORDER BY full_name
                    ";
                    $students_stmt = $pdo->prepare($students_query);
                    $students_stmt->execute([$section['id']]);
                    $section['students'] = $students_stmt->fetchAll(PDO::FETCH_ASSOC);
                }
                
                $data = $sections;
            } else {
                // Get specific section details
                $section_query = "
                    SELECT s.*, u.full_name as teacher_name
                    FROM sections s
                    LEFT JOIN users u ON s.teacher_id = u.id
                    WHERE s.id = ?
                ";
                $section_stmt = $pdo->prepare($section_query);
                $section_stmt->execute([$section_id]);
                $section = $section_stmt->fetch(PDO::FETCH_ASSOC);
                
                // Get students in this section
                $students_query = "
                    SELECT id, full_name, username, lrn, email, phone, profile_image, qr_code
                    FROM users 
                    WHERE section_id = ? AND role = 'student' AND status = 'active'
                    ORDER BY full_name
                ";
                $students_stmt = $pdo->prepare($students_query);
                $students_stmt->execute([$section_id]);
                $students = $students_stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $data = [
                    'section' => $section,
                    'students' => $students
                ];
            }
            break;
            
        case 'student_qr':
            $student_id = $_GET['student_id'] ?? '';
            $filename = 'student_qr_' . date('Y-m-d');
            
            if ($student_id) {
                // Get specific student
                $query = "
                    SELECT u.id, u.full_name, u.username, u.lrn, u.qr_code, s.section_name, s.grade_level
                    FROM users u
                    LEFT JOIN sections s ON u.section_id = s.id
                    WHERE u.id = ? AND u.role = 'student'
                ";
                $stmt = $pdo->prepare($query);
                $stmt->execute([$student_id]);
                $data = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                // Get all students with QR codes
                $query = "
                    SELECT u.id, u.full_name, u.username, u.lrn, u.qr_code, s.section_name, s.grade_level
                    FROM users u
                    LEFT JOIN sections s ON u.section_id = s.id
                    WHERE u.role = 'student' AND u.status = 'active'
                ";
                
                if ($user_role == 'teacher') {
                    $query .= " AND u.section_id IN (SELECT id FROM sections WHERE teacher_id = ?)";
                    $params = [$current_user['id']];
                } else {
                    $params = [];
                }
                
                $query .= " ORDER BY s.grade_level, s.section_name, u.full_name";
                
                $stmt = $pdo->prepare($query);
                $stmt->execute($params);
                $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            break;
            
        case 'attendance_per_section':
            $filename = 'attendance_per_section_' . date('Y-m-d');
            $section_id = $_GET['section_id'] ?? '';
            $date_from = $_GET['date_from'] ?? date('Y-m-01');
            $date_to = $_GET['date_to'] ?? date('Y-m-t');
            
            if (!$section_id) {
                // Summary for all sections
                $query = "
                    SELECT s.id, s.section_name, s.grade_level,
                           COUNT(DISTINCT a.student_id) as students_with_attendance,
                           COUNT(a.id) as total_records,
                           SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_count,
                           SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent_count,
                           SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late_count,
                           SUM(CASE WHEN a.status = 'out' THEN 1 ELSE 0 END) as out_count
                    FROM sections s
                    LEFT JOIN attendance a ON s.id = a.section_id AND a.attendance_date BETWEEN ? AND ?
                    WHERE s.status = 'active'
                ";
                $params = [$date_from, $date_to];
                
                if ($user_role == 'teacher') {
                    $query .= " AND s.teacher_id = ?";
                    $params[] = $current_user['id'];
                }
                
                $query .= " GROUP BY s.id ORDER BY s.grade_level, s.section_name";
                
                $stmt = $pdo->prepare($query);
                $stmt->execute($params);
                $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                // Detailed attendance for a specific section
                $section_query = "
                    SELECT s.*, u.full_name as teacher_name
                    FROM sections s
                    LEFT JOIN users u ON s.teacher_id = u.id
                    WHERE s.id = ?
                ";
                $section_stmt = $pdo->prepare($section_query);
                $section_stmt->execute([$section_id]);
                $section = $section_stmt->fetch(PDO::FETCH_ASSOC);
                
                // Get attendance records for this section
                $attendance_query = "
                    SELECT a.*, u.full_name as student_name, u.username, t.full_name as teacher_name
                    FROM attendance a
                    JOIN users u ON a.student_id = u.id
                    JOIN users t ON a.teacher_id = t.id
                    WHERE a.section_id = ? AND a.attendance_date BETWEEN ? AND ?
                    ORDER BY a.attendance_date DESC, u.full_name
                ";
                $attendance_stmt = $pdo->prepare($attendance_query);
                $attendance_stmt->execute([$section_id, $date_from, $date_to]);
                $attendance = $attendance_stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Get summary by date
                $summary_query = "
                    SELECT a.attendance_date,
                           COUNT(a.id) as total_records,
                           SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_count,
                           SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent_count,
                           SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late_count,
                           SUM(CASE WHEN a.status = 'out' THEN 1 ELSE 0 END) as out_count
                    FROM attendance a
                    WHERE a.section_id = ? AND a.attendance_date BETWEEN ? AND ?
                    GROUP BY a.attendance_date
                    ORDER BY a.attendance_date DESC
                ";
                $summary_stmt = $pdo->prepare($summary_query);
                $summary_stmt->execute([$section_id, $date_from, $date_to]);
                $summary = $summary_stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $data = [
                    'section' => $section,
                    'attendance' => $attendance,
                    'summary' => $summary
                ];
            }
            break;
            
        case 'attendance_per_student':
            $filename = 'attendance_per_student_' . date('Y-m-d');
            $student_id = $_GET['student_id'] ?? '';
            $date_from = $_GET['date_from'] ?? date('Y-m-01');
            $date_to = $_GET['date_to'] ?? date('Y-m-t');
            
            if (!$student_id) {
                // Summary for all students
                $query = "
                    SELECT u.id, u.full_name, u.username, s.section_name, s.grade_level,
                           COUNT(a.id) as total_records,
                           SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_count,
                           SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent_count,
                           SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late_count,
                           SUM(CASE WHEN a.status = 'out' THEN 1 ELSE 0 END) as out_count
                    FROM users u
                    LEFT JOIN sections s ON u.section_id = s.id
                    LEFT JOIN attendance a ON u.id = a.student_id AND a.attendance_date BETWEEN ? AND ?
                    WHERE u.role = 'student' AND u.status = 'active'
                ";
                $params = [$date_from, $date_to];
                
                if ($user_role == 'teacher') {
                    $query .= " AND u.section_id IN (SELECT id FROM sections WHERE teacher_id = ?)";
                    $params[] = $current_user['id'];
                }
                
                $query .= " GROUP BY u.id ORDER BY s.grade_level, s.section_name, u.full_name";
                
                $stmt = $pdo->prepare($query);
                $stmt->execute($params);
                $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                // Detailed attendance for a specific student
                $student_query = "
                    SELECT u.*, s.section_name, s.grade_level
                    FROM users u
                    LEFT JOIN sections s ON u.section_id = s.id
                    WHERE u.id = ?
                ";
                $student_stmt = $pdo->prepare($student_query);
                $student_stmt->execute([$student_id]);
                $student = $student_stmt->fetch(PDO::FETCH_ASSOC);
                
                // Get attendance records for this student
                $attendance_query = "
                    SELECT a.*, s.section_name, t.full_name as teacher_name
                    FROM attendance a
                    JOIN sections s ON a.section_id = s.id
                    JOIN users t ON a.teacher_id = t.id
                    WHERE a.student_id = ? AND a.attendance_date BETWEEN ? AND ?
                    ORDER BY a.attendance_date DESC
                ";
                $attendance_stmt = $pdo->prepare($attendance_query);
                $attendance_stmt->execute([$student_id, $date_from, $date_to]);
                $attendance = $attendance_stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Get summary by month
                $summary_query = "
                    SELECT DATE_FORMAT(a.attendance_date, '%Y-%m') as month,
                           COUNT(a.id) as total_records,
                           SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_count,
                           SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent_count,
                           SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late_count,
                           SUM(CASE WHEN a.status = 'out' THEN 1 ELSE 0 END) as out_count
                    FROM attendance a
                    WHERE a.student_id = ? AND a.attendance_date BETWEEN ? AND ?
                    GROUP BY DATE_FORMAT(a.attendance_date, '%Y-%m')
                    ORDER BY month DESC
                ";
                $summary_stmt = $pdo->prepare($summary_query);
                $summary_stmt->execute([$student_id, $date_from, $date_to]);
                $summary = $summary_stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $data = [
                    'student' => $student,
                    'attendance' => $attendance,
                    'summary' => $summary
                ];
            }
            break;
    }
    
    if ($format == 'csv') {
        outputCSV($data, $filename);
    } elseif ($format == 'pdf') {
        outputPDF($data, $filename, $type);
    } else {
        outputHTML($data, $filename, $type);
    }
}

function outputCSV($data, $filename) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    if (!empty($data)) {
        // Write headers
        fputcsv($output, array_keys($data[0]));
        
        // Write data
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
    }
    
    fclose($output);
}

function outputHTML($data, $filename, $type) {
    echo "<!DOCTYPE html><html><head><title>$filename</title>";
    echo "<style>
        body{font-family:Arial,sans-serif;margin:20px;} 
        table{width:100%;border-collapse:collapse;margin-bottom:20px;} 
        th,td{border:1px solid #ddd;padding:8px;text-align:left;} 
        th{background-color:#f2f2f2;} 
        .header{margin-bottom:20px;}
        .section{margin-bottom:30px;}
        .qr-code{text-align:center;margin:10px 0;}
        .student-card{border:1px solid #ddd;padding:15px;margin-bottom:15px;border-radius:5px;}
        .student-info{display:flex;flex-wrap:wrap;}
        .student-info div{margin-right:20px;margin-bottom:10px;}
        .student-label{font-weight:bold;color:#555;}
        .summary-box{background:#f9f9f9;padding:15px;border-radius:5px;margin-bottom:15px;}
        .status-present{color:green;} 
        .status-absent{color:red;} 
        .status-late{color:orange;}
        .status-info{color:blue;}
        h2{color:#2c3e50;border-bottom:1px solid #eee;padding-bottom:10px;}
        h3{color:#3498db;}
    </style>";
    echo "</head><body>";
    echo "<div class='header'>";
    echo "<h1>" . ucwords(str_replace('_', ' ', $type)) . "</h1>";
    echo "<p>Generated on: " . date('Y-m-d H:i:s') . "</p>";
    echo "</div>";
    
    switch($type) {
        case 'students_list':
            if (!empty($data)) {
                echo "<table>";
                echo "<tr>";
                echo "<th>ID</th>";
                echo "<th>Name</th>";
                echo "<th>Username</th>";
                echo "<th>LRN</th>";
                echo "<th>Section</th>";
                echo "<th>Grade Level</th>";
                echo "<th>Email</th>";
                echo "<th>Phone</th>";
                echo "</tr>";
                
                foreach ($data as $row) {
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($row['id']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['full_name']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['username']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['lrn'] ?? '') . "</td>";
                    echo "<td>" . htmlspecialchars($row['section_name'] ?? '') . "</td>";
                    echo "<td>" . htmlspecialchars($row['grade_level'] ?? '') . "</td>";
                    echo "<td>" . htmlspecialchars($row['email'] ?? '') . "</td>";
                    echo "<td>" . htmlspecialchars($row['phone'] ?? '') . "</td>";
                    echo "</tr>";
                }
                echo "</table>";
            } else {
                echo "<p>No student data found.</p>";
            }
            break;
            
        case 'student_info':
            if (!empty($data)) {
                echo "<div class='student-card'>";
                echo "<h2>" . htmlspecialchars($data['full_name']) . "</h2>";
                
                echo "<div class='student-info'>";
                echo "<div><span class='student-label'>Username:</span> " . htmlspecialchars($data['username']) . "</div>";
                echo "<div><span class='student-label'>LRN:</span> " . htmlspecialchars($data['lrn'] ?? 'Not set') . "</div>";
                echo "<div><span class='student-label'>Email:</span> " . htmlspecialchars($data['email'] ?? 'Not set') . "</div>";
                echo "<div><span class='student-label'>Phone:</span> " . htmlspecialchars($data['phone'] ?? 'Not set') . "</div>";
                echo "<div><span class='student-label'>Section:</span> " . htmlspecialchars($data['section_name'] ?? 'Not assigned') . "</div>";
                echo "<div><span class='student-label'>Grade Level:</span> " . htmlspecialchars($data['grade_level'] ?? 'Not assigned') . "</div>";
                echo "<div><span class='student-label'>Status:</span> " . htmlspecialchars($data['status']) . "</div>";
                echo "<div><span class='student-label'>Parents/Guardians:</span> " . htmlspecialchars($data['parents'] ?? 'None registered') . "</div>";
                echo "</div>";
                
                if (!empty($data['qr_code'])) {
                    echo "<div class='qr-code'>";
                    echo "<h3>Student QR Code</h3>";
                    echo "<img src='" . htmlspecialchars($data['qr_code']) . "' alt='QR Code' style='max-width:200px;'>";
                    echo "</div>";
                }
                
                echo "</div>";
            } else {
                echo "<p>No student data found.</p>";
            }
            break;
            
        case 'attendance_records':
            if (!empty($data)) {
                echo "<table>";
                echo "<tr>";
                echo "<th>Date</th>";
                echo "<th>Student</th>";
                echo "<th>Section</th>";
                echo "<th>Time In</th>";
                echo "<th>Time Out</th>";
                echo "<th>Status</th>";
                echo "<th>Teacher</th>";
                echo "<th>Remarks</th>";
                echo "</tr>";
                
                foreach ($data as $row) {
                    $status_class = 'status-' . strtolower($row['status']);
                    
                    echo "<tr>";
                    echo "<td>" . date('Y-m-d', strtotime($row['attendance_date'])) . "</td>";
                    echo "<td>" . htmlspecialchars($row['student_name']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['section_name']) . "</td>";
                    echo "<td>" . ($row['time_in'] ? date('h:i A', strtotime($row['time_in'])) : '-') . "</td>";
                    echo "<td>" . ($row['time_out'] ? date('h:i A', strtotime($row['time_out'])) : '-') . "</td>";
                    echo "<td class='" . $status_class . "'>" . htmlspecialchars($row['status']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['teacher_name']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['remarks'] ?? '') . "</td>";
                    echo "</tr>";
                }
                echo "</table>";
            } else {
                echo "<p>No attendance records found.</p>";
            }
            break;
            
        case 'students_per_section':
            if (!empty($data)) {
                // Check if it's a single section or multiple sections
                if (isset($data['section'])) {
                    // Single section view
                    $section = $data['section'];
                    $students = $data['students'];
                    
                    echo "<div class='section'>";
                    echo "<h2>Section: " . htmlspecialchars($section['section_name']) . " (" . htmlspecialchars($section['grade_level']) . ")</h2>";
                    echo "<p><strong>Teacher:</strong> " . htmlspecialchars($section['teacher_name'] ?? 'Not assigned') . "</p>";
                    echo "<p><strong>Total Students:</strong> " . count($students) . "</p>";
                    
                    if (!empty($students)) {
                        echo "<h3>Student List</h3>";
                        echo "<table>";
                        echo "<tr>";
                        echo "<th>ID</th>";
                        echo "<th>Name</th>";
                        echo "<th>Username</th>";
                        echo "<th>LRN</th>";
                        echo "<th>Email</th>";
                        echo "<th>Phone</th>";
                        echo "</tr>";
                        
                        foreach ($students as $student) {
                            echo "<tr>";
                            echo "<td>" . htmlspecialchars($student['id']) . "</td>";
                            echo "<td>" . htmlspecialchars($student['full_name']) . "</td>";
                            echo "<td>" . htmlspecialchars($student['username']) . "</td>";
                            echo "<td>" . htmlspecialchars($student['lrn'] ?? '') . "</td>";
                            echo "<td>" . htmlspecialchars($student['email'] ?? '') . "</td>";
                            echo "<td>" . htmlspecialchars($student['phone'] ?? '') . "</td>";
                            echo "</tr>";
                        }
                        echo "</table>";
                    } else {
                        echo "<p>No students found in this section.</p>";
                    }
                    echo "</div>";
                } else {
                    // Multiple sections view
                    foreach ($data as $section) {
                        echo "<div class='section'>";
                        echo "<h2>Section: " . htmlspecialchars($section['section_name']) . " (" . htmlspecialchars($section['grade_level']) . ")</h2>";
                        echo "<p><strong>Teacher:</strong> " . htmlspecialchars($section['teacher_name'] ?? 'Not assigned') . "</p>";
                        echo "<p><strong>Total Students:</strong> " . htmlspecialchars($section['student_count']) . "</p>";
                        
                        if (!empty($section['students'])) {
                            echo "<h3>Student List</h3>";
                            echo "<table>";
                            echo "<tr>";
                            echo "<th>ID</th>";
                            echo "<th>Name</th>";
                            echo "<th>Username</th>";
                            echo "<th>LRN</th>";
                            echo "<th>Email</th>";
                            echo "<th>Phone</th>";
                            echo "</tr>";
                            
                            foreach ($section['students'] as $student) {
                                echo "<tr>";
                                echo "<td>" . htmlspecialchars($student['id']) . "</td>";
                                echo "<td>" . htmlspecialchars($student['full_name']) . "</td>";
                                echo "<td>" . htmlspecialchars($student['username']) . "</td>";
                                echo "<td>" . htmlspecialchars($student['lrn'] ?? '') . "</td>";
                                echo "<td>" . htmlspecialchars($student['email'] ?? '') . "</td>";
                                echo "<td>" . htmlspecialchars($student['phone'] ?? '') . "</td>";
                                echo "</tr>";
                            }
                            echo "</table>";
                        } else {
                            echo "<p>No students found in this section.</p>";
                        }
                        echo "</div>";
                    }
                }
            } else {
                echo "<p>No section data found.</p>";
            }
            break;
            
        case 'student_qr':
            if (!empty($data)) {
                // Check if it's a single student or multiple students
                if (isset($data['id'])) {
                    // Single student view
                    echo "<div class='student-card'>";
                    echo "<h2>" . htmlspecialchars($data['full_name']) . "</h2>";
                    
                    echo "<div class='student-info'>";
                    echo "<div><span class='student-label'>Username:</span> " . htmlspecialchars($data['username']) . "</div>";
                    echo "<div><span class='student-label'>LRN:</span> " . htmlspecialchars($data['lrn'] ?? 'Not set') . "</div>";
                    echo "<div><span class='student-label'>Section:</span> " . htmlspecialchars($data['section_name'] ?? 'Not assigned') . "</div>";
                    echo "<div><span class='student-label'>Grade Level:</span> " . htmlspecialchars($data['grade_level'] ?? 'Not assigned') . "</div>";
                    echo "</div>";
                    
                    if (!empty($data['qr_code'])) {
                        echo "<div class='qr-code'>";
                        echo "<h3>Student QR Code</h3>";
                        echo "<img src='" . htmlspecialchars($data['qr_code']) . "' alt='QR Code' style='max-width:200px;'>";
                        echo "</div>";
                    } else {
                        echo "<p>No QR code available for this student.</p>";
                    }
                    
                    echo "</div>";
                } else {
                    // Multiple students view
                    foreach ($data as $student) {
                        echo "<div class='student-card'>";
                        echo "<h2>" . htmlspecialchars($student['full_name']) . "</h2>";
                        
                        echo "<div class='student-info'>";
                        echo "<div><span class='student-label'>Username:</span> " . htmlspecialchars($student['username']) . "</div>";
                        echo "<div><span class='student-label'>LRN:</span> " . htmlspecialchars($student['lrn'] ?? 'Not set') . "</div>";
                        echo "<div><span class='student-label'>Section:</span> " . htmlspecialchars($student['section_name'] ?? 'Not assigned') . "</div>";
                        echo "<div><span class='student-label'>Grade Level:</span> " . htmlspecialchars($student['grade_level'] ?? 'Not assigned') . "</div>";
                        echo "</div>";
                        
                        if (!empty($student['qr_code'])) {
                            echo "<div class='qr-code'>";
                            echo "<h3>Student QR Code</h3>";
                            echo "<img src='" . htmlspecialchars($student['qr_code']) . "' alt='QR Code' style='max-width:200px;'>";
                            echo "</div>";
                        } else {
                            echo "<p>No QR code available for this student.</p>";
                        }
                        
                        echo "</div>";
                    }
                }
            } else {
                echo "<p>No student data found.</p>";
            }
            break;
            
        case 'attendance_per_section':
            if (!empty($data)) {
                // Check if it's a single section or multiple sections
                if (isset($data['section'])) {
                    // Single section view
                    $section = $data['section'];
                    $attendance = $data['attendance'];
                    $summary = $data['summary'];
                    
                    echo "<div class='section'>";
                    echo "<h2>Section: " . htmlspecialchars($section['section_name']) . " (" . htmlspecialchars($section['grade_level']) . ")</h2>";
                    echo "<p><strong>Teacher:</strong> " . htmlspecialchars($section['teacher_name'] ?? 'Not assigned') . "</p>";
                    
                    // Attendance summary
                    echo "<div class='summary-box'>";
                    echo "<h3>Attendance Summary</h3>";
                    echo "<table>";
                    echo "<tr>";
                    echo "<th>Date</th>";
                    echo "<th>Total Records</th>";
                    echo "<th>Present</th>";
                    echo "<th>Absent</th>";
                    echo "<th>Late</th>";
                    echo "<th>Out</th>";
                    echo "</tr>";
                    
                    foreach ($summary as $day) {
                        echo "<tr>";
                        echo "<td>" . date('Y-m-d', strtotime($day['attendance_date'])) . "</td>";
                        echo "<td>" . htmlspecialchars($day['total_records']) . "</td>";
                        echo "<td class='status-present'>" . htmlspecialchars($day['present_count']) . "</td>";
                        echo "<td class='status-absent'>" . htmlspecialchars($day['absent_count']) . "</td>";
                        echo "<td class='status-late'>" . htmlspecialchars($day['late_count']) . "</td>";
                        echo "<td class='status-info'>" . htmlspecialchars($day['out_count']) . "</td>";
                        echo "</tr>";
                    }
                    echo "</table>";
                    echo "</div>";
                    
                    // Detailed attendance records
                    if (!empty($attendance)) {
                        echo "<h3>Detailed Attendance Records</h3>";
                        echo "<table>";
                        echo "<tr>";
                        echo "<th>Date</th>";
                        echo "<th>Student</th>";
                        echo "<th>Time In</th>";
                        echo "<th>Time Out</th>";
                        echo "<th>Status</th>";
                        echo "<th>Teacher</th>";
                        echo "<th>Remarks</th>";
                        echo "</tr>";
                        
                        foreach ($attendance as $record) {
                            $status_class = 'status-' . strtolower($record['status']);
                            
                            echo "<tr>";
                            echo "<td>" . date('Y-m-d', strtotime($record['attendance_date'])) . "</td>";
                            echo "<td>" . htmlspecialchars($record['student_name']) . "</td>";
                            echo "<td>" . ($record['time_in'] ? date('h:i A', strtotime($record['time_in'])) : '-') . "</td>";
                            echo "<td>" . ($record['time_out'] ? date('h:i A', strtotime($record['time_out'])) : '-') . "</td>";
                            echo "<td class='" . $status_class . "'>" . htmlspecialchars($record['status']) . "</td>";
                            echo "<td>" . htmlspecialchars($record['teacher_name']) . "</td>";
                            echo "<td>" . htmlspecialchars($record['remarks'] ?? '') . "</td>";
                            echo "</tr>";
                        }
                        echo "</table>";
                    } else {
                        echo "<p>No attendance records found for this section.</p>";
                    }
                    echo "</div>";
                } else {
                    // Multiple sections view - summary
                    echo "<h2>Attendance Summary by Section</h2>";
                    echo "<table>";
                    echo "<tr>";
                    echo "<th>Section</th>";
                    echo "<th>Grade Level</th>";
                    echo "<th>Students with Attendance</th>";
                    echo "<th>Total Records</th>";
                    echo "<th>Present</th>";
                    echo "<th>Absent</th>";
                    echo "<th>Late</th>";
                    echo "<th>Out</th>";
                    echo "</tr>";
                    
                    foreach ($data as $section) {
                        echo "<tr>";
                        echo "<td>" . htmlspecialchars($section['section_name']) . "</td>";
                        echo "<td>" . htmlspecialchars($section['grade_level']) . "</td>";
                        echo "<td>" . htmlspecialchars($section['students_with_attendance']) . "</td>";
                        echo "<td>" . htmlspecialchars($section['total_records']) . "</td>";
                        echo "<td class='status-present'>" . htmlspecialchars($section['present_count']) . "</td>";
                        echo "<td class='status-absent'>" . htmlspecialchars($section['absent_count']) . "</td>";
                        echo "<td class='status-late'>" . htmlspecialchars($section['late_count']) . "</td>";
                        echo "<td class='status-info'>" . htmlspecialchars($section['out_count']) . "</td>";
                        echo "</tr>";
                    }
                    echo "</table>";
                }
            } else {
                echo "<p>No attendance data found.</p>";
            }
            break;
            
        case 'attendance_per_student':
            if (!empty($data)) {
                // Check if it's a single student or multiple students
                if (isset($data['student'])) {
                    // Single student view
                    $student = $data['student'];
                    $attendance = $data['attendance'];
                    $summary = $data['summary'];
                    
                    echo "<div class='student-card'>";
                    echo "<h2>" . htmlspecialchars($student['full_name']) . "</h2>";
                    
                    echo "<div class='student-info'>";
                    echo "<div><span class='student-label'>Username:</span> " . htmlspecialchars($student['username']) . "</div>";
                    echo "<div><span class='student-label'>LRN:</span> " . htmlspecialchars($student['lrn'] ?? 'Not set') . "</div>";
                    echo "<div><span class='student-label'>Section:</span> " . htmlspecialchars($student['section_name'] ?? 'Not assigned') . "</div>";
                    echo "<div><span class='student-label'>Grade Level:</span> " . htmlspecialchars($student['grade_level'] ?? 'Not assigned') . "</div>";
                    echo "</div>";
                    
                    // Attendance summary by month
                    echo "<div class='summary-box'>";
                    echo "<h3>Monthly Attendance Summary</h3>";
                    echo "<table>";
                    echo "<tr>";
                    echo "<th>Month</th>";
                    echo "<th>Total Records</th>";
                    echo "<th>Present</th>";
                    echo "<th>Absent</th>";
                    echo "<th>Late</th>";
                    echo "<th>Out</th>";
                    echo "</tr>";
                    
                    foreach ($summary as $month) {
                        echo "<tr>";
                        echo "<td>" . date('F Y', strtotime($month['month'] . '-01')) . "</td>";
                        echo "<td>" . htmlspecialchars($month['total_records']) . "</td>";
                        echo "<td class='status-present'>" . htmlspecialchars($month['present_count']) . "</td>";
                        echo "<td class='status-absent'>" . htmlspecialchars($month['absent_count']) . "</td>";
                        echo "<td class='status-late'>" . htmlspecialchars($month['late_count']) . "</td>";
                        echo "<td class='status-info'>" . htmlspecialchars($month['out_count']) . "</td>";
                        echo "</tr>";
                    }
                    echo "</table>";
                    echo "</div>";
                    
                    // Detailed attendance records
                    if (!empty($attendance)) {
                        echo "<h3>Detailed Attendance Records</h3>";
                        echo "<table>";
                        echo "<tr>";
                        echo "<th>Date</th>";
                        echo "<th>Section</th>";
                        echo "<th>Time In</th>";
                        echo "<th>Time Out</th>";
                        echo "<th>Status</th>";
                        echo "<th>Teacher</th>";
                        echo "<th>Remarks</th>";
                        echo "</tr>";
                        
                        foreach ($attendance as $record) {
                            $status_class = 'status-' . strtolower($record['status']);
                            
                            echo "<tr>";
                            echo "<td>" . date('Y-m-d', strtotime($record['attendance_date'])) . "</td>";
                            echo "<td>" . htmlspecialchars($record['section_name']) . "</td>";
                            echo "<td>" . ($record['time_in'] ? date('h:i A', strtotime($record['time_in'])) : '-') . "</td>";
                            echo "<td>" . ($record['time_out'] ? date('h:i A', strtotime($record['time_out'])) : '-') . "</td>";
                            echo "<td class='" . $status_class . "'>" . htmlspecialchars($record['status']) . "</td>";
                            echo "<td>" . htmlspecialchars($record['teacher_name']) . "</td>";
                            echo "<td>" . htmlspecialchars($record['remarks'] ?? '') . "</td>";
                            echo "</tr>";
                        }
                        echo "</table>";
                    } else {
                        echo "<p>No attendance records found for this student.</p>";
                    }
                    echo "</div>";
                } else {
                    // Multiple students view - summary
                    echo "<h2>Attendance Summary by Student</h2>";
                    echo "<table>";
                    echo "<tr>";
                    echo "<th>Student</th>";
                    echo "<th>Section</th>";
                    echo "<th>Grade Level</th>";
                    echo "<th>Total Records</th>";
                    echo "<th>Present</th>";
                    echo "<th>Absent</th>";
                    echo "<th>Late</th>";
                    echo "<th>Out</th>";
                    echo "</tr>";
                    
                    foreach ($data as $student) {
                        echo "<tr>";
                        echo "<td>" . htmlspecialchars($student['full_name']) . "</td>";
                        echo "<td>" . htmlspecialchars($student['section_name'] ?? 'Not assigned') . "</td>";
                        echo "<td>" . htmlspecialchars($student['grade_level'] ?? 'Not assigned') . "</td>";
                        echo "<td>" . htmlspecialchars($student['total_records']) . "</td>";
                        echo "<td class='status-present'>" . htmlspecialchars($student['present_count']) . "</td>";
                        echo "<td class='status-absent'>" . htmlspecialchars($student['absent_count']) . "</td>";
                        echo "<td class='status-late'>" . htmlspecialchars($student['late_count']) . "</td>";
                        echo "<td class='status-info'>" . htmlspecialchars($student['out_count']) . "</td>";
                        echo "</tr>";
                    }
                    echo "</table>";
                }
            } else {
                echo "<p>No attendance data found.</p>";
            }
            break;
            
        default:
            echo "<p>No data found or unsupported report type.</p>";
    }
    
    echo "</body></html>";
}

function outputPDF($data, $filename, $type) {
    // For PDF generation, you would typically use a library like TCPDF or FPDF
    // For now, we'll output HTML with print styles
    outputHTML($data, $filename, $type);
}
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 fw-bold text-primary">
                    <i class="fas fa-chart-bar me-2"></i>Reports
                </h1>
                <p class="text-muted mb-0">Generate and download various reports</p>
            </div>
            <div class="text-end">
                <span class="badge bg-info fs-6">
                    <?php echo ucfirst($user_role); ?>
                </span>
                <div class="small text-muted mt-1" id="current-datetime"></div>
            </div>
        </div>
    </div>
</div>

<!-- Report Categories -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-list me-2"></i>Available Reports
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <!-- Students Reports -->
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card border-primary h-100">
                            <div class="card-header bg-primary text-white">
                                <h6 class="card-title mb-0">
                                    <i class="fas fa-users me-2"></i>Students Reports
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <button class="btn btn-outline-primary btn-sm" onclick="showReportModal('students_list')">
                                        <i class="fas fa-list me-2"></i>Students List
                                    </button>
                                    <button class="btn btn-outline-primary btn-sm" onclick="showReportModal('students_per_section')">
                                        <i class="fas fa-user-friends me-2"></i>Students Per Section
                                    </button>
                                    <?php if (!empty($students)): ?>
                                        <button class="btn btn-outline-primary btn-sm" onclick="showReportModal('student_info')">
                                            <i class="fas fa-id-card me-2"></i>Student Information Sheet
                                        </button>
                                        <button class="btn btn-outline-primary btn-sm" onclick="showReportModal('student_qr')">
                                            <i class="fas fa-qrcode me-2"></i>Student QR Codes
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Attendance Reports -->
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card border-success h-100">
                            <div class="card-header bg-success text-white">
                                <h6 class="card-title mb-0">
                                    <i class="fas fa-calendar-check me-2"></i>Attendance Reports
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <button class="btn btn-outline-success btn-sm" onclick="showReportModal('attendance_records')">
                                        <i class="fas fa-table me-2"></i>Attendance Records
                                    </button>
                                    <button class="btn btn-outline-success btn-sm" onclick="showReportModal('attendance_per_section')">
                                        <i class="fas fa-school me-2"></i>Attendance Per Section
                                    </button>
                                    <button class="btn btn-outline-success btn-sm" onclick="showReportModal('attendance_per_student')">
                                        <i class="fas fa-user-graduate me-2"></i>Attendance Per Student
                                    </button>
                                    <?php if ($user_role == 'admin' || $user_role == 'teacher'): ?>
                                        <button class="btn btn-outline-success btn-sm" onclick="showReportModal('section_attendance')">
                                            <i class="fas fa-clipboard-list me-2"></i>Section Attendance
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Analytics Reports -->
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card border-info h-100">
                            <div class="card-header bg-info text-white">
                                <h6 class="card-title mb-0">
                                    <i class="fas fa-analytics me-2"></i>Analytics Reports
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <button class="btn btn-outline-info btn-sm" onclick="showReportModal('monthly_stats')">
                                        <i class="fas fa-calendar-alt me-2"></i>Monthly Statistics
                                    </button>
                                    <button class="btn btn-outline-info btn-sm" onclick="showReportModal('performance_analysis')">
                                        <i class="fas fa-chart-line me-2"></i>Performance Analysis
                                    </button>
                                    <?php if ($user_role == 'admin'): ?>
                                        <button class="btn btn-outline-info btn-sm" onclick="showReportModal('system_usage')">
                                            <i class="fas fa-server me-2"></i>System Usage
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Stats -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-tachometer-alt me-2"></i>Quick Statistics
                </h5>
            </div>
            <div class="card-body">
                <?php
                try {
                    // Get quick stats based on user role
                    if ($user_role == 'admin') {
                        $total_students = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student' AND status = 'active'")->fetchColumn();
                        $total_teachers = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'teacher' AND status = 'active'")->fetchColumn();
                        $total_sections = $pdo->query("SELECT COUNT(*) FROM sections WHERE status = 'active'")->fetchColumn();
                        $today_attendance = $pdo->query("SELECT COUNT(*) FROM attendance WHERE attendance_date = CURDATE()")->fetchColumn();
                        
                    } elseif ($user_role == 'teacher') {
                        $my_sections = $pdo->prepare("SELECT COUNT(*) FROM sections WHERE teacher_id = ? AND status = 'active'");
                        $my_sections->execute([$current_user['id']]);
                        $total_sections = $my_sections->fetchColumn();
                        
                        $my_students = $pdo->prepare("SELECT COUNT(*) FROM users WHERE section_id IN (SELECT id FROM sections WHERE teacher_id = ?) AND role = 'student' AND status = 'active'");
                        $my_students->execute([$current_user['id']]);
                        $total_students = $my_students->fetchColumn();
                        
                        $today_scans = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE teacher_id = ? AND attendance_date = CURDATE()");
                        $today_scans->execute([$current_user['id']]);
                        $today_attendance = $today_scans->fetchColumn();
                        
                        $total_teachers = 1; // Current teacher
                        
                    } else {
                        $total_students = count($students);
                        $total_teachers = 0;
                        $total_sections = 0;
                        
                        if ($user_role == 'student') {
                            $my_attendance = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE student_id = ? AND attendance_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
                            $my_attendance->execute([$current_user['id']]);
                            $today_attendance = $my_attendance->fetchColumn();
                        } else {
                            $children_attendance = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE student_id IN (SELECT student_id FROM student_parents WHERE parent_id = ?) AND attendance_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
                            $children_attendance->execute([$current_user['id']]);
                            $today_attendance = $children_attendance->fetchColumn();
                        }
                    }
                } catch(PDOException $e) {
                    $total_students = $total_teachers = $total_sections = $today_attendance = 0;
                }
                ?>
                
                <div class="row text-center">
                    <div class="col-6 col-md-3 mb-3">
                        <div class="border rounded p-3">
                            <h4 class="text-primary fw-bold"><?php echo number_format($total_students); ?></h4>
                            <small class="text-muted">
                                <?php echo $user_role == 'parent' ? 'Children' : 'Students'; ?>
                            </small>
                        </div>
                    </div>
                    
                    <?php if ($user_role == 'admin' || $user_role == 'teacher'): ?>
                        <div class="col-6 col-md-3 mb-3">
                            <div class="border rounded p-3">
                                <h4 class="text-success fw-bold"><?php echo number_format($total_teachers); ?></h4>
                                <small class="text-muted">Teachers</small>
                            </div>
                        </div>
                        
                        <div class="col-6 col-md-3 mb-3">
                            <div class="border rounded p-3">
                                <h4 class="text-info fw-bold"><?php echo number_format($total_sections); ?></h4>
                                <small class="text-muted">Sections</small>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="col-6 col-md-3 mb-3">
                        <div class="border rounded p-3">
                            <h4 class="text-warning fw-bold"><?php echo number_format($today_attendance); ?></h4>
                            <small class="text-muted">
                                <?php 
                                if ($user_role == 'admin' || $user_role == 'teacher') echo "Today's Records";
                                else echo "Recent Records";
                                ?>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Reports -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-history me-2"></i>Quick Report Generation
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 col-lg-4 mb-3">
                        <div class="card border-0 bg-light">
                            <div class="card-body text-center">
                                <i class="fas fa-users fa-2x text-primary mb-3"></i>
                                <h6 class="fw-bold">All Students</h6>
                                <p class="small text-muted mb-3">Complete list of all students</p>
                                <div class="btn-group-vertical d-grid gap-1">
                                    <a href="?generate=1&type=students_list&format=html" class="btn btn-sm btn-outline-primary" target="_blank">
                                        <i class="fas fa-eye me-1"></i>View
                                    </a>
                                    <a href="?generate=1&type=students_list&format=csv" class="btn btn-sm btn-primary">
                                        <i class="fas fa-download me-1"></i>Download CSV
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6 col-lg-4 mb-3">
                        <div class="card border-0 bg-light">
                            <div class="card-body text-center">
                                <i class="fas fa-calendar-check fa-2x text-success mb-3"></i>
                                <h6 class="fw-bold">This Month's Attendance</h6>
                                <p class="small text-muted mb-3">Current month attendance records</p>
                                <div class="btn-group-vertical d-grid gap-1">
                                    <a href="?generate=1&type=attendance_records&format=html&date_from=<?php echo date('Y-m-01'); ?>&date_to=<?php echo date('Y-m-t'); ?>" class="btn btn-sm btn-outline-success" target="_blank">
                                        <i class="fas fa-eye me-1"></i>View
                                    </a>
                                    <a href="?generate=1&type=attendance_records&format=csv&date_from=<?php echo date('Y-m-01'); ?>&date_to=<?php echo date('Y-m-t'); ?>" class="btn btn-sm btn-success">
                                        <i class="fas fa-download me-1"></i>Download CSV
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6 col-lg-4 mb-3">
                        <div class="card border-0 bg-light">
                            <div class="card-body text-center">
                                <i class="fas fa-chart-pie fa-2x text-info mb-3"></i>
                                <h6 class="fw-bold">Today's Summary</h6>
                                <p class="small text-muted mb-3">Today's attendance summary</p>
                                <div class="btn-group-vertical d-grid gap-1">
                                    <a href="?generate=1&type=attendance_records&format=html&date_from=<?php echo date('Y-m-d'); ?>&date_to=<?php echo date('Y-m-d'); ?>" class="btn btn-sm btn-outline-info" target="_blank">
                                        <i class="fas fa-eye me-1"></i>View
                                    </a>
                                    <a href="?generate=1&type=attendance_records&format=csv&date_from=<?php echo date('Y-m-d'); ?>&date_to=<?php echo date('Y-m-d'); ?>" class="btn btn-sm btn-info">
                                        <i class="fas fa-download me-1"></i>Download CSV
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Database Records Tables -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-database me-2"></i>Database Records
                </h5>
            </div>
            <div class="card-body">
                <!-- Mobile-friendly tabs with dropdown for small screens -->
                <div class="d-md-none mb-3">
                    <label for="tabSelector" class="form-label">Select Report Type</label>
                    <select class="form-select" id="tabSelector">
                        <option value="users">Users</option>
                        <option value="sections">Sections</option>
                        <option value="attendance">Attendance</option>
                        <option value="students-section">Students Per Section</option>
                        <option value="qr-codes">QR Codes</option>
                        <option value="attendance-section">Attendance Per Section</option>
                        <option value="attendance-student">Attendance Per Student</option>
                        <?php if ($user_role == 'admin'): ?>
                        <option value="sms">SMS Logs</option>
                        <?php endif; ?>
                    </select>
                </div>
                
                <!-- Regular tabs for larger screens -->
                <ul class="nav nav-tabs d-none d-md-flex" id="recordsTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="users-tab" data-bs-toggle="tab" data-bs-target="#users" type="button" role="tab" aria-controls="users" aria-selected="true">Users</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="sections-tab" data-bs-toggle="tab" data-bs-target="#sections" type="button" role="tab" aria-controls="sections" aria-selected="false">Sections</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="attendance-tab" data-bs-toggle="tab" data-bs-target="#attendance" type="button" role="tab" aria-controls="attendance" aria-selected="false">Attendance</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="students-section-tab" data-bs-toggle="tab" data-bs-target="#students-section" type="button" role="tab" aria-controls="students-section" aria-selected="false">Students Per Section</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="qr-codes-tab" data-bs-toggle="tab" data-bs-target="#qr-codes" type="button" role="tab" aria-controls="qr-codes" aria-selected="false">QR Codes</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="attendance-section-tab" data-bs-toggle="tab" data-bs-target="#attendance-section" type="button" role="tab" aria-controls="attendance-section" aria-selected="false">Attendance Per Section</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="attendance-student-tab" data-bs-toggle="tab" data-bs-target="#attendance-student" type="button" role="tab" aria-controls="attendance-student" aria-selected="false">Attendance Per Student</button>
                    </li>
                    <?php if ($user_role == 'admin'): ?>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="sms-tab" data-bs-toggle="tab" data-bs-target="#sms" type="button" role="tab" aria-controls="sms" aria-selected="false">SMS Logs</button>
                    </li>
                    <?php endif; ?>
                </ul>
                
                <!-- Tab content -->
                <div class="tab-content p-3 border border-top-0 rounded-bottom" id="recordsTabContent">
                    <!-- Users Cards -->
                    <div class="tab-pane fade show active" id="users" role="tabpanel" aria-labelledby="users-tab">
                        <div class="mb-3 d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="fas fa-users me-2"></i>User Records</h5>
                            <button class="btn btn-sm btn-outline-secondary print-tab-content" data-target="users">
                                <i class="fas fa-print me-1"></i>Print All
                            </button>
                        </div>
                        
                        <!-- Search and filter controls -->
                        <div class="card mb-3">
                            <div class="card-header p-2 d-md-none">
                                <button class="btn btn-sm btn-outline-primary w-100" type="button" data-bs-toggle="collapse" data-bs-target="#userFiltersCollapse">
                                    <i class="fas fa-filter me-1"></i>Search & Filters
                                </button>
                            </div>
                            <div class="collapse d-md-block" id="userFiltersCollapse">
                                <div class="card-body p-2 p-md-3">
                                    <div class="row g-2">
                                        <div class="col-12 col-md-4">
                                            <label for="userSearchInput" class="form-label small mb-1">Search</label>
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                                                <input type="text" class="form-control form-control-sm" id="userSearchInput" placeholder="Name, username, email...">
                                            </div>
                                        </div>
                                        <div class="col-6 col-md-4">
                                            <label for="userRoleFilter" class="form-label small mb-1">Role</label>
                                            <select class="form-select form-select-sm select2" id="userRoleFilter">
                                                <option value="">All Roles</option>
                                                <option value="admin">Admin</option>
                                                <option value="teacher">Teacher</option>
                                                <option value="student">Student</option>
                                                <option value="parent">Parent</option>
                                            </select>
                                        </div>
                                        <div class="col-6 col-md-4">
                                            <label for="userStatusFilter" class="form-label small mb-1">Status</label>
                                            <select class="form-select form-select-sm select2" id="userStatusFilter">
                                                <option value="">All Statuses</option>
                                                <option value="active">Active</option>
                                                <option value="inactive">Inactive</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                            <?php
                            try {
                                $query = "SELECT u.id, u.full_name, u.username, u.role, u.email, u.phone, s.section_name, u.status, u.profile_image 
                                        FROM users u 
                                        LEFT JOIN sections s ON u.section_id = s.id";
                                
                                // Limit records based on user role
                                if ($user_role == 'teacher') {
                                    $query .= " WHERE u.section_id IN (SELECT id FROM sections WHERE teacher_id = ?) OR u.id = ?";
                                    $stmt = $pdo->prepare($query);
                                    $stmt->execute([$current_user['id'], $current_user['id']]);
                                } else if ($user_role == 'parent') {
                                    $query .= " WHERE u.id IN (SELECT student_id FROM student_parents WHERE parent_id = ?) OR u.id = ?";
                                    $stmt = $pdo->prepare($query);
                                    $stmt->execute([$current_user['id'], $current_user['id']]);
                                } else if ($user_role == 'student') {
                                    $query .= " WHERE u.id = ? OR u.id IN (SELECT teacher_id FROM sections WHERE id = ?)";
                                    $stmt = $pdo->prepare($query);
                                    $stmt->execute([$current_user['id'], $current_user['section_id']]);
                                } else {
                                    // Admin sees all
                                    $stmt = $pdo->query($query);
                                }
                                
                                $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                
                                foreach ($users as $user) {
                                    $role_color = $user['role'] == 'admin' ? 'danger' : 
                                                ($user['role'] == 'teacher' ? 'primary' : 
                                                ($user['role'] == 'student' ? 'success' : 'info'));
                                    
                                    echo '<div class="col">';
                                    echo '<div class="card h-100 border-' . $role_color . ' shadow-sm">';
                                    echo '<div class="card-header bg-' . $role_color . ' bg-opacity-10 d-flex justify-content-between align-items-center">';
                                    echo '<span class="badge bg-' . $role_color . '">' . ucfirst($user['role']) . '</span>';
                                    echo '<span class="badge bg-' . ($user['status'] == 'active' ? 'success' : 'secondary') . '">' . $user['status'] . '</span>';
                                    echo '</div>';
                                    
                                    echo '<div class="card-body">';
                                    echo '<div class="text-center mb-3">';
                                    echo '<div class="avatar-circle mx-auto mb-2">';
                                    
                                    // Default profile image based on role if no image is set
                                    $profile_icon = $user['role'] == 'admin' ? 'user-tie' : 
                                                ($user['role'] == 'teacher' ? 'chalkboard-teacher' : 
                                                ($user['role'] == 'student' ? 'user-graduate' : 'user'));
                                                
                                    if (!empty($user['profile_image'])) {
                                        echo '<img src="uploads/student_photos/' . htmlspecialchars($user['profile_image']) . '" alt="Profile" class="img-fluid rounded-circle" style="width: 80px; height: 80px; object-fit: cover;">';
                                    } else {
                                        echo '<div class="bg-' . $role_color . ' bg-opacity-25 rounded-circle d-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">';
                                        echo '<i class="fas fa-' . $profile_icon . ' fa-2x text-' . $role_color . '"></i>';
                                        echo '</div>';
                                    }
                                    
                                    echo '</div>'; // End avatar-circle
                                    echo '<h5 class="card-title mb-0">' . htmlspecialchars($user['full_name']) . '</h5>';
                                    echo '<p class="text-muted small mb-0">@' . htmlspecialchars($user['username']) . '</p>';
                                    echo '</div>'; // End text-center
                                    
                                    echo '<ul class="list-group list-group-flush">';
                                    if (!empty($user['email'])) {
                                        echo '<li class="list-group-item px-0 py-2 d-flex align-items-center">';
                                        echo '<i class="fas fa-envelope text-muted me-2"></i>';
                                        echo '<span class="small text-truncate">' . htmlspecialchars($user['email']) . '</span>';
                                        echo '</li>';
                                    }
                                    
                                    if (!empty($user['phone'])) {
                                        echo '<li class="list-group-item px-0 py-2 d-flex align-items-center">';
                                        echo '<i class="fas fa-phone text-muted me-2"></i>';
                                        echo '<span class="small">' . htmlspecialchars($user['phone']) . '</span>';
                                        echo '</li>';
                                    }
                                    
                                    if (!empty($user['section_name'])) {
                                        echo '<li class="list-group-item px-0 py-2 d-flex align-items-center">';
                                        echo '<i class="fas fa-users text-muted me-2"></i>';
                                        echo '<span class="small">' . htmlspecialchars($user['section_name']) . '</span>';
                                        echo '</li>';
                                    }
                                    echo '</ul>';
                                    
                                    echo '</div>'; // End card-body
                                    
                                    echo '</div>'; // End card
                                    echo '</div>'; // End col
                                }
                            } catch(PDOException $e) {
                                echo '<div class="col-12 text-danger">Error loading user data: ' . htmlspecialchars($e->getMessage()) . '</div>';
                            }
                            ?>
                        </div>
                    </div>
                    
                    <!-- Sections Cards -->
                    <div class="tab-pane fade" id="sections" role="tabpanel" aria-labelledby="sections-tab">
                        <div class="mb-3 d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="fas fa-school me-2"></i>Section Records</h5>
                            <button class="btn btn-sm btn-outline-secondary print-tab-content" data-target="sections">
                                <i class="fas fa-print me-1"></i>Print All
                            </button>
                        </div>
                        
                        <!-- Search and filter controls -->
                        <div class="card mb-3">
                            <div class="card-header p-2 d-md-none">
                                <button class="btn btn-sm btn-outline-primary w-100" type="button" data-bs-toggle="collapse" data-bs-target="#sectionFiltersCollapse">
                                    <i class="fas fa-filter me-1"></i>Search & Filters
                                </button>
                            </div>
                            <div class="collapse d-md-block" id="sectionFiltersCollapse">
                                <div class="card-body p-2 p-md-3">
                                    <div class="row g-2">
                                        <div class="col-12 col-md-4">
                                            <label for="sectionSearchInput" class="form-label small mb-1">Search</label>
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                                                <input type="text" class="form-control form-control-sm" id="sectionSearchInput" placeholder="Section name...">
                                            </div>
                                        </div>
                                        <div class="col-6 col-md-4">
                                            <label for="gradeLevelFilter" class="form-label small mb-1">Grade Level</label>
                                            <select class="form-select form-select-sm select2" id="gradeLevelFilter">
                                                <option value="">All Grades</option>
                                                <?php
                                                try {
                                                    $grade_levels = $pdo->query("SELECT DISTINCT grade_level FROM sections ORDER BY grade_level")->fetchAll(PDO::FETCH_COLUMN);
                                                    foreach ($grade_levels as $grade) {
                                                        echo '<option value="' . htmlspecialchars($grade) . '">' . htmlspecialchars($grade) . '</option>';
                                                    }
                                                } catch(PDOException $e) {
                                                    // Silently fail
                                                }
                                                ?>
                                            </select>
                                        </div>
                                        <div class="col-6 col-md-4">
                                            <label for="sectionStatusFilter" class="form-label small mb-1">Status</label>
                                            <select class="form-select form-select-sm select2" id="sectionStatusFilter">
                                                <option value="">All Statuses</option>
                                                <option value="active">Active</option>
                                                <option value="inactive">Inactive</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                            <?php
                            try {
                                $query = "SELECT s.id, s.section_name, s.grade_level, u.full_name as teacher_name, s.description, s.status,
                                        (SELECT COUNT(*) FROM users WHERE section_id = s.id AND role = 'student') as student_count
                                        FROM sections s
                                        LEFT JOIN users u ON s.teacher_id = u.id";
                                
                                // Limit records based on user role
                                if ($user_role == 'teacher') {
                                    $query .= " WHERE s.teacher_id = ?";
                                    $stmt = $pdo->prepare($query);
                                    $stmt->execute([$current_user['id']]);
                                } else if ($user_role == 'student') {
                                    $query .= " WHERE s.id = ?";
                                    $stmt = $pdo->prepare($query);
                                    $stmt->execute([$current_user['section_id']]);
                                } else if ($user_role == 'parent') {
                                    $query .= " WHERE s.id IN (SELECT section_id FROM users WHERE id IN (SELECT student_id FROM student_parents WHERE parent_id = ?))";
                                    $stmt = $pdo->prepare($query);
                                    $stmt->execute([$current_user['id']]);
                                } else {
                                    // Admin sees all
                                    $stmt = $pdo->query($query);
                                }
                                
                                $sections = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                
                                foreach ($sections as $section) {
                                    echo '<div class="col">';
                                    echo '<div class="card h-100 border-info shadow-sm">';
                                    
                                    echo '<div class="card-header bg-info bg-opacity-10 d-flex justify-content-between align-items-center">';
                                    echo '<span class="fw-bold">' . htmlspecialchars($section['grade_level']) . '</span>';
                                    echo '<span class="badge bg-' . ($section['status'] == 'active' ? 'success' : 'secondary') . '">' . $section['status'] . '</span>';
                                    echo '</div>';
                                    
                                    echo '<div class="card-body">';
                                    echo '<div class="text-center mb-3">';
                                    echo '<div class="avatar-circle mx-auto mb-2">';
                                    echo '<div class="bg-info bg-opacity-25 rounded-circle d-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">';
                                    echo '<i class="fas fa-school fa-2x text-info"></i>';
                                    echo '</div>';
                                    echo '</div>'; // End avatar-circle
                                    
                                    echo '<h5 class="card-title mb-0">' . htmlspecialchars($section['section_name']) . '</h5>';
                                    echo '</div>'; // End text-center
                                    
                                    echo '<ul class="list-group list-group-flush">';
                                    if (!empty($section['teacher_name'])) {
                                        echo '<li class="list-group-item px-0 py-2 d-flex align-items-center">';
                                        echo '<i class="fas fa-chalkboard-teacher text-muted me-2"></i>';
                                        echo '<span class="small">Teacher: ' . htmlspecialchars($section['teacher_name']) . '</span>';
                                        echo '</li>';
                                    }
                                    
                                    echo '<li class="list-group-item px-0 py-2 d-flex align-items-center">';
                                    echo '<i class="fas fa-user-graduate text-muted me-2"></i>';
                                    echo '<span class="small">Students: <span class="badge bg-info">' . $section['student_count'] . '</span></span>';
                                    echo '</li>';
                                    
                                    if (!empty($section['description'])) {
                                        echo '<li class="list-group-item px-0 py-2">';
                                        echo '<i class="fas fa-info-circle text-muted me-2"></i>';
                                        echo '<span class="small">' . (strlen($section['description']) > 100 ? substr(htmlspecialchars($section['description']), 0, 100) . '...' : htmlspecialchars($section['description'])) . '</span>';
                                        echo '</li>';
                                    }
                                    echo '</ul>';
                                    echo '</div>'; // End card-body
                                    
                                    echo '</div>'; // End card
                                    echo '</div>'; // End col
                                }
                            } catch(PDOException $e) {
                                echo '<div class="col-12 text-danger">Error loading section data: ' . htmlspecialchars($e->getMessage()) . '</div>';
                            }
                            ?>
                        </div>
                    </div>
                    
                    <!-- Attendance Cards -->
                    <div class="tab-pane fade" id="attendance" role="tabpanel" aria-labelledby="attendance-tab">
                        <div class="mb-3 d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="fas fa-calendar-check me-2"></i>Attendance Records</h5>
                            <button class="btn btn-sm btn-outline-secondary print-tab-content" data-target="attendance">
                                <i class="fas fa-print me-1"></i>Print All
                            </button>
                        </div>
                        
                        <!-- Search and filter controls -->
                        <div class="card mb-3">
                            <div class="card-header p-2 d-md-none">
                                <button class="btn btn-sm btn-outline-primary w-100" type="button" data-bs-toggle="collapse" data-bs-target="#attendanceFiltersCollapse">
                                    <i class="fas fa-filter me-1"></i>Search & Filters
                                </button>
                            </div>
                            <div class="collapse d-md-block" id="attendanceFiltersCollapse">
                                <div class="card-body p-2 p-md-3">
                                    <div class="row g-2">
                                        <div class="col-12 col-md-3">
                                            <label for="attendanceSearchInput" class="form-label small mb-1">Search</label>
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                                                <input type="text" class="form-control form-control-sm" id="attendanceSearchInput" placeholder="Student name...">
                                            </div>
                                        </div>
                                        <div class="col-6 col-md-3">
                                            <label for="attendanceStatusFilter" class="form-label small mb-1">Status</label>
                                            <select class="form-select form-select-sm select2" id="attendanceStatusFilter">
                                                <option value="">All Statuses</option>
                                                <option value="present">Present</option>
                                                <option value="absent">Absent</option>
                                                <option value="late">Late</option>
                                                <option value="out">Out</option>
                                            </select>
                                        </div>
                                        <div class="col-6 col-md-3">
                                            <label for="attendanceDateFilter" class="form-label small mb-1">Date</label>
                                            <select class="form-select form-select-sm select2" id="attendanceDateFilter">
                                                <option value="">All Dates</option>
                                                <option value="today">Today</option>
                                                <option value="yesterday">Yesterday</option>
                                                <option value="thisweek">This Week</option>
                                                <option value="lastweek">Last Week</option>
                                            </select>
                                        </div>
                                        <div class="col-12 col-md-3">
                                            <label for="attendanceSectionFilter" class="form-label small mb-1">Section</label>
                                            <select class="form-select form-select-sm select2" id="attendanceSectionFilter">
                                                <option value="">All Sections</option>
                                                <?php foreach ($sections as $section): ?>
                                                    <option value="<?php echo htmlspecialchars($section['section_name']); ?>">
                                                        <?php echo htmlspecialchars($section['section_name']); ?> - <?php echo htmlspecialchars($section['grade_level']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                            <?php
                            try {
                                $query = "SELECT a.id, a.attendance_date, a.time_in, a.time_out, a.status, a.remarks,
                                        u.full_name as student_name, u.username, u.profile_image,
                                        s.section_name, t.full_name as teacher_name
                                        FROM attendance a
                                        JOIN users u ON a.student_id = u.id
                                        JOIN sections s ON a.section_id = s.id
                                        JOIN users t ON a.teacher_id = t.id
                                        WHERE a.attendance_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
                                
                                // Limit records based on user role
                                if ($user_role == 'teacher') {
                                    $query .= " AND a.teacher_id = ?";
                                    $stmt = $pdo->prepare($query);
                                    $stmt->execute([$current_user['id']]);
                                } else if ($user_role == 'student') {
                                    $query .= " AND a.student_id = ?";
                                    $stmt = $pdo->prepare($query);
                                    $stmt->execute([$current_user['id']]);
                                } else if ($user_role == 'parent') {
                                    $query .= " AND a.student_id IN (SELECT student_id FROM student_parents WHERE parent_id = ?)";
                                    $stmt = $pdo->prepare($query);
                                    $stmt->execute([$current_user['id']]);
                                } else {
                                    // Admin sees all (but limited to last 7 days)
                                    $stmt = $pdo->query($query);
                                }
                                
                                $attendance_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                
                                foreach ($attendance_records as $record) {
                                    $status_color = $record['status'] == 'present' ? 'success' : 
                                                ($record['status'] == 'absent' ? 'danger' : 
                                                ($record['status'] == 'late' ? 'warning' : 'info'));
                                    
                                    echo '<div class="col">';
                                    echo '<div class="card h-100 border-' . $status_color . ' shadow-sm">';
                                    
                                    echo '<div class="card-header bg-' . $status_color . ' bg-opacity-10 d-flex justify-content-between align-items-center">';
                                    echo '<span class="badge bg-' . $status_color . '">' . ucfirst($record['status']) . '</span>';
                                    echo '<span class="small">' . date('M d, Y', strtotime($record['attendance_date'])) . '</span>';
                                    echo '</div>';
                                    
                                    echo '<div class="card-body">';
                                    echo '<div class="d-flex align-items-center mb-3">';
                                    
                                    // Student profile image or icon
                                    echo '<div class="me-3">';
                                    if (!empty($record['profile_image'])) {
                                        echo '<img src="' . htmlspecialchars($record['profile_image']) . '" alt="Profile" class="rounded-circle" style="width: 50px; height: 50px; object-fit: cover;">';
                                    } else {
                                        echo '<div class="bg-success bg-opacity-25 rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">';
                                        echo '<i class="fas fa-user-graduate fa-lg text-success"></i>';
                                        echo '</div>';
                                    }
                                    echo '</div>'; // End profile image div
                                    
                                    echo '<div>';
                                    echo '<h6 class="mb-0">' . htmlspecialchars($record['student_name']) . '</h6>';
                                    echo '<p class="text-muted small mb-0">@' . htmlspecialchars($record['username']) . '</p>';
                                    echo '</div>';
                                    echo '</div>'; // End d-flex
                                    
                                    echo '<ul class="list-group list-group-flush">';
                                    echo '<li class="list-group-item px-0 py-2 d-flex align-items-center">';
                                    echo '<i class="fas fa-clock text-muted me-2"></i>';
                                    echo '<span class="small">Time: ' . 
                                        ($record['time_in'] ? date('h:i A', strtotime($record['time_in'])) : '-') . 
                                        ' - ' . 
                                        ($record['time_out'] ? date('h:i A', strtotime($record['time_out'])) : '-') . '</span>';
                                    echo '</li>';
                                    
                                    echo '<li class="list-group-item px-0 py-2 d-flex align-items-center">';
                                    echo '<i class="fas fa-users text-muted me-2"></i>';
                                    echo '<span class="small">Section: ' . htmlspecialchars($record['section_name']) . '</span>';
                                    echo '</li>';
                                    
                                    echo '<li class="list-group-item px-0 py-2 d-flex align-items-center">';
                                    echo '<i class="fas fa-chalkboard-teacher text-muted me-2"></i>';
                                    echo '<span class="small">Teacher: ' . htmlspecialchars($record['teacher_name']) . '</span>';
                                    echo '</li>';
                                    
                                    if (!empty($record['remarks'])) {
                                        echo '<li class="list-group-item px-0 py-2">';
                                        echo '<i class="fas fa-comment text-muted me-2"></i>';
                                        echo '<span class="small">' . htmlspecialchars($record['remarks']) . '</span>';
                                        echo '</li>';
                                    }
                                    echo '</ul>';
                                    echo '</div>'; // End card-body
                                    
                                    echo '</div>'; // End card
                                    echo '</div>'; // End col
                                }
                            } catch(PDOException $e) {
                                echo '<div class="col-12 text-danger">Error loading attendance data: ' . htmlspecialchars($e->getMessage()) . '</div>';
                            }
                            ?>
                        </div>
                    </div>
                    
                    <!-- SMS Logs Cards (Admin Only) -->
                    <?php if ($user_role == 'admin'): ?>
                    <div class="tab-pane fade" id="sms" role="tabpanel" aria-labelledby="sms-tab">
                        <div class="mb-3 d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="fas fa-sms me-2"></i>SMS Logs</h5>
                            <button class="btn btn-sm btn-outline-secondary print-tab-content" data-target="sms">
                                <i class="fas fa-print me-1"></i>Print All
                            </button>
                        </div>
                        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                            <?php
                            try {
                                $query = "SELECT id, phone_number, message, notification_type, status, sent_at, response 
                                        FROM sms_logs 
                                        ORDER BY sent_at DESC LIMIT 50";
                                $stmt = $pdo->query($query);
                                $sms_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                
                                foreach ($sms_logs as $log) {
                                    $status_color = $log['status'] == 'sent' ? 'success' : 
                                                ($log['status'] == 'failed' ? 'danger' : 'warning');
                                    
                                    echo '<div class="col">';
                                    echo '<div class="card h-100 border-' . $status_color . ' shadow-sm">';
                                    
                                    echo '<div class="card-header bg-' . $status_color . ' bg-opacity-10 d-flex justify-content-between align-items-center">';
                                    echo '<span class="badge bg-' . $status_color . '">' . ucfirst($log['status']) . '</span>';
                                    echo '<span class="small">' . date('M d, Y h:i A', strtotime($log['sent_at'])) . '</span>';
                                    echo '</div>';
                                    
                                    echo '<div class="card-body">';
                                    echo '<div class="mb-3">';
                                    echo '<div class="d-flex align-items-center mb-2">';
                                    echo '<i class="fas fa-sms fa-lg text-' . $status_color . ' me-2"></i>';
                                    echo '<h6 class="mb-0">' . ucfirst($log['notification_type']) . ' Message</h6>';
                                    echo '</div>';
                                    echo '<p class="small text-muted mb-0">To: ' . htmlspecialchars($log['phone_number']) . '</p>';
                                    echo '</div>';
                                    
                                    echo '<div class="bg-light p-2 rounded mb-3">';
                                    echo '<p class="small mb-0">' . htmlspecialchars($log['message']) . '</p>';
                                    echo '</div>';
                                    
                                    if (!empty($log['response'])) {
                                        echo '<div class="small">';
                                        echo '<strong>Response:</strong>';
                                        echo '<p class="text-muted mb-0 small">' . (strlen($log['response']) > 100 ? substr(htmlspecialchars($log['response']), 0, 100) . '...' : htmlspecialchars($log['response'])) . '</p>';
                                        echo '</div>';
                                    }
                                    
                                    echo '</div>'; // End card-body
                                    
                                    echo '</div>'; // End card
                                    echo '</div>'; // End col
                                }
                            } catch(PDOException $e) {
                                echo '<div class="col-12 text-danger">Error loading SMS logs: ' . htmlspecialchars($e->getMessage()) . '</div>';
                            }
                            ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Students Per Section Tab -->
                    <div class="tab-pane fade" id="students-section" role="tabpanel" aria-labelledby="students-section-tab">
                        <div class="mb-3 d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="fas fa-user-friends me-2"></i>Students Per Section</h5>
                            <button class="btn btn-sm btn-outline-secondary print-tab-content" data-target="students-section">
                                <i class="fas fa-print me-1"></i>Print All
                            </button>
                        </div>
                        
                        <!-- Search and filter controls -->
                        <div class="card mb-3">
                            <div class="card-header p-2 d-md-none">
                                <button class="btn btn-sm btn-outline-primary w-100" type="button" data-bs-toggle="collapse" data-bs-target="#studentsSectionFiltersCollapse">
                                    <i class="fas fa-filter me-1"></i>Search & Filters
                                </button>
                            </div>
                            <div class="collapse d-md-block" id="studentsSectionFiltersCollapse">
                                <div class="card-body p-2 p-md-3">
                                    <div class="row g-2">
                                        <div class="col-12 col-md-6">
                                            <label for="sectionStudentSearchInput" class="form-label small mb-1">Search</label>
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                                                <input type="text" class="form-control form-control-sm" id="sectionStudentSearchInput" placeholder="Section or student name...">
                                            </div>
                                        </div>
                                        <div class="col-12 col-md-6">
                                            <label for="sectionGradeFilter" class="form-label small mb-1">Grade Level</label>
                                            <select class="form-select form-select-sm select2" id="sectionGradeFilter">
                                                <option value="">All Grades</option>
                                                <?php
                                                try {
                                                    $grade_levels = $pdo->query("SELECT DISTINCT grade_level FROM sections ORDER BY grade_level")->fetchAll(PDO::FETCH_COLUMN);
                                                    foreach ($grade_levels as $grade) {
                                                        echo '<option value="' . htmlspecialchars($grade) . '">' . htmlspecialchars($grade) . '</option>';
                                                    }
                                                } catch(PDOException $e) {
                                                    // Silently fail
                                                }
                                                ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <?php
                        try {
                            // Get all sections with their students
                            $query = "
                                SELECT s.id, s.section_name, s.grade_level, 
                                       u.full_name as teacher_name,
                                       (SELECT COUNT(*) FROM users WHERE section_id = s.id AND role = 'student' AND status = 'active') as student_count
                                FROM sections s
                                LEFT JOIN users u ON s.teacher_id = u.id
                                WHERE s.status = 'active'
                            ";
                            
                            if ($user_role == 'teacher') {
                                $query .= " AND s.teacher_id = ?";
                                $params = [$current_user['id']];
                            } else {
                                $params = [];
                            }
                            
                            $query .= " ORDER BY s.grade_level, s.section_name";
                            
                            $stmt = $pdo->prepare($query);
                            $stmt->execute($params);
                            $sections = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            foreach ($sections as $section) {
                                echo '<div class="card mb-4">';
                                echo '<div class="card-header bg-primary bg-opacity-10">';
                                echo '<div class="d-flex justify-content-between align-items-center">';
                                echo '<h5 class="mb-0 text-primary">' . htmlspecialchars($section['section_name']) . ' - ' . htmlspecialchars($section['grade_level']) . '</h5>';
                                echo '<span class="badge bg-info">' . $section['student_count'] . ' Students</span>';
                                echo '</div>';
                                echo '<p class="text-muted mb-0 small">Teacher: ' . htmlspecialchars($section['teacher_name'] ?? 'Not Assigned') . '</p>';
                                echo '</div>';
                                
                                // Get students in this section
                                $students_query = "
                                    SELECT id, full_name, username, lrn, email, phone, profile_image
                                    FROM users 
                                    WHERE section_id = ? AND role = 'student' AND status = 'active'
                                    ORDER BY full_name
                                ";
                                $students_stmt = $pdo->prepare($students_query);
                                $students_stmt->execute([$section['id']]);
                                $students = $students_stmt->fetchAll(PDO::FETCH_ASSOC);
                                
                                echo '<div class="card-body">';
                                if (!empty($students)) {
                                    echo '<div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-3">';
                                    foreach ($students as $student) {
                                        echo '<div class="col">';
                                        echo '<div class="card h-100 border-success shadow-sm">';
                                        
                                        echo '<div class="card-body">';
                                        echo '<div class="d-flex align-items-center mb-3">';
                                        
                                        // Student profile image or icon
                                        echo '<div class="me-3">';
                                        if (!empty($student['profile_image'])) {
                                            echo '<img src="' . htmlspecialchars($student['profile_image']) . '" alt="Profile" class="rounded-circle" style="width: 50px; height: 50px; object-fit: cover;">';
                                        } else {
                                            echo '<div class="bg-success bg-opacity-25 rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">';
                                            echo '<i class="fas fa-user-graduate fa-lg text-success"></i>';
                                            echo '</div>';
                                        }
                                        echo '</div>'; // End profile image div
                                        
                                        echo '<div>';
                                        echo '<h6 class="mb-0">' . htmlspecialchars($student['full_name']) . '</h6>';
                                        echo '<p class="text-muted small mb-0">@' . htmlspecialchars($student['username']) . '</p>';
                                        echo '</div>';
                                        echo '</div>'; // End d-flex
                                        
                                        echo '<ul class="list-group list-group-flush">';
                                        if (!empty($student['lrn'])) {
                                            echo '<li class="list-group-item px-0 py-2 d-flex align-items-center">';
                                            echo '<i class="fas fa-id-card text-muted me-2"></i>';
                                            echo '<span class="small">LRN: ' . htmlspecialchars($student['lrn']) . '</span>';
                                            echo '</li>';
                                        }
                                        
                                        if (!empty($student['email'])) {
                                            echo '<li class="list-group-item px-0 py-2 d-flex align-items-center">';
                                            echo '<i class="fas fa-envelope text-muted me-2"></i>';
                                            echo '<span class="small text-truncate">' . htmlspecialchars($student['email']) . '</span>';
                                            echo '</li>';
                                        }
                                        
                                        if (!empty($student['phone'])) {
                                            echo '<li class="list-group-item px-0 py-2 d-flex align-items-center">';
                                            echo '<i class="fas fa-phone text-muted me-2"></i>';
                                            echo '<span class="small">' . htmlspecialchars($student['phone']) . '</span>';
                                            echo '</li>';
                                        }
                                        echo '</ul>';
                                        
                                        echo '</div>'; // End card-body
                                        echo '</div>'; // End card
                                        echo '</div>'; // End col
                                    }
                                    echo '</div>'; // End row
                                } else {
                                    echo '<div class="alert alert-info">No students assigned to this section.</div>';
                                }
                                echo '</div>'; // End card-body
                                echo '</div>'; // End section card
                            }
                            
                            if (empty($sections)) {
                                echo '<div class="alert alert-info">No sections found.</div>';
                            }
                        } catch(PDOException $e) {
                            echo '<div class="alert alert-danger">Error loading section data: ' . htmlspecialchars($e->getMessage()) . '</div>';
                        }
                        ?>
                    </div>
                    
                    <!-- QR Codes Tab -->
                    <div class="tab-pane fade" id="qr-codes" role="tabpanel" aria-labelledby="qr-codes-tab">
                        <div class="mb-3 d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="fas fa-qrcode me-2"></i>Student QR Codes</h5>
                            <div class="btn-group">
                                <button class="btn btn-sm btn-outline-primary" id="downloadAllQR">
                                    <i class="fas fa-download me-1"></i>Download All
                                </button>
                                <button class="btn btn-sm btn-outline-secondary" id="printAllQR">
                                    <i class="fas fa-print me-1"></i>Print All
                                </button>
                            </div>
                        </div>
                        
                        <!-- Search and filter controls -->
                        <div class="card mb-3">
                            <div class="card-header p-2 d-md-none">
                                <button class="btn btn-sm btn-outline-primary w-100" type="button" data-bs-toggle="collapse" data-bs-target="#qrFiltersCollapse">
                                    <i class="fas fa-filter me-1"></i>Search & Filters
                                </button>
                            </div>
                            <div class="collapse d-md-block" id="qrFiltersCollapse">
                                <div class="card-body p-2 p-md-3">
                                    <div class="row g-2">
                                        <div class="col-12 col-md-6">
                                            <label for="qrSearchInput" class="form-label small mb-1">Search</label>
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                                                <input type="text" class="form-control form-control-sm" id="qrSearchInput" placeholder="Student name or ID...">
                                            </div>
                                        </div>
                                        <div class="col-12 col-md-6">
                                            <label for="qrSectionFilter" class="form-label small mb-1">Section</label>
                                            <select class="form-select form-select-sm select2" id="qrSectionFilter">
                                                <option value="">All Sections</option>
                                                <?php foreach ($sections as $section): ?>
                                                    <option value="<?php echo htmlspecialchars($section['section_name']); ?>">
                                                        <?php echo htmlspecialchars($section['section_name']); ?> - <?php echo htmlspecialchars($section['grade_level']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                            <?php
                            try {
                                // Get students with QR codes
                                $query = "
                                    SELECT u.id, u.full_name, u.username, u.lrn, u.qr_code, u.email, u.phone, s.section_name, s.grade_level
                                    FROM users u
                                    LEFT JOIN sections s ON u.section_id = s.id
                                    WHERE u.role = 'student' AND u.status = 'active'
                                ";
                                
                                if ($user_role == 'teacher') {
                                    $query .= " AND u.section_id IN (SELECT id FROM sections WHERE teacher_id = ?)";
                                    $params = [$current_user['id']];
                                } elseif ($user_role == 'student') {
                                    $query .= " AND u.id = ?";
                                    $params = [$current_user['id']];
                                } elseif ($user_role == 'parent') {
                                    $query .= " AND u.id IN (SELECT student_id FROM student_parents WHERE parent_id = ?)";
                                    $params = [$current_user['id']];
                                } else {
                                    $params = [];
                                }
                                
                                $query .= " ORDER BY s.grade_level, s.section_name, u.full_name";
                                
                                $stmt = $pdo->prepare($query);
                                $stmt->execute($params);
                                $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                
                                foreach ($students as $student) {
                                    // Generate QR code if it doesn't exist
                                    if (empty($student['qr_code'])) {
                                        $student['qr_code'] = generateStudentQR($student['id']);
                                        // Update QR code in database
                                        $update_stmt = $pdo->prepare("UPDATE users SET qr_code = ? WHERE id = ?");
                                        $update_stmt->execute([$student['qr_code'], $student['id']]);
                                    }
                                    
                                    echo '<div class="col">';
                                    echo '<div class="card h-100 shadow-sm qr-card" data-student-id="' . $student['id'] . '">';
                                    
                                    echo '<div class="card-header bg-primary bg-opacity-10 d-flex justify-content-between align-items-center">';
                                    echo '<span class="fw-bold">' . htmlspecialchars($student['full_name']) . '</span>';
                                    echo '<div class="btn-group btn-group-sm">';
                                    echo '<button class="btn btn-sm btn-outline-primary download-qr" data-student-id="' . $student['id'] . '" title="Download"><i class="fas fa-download"></i></button>';
                                    echo '<button class="btn btn-sm btn-outline-secondary print-qr" data-student-id="' . $student['id'] . '" title="Print"><i class="fas fa-print"></i></button>';
                                    echo '</div>';
                                    echo '</div>';
                                    
                                    echo '<div class="card-body text-center">';
                                    
                                    // Student information
                                    echo '<div class="mb-3">';
                                    echo '<p class="text-muted small mb-1">@' . htmlspecialchars($student['username']) . '</p>';
                                    if (!empty($student['lrn'])) {
                                        echo '<p class="small mb-1"><strong>LRN:</strong> ' . htmlspecialchars($student['lrn']) . '</p>';
                                    }
                                    if (!empty($student['section_name'])) {
                                        echo '<p class="small mb-1"><strong>Section:</strong> ' . htmlspecialchars($student['section_name']) . ' - ' . htmlspecialchars($student['grade_level']) . '</p>';
                                    }
                                    echo '</div>';
                                    
                                    // QR Code
                                    echo '<div class="qr-code-container border rounded p-3 mb-3">';
                                    echo '<div class="qr-code" id="qr-code-' . $student['id'] . '" data-qr="' . htmlspecialchars($student['qr_code']) . '"></div>';
                                    echo '</div>';
                                    echo '<div class="small text-muted">Scan for attendance</div>';
                                    
                                    echo '</div>'; // End card-body
                                    
                                                    echo '<div class="card-footer bg-light p-2 text-end">';
                echo '<span class="badge bg-primary">Student ID: ' . $student['id'] . '</span>';
                echo '</div>'; // End card-footer
                                    
                                    echo '</div>'; // End card
                                    echo '</div>'; // End col
                                }
                                
                                if (empty($students)) {
                                    echo '<div class="col-12"><div class="alert alert-info">No QR codes found.</div></div>';
                                }
                            } catch(PDOException $e) {
                                echo '<div class="col-12 text-danger">Error loading QR codes: ' . htmlspecialchars($e->getMessage()) . '</div>';
                            }
                            ?>
                        </div>
                    </div>
                    
                    <!-- QR Code JavaScript -->
                    <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
                    <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        // Generate QR codes for each student
                        document.querySelectorAll('.qr-code').forEach(function(container) {
                            const qrData = container.getAttribute('data-qr');
                            
                            if (typeof QRCode !== 'undefined') {
                                QRCode.toCanvas(container, qrData, {
                                    width: 150,
                                    height: 150,
                                    colorDark: '#000000',
                                    colorLight: '#ffffff',
                                    correctLevel: QRCode.CorrectLevel.H,
                                    margin: 4
                                }, function (error) {
                                    if (error) {
                                        console.error('QR Code generation error:', error);
                                        generateQRCodeAlternative(container, qrData);
                                    }
                                });
                            } else {
                                generateQRCodeAlternative(container, qrData);
                            }
                        });
                        
                        // Alternative QR Code generation
                        function generateQRCodeAlternative(container, qrData) {
                            const qrSize = 150;
                            const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=${qrSize}x${qrSize}&data=${encodeURIComponent(qrData)}&format=png&margin=10`;
                            
                            const img = document.createElement('img');
                            img.src = qrUrl;
                            img.alt = 'QR Code';
                            img.style.maxWidth = '100%';
                            img.style.height = 'auto';
                            
                            container.innerHTML = '';
                            container.appendChild(img);
                        }
                        
                        // Download individual QR code
                        document.querySelectorAll('.download-qr').forEach(function(button) {
                            button.addEventListener('click', function() {
                                const studentId = this.getAttribute('data-student-id');
                                const qrContainer = document.getElementById('qr-code-' + studentId);
                                const canvas = qrContainer.querySelector('canvas');
                                const img = qrContainer.querySelector('img');
                                const studentName = this.closest('.qr-card').querySelector('.card-header .fw-bold').textContent;
                                
                                if (canvas) {
                                    const link = document.createElement('a');
                                    link.download = studentName.replace(/\s+/g, '_') + '_qr_code.png';
                                    link.href = canvas.toDataURL();
                                    link.click();
                                } else if (img) {
                                    const link = document.createElement('a');
                                    link.download = studentName.replace(/\s+/g, '_') + '_qr_code.png';
                                    link.href = img.src;
                                    link.target = '_blank';
                                    link.click();
                                }
                            });
                        });
                        
                        // Print individual QR code
                        document.querySelectorAll('.print-qr').forEach(function(button) {
                            button.addEventListener('click', function() {
                                const studentId = this.getAttribute('data-student-id');
                                const card = this.closest('.qr-card');
                                const qrContainer = document.getElementById('qr-code-' + studentId);
                                const canvas = qrContainer.querySelector('canvas');
                                const img = qrContainer.querySelector('img');
                                const studentName = card.querySelector('.card-header .fw-bold').textContent;
                                const studentUsername = card.querySelector('.card-body .text-muted').textContent;
                                let studentLRN = '';
                                let studentSection = '';
                                
                                // Find LRN and Section using standard DOM methods
                                card.querySelectorAll('.card-body strong').forEach(function(el) {
                                    if (el.textContent === 'LRN:') {
                                        studentLRN = el.parentNode.textContent.replace('LRN:', '').trim();
                                    }
                                    if (el.textContent === 'Section:') {
                                        studentSection = el.parentNode.textContent.replace('Section:', '').trim();
                                    }
                                });
                                
                                let qrElement = '';
                                if (canvas) {
                                    qrElement = '<img src="' + canvas.toDataURL() + '" style="border: 3px solid #000; border-radius: 15px; max-width: 200px;">';
                                } else if (img) {
                                    qrElement = '<img src="' + img.src + '" style="border: 3px solid #000; border-radius: 15px; max-width: 200px;">';
                                }
                                
                                const printWindow = window.open('', '_blank');
                                const studentInfo = `
                                    <div style="text-align: center; padding: 20px; font-family: Arial, sans-serif;">
                                        <h1 style="color: #007bff; margin-bottom: 10px;">KES-SMART</h1>
                                        <h2 style="margin-bottom: 20px;">${studentName}</h2>
                                        <p style="margin-bottom: 5px;"><strong>Student ID:</strong> ${studentUsername}</p>
                                        ${studentLRN ? `<p style="margin-bottom: 5px;"><strong>LRN:</strong> ${studentLRN}</p>` : ''}
                                        ${studentSection ? `<p style="margin-bottom: 20px;"><strong>Section:</strong> ${studentSection}</p>` : ''}
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
                                            <title>QR Code - ${studentName}</title>
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
                                
                                // Add print button instead of auto-print
                                const printScript = printWindow.document.createElement('script');
                                printScript.innerHTML = `
                                    document.body.insertAdjacentHTML('afterbegin', 
                                        '<div style="text-align: center; margin: 20px; page-break-after: avoid;" class="no-print">' +
                                        '<button onclick="window.print()" style="background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 5px; margin-right: 10px; cursor: pointer;">Print QR Code</button>' +
                                        '<button onclick="window.close()" style="background: #6c757d; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;">Close</button>' +
                                        '</div>'
                                    );
                                    
                                    // Add CSS to hide print buttons when printing
                                    const style = document.createElement('style');
                                    style.innerHTML = '@media print { .no-print { display: none !important; } }';
                                    document.head.appendChild(style);
                                `;
                                printWindow.document.head.appendChild(printScript);
                            });
                        });
                        
                        // Download all QR codes as ZIP
                        document.getElementById('downloadAllQR').addEventListener('click', function() {
                            alert('To download all QR codes, please select each student and download individually or use the "Print All" option to print them all at once.');
                        });
                        
                        // Print all QR codes
                        document.getElementById('printAllQR').addEventListener('click', function() {
                            const printWindow = window.open('', '_blank');
                            let allStudentInfo = '';
                            
                            document.querySelectorAll('.qr-card').forEach(function(card) {
                                const studentId = card.getAttribute('data-student-id');
                                const qrContainer = document.getElementById('qr-code-' + studentId);
                                const canvas = qrContainer.querySelector('canvas');
                                const img = qrContainer.querySelector('img');
                                const studentName = card.querySelector('.card-header .fw-bold').textContent;
                                const studentUsername = card.querySelector('.card-body .text-muted').textContent;
                                let studentLRN = '';
                                let studentSection = '';
                                
                                // Find LRN and Section using standard DOM methods
                                card.querySelectorAll('.card-body strong').forEach(function(el) {
                                    if (el.textContent === 'LRN:') {
                                        studentLRN = el.parentNode.textContent.replace('LRN:', '').trim();
                                    }
                                    if (el.textContent === 'Section:') {
                                        studentSection = el.parentNode.textContent.replace('Section:', '').trim();
                                    }
                                });
                                
                                let qrElement = '';
                                if (canvas) {
                                    qrElement = '<img src="' + canvas.toDataURL() + '" style="border: 3px solid #000; border-radius: 15px; max-width: 200px;">';
                                } else if (img) {
                                    qrElement = '<img src="' + img.src + '" style="border: 3px solid #000; border-radius: 15px; max-width: 200px;">';
                                }
                                
                                const studentInfo = `
                                    <div style="text-align: center; padding: 20px; font-family: Arial, sans-serif; page-break-after: always;">
                                        <h1 style="color: #007bff; margin-bottom: 10px;">KES-SMART</h1>
                                        <h2 style="margin-bottom: 20px;">${studentName}</h2>
                                        <p style="margin-bottom: 5px;"><strong>Student ID:</strong> ${studentUsername}</p>
                                        ${studentLRN ? `<p style="margin-bottom: 5px;"><strong>LRN:</strong> ${studentLRN}</p>` : ''}
                                        ${studentSection ? `<p style="margin-bottom: 20px;"><strong>Section:</strong> ${studentSection}</p>` : ''}
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
                                
                                allStudentInfo += studentInfo;
                            });
                            
                            printWindow.document.write(`
                                <html>
                                    <head>
                                        <title>All QR Codes</title>
                                        <style>
                                            @media print {
                                                body { margin: 0; }
                                                img { border: 2px solid #000; border-radius: 10px; }
                                            }
                                        </style>
                                    </head>
                                    <body>
                                        ${allStudentInfo}
                                    </body>
                                </html>
                            `);
                            printWindow.document.close();
                            
                            // Add print button instead of auto-print
                            const printScript = printWindow.document.createElement('script');
                            printScript.innerHTML = `
                                document.body.insertAdjacentHTML('afterbegin', 
                                    '<div style="text-align: center; margin: 20px; page-break-after: avoid;" class="no-print">' +
                                    '<button onclick="window.print()" style="background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 5px; margin-right: 10px; cursor: pointer;">Print All QR Codes</button>' +
                                    '<button onclick="window.close()" style="background: #6c757d; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;">Close</button>' +
                                    '</div>'
                                );
                                
                                // Add CSS to hide print buttons when printing
                                const style = printWindow.document.createElement('style');
                                style.innerHTML = '@media print { .no-print { display: none !important; } }';
                                printWindow.document.head.appendChild(style);
                            `;
                            printWindow.document.head.appendChild(printScript);
                        });
                    });
                    </script>
                    
                    <!-- Attendance Per Section Tab -->
                    <div class="tab-pane fade" id="attendance-section" role="tabpanel" aria-labelledby="attendance-section-tab">
                        <div class="mb-3 d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="fas fa-school me-2"></i>Attendance Per Section</h5>
                            <button class="btn btn-sm btn-outline-secondary print-tab-content" data-target="attendance-section">
                                <i class="fas fa-print me-1"></i>Print All
                            </button>
                        </div>
                        
                        <!-- Search and filter controls -->
                        <div class="card mb-3">
                            <div class="card-header p-2 d-md-none">
                                <button class="btn btn-sm btn-outline-primary w-100" type="button" data-bs-toggle="collapse" data-bs-target="#attendanceSectionFiltersCollapse">
                                    <i class="fas fa-filter me-1"></i>Search & Filters
                                </button>
                            </div>
                            <div class="collapse d-md-block" id="attendanceSectionFiltersCollapse">
                                <div class="card-body p-2 p-md-3">
                                    <div class="row g-2">
                                        <div class="col-12 col-md-4">
                                            <label for="attendanceSectionSearchInput" class="form-label small mb-1">Search</label>
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                                                <input type="text" class="form-control form-control-sm" id="attendanceSectionSearchInput" placeholder="Section name...">
                                            </div>
                                        </div>
                                        <div class="col-6 col-md-4">
                                            <label for="attendanceGradeFilter" class="form-label small mb-1">Grade Level</label>
                                            <select class="form-select form-select-sm select2" id="attendanceGradeFilter">
                                                <option value="">All Grades</option>
                                                <?php
                                                try {
                                                    $grade_levels = $pdo->query("SELECT DISTINCT grade_level FROM sections ORDER BY grade_level")->fetchAll(PDO::FETCH_COLUMN);
                                                    foreach ($grade_levels as $grade) {
                                                        echo '<option value="' . htmlspecialchars($grade) . '">' . htmlspecialchars($grade) . '</option>';
                                                    }
                                                } catch(PDOException $e) {
                                                    // Silently fail
                                                }
                                                ?>
                                            </select>
                                        </div>
                                        <div class="col-6 col-md-4">
                                            <label for="attendanceRateFilter" class="form-label small mb-1">Attendance Rate</label>
                                            <select class="form-select form-select-sm select2" id="attendanceRateFilter">
                                                <option value="">All Rates</option>
                                                <option value="high">High (90%+)</option>
                                                <option value="medium">Medium (75-90%)</option>
                                                <option value="low">Low (<75%)</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <?php
                        try {
                            // Get all sections with attendance summary
                            $date_from = date('Y-m-d', strtotime('-7 days'));
                            $date_to = date('Y-m-d');
                            
                            $query = "
                                SELECT s.id, s.section_name, s.grade_level,
                                       u.full_name as teacher_name,
                                       COUNT(DISTINCT a.student_id) as students_with_attendance,
                                       COUNT(a.id) as total_records,
                                       SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_count,
                                       SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent_count,
                                       SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late_count,
                                       SUM(CASE WHEN a.status = 'out' THEN 1 ELSE 0 END) as out_count
                                FROM sections s
                                LEFT JOIN users u ON s.teacher_id = u.id
                                LEFT JOIN attendance a ON s.id = a.section_id AND a.attendance_date BETWEEN ? AND ?
                                WHERE s.status = 'active'
                            ";
                            $params = [$date_from, $date_to];
                            
                            if ($user_role == 'teacher') {
                                $query .= " AND s.teacher_id = ?";
                                $params[] = $current_user['id'];
                            }
                            
                            $query .= " GROUP BY s.id ORDER BY s.grade_level, s.section_name";
                            
                            $stmt = $pdo->prepare($query);
                            $stmt->execute($params);
                            $sections = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            echo '<div class="mb-3 d-flex justify-content-between align-items-center">';
                            echo '<h5 class="mb-0">Attendance Summary (Last 7 Days)</h5>';
                            echo '<span class="badge bg-secondary">' . date('M d, Y', strtotime($date_from)) . ' - ' . date('M d, Y', strtotime($date_to)) . '</span>';
                            echo '</div>';
                            
                            foreach ($sections as $section) {
                                echo '<div class="card mb-4">';
                                echo '<div class="card-header bg-success bg-opacity-10">';
                                echo '<div class="d-flex justify-content-between align-items-center">';
                                echo '<h5 class="mb-0 text-success">' . htmlspecialchars($section['section_name']) . ' - ' . htmlspecialchars($section['grade_level']) . '</h5>';
                                echo '<span class="badge bg-info">' . $section['students_with_attendance'] . ' Students</span>';
                                echo '</div>';
                                echo '<p class="text-muted mb-0 small">Teacher: ' . htmlspecialchars($section['teacher_name'] ?? 'Not Assigned') . '</p>';
                                echo '</div>';
                                
                                echo '<div class="card-body">';
                                echo '<div class="row mb-4">';
                                
                                // Attendance summary stats
                                echo '<div class="col-md-4">';
                                echo '<div class="card border-success h-100">';
                                echo '<div class="card-body text-center">';
                                echo '<h1 class="display-4 text-success">' . $section['total_records'] . '</h1>';
                                echo '<p class="text-muted">Total Records</p>';
                                echo '</div>';
                                echo '</div>';
                                echo '</div>';
                                
                                echo '<div class="col-md-8">';
                                echo '<div class="row g-2">';
                                
                                echo '<div class="col-3">';
                                echo '<div class="card border-success h-100">';
                                echo '<div class="card-body text-center p-2">';
                                echo '<h3 class="text-success mb-0">' . $section['present_count'] . '</h3>';
                                echo '<p class="text-muted small mb-0">Present</p>';
                                echo '</div>';
                                echo '</div>';
                                echo '</div>';
                                
                                echo '<div class="col-3">';
                                echo '<div class="card border-danger h-100">';
                                echo '<div class="card-body text-center p-2">';
                                echo '<h3 class="text-danger mb-0">' . $section['absent_count'] . '</h3>';
                                echo '<p class="text-muted small mb-0">Absent</p>';
                                echo '</div>';
                                echo '</div>';
                                echo '</div>';
                                
                                echo '<div class="col-3">';
                                echo '<div class="card border-warning h-100">';
                                echo '<div class="card-body text-center p-2">';
                                echo '<h3 class="text-warning mb-0">' . $section['late_count'] . '</h3>';
                                echo '<p class="text-muted small mb-0">Late</p>';
                                echo '</div>';
                                echo '</div>';
                                echo '</div>';
                                
                                echo '<div class="col-3">';
                                echo '<div class="card border-info h-100">';
                                echo '<div class="card-body text-center p-2">';
                                echo '<h3 class="text-info mb-0">' . $section['out_count'] . '</h3>';
                                echo '<p class="text-muted small mb-0">Out</p>';
                                echo '</div>';
                                echo '</div>';
                                echo '</div>';
                                
                                echo '</div>'; // End inner row
                                echo '</div>'; // End col-md-8
                                
                                echo '</div>'; // End row
                                
                                // Get recent attendance records for this section
                                $records_query = "
                                    SELECT a.attendance_date, a.status, u.full_name as student_name, u.username
                                    FROM attendance a
                                    JOIN users u ON a.student_id = u.id
                                    WHERE a.section_id = ? AND a.attendance_date BETWEEN ? AND ?
                                    ORDER BY a.attendance_date DESC, u.full_name
                                    LIMIT 10
                                ";
                                $records_stmt = $pdo->prepare($records_query);
                                $records_stmt->execute([$section['id'], $date_from, $date_to]);
                                $records = $records_stmt->fetchAll(PDO::FETCH_ASSOC);
                                
                                if (!empty($records)) {
                                    echo '<h6 class="mb-3">Recent Records</h6>';
                                    echo '<div class="table-responsive">';
                                    echo '<table class="table table-sm table-hover">';
                                    echo '<thead class="table-light">';
                                    echo '<tr>';
                                    echo '<th>Date</th>';
                                    echo '<th>Student</th>';
                                    echo '<th>Status</th>';
                                    echo '</tr>';
                                    echo '</thead>';
                                    echo '<tbody>';
                                    
                                    foreach ($records as $record) {
                                        $status_class = $record['status'] == 'present' ? 'success' : 
                                                    ($record['status'] == 'absent' ? 'danger' : 
                                                    ($record['status'] == 'late' ? 'warning' : 'info'));
                                        
                                        echo '<tr>';
                                        echo '<td>' . date('M d, Y', strtotime($record['attendance_date'])) . '</td>';
                                        echo '<td>' . htmlspecialchars($record['student_name']) . ' <span class="text-muted small">@' . htmlspecialchars($record['username']) . '</span></td>';
                                        echo '<td><span class="badge bg-' . $status_class . '">' . ucfirst($record['status']) . '</span></td>';
                                        echo '</tr>';
                                    }
                                    
                                    echo '</tbody>';
                                    echo '</table>';
                                    echo '</div>';
                                } else {
                                    echo '<div class="alert alert-info">No recent attendance records found.</div>';
                                }
                                
                                echo '</div>'; // End card-body
                                echo '</div>'; // End section card
                            }
                            
                            if (empty($sections)) {
                                echo '<div class="alert alert-info">No sections found.</div>';
                            }
                        } catch(PDOException $e) {
                            echo '<div class="alert alert-danger">Error loading attendance data: ' . htmlspecialchars($e->getMessage()) . '</div>';
                        }
                        ?>
                    </div>
                    
                    <!-- Attendance Per Student Tab -->
                    <div class="tab-pane fade" id="attendance-student" role="tabpanel" aria-labelledby="attendance-student-tab">
                        <div class="mb-3 d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="fas fa-user-graduate me-2"></i>Attendance Per Student</h5>
                            <button class="btn btn-sm btn-outline-secondary print-tab-content" data-target="attendance-student">
                                <i class="fas fa-print me-1"></i>Print All
                            </button>
                        </div>
                        
                        <!-- Search and filter controls -->
                        <div class="card mb-3">
                            <div class="card-header p-2 d-md-none">
                                <button class="btn btn-sm btn-outline-primary w-100" type="button" data-bs-toggle="collapse" data-bs-target="#attendanceStudentFiltersCollapse">
                                    <i class="fas fa-filter me-1"></i>Search & Filters
                                </button>
                            </div>
                            <div class="collapse d-md-block" id="attendanceStudentFiltersCollapse">
                                <div class="card-body p-2 p-md-3">
                                    <div class="row g-2">
                                        <div class="col-12 col-md-3">
                                            <label for="studentAttendanceSearchInput" class="form-label small mb-1">Search</label>
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                                                <input type="text" class="form-control form-control-sm" id="studentAttendanceSearchInput" placeholder="Student name...">
                                            </div>
                                        </div>
                                        <div class="col-12 col-md-3">
                                            <label for="studentSectionFilter" class="form-label small mb-1">Section</label>
                                            <select class="form-select form-select-sm select2" id="studentSectionFilter">
                                                <option value="">All Sections</option>
                                                <?php foreach ($sections as $section): ?>
                                                    <option value="<?php echo htmlspecialchars($section['section_name']); ?>">
                                                        <?php echo htmlspecialchars($section['section_name']); ?> - <?php echo htmlspecialchars($section['grade_level']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-6 col-md-3">
                                            <label for="studentAttendanceRateFilter" class="form-label small mb-1">Rate</label>
                                            <select class="form-select form-select-sm select2" id="studentAttendanceRateFilter">
                                                <option value="">All Rates</option>
                                                <option value="high">High (90%+)</option>
                                                <option value="medium">Medium (75-90%)</option>
                                                <option value="low">Low (<75%)</option>
                                            </select>
                                        </div>
                                        <div class="col-6 col-md-3">
                                            <label for="studentStatusFilter" class="form-label small mb-1">Status</label>
                                            <select class="form-select form-select-sm select2" id="studentStatusFilter">
                                                <option value="">All Statuses</option>
                                                <option value="present">Present</option>
                                                <option value="absent">Absent</option>
                                                <option value="late">Late</option>
                                                <option value="out">Out</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <?php
                        try {
                            // Get date range
                            $date_from = date('Y-m-d', strtotime('-30 days'));
                            $date_to = date('Y-m-d');
                            
                            // Get students with attendance summary
                            $query = "
                                SELECT u.id, u.full_name, u.username, u.profile_image, s.section_name, s.grade_level,
                                       COUNT(a.id) as total_records,
                                       SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_count,
                                       SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent_count,
                                       SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late_count,
                                       SUM(CASE WHEN a.status = 'out' THEN 1 ELSE 0 END) as out_count
                                FROM users u
                                LEFT JOIN sections s ON u.section_id = s.id
                                LEFT JOIN attendance a ON u.id = a.student_id AND a.attendance_date BETWEEN ? AND ?
                                WHERE u.role = 'student' AND u.status = 'active'
                            ";
                            $params = [$date_from, $date_to];
                            
                            if ($user_role == 'teacher') {
                                $query .= " AND u.section_id IN (SELECT id FROM sections WHERE teacher_id = ?)";
                                $params[] = $current_user['id'];
                            } elseif ($user_role == 'student') {
                                $query .= " AND u.id = ?";
                                $params[] = $current_user['id'];
                            } elseif ($user_role == 'parent') {
                                $query .= " AND u.id IN (SELECT student_id FROM student_parents WHERE parent_id = ?)";
                                $params[] = $current_user['id'];
                            }
                            
                            $query .= " GROUP BY u.id ORDER BY s.grade_level, s.section_name, u.full_name";
                            
                            $stmt = $pdo->prepare($query);
                            $stmt->execute($params);
                            $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            echo '<div class="mb-3 d-flex justify-content-between align-items-center">';
                            echo '<h5 class="mb-0">Student Attendance Summary (Last 30 Days)</h5>';
                            echo '<span class="badge bg-secondary">' . date('M d, Y', strtotime($date_from)) . ' - ' . date('M d, Y', strtotime($date_to)) . '</span>';
                            echo '</div>';
                            
                            echo '<div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">';
                            
                            foreach ($students as $student) {
                                // Calculate attendance percentage
                                $total_days = $student['total_records'] > 0 ? $student['total_records'] : 1;
                                $present_percentage = round(($student['present_count'] / $total_days) * 100);
                                
                                // Determine status color based on percentage
                                if ($present_percentage >= 90) {
                                    $status_color = 'success';
                                } elseif ($present_percentage >= 75) {
                                    $status_color = 'warning';
                                } else {
                                    $status_color = 'danger';
                                }
                                
                                echo '<div class="col">';
                                echo '<div class="card h-100 border-' . $status_color . ' shadow-sm">';
                                
                                echo '<div class="card-header bg-' . $status_color . ' bg-opacity-10 d-flex justify-content-between align-items-center">';
                                echo '<span class="fw-bold">' . htmlspecialchars($student['full_name']) . '</span>';
                                echo '<span class="badge bg-' . $status_color . '">' . $present_percentage . '%</span>';
                                echo '</div>';
                                
                                echo '<div class="card-body">';
                                
                                // Student info
                                echo '<div class="d-flex align-items-center mb-3">';
                                
                                // Student profile image or icon
                                echo '<div class="me-3">';
                                if (!empty($student['profile_image'])) {
                                    echo '<img src="' . htmlspecialchars($student['profile_image']) . '" alt="Profile" class="rounded-circle" style="width: 50px; height: 50px; object-fit: cover;">';
                                } else {
                                    echo '<div class="bg-success bg-opacity-25 rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">';
                                    echo '<i class="fas fa-user-graduate fa-lg text-success"></i>';
                                    echo '</div>';
                                }
                                echo '</div>'; // End profile image div
                                
                                echo '<div>';
                                echo '<p class="text-muted small mb-0">@' . htmlspecialchars($student['username']) . '</p>';
                                echo '<p class="small mb-0">' . htmlspecialchars($student['section_name'] ?? 'No Section') . ' - ' . htmlspecialchars($student['grade_level'] ?? 'No Grade') . '</p>';
                                echo '</div>';
                                
                                echo '</div>'; // End d-flex
                                
                                // Attendance stats
                                echo '<div class="mb-3">';
                                echo '<div class="progress" style="height: 10px;">';
                                echo '<div class="progress-bar bg-success" role="progressbar" style="width: ' . ($student['present_count'] / $total_days * 100) . '%"></div>';
                                echo '<div class="progress-bar bg-warning" role="progressbar" style="width: ' . ($student['late_count'] / $total_days * 100) . '%"></div>';
                                echo '<div class="progress-bar bg-danger" role="progressbar" style="width: ' . ($student['absent_count'] / $total_days * 100) . '%"></div>';
                                echo '<div class="progress-bar bg-info" role="progressbar" style="width: ' . ($student['out_count'] / $total_days * 100) . '%"></div>';
                                echo '</div>';
                                echo '</div>';
                                
                                echo '<div class="row text-center g-2 mb-3">';
                                
                                echo '<div class="col-3">';
                                echo '<div class="border rounded p-2">';
                                echo '<h5 class="text-success mb-0">' . $student['present_count'] . '</h5>';
                                echo '<small class="text-muted">Present</small>';
                                echo '</div>';
                                echo '</div>';
                                
                                echo '<div class="col-3">';
                                echo '<div class="border rounded p-2">';
                                echo '<h5 class="text-danger mb-0">' . $student['absent_count'] . '</h5>';
                                echo '<small class="text-muted">Absent</small>';
                                echo '</div>';
                                echo '</div>';
                                
                                echo '<div class="col-3">';
                                echo '<div class="border rounded p-2">';
                                echo '<h5 class="text-warning mb-0">' . $student['late_count'] . '</h5>';
                                echo '<small class="text-muted">Late</small>';
                                echo '</div>';
                                echo '</div>';
                                
                                echo '<div class="col-3">';
                                echo '<div class="border rounded p-2">';
                                echo '<h5 class="text-info mb-0">' . $student['out_count'] . '</h5>';
                                echo '<small class="text-muted">Out</small>';
                                echo '</div>';
                                echo '</div>';
                                
                                echo '</div>'; // End row
                                
                                // Get recent attendance
                                $recent_query = "
                                    SELECT a.attendance_date, a.status
                                    FROM attendance a
                                    WHERE a.student_id = ? AND a.attendance_date BETWEEN ? AND ?
                                    ORDER BY a.attendance_date DESC
                                    LIMIT 5
                                ";
                                $recent_stmt = $pdo->prepare($recent_query);
                                $recent_stmt->execute([$student['id'], $date_from, $date_to]);
                                $recent_records = $recent_stmt->fetchAll(PDO::FETCH_ASSOC);
                                
                                if (!empty($recent_records)) {
                                    echo '<div class="small">';
                                    echo '<p class="fw-bold mb-2">Recent Attendance:</p>';
                                    
                                    foreach ($recent_records as $record) {
                                        $status_class = $record['status'] == 'present' ? 'success' : 
                                                    ($record['status'] == 'absent' ? 'danger' : 
                                                    ($record['status'] == 'late' ? 'warning' : 'info'));
                                        
                                        echo '<div class="d-flex justify-content-between align-items-center mb-1">';
                                        echo '<span>' . date('M d, Y', strtotime($record['attendance_date'])) . '</span>';
                                        echo '<span class="badge bg-' . $status_class . '">' . ucfirst($record['status']) . '</span>';
                                        echo '</div>';
                                    }
                                    
                                    echo '</div>';
                                } else {
                                    echo '<div class="alert alert-info small">No recent attendance records.</div>';
                                }
                                
                                echo '</div>'; // End card-body
                                echo '</div>'; // End card
                                echo '</div>'; // End col
                            }
                            
                            echo '</div>'; // End row
                            
                            if (empty($students)) {
                                echo '<div class="alert alert-info">No student attendance data found.</div>';
                            }
                        } catch(PDOException $e) {
                            echo '<div class="alert alert-danger">Error loading student attendance data: ' . htmlspecialchars($e->getMessage()) . '</div>';
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Report Generation Modal -->
<div class="modal fade" id="reportModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-chart-bar me-2"></i>Generate Report
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="reportForm" class="needs-validation" novalidate>
                    <input type="hidden" id="reportType" name="type">
                    
                    <div class="mb-3">
                        <label for="reportFormat" class="form-label">Format</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-file-alt"></i></span>
                            <select class="form-select" id="reportFormat" name="format" required>
                                <option value="html">HTML (View in browser)</option>
                                <option value="csv">CSV (Excel compatible)</option>
                                <option value="pdf">PDF (Printable)</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Student selection -->
                    <div class="mb-3" id="studentSelection" style="display: none;">
                        <label for="studentId" class="form-label">Student</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-user-graduate"></i></span>
                            <select class="form-select select2" id="studentId" name="student_id">
                                <option value="">All Students</option>
                                <?php foreach ($students as $student): ?>
                                    <option value="<?php echo $student['id']; ?>">
                                        <?php echo $student['full_name']; ?> (<?php echo $student['username']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Section selection -->
                    <div class="mb-3" id="sectionSelection" style="display: none;">
                        <label for="sectionId" class="form-label">Section</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-school"></i></span>
                            <select class="form-select select2" id="sectionId" name="section_id">
                                <option value="">All Sections</option>
                                <?php foreach ($sections as $section): ?>
                                    <option value="<?php echo $section['id']; ?>">
                                        <?php echo $section['section_name']; ?> - <?php echo $section['grade_level']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Date range -->
                    <div class="mb-3" id="dateSelection" style="display: none;">
                        <label class="form-label">Date Range</label>
                        <div class="row g-2">
                            <div class="col-sm-6">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                                    <input type="date" class="form-control" id="dateFrom" name="date_from" value="<?php echo date('Y-m-01'); ?>">
                                </div>
                                <div class="form-text">From</div>
                            </div>
                            <div class="col-sm-6">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                                    <input type="date" class="form-control" id="dateTo" name="date_to" value="<?php echo date('Y-m-t'); ?>">
                                </div>
                                <div class="form-text">To</div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer flex-wrap gap-2">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>Cancel
                </button>
                <button type="button" class="btn btn-primary" onclick="generateReport()">
                    <i class="fas fa-chart-bar me-1"></i>Generate Report
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Format Selection Modal for Print All -->
<div class="modal fade" id="formatSelectionModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-file-export me-2"></i>Select Export Format
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-3">Choose the format for exporting the data:</p>
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="card border-2 format-option" data-format="html" style="cursor: pointer;">
                            <div class="card-body text-center">
                                <i class="fas fa-file-code fa-2x text-primary mb-2"></i>
                                <h6 class="card-title">HTML View</h6>
                                <p class="card-text small text-muted">View in browser with print option</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-2 format-option" data-format="csv" style="cursor: pointer;">
                            <div class="card-body text-center">
                                <i class="fas fa-file-csv fa-2x text-success mb-2"></i>
                                <h6 class="card-title">CSV</h6>
                                <p class="card-text small text-muted">Excel compatible download</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-2 format-option" data-format="pdf" style="cursor: pointer;">
                            <div class="card-body text-center">
                                <i class="fas fa-file-pdf fa-2x text-danger mb-2"></i>
                                <h6 class="card-title">PDF</h6>
                                <p class="card-text small text-muted">Printable document</p>
                            </div>
                        </div>
                    </div>
                </div>
                <input type="hidden" id="selectedFormat" value="">
                <input type="hidden" id="selectedTabTarget" value="">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>Cancel
                </button>
                <button type="button" class="btn btn-primary" id="confirmFormatSelection" disabled>
                    <i class="fas fa-download me-1"></i>Export
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function showReportModal(type) {
    document.getElementById('reportType').value = type;
    
    // Show/hide form fields based on report type
    const studentSelection = document.getElementById('studentSelection');
    const sectionSelection = document.getElementById('sectionSelection');
    const dateSelection = document.getElementById('dateSelection');
    
    // Reset visibility
    studentSelection.style.display = 'none';
    sectionSelection.style.display = 'none';
    dateSelection.style.display = 'none';
    
    switch(type) {
        case 'students_list':
            sectionSelection.style.display = 'block';
            break;
        case 'students_per_section':
            sectionSelection.style.display = 'block';
            break;
        case 'student_info':
        case 'student_qr':
            studentSelection.style.display = 'block';
            // Make student selection required
            document.getElementById('studentId').required = true;
            break;
        case 'attendance_records':
        case 'attendance_summary':
        case 'section_attendance':
        case 'monthly_stats':
        case 'performance_analysis':
            studentSelection.style.display = 'block';
            sectionSelection.style.display = 'block';
            dateSelection.style.display = 'block';
            break;
        case 'attendance_per_section':
            sectionSelection.style.display = 'block';
            dateSelection.style.display = 'block';
            break;
        case 'attendance_per_student':
            studentSelection.style.display = 'block';
            dateSelection.style.display = 'block';
            break;
    }
    
    new bootstrap.Modal(document.getElementById('reportModal')).show();
}

function generateReport() {
    const form = document.getElementById('reportForm');
    const formData = new FormData(form);
    
    // Validate required fields
    const reportType = formData.get('type');
    if ((reportType === 'student_info' || reportType === 'student_qr') && !formData.get('student_id')) {
        alert('Please select a student for this report.');
        return;
    }
    
    // Build URL
    const params = new URLSearchParams(formData);
    params.append('generate', '1');
    
    const url = 'reports.php?' + params.toString();
    
    // Open in new tab for HTML, download for others
    if (formData.get('format') === 'html') {
        window.open(url, '_blank');
    } else {
        window.location.href = url;
    }
    
    // Close modal
    bootstrap.Modal.getInstance(document.getElementById('reportModal')).hide();
}

// Initialize Select2 when modal is shown
document.getElementById('reportModal').addEventListener('shown.bs.modal', function() {
    $('.select2').select2({
        theme: 'bootstrap-5',
        width: '100%',
        dropdownParent: $('#reportModal')
    });
});

// Quick date range buttons
function setQuickRange(range) {
    const dateFrom = document.getElementById('dateFrom');
    const dateTo = document.getElementById('dateTo');
    const today = new Date();
    
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
    }
}

// Add quick range buttons to date selection
document.addEventListener('DOMContentLoaded', function() {
    const dateSelection = document.getElementById('dateSelection');
    const quickRangeDiv = document.createElement('div');
    quickRangeDiv.className = 'mt-2';
    quickRangeDiv.innerHTML = `
        <small class="text-muted d-block mb-1">Quick ranges:</small>
        <div class="btn-group btn-group-sm" role="group">
            <button type="button" class="btn btn-outline-secondary" onclick="setQuickRange('today')">Today</button>
            <button type="button" class="btn btn-outline-secondary" onclick="setQuickRange('week')">This Week</button>
            <button type="button" class="btn btn-outline-secondary" onclick="setQuickRange('month')">This Month</button>
        </div>
    `;
    dateSelection.appendChild(quickRangeDiv);
});

// Print Tab Content Script
document.addEventListener('DOMContentLoaded', function() {
    // Mobile tab selector functionality
    const tabSelector = document.getElementById('tabSelector');
    if (tabSelector) {
        tabSelector.addEventListener('change', function() {
            const tabId = this.value;
            const tabToShow = document.getElementById(tabId);
            
            if (tabToShow) {
                // Hide all tabs
                document.querySelectorAll('.tab-pane').forEach(function(tab) {
                    tab.classList.remove('show', 'active');
                });
                
                // Show selected tab
                tabToShow.classList.add('show', 'active');
                
                // Update tab buttons for larger screens (for consistency)
                document.querySelectorAll('.nav-link').forEach(function(link) {
                    link.classList.remove('active');
                    link.setAttribute('aria-selected', 'false');
                });
                
                const correspondingTab = document.querySelector(`[data-bs-target="#${tabId}"]`);
                if (correspondingTab) {
                    correspondingTab.classList.add('active');
                    correspondingTab.setAttribute('aria-selected', 'true');
                }
            }
        });
        
        // Set initial value based on active tab
        const activeTab = document.querySelector('.tab-pane.active');
        if (activeTab) {
            tabSelector.value = activeTab.id;
        }
    }
    
    // Add event listeners to print buttons
    document.querySelectorAll('.print-tab-content').forEach(function(button) {
        button.addEventListener('click', function() {
            const targetId = this.getAttribute('data-target');
            const tabContent = document.getElementById(targetId);
            
            if (!tabContent) return;
            
            // Store the target and show format selection modal
            document.getElementById('selectedTabTarget').value = targetId;
            document.getElementById('selectedFormat').value = '';
            
            // Reset format selection
            document.querySelectorAll('.format-option').forEach(function(option) {
                option.classList.remove('border-primary', 'bg-light');
            });
            document.getElementById('confirmFormatSelection').disabled = true;
            
            // Show format selection modal
            new bootstrap.Modal(document.getElementById('formatSelectionModal')).show();
        });
    });
    
    // Handle format selection
    document.querySelectorAll('.format-option').forEach(function(option) {
        option.addEventListener('click', function() {
            // Remove selection from other options
            document.querySelectorAll('.format-option').forEach(function(opt) {
                opt.classList.remove('border-primary', 'bg-light');
            });
            
            // Add selection to this option
            this.classList.add('border-primary', 'bg-light');
            
            // Store selected format
            const format = this.getAttribute('data-format');
            document.getElementById('selectedFormat').value = format;
            document.getElementById('confirmFormatSelection').disabled = false;
        });
    });
    
    // Handle format confirmation
    document.getElementById('confirmFormatSelection').addEventListener('click', function() {
        const targetId = document.getElementById('selectedTabTarget').value;
        const format = document.getElementById('selectedFormat').value;
        const tabContent = document.getElementById(targetId);
        
        if (!tabContent || !format) return;
        
        // Hide the modal
        bootstrap.Modal.getInstance(document.getElementById('formatSelectionModal')).hide();
        
        if (format === 'html') {
            // Original print functionality for HTML
            const printWindow = window.open('', '_blank');
            const tabTitle = document.querySelector(`[data-target="${targetId}"]`).closest('.d-flex').querySelector('h5').textContent;
            
            // Create the HTML content with table-based layout
            let html = '<!DOCTYPE html><html><head>';
            html += '<title>' + tabTitle + '</title>';
            html += '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">';
            html += '<style>';
            html += 'body { font-family: Arial, sans-serif; padding: 20px; }';
            html += 'h1 { color: #007bff; margin-bottom: 20px; }';
            html += 'table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }';
            html += 'th { background-color: #f8f9fa; font-weight: bold; text-align: left; padding: 10px; border: 1px solid #dee2e6; }';
            html += 'td { padding: 10px; border: 1px solid #dee2e6; vertical-align: top; }';
            html += 'tr:nth-child(even) { background-color: #f2f2f2; }';
            html += '.text-center { text-align: center; }';
            html += '.text-success { color: #28a745; }';
            html += '.text-danger { color: #dc3545; }';
            html += '.text-warning { color: #ffc107; }';
            html += '.badge { display: inline-block; padding: 0.25em 0.4em; font-size: 75%; font-weight: 700; line-height: 1; text-align: center; white-space: nowrap; vertical-align: baseline; border-radius: 0.25rem; }';
            html += '.badge-success { background-color: #28a745; color: white; }';
            html += '.badge-danger { background-color: #dc3545; color: white; }';
            html += '.badge-warning { background-color: #ffc107; color: black; }';
            html += '.badge-info { background-color: #17a2b8; color: white; }';
            html += '.badge-primary { background-color: #007bff; color: white; }';
            html += '.badge-secondary { background-color: #6c757d; color: white; }';
            html += '@media print {';
            html += '  @page { margin: 0.5cm; }';
            html += '  body { font-size: 12pt; }';
            html += '  h1 { font-size: 18pt; }';
            html += '  th { background-color: #f8f9fa !important; -webkit-print-color-adjust: exact; }';
            html += '  tr:nth-child(even) { background-color: #f2f2f2 !important; -webkit-print-color-adjust: exact; }';
            html += '  .badge-success { background-color: #28a745 !important; color: white !important; -webkit-print-color-adjust: exact; }';
            html += '  .badge-danger { background-color: #dc3545 !important; color: white !important; -webkit-print-color-adjust: exact; }';
            html += '  .badge-warning { background-color: #ffc107 !important; color: black !important; -webkit-print-color-adjust: exact; }';
            html += '  .badge-info { background-color: #17a2b8 !important; color: white !important; -webkit-print-color-adjust: exact; }';
            html += '  .badge-primary { background-color: #007bff !important; color: white !important; -webkit-print-color-adjust: exact; }';
            html += '  .badge-secondary { background-color: #6c757d !important; color: white !important; -webkit-print-color-adjust: exact; }';
            html += '}';
            html += '</style>';
            html += '</head><body>';
            html += '<h1>' + tabTitle + '</h1>';
            html += '<p>Generated on: ' + new Date().toLocaleString() + '</p>';
            
            // Generate table content based on tab type
            switch(targetId) {
                case 'users':
                    html += generateUsersTable(tabContent);
                    break;
                case 'sections':
                    html += generateSectionsTable(tabContent);
                    break;
                case 'attendance':
                    html += generateAttendanceTable(tabContent);
                    break;
                case 'students-section':
                    html += generateStudentsSectionTable(tabContent);
                    break;
                case 'qr-codes':
                    html += generateQRCodesTable(tabContent);
                    break;
                case 'attendance-section':
                    html += generateAttendanceSectionTable(tabContent);
                    break;
                case 'attendance-student':
                    html += generateAttendanceStudentTable(tabContent);
                    break;
                case 'sms':
                    html += generateSMSTable(tabContent);
                    break;
                default:
                    html += '<div class="alert alert-warning">No table format available for this tab.</div>';
            }
            
            // Add a print button and instructions instead of auto-print
            html += '<div class="no-print mb-3">';
            html += '<button onclick="window.print()" class="btn btn-primary me-2">';
            html += '<i class="fas fa-print me-1"></i>Print Document';
            html += '</button>';
            html += '<button onclick="window.close()" class="btn btn-secondary">';
            html += '<i class="fas fa-times me-1"></i>Close';
            html += '</button>';
            html += '</div>';
            
            html += '<style>';
            html += '@media print { .no-print { display: none !important; } }';
            html += '</style>';
            html += '</body></html>';
            
            // Write the HTML to the new window and close the document
            printWindow.document.write(html);
            printWindow.document.close();
        } else if (format === 'csv') {
            // Generate CSV and download
            generateTabCSV(targetId, tabContent);
        } else if (format === 'pdf') {
            // Generate PDF using the existing report system
            generateTabPDF(targetId, tabContent);
        }
    });
    
    // Function to generate CSV from tab content
    function generateTabCSV(targetId, tabContent) {
        let csvContent = '';
        const fileName = targetId + '_export_' + new Date().toISOString().slice(0, 10) + '.csv';
        
        // Generate CSV content based on tab type
        switch(targetId) {
            case 'users':
                csvContent = generateUsersCSV(tabContent);
                break;
            case 'sections':
                csvContent = generateSectionsCSV(tabContent);
                break;
            case 'attendance':
                csvContent = generateAttendanceCSV(tabContent);
                break;
            case 'students-section':
                csvContent = generateStudentsSectionCSV(tabContent);
                break;
            case 'qr-codes':
                csvContent = generateQRCodesCSV(tabContent);
                break;
            case 'attendance-section':
                csvContent = generateAttendanceSectionCSV(tabContent);
                break;
            case 'attendance-student':
                csvContent = generateAttendanceStudentCSV(tabContent);
                break;
            case 'sms':
                csvContent = generateSMSCSV(tabContent);
                break;
            default:
                csvContent = 'No data available for export';
        }
        
        // Create and download CSV file
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);
        link.setAttribute('href', url);
        link.setAttribute('download', fileName);
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
    
    // CSV generation functions for each tab type
    function generateUsersCSV(tabContent) {
        let csv = 'ID,Name,Username,Email,Role,Status,Section,Phone\n';
        
        const userCards = tabContent.querySelectorAll('.col .card');
        userCards.forEach(function(card) {
            const name = card.querySelector('.card-title')?.textContent?.trim() || '';
            const username = card.querySelector('.text-muted.small')?.textContent?.trim() || '';
            const email = card.querySelector('.fas.fa-envelope')?.parentElement?.textContent?.replace('📧', '')?.trim() || '';
            const badges = card.querySelectorAll('.badge');
            const role = badges[0]?.textContent?.trim() || '';
            const status = badges[1]?.textContent?.trim() || '';
            const section = card.querySelector('.fas.fa-users')?.parentElement?.textContent?.replace('👥', '')?.trim() || '';
            const phone = card.querySelector('.fas.fa-phone')?.parentElement?.textContent?.replace('📱', '')?.trim() || '';
            const id = card.querySelector('.card-header .badge')?.textContent?.replace('ID: ', '')?.trim() || '';
            
            csv += `"${id}","${name}","${username}","${email}","${role}","${status}","${section}","${phone}"\n`;
        });
        
        return csv;
    }
    
    function generateSectionsCSV(tabContent) {
        let csv = 'ID,Section Name,Grade Level,Teacher,Student Count,Status\n';
        
        const sectionCards = tabContent.querySelectorAll('.col .card');
        sectionCards.forEach(function(card) {
            const sectionName = card.querySelector('.card-title')?.textContent?.trim() || '';
            const gradeLevel = card.querySelector('.fw-bold')?.textContent?.trim() || '';
            const teacher = card.querySelector('.fas.fa-chalkboard-teacher')?.parentElement?.textContent?.replace('👨‍🏫', '')?.trim() || '';
            const studentCount = card.querySelector('.fas.fa-users')?.parentElement?.textContent?.replace('👥', '')?.trim() || '';
            const status = card.querySelector('.badge')?.textContent?.trim() || '';
            const id = card.querySelector('.card-header .badge')?.textContent?.replace('ID: ', '')?.trim() || '';
            
            csv += `"${id}","${sectionName}","${gradeLevel}","${teacher}","${studentCount}","${status}"\n`;
        });
        
        return csv;
    }
    
    function generateAttendanceCSV(tabContent) {
        let csv = 'Date,Student Name,Section,Status,Time In,Time Out,Teacher,Remarks\n';
        
        const attendanceCards = tabContent.querySelectorAll('.col .card');
        attendanceCards.forEach(function(card) {
            const date = card.querySelector('.card-header .small')?.textContent?.trim() || '';
            const studentName = card.querySelector('h6')?.textContent?.trim() || '';
            const section = card.querySelector('.fas.fa-users')?.parentElement?.textContent?.replace('👥', '')?.trim() || '';
            const status = card.querySelector('.badge')?.textContent?.trim() || '';
            const timeIn = card.querySelector('.fas.fa-clock')?.parentElement?.textContent?.replace('🕐', '')?.trim() || '';
            const timeOut = card.querySelector('.fas.fa-sign-out-alt')?.parentElement?.textContent?.replace('🚪', '')?.trim() || '';
            const teacher = card.querySelector('.fas.fa-chalkboard-teacher')?.parentElement?.textContent?.replace('👨‍🏫', '')?.trim() || '';
            const remarks = card.querySelector('.card-text')?.textContent?.trim() || '';
            
            csv += `"${date}","${studentName}","${section}","${status}","${timeIn}","${timeOut}","${teacher}","${remarks}"\n`;
        });
        
        return csv;
    }
    
    function generateStudentsSectionCSV(tabContent) {
        let csv = 'Section,Student Name,Username,LRN,Status\n';
        
        const sectionCards = tabContent.querySelectorAll('.card.mb-4');
        sectionCards.forEach(function(sectionCard) {
            const sectionName = sectionCard.querySelector('.card-header h5')?.textContent?.trim() || '';
            const studentCards = sectionCard.querySelectorAll('.row .col .card');
            
            studentCards.forEach(function(studentCard) {
                const studentName = studentCard.querySelector('.fw-bold')?.textContent?.trim() || '';
                const username = studentCard.querySelector('.text-muted.small')?.textContent?.trim() || '';
                const lrn = studentCard.querySelector('.badge.bg-info')?.textContent?.replace('LRN: ', '')?.trim() || '';
                const status = studentCard.querySelector('.badge.bg-success, .badge.bg-warning')?.textContent?.trim() || '';
                
                csv += `"${sectionName}","${studentName}","${username}","${lrn}","${status}"\n`;
            });
        });
        
        return csv;
    }
    
    function generateQRCodesCSV(tabContent) {
        let csv = 'Student ID,Name,Username,LRN,Section,Grade Level,QR Code\n';
        
        const qrCards = tabContent.querySelectorAll('.qr-card');
        qrCards.forEach(function(card) {
            const studentName = card.querySelector('.fw-bold')?.textContent?.trim() || '';
            const studentId = card.querySelector('.badge')?.textContent?.trim() || '';
            const details = card.querySelector('.small')?.textContent?.trim() || '';
            // Parse details to extract username, LRN, section, etc.
            const username = details.match(/Username: ([^|]+)/)?.[1]?.trim() || '';
            const lrn = details.match(/LRN: ([^|]+)/)?.[1]?.trim() || '';
            const section = details.match(/Section: ([^|]+)/)?.[1]?.trim() || '';
            const gradeLevel = details.match(/Grade: ([^|]+)/)?.[1]?.trim() || '';
            
            csv += `"${studentId}","${studentName}","${username}","${lrn}","${section}","${gradeLevel}","Generated"\n`;
        });
        
        return csv;
    }
    
    function generateAttendanceSectionCSV(tabContent) {
        let csv = 'Section,Grade Level,Total Records,Present,Absent,Late,Out\n';
        
        const sectionCards = tabContent.querySelectorAll('.card.mb-4');
        sectionCards.forEach(function(card) {
            const sectionName = card.querySelector('.card-header h5')?.textContent?.trim() || '';
            const gradeLevel = card.querySelector('.card-header h5')?.textContent?.match(/Grade \d+/)?.[0] || '';
            const totalRecords = card.querySelector('.display-4')?.textContent?.trim() || '0';
            
            // Extract attendance counts from the card content
            const present = card.textContent.match(/Present:\s*(\d+)/)?.[1] || '0';
            const absent = card.textContent.match(/Absent:\s*(\d+)/)?.[1] || '0';
            const late = card.textContent.match(/Late:\s*(\d+)/)?.[1] || '0';
            const out = card.textContent.match(/Out:\s*(\d+)/)?.[1] || '0';
            
            csv += `"${sectionName}","${gradeLevel}","${totalRecords}","${present}","${absent}","${late}","${out}"\n`;
        });
        
        return csv;
    }
    
    function generateAttendanceStudentCSV(tabContent) {
        let csv = 'Student Name,Section,Total Records,Present,Absent,Late,Out,Attendance Rate\n';
        
        const studentCards = tabContent.querySelectorAll('.col .card');
        studentCards.forEach(function(card) {
            const studentName = card.querySelector('.fw-bold')?.textContent?.trim() || '';
            const section = card.querySelector('.small.mb-0')?.textContent?.trim() || '';
            
            // Extract attendance counts from the card content
            const present = card.textContent.match(/Present:\s*(\d+)/)?.[1] || '0';
            const absent = card.textContent.match(/Absent:\s*(\d+)/)?.[1] || '0';
            const late = card.textContent.match(/Late:\s*(\d+)/)?.[1] || '0';
            const out = card.textContent.match(/Out:\s*(\d+)/)?.[1] || '0';
            const totalRecords = parseInt(present) + parseInt(absent) + parseInt(late) + parseInt(out);
            const attendanceRate = totalRecords > 0 ? ((parseInt(present) / totalRecords) * 100).toFixed(1) + '%' : '0%';
            
            csv += `"${studentName}","${section}","${totalRecords}","${present}","${absent}","${late}","${out}","${attendanceRate}"\n`;
        });
        
        return csv;
    }
    
    function generateSMSCSV(tabContent) {
        let csv = 'Date,Message,Phone Number,Status,Type\n';
        
        const smsCards = tabContent.querySelectorAll('.col .card');
        smsCards.forEach(function(card) {
            const date = card.querySelector('.card-header .small')?.textContent?.trim() || '';
            const message = card.querySelector('.card-text')?.textContent?.trim() || '';
            const phone = card.querySelector('.fas.fa-phone')?.parentElement?.textContent?.replace('📱', '')?.trim() || '';
            const status = card.querySelector('.badge')?.textContent?.trim() || '';
            const type = card.querySelector('.card-title')?.textContent?.trim() || '';
            
            csv += `"${date}","${message}","${phone}","${status}","${type}"\n`;
        });
        
        return csv;
    }
    
    // Function to generate PDF from tab content
    function generateTabPDF(targetId, tabContent) {
        // Map targetId to existing report types
        let reportType = '';
        let queryParams = new URLSearchParams();
        
        switch(targetId) {
            case 'users':
                reportType = 'students_list';
                break;
            case 'sections':
                reportType = 'students_per_section';
                break;
            case 'attendance':
                reportType = 'attendance_records';
                // Add default date range
                queryParams.append('date_from', new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0]);
                queryParams.append('date_to', new Date().toISOString().split('T')[0]);
                break;
            case 'students-section':
                reportType = 'students_per_section';
                break;
            case 'qr-codes':
                reportType = 'student_qr';
                break;
            case 'attendance-section':
                reportType = 'attendance_per_section';
                // Add default date range
                queryParams.append('date_from', new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0]);
                queryParams.append('date_to', new Date().toISOString().split('T')[0]);
                break;
            case 'attendance-student':
                reportType = 'attendance_per_student';
                // Add default date range
                queryParams.append('date_from', new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0]);
                queryParams.append('date_to', new Date().toISOString().split('T')[0]);
                break;
            default:
                alert('PDF export not available for this data type.');
                return;
        }
        
        // Build URL for PDF generation
        queryParams.append('generate', '1');
        queryParams.append('type', reportType);
        queryParams.append('format', 'pdf');
        
        const url = 'reports.php?' + queryParams.toString();
        
        // Open PDF in new tab
        window.open(url, '_blank');
    }
});
</script>

<!-- Include report table generation functions -->
<script src="assets/js/report-tables.js"></script>

<script>
    // Initialize tab filter functionality
    document.addEventListener('DOMContentLoaded', function() {
        // Tab filters will be initialized by Select2 in footer.php
        
        // Re-initialize Select2 when tabs are shown
        $('a[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
            $(e.target.getAttribute('data-bs-target')).find('select.select2').each(function() {
                if (!$(this).hasClass('select2-hidden-accessible')) {
                    $(this).select2({
                        theme: 'bootstrap-5',
                        width: '100%'
                    });
                }
            });
        });
        
        // Users tab search and filter functionality
        $(document).on('input change', '#userSearchInput, #userRoleFilter, #userStatusFilter', function() {
            const searchTerm = $('#userSearchInput').val().toLowerCase();
            const roleFilter = $('#userRoleFilter').val().toLowerCase();
            const statusFilter = $('#userStatusFilter').val().toLowerCase();
            
            $('#users .col').each(function() {
                const card = $(this).find('.card');
                const userName = card.find('.card-title').text().toLowerCase();
                const userUsername = card.find('.text-muted.small').text().toLowerCase();
                const userEmail = card.find('.fas.fa-envelope').parent().text().toLowerCase();
                const userRole = card.find('.badge').first().text().toLowerCase();
                const userStatus = card.find('.badge').last().text().toLowerCase();
                
                const matchesSearch = !searchTerm || 
                    userName.includes(searchTerm) || 
                    userUsername.includes(searchTerm) || 
                    userEmail.includes(searchTerm);
                    
                const matchesRole = !roleFilter || userRole.includes(roleFilter);
                const matchesStatus = !statusFilter || userStatus.includes(statusFilter);
                
                $(this).toggle(matchesSearch && matchesRole && matchesStatus);
            });
        });
        
        // Sections tab search and filter functionality
        $(document).on('input change', '#sectionSearchInput, #gradeLevelFilter, #sectionStatusFilter', function() {
            const searchTerm = $('#sectionSearchInput').val().toLowerCase();
            const gradeFilter = $('#gradeLevelFilter').val().toLowerCase();
            const statusFilter = $('#sectionStatusFilter').val().toLowerCase();
            
            $('#sections .col').each(function() {
                const card = $(this).find('.card');
                const sectionName = card.find('.card-title').text().toLowerCase();
                const gradeLevel = card.find('.fw-bold').text().toLowerCase();
                const sectionStatus = card.find('.badge').text().toLowerCase();
                
                const matchesSearch = !searchTerm || sectionName.includes(searchTerm);
                const matchesGrade = !gradeFilter || gradeLevel.includes(gradeFilter);
                const matchesStatus = !statusFilter || sectionStatus.includes(statusFilter);
                
                $(this).toggle(matchesSearch && matchesGrade && matchesStatus);
            });
        });
        
        // Attendance tab search and filter functionality
        $(document).on('input change', '#attendanceSearchInput, #attendanceStatusFilter, #attendanceDateFilter, #attendanceSectionFilter', function() {
            const searchTerm = $('#attendanceSearchInput').val().toLowerCase();
            const statusFilter = $('#attendanceStatusFilter').val().toLowerCase();
            const dateFilter = $('#attendanceDateFilter').val();
            const sectionFilter = $('#attendanceSectionFilter').val().toLowerCase();
            
            $('#attendance .col').each(function() {
                const card = $(this).find('.card');
                const studentName = card.find('h6').text().toLowerCase();
                const status = card.find('.badge').first().text().toLowerCase();
                const date = card.find('.card-header .small').text();
                const section = card.find('.fas.fa-users').parent().text().toLowerCase();
                
                const matchesSearch = !searchTerm || studentName.includes(searchTerm);
                const matchesStatus = !statusFilter || status.includes(statusFilter);
                const matchesSection = !sectionFilter || section.includes(sectionFilter);
                
                // Date filtering logic
                let matchesDate = true;
                if (dateFilter) {
                    const recordDate = new Date(date);
                    const today = new Date();
                    today.setHours(0, 0, 0, 0);
                    
                    const yesterday = new Date(today);
                    yesterday.setDate(yesterday.getDate() - 1);
                    
                    const thisWeekStart = new Date(today);
                    thisWeekStart.setDate(thisWeekStart.getDate() - thisWeekStart.getDay());
                    
                    const lastWeekStart = new Date(thisWeekStart);
                    lastWeekStart.setDate(lastWeekStart.getDate() - 7);
                    
                    const lastWeekEnd = new Date(thisWeekStart);
                    lastWeekEnd.setDate(lastWeekEnd.getDate() - 1);
                    
                    switch(dateFilter) {
                        case 'today':
                            matchesDate = recordDate.toDateString() === today.toDateString();
                            break;
                        case 'yesterday':
                            matchesDate = recordDate.toDateString() === yesterday.toDateString();
                            break;
                        case 'thisweek':
                            matchesDate = recordDate >= thisWeekStart && recordDate <= today;
                            break;
                        case 'lastweek':
                            matchesDate = recordDate >= lastWeekStart && recordDate <= lastWeekEnd;
                            break;
                    }
                }
                
                $(this).toggle(matchesSearch && matchesStatus && matchesDate && matchesSection);
            });
        });
        
        // Students Per Section tab search and filter functionality
        $(document).on('input change', '#sectionStudentSearchInput, #sectionGradeFilter', function() {
            const searchTerm = $('#sectionStudentSearchInput').val().toLowerCase();
            const gradeFilter = $('#sectionGradeFilter').val().toLowerCase();
            
            // First filter the section cards
            $('#students-section .card.mb-4').each(function() {
                const sectionName = $(this).find('.card-header h5').text().toLowerCase();
                const gradeLevel = $(this).find('.card-header h5').text().toLowerCase();
                
                const matchesSearch = !searchTerm || sectionName.includes(searchTerm);
                const matchesGrade = !gradeFilter || gradeLevel.includes(gradeFilter);
                
                if (matchesSearch && matchesGrade) {
                    $(this).show();
                    
                    // If section matches, filter its students
                    if (searchTerm) {
                        $(this).find('.col').each(function() {
                            const studentName = $(this).find('h6').text().toLowerCase();
                            const studentUsername = $(this).find('.text-muted.small').text().toLowerCase();
                            
                            const studentMatchesSearch = studentName.includes(searchTerm) || 
                                                        studentUsername.includes(searchTerm);
                            
                            $(this).toggle(studentMatchesSearch);
                        });
                    } else {
                        // Show all students if no search term
                        $(this).find('.col').show();
                    }
                } else {
                    $(this).hide();
                }
            });
        });
        
        // QR Codes tab search and filter functionality
        $(document).on('input change', '#qrSearchInput, #qrSectionFilter', function() {
            const searchTerm = $('#qrSearchInput').val().toLowerCase();
            const sectionFilter = $('#qrSectionFilter').val().toLowerCase();
            
            $('#qr-codes .qr-card').each(function() {
                const studentName = $(this).find('.fw-bold').text().toLowerCase();
                const studentId = $(this).find('.badge').text().toLowerCase();
                const section = $(this).find('strong:contains("Section:")').parent().text().toLowerCase();
                
                const matchesSearch = !searchTerm || 
                    studentName.includes(searchTerm) || 
                    studentId.includes(searchTerm);
                    
                const matchesSection = !sectionFilter || section.includes(sectionFilter);
                
                $(this).closest('.col').toggle(matchesSearch && matchesSection);
            });
        });
        
        // Attendance Per Section tab search and filter functionality
        $(document).on('input change', '#attendanceSectionSearchInput, #attendanceGradeFilter, #attendanceRateFilter', function() {
            const searchTerm = $('#attendanceSectionSearchInput').val().toLowerCase();
            const gradeFilter = $('#attendanceGradeFilter').val().toLowerCase();
            const rateFilter = $('#attendanceRateFilter').val();
            
            $('#attendance-section .card.mb-4').each(function() {
                const sectionName = $(this).find('.card-header h5').text().toLowerCase();
                const gradeLevel = $(this).find('.card-header h5').text().toLowerCase();
                
                // Calculate attendance rate
                let presentCount = 0;
                let totalCount = 0;
                
                try {
                    presentCount = parseInt($(this).find('.text-success').text()) || 0;
                    totalCount = parseInt($(this).find('.display-4').text()) || 1;
                } catch(e) {
                    // In case of parsing error, default to 0
                }
                
                const attendanceRate = (presentCount / totalCount) * 100;
                
                const matchesSearch = !searchTerm || sectionName.includes(searchTerm);
                const matchesGrade = !gradeFilter || gradeLevel.includes(gradeFilter);
                
                let matchesRate = true;
                if (rateFilter) {
                    switch(rateFilter) {
                        case 'high':
                            matchesRate = attendanceRate >= 90;
                            break;
                        case 'medium':
                            matchesRate = attendanceRate >= 75 && attendanceRate < 90;
                            break;
                        case 'low':
                            matchesRate = attendanceRate < 75;
                            break;
                    }
                }
                
                $(this).toggle(matchesSearch && matchesGrade && matchesRate);
            });
        });
        
        // Attendance Per Student tab search and filter functionality
        $('#studentAttendanceSearchInput, #studentSectionFilter, #studentAttendanceRateFilter, #studentStatusFilter').on('input change', function() {
            const searchTerm = $('#studentAttendanceSearchInput').val().toLowerCase();
            const sectionFilter = $('#studentSectionFilter').val().toLowerCase();
            const rateFilter = $('#studentAttendanceRateFilter').val();
            const statusFilter = $('#studentStatusFilter').val().toLowerCase();
            
            $('#attendance-student .col').each(function() {
                const card = $(this).find('.card');
                const studentName = card.find('.fw-bold').text().toLowerCase();
                const section = card.find('.small.mb-0').text().toLowerCase();
                
                // Get attendance counts
                let presentCount = 0;
                let absentCount = 0;
                let lateCount = 0;
                let outCount = 0;
                let totalCount = 0;
                
                try {
                    presentCount = parseInt(card.find('.text-success').text()) || 0;
                    absentCount = parseInt(card.find('.text-danger').text()) || 0;
                    lateCount = parseInt(card.find('.text-warning').text()) || 0;
                    outCount = parseInt(card.find('.text-info').text()) || 0;
                    totalCount = presentCount + absentCount + lateCount + outCount || 1;
                } catch(e) {
                    // In case of parsing error, default to 0
                }
                
                const attendanceRate = (presentCount / totalCount) * 100;
                
                // Determine most common status
                let mostCommonStatus = 'present';
                let maxCount = presentCount;
                
                if (absentCount > maxCount) {
                    mostCommonStatus = 'absent';
                    maxCount = absentCount;
                }
                
                if (lateCount > maxCount) {
                    mostCommonStatus = 'late';
                    maxCount = lateCount;
                }
                
                if (outCount > maxCount) {
                    mostCommonStatus = 'out';
                    maxCount = outCount;
                }
                
                const matchesSearch = !searchTerm || studentName.includes(searchTerm);
                const matchesSection = !sectionFilter || section.includes(sectionFilter);
                
                let matchesRate = true;
                if (rateFilter) {
                    switch(rateFilter) {
                        case 'high':
                            matchesRate = attendanceRate >= 90;
                            break;
                        case 'medium':
                            matchesRate = attendanceRate >= 75 && attendanceRate < 90;
                            break;
                        case 'low':
                            matchesRate = attendanceRate < 75;
                            break;
                    }
                }
                
                const matchesStatus = !statusFilter || mostCommonStatus === statusFilter;
                
                $(this).toggle(matchesSearch && matchesSection && matchesRate && matchesStatus);
            });
        });
    });
</script>

<?php include 'footer.php'; ?>
