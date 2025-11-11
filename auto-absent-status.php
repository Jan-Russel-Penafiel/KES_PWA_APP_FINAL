<?php
/**
 * Auto-Absent Status Checker
 * 
 * Quick overview of the auto-absent system status
 */

require_once __DIR__ . '/config.php';

// Set timezone
date_default_timezone_set('Asia/Manila');

echo "=== AUTO-ABSENT SYSTEM STATUS ===\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n";
echo "System: KES-SMART Auto-Absent\n\n";

$today = date('Y-m-d');
$current_time = date('H:i:s');
$day_of_week = date('N');

// 1. Time Status
echo "⏰ TIME STATUS:\n";
echo "- Current Date: $today\n";
echo "- Current Time: $current_time\n";
echo "- Day of Week: " . date('l') . " ($day_of_week)\n";
echo "- Is Weekday: " . ($day_of_week <= 5 ? 'YES ✅' : 'NO ❌') . "\n";
echo "- Past 4:30 PM: " . ($current_time >= '16:30:00' ? 'YES ✅' : 'NO ❌') . "\n";

// 2. Check if auto-absent already ran today
echo "\n📊 TODAY'S AUTO-ABSENT STATUS:\n";
$check_auto_absent = $pdo->prepare("
    SELECT COUNT(*) as count, 
           MIN(created_at) as first_run,
           MAX(created_at) as last_run
    FROM attendance 
    WHERE attendance_date = ? 
    AND status = 'absent' 
    AND attendance_source = 'auto'
");
$check_auto_absent->execute([$today]);
$auto_status = $check_auto_absent->fetch(PDO::FETCH_ASSOC);

if ($auto_status['count'] > 0) {
    echo "- Auto-absent already ran: YES ✅\n";
    echo "- Students marked: {$auto_status['count']}\n";
    echo "- First run: {$auto_status['first_run']}\n";
    echo "- Last run: {$auto_status['last_run']}\n";
} else {
    echo "- Auto-absent already ran: NO ❌\n";
    echo "- Students marked: 0\n";
}

// 3. Students without attendance today
echo "\n👥 STUDENTS WITHOUT ATTENDANCE:\n";
$absent_students_query = "
    SELECT COUNT(*) as count
    FROM users u
    LEFT JOIN attendance a ON u.id = a.student_id AND a.attendance_date = ?
    WHERE u.role = 'student' 
    AND u.status = 'active'
    AND a.id IS NULL
";
$stmt = $pdo->prepare($absent_students_query);
$stmt->execute([$today]);
$students_without_attendance = $stmt->fetchColumn();

echo "- Students without attendance: $students_without_attendance\n";

if ($students_without_attendance > 0) {
    // Get names
    $names_query = "
        SELECT u.full_name, u.username, s.section_name, s.grade_level
        FROM users u
        LEFT JOIN sections s ON u.section_id = s.id
        LEFT JOIN attendance a ON u.id = a.student_id AND a.attendance_date = ?
        WHERE u.role = 'student' 
        AND u.status = 'active'
        AND a.id IS NULL
        LIMIT 5
    ";
    $names_stmt = $pdo->prepare($names_query);
    $names_stmt->execute([$today]);
    $student_names = $names_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "- Sample students:\n";
    foreach ($student_names as $student) {
        $section = $student['section_name'] ? "{$student['section_name']} (Grade {$student['grade_level']})" : 'No Section';
        echo "  * {$student['full_name']} ({$student['username']}) - $section\n";
    }
    
    if ($students_without_attendance > 5) {
        echo "  ... and " . ($students_without_attendance - 5) . " more\n";
    }
}

// 4. System readiness
echo "\n🎯 SYSTEM READINESS:\n";

$can_run = true;
$reasons = [];

if ($day_of_week > 5) {
    $can_run = false;
    $reasons[] = "Weekend (auto-absent only runs on weekdays)";
}

if ($current_time < '16:30:00') {
    $time_remaining = strtotime("16:30:00") - strtotime($current_time);
    $hours = floor($time_remaining / 3600);
    $minutes = floor(($time_remaining % 3600) / 60);
    $reasons[] = "Before 4:30 PM (${hours}h ${minutes}m remaining)";
}

if ($auto_status['count'] > 0) {
    $reasons[] = "Auto-absent already processed today";
}

if ($students_without_attendance == 0 && $auto_status['count'] == 0) {
    $reasons[] = "All students already have attendance records";
}

if ($can_run && count($reasons) == 0 && $students_without_attendance > 0) {
    echo "- Status: READY TO RUN ✅\n";
    echo "- Action: Auto-absent will mark $students_without_attendance students as absent\n";
} else {
    echo "- Status: WAITING ⏳\n";
    echo "- Reasons:\n";
    foreach ($reasons as $reason) {
        echo "  * $reason\n";
    }
}

// 5. Recent activity
echo "\n📈 RECENT ATTENDANCE ACTIVITY:\n";
$recent_query = "
    SELECT COUNT(*) as total_today,
           SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_today,
           SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_today,
           SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_today,
           SUM(CASE WHEN attendance_source = 'qr_scan' THEN 1 ELSE 0 END) as qr_scans_today,
           SUM(CASE WHEN attendance_source = 'manual' THEN 1 ELSE 0 END) as manual_today,
           SUM(CASE WHEN attendance_source = 'auto' THEN 1 ELSE 0 END) as auto_today
    FROM attendance 
    WHERE attendance_date = ?
";
$recent_stmt = $pdo->prepare($recent_query);
$recent_stmt->execute([$today]);
$recent_stats = $recent_stmt->fetch(PDO::FETCH_ASSOC);

echo "- Total attendance records today: {$recent_stats['total_today']}\n";
echo "- Present: {$recent_stats['present_today']}\n";
echo "- Absent: {$recent_stats['absent_today']}\n";
echo "- Late: {$recent_stats['late_today']}\n";
echo "- QR Scans: {$recent_stats['qr_scans_today']}\n";
echo "- Manual entries: {$recent_stats['manual_today']}\n";
echo "- Auto-marked: {$recent_stats['auto_today']}\n";

echo "\n" . str_repeat("=", 60) . "\n";
echo "💡 TIP: Use 'php simulate-auto-absent.php' to test the auto-absent process immediately\n";
echo "🔧 TIP: Use 'php manual-auto-absent.php' for web-based manual triggering\n";

?>