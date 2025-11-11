<?php
// Include database connection
require_once "config.php";

/**
 * Send SMS using IPROG SMS API
 * @param string $phone_number Recipient phone number
 * @param string $message SMS message content
 * @param string $api_key IPROG SMS API token
 * @return array Response with status and message
 */
function sendSMSUsingIPROG($phone_number, $message, $api_key) {
    // Prepare the phone number (remove any spaces and ensure 63 format for IPROG)
    $phone_number = str_replace([' ', '-'], '', $phone_number);
    if (substr($phone_number, 0, 1) === '0') {
        $phone_number = '63' . substr($phone_number, 1);
    } elseif (substr($phone_number, 0, 1) === '+') {
        $phone_number = substr($phone_number, 1);
    }

    // Validate phone number format
    if (!preg_match('/^63[0-9]{10}$/', $phone_number)) {
        return array(
            'success' => false,
            'message' => 'Invalid phone number format. Must be a valid Philippine mobile number.'
        );
    }

    // Prepare the request data for IPROG SMS API
    $data = array(
        'api_token' => $api_key,
        'message' => $message,
        'phone_number' => $phone_number
    );

    // Initialize cURL session
    $ch = curl_init("https://sms.iprogtech.com/api/v1/sms_messages");

    // Set cURL options for IPROG SMS
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($data),
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/x-www-form-urlencoded'
        ),
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true
    ));

    // Execute cURL request
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    $curl_errno = curl_errno($ch);

    // Close cURL session
    curl_close($ch);

    // Log the API request for debugging
    error_log(sprintf(
        "IPROG SMS API Request - Number: %s, Status: %d, Response: %s, Error: %s",
        $phone_number,
        $http_code,
        $response,
        $curl_error
    ));

    // Handle cURL errors
    if ($curl_errno) {
        return array(
            'success' => false,
            'message' => 'Connection error: ' . $curl_error,
            'error_code' => $curl_errno
        );
    }

    // Parse response
    $result = json_decode($response, true);

    // Handle API response for IPROG SMS
    if ($http_code === 200 || $http_code === 201) {
        // IPROG SMS typically returns success in different formats
        // Check for common success indicators
        if ((isset($result['status']) && $result['status'] === 'success') ||
            (isset($result['success']) && $result['success'] === true) ||
            (isset($result['message']) && stripos($result['message'], 'sent') !== false) ||
            (!isset($result['error']) && !isset($result['errors']))) {
            return array(
                'success' => true,
                'message' => 'SMS sent successfully',
                'reference_id' => $result['message_id'] ?? $result['id'] ?? $result['reference'] ?? null,
                'delivery_status' => $result['status'] ?? 'Sent',
                'timestamp' => $result['timestamp'] ?? date('Y-m-d g:i A')
            );
        }
    }

    // Handle error responses
    $error_message = isset($result['message']) ? $result['message'] : 
                    (isset($result['error']) ? $result['error'] : 
                    (isset($result['errors']) ? (is_array($result['errors']) ? implode(', ', $result['errors']) : $result['errors']) : 'Unknown error occurred'));
    
    return array(
        'success' => false,
        'message' => 'API Error: ' . $error_message,
        'error_code' => $http_code,
        'error_details' => $result
    );
}

/**
 * Send SMS notification and store in database
 * @param int $student_id Recipient student ID (for getting parent contact)
 * @param string $message SMS message content
 * @param string $notification_type Type of notification
 * @param string $scheduled_at Optional scheduled datetime
 * @return array Response with status and message
 */
function sendSMSNotificationToParent($student_id, $message, $notification_type = 'attendance', $scheduled_at = null) {
    global $pdo;

    try {
        // Get student's parent phone number
        $stmt = $pdo->prepare("
            SELECT u.phone, u.full_name as parent_name, s.full_name as student_name
            FROM users u 
            JOIN student_parents sp ON u.id = sp.parent_id 
            JOIN users s ON sp.student_id = s.id
            WHERE sp.student_id = ? AND sp.is_primary = 1 AND u.phone IS NOT NULL
        ");
        $stmt->execute([$student_id]);
        $parent = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$parent || empty($parent['phone'])) {
            return array(
                'success' => false,
                'message' => 'Invalid student or missing parent phone number'
            );
        }

        // Get SMS API key from config
        $sms_config = getSMSConfig($pdo);
        if (!$sms_config || $sms_config['status'] != 'active') {
            return array(
                'success' => false,
                'message' => 'SMS service is not active'
            );
        }

        $api_key = $sms_config['api_key'];
        if (empty($api_key)) {
            return array(
                'success' => false,
                'message' => 'SMS API key not configured'
            );
        }

        // If scheduled for future, just store in database
        if (!empty($scheduled_at) && strtotime($scheduled_at) > time()) {
                    $sent_at = date('Y-m-d g:i:s A');
        
        $stmt = $pdo->prepare("
            INSERT INTO sms_logs 
            (phone_number, message, status, notification_type, scheduled_at, sent_at)
            VALUES (?, ?, 'pending', ?, ?, ?)
        ");
            $stmt->execute([
                $parent['phone'],
                $message,
                $notification_type,
                $scheduled_at,
                $sent_at
            ]);

            return array(
                'success' => true,
                'message' => 'SMS scheduled successfully'
            );
        }

        // Send SMS using IPROG SMS
        $sms_result = sendSMSUsingIPROG($parent['phone'], $message, $api_key);

        // Store in database
        $status = $sms_result['success'] ? 'sent' : 'failed';
        $stmt = $pdo->prepare("
            INSERT INTO sms_logs 
            (phone_number, message, status, notification_type, response, reference_id, sent_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $error_message = $sms_result['success'] ? $sms_result['message'] : $sms_result['message'];
        $reference_id = $sms_result['success'] ? ($sms_result['reference_id'] ?? null) : null;
        
        // Format timestamp in 12-hour format for database
        $sent_at = date('Y-m-d g:i:s A');
        
        $stmt = $pdo->prepare("
            INSERT INTO sms_logs 
            (phone_number, message, status, notification_type, response, reference_id, sent_at)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $parent['phone'],
            $message,
            $status,
            $notification_type,
            $error_message,
            $reference_id,
            $sent_at
        ]);

        return $sms_result;

    } catch (Exception $e) {
        return array(
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        );
    }
}

/**
 * Process scheduled SMS notifications
 * This should be run by a cron job every minute
 */
function processScheduledSMS() {
    global $pdo;

    try {
        // Get SMS API key
        $sms_config = getSMSConfig($pdo);
        if (!$sms_config || $sms_config['status'] != 'active') {
            throw new Exception('SMS service is not active');
        }

        $api_key = $sms_config['api_key'];
        if (empty($api_key)) {
            throw new Exception('SMS API key not configured');
        }

        // Get due scheduled SMS
        $stmt = $pdo->prepare("
            SELECT id, phone_number, message, notification_type
            FROM sms_logs
            WHERE status = 'pending'
            AND scheduled_at <= NOW()
        ");
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($result as $sms) {
            // Send SMS using IPROG SMS
            $sms_result = sendSMSUsingIPROG($sms['phone_number'], $sms['message'], $api_key);

            // Update status
            $status = $sms_result['success'] ? 'sent' : 'failed';
            $sent_at = date('Y-m-d g:i:s A');
            
            $stmt = $pdo->prepare("
                UPDATE sms_logs
                SET status = ?,
                    response = ?,
                    reference_id = ?,
                    sent_at = ?
                WHERE id = ?
            ");
            $error_message = $sms_result['success'] ? $sms_result['message'] : $sms_result['message'];
            $reference_id = $sms_result['success'] ? ($sms_result['reference_id'] ?? null) : null;
            $stmt->execute([
                $status,
                $error_message,
                $reference_id,
                $sent_at,
                $sms['id']
            ]);
        }

    } catch (Exception $e) {
        error_log('Error processing scheduled SMS: ' . $e->getMessage());
    }
}
?>
