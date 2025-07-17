<?php
/**
 * Automatic Absent Marking Script
 * 
 * This script should be run daily at 4:16 PM to automatically mark students
 * as absent if they haven't checked in by 4:15 PM.
 * 
 * Add to crontab:
 * 16 16 * * 1-5 /usr/bin/php /path/to/smart/cron/mark_absent.php
 * 
 * Or for Windows Task Scheduler:
 * Run daily at 4:16 PM on weekdays only
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
    
    // Only run this script after 4:15 PM
    if ($current_time < '16:15:00') {
        echo "Script can only run after 4:15 PM\n";
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
    
    // Find all students who don't have attendance records for today
    $absent_students_query = "
        SELECT u.id, u.username, u.full_name, u.section_id, s.section_name
        FROM users u
        LEFT JOIN sections s ON u.section_id = s.id
        LEFT JOIN attendance a ON u.id = a.student_id AND a.attendance_date = ?
        WHERE u.role = 'student' 
        AND u.status = 'active'
        AND a.id IS NULL
    ";
    
    $stmt = $pdo->prepare($absent_students_query);
    $stmt->execute([$today]);
    $absent_students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $marked_count = 0;
    $sms_sent_count = 0;
    $sms_failed_count = 0;
    
    foreach ($absent_students as $student) {
        // Create absent attendance record
        $insert_stmt = $pdo->prepare("
            INSERT INTO attendance 
            (student_id, teacher_id, section_id, attendance_date, status, remarks, qr_scanned) 
            VALUES (?, 1, ?, ?, 'absent', 'Auto-marked absent - no check-in by 4:15 PM', 0)
        ");
        
        $insert_stmt->execute([
            $student['id'],
            $student['section_id'],
            $today
        ]);
        
        $marked_count++;
        
        // Send SMS notification to parents about absence
        $current_date = date('F j, Y');
        $sms_message = "Hi! Your child {$student['full_name']} was marked absent today ($current_date) as they did not check in by 4:15 PM. Section: {$student['section_name']}. Please contact the school if this is an error. - KES-SMART";
        
        $sms_result = sendSMSNotificationToParent($student['id'], $sms_message, 'absent');
        
        if ($sms_result['success']) {
            $sms_sent_count++;
            echo "Marked {$student['full_name']} (ID: {$student['username']}) as absent - SMS sent\n";
        } else {
            $sms_failed_count++;
            echo "Marked {$student['full_name']} (ID: {$student['username']}) as absent - SMS failed: {$sms_result['message']}\n";
        }
    }
    
    echo "Auto-absent marking completed.\n";
    echo "Total students marked absent: $marked_count\n";
    echo "SMS notifications sent: $sms_sent_count\n";
    echo "SMS notifications failed: $sms_failed_count\n";
    
    // Log the operation
    $log_message = "Auto-absent script executed on $today at " . date('H:i:s') . ". Marked $marked_count students as absent. SMS sent: $sms_sent_count, SMS failed: $sms_failed_count.";
    error_log($log_message, 3, dirname(__DIR__) . '/logs/auto_absent.log');
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    error_log("Auto-absent script error: " . $e->getMessage(), 3, dirname(__DIR__) . '/logs/auto_absent_errors.log');
}
?>
