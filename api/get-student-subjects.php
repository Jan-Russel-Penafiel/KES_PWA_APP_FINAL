<?php
/**
 * Get Student Subjects API
 * Returns the subjects a student is currently enrolled in
 */

require_once dirname(__DIR__) . '/config.php';

// Return JSON response
header('Content-Type: application/json');

// Allow CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Check if user is logged in and has permission
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$current_user = getCurrentUser($pdo);
$user_role = $_SESSION['role'];

// Only allow admin and teacher access
if (!in_array($user_role, ['admin', 'teacher'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

// Get student ID from query parameter
$student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;

if ($student_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid student ID']);
    exit;
}

try {
    // Verify student exists and user has permission to view
    $student_check_query = "
        SELECT u.id, u.full_name, u.section_id
        FROM users u
        WHERE u.id = ? AND u.role = 'student' AND u.status = 'active'
    ";
    
    // If teacher, check if they have access to this student's section
    if ($user_role == 'teacher') {
        $student_check_query .= " AND u.section_id IN (SELECT id FROM sections WHERE teacher_id = ?)";
        $student_check_stmt = $pdo->prepare($student_check_query);
        $student_check_stmt->execute([$student_id, $current_user['id']]);
    } else {
        $student_check_stmt = $pdo->prepare($student_check_query);
        $student_check_stmt->execute([$student_id]);
    }
    
    $student = $student_check_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$student) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Student not found or access denied']);
        exit;
    }
    
    // Get student's current subject enrollments
    $subjects_query = "
        SELECT ss.subject_id, s.subject_name, s.subject_code, s.grade_level
        FROM student_subjects ss
        INNER JOIN subjects s ON ss.subject_id = s.id
        WHERE ss.student_id = ? 
        AND ss.status = 'enrolled'
        AND s.status = 'active'
        ORDER BY s.subject_name
    ";
    
    $subjects_stmt = $pdo->prepare($subjects_query);
    $subjects_stmt->execute([$student_id]);
    $enrolled_subjects = $subjects_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Extract subject IDs for easy checking
    $subject_ids = array_column($enrolled_subjects, 'subject_id');
    
    echo json_encode([
        'success' => true,
        'student' => [
            'id' => $student['id'],
            'full_name' => $student['full_name'],
            'section_id' => $student['section_id']
        ],
        'subjects' => $subject_ids, // Array of subject IDs for checkbox checking
        'enrolled_subjects' => $enrolled_subjects, // Full subject details
        'total_enrolled' => count($enrolled_subjects)
    ]);
    
} catch (Exception $e) {
    error_log("Get student subjects API error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error occurred',
        'error' => $e->getMessage()
    ]);
}
?>