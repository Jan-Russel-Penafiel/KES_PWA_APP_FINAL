<?php
/**
 * Test different SMS formats to find what IPROG accepts
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';
require_once 'sms_functions.php';

echo "<h2>IPROG SMS Format Tester</h2>";

// Get SMS config
$sms_config = getSMSConfig($pdo);
if (!$sms_config || empty($sms_config['api_key'])) {
    die("SMS not configured");
}

$api_key = $sms_config['api_key'];

// Test phone number
$test_phone = "09676402632";

// Different message formats to test
$test_messages = [
    "Format 1 (Simple)" => "Test notification sent on January 4, 2026.",
    "Format 2 (Name)" => "John Doe has arrived at school.",
    "Format 3 (Teacher)" => "Teacher John Doe is absent today.",
    "Format 4 (With @)" => "test@teacher will be absent today.",
    "Format 5 (No @)" => "Teacher Smith will be absent today, January 4, 2026.",
];

if (isset($_GET['test'])) {
    $test_key = $_GET['test'];
    if (isset($test_messages[$test_key])) {
        $raw_message = $test_messages[$test_key];
        $formatted = formatSMSMessage($raw_message);
        
        echo "<h3>Testing: {$test_key}</h3>";
        echo "<p><strong>Raw:</strong> " . htmlspecialchars($raw_message) . "</p>";
        echo "<p><strong>Formatted:</strong> " . htmlspecialchars($formatted) . "</p>";
        echo "<p><strong>Length:</strong> " . strlen($formatted) . " characters</p>";
        
        echo "<p>Sending SMS...</p>";
        
        $result = sendSMSUsingIPROG($test_phone, $raw_message, $api_key);
        
        echo "<h4>Result:</h4>";
        echo "<pre>" . print_r($result, true) . "</pre>";
        
        if ($result['success']) {
            echo "<p style='color:green'><strong>✅ SUCCESS!</strong></p>";
        } else {
            echo "<p style='color:red'><strong>❌ FAILED: " . htmlspecialchars($result['message']) . "</strong></p>";
        }
    }
}

echo "<hr><h3>Available Tests (each will use 1 credit if successful):</h3>";
echo "<ul>";
foreach ($test_messages as $key => $msg) {
    $formatted = formatSMSMessage($msg);
    echo "<li>";
    echo "<strong>{$key}:</strong><br>";
    echo "Raw: " . htmlspecialchars($msg) . "<br>";
    echo "Formatted: " . htmlspecialchars($formatted) . "<br>";
    echo "<a href='?test=" . urlencode($key) . "' style='color:blue;'>Test this format</a>";
    echo "</li><br>";
}
echo "</ul>";
?>
