<?php
require_once '../config.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$parent_id = isset($_GET['parent_id']) ? intval($_GET['parent_id']) : 0;

if (!$parent_id) {
    echo json_encode(['success' => false, 'error' => 'Parent ID required']);
    exit;
}

try {
    // Get students linked to this parent
    $query = "
        SELECT sp.id as relationship_id, 
               sp.relationship, 
               s.id as student_id, 
               s.full_name, 
               s.username
        FROM student_parents sp
        JOIN users s ON sp.student_id = s.id
        WHERE sp.parent_id = ? AND s.status = 'active'
        ORDER BY s.full_name ASC
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$parent_id]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'students' => $students
    ]);
    
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>