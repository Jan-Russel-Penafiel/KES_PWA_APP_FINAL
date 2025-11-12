<?php
// Include required files
require_once "../config.php";
require_once "../sms_functions.php";

header('Content-Type: application/json');

// Check if user is logged in and is a teacher
if (!isLoggedIn() || $_SESSION['role'] !== 'teacher') {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access'
    ]);
    exit;
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
    exit;
}

try {
    // Get current teacher information
    $teacher_id = $_SESSION['user_id'];
    $current_user = getCurrentUser($pdo);
    
    if (!$current_user) {
        throw new Exception('Teacher information not found');
    }
    
    $teacher_name = $current_user['full_name'];
    $today = date('Y-m-d');
    $current_time = date('g:i A');
    
    // Get all students that this teacher teaches
    $stmt = $pdo->prepare("
        SELECT DISTINCT s.id, s.full_name as student_name, 
               sub.subject_name, sub.subject_code
        FROM users s
        JOIN student_subjects ss ON s.id = ss.student_id
        JOIN subjects sub ON ss.subject_id = sub.id
        WHERE sub.teacher_id = ? 
        AND s.role = 'student' 
        AND s.status = 'active'
        AND ss.status = 'enrolled'
        AND sub.status = 'active'
        ORDER BY s.full_name, sub.subject_name
    ");
    $stmt->execute([$teacher_id]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($students)) {
        throw new Exception('No students found for this teacher');
    }
    
    // Check if already sent absent notification today
    $check_stmt = $pdo->prepare("
        SELECT COUNT(*) FROM teacher_absent_logs 
        WHERE teacher_id = ? AND DATE(created_at) = ?
    ");
    $check_stmt->execute([$teacher_id, $today]);
    $already_sent_today = $check_stmt->fetchColumn() > 0;
    
    if ($already_sent_today) {
        echo json_encode([
            'success' => false,
            'message' => 'Teacher absent notification already sent today'
        ]);
        exit;
    }
    
    // Compose SMS message
    $message = "NOTIFICATION: Your child's teacher, {$teacher_name}, is absent today ({$today}). Please check with the school for alternative arrangements. - KES SMART";
    
    // Send SMS to all parents
    $successful_sms = 0;
    $failed_sms = 0;
    $sms_details = [];
    
    foreach ($students as $student) {
        $sms_result = sendSMSNotificationToParent(
            $student['id'], 
            $message, 
            'teacher_absent'
        );
        
        if ($sms_result['success']) {
            $successful_sms++;
        } else {
            $failed_sms++;
        }
        
        $sms_details[] = [
            'student_name' => $student['student_name'],
            'subject' => $student['subject_name'] . ' (' . $student['subject_code'] . ')',
            'sms_status' => $sms_result['success'] ? 'sent' : 'failed',
            'sms_message' => $sms_result['message']
        ];
    }
    
    // Log the teacher absent notification
    $log_stmt = $pdo->prepare("
        INSERT INTO teacher_absent_logs 
        (teacher_id, teacher_name, notification_date, students_notified, sms_sent, sms_failed, created_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    $log_stmt->execute([
        $teacher_id,
        $teacher_name,
        $today,
        count($students),
        $successful_sms,
        $failed_sms
    ]);
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => "Teacher absent notification sent successfully",
        'data' => [
            'teacher_name' => $teacher_name,
            'date' => $today,
            'time' => $current_time,
            'total_students' => count($students),
            'sms_sent' => $successful_sms,
            'sms_failed' => $failed_sms,
            'details' => $sms_details
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Teacher Absent API Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error sending teacher absent notification: ' . $e->getMessage()
    ]);
}
?>