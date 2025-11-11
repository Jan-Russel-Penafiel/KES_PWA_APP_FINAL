<?php
/**
 * Manual Auto-Absent Trigger
 * 
 * This script allows manual triggering of auto-absent functionality
 * for testing purposes, regardless of time restrictions
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/sms_functions.php';

// Set timezone to Philippines
date_default_timezone_set('Asia/Manila');

// Check if this is a POST request with confirmation
$confirmed = isset($_POST['confirm']) && $_POST['confirm'] === 'yes';

if (!$confirmed) {
    // Show confirmation form
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Manual Auto-Absent Trigger</title>
        <style>
            body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
            .warning { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 20px 0; }
            .info { background: #d1ecf1; border: 1px solid #bee5eb; padding: 15px; border-radius: 5px; margin: 20px 0; }
            button { background: #dc3545; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; }
            button:hover { background: #c82333; }
            .cancel { background: #6c757d; margin-left: 10px; }
            .cancel:hover { background: #5a6268; }
        </style>
    </head>
    <body>
        <h1>Manual Auto-Absent Trigger</h1>
        
        <div class="warning">
            <strong>⚠️ Warning:</strong> This will manually trigger the auto-absent marking process, 
            regardless of the current time. This should only be used for testing purposes.
        </div>
        
        <div class="info">
            <strong>Current Status:</strong><br>
            Date: <?php echo date('Y-m-d'); ?><br>
            Time: <?php echo date('H:i:s'); ?><br>
            Day: <?php echo date('l'); ?><br>
        </div>
        
        <?php
        try {
            // Show students that would be affected
            $today = date('Y-m-d');
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
            
            echo "<div class='info'>";
            echo "<strong>Students that would be marked absent:</strong><br>";
            if (count($absent_students) > 0) {
                echo "Found " . count($absent_students) . " students without attendance today:<br><br>";
                foreach ($absent_students as $student) {
                    $section_info = $student['section_name'] ? "{$student['section_name']} (Grade {$student['grade_level']})" : 'No Section';
                    echo "• {$student['full_name']} ({$student['username']}) - {$section_info}<br>";
                }
            } else {
                echo "No students found without attendance records today.";
            }
            echo "</div>";
        } catch (Exception $e) {
            echo "<div class='warning'>Error checking students: " . $e->getMessage() . "</div>";
        }
        ?>
        
        <form method="POST" onsubmit="return confirm('Are you sure you want to trigger auto-absent marking? This cannot be undone.');">
            <input type="hidden" name="confirm" value="yes">
            <button type="submit">🚨 Trigger Auto-Absent</button>
            <button type="button" class="cancel" onclick="window.location.href='dashboard.php'">Cancel</button>
        </form>
        
    </body>
    </html>
    <?php
    exit;
}

// Execute auto-absent marking
try {
    echo "<h1>Auto-Absent Execution Results</h1>";
    echo "<p>Started at: " . date('Y-m-d H:i:s') . "</p>";
    
    $today = date('Y-m-d');
    
    // Find all students who don't have attendance records for today
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
    
    $marked_count = 0;
    $sms_sent_count = 0;
    $sms_failed_count = 0;
    
    echo "<h2>Processing " . count($absent_students) . " students...</h2>";
    
    foreach ($absent_students as $student) {
        echo "<div style='border: 1px solid #ddd; padding: 10px; margin: 10px 0;'>";
        echo "<strong>{$student['full_name']} ({$student['username']})</strong><br>";
        
        // Ensure we have a valid section_id
        $section_id = $student['section_id'] ? $student['section_id'] : 1;
        
        // Create absent attendance record
        $insert_stmt = $pdo->prepare("
            INSERT INTO attendance 
            (student_id, teacher_id, section_id, subject_id, attendance_date, time_in, time_out, status, remarks, qr_scanned, attendance_source, created_at) 
            VALUES (?, ?, ?, NULL, ?, NULL, NULL, 'absent', ?, 0, 'auto', NOW())
        ");
        
        $remarks = "MANUAL TRIGGER: Auto-marked absent - no check-in by 4:30 PM (Triggered at " . date('Y-m-d H:i:s') . ")";
        
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
            echo "✅ Successfully marked as absent (Attendance ID: $attendance_id)<br>";
            
            // Send SMS notification
            $current_date = date('F j, Y');
            $section_text = $student['section_name'] ? "{$student['section_name']} (Grade {$student['grade_level']})" : 'Unknown Section';
            $sms_message = "Hi! Your child {$student['full_name']} was marked absent today ($current_date) as they did not check in by 4:30 PM. Section: $section_text. Please contact the school if this is an error. - KES-SMART";
            
            $sms_result = sendSMSNotificationToParent($student['id'], $sms_message, 'absent');
            
            if ($sms_result['success']) {
                $sms_sent_count++;
                echo "📱 SMS sent successfully<br>";
            } else {
                $sms_failed_count++;
                echo "❌ SMS failed: {$sms_result['message']}<br>";
            }
        } else {
            $error_info = $insert_stmt->errorInfo();
            echo "❌ Failed to mark absent: " . implode(' - ', $error_info) . "<br>";
        }
        
        echo "</div>";
    }
    
    echo "<h2>Summary</h2>";
    echo "<ul>";
    echo "<li>Total students marked absent: <strong>$marked_count</strong></li>";
    echo "<li>SMS notifications sent: <strong>$sms_sent_count</strong></li>";
    echo "<li>SMS notifications failed: <strong>$sms_failed_count</strong></li>";
    echo "</ul>";
    
    echo "<p><a href='dashboard.php'>← Back to Dashboard</a></p>";
    
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; border-radius: 5px;'>";
    echo "<strong>Error:</strong> " . $e->getMessage();
    echo "</div>";
}
?>