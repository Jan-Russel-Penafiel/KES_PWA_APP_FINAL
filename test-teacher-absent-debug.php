<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Step 1: Loading config...<br>";
require_once "config.php";
echo "Config loaded.<br>";

echo "Step 2: Loading sms_functions...<br>";
require_once "sms_functions.php";
echo "SMS functions loaded.<br>";

echo "Step 3: Checking teacher_absent_logs table...<br>";
try {
    $result = $pdo->query("SHOW TABLES LIKE 'teacher_absent_logs'");
    if ($result->rowCount() > 0) {
        echo "Table exists.<br>";
    } else {
        echo "<strong style='color:red'>Table 'teacher_absent_logs' does NOT exist!</strong><br>";
        echo "Creating table...<br>";
        
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS teacher_absent_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                teacher_id INT NOT NULL,
                teacher_name VARCHAR(255) NOT NULL,
                notification_date DATE NOT NULL,
                students_notified INT DEFAULT 0,
                sms_sent INT DEFAULT 0,
                sms_failed INT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_teacher_date (teacher_id, notification_date),
                INDEX idx_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        echo "<strong style='color:green'>Table created successfully!</strong><br>";
    }
} catch (PDOException $e) {
    echo "<strong style='color:red'>Database error: " . $e->getMessage() . "</strong><br>";
}

echo "Step 4: Checking session...<br>";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
echo "Session ID: " . session_id() . "<br>";
echo "User ID: " . ($_SESSION['user_id'] ?? 'Not set') . "<br>";
echo "Role: " . ($_SESSION['role'] ?? 'Not set') . "<br>";

echo "<br><strong>Debug complete.</strong>";
?>
