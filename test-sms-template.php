<?php
/**
 * Test SMS Template Implementation
 * This script tests the updated SMS notification format with VMC template
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include required files
require_once 'config.php';
require_once 'sms_functions.php';

echo "<h2>SMS Template Test</h2>";
echo "<hr>";

// Test 1: Test the formatSMSMessage function
echo "<h3>1. Testing formatSMSMessage() function:</h3>";
$test_message = "John Doe arrived at school at 7:30 AM, January 2, 2026. Section: Section A.";
$formatted = formatSMSMessage($test_message);
echo "<p><strong>Original:</strong> " . htmlspecialchars($test_message) . "</p>";
echo "<p><strong>Formatted:</strong> " . htmlspecialchars($formatted) . "</p>";
echo "<p><strong>Character count:</strong> " . strlen($formatted) . " characters</p>";
echo "<hr>";

// Test 2: Get SMS configuration
echo "<h3>2. SMS Configuration:</h3>";
$sms_config = getSMSConfig($pdo);
if ($sms_config) {
    echo "<p>Provider: " . htmlspecialchars($sms_config['provider'] ?? 'IPROG SMS') . "</p>";
    echo "<p>Status: " . htmlspecialchars($sms_config['status'] ?? 'unknown') . "</p>";
    echo "<p>API Key: " . substr($sms_config['api_key'] ?? '', 0, 10) . "..." . "</p>";
} else {
    echo "<p style='color:red'>SMS not configured!</p>";
    exit;
}
echo "<hr>";

// Test 3: Send actual test SMS
echo "<h3>3. Sending Test SMS:</h3>";

// Get a test phone number - you can change this to your phone number
$test_phone = ""; // Leave empty to use a parent's phone from database

if (empty($test_phone)) {
    // Get a parent phone number from database for testing
    $stmt = $pdo->prepare("
        SELECT u.phone, u.full_name, s.full_name as student_name, s.id as student_id
        FROM users u 
        JOIN student_parents sp ON u.id = sp.parent_id 
        JOIN users s ON sp.student_id = s.id
        WHERE u.phone IS NOT NULL AND u.phone != '' AND u.role = 'parent'
        ORDER BY sp.is_primary DESC
        LIMIT 1
    ");
    $stmt->execute();
    $test_parent = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($test_parent) {
        $test_phone = $test_parent['phone'];
        echo "<p>Using parent: " . htmlspecialchars($test_parent['full_name']) . "</p>";
        echo "<p>Student: " . htmlspecialchars($test_parent['student_name']) . "</p>";
        echo "<p>Phone: " . htmlspecialchars($test_phone) . "</p>";
    } else {
        echo "<p style='color:red'>No parent with phone number found in database!</p>";
        exit;
    }
}

// Prepare test message (shortened format)
$current_time = date('g:i A');
$current_date = date('F j, Y');
$test_sms_message = "TEST: This is a test notification at {$current_time}, {$current_date}.";

echo "<p><strong>Message to send:</strong> " . htmlspecialchars($test_sms_message) . "</p>";
echo "<p><strong>After formatting:</strong> " . htmlspecialchars(formatSMSMessage($test_sms_message)) . "</p>";

// Confirm before sending
if (!isset($_GET['confirm'])) {
    echo "<br><p style='color:orange'><strong>⚠️ This will send a real SMS and deduct 1 credit!</strong></p>";
    echo "<a href='?confirm=1' style='padding: 10px 20px; background: #4CAF50; color: white; text-decoration: none; border-radius: 5px;'>Click here to confirm and send test SMS</a>";
    echo "<hr>";
    exit;
}

// Send the SMS
echo "<br><p>Sending SMS...</p>";

$result = sendSMSUsingIPROG($test_phone, $test_sms_message, $sms_config['api_key']);

echo "<h4>Result:</h4>";
echo "<pre>" . print_r($result, true) . "</pre>";

if ($result['success']) {
    echo "<p style='color:green'><strong>✅ SMS sent successfully!</strong></p>";
    echo "<p>Your credits should now be reduced by 1.</p>";
    echo "<p>Reference ID: " . ($result['reference_id'] ?? 'N/A') . "</p>";
} else {
    echo "<p style='color:red'><strong>❌ SMS failed to send!</strong></p>";
    echo "<p>Error: " . htmlspecialchars($result['message']) . "</p>";
}

// Log to database
try {
    $status = $result['success'] ? 'sent' : 'failed';
    $stmt = $pdo->prepare("
        INSERT INTO sms_logs 
        (phone_number, message, status, notification_type, response, reference_id, sent_at)
        VALUES (?, ?, ?, 'test', ?, ?, NOW())
    ");
    $stmt->execute([
        $test_phone,
        formatSMSMessage($test_sms_message),
        $status,
        $result['message'],
        $result['reference_id'] ?? null
    ]);
    echo "<p>SMS logged to database.</p>";
} catch (PDOException $e) {
    echo "<p style='color:orange'>Warning: Could not log SMS - " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><a href='test-sms-template.php'>Run another test</a></p>";
?>
