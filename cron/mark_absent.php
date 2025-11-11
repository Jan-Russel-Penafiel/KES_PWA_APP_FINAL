<?php
/**
 * Automatic Absent Marking Script
 * 
 * This script should be run daily at 4:31 PM to automatically mark students
 * as absent if they haven't checked in by 4:30 PM.
 * 
 * Add to crontab:
 * 31 16 * * 1-5 /usr/bin/php /path/to/smart/cron/mark_absent.php
 * 
 * Or for Windows Task Scheduler:
 * Run daily at 4:31 PM on weekdays only
 */

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/sms_functions.php';

try {
    $today = date('Y-m-d');
    $current_time = date('H:i:s');
    $day_of_week = date('N'); // 1 (Monday) to 7 (Sunday)
    
    // Only run on weekdays (Monday to Friday)
    if ($day_of_week > 5) {
        echo "Script only runs on weekdays (Monday-Friday)\n";
        exit;
    }
    
    // Only run this script after 4:30 PM
    if ($current_time < '16:30:00') {
        echo "Script can only run after 4:30 PM\n";
        exit;
    }
    
    // Check if auto-absent has already been run today
    $check_auto_absent = $pdo->prepare("
        SELECT COUNT(*) as auto_absent_count 
        FROM attendance 
        WHERE attendance_date = ? 
        AND status = 'absent' 
        AND remarks LIKE '%Auto-marked absent%'
    ");
    $check_auto_absent->execute([$today]);
    $auto_absent_count = $check_auto_absent->fetchColumn();
    
    if ($auto_absent_count > 0) {
        echo "Auto-absent marking has already been run today. Found $auto_absent_count auto-marked absent records.\n";
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
    
    $marked_count = 0;
    $sms_sent_count = 0;
    $sms_failed_count = 0;
    $total_attendance_records = 0;
    
    foreach ($all_students as $student) {
        // Ensure we have a valid section_id, default to 1 if null
        $section_id = $student['section_id'] ? $student['section_id'] : 1;
        
        // Get all active subjects for this student's section/grade
        $subjects_query = "
            SELECT s.id, s.subject_name, s.teacher_id
            FROM subjects s
            WHERE s.status = 'active'
            AND (s.section_id = ? OR s.section_id IS NULL)
            ORDER BY s.subject_name
        ";
        
        $subjects_stmt = $pdo->prepare($subjects_query);
        $subjects_stmt->execute([$section_id]);
        $subjects = $subjects_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // If no subjects found, create a general attendance record
        if (empty($subjects)) {
            $subjects = [['id' => null, 'subject_name' => 'General', 'teacher_id' => 1]];
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
                $remarks = "Auto-marked absent for $subject_name - no check-in by 4:30 PM (Cron job executed at " . date('Y-m-d H:i:s') . ")";
                
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
                    
                    $student_absent_subjects[] = $subject_name;
                    
                    echo "Successfully marked {$student['full_name']} (ID: {$student['username']}) as absent for $subject_name. Attendance ID: $attendance_id\n";
                } else {
                    $error_info = $insert_stmt->errorInfo();
                    echo "Failed to mark {$student['full_name']} (ID: {$student['username']}) as absent for $subject_name. Error: " . implode(' - ', $error_info) . "\n";
                }
            }
        }
        
        // If student was marked absent for any subjects, send SMS
        if ($student_attendance_count > 0) {
            $marked_count++;
            
            // Send SMS notification to parents about absence
            $current_date = date('F j, Y');
            $section_text = $student['section_name'] ? "{$student['section_name']} (Grade {$student['grade_level']})" : 'Unknown Section';
            
            // Create subject list for SMS
            $subjects_text = count($student_absent_subjects) > 1 ? 
                implode(', ', array_slice($student_absent_subjects, 0, -1)) . ' and ' . end($student_absent_subjects) :
                $student_absent_subjects[0];
            
            $sms_message = "Hi! Your child {$student['full_name']} was marked absent today ($current_date) for $subjects_text as they did not check in by 4:30 PM. Section: $section_text. Please contact the school if this is an error. - KES-SMART";
            
            $sms_result = sendSMSNotificationToParent($student['id'], $sms_message, 'absent');
            
            if ($sms_result['success']) {
                $sms_sent_count++;
                echo "SMS sent to parents of {$student['full_name']} (ID: {$student['username']})\n";
            } else {
                $sms_failed_count++;
                echo "SMS failed for {$student['full_name']} (ID: {$student['username']}): {$sms_result['message']}\n";
            }
        }
    }
    
    echo "Auto-absent marking completed.\n";
    echo "Total students marked absent: $marked_count\n";
    echo "Total attendance records created: $total_attendance_records\n";
    echo "SMS notifications sent: $sms_sent_count\n";
    echo "SMS notifications failed: $sms_failed_count\n";
    
    // Log the operation
    $log_message = "Auto-absent script executed on $today at " . date('H:i:s') . ". Marked $marked_count students as absent across $total_attendance_records subjects. SMS sent: $sms_sent_count, SMS failed: $sms_failed_count.";
    error_log($log_message, 3, dirname(__DIR__) . '/logs/auto_absent.log');
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    error_log("Auto-absent script error: " . $e->getMessage(), 3, dirname(__DIR__) . '/logs/auto_absent_errors.log');
}
?>
