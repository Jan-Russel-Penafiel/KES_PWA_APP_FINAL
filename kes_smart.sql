-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 22, 2025 at 06:23 PM
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
  `section_id` int(11) DEFAULT NULL,
  `subject_id` int(11) DEFAULT NULL,
  `attendance_date` date NOT NULL,
  `time_in` time DEFAULT NULL,
  `time_out` time DEFAULT NULL,
  `status` enum('present','absent','late','out') NOT NULL,
  `remarks` text DEFAULT NULL,
  `qr_scanned` tinyint(1) DEFAULT 0,
  `attendance_source` enum('manual','qr_scan','auto') DEFAULT 'manual',
  `scan_location` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attendance`
--

INSERT INTO `attendance` (`id`, `student_id`, `teacher_id`, `section_id`, `subject_id`, `attendance_date`, `time_in`, `time_out`, `status`, `remarks`, `qr_scanned`, `attendance_source`, `scan_location`, `created_at`) VALUES
(96, 3, 2, NULL, 16, '2025-11-12', '12:50:32', NULL, 'late', 'Main Gate', 1, 'qr_scan', NULL, '2025-11-12 04:50:32');

-- --------------------------------------------------------

--
-- Table structure for table `attendance_sessions`
--

CREATE TABLE `attendance_sessions` (
  `id` int(11) NOT NULL,
  `session_id` varchar(100) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `section_id` int(11) NOT NULL,
  `qr_data` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NOT NULL DEFAULT (current_timestamp() + interval 1 hour),
  `status` enum('active','expired','ended') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `attendance_summary`
-- (See below for the actual view)
--
CREATE TABLE `attendance_summary` (
`id` int(11)
,`student_id` int(11)
,`student_username` varchar(50)
,`student_name` varchar(100)
,`lrn` varchar(12)
,`teacher_id` int(11)
,`teacher_name` varchar(100)
,`section_id` int(11)
,`section_name` varchar(50)
,`grade_level` varchar(20)
,`subject_id` int(11)
,`subject_name` varchar(100)
,`subject_code` varchar(20)
,`attendance_date` date
,`time_in` time
,`time_out` time
,`status` enum('present','absent','late','out')
,`remarks` text
,`qr_scanned` tinyint(1)
,`attendance_source` enum('manual','qr_scan','auto')
,`scan_location` varchar(255)
,`created_at` timestamp
);

-- --------------------------------------------------------

--
-- Table structure for table `qr_scans`
--

CREATE TABLE `qr_scans` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `qr_type` enum('student','teacher','attendance') DEFAULT 'attendance',
  `scan_result` enum('success','failed','duplicate') DEFAULT 'success',
  `session_id` int(11) DEFAULT NULL,
  `scan_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `location` varchar(100) DEFAULT NULL,
  `device_info` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `qr_scans`
--

INSERT INTO `qr_scans` (`id`, `student_id`, `teacher_id`, `qr_type`, `scan_result`, `session_id`, `scan_time`, `location`, `device_info`) VALUES
(1, 3, 2, 'attendance', 'success', NULL, '2025-11-07 03:25:03', 'Student Self-Scan', '{\"user_agent\":\"Mozilla\\/5.0 (Linux; Android 8.0.0; SM-G955U Build\\/R16NW) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/142.0.0.0 Mobile Safari\\/537.36\",\"ip_address\":\"::1\",\"session_id\":\"session_1762485917869\"}'),
(2, 3, 2, 'attendance', 'success', NULL, '2025-11-07 03:27:05', 'Student Self-Scan', '{\"user_agent\":\"Mozilla\\/5.0 (Linux; Android 10; K) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/142.0.0.0 Mobile Safari\\/537.36\",\"ip_address\":\"::1\",\"session_id\":\"session_1762486012378\"}'),
(3, 3, 2, 'attendance', 'success', NULL, '2025-11-09 07:18:11', 'Student Self-Scan', '{\"user_agent\":\"Mozilla\\/5.0 (Linux; Android 10; K) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/142.0.0.0 Mobile Safari\\/537.36\",\"ip_address\":\"::1\",\"session_id\":\"session_1762672633133\"}'),
(4, 3, 2, 'attendance', 'success', NULL, '2025-11-09 07:19:02', 'Student Self-Scan', '{\"user_agent\":\"Mozilla\\/5.0 (Linux; Android 10; K) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/142.0.0.0 Mobile Safari\\/537.36\",\"ip_address\":\"::1\",\"session_id\":\"session_1762672731416\"}'),
(5, 3, 2, 'attendance', 'success', NULL, '2025-11-09 07:19:36', 'Student Self-Scan', '{\"user_agent\":\"Mozilla\\/5.0 (Linux; Android 10; K) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/142.0.0.0 Mobile Safari\\/537.36\",\"ip_address\":\"::1\",\"session_id\":\"session_1762672759368\"}'),
(6, 3, 2, 'attendance', 'success', NULL, '2025-11-09 07:20:40', 'Student Self-Scan', '{\"user_agent\":\"Mozilla\\/5.0 (Linux; Android 10; K) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/142.0.0.0 Mobile Safari\\/537.36\",\"ip_address\":\"::1\",\"session_id\":\"session_1762672837219\"}'),
(7, 3, 2, 'attendance', 'success', NULL, '2025-11-10 08:04:01', 'Student Self-Scan', '{\"user_agent\":\"Mozilla\\/5.0 (Linux; Android 10; K) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/142.0.0.0 Mobile Safari\\/537.36\",\"ip_address\":\"::1\",\"session_id\":\"session_1762761802559\"}'),
(8, 3, 2, 'attendance', 'success', NULL, '2025-11-10 08:04:52', 'Student Self-Scan', '{\"user_agent\":\"Mozilla\\/5.0 (Linux; Android 10; K) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/142.0.0.0 Mobile Safari\\/537.36\",\"ip_address\":\"::1\",\"session_id\":\"session_1762761802559\"}'),
(9, 3, 2, 'attendance', 'success', NULL, '2025-11-10 08:08:32', 'Student Self-Scan', '{\"user_agent\":\"Mozilla\\/5.0 (Linux; Android 10; K) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/142.0.0.0 Mobile Safari\\/537.36\",\"ip_address\":\"::1\",\"session_id\":\"session_1762762103592\"}'),
(10, 3, 2, 'attendance', 'success', NULL, '2025-11-10 08:15:41', 'Student Self-Scan', '{\"user_agent\":\"Mozilla\\/5.0 (Linux; Android 10; K) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/142.0.0.0 Mobile Safari\\/537.36\",\"ip_address\":\"::1\",\"session_id\":\"session_1762762103592\"}'),
(11, 3, 2, 'attendance', 'success', NULL, '2025-11-10 11:39:23', 'Student Self-Scan', '{\"user_agent\":\"Mozilla\\/5.0 (Linux; Android 10; K) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/142.0.0.0 Mobile Safari\\/537.36\",\"ip_address\":\"::1\",\"session_id\":\"session_1762762103592\"}'),
(12, 3, 2, 'attendance', 'success', NULL, '2025-11-10 11:42:51', 'Student Self-Scan', '{\"user_agent\":\"Mozilla\\/5.0 (Linux; Android 10; K) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/142.0.0.0 Mobile Safari\\/537.36\",\"ip_address\":\"::1\",\"session_id\":\"session_1762774917348\"}'),
(13, 3, 2, 'attendance', 'success', NULL, '2025-11-11 01:16:26', 'Student Self-Scan', '{\"user_agent\":\"Mozilla\\/5.0 (Linux; Android 10; K) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/142.0.0.0 Mobile Safari\\/537.36\",\"ip_address\":\"::1\",\"session_id\":\"session_1762823734399\"}'),
(14, 3, 2, 'attendance', 'success', NULL, '2025-11-11 01:23:56', 'Student Self-Scan', '{\"user_agent\":\"Mozilla\\/5.0 (Linux; Android 10; K) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/142.0.0.0 Mobile Safari\\/537.36\",\"ip_address\":\"::1\",\"session_id\":\"session_1762824221302_in\"}'),
(15, 3, 2, 'attendance', 'success', NULL, '2025-11-11 01:24:08', 'Student Self-Scan', '{\"user_agent\":\"Mozilla\\/5.0 (Linux; Android 10; K) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/142.0.0.0 Mobile Safari\\/537.36\",\"ip_address\":\"::1\",\"session_id\":\"session_1762824221302_in\"}'),
(16, 3, 2, 'attendance', 'success', NULL, '2025-11-11 01:33:01', 'Student Self-Scan', '{\"user_agent\":\"Mozilla\\/5.0 (Linux; Android 10; K) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/142.0.0.0 Mobile Safari\\/537.36\",\"ip_address\":\"::1\",\"session_id\":\"session_1762824762078\"}'),
(17, 3, 2, 'attendance', 'success', NULL, '2025-11-11 06:01:04', 'Student Self-Scan', '{\"user_agent\":\"Mozilla\\/5.0 (Linux; Android 10; K) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/142.0.0.0 Mobile Safari\\/537.36\",\"ip_address\":\"::1\",\"session_id\":\"session_1762840845986\"}'),
(18, 3, 2, 'attendance', 'success', NULL, '2025-11-11 06:02:37', 'Student Self-Scan', '{\"user_agent\":\"Mozilla\\/5.0 (Linux; Android 10; K) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/142.0.0.0 Mobile Safari\\/537.36\",\"ip_address\":\"::1\",\"session_id\":\"session_1762840845986\"}'),
(19, 3, 2, 'attendance', 'success', NULL, '2025-11-11 06:09:35', 'Student Self-Scan', '{\"user_agent\":\"Mozilla\\/5.0 (Linux; Android 10; K) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/142.0.0.0 Mobile Safari\\/537.36\",\"ip_address\":\"::1\",\"session_id\":\"session_1762841364914\"}'),
(20, 3, 2, 'attendance', 'success', NULL, '2025-11-11 06:12:56', 'Student Self-Scan', '{\"user_agent\":\"Mozilla\\/5.0 (Linux; Android 10; K) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/142.0.0.0 Mobile Safari\\/537.36\",\"ip_address\":\"::1\",\"session_id\":\"session_1762841364914\"}'),
(21, 3, 2, 'attendance', 'success', NULL, '2025-11-11 06:14:02', 'Student Self-Scan', '{\"user_agent\":\"Mozilla\\/5.0 (Linux; Android 10; K) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/142.0.0.0 Mobile Safari\\/537.36\",\"ip_address\":\"::1\",\"session_id\":\"session_1762841364914\"}'),
(22, 3, 2, 'attendance', 'success', NULL, '2025-11-11 06:21:40', 'Student Self-Scan', '{\"user_agent\":\"Mozilla\\/5.0 (Linux; Android 10; K) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/142.0.0.0 Mobile Safari\\/537.36\",\"ip_address\":\"::1\",\"session_id\":\"session_1762842062001\"}'),
(23, 3, 2, 'attendance', 'success', NULL, '2025-11-11 06:38:37', 'Student Self-Scan', '{\"user_agent\":\"Mozilla\\/5.0 (Linux; Android 10; K) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/142.0.0.0 Mobile Safari\\/537.36\",\"ip_address\":\"::1\",\"session_id\":\"session_1762843086648\"}'),
(24, 3, 2, 'attendance', 'success', NULL, '2025-11-11 06:41:17', 'Student Self-Scan', '{\"user_agent\":\"Mozilla\\/5.0 (Linux; Android 10; K) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/142.0.0.0 Mobile Safari\\/537.36\",\"ip_address\":\"::1\",\"session_id\":\"session_1762843254910\"}'),
(25, 3, 2, 'attendance', 'success', NULL, '2025-11-11 06:42:46', 'Student Self-Scan', '{\"user_agent\":\"Mozilla\\/5.0 (Linux; Android 10; K) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/142.0.0.0 Mobile Safari\\/537.36\",\"ip_address\":\"::1\",\"session_id\":\"session_1762843356169\"}'),
(26, 3, 2, 'attendance', 'success', NULL, '2025-11-12 03:43:21', 'Student Self-Scan', '{\"user_agent\":\"Mozilla\\/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit\\/537.36 (KHTML, like Gecko) Chrome\\/142.0.0.0 Safari\\/537.36\",\"ip_address\":\"::1\",\"session_id\":\"session_1762670825544\"}');

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
(1, 'St. Alpha', '7', 2, 'asSA', 'active', '2025-07-17 09:10:38'),
(2, 'St. Root', '8', NULL, 'SAD ad', 'active', '2025-07-18 08:19:57');

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
(1, 'PhilSMS', 'https://sms.iprogtech.com/api/v1/sms_messages', '1ef3b27ea753780a90cbdf07d027fb7b52791004', 'Ip', 'active', '2025-07-17 09:04:17', '2025-11-11 06:25:51');

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
(84, '09677726912', 'Hi! Your child test@student has arrived late to asdada class at 12:50 PM on November 12, 2025. - KES-SMART', 'SMS sent successfully', 'sent', 'attendance', 'iSms-KCBgbr', NULL, '2025-11-12 04:50:33');

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
-- Table structure for table `student_subjects`
--

CREATE TABLE `student_subjects` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `enrolled_date` date DEFAULT curdate(),
  `status` enum('enrolled','dropped') DEFAULT 'enrolled',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `student_subjects`
--

INSERT INTO `student_subjects` (`id`, `student_id`, `subject_id`, `enrolled_date`, `status`, `created_at`) VALUES
(15, 3, 16, '2025-11-10', 'enrolled', '2025-11-10 07:13:44'),
(16, 3, 5, '2025-11-10', 'enrolled', '2025-11-10 07:13:44');

-- --------------------------------------------------------

--
-- Table structure for table `subjects`
--

CREATE TABLE `subjects` (
  `id` int(11) NOT NULL,
  `subject_name` varchar(100) NOT NULL,
  `subject_code` varchar(20) NOT NULL,
  `teacher_id` int(11) DEFAULT NULL,
  `section_id` int(11) DEFAULT NULL,
  `grade_level` varchar(20) NOT NULL,
  `description` text DEFAULT NULL,
  `schedule` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subjects`
--

INSERT INTO `subjects` (`id`, `subject_name`, `subject_code`, `teacher_id`, `section_id`, `grade_level`, `description`, `schedule`, `status`, `created_at`, `updated_at`) VALUES
(5, 'English', 'ENG101', 2, 1, 'Grade 7', 'English Language Arts', 'Monday 9:00-10:00 AM, Tuesday 9:00-10:00 AM, Thursday 9:00-10:00 AM', 'active', '2025-08-17 08:34:17', '2025-08-19 03:57:47'),
(7, 'Social Studies', 'SS101', NULL, 1, 'Grade 7', 'Social Studies and History', 'Wednesday 10:00-11:00 AM, Friday 10:00-11:00 AM', 'active', '2025-08-17 08:34:17', '2025-08-19 03:57:47'),
(8, 'Physical Education', 'PE101', NULL, 1, 'Grade 7', 'Physical Education and Health', 'Friday 2:00-3:00 PM', 'active', '2025-08-17 08:34:17', '2025-08-19 03:57:47'),
(9, 'Advanced Mathematics', 'MATH201', NULL, 2, 'Grade 8', 'Intermediate Mathematics', 'Monday 10:00-11:00 AM, Wednesday 10:00-11:00 AM, Friday 10:00-11:00 AM', 'active', '2025-08-17 08:34:17', '2025-08-19 03:57:47'),
(10, 'Literature', 'ENG201', NULL, 2, 'Grade 8', 'English Literature and Writing', 'Monday 11:00-12:00 PM, Tuesday 11:00-12:00 PM, Thursday 11:00-12:00 PM', 'active', '2025-08-17 08:34:17', '2025-08-19 03:57:47'),
(11, 'Biology', 'BIO201', NULL, 2, 'Grade 8', 'Introduction to Biology', 'Tuesday 2:00-3:00 PM, Thursday 2:00-3:00 PM', 'active', '2025-08-17 08:34:17', '2025-08-19 03:57:47'),
(12, 'Geography', 'GEO201', NULL, 2, 'Grade 8', 'World Geography', 'Wednesday 2:00-3:00 PM', 'active', '2025-08-17 08:34:17', '2025-08-19 03:57:47'),
(13, 'Computer Science', 'CS201', NULL, 2, 'Grade 8', 'Basic Computer Skills', 'Friday 11:00-12:00 PM', 'active', '2025-08-17 08:34:17', '2025-08-19 03:57:47'),
(16, 'asdada', 'asda', 2, 1, 'Grade 7', 'asda', 'asda', 'active', '2025-11-10 09:06:48', '2025-11-10 09:06:48');

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
(2, 'school_address', 'Tacurong City', 'School address', '2025-07-17 09:04:17'),
(3, 'attendance_time_start', '07:00', 'School start time', '2025-07-17 09:04:17'),
(4, 'attendance_time_end', '16:00', 'School end time', '2025-11-07 03:36:44'),
(5, 'late_threshold', '15', 'Minutes after start time to mark as late', '2025-07-17 09:04:17'),
(6, 'auto_sms_notifications', '1', 'Enable automatic SMS notifications', '2025-07-17 09:04:17'),
(7, 'qr_session_duration', '24', 'Duration in hours for QR code session validity', '2025-08-28 12:01:08'),
(8, 'auto_mark_absent', '1', 'Automatically mark students absent if not scanned by end of day', '2025-08-28 12:01:08'),
(9, 'attendance_grace_period', '30', 'Grace period in minutes after class start for late marking', '2025-08-28 12:01:08'),
(10, 'qr_scan_cooldown', '5', 'Cooldown period in minutes between scans for same student-subject', '2025-08-28 12:01:08');

-- --------------------------------------------------------

--
-- Table structure for table `teacher_absent_logs`
--

CREATE TABLE `teacher_absent_logs` (
  `id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `teacher_name` varchar(255) NOT NULL,
  `notification_date` date NOT NULL,
  `subject_ids` text DEFAULT NULL,
  `subject_names` text DEFAULT NULL,
  `students_notified` int(11) DEFAULT 0,
  `sms_sent` int(11) DEFAULT 0,
  `sms_failed` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `teacher_absent_logs`
--

INSERT INTO `teacher_absent_logs` (`id`, `teacher_id`, `teacher_name`, `notification_date`, `subject_ids`, `subject_names`, `students_notified`, `sms_sent`, `sms_failed`, `created_at`) VALUES
(1, 2, 'test@teacher', '2025-11-12', NULL, NULL, 2, 2, 0, '2025-11-12 03:18:12');

-- --------------------------------------------------------

--
-- Table structure for table `teacher_qr_sessions`
--

CREATE TABLE `teacher_qr_sessions` (
  `id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `section_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `qr_code` text NOT NULL,
  `session_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NOT NULL DEFAULT (current_timestamp() + interval 24 hour),
  `status` enum('active','expired','closed') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `profile_image_path` varchar(255) DEFAULT NULL,
  `qr_code` text DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `full_name`, `email`, `phone`, `role`, `lrn`, `section_id`, `parent_id`, `profile_image`, `profile_image_path`, `qr_code`, `status`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'System Administrator', 'cnoel1570@gmail.com', NULL, 'admin', NULL, NULL, NULL, NULL, NULL, NULL, 'active', '2025-07-17 09:04:17', '2025-11-22 17:20:03'),
(2, 'teacher', 'test@teacher', 'swaynedaanoy@gmail.com', '09659751021', 'teacher', NULL, NULL, NULL, NULL, NULL, NULL, 'active', '2025-07-17 09:10:24', '2025-11-22 17:21:00'),
(3, 'student', 'test@student', 'cnoel1570@gmail.com', '09531983833', 'student', '217614409312', 1, NULL, 'student_3_1762762339.jpg', 'uploads/student_photos/thumbnails/student_3_1762762339.jpg', 'S0VTLVNNQVJULVNUVURFTlQtc3R1ZGVudC0yMDI1', 'active', '2025-07-17 09:10:59', '2025-11-22 17:23:12'),
(4, 'parent', 'test@parent', 'krysteljoyligo0@gmail.com', '09676402632', 'parent', NULL, NULL, NULL, NULL, NULL, NULL, 'active', '2025-07-17 09:11:21', '2025-11-22 17:22:59');

-- --------------------------------------------------------

--
-- Structure for view `attendance_summary`
--
DROP TABLE IF EXISTS `attendance_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `attendance_summary`  AS SELECT `a`.`id` AS `id`, `a`.`student_id` AS `student_id`, `s`.`username` AS `student_username`, `s`.`full_name` AS `student_name`, `s`.`lrn` AS `lrn`, `a`.`teacher_id` AS `teacher_id`, `t`.`full_name` AS `teacher_name`, `a`.`section_id` AS `section_id`, `sec`.`section_name` AS `section_name`, `sec`.`grade_level` AS `grade_level`, `a`.`subject_id` AS `subject_id`, `subj`.`subject_name` AS `subject_name`, `subj`.`subject_code` AS `subject_code`, `a`.`attendance_date` AS `attendance_date`, `a`.`time_in` AS `time_in`, `a`.`time_out` AS `time_out`, `a`.`status` AS `status`, `a`.`remarks` AS `remarks`, `a`.`qr_scanned` AS `qr_scanned`, `a`.`attendance_source` AS `attendance_source`, `a`.`scan_location` AS `scan_location`, `a`.`created_at` AS `created_at` FROM ((((`attendance` `a` join `users` `s` on(`a`.`student_id` = `s`.`id`)) join `users` `t` on(`a`.`teacher_id` = `t`.`id`)) left join `sections` `sec` on(`a`.`section_id` = `sec`.`id`)) left join `subjects` `subj` on(`a`.`subject_id` = `subj`.`id`)) WHERE `s`.`role` = 'student' AND `t`.`role` = 'teacher' AND `s`.`status` = 'active' AND `t`.`status` = 'active' ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_subject_attendance` (`student_id`,`subject_id`,`attendance_date`),
  ADD KEY `section_id` (`section_id`),
  ADD KEY `idx_attendance_subject` (`subject_id`),
  ADD KEY `idx_attendance_subject_date` (`subject_id`,`attendance_date`),
  ADD KEY `idx_attendance_teacher_subject` (`teacher_id`,`subject_id`),
  ADD KEY `idx_attendance_date_status` (`attendance_date`,`status`),
  ADD KEY `idx_attendance_source` (`attendance_source`);

--
-- Indexes for table `attendance_sessions`
--
ALTER TABLE `attendance_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_session_id` (`session_id`),
  ADD KEY `idx_sessions_teacher` (`teacher_id`),
  ADD KEY `idx_sessions_subject` (`subject_id`),
  ADD KEY `idx_sessions_section` (`section_id`),
  ADD KEY `idx_sessions_status` (`status`);

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
-- Indexes for table `student_subjects`
--
ALTER TABLE `student_subjects`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_enrollment` (`student_id`,`subject_id`),
  ADD KEY `idx_student_subjects_student` (`student_id`),
  ADD KEY `idx_student_subjects_subject` (`subject_id`);

--
-- Indexes for table `subjects`
--
ALTER TABLE `subjects`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `subject_code` (`subject_code`),
  ADD KEY `idx_subjects_teacher` (`teacher_id`),
  ADD KEY `idx_subjects_code` (`subject_code`),
  ADD KEY `idx_subjects_section` (`section_id`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_name` (`setting_name`);

--
-- Indexes for table `teacher_absent_logs`
--
ALTER TABLE `teacher_absent_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_teacher_date` (`teacher_id`,`notification_date`),
  ADD KEY `idx_notification_date` (`notification_date`);

--
-- Indexes for table `teacher_qr_sessions`
--
ALTER TABLE `teacher_qr_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_session` (`teacher_id`,`section_id`,`subject_id`,`session_date`),
  ADD KEY `teacher_id` (`teacher_id`),
  ADD KEY `section_id` (`section_id`),
  ADD KEY `subject_id` (`subject_id`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=97;

--
-- AUTO_INCREMENT for table `attendance_sessions`
--
ALTER TABLE `attendance_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `qr_scans`
--
ALTER TABLE `qr_scans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `sections`
--
ALTER TABLE `sections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `sms_config`
--
ALTER TABLE `sms_config`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `sms_logs`
--
ALTER TABLE `sms_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=85;

--
-- AUTO_INCREMENT for table `student_parents`
--
ALTER TABLE `student_parents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `student_subjects`
--
ALTER TABLE `student_subjects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `subjects`
--
ALTER TABLE `subjects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=807;

--
-- AUTO_INCREMENT for table `teacher_absent_logs`
--
ALTER TABLE `teacher_absent_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `teacher_qr_sessions`
--
ALTER TABLE `teacher_qr_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `attendance_ibfk_2` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `attendance_ibfk_3` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `attendance_ibfk_4` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`);

--
-- Constraints for table `attendance_sessions`
--
ALTER TABLE `attendance_sessions`
  ADD CONSTRAINT `sessions_section_fk` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sessions_subject_fk` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sessions_teacher_fk` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

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
-- Constraints for table `student_subjects`
--
ALTER TABLE `student_subjects`
  ADD CONSTRAINT `student_subjects_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `student_subjects_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`);

--
-- Constraints for table `subjects`
--
ALTER TABLE `subjects`
  ADD CONSTRAINT `subjects_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `subjects_ibfk_2` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`);

--
-- Constraints for table `teacher_absent_logs`
--
ALTER TABLE `teacher_absent_logs`
  ADD CONSTRAINT `teacher_absent_logs_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `teacher_qr_sessions`
--
ALTER TABLE `teacher_qr_sessions`
  ADD CONSTRAINT `teacher_qr_sessions_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `teacher_qr_sessions_ibfk_2` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`),
  ADD CONSTRAINT `teacher_qr_sessions_ibfk_3` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`);

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
