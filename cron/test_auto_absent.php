<?php
/**
 * Test Script for Auto-Absent Functionality
 * 
 * This script allows manual testing of the auto-absent marking system
 * without waiting for 4:15 PM or modifying the time restrictions.
 * 
 * Usage: php test_auto_absent.php
 */

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/sms_functions.php';

echo "=== KES-SMART Auto-Absent Test Script ===\n";
echo "Current time: " . date('Y-m-d H:i:s') . "\n\n";

try {
    $today = date('Y-m-d');
    
    // Allow testing at any time (remove time restriction for testing)
    echo "Testing auto-absent functionality for date: $today\n\n";
    
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
    
    if (empty($absent_students)) {
        echo "No students found without attendance records for today.\n";
        echo "All students have already been marked with attendance.\n";
        exit;
    }
    
    echo "Found " . count($absent_students) . " students without attendance records:\n";
    foreach ($absent_students as $student) {
        echo "- {$student['full_name']} (ID: {$student['username']}) - Section: {$student['section_name']}\n";
    }
    
    echo "\nDo you want to mark these students as absent and send SMS notifications? (y/N): ";
    $confirm = trim(fgets(STDIN));
    
    if (strtolower($confirm) !== 'y' && strtolower($confirm) !== 'yes') {
        echo "Operation cancelled.\n";
        exit;
    }
    
    echo "\nProcessing students...\n\n";
    
    $marked_count = 0;
    $sms_sent_count = 0;
    $sms_failed_count = 0;
    
    foreach ($absent_students as $student) {
        // Create absent attendance record
        $insert_stmt = $pdo->prepare("
            INSERT INTO attendance 
            (student_id, teacher_id, section_id, attendance_date, status, remarks, qr_scanned) 
            VALUES (?, 1, ?, ?, 'absent', 'Auto-marked absent - TEST RUN - no check-in by 4:15 PM', 0)
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
            echo "✓ Marked {$student['full_name']} (ID: {$student['username']}) as absent - SMS sent\n";
        } else {
            $sms_failed_count++;
            echo "✗ Marked {$student['full_name']} (ID: {$student['username']}) as absent - SMS failed: {$sms_result['message']}\n";
        }
    }
    
    echo "\n=== Test Results ===\n";
    echo "Total students marked absent: $marked_count\n";
    echo "SMS notifications sent: $sms_sent_count\n";
    echo "SMS notifications failed: $sms_failed_count\n";
    
    if ($sms_failed_count > 0) {
        echo "\nNote: SMS failures may be due to:\n";
        echo "- No parent phone number on file\n";
        echo "- SMS service configuration issues\n";
        echo "- Invalid phone number format\n";
    }
    
    // Log the test operation
    $log_message = "TEST: Auto-absent script executed on $today at " . date('H:i:s') . ". Marked $marked_count students as absent. SMS sent: $sms_sent_count, SMS failed: $sms_failed_count.";
    error_log($log_message, 3, dirname(__DIR__) . '/logs/auto_absent_test.log');
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    error_log("Auto-absent test script error: " . $e->getMessage(), 3, dirname(__DIR__) . '/logs/auto_absent_test_errors.log');
}

echo "\nTest completed.\n";
?>
