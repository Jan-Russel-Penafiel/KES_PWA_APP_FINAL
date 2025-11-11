<?php
require_once '../config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'teacher'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$section_id = intval($_GET['section_id'] ?? 0);
$current_user_id = $_SESSION['user_id'];
$current_role = $_SESSION['role'];

if ($section_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid section ID']);
    exit;
}

try {
    // Build query based on user role
    if ($current_role == 'admin') {
        // Admins can see all subjects in the section
        $stmt = $pdo->prepare("
            SELECT id, subject_name, subject_code, grade_level, teacher_id 
            FROM subjects 
            WHERE section_id = ? AND status = 'active' 
            ORDER BY subject_name
        ");
        $stmt->execute([$section_id]);
    } else {
        // Teachers can only see subjects they own in the section
        $stmt = $pdo->prepare("
            SELECT id, subject_name, subject_code, grade_level, teacher_id 
            FROM subjects 
            WHERE section_id = ? AND teacher_id = ? AND status = 'active' 
            ORDER BY subject_name
        ");
        $stmt->execute([$section_id, $current_user_id]);
    }
    
    $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'subjects' => $subjects,
        'filter_info' => [
            'section_id' => $section_id,
            'teacher_filtered' => $current_role == 'teacher',
            'teacher_id' => $current_role == 'teacher' ? $current_user_id : null
        ]
    ]);
    
} catch (PDOException $e) {
    error_log("Error fetching subjects by section: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>