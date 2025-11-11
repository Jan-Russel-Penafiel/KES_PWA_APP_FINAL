<?php
/**
 * Auto-Absent Simulation Test
 * 
 * This script simulates the auto-absent process for immediate testing
 * by bypassing time restrictions
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/sms_functions.php';

// Set timezone to Philippines
date_default_timezone_set('Asia/Manila');

echo "=== AUTO-ABSENT SIMULATION TEST ===\n";
echo "This test simulates what would happen at 4:30 PM\n";
echo "Current Date: " . date('Y-m-d') . "\n";
echo "Current Time: " . date('H:i:s') . "\n";
echo "Simulating: 4:30 PM (16:30:00)\n\n";

try {
    $today = date('Y-m-d');
    $simulated_time = '16:30:00';
    $day_of_week = date('N');
    
    echo "🎯 SIMULATION CONDITIONS:\n";
    echo "- Date: $today\n";
    echo "- Simulated Time: $simulated_time\n";
    echo "- Day of Week: " . date('l') . " ($day_of_week)\n";
    echo "- Is Weekday: " . ($day_of_week <= 5 ? 'YES' : 'NO') . "\n";
    echo "- Past 4:30 PM: YES (simulated)\n\n";
    
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
        echo "⚠️  Auto-absent has already been processed today. Found $auto_absent_count auto-marked absent records.\n";
        echo "To test again, you would need to:\n";
        echo "1. Delete today's auto-absent records, OR\n";
        echo "2. Test with a different date\n\n";
        
        // Show existing auto-absent records
        echo "🔍 EXISTING AUTO-ABSENT RECORDS TODAY:\n";
        $existing_records = $pdo->prepare("
            SELECT a.*, u.full_name, u.username 
            FROM attendance a 
            JOIN users u ON a.student_id = u.id 
            WHERE a.attendance_date = ? 
            AND a.status = 'absent' 
            AND (a.remarks LIKE '%Auto-marked absent%' OR a.remarks LIKE '%auto-marked absent%')
        ");
        $existing_records->execute([$today]);
        $records = $existing_records->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($records as $record) {
            echo "- {$record['full_name']} ({$record['username']}) - {$record['remarks']}\n";
        }
        
        echo "\n❓ Would you like to continue with simulation anyway? (y/N): ";
        $handle = fopen("php://stdin", "r");
        $input = trim(fgets($handle));
        fclose($handle);
        
        if (strtolower($input) !== 'y' && strtolower($input) !== 'yes') {
            echo "Simulation cancelled.\n";
            exit;
        }
        
        echo "\n📋 Continuing with simulation...\n\n";
    }
    
    // Find students without attendance today
    $absent_students_query = "
        SELECT u.id, u.username, u.full_name, u.section_id, s.section_name, s.grade_level
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
    
    echo "🎯 STUDENTS TO BE MARKED ABSENT:\n";
    echo "Found " . count($absent_students) . " students without attendance records:\n";
    
    if (count($absent_students) == 0) {
        echo "✅ All students have attendance records for today!\n";
        echo "Auto-absent would have nothing to process.\n";
        exit;
    }
    
    foreach ($absent_students as $student) {
        $section_info = $student['section_name'] ? "{$student['section_name']} (Grade {$student['grade_level']})" : 'No Section';
        echo "- {$student['full_name']} ({$student['username']}) from {$section_info}\n";
    }
    
    echo "\n❓ Execute the auto-absent simulation? This will actually mark these students as absent. (y/N): ";
    $handle = fopen("php://stdin", "r");
    $input = trim(fgets($handle));
    fclose($handle);
    
    if (strtolower($input) !== 'y' && strtolower($input) !== 'yes') {
        echo "Simulation cancelled. No changes made.\n";
        exit;
    }
    
    echo "\n🚀 EXECUTING AUTO-ABSENT SIMULATION...\n";
    echo str_repeat("=", 60) . "\n";
    
    $marked_count = 0;
    $sms_sent_count = 0;
    $sms_failed_count = 0;
    $processed_students = [];
    
    foreach ($absent_students as $student) {
        echo "\n👤 Processing: {$student['full_name']} ({$student['username']})\n";
        
        // Ensure we have a valid section_id
        $section_id = $student['section_id'] ? $student['section_id'] : 1;
        
        // Create absent attendance record
        $insert_stmt = $pdo->prepare("
            INSERT INTO attendance 
            (student_id, teacher_id, section_id, subject_id, attendance_date, time_in, time_out, status, remarks, qr_scanned, attendance_source, created_at) 
            VALUES (?, ?, ?, NULL, ?, NULL, NULL, 'absent', ?, 0, 'auto', NOW())
        ");
        
        $remarks = "SIMULATION: Auto-marked absent - no check-in by 4:30 PM (Simulated at " . date('Y-m-d H:i:s') . ")";
        
        $insert_result = $insert_stmt->execute([
            $student['id'],
            1,
            $section_id,
            $today,
            $remarks
        ]);
        
        if ($insert_result) {
            $attendance_id = $pdo->lastInsertId();
            $marked_count++;
            echo "   ✅ Marked as absent (Attendance ID: $attendance_id)\n";
            
            // Send SMS notification
            $current_date = date('F j, Y');
            $section_text = $student['section_name'] ? "{$student['section_name']} (Grade {$student['grade_level']})" : 'Unknown Section';
            $sms_message = "Hi! Your child {$student['full_name']} was marked absent today ($current_date) as they did not check in by 4:30 PM. Section: $section_text. Please contact the school if this is an error. - KES-SMART";
            
            echo "   📱 Sending SMS to parents...\n";
            $sms_result = sendSMSNotificationToParent($student['id'], $sms_message, 'absent');
            
            if ($sms_result['success']) {
                $sms_sent_count++;
                echo "   ✅ SMS sent successfully\n";
            } else {
                $sms_failed_count++;
                echo "   ❌ SMS failed: {$sms_result['message']}\n";
            }
            
            $processed_students[] = [
                'id' => $student['id'],
                'name' => $student['full_name'],
                'username' => $student['username'],
                'section' => $student['section_name'],
                'attendance_id' => $attendance_id,
                'sms_sent' => $sms_result['success']
            ];
            
        } else {
            $error_info = $insert_stmt->errorInfo();
            echo "   ❌ Failed to mark absent: " . implode(' - ', $error_info) . "\n";
        }
    }
    
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "🎯 SIMULATION COMPLETE!\n\n";
    
    echo "📊 RESULTS SUMMARY:\n";
    echo "- Total students processed: " . count($absent_students) . "\n";
    echo "- Successfully marked absent: $marked_count\n";
    echo "- SMS notifications sent: $sms_sent_count\n";
    echo "- SMS notifications failed: $sms_failed_count\n";
    echo "- Simulation date/time: " . date('Y-m-d H:i:s') . "\n";
    
    if (count($processed_students) > 0) {
        echo "\n📋 DETAILED RESULTS:\n";
        foreach ($processed_students as $student) {
            echo "- {$student['name']} ({$student['username']}): ";
            echo "Attendance ID {$student['attendance_id']}, ";
            echo "SMS " . ($student['sms_sent'] ? 'SENT' : 'FAILED') . "\n";
        }
    }
    
    echo "\n✅ You can now check the attendance table to see the new records.\n";
    echo "💡 To verify: SELECT * FROM attendance WHERE attendance_date = '$today' AND status = 'absent';\n";
    
} catch (Exception $e) {
    echo "❌ Error during simulation: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
?>