-- KES-SMART Database Schema
CREATE DATABASE IF NOT EXISTS kes_smart;
USE kes_smart;

-- Users table (Admin, Teachers, Students, Parents)
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    phone VARCHAR(20),
    role ENUM('admin', 'teacher', 'student', 'parent') NOT NULL,
    lrn VARCHAR(12) UNIQUE NULL COMMENT 'Learner Reference Number for students only',
    section_id INT,
    parent_id INT,
    profile_image VARCHAR(255),
    qr_code TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Sections table
CREATE TABLE sections (
    id INT PRIMARY KEY AUTO_INCREMENT,
    section_name VARCHAR(50) NOT NULL,
    grade_level VARCHAR(20) NOT NULL,
    teacher_id INT,
    description TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES users(id)
);

-- Student-Parent relationship
CREATE TABLE student_parents (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    parent_id INT NOT NULL,
    relationship ENUM('father', 'mother', 'guardian') NOT NULL,
    is_primary BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(id),
    FOREIGN KEY (parent_id) REFERENCES users(id)
);

-- Attendance table
CREATE TABLE attendance (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    teacher_id INT NOT NULL,
    section_id INT NOT NULL,
    attendance_date DATE NOT NULL,
    time_in TIME,
    time_out TIME,
    status ENUM('present', 'absent', 'late', 'out') NOT NULL,
    remarks TEXT,
    qr_scanned BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(id),
    FOREIGN KEY (teacher_id) REFERENCES users(id),
    FOREIGN KEY (section_id) REFERENCES sections(id),
    UNIQUE KEY unique_attendance (student_id, attendance_date)
);

-- SMS Configuration table
CREATE TABLE sms_config (
    id INT PRIMARY KEY AUTO_INCREMENT,
    provider_name VARCHAR(50) NOT NULL,
    api_url VARCHAR(255) NOT NULL,
    api_key VARCHAR(255) NOT NULL,
    sender_name VARCHAR(20) NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- SMS Logs table
CREATE TABLE sms_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    phone_number VARCHAR(20) NOT NULL,
    message TEXT NOT NULL,
    response TEXT,
    status ENUM('sent', 'failed', 'pending') DEFAULT 'pending',
    notification_type VARCHAR(50) DEFAULT 'general',
    reference_id VARCHAR(100),
    scheduled_at TIMESTAMP NULL,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- QR Code Scans table
CREATE TABLE qr_scans (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    teacher_id INT NOT NULL,
    scan_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    location VARCHAR(100),
    device_info TEXT,
    FOREIGN KEY (student_id) REFERENCES users(id),
    FOREIGN KEY (teacher_id) REFERENCES users(id)
);

-- System Settings table
CREATE TABLE system_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_name VARCHAR(50) UNIQUE NOT NULL,
    setting_value TEXT,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default admin user
INSERT INTO users (username, full_name, email, role) VALUES 
('admin', 'System Administrator', 'admin@kes-smart.com', 'admin');

-- Insert default SMS configuration for PhilSMS
INSERT INTO sms_config (provider_name, api_url, api_key, sender_name, status) VALUES 
('PhilSMS', 'https://app.philsms.com/api/v3/sms/send', '2100|J9BVGEx9FFOJAbHV0xfn6SMOkKBt80HTLjHb6zZX', 'PhilSMS', 'active');

-- Insert default system settings
INSERT INTO system_settings (setting_name, setting_value, description) VALUES 
('school_name', 'KES School', 'Name of the school'),
('school_address', 'School Address Here', 'School address'),
('attendance_time_start', '07:00', 'School start time'),
('attendance_time_end', '17:00', 'School end time'),
('late_threshold', '15', 'Minutes after start time to mark as late'),
('auto_sms_notifications', '1', 'Enable automatic SMS notifications');

-- Add foreign key constraints
ALTER TABLE users ADD FOREIGN KEY (section_id) REFERENCES sections(id);
ALTER TABLE users ADD FOREIGN KEY (parent_id) REFERENCES users(id);

-- Add index for LRN for faster lookups
CREATE INDEX idx_users_lrn ON users(lrn);

-- Add check constraint to ensure LRN is only set for students
ALTER TABLE users ADD CONSTRAINT chk_lrn_student_only 
CHECK ((role = 'student' AND lrn IS NOT NULL) OR (role != 'student' AND lrn IS NULL) OR (role = 'student' AND lrn IS NULL));
