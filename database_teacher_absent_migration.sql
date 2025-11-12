-- Create teacher_absent_logs table
CREATE TABLE IF NOT EXISTS teacher_absent_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    teacher_name VARCHAR(255) NOT NULL,
    notification_date DATE NOT NULL,
    students_notified INT DEFAULT 0,
    sms_sent INT DEFAULT 0,
    sms_failed INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_teacher_date (teacher_id, notification_date),
    INDEX idx_notification_date (notification_date)
);