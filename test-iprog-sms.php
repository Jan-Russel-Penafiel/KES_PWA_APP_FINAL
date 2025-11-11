<?php
/**
 * iProgsms API Test Script
 * This script tests the iProgsms API directly with the provided example code
 */

// Include config for database connection if needed
require_once 'config.php';

// iProgsms API endpoint
$url = 'https://sms.iprogtech.com/api/v1/sms_messages';

// Sample data
$firstName = "John";
$lastName  = "Doe";
$message = sprintf("Hi %s %s", $firstName, $lastName);

// API data with your actual API token
$data = [
    'api_token' => '1ef3b27ea753780a90cbdf07d027fb7b52791004',  // Your actual API token
    'message' => $message,
    'phone_number' => '639677726912'  // Change this to your test phone number
];

echo "<h2>iProgsms API Test</h2>";
echo "<p><strong>Testing with:</strong></p>";
echo "<ul>";
echo "<li>API URL: " . $url . "</li>";
echo "<li>Phone Number: " . $data['phone_number'] . "</li>";
echo "<li>Message: " . htmlspecialchars($data['message']) . "</li>";
echo "<li>API Token: " . substr($data['api_token'], 0, 10) . "..." . "</li>";
echo "</ul>";

echo "<hr>";
echo "<p><strong>Sending SMS...</strong></p>";

// Initialize cURL
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/x-www-form-urlencoded'
]);

// Set timeout options
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

// Execute the request
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "<h3>Response Details:</h3>";

if ($error) {
    echo "<p style='color: red;'><strong>cURL Error:</strong> " . htmlspecialchars($error) . "</p>";
} else {
    echo "<p><strong>HTTP Status Code:</strong> " . $httpCode . "</p>";
    echo "<p><strong>Raw Response:</strong></p>";
    echo "<pre style='background: #f5f5f5; padding: 10px; border: 1px solid #ddd;'>";
    echo htmlspecialchars($response);
    echo "</pre>";
    
    // Try to decode JSON response
    $jsonResponse = json_decode($response, true);
    if ($jsonResponse) {
        echo "<p><strong>Parsed JSON Response:</strong></p>";
        echo "<pre style='background: #e8f5e8; padding: 10px; border: 1px solid #4CAF50;'>";
        print_r($jsonResponse);
        echo "</pre>";
        
        // Check for success/failure
        if (isset($jsonResponse['status'])) {
            // iProgsms returns status 200 for success, or check for successful message
            if ($jsonResponse['status'] == 200 || 
                $jsonResponse['status'] === 'success' || 
                $jsonResponse['status'] === 'sent' ||
                (isset($jsonResponse['message']) && strpos($jsonResponse['message'], 'successfully queued') !== false)) {
                echo "<p style='color: green; font-weight: bold;'>✅ SMS sent successfully!</p>";
                if (isset($jsonResponse['message_id'])) {
                    echo "<p><strong>Message ID:</strong> " . htmlspecialchars($jsonResponse['message_id']) . "</p>";
                }
                if (isset($jsonResponse['sms_rate'])) {
                    echo "<p><strong>SMS Rate:</strong> ₱" . htmlspecialchars($jsonResponse['sms_rate']) . "</p>";
                }
            } else {
                echo "<p style='color: red; font-weight: bold;'>❌ SMS failed to send</p>";
                if (isset($jsonResponse['message'])) {
                    echo "<p><strong>Error Message:</strong> " . htmlspecialchars($jsonResponse['message']) . "</p>";
                }
            }
        }
    } else {
        echo "<p><strong>Note:</strong> Response is not valid JSON or is empty</p>";
    }
}

echo "<hr>";
echo "<p><a href='sms-config.php'>← Back to SMS Configuration</a></p>";
echo "<p><a href='dashboard.php'>← Back to Dashboard</a></p>";

// Log the test attempt
try {
    $stmt = $pdo->prepare("INSERT INTO sms_logs (phone_number, message, status, response, sent_at) VALUES (?, ?, ?, ?, NOW())");
    $status = ($error || $httpCode !== 200) ? 'failed' : 'sent';
    $logResponse = $error ? "cURL Error: $error" : $response;
    $stmt->execute([$data['phone_number'], $data['message'], $status, $logResponse]);
    echo "<p style='color: blue;'><em>✅ Test logged to database successfully</em></p>";
} catch (Exception $e) {
    echo "<p style='color: orange;'><em>Could not log to database: " . htmlspecialchars($e->getMessage()) . "</em></p>";
}
?>