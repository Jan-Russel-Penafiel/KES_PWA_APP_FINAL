-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 17, 2025 at 04:05 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `kes_smart`
--

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `section_id` int(11) NOT NULL,
  `attendance_date` date NOT NULL,
  `time_in` time DEFAULT NULL,
  `time_out` time DEFAULT NULL,
  `status` enum('present','absent','late','out') NOT NULL,
  `remarks` text DEFAULT NULL,
  `qr_scanned` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `qr_scans`
--

CREATE TABLE `qr_scans` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `scan_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `location` varchar(100) DEFAULT NULL,
  `device_info` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sections`
--

CREATE TABLE `sections` (
  `id` int(11) NOT NULL,
  `section_name` varchar(50) NOT NULL,
  `grade_level` varchar(20) NOT NULL,
  `teacher_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sections`
--

INSERT INTO `sections` (`id`, `section_name`, `grade_level`, `teacher_id`, `description`, `status`, `created_at`) VALUES
(1, 'St. Alpha', '7', 2, 'asSA', 'active', '2025-07-17 09:10:38');

-- --------------------------------------------------------

--
-- Table structure for table `sms_config`
--

CREATE TABLE `sms_config` (
  `id` int(11) NOT NULL,
  `provider_name` varchar(50) NOT NULL,
  `api_url` varchar(255) NOT NULL,
  `api_key` varchar(255) NOT NULL,
  `sender_name` varchar(20) NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sms_config`
--

INSERT INTO `sms_config` (`id`, `provider_name`, `api_url`, `api_key`, `sender_name`, `status`, `created_at`, `updated_at`) VALUES
(1, 'PhilSMS', 'https://app.philsms.com/api/v3/sms/send', '2100|J9BVGEx9FFOJAbHV0xfn6SMOkKBt80HTLjHb6zZX', 'PhilSMS', 'active', '2025-07-17 09:04:17', '2025-07-17 09:04:17');

-- --------------------------------------------------------

--
-- Table structure for table `sms_logs`
--

CREATE TABLE `sms_logs` (
  `id` int(11) NOT NULL,
  `phone_number` varchar(20) NOT NULL,
  `message` text NOT NULL,
  `response` text DEFAULT NULL,
  `status` enum('sent','failed','pending') DEFAULT 'pending',
  `notification_type` varchar(50) DEFAULT 'general',
  `reference_id` varchar(100) DEFAULT NULL,
  `scheduled_at` timestamp NULL DEFAULT NULL,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sms_logs`
--

INSERT INTO `sms_logs` (`id`, `phone_number`, `message`, `response`, `status`, `notification_type`, `reference_id`, `scheduled_at`, `sent_at`) VALUES
(1, '639977542522', 'Hello from KES-SMART! This is a test message to verify SMS configuration.', 'SMS sent successfully', 'sent', 'general', NULL, NULL, '2025-07-17 12:03:29');

-- --------------------------------------------------------

--
-- Table structure for table `student_parents`
--

CREATE TABLE `student_parents` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `parent_id` int(11) NOT NULL,
  `relationship` enum('father','mother','guardian') NOT NULL,
  `is_primary` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_parents`
--

INSERT INTO `student_parents` (`id`, `student_id`, `parent_id`, `relationship`, `is_primary`, `created_at`) VALUES
(1, 3, 4, 'mother', 1, '2025-07-17 09:11:37');

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL,
  `setting_name` varchar(50) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`id`, `setting_name`, `setting_value`, `description`, `updated_at`) VALUES
(1, 'school_name', 'KES School', 'Name of the school', '2025-07-17 09:04:17'),
(2, 'school_address', 'School Address Here', 'School address', '2025-07-17 09:04:17'),
(3, 'attendance_time_start', '07:00', 'School start time', '2025-07-17 09:04:17'),
(4, 'attendance_time_end', '17:00', 'School end time', '2025-07-17 09:04:17'),
(5, 'late_threshold', '15', 'Minutes after start time to mark as late', '2025-07-17 09:04:17'),
(6, 'auto_sms_notifications', '1', 'Enable automatic SMS notifications', '2025-07-17 09:04:17');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `role` enum('admin','teacher','student','parent') NOT NULL,
  `lrn` varchar(12) DEFAULT NULL COMMENT 'Learner Reference Number for students only',
  `section_id` int(11) DEFAULT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `qr_code` text DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `full_name`, `email`, `phone`, `role`, `lrn`, `section_id`, `parent_id`, `profile_image`, `qr_code`, `status`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'System Administrator', 'admin@kes-smart.com', NULL, 'admin', NULL, NULL, NULL, NULL, NULL, 'active', '2025-07-17 09:04:17', '2025-07-17 11:22:16'),
(2, 'teacher', 'Jan Russel Peñafiel', 'artiedastephany@gmail.com', '09677726912', 'teacher', NULL, NULL, NULL, NULL, NULL, 'active', '2025-07-17 09:10:24', '2025-07-17 12:04:30'),
(3, 'student', 'Jan Russel Peñafielaaa', 'artiedastephanya@gmail.com', '09677726912', 'student', '217614409312', 1, NULL, NULL, 'S0VTLVNNQVJULVNUVURFTlQtc3R1ZGVudC0yMDI1', 'active', '2025-07-17 09:10:59', '2025-07-17 09:12:00'),
(4, 'parent', 'Jan Russel Peñafiel', 'penafielliezl3322@gmail.com', '09677726912', 'parent', NULL, NULL, NULL, NULL, NULL, 'active', '2025-07-17 09:11:21', '2025-07-17 09:11:21');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_attendance` (`student_id`,`attendance_date`),
  ADD KEY `teacher_id` (`teacher_id`),
  ADD KEY `section_id` (`section_id`);

--
-- Indexes for table `qr_scans`
--
ALTER TABLE `qr_scans`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `teacher_id` (`teacher_id`);

--
-- Indexes for table `sections`
--
ALTER TABLE `sections`
  ADD PRIMARY KEY (`id`),
  ADD KEY `teacher_id` (`teacher_id`);

--
-- Indexes for table `sms_config`
--
ALTER TABLE `sms_config`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `sms_logs`
--
ALTER TABLE `sms_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `student_parents`
--
ALTER TABLE `student_parents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `parent_id` (`parent_id`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_name` (`setting_name`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `lrn` (`lrn`),
  ADD KEY `section_id` (`section_id`),
  ADD KEY `parent_id` (`parent_id`),
  ADD KEY `idx_users_lrn` (`lrn`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `qr_scans`
--
ALTER TABLE `qr_scans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sections`
--
ALTER TABLE `sections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `sms_config`
--
ALTER TABLE `sms_config`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `sms_logs`
--
ALTER TABLE `sms_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `student_parents`
--
ALTER TABLE `student_parents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `attendance_ibfk_2` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `attendance_ibfk_3` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`);

--
-- Constraints for table `qr_scans`
--
ALTER TABLE `qr_scans`
  ADD CONSTRAINT `qr_scans_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `qr_scans_ibfk_2` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `sections`
--
ALTER TABLE `sections`
  ADD CONSTRAINT `sections_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `student_parents`
--
ALTER TABLE `student_parents`
  ADD CONSTRAINT `student_parents_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `student_parents_ibfk_2` FOREIGN KEY (`parent_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`),
  ADD CONSTRAINT `users_ibfk_2` FOREIGN KEY (`parent_id`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
