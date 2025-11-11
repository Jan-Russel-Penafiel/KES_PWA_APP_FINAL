<?php
require_once 'config.php';

echo "<h1>IPROG SMS Database Update</h1>";

try {
    // Update existing SMS configuration to use IPROG SMS
    $stmt = $pdo->prepare("
        UPDATE sms_config 
        SET 
            provider_name = 'IPROG SMS',
            api_url = 'https://sms.iprogtech.com/api/v1/sms_messages',
            api_key = '1ef3b27ea753780a90cbdf07d027fb7b52791004',
            sender_name = 'KES-SMART',
            status = 'active'
        WHERE id = 1
    ");
    
    $stmt->execute();
    
    echo "<div style='background-color: #d4edda; border: 1px solid #c3e6cb; padding: 10px; margin: 10px 0;'>";
    echo "<strong>✅ Success!</strong><br>";
    echo "SMS configuration updated successfully to use IPROG SMS API.<br>";
    echo "Provider: IPROG SMS<br>";
    echo "API URL: https://sms.iprogtech.com/api/v1/sms_messages<br>";
    echo "Status: Active";
    echo "</div>";
    
    // Verify the update
    $updated_config = $pdo->query("SELECT * FROM sms_config WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
    
    if ($updated_config) {
        echo "<h2>Current Configuration:</h2>";
        echo "<table border='1' cellpadding='8' cellspacing='0' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><td><strong>Provider Name:</strong></td><td>" . htmlspecialchars($updated_config['provider_name']) . "</td></tr>";
        echo "<tr><td><strong>API URL:</strong></td><td>" . htmlspecialchars($updated_config['api_url']) . "</td></tr>";
        echo "<tr><td><strong>API Key:</strong></td><td>" . substr($updated_config['api_key'], 0, 10) . "..." . substr($updated_config['api_key'], -10) . "</td></tr>";
        echo "<tr><td><strong>Sender Name:</strong></td><td>" . htmlspecialchars($updated_config['sender_name']) . "</td></tr>";
        echo "<tr><td><strong>Status:</strong></td><td><span style='color: " . ($updated_config['status'] === 'active' ? 'green' : 'red') . ";'>" . ucfirst($updated_config['status']) . "</span></td></tr>";
        echo "</table>";
    }
    
} catch (PDOException $e) {
    echo "<div style='background-color: #f8d7da; border: 1px solid #f5c6cb; padding: 10px; margin: 10px 0;'>";
    echo "<strong>❌ Error updating database:</strong><br>";
    echo htmlspecialchars($e->getMessage());
    echo "</div>";
}

echo "<h2>Next Steps:</h2>";
echo "<ol>";
echo "<li><a href='sms-config.php'>Go to SMS Configuration page</a> to verify settings</li>";
echo "<li><a href='test-iprog-sms.php'>Run SMS test</a> to verify functionality</li>";
echo "<li>Test SMS functionality from the admin panel</li>";
echo "<li>Remove this update file after successful migration</li>";
echo "</ol>";

echo "<p><a href='sms-config.php' style='background-color: #007bff; color: white; padding: 8px 16px; text-decoration: none; border-radius: 4px;'>Go to SMS Configuration</a></p>";
?>