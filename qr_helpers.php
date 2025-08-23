<?php
/**
 * QR Code Helper Functions for KES-SMART
 * Contains functions for generating and validating QR codes
 */

/**
 * Generate a teacher QR code for attendance sessions
 * @param int $teacher_id Teacher's user ID
 * @param int $section_id Section ID
 * @param int $subject_id Subject ID
 * @return string Base64 encoded QR data
 */
function generateTeacherQR($teacher_id, $section_id, $subject_id) {
    $timestamp = time();
    $qr_string = "KES-SMART-TEACHER-{$teacher_id}-{$section_id}-{$subject_id}-{$timestamp}";
    return base64_encode($qr_string);
}

/**
 * Validate and parse teacher QR code
 * @param string $qr_data Base64 encoded QR data
 * @return array|false Array with teacher_id, section_id, subject_id, timestamp or false if invalid
 */
function validateTeacherQR($qr_data) {
    try {
        $decoded = base64_decode($qr_data);
        
        if (preg_match('/^KES-SMART-TEACHER-(\d+)-(\d+)-(\d+)-(\d+)$/', $decoded, $matches)) {
            return [
                'teacher_id' => intval($matches[1]),
                'section_id' => intval($matches[2]),
                'subject_id' => intval($matches[3]),
                'timestamp' => intval($matches[4])
            ];
        }
        
        // Support older format without timestamp
        if (preg_match('/^KES-SMART-TEACHER-(\d+)-(\d+)-(\d+)$/', $decoded, $matches)) {
            return [
                'teacher_id' => intval($matches[1]),
                'section_id' => intval($matches[2]),
                'subject_id' => intval($matches[3]),
                'timestamp' => time()
            ];
        }
        
        return false;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Validate and parse student QR code
 * @param string $qr_data Base64 encoded QR data
 * @return array|false Array with student_id, year or false if invalid
 */
function validateStudentQR($qr_data) {
    try {
        $decoded = base64_decode($qr_data);
        
        if (preg_match('/^KES-SMART-STUDENT-(\d+)-(\d+)$/', $decoded, $matches)) {
            return [
                'student_id' => intval($matches[1]),
                'year' => intval($matches[2])
            ];
        }
        
        return false;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Check if QR code is expired
 * @param int $timestamp QR code timestamp
 * @param int $validity_hours Validity period in hours (default 24)
 * @return bool True if expired, false if still valid
 */
function isQRExpired($timestamp, $validity_hours = 24) {
    $expiry_time = $timestamp + ($validity_hours * 3600);
    return time() > $expiry_time;
}

/**
 * Create or update teacher QR session
 * @param PDO $pdo Database connection
 * @param int $teacher_id Teacher's user ID
 * @param int $section_id Section ID
 * @param int $subject_id Subject ID
 * @return string|false QR code data or false on failure
 */
function createTeacherQRSession($pdo, $teacher_id, $section_id, $subject_id) {
    try {
        $qr_code = generateTeacherQR($teacher_id, $section_id, $subject_id);
        $session_date = date('Y-m-d');
        
        // Check if session already exists for today
        $check_stmt = $pdo->prepare("
            SELECT id, qr_code FROM teacher_qr_sessions 
            WHERE teacher_id = ? AND section_id = ? AND subject_id = ? AND session_date = ?
        ");
        $check_stmt->execute([$teacher_id, $section_id, $subject_id, $session_date]);
        $existing = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing) {
            // Update existing session
            $update_stmt = $pdo->prepare("
                UPDATE teacher_qr_sessions 
                SET qr_code = ?, expires_at = (NOW() + INTERVAL 24 HOUR), status = 'active'
                WHERE id = ?
            ");
            $update_stmt->execute([$qr_code, $existing['id']]);
        } else {
            // Create new session
            $insert_stmt = $pdo->prepare("
                INSERT INTO teacher_qr_sessions (teacher_id, section_id, subject_id, qr_code, session_date) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $insert_stmt->execute([$teacher_id, $section_id, $subject_id, $qr_code, $session_date]);
        }
        
        return $qr_code;
    } catch (PDOException $e) {
        error_log("Error creating teacher QR session: " . $e->getMessage());
        return false;
    }
}

/**
 * Validate attendance QR scan
 * @param PDO $pdo Database connection
 * @param string $teacher_qr_data Teacher's QR code data
 * @param string $student_qr_data Student's QR code data
 * @return array Result with success status and data
 */
function validateAttendanceQRScan($pdo, $teacher_qr_data, $student_qr_data) {
    try {
        // Validate teacher QR
        $teacher_qr = validateTeacherQR($teacher_qr_data);
        if (!$teacher_qr) {
            return ['success' => false, 'message' => 'Invalid teacher QR code'];
        }
        
        // Validate student QR
        $student_qr = validateStudentQR($student_qr_data);
        if (!$student_qr) {
            return ['success' => false, 'message' => 'Invalid student QR code'];
        }
        
        // Check if QR is not too old (24 hours by default)
        if (isset($teacher_qr['timestamp']) && isQRExpired($teacher_qr['timestamp'])) {
            return ['success' => false, 'message' => 'QR code has expired. Please ask teacher to generate a new one.'];
        }
        
        // Verify teacher exists and is active
        $teacher_stmt = $pdo->prepare("SELECT id, full_name FROM users WHERE id = ? AND role = 'teacher' AND status = 'active'");
        $teacher_stmt->execute([$teacher_qr['teacher_id']]);
        $teacher = $teacher_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$teacher) {
            return ['success' => false, 'message' => 'Teacher not found or inactive'];
        }
        
        // Verify student exists and is active
        $student_stmt = $pdo->prepare("SELECT id, full_name, section_id FROM users WHERE id = ? AND role = 'student' AND status = 'active'");
        $student_stmt->execute([$student_qr['student_id']]);
        $student = $student_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$student) {
            return ['success' => false, 'message' => 'Student not found or inactive'];
        }
        
        // Verify section and subject exist
        $section_stmt = $pdo->prepare("SELECT id, section_name FROM sections WHERE id = ? AND status = 'active'");
        $section_stmt->execute([$teacher_qr['section_id']]);
        $section = $section_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$section) {
            return ['success' => false, 'message' => 'Section not found or inactive'];
        }
        
        $subject_stmt = $pdo->prepare("SELECT id, subject_name FROM subjects WHERE id = ? AND status = 'active'");
        $subject_stmt->execute([$teacher_qr['subject_id']]);
        $subject = $subject_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$subject) {
            return ['success' => false, 'message' => 'Subject not found or inactive'];
        }
        
        return [
            'success' => true,
            'teacher' => $teacher,
            'student' => $student,
            'section' => $section,
            'subject' => $subject,
            'teacher_qr' => $teacher_qr,
            'student_qr' => $student_qr
        ];
        
    } catch (Exception $e) {
        error_log("Error validating attendance QR scan: " . $e->getMessage());
        return ['success' => false, 'message' => 'System error during validation'];
    }
}

/**
 * Check if student can scan for attendance (cooldown period)
 * @param PDO $pdo Database connection
 * @param int $student_id Student ID
 * @param int $subject_id Subject ID
 * @param int $cooldown_minutes Cooldown period in minutes (default 5)
 * @return bool True if can scan, false if in cooldown
 */
function canStudentScan($pdo, $student_id, $subject_id, $cooldown_minutes = 5) {
    try {
        $cooldown_time = date('Y-m-d H:i:s', strtotime("-{$cooldown_minutes} minutes"));
        
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM qr_scans 
            WHERE student_id = ? AND scan_time > ? 
            AND device_info LIKE '%subject_{$subject_id}%'
        ");
        $stmt->execute([$student_id, $cooldown_time]);
        
        return $stmt->fetchColumn() == 0;
    } catch (PDOException $e) {
        error_log("Error checking scan cooldown: " . $e->getMessage());
        return true; // Allow scan if there's an error
    }
}

/**
 * Log QR scan attempt
 * @param PDO $pdo Database connection
 * @param int $student_id Student ID
 * @param int $teacher_id Teacher ID
 * @param string $qr_type Type of QR scan
 * @param string $scan_result Result of scan
 * @param string $location Scan location
 * @param string $device_info Device information
 * @return bool Success status
 */
function logQRScan($pdo, $student_id, $teacher_id, $qr_type = 'attendance', $scan_result = 'success', $location = null, $device_info = null) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO qr_scans (student_id, teacher_id, qr_type, scan_result, location, device_info) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        return $stmt->execute([$student_id, $teacher_id, $qr_type, $scan_result, $location, $device_info]);
    } catch (PDOException $e) {
        error_log("Error logging QR scan: " . $e->getMessage());
        return false;
    }
}

/**
 * Generate QR code image URL using external service
 * @param string $qr_data QR code data
 * @param int $size Image size in pixels
 * @return string QR code image URL
 */
function generateQRImageURL($qr_data, $size = 250) {
    return "https://api.qrserver.com/v1/create-qr-code/?size={$size}x{$size}&data=" . urlencode($qr_data) . "&format=png&margin=10";
}

/**
 * Get QR scanner statistics for teacher dashboard
 * @param PDO $pdo Database connection
 * @param int $teacher_id Teacher ID
 * @return array Statistics data
 */
function getQRScanStats($pdo, $teacher_id) {
    try {
        $today = date('Y-m-d');
        
        // Today's scans
        $today_stmt = $pdo->prepare("
            SELECT COUNT(*) FROM qr_scans 
            WHERE teacher_id = ? AND DATE(scan_time) = ?
        ");
        $today_stmt->execute([$teacher_id, $today]);
        $today_scans = $today_stmt->fetchColumn();
        
        // This week's scans
        $week_start = date('Y-m-d', strtotime('monday this week'));
        $week_stmt = $pdo->prepare("
            SELECT COUNT(*) FROM qr_scans 
            WHERE teacher_id = ? AND DATE(scan_time) >= ?
        ");
        $week_stmt->execute([$teacher_id, $week_start]);
        $week_scans = $week_stmt->fetchColumn();
        
        // Active sessions
        $sessions_stmt = $pdo->prepare("
            SELECT COUNT(*) FROM teacher_qr_sessions 
            WHERE teacher_id = ? AND status = 'active' AND expires_at > NOW()
        ");
        $sessions_stmt->execute([$teacher_id]);
        $active_sessions = $sessions_stmt->fetchColumn();
        
        return [
            'today_scans' => $today_scans,
            'week_scans' => $week_scans,
            'active_sessions' => $active_sessions
        ];
        
    } catch (PDOException $e) {
        error_log("Error getting QR scan stats: " . $e->getMessage());
        return [
            'today_scans' => 0,
            'week_scans' => 0,
            'active_sessions' => 0
        ];
    }
}
?>
