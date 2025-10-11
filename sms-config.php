<?php
require_once 'config.php';

// Check if user is logged in and is admin
requireRole('admin');

$current_user = getCurrentUser($pdo);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if ($action == 'update_sms_config') {
            $provider_name = sanitize_input($_POST['provider_name']);
            $api_url = sanitize_input($_POST['api_url']);
            $api_key = sanitize_input($_POST['api_key']);
            $sender_name = sanitize_input($_POST['sender_name']);
            $status = sanitize_input($_POST['status']);
            
            try {
                // Update or insert SMS configuration
                $stmt = $pdo->prepare("
                    INSERT INTO sms_config (id, provider_name, api_url, api_key, sender_name, status) 
                    VALUES (1, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE 
                    provider_name = VALUES(provider_name),
                    api_url = VALUES(api_url),
                    api_key = VALUES(api_key),
                    sender_name = VALUES(sender_name),
                    status = VALUES(status)
                ");
                $stmt->execute([$provider_name, $api_url, $api_key, $sender_name, $status]);
                
                $_SESSION['success'] = 'SMS configuration updated successfully!';
            } catch(PDOException $e) {
                $_SESSION['error'] = 'Failed to update SMS configuration: ' . $e->getMessage();
            }
            
        } elseif ($action == 'test_sms') {
            $test_phone = sanitize_input($_POST['test_phone']);
            $test_message = sanitize_input($_POST['test_message']);
            
            // Get current SMS configuration
            try {
                $sms_config = $pdo->query("SELECT * FROM sms_config WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
                
                if ($sms_config && $sms_config['status'] == 'active') {
                    // Validate required fields
                    if (empty($sms_config['api_key']) || empty($sms_config['api_url'])) {
                        $_SESSION['error'] = 'SMS configuration is incomplete. Please fill in all required fields.';
                    } else {
                        // Test SMS sending
                        $success = sendTestSMS($test_phone, $test_message, $sms_config, $pdo);
                        
                        if ($success) {
                            $_SESSION['success'] = 'Test SMS sent successfully! Check the SMS logs for delivery status.';
                        } else {
                            // Get the last SMS log entry to show the error
                            try {
                                $last_log = $pdo->query("SELECT response FROM sms_logs ORDER BY sent_at DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
                                $error_detail = $last_log ? $last_log['response'] : 'Unknown error';
                                $_SESSION['error'] = 'Failed to send test SMS. Error: ' . $error_detail;
                            } catch(PDOException $e) {
                                $_SESSION['error'] = 'Failed to send test SMS. Please check your configuration and try again.';
                            }
                        }
                    }
                } else {
                    $_SESSION['error'] = 'SMS configuration is not active or not found. Please activate SMS configuration first.';
                }
            } catch(PDOException $e) {
                $_SESSION['error'] = 'Error accessing SMS configuration: ' . $e->getMessage();
            }
            
        } elseif ($action == 'add_template') {
            $template_name = sanitize_input($_POST['template_name']);
            $template_message = sanitize_input($_POST['template_message']);
            $template_type = sanitize_input($_POST['template_type']);
            
            // For simplicity, store templates in a JSON file
            $templates_file = 'sms_templates.json';
            $templates = [];
            
            if (file_exists($templates_file)) {
                $templates = json_decode(file_get_contents($templates_file), true) ?: [];
            }
            
            $templates[] = [
                'id' => uniqid(),
                'name' => $template_name,
                'message' => $template_message,
                'type' => $template_type,
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            if (file_put_contents($templates_file, json_encode($templates, JSON_PRETTY_PRINT))) {
                $_SESSION['success'] = 'SMS template added successfully!';
            } else {
                $_SESSION['error'] = 'Failed to add SMS template.';
            }
        }
    }
    
    redirect('sms-config.php');
}

$page_title = 'SMS Configuration';
include 'header.php';

// Get current SMS configuration
try {
    $sms_config = $pdo->query("SELECT * FROM sms_config WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
    if (!$sms_config) {
        $sms_config = [
            'provider_name' => 'PhilSMS',
            'api_url' => 'https://app.philsms.com/api/v3/sms/send',
            'api_key' => '2100|J9BVGEx9FFOJAbHV0xfn6SMOkKBt80HTLjHb6zZX',
            'sender_name' => 'KES-SMART',
            'status' => 'inactive'
        ];
    }
} catch(PDOException $e) {
    $sms_config = [
        'provider_name' => 'PhilSMS',
        'api_url' => 'https://app.philsms.com/api/v3/sms/send',
        'api_key' => '2100|J9BVGEx9FFOJAbHV0xfn6SMOkKBt80HTLjHb6zZX',
        'sender_name' => 'KES-SMART',
        'status' => 'inactive'
    ];
}

// Get SMS statistics
try {
    $sms_stats = [
        'total_sent' => $pdo->query("SELECT COUNT(*) FROM sms_logs WHERE status = 'sent'")->fetchColumn(),
        'total_failed' => $pdo->query("SELECT COUNT(*) FROM sms_logs WHERE status = 'failed'")->fetchColumn(),
        'total_pending' => $pdo->query("SELECT COUNT(*) FROM sms_logs WHERE status = 'pending'")->fetchColumn(),
        'today_sent' => $pdo->query("SELECT COUNT(*) FROM sms_logs WHERE status = 'sent' AND DATE(sent_at) = CURDATE()")->fetchColumn(),
        'this_month' => $pdo->query("SELECT COUNT(*) FROM sms_logs WHERE status = 'sent' AND MONTH(sent_at) = MONTH(CURDATE())")->fetchColumn()
    ];
} catch(PDOException $e) {
    $sms_stats = [
        'total_sent' => 0,
        'total_failed' => 0,
        'total_pending' => 0,
        'today_sent' => 0,
        'this_month' => 0
    ];
}

// Get recent SMS logs
try {
    $recent_sms = $pdo->query("
        SELECT phone_number, message, status, sent_at 
        FROM sms_logs 
        ORDER BY sent_at DESC 
        LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $recent_sms = [];
}

// Get SMS templates
$templates = [];
if (file_exists('sms_templates.json')) {
    $templates = json_decode(file_get_contents('sms_templates.json'), true) ?: [];
}

// Function to send test SMS
function sendTestSMS($phone, $message, $config, $pdo) {
    try {
        // Use the new PhilSMS implementation
        $result = sendSMSUsingPhilSMS($phone, $message, $config['api_key']);
        
        // Format phone number for logging (same as in the new implementation)
        $formatted_phone = str_replace([' ', '-'], '', $phone);
        if (substr($formatted_phone, 0, 1) === '0') {
            $formatted_phone = '63' . substr($formatted_phone, 1);
        } elseif (substr($formatted_phone, 0, 1) === '+') {
            $formatted_phone = substr($formatted_phone, 1);
        }
        
        // Log SMS attempt with detailed response
        try {
            $status = $result['success'] ? 'sent' : 'failed';
            $stmt = $pdo->prepare("INSERT INTO sms_logs (phone_number, message, response, status, sent_at) VALUES (?, ?, ?, ?, NOW())");
            $log_response = $result['message'];
            $stmt->execute([$formatted_phone, $message, $log_response, $status]);
        } catch(PDOException $db_e) {
            // Ignore database errors in error handling
        }
        
        return $result['success'];
    } catch(Exception $e) {
        // If that fails, log a failed attempt
        try {
            $stmt = $pdo->prepare("INSERT INTO sms_logs (phone_number, message, status, response, sent_at) VALUES (?, ?, 'failed', ?, NOW())");
            $stmt->execute([$phone, $message, 'Exception: ' . $e->getMessage()]);
        } catch(PDOException $db_e) {
            // Ignore database errors in error handling
        }
        return false;
    }
}
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 fw-bold text-primary">
                    <i class="fas fa-sms me-2"></i>SMS Configuration
                </h1>
                <p class="text-muted mb-0">Configure SMS settings and manage message templates</p>
            </div>
            <div class="text-end">
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#templateModal">
                    <i class="fas fa-plus me-2"></i>Add Template
                </button>
                <div class="small text-muted mt-1">
                    Status: 
                    <span class="badge bg-<?php echo $sms_config['status'] == 'active' ? 'success' : 'danger'; ?>">
                        <?php echo ucfirst($sms_config['status']); ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- SMS Statistics -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-2">
        <div class="card bg-primary text-white h-100">
            <div class="card-body text-center p-3">
                <h4 class="fw-bold mb-1"><?php echo number_format($sms_stats['total_sent']); ?></h4>
                <p class="mb-0 small">Total Sent</p>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="card bg-success text-white h-100">
            <div class="card-body text-center p-3">
                <h4 class="fw-bold mb-1"><?php echo number_format($sms_stats['today_sent']); ?></h4>
                <p class="mb-0 small">Today</p>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="card bg-info text-white h-100">
            <div class="card-body text-center p-3">
                <h4 class="fw-bold mb-1"><?php echo number_format($sms_stats['this_month']); ?></h4>
                <p class="mb-0 small">This Month</p>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="card bg-warning text-white h-100">
            <div class="card-body text-center p-3">
                <h4 class="fw-bold mb-1"><?php echo number_format($sms_stats['total_pending']); ?></h4>
                <p class="mb-0 small">Pending</p>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="card bg-danger text-white h-100">
            <div class="card-body text-center p-3">
                <h4 class="fw-bold mb-1"><?php echo number_format($sms_stats['total_failed']); ?></h4>
                <p class="mb-0 small">Failed</p>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-2">
        <div class="card bg-secondary text-white h-100">
            <div class="card-body text-center p-3">
                <h4 class="fw-bold mb-1"><?php echo count($templates); ?></h4>
                <p class="mb-0 small">Templates</p>
            </div>
        </div>
    </div>
</div>

<!-- Configuration Tabs -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <ul class="nav nav-tabs card-header-tabs flex-nowrap overflow-auto" id="smsConfigTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="config-tab" data-bs-toggle="tab" data-bs-target="#config" type="button" role="tab">
                            <i class="fas fa-cog me-2"></i><span class="d-none d-sm-inline">Configuration</span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="templates-tab" data-bs-toggle="tab" data-bs-target="#templates" type="button" role="tab">
                            <i class="fas fa-file-alt me-2"></i><span class="d-none d-sm-inline">Templates</span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="logs-tab" data-bs-toggle="tab" data-bs-target="#logs" type="button" role="tab">
                            <i class="fas fa-history me-2"></i><span class="d-none d-sm-inline">SMS Logs</span>
                        </button>
                    </li>
                </ul>
            </div>
            <div class="card-body p-0 p-sm-3">
                <div class="tab-content" id="smsConfigTabsContent">
                    <!-- Configuration Tab -->
                    <div class="tab-pane fade show active" id="config" role="tabpanel">
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="update_sms_config">
                            
                            <div class="row g-3">
                                <div class="col-12">
                                    <div class="mb-3">
                                        <label for="provider_name" class="form-label">Provider Name</label>
                                        <input type="text" class="form-control" id="provider_name" name="provider_name" 
                                               value="<?php echo htmlspecialchars($sms_config['provider_name']); ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="api_url" class="form-label">API URL</label>
                                        <input type="url" class="form-control" id="api_url" name="api_url" 
                                               value="<?php echo htmlspecialchars($sms_config['api_url']); ?>" required
                                               placeholder="https://api.provider.com/v1/send">
                                        <div class="form-text">The endpoint URL for your SMS service provider</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="api_key" class="form-label">API Key</label>
                                        <div class="input-group">
                                            <input type="password" class="form-control" id="api_key" name="api_key" 
                                                   value="<?php echo htmlspecialchars($sms_config['api_key']); ?>" required
                                                   placeholder="Enter your SMS API key">
                                            <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility('api_key')">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                        <div class="form-text">
                                            Your SMS provider API key or token<br>
                                            <small class="text-info"><i class="fas fa-info-circle"></i> For PhilSMS v3 API, use the Bearer token format</small>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="sender_name" class="form-label">Sender Name</label>
                                        <input type="text" class="form-control" id="sender_name" name="sender_name" 
                                               value="<?php echo htmlspecialchars($sms_config['sender_name']); ?>" required
                                               placeholder="KES-SMART" maxlength="11">
                                        <div class="form-text">Maximum 11 characters for sender name</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="status" class="form-label">Status</label>
                                        <select class="form-select" id="status" name="status" required>
                                            <option value="active" <?php echo $sms_config['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                            <option value="inactive" <?php echo $sms_config['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                        </select>
                                    </div>
                                    
                                    <div class="text-end">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save me-2"></i>Save Configuration
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Templates Tab -->
                    <div class="tab-pane fade" id="templates" role="tabpanel">
                        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-center mb-3 gap-2">
                            <h6 class="mb-0">SMS Templates</h6>
                            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#templateModal">
                                <i class="fas fa-plus me-2"></i>Add Template
                            </button>
                        </div>
                        
                        <?php if (empty($templates)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-file-alt fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted mb-2">No Templates Found</h5>
                                <p class="text-muted small mb-3">Create your first SMS template to get started.</p>
                                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#templateModal">
                                    <i class="fas fa-plus me-2"></i>Create Template
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="row g-3">
                                <?php foreach ($templates as $template): ?>
                                    <div class="col-12 col-sm-6 col-lg-4">
                                        <div class="card h-100">
                                            <div class="card-body p-3">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <h6 class="card-title mb-0 text-truncate pe-2"><?php echo htmlspecialchars($template['name']); ?></h6>
                                                    <span class="badge bg-<?php 
                                                        echo $template['type'] == 'attendance' ? 'primary' : 
                                                             ($template['type'] == 'alert' ? 'danger' : 'success'); 
                                                    ?>">
                                                        <?php echo ucfirst($template['type']); ?>
                                                    </span>
                                                </div>
                                                <p class="card-text small text-muted mb-3"><?php echo htmlspecialchars($template['message']); ?></p>
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <small class="text-muted">
                                                        <?php echo date('M j, Y', strtotime($template['created_at'])); ?>
                                                    </small>
                                                    <div class="btn-group btn-group-sm">
                                                        <button class="btn btn-outline-primary" onclick="useTemplate('<?php echo addslashes($template['message']); ?>')">
                                                            <i class="fas fa-copy"></i>
                                                        </button>
                                                        <button class="btn btn-outline-danger" onclick="deleteTemplate('<?php echo $template['id']; ?>')">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- SMS Logs Tab -->
                    <div class="tab-pane fade" id="logs" role="tabpanel">
                        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-center mb-3 gap-2">
                            <h6 class="mb-0">Recent SMS Activity</h6>
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-secondary active" data-filter="all">All</button>
                                <button class="btn btn-outline-secondary" data-filter="sent">Sent</button>
                                <button class="btn btn-outline-secondary" data-filter="failed">Failed</button>
                                <button class="btn btn-outline-secondary" data-filter="pending">Pending</button>
                            </div>
                        </div>
                        
                        <?php if (empty($recent_sms)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted mb-2">No SMS Logs Found</h5>
                                <p class="text-muted small">SMS activity will appear here once messages are sent.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead>
                                        <tr>
                                            <th>Phone</th>
                                            <th>Message</th>
                                            <th>Status</th>
                                            <th>Sent At</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_sms as $sms): ?>
                                            <tr data-status="<?php echo $sms['status']; ?>">
                                                <td>
                                                    <code class="small"><?php echo substr($sms['phone_number'], 0, 7) . '***'; ?></code>
                                                </td>
                                                <td>
                                                    <div class="text-truncate" style="max-width: 200px;">
                                                        <small><?php echo htmlspecialchars(substr($sms['message'], 0, 50)); ?></small>
                                                        <?php if (strlen($sms['message']) > 50): ?>
                                                            <span class="text-muted">...</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        echo $sms['status'] == 'sent' ? 'success' : 
                                                             ($sms['status'] == 'failed' ? 'danger' : 'warning'); 
                                                    ?>">
                                                        <?php echo ucfirst($sms['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <small class="text-muted"><?php echo date('M j, g:i A', strtotime($sms['sent_at'])); ?></small>
                                                </td>
                                                <td class="text-end">
                                                    <button class="btn btn-sm btn-outline-primary" 
                                                            onclick="viewSmsDetails('<?php echo addslashes($sms['message']); ?>', '<?php echo $sms['phone_number']; ?>', '<?php echo $sms['sent_at']; ?>')">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Test SMS Modal -->
<div class="modal fade" id="testSmsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-paper-plane me-2"></i>Test SMS
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="test_sms">
                    
                    <div class="mb-3">
                        <label for="test_phone" class="form-label">Phone Number</label>
                        <input type="text" class="form-control" id="test_phone" name="test_phone" 
                               placeholder="+639123456789" required>
                        <div class="form-text">Include country code (e.g., +63 for Philippines)</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="test_message" class="form-label">Test Message</label>
                        <textarea class="form-control" id="test_message" name="test_message" rows="4" required 
                                  maxlength="160">Hello from KES-SMART! This is a test message to verify SMS configuration.</textarea>
                        <div class="d-flex justify-content-between">
                            <div class="form-text">Maximum 160 characters</div>
                            <small class="text-muted"><span id="char-count">0</span>/160</small>
                        </div>
                    </div>
                    
                    <!-- Configuration Warnings -->
                    <?php if ($sms_config['status'] !== 'active'): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            SMS configuration is not active. Please activate it first to send test messages.
                        </div>
                    <?php elseif (empty($sms_config['api_key'])): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            API key is not configured. Please set your SMS provider API key first.
                        </div>
                    <?php elseif (empty($sms_config['api_url'])): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            API URL is not configured. Please set your SMS provider API URL first.
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            This will send a test SMS using your configured provider: <strong><?php echo htmlspecialchars($sms_config['provider_name']); ?></strong>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <?php 
                    $can_test = $sms_config['status'] == 'active' && 
                               !empty($sms_config['api_key']) && 
                               !empty($sms_config['api_url']); 
                    ?>
                    <button type="submit" class="btn btn-success" 
                            <?php echo !$can_test ? 'disabled' : ''; ?>>
                        <i class="fas fa-paper-plane me-2"></i>Send Test SMS
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Template Modal -->
<div class="modal fade" id="templateModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-plus me-2"></i>Add SMS Template
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_template">
                    
                    <div class="mb-3">
                        <label for="template_name" class="form-label">Template Name</label>
                        <input type="text" class="form-control" id="template_name" name="template_name" 
                               placeholder="e.g., Student Arrival" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="template_type" class="form-label">Template Type</label>
                        <select class="form-select" id="template_type" name="template_type" required>
                            <option value="">Select Type</option>
                            <option value="attendance">Attendance</option>
                            <option value="alert">Alert</option>
                            <option value="notification">Notification</option>
                            <option value="reminder">Reminder</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="template_message" class="form-label">Message Template</label>
                        <textarea class="form-control" id="template_message" name="template_message" rows="4" required 
                                  maxlength="160" placeholder="Hi {parent_name}, your child {student_name} has arrived at school at {time}."></textarea>
                        <div class="form-text">
                            Use placeholders: {parent_name}, {student_name}, {time}, {date}, {school_name}
                        </div>
                        <div class="d-flex justify-content-between">
                            <small class="text-muted">Maximum 160 characters</small>
                            <small class="text-muted"><span id="template-char-count">0</span>/160</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Save Template
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- SMS Details Modal -->
<div class="modal fade" id="smsDetailsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-eye me-2"></i>SMS Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Phone Number:</label>
                    <p id="sms-phone" class="form-control-plaintext"></p>
                </div>
                <div class="mb-3">
                    <label class="form-label">Message:</label>
                    <p id="sms-message" class="form-control-plaintext"></p>
                </div>
                <div class="mb-3">
                    <label class="form-label">Sent At:</label>
                    <p id="sms-sent-at" class="form-control-plaintext"></p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
// Toggle password visibility
function togglePasswordVisibility(inputId) {
    const input = document.getElementById(inputId);
    const icon = event.target.closest('button').querySelector('i');
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'fas fa-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'fas fa-eye';
    }
}

// Character count for text areas
document.getElementById('test_message')?.addEventListener('input', function() {
    const count = this.value.length;
    document.getElementById('char-count').textContent = count;
    
    if (count > 160) {
        this.classList.add('is-invalid');
    } else {
        this.classList.remove('is-invalid');
    }
});

document.getElementById('template_message')?.addEventListener('input', function() {
    const count = this.value.length;
    document.getElementById('template-char-count').textContent = count;
    
    if (count > 160) {
        this.classList.add('is-invalid');
    } else {
        this.classList.remove('is-invalid');
    }
});

// Use template function
function useTemplate(message) {
    document.getElementById('test_message').value = message;
    document.getElementById('char-count').textContent = message.length;
    
    // Show test SMS modal
    new bootstrap.Modal(document.getElementById('testSmsModal')).show();
}

// View SMS details
function viewSmsDetails(message, phone, sentAt) {
    document.getElementById('sms-phone').textContent = phone;
    document.getElementById('sms-message').textContent = message;
    document.getElementById('sms-sent-at').textContent = new Date(sentAt).toLocaleString();
    
    new bootstrap.Modal(document.getElementById('smsDetailsModal')).show();
}

// Filter SMS logs
document.querySelectorAll('[data-filter]').forEach(button => {
    button.addEventListener('click', function() {
        const filter = this.dataset.filter;
        
        // Update active button
        document.querySelectorAll('[data-filter]').forEach(btn => btn.classList.remove('active'));
        this.classList.add('active');
        
        // Filter table rows
        document.querySelectorAll('tbody tr[data-status]').forEach(row => {
            if (filter === 'all' || row.dataset.status === filter) {
                row.style.display = 'table-row';
            } else {
                row.style.display = 'none';
            }
        });
    });
});

// Delete template function (placeholder)
function deleteTemplate(templateId) {
    if (confirm('Are you sure you want to delete this template?')) {
        // In a real implementation, you would make an AJAX call to delete the template
        alert('Template deletion would be implemented here');
    }
}

// Auto-populate common phone format
document.getElementById('test_phone')?.addEventListener('blur', function() {
    let phone = this.value.replace(/\D/g, ''); // Remove non-digits
    
    if (phone.length === 10 && phone.startsWith('9')) {
        // Convert 9xxxxxxxxx to +639xxxxxxxxx
        this.value = '+63' + phone;
    } else if (phone.length === 11 && phone.startsWith('09')) {
        // Convert 09xxxxxxxxx to +639xxxxxxxxx
        this.value = '+63' + phone.substring(1);
    }
});

// Tab URL handling
document.addEventListener('DOMContentLoaded', function() {
    // Show tab based on URL hash
    const hash = window.location.hash;
    if (hash) {
        const tabElement = document.querySelector(`[data-bs-target="${hash}"]`);
        if (tabElement) {
            new bootstrap.Tab(tabElement).show();
        }
    }
    
    // Update URL when tab changes
    document.querySelectorAll('[data-bs-toggle="tab"]').forEach(tab => {
        tab.addEventListener('shown.bs.tab', function(e) {
            const target = e.target.getAttribute('data-bs-target');
            history.replaceState(null, null, window.location.pathname + target);
        });
    });
    
    // Initialize character counts
    const testMessage = document.getElementById('test_message');
    const templateMessage = document.getElementById('template_message');
    
    if (testMessage) {
        document.getElementById('char-count').textContent = testMessage.value.length;
    }
    
    if (templateMessage) {
        document.getElementById('template-char-count').textContent = templateMessage.value.length;
    }
});
</script>

<?php include 'footer.php'; ?>

<style>
@media (max-width: 576px) {
    .card-body {
        padding: 0.75rem !important;
    }
    
    .nav-tabs .nav-link {
        padding: 0.5rem;
        font-size: 0.875rem;
    }
    
    .table {
        font-size: 0.875rem;
    }
    
    .table td {
        padding: 0.5rem;
    }
    
    .btn-sm {
        padding: 0.25rem 0.5rem;
        font-size: 0.75rem;
    }
    
    .form-control,
    .form-select {
        font-size: 0.875rem;
        padding: 0.375rem 0.75rem;
    }
    
    .form-text {
        font-size: 0.75rem;
    }
    
    .alert {
        padding: 0.75rem;
        font-size: 0.875rem;
    }
}

.fade-in {
    animation: fadeIn 0.3s ease-in;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.card {
    transition: transform 0.2s;
}

.card:active {
    transform: scale(0.98);
}
</style>
