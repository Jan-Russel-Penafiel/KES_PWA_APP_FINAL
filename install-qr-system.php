<?php
/**
 * Installation script for QR Attendance System
 * Run this script to set up the new QR code attendance functionality
 */

require_once 'config.php';

$installation_steps = [];
$errors = [];

try {
    // Step 1: Check database connection
    if ($GLOBALS['is_offline_mode']) {
        throw new Exception("Database connection required for installation");
    }
    
    $installation_steps[] = "✓ Database connection verified";
    
    // Step 2: Create teacher_qr_sessions table
    $create_sessions_table = "
        CREATE TABLE IF NOT EXISTS `teacher_qr_sessions` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `teacher_id` int(11) NOT NULL,
          `section_id` int(11) NOT NULL,
          `subject_id` int(11) NOT NULL,
          `qr_code` text NOT NULL,
          `session_date` date NOT NULL,
          `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
          `expires_at` timestamp NOT NULL DEFAULT (current_timestamp() + INTERVAL 24 HOUR),
          `status` enum('active','expired','closed') DEFAULT 'active',
          PRIMARY KEY (`id`),
          UNIQUE KEY `unique_session` (`teacher_id`, `section_id`, `subject_id`, `session_date`),
          KEY `teacher_id` (`teacher_id`),
          KEY `section_id` (`section_id`),
          KEY `subject_id` (`subject_id`),
          CONSTRAINT `teacher_qr_sessions_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`),
          CONSTRAINT `teacher_qr_sessions_ibfk_2` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`),
          CONSTRAINT `teacher_qr_sessions_ibfk_3` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ";
    
    $pdo->exec($create_sessions_table);
    $installation_steps[] = "✓ Teacher QR sessions table created";
    
    // Step 3: Update attendance table
    try {
        $pdo->exec("ALTER TABLE `attendance` ADD COLUMN `attendance_source` enum('manual','qr_scan','auto') DEFAULT 'manual' AFTER `qr_scanned`");
        $installation_steps[] = "✓ Added attendance_source column to attendance table";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            $installation_steps[] = "✓ attendance_source column already exists";
        } else {
            throw $e;
        }
    }
    
    try {
        $pdo->exec("ALTER TABLE `attendance` ADD COLUMN `scan_location` varchar(255) DEFAULT NULL AFTER `attendance_source`");
        $installation_steps[] = "✓ Added scan_location column to attendance table";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            $installation_steps[] = "✓ scan_location column already exists";
        } else {
            throw $e;
        }
    }
    
    // Step 4: Update qr_scans table
    try {
        $pdo->exec("ALTER TABLE `qr_scans` ADD COLUMN `qr_type` enum('student','teacher','attendance') DEFAULT 'attendance' AFTER `teacher_id`");
        $installation_steps[] = "✓ Added qr_type column to qr_scans table";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            $installation_steps[] = "✓ qr_type column already exists";
        } else {
            throw $e;
        }
    }
    
    try {
        $pdo->exec("ALTER TABLE `qr_scans` ADD COLUMN `scan_result` enum('success','failed','duplicate') DEFAULT 'success' AFTER `qr_type`");
        $installation_steps[] = "✓ Added scan_result column to qr_scans table";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            $installation_steps[] = "✓ scan_result column already exists";
        } else {
            throw $e;
        }
    }
    
    try {
        $pdo->exec("ALTER TABLE `qr_scans` ADD COLUMN `session_id` int(11) DEFAULT NULL AFTER `scan_result`");
        $installation_steps[] = "✓ Added session_id column to qr_scans table";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            $installation_steps[] = "✓ session_id column already exists";
        } else {
            throw $e;
        }
    }
    
    // Step 5: Add indexes for better performance
    try {
        $pdo->exec("ALTER TABLE `attendance` ADD INDEX `idx_attendance_date_status` (`attendance_date`, `status`)");
        $installation_steps[] = "✓ Added attendance date/status index";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate key') !== false) {
            $installation_steps[] = "✓ Attendance date/status index already exists";
        } else {
            throw $e;
        }
    }
    
    try {
        $pdo->exec("ALTER TABLE `attendance` ADD INDEX `idx_attendance_source` (`attendance_source`)");
        $installation_steps[] = "✓ Added attendance source index";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate key') !== false) {
            $installation_steps[] = "✓ Attendance source index already exists";
        } else {
            throw $e;
        }
    }
    
    // Step 6: Create attendance summary view
    $create_view = "
        CREATE OR REPLACE VIEW `attendance_summary` AS
        SELECT 
            a.id,
            a.student_id,
            s.username as student_username,
            s.full_name as student_name,
            s.lrn,
            a.teacher_id,
            t.full_name as teacher_name,
            a.section_id,
            sec.section_name,
            sec.grade_level,
            a.subject_id,
            subj.subject_name,
            subj.subject_code,
            a.attendance_date,
            a.time_in,
            a.time_out,
            a.status,
            a.remarks,
            a.qr_scanned,
            a.attendance_source,
            a.scan_location,
            a.created_at
        FROM attendance a
        JOIN users s ON a.student_id = s.id
        JOIN users t ON a.teacher_id = t.id
        LEFT JOIN sections sec ON a.section_id = sec.id
        LEFT JOIN subjects subj ON a.subject_id = subj.id
        WHERE s.role = 'student' 
        AND t.role = 'teacher'
        AND s.status = 'active'
        AND t.status = 'active'
    ";
    
    $pdo->exec($create_view);
    $installation_steps[] = "✓ Created attendance summary view";
    
    // Step 7: Insert system settings for QR functionality
    $qr_settings = [
        ['qr_session_duration', '24', 'Duration in hours for QR code session validity'],
        ['auto_mark_absent', '1', 'Automatically mark students absent if not scanned by end of day'],
        ['attendance_grace_period', '30', 'Grace period in minutes after class start for late marking'],
        ['qr_scan_cooldown', '5', 'Cooldown period in minutes between scans for same student-subject']
    ];
    
    $settings_stmt = $pdo->prepare("INSERT IGNORE INTO `system_settings` (`setting_name`, `setting_value`, `description`) VALUES (?, ?, ?)");
    
    foreach ($qr_settings as $setting) {
        $settings_stmt->execute($setting);
    }
    
    $installation_steps[] = "✓ Added QR system settings";
    
    // Step 8: Update existing attendance records
    $update_source_stmt = $pdo->prepare("UPDATE `attendance` SET `attendance_source` = 'qr_scan' WHERE `qr_scanned` = 1 AND (`attendance_source` IS NULL OR `attendance_source` = 'manual')");
    $update_source_stmt->execute();
    $updated_records = $update_source_stmt->rowCount();
    
    $installation_steps[] = "✓ Updated {$updated_records} existing attendance records with QR source";
    
    // Step 9: Verify installation
    $tables_to_check = ['teacher_qr_sessions', 'attendance', 'qr_scans', 'system_settings'];
    foreach ($tables_to_check as $table) {
        $check_stmt = $pdo->query("SHOW TABLES LIKE '{$table}'");
        if ($check_stmt->rowCount() > 0) {
            $installation_steps[] = "✓ Table {$table} verified";
        } else {
            $errors[] = "✗ Table {$table} not found";
        }
    }
    
    $installation_steps[] = "✓ Installation completed successfully!";
    
} catch (PDOException $e) {
    $errors[] = "Database error: " . $e->getMessage();
} catch (Exception $e) {
    $errors[] = "Installation error: " . $e->getMessage();
}

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Attendance System Installation</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0">
                            <i class="fas fa-qrcode me-2"></i>
                            QR Attendance System Installation
                        </h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($errors)): ?>
                            <div class="alert alert-success">
                                <h5><i class="fas fa-check-circle me-2"></i>Installation Successful!</h5>
                                <p class="mb-0">The QR attendance system has been successfully installed and configured.</p>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-danger">
                                <h5><i class="fas fa-exclamation-circle me-2"></i>Installation Errors</h5>
                                <p class="mb-0">Some errors occurred during installation. Please review and fix them.</p>
                            </div>
                        <?php endif; ?>
                        
                        <h5>Installation Steps:</h5>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($installation_steps as $step): ?>
                                <li class="list-group-item border-0 ps-0">
                                    <span class="text-success"><?php echo htmlspecialchars($step); ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        
                        <?php if (!empty($errors)): ?>
                            <h5 class="text-danger mt-4">Errors:</h5>
                            <ul class="list-group list-group-flush">
                                <?php foreach ($errors as $error): ?>
                                    <li class="list-group-item border-0 ps-0">
                                        <span class="text-danger"><?php echo htmlspecialchars($error); ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        
                        <div class="mt-4">
                            <h5>What's New:</h5>
                            <ul>
                                <li><strong>Teacher QR Code Generation:</strong> Teachers can now generate QR codes for attendance sessions</li>
                                <li><strong>Student QR Scanner:</strong> Students can scan teacher QR codes to mark attendance</li>
                                <li><strong>Automatic Enrollment:</strong> Students are automatically enrolled in subjects when scanning</li>
                                <li><strong>Enhanced Tracking:</strong> Better logging and session management for QR scans</li>
                                <li><strong>SMS Notifications:</strong> Parents receive SMS when students mark attendance via QR</li>
                                <li><strong>Cooldown Protection:</strong> Prevents spam scanning with cooldown periods</li>
                            </ul>
                        </div>
                        
                        <div class="mt-4">
                            <h5>Next Steps:</h5>
                            <ol>
                                <li>Teachers can generate attendance QR codes from their dashboard</li>
                                <li>Students can scan QR codes using the scanner in their dashboard</li>
                                <li>Monitor attendance through the enhanced attendance reports</li>
                                <li>Configure QR settings in the system settings page</li>
                            </ol>
                        </div>
                        
                        <div class="text-center mt-4">
                            <a href="dashboard.php" class="btn btn-primary">
                                <i class="fas fa-home me-2"></i>Go to Dashboard
                            </a>
                            <a href="attendance.php" class="btn btn-success ms-2">
                                <i class="fas fa-chart-line me-2"></i>View Attendance
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>System Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <strong>Database:</strong> <?php echo $db_name; ?><br>
                                <strong>Host:</strong> <?php echo $db_host; ?><br>
                                <strong>PHP Version:</strong> <?php echo PHP_VERSION; ?>
                            </div>
                            <div class="col-md-6">
                                <strong>Installation Date:</strong> <?php echo date('Y-m-d H:i:s'); ?><br>
                                <strong>Server:</strong> <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?><br>
                                <strong>User:</strong> <?php echo $_SESSION['username'] ?? 'Not logged in'; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
