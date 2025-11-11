<?php
/**
 * Auto Absent API - Marks students as absent after 4:30 PM
 * 
 * This API endpoint can be called from the frontend to automatically
 * mark students as absent if they haven't checked in by 4:30 PM.
 */

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/sms_functions.php';

// Set timezone to Philippines
date_default_timezone_set('Asia/Manila');

// Return JSON response
header('Content-Type: application/json');

// Allow CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $today = date('Y-m-d');
    $current_time = date('H:i:s');
    $day_of_week = date('N'); // 1 (Monday) to 7 (Sunday)
    
    // Only run on weekdays (Monday to Friday)
    if ($day_of_week > 5) {
        echo json_encode([
            'success' => false, 
            'message' => 'Auto-absent only runs on weekdays',
            'data' => ['is_weekend' => true]
        ]);
        exit;
    }
    
    // Only run this script after 4:30 PM
    if ($current_time < '16:30:00') {
        echo json_encode([
            'success' => false, 
            'message' => 'Auto-absent only runs after 4:30 PM',
            'data' => ['current_time' => $current_time, 'cutoff_time' => '16:30:00']
        ]);
        exit;
    }
    
    // Check if auto-absent has already been run today
    $check_auto_absent = $pdo->prepare("
        SELECT COUNT(*) as auto_absent_count 
        FROM attendance 
        WHERE attendance_date = ? 
        AND status = 'absent' 
        AND (remarks LIKE '%Auto-marked absent%' OR remarks LIKE '%auto-marked absent%')
    ");
    $check_auto_absent->execute([$today]);
    $auto_absent_count = $check_auto_absent->fetchColumn();
    
    if ($auto_absent_count > 0) {
        echo json_encode([
            'success' => true, 
            'message' => 'Auto-absent has already been processed today',
            'data' => [
                'already_processed' => true,
                'auto_absent_count' => $auto_absent_count,
                'date' => $today
            ]
        ]);
        exit;
    }
    
    // Find students and their subjects that don't have attendance records for today
    // First, get all active students
    $students_query = "
        SELECT u.id, u.username, u.full_name, u.section_id, s.section_name, s.grade_level
        FROM users u
        LEFT JOIN sections s ON u.section_id = s.id
        WHERE u.role = 'student' 
        AND u.status = 'active'
    ";
    
    $students_stmt = $pdo->prepare($students_query);
    $students_stmt->execute();
    $all_students = $students_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // For each student, find subjects they should have attendance for but don't
    $marked_count = 0;
    $sms_sent_count = 0;
    $sms_failed_count = 0;
    $processed_students = [];
    $total_attendance_records = 0;
    
    foreach ($all_students as $student) {
        // Ensure we have a valid section_id, default to 1 if null
        $section_id = $student['section_id'] ? $student['section_id'] : 1;
        
        // Get subjects the student is actually enrolled in (not all subjects)
        $subjects_query = "
            SELECT s.id, s.subject_name, s.teacher_id
            FROM subjects s
            INNER JOIN student_subjects ss ON s.id = ss.subject_id
            WHERE s.status = 'active'
            AND ss.student_id = ?
            AND ss.status = 'enrolled'
            ORDER BY s.subject_name
        ";
        
        $subjects_stmt = $pdo->prepare($subjects_query);
        $subjects_stmt->execute([$student['id']]);
        $subjects = $subjects_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // If no enrolled subjects found, skip this student (they're not enrolled in any subjects)
        if (empty($subjects)) {
            error_log("Auto-absent: Student ID {$student['id']} ({$student['full_name']}) has no enrolled subjects - skipping", 3, dirname(__DIR__) . '/logs/auto_absent.log');
            continue;
        }
        
        $student_absent_subjects = [];
        $student_attendance_count = 0;
        
        foreach ($subjects as $subject) {
            $subject_id = $subject['id'];
            $teacher_id = $subject['teacher_id'] ?: 1;
            
            // Check if student already has attendance for this subject today
            $check_attendance = $pdo->prepare("
                SELECT id FROM attendance 
                WHERE student_id = ? 
                AND attendance_date = ? 
                AND (subject_id = ? OR (subject_id IS NULL AND ? IS NULL))
            ");
            $check_attendance->execute([$student['id'], $today, $subject_id, $subject_id]);
            $existing_attendance = $check_attendance->fetch(PDO::FETCH_ASSOC);
            
            if (!$existing_attendance) {
                // Create absent attendance record for this subject
                $insert_stmt = $pdo->prepare("
                    INSERT INTO attendance 
                    (student_id, teacher_id, section_id, subject_id, attendance_date, time_in, time_out, status, remarks, qr_scanned, attendance_source, created_at) 
                    VALUES (?, ?, ?, ?, ?, NULL, NULL, 'absent', ?, 0, 'auto', NOW())
                ");
                
                $subject_name = $subject['subject_name'] ?: 'General';
                $remarks = "Auto-marked absent for enrolled subject: $subject_name - no check-in by 4:30 PM on " . date('Y-m-d H:i:s');
                
                $insert_result = $insert_stmt->execute([
                    $student['id'],           // student_id
                    $teacher_id,              // teacher_id
                    $section_id,              // section_id
                    $subject_id,              // subject_id
                    $today,                   // attendance_date
                    $remarks                  // remarks
                ]);
                
                if ($insert_result) {
                    $attendance_id = $pdo->lastInsertId();
                    $student_attendance_count++;
                    $total_attendance_records++;
                    
                    $student_absent_subjects[] = [
                        'subject_id' => $subject_id,
                        'subject_name' => $subject_name,
                        'attendance_id' => $attendance_id,
                        'teacher_id' => $teacher_id
                    ];
                    
                    // Log successful insertion
                    error_log("Auto-absent: Marked student ID {$student['id']} ({$student['full_name']}) as absent for enrolled subject: $subject_name. Attendance ID: $attendance_id", 3, dirname(__DIR__) . '/logs/auto_absent.log');
                } else {
                    // Log database insertion failure
                    $error_info = $insert_stmt->errorInfo();
                    error_log("Auto-absent: Failed to insert attendance for student ID {$student['id']} ({$student['full_name']}) for $subject_name. Error: " . implode(' - ', $error_info), 3, dirname(__DIR__) . '/logs/auto_absent_errors.log');
                }
            }
        }
        
        // If student was marked absent for any subjects, send SMS and add to processed list
        if ($student_attendance_count > 0) {
            $marked_count++;
            
            // Send SMS notification to parents about absence
            $current_date = date('F j, Y');
            $section_text = $student['section_name'] ? "{$student['section_name']} (Grade {$student['grade_level']})" : 'Unknown Section';
            
            // Create subject list for SMS
            $subject_names = array_column($student_absent_subjects, 'subject_name');
            $subjects_text = count($subject_names) > 1 ? 
                implode(', ', array_slice($subject_names, 0, -1)) . ' and ' . end($subject_names) :
                $subject_names[0];
            
            $sms_message = "Hi! Your child {$student['full_name']} was marked absent today ($current_date) for $subjects_text as they did not check in by 4:30 PM. Section: $section_text. Please contact the school if this is an error. - KES-SMART";
            
            $sms_result = sendSMSNotificationToParent($student['id'], $sms_message, 'absent');
            
            $student_info = [
                'id' => $student['id'],
                'name' => $student['full_name'],
                'username' => $student['username'],
                'section' => $student['section_name'],
                'grade_level' => $student['grade_level'],
                'absent_subjects' => $student_absent_subjects,
                'subjects_count' => $student_attendance_count,
                'marked_time' => date('H:i:s'),
                'sms_sent' => $sms_result['success'],
                'sms_message' => $sms_result['message'] ?? ''
            ];
            
            if ($sms_result['success']) {
                $sms_sent_count++;
            } else {
                $sms_failed_count++;
                error_log("Auto-absent: SMS failed for student ID {$student['id']}: {$sms_result['message']}", 3, dirname(__DIR__) . '/logs/auto_absent.log');
            }
            
            $processed_students[] = $student_info;
        }
    }
    
    // Log the operation
    $log_message = "Auto-absent API executed on $today at " . date('H:i:s') . ". Marked $marked_count students as absent across $total_attendance_records enrolled subject records. SMS sent: $sms_sent_count, SMS failed: $sms_failed_count.";
    error_log($log_message, 3, dirname(__DIR__) . '/logs/auto_absent.log');
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => "Auto-absent marking completed. $marked_count students marked absent across $total_attendance_records enrolled subject records.",
        'data' => [
            'total_students_marked' => $marked_count,
            'total_attendance_records' => $total_attendance_records,
            'sms_sent' => $sms_sent_count,
            'sms_failed' => $sms_failed_count,
            'processed_students' => $processed_students,
            'date' => $today,
            'time' => date('H:i:s')
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Auto-absent API error: " . $e->getMessage(), 3, dirname(__DIR__) . '/logs/auto_absent_errors.log');
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error occurred during auto-absent processing',
        'error' => $e->getMessage()
    ]);
}
?>