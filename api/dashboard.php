<?php
// Include database configuration
require_once '../config.php';

// Set header to JSON response
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Default response
$response = [
    'success' => false,
    'message' => 'Invalid request'
];

try {
    // Check if user is authenticated
    if (!isLoggedIn()) {
        $response['message'] = 'Authentication required';
        echo json_encode($response);
        exit();
    }

    $user_id = $_SESSION['user_id'];
    $user_role = $_SESSION['role'];

    // Get dashboard data based on role
    if ($user_role === 'admin') {
        // Admin dashboard data
        $total_students = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student' AND status = 'active'")->fetchColumn();
        $total_teachers = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'teacher' AND status = 'active'")->fetchColumn();
        $total_parents = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'parent' AND status = 'active'")->fetchColumn();
        $total_sections = $pdo->query("SELECT COUNT(*) FROM sections WHERE status = 'active'")->fetchColumn();
        
        $today_attendance = $pdo->query("SELECT COUNT(*) FROM attendance WHERE attendance_date = CURDATE()")->fetchColumn();
        $present_today = $pdo->query("SELECT COUNT(*) FROM attendance WHERE attendance_date = CURDATE() AND status = 'present'")->fetchColumn();
        $absent_today = $pdo->query("SELECT COUNT(*) FROM attendance WHERE attendance_date = CURDATE() AND status = 'absent'")->fetchColumn();
        $late_today = $pdo->query("SELECT COUNT(*) FROM attendance WHERE attendance_date = CURDATE() AND status = 'late'")->fetchColumn();

        // Recent activities
        $recent_activities = $pdo->query("
            SELECT a.*, u.full_name as student_name, s.section_name 
            FROM attendance a 
            JOIN users u ON a.student_id = u.id 
            JOIN sections s ON a.section_id = s.id 
            WHERE a.attendance_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAYS)
            ORDER BY a.created_at DESC 
            LIMIT 10
        ")->fetchAll(PDO::FETCH_ASSOC);
        
        $response = [
            'success' => true,
            'data' => [
                'totalStudents' => (int)$total_students,
                'totalTeachers' => (int)$total_teachers,
                'totalParents' => (int)$total_parents,
                'totalSections' => (int)$total_sections,
                'presentToday' => (int)$present_today,
                'absentToday' => (int)$absent_today,
                'lateToday' => (int)$late_today,
                'totalToday' => (int)$today_attendance,
                'recentActivities' => $recent_activities
            ]
        ];
        
    } elseif ($user_role === 'teacher') {
        // Teacher dashboard data
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM sections WHERE teacher_id = ? AND status = 'active'");
        $stmt->execute([$user_id]);
        $total_sections = $stmt->fetchColumn();
        
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE section_id IN (SELECT id FROM sections WHERE teacher_id = ?) AND role = 'student' AND status = 'active'");
        $stmt->execute([$user_id]);
        $total_students = $stmt->fetchColumn();
        
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM qr_scans WHERE teacher_id = ? AND DATE(scan_time) = CURDATE()");
        $stmt->execute([$user_id]);
        $today_scans = $stmt->fetchColumn();

        // Get teacher's sections
        $stmt = $pdo->prepare("SELECT id, section_name, grade_level FROM sections WHERE teacher_id = ? AND status = 'active' ORDER BY section_name");
        $stmt->execute([$user_id]);
        $sections = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get teacher's subjects
        $stmt = $pdo->prepare("SELECT id, subject_name, subject_code FROM subjects WHERE teacher_id = ? AND status = 'active' ORDER BY subject_name");
        $stmt->execute([$user_id]);
        $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $response = [
            'success' => true,
            'data' => [
                'totalStudents' => (int)$total_students,
                'totalSections' => (int)$total_sections,
                'todayScans' => (int)$today_scans,
                'sections' => $sections,
                'subjects' => $subjects
            ]
        ];
        
    } elseif ($user_role === 'student') {
        // Student dashboard data
        $attendance_data = evaluateAttendance($pdo, $user_id);
        
        // Get student's QR code
        $stmt = $pdo->prepare("SELECT qr_code FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $qr_code = $user['qr_code'] ?? generateStudentQR($user_id);
        
        // Update QR code if it doesn't exist
        if (!$user['qr_code']) {
            $update_stmt = $pdo->prepare("UPDATE users SET qr_code = ? WHERE id = ?");
            $update_stmt->execute([$qr_code, $user_id]);
        }

        // Get recent attendance records
        $stmt = $pdo->prepare("
            SELECT a.*, s.section_name 
            FROM attendance a 
            JOIN sections s ON a.section_id = s.id 
            WHERE a.student_id = ? AND a.attendance_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAYS)
            ORDER BY a.created_at DESC 
            LIMIT 10
        ");
        $stmt->execute([$user_id]);
        $recent_attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $response = [
            'success' => true,
            'data' => [
                'totalDays' => $attendance_data['total_days'],
                'presentDays' => $attendance_data['present_days'],
                'lateDays' => $attendance_data['late_days'],
                'absentDays' => $attendance_data['absent_days'],
                'attendanceRate' => $attendance_data['attendance_rate'],
                'evaluation' => $attendance_data['evaluation'],
                'qrCode' => $qr_code,
                'recentAttendance' => $recent_attendance
            ]
        ];
        
    } elseif ($user_role === 'parent') {
        // Parent dashboard data
        $stmt = $pdo->prepare("
            SELECT u.* 
            FROM users u 
            JOIN student_parents sp ON u.id = sp.student_id 
            WHERE sp.parent_id = ?
        ");
        $stmt->execute([$user_id]);
        $children = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get attendance data for each child
        foreach ($children as &$child) {
            $attendance_data = evaluateAttendance($pdo, $child['id']);
            $child['attendanceRate'] = $attendance_data['attendance_rate'];
            $child['evaluation'] = $attendance_data['evaluation'];
        }
        
        $response = [
            'success' => true,
            'data' => [
                'children' => $children,
                'totalChildren' => count($children)
            ]
        ];
    }

} catch (PDOException $e) {
    $response['message'] = 'Database error: ' . $e->getMessage();
} catch (Exception $e) {
    $response['message'] = 'Error: ' . $e->getMessage();
}

// Send JSON response
echo json_encode($response);
?>
