-- KES-SMART QR Attendance System Database Updates
-- This file ensures the database has all required tables and columns for the QR attendance system
-- Note: Most components already exist, this script just ensures completeness

-- Ensure the attendance table has subject_id column (already exists, but safe to run)
ALTER TABLE `attendance` 
ADD COLUMN IF NOT EXISTS `subject_id` int(11) DEFAULT NULL;

-- Add index for subject_id if it doesn't exist (safe to run)
CREATE INDEX IF NOT EXISTS `idx_attendance_subject` ON `attendance` (`subject_id`);

-- Ensure the student_subjects table exists (already exists, safe to run)
CREATE TABLE IF NOT EXISTS `student_subjects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `enrolled_date` date DEFAULT (curdate()),
  `status` enum('enrolled','dropped') DEFAULT 'enrolled',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_student_subject` (`student_id`, `subject_id`),
  KEY `idx_student_subjects_student` (`student_id`),
  KEY `idx_student_subjects_subject` (`subject_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Foreign key constraints already exist with different names:
-- attendance.subject_id -> subjects.id (exists as 'attendance_ibfk_4')
-- student_subjects.student_id -> users.id (exists as 'student_subjects_ibfk_1') 
-- student_subjects.subject_id -> subjects.id (exists as 'student_subjects_ibfk_2')
-- No need to add them again.

-- Create attendance sessions table for tracking teacher QR sessions (optional)
-- Note: This table already exists with proper structure and constraints, so this step is skipped
-- CREATE TABLE IF NOT EXISTS `attendance_sessions` (
--   `id` int(11) NOT NULL AUTO_INCREMENT,
--   `session_id` varchar(100) NOT NULL,
--   `teacher_id` int(11) NOT NULL,
--   `subject_id` int(11) NOT NULL,
--   `section_id` int(11) NOT NULL,
--   `qr_data` text NOT NULL,
--   `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
--   `expires_at` timestamp NOT NULL DEFAULT (current_timestamp() + INTERVAL 1 HOUR),
--   `status` enum('active','expired','ended') DEFAULT 'active',
--   PRIMARY KEY (`id`),
--   UNIQUE KEY `unique_session_id` (`session_id`),
--   KEY `idx_sessions_teacher` (`teacher_id`),
--   KEY `idx_sessions_subject` (`subject_id`),
--   KEY `idx_sessions_section` (`section_id`),
--   KEY `idx_sessions_status` (`status`),
--   CONSTRAINT `sessions_teacher_fk` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
--   CONSTRAINT `sessions_subject_fk` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE,
--   CONSTRAINT `sessions_section_fk` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE CASCADE
-- ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Sample data: Enroll all students in all subjects of their grade level
-- This is a basic enrollment that you can customize based on your school's needs
INSERT IGNORE INTO `student_subjects` (`student_id`, `subject_id`)
SELECT 
    u.id as student_id,
    s.id as subject_id
FROM `users` u
CROSS JOIN `subjects` s
INNER JOIN `sections` sec ON u.section_id = sec.id
WHERE u.role = 'student' 
  AND u.status = 'active'
  AND s.status = 'active'
  AND s.grade_level = sec.grade_level;

-- Update existing attendance records to link them with subjects where possible
-- This tries to match attendance records with subjects based on section and date
UPDATE `attendance` a
INNER JOIN `users` u ON a.student_id = u.id
INNER JOIN `sections` sec ON a.section_id = sec.id
INNER JOIN `subjects` s ON s.section_id = sec.id AND s.status = 'active'
SET a.subject_id = s.id
WHERE a.subject_id IS NULL
  AND s.teacher_id = a.teacher_id;