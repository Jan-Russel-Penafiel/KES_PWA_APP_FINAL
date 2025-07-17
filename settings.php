<?php
require_once 'config.php';

// Check if user is logged in and is admin
if (!isLoggedIn()) {
    redirect('login.php');
}

if (!hasRole('admin')) {
    $_SESSION['error'] = 'Access denied. Admin privileges required.';
    redirect('dashboard.php');
}

$current_user = getCurrentUser($pdo);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if ($action == 'update_sms_config') {
            $sms_api_url = sanitize_input($_POST['sms_api_url']);
            $sms_api_key = sanitize_input($_POST['sms_api_key']);
            $sms_sender_name = sanitize_input($_POST['sms_sender_name']);
            $sms_status = isset($_POST['sms_enabled']) ? 'active' : 'inactive';
            
            try {
                // Update or insert PhilSMS configuration
                $stmt = $pdo->prepare("
                    INSERT INTO sms_config (id, provider_name, api_url, api_key, sender_name, status) 
                    VALUES (1, 'PhilSMS', ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE 
                    provider_name = 'PhilSMS',
                    api_url = VALUES(api_url),
                    api_key = VALUES(api_key),
                    sender_name = VALUES(sender_name),
                    status = VALUES(status)
                ");
                $stmt->execute([$sms_api_url, $sms_api_key, $sms_sender_name, $sms_status]);
                
                $_SESSION['success'] = 'PhilSMS configuration updated successfully!';
            } catch(PDOException $e) {
                $_SESSION['error'] = 'Failed to update PhilSMS configuration: ' . $e->getMessage();
            }
            
        } elseif ($action == 'test_sms') {
            $test_phone = sanitize_input($_POST['test_phone']);
            $test_message = sanitize_input($_POST['test_message']);
            
            if (sendSMS($test_phone, $test_message, $pdo)) {
                $_SESSION['success'] = 'Test SMS sent successfully!';
            } else {
                $_SESSION['error'] = 'Failed to send test SMS. Please check your configuration.';
            }
            
        } elseif ($action == 'update_general_settings') {
            $school_name = sanitize_input($_POST['school_name']);
            $school_address = sanitize_input($_POST['school_address']);
            $school_contact = sanitize_input($_POST['school_contact']);
            $school_email = sanitize_input($_POST['school_email']);
            $timezone = sanitize_input($_POST['timezone']);
            $date_format = sanitize_input($_POST['date_format']);
            $time_format = sanitize_input($_POST['time_format']);
            
            // For simplicity, we'll store these in a JSON file
            $settings = [
                'school_name' => $school_name,
                'school_address' => $school_address,
                'school_contact' => $school_contact,
                'school_email' => $school_email,
                'timezone' => $timezone,
                'date_format' => $date_format,
                'time_format' => $time_format,
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            if (file_put_contents('settings.json', json_encode($settings, JSON_PRETTY_PRINT))) {
                $_SESSION['success'] = 'General settings updated successfully!';
            } else {
                $_SESSION['error'] = 'Failed to update general settings.';
            }
            
        } elseif ($action == 'clear_attendance_data') {
            try {
                // Clear attendance data older than specified days
                $days = intval($_POST['clear_days']);
                $date_cutoff = date('Y-m-d', strtotime("-{$days} days"));
                
                $stmt = $pdo->prepare("DELETE FROM attendance WHERE scan_time < ?");
                $stmt->execute([$date_cutoff]);
                $deleted_records = $stmt->rowCount();
                
                $_SESSION['success'] = "Cleared {$deleted_records} attendance records older than {$days} days.";
            } catch(PDOException $e) {
                $_SESSION['error'] = 'Failed to clear attendance data.';
            }
            
        } elseif ($action == 'backup_database') {
            $backup_file = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
            
            // Simple backup using mysqldump (would need to be adapted for production)
            $command = "mysqldump --host={$_ENV['DB_HOST']} --user={$_ENV['DB_USER']} --password={$_ENV['DB_PASS']} {$_ENV['DB_NAME']} > {$backup_file}";
            
            if (exec($command) !== false) {
                $_SESSION['success'] = "Database backup created: {$backup_file}";
            } else {
                $_SESSION['error'] = 'Failed to create database backup.';
            }
        }
    }
    
    redirect('settings.php');
}

$page_title = 'Settings & Configuration';
include 'header.php';

// Get current SMS configuration
try {
    $sms_config = $pdo->query("SELECT * FROM sms_config WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
    if (!$sms_config) {
        $sms_config = [
            'provider_name' => 'PhilSMS',
            'api_url' => 'https://app.philsms.com/api/v3/sms/send',
            'api_key' => '',
            'sender_name' => 'KES-SMART',
            'status' => 'inactive'
        ];
    }
} catch(PDOException $e) {
    $sms_config = [
        'provider_name' => 'PhilSMS',
        'api_url' => 'https://app.philsms.com/api/v3/sms/send',
        'api_key' => '',
        'sender_name' => 'KES-SMART',
        'status' => 'inactive'
    ];
}

// Get general settings
$general_settings = [];
if (file_exists('settings.json')) {
    $general_settings = json_decode(file_get_contents('settings.json'), true);
}

$default_settings = [
    'school_name' => 'KES - Kalaw Elementary School',
    'school_address' => '123 Education Street, Kalaw City',
    'school_contact' => '+1234567890',
    'school_email' => 'info@kes.edu',
    'timezone' => 'Asia/Manila',
    'date_format' => 'Y-m-d',
    'time_format' => 'H:i:s'
];

$general_settings = array_merge($default_settings, $general_settings);

// Get system statistics
try {
    $system_stats = [
        'total_users' => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
        'total_students' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn(),
        'total_attendance' => $pdo->query("SELECT COUNT(*) FROM attendance")->fetchColumn(),
        'total_sms' => $pdo->query("SELECT COUNT(*) FROM sms_logs")->fetchColumn(),
        'database_size' => '0 MB', // Would need to calculate properly
        'last_backup' => 'Never'
    ];
} catch(PDOException $e) {
    $system_stats = [
        'total_users' => 0,
        'total_students' => 0,
        'total_attendance' => 0,
        'total_sms' => 0,
        'database_size' => '0 MB',
        'last_backup' => 'Never'
    ];
}
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 fw-bold text-primary">
                    <i class="fas fa-cogs me-2"></i>Settings & Configuration
                </h1>
                <p class="text-muted mb-0">Configure system settings and preferences</p>
            </div>
            <div class="text-end">
                <button class="btn btn-success" onclick="location.reload()">
                    <i class="fas fa-sync me-2"></i>Refresh
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Settings Navigation -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-body p-0">
                <nav class="nav nav-pills nav-fill flex-nowrap overflow-auto">
                    <a class="nav-link active" data-bs-toggle="tab" href="#general-tab">
                        <i class="fas fa-school me-2"></i><span class="d-none d-sm-inline">General</span>
                    </a>
                    <a class="nav-link" data-bs-toggle="tab" href="#sms-tab">
                        <i class="fas fa-sms me-2"></i><span class="d-none d-sm-inline">SMS Config</span>
                    </a>
                    <a class="nav-link" data-bs-toggle="tab" href="#system-tab">
                        <i class="fas fa-server me-2"></i><span class="d-none d-sm-inline">System</span>
                    </a>
                    <a class="nav-link" data-bs-toggle="tab" href="#maintenance-tab">
                        <i class="fas fa-tools me-2"></i><span class="d-none d-sm-inline">Maintenance</span>
                    </a>
                </nav>
            </div>
        </div>
    </div>
</div>

<!-- Tab Content -->
<div class="tab-content">
    <!-- General Settings Tab -->
    <div class="tab-pane fade show active" id="general-tab">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-school me-2"></i>General Settings
                </h5>
            </div>
            <div class="card-body p-0 p-sm-3">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="update_general_settings">
                    
                    <div class="row g-3">
                        <div class="col-12 col-sm-6">
                            <label for="school_name" class="form-label">School Name</label>
                            <input type="text" class="form-control" id="school_name" name="school_name" 
                                   value="<?php echo htmlspecialchars($general_settings['school_name']); ?>" required>
                        </div>
                        
                        <div class="col-12 col-sm-6">
                            <label for="school_email" class="form-label">School Email</label>
                            <input type="email" class="form-control" id="school_email" name="school_email" 
                                   value="<?php echo htmlspecialchars($general_settings['school_email']); ?>">
                        </div>
                        
                        <div class="col-12 col-sm-6">
                            <label for="school_contact" class="form-label">School Contact</label>
                            <input type="text" class="form-control" id="school_contact" name="school_contact" 
                                   value="<?php echo htmlspecialchars($general_settings['school_contact']); ?>">
                        </div>
                        
                        <div class="col-12 col-sm-6">
                            <label for="timezone" class="form-label">Timezone</label>
                            <select class="form-select" id="timezone" name="timezone">
                                <option value="Asia/Manila" <?php echo $general_settings['timezone'] == 'Asia/Manila' ? 'selected' : ''; ?>>Asia/Manila</option>
                                <option value="UTC" <?php echo $general_settings['timezone'] == 'UTC' ? 'selected' : ''; ?>>UTC</option>
                                <option value="America/New_York" <?php echo $general_settings['timezone'] == 'America/New_York' ? 'selected' : ''; ?>>America/New_York</option>
                            </select>
                        </div>
                        
                        <div class="col-12">
                            <label for="school_address" class="form-label">School Address</label>
                            <textarea class="form-control" id="school_address" name="school_address" rows="3"><?php echo htmlspecialchars($general_settings['school_address']); ?></textarea>
                        </div>
                        
                        <div class="col-12 col-sm-6">
                            <label for="date_format" class="form-label">Date Format</label>
                            <select class="form-select" id="date_format" name="date_format">
                                <option value="Y-m-d" <?php echo $general_settings['date_format'] == 'Y-m-d' ? 'selected' : ''; ?>>YYYY-MM-DD</option>
                                <option value="m/d/Y" <?php echo $general_settings['date_format'] == 'm/d/Y' ? 'selected' : ''; ?>>MM/DD/YYYY</option>
                                <option value="d/m/Y" <?php echo $general_settings['date_format'] == 'd/m/Y' ? 'selected' : ''; ?>>DD/MM/YYYY</option>
                            </select>
                        </div>
                        
                        <div class="col-12 col-sm-6">
                            <label for="time_format" class="form-label">Time Format</label>
                            <select class="form-select" id="time_format" name="time_format">
                                <option value="H:i:s" <?php echo $general_settings['time_format'] == 'H:i:s' ? 'selected' : ''; ?>>24 Hour (HH:MM:SS)</option>
                                <option value="h:i A" <?php echo $general_settings['time_format'] == 'h:i A' ? 'selected' : ''; ?>>12 Hour (HH:MM AM/PM)</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="text-end mt-3">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Save General Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- SMS Configuration Tab -->
    <div class="tab-pane fade" id="sms-tab">
        <div class="row">
            <!-- SMS Configuration -->
            <div class="col-lg-8 mb-4">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-sms me-2"></i>PhilSMS API Configuration
                        </h5>
                    </div>
                    <div class="card-body">
                        <!-- Provider Info Banner -->
                        <div class="alert alert-info d-flex align-items-center mb-3">
                            <i class="fas fa-info-circle me-2"></i>
                            <div>
                                <strong>PhilSMS Configuration</strong><br>
                                <small>Official PhilSMS API integration for reliable SMS delivery in the Philippines</small>
                            </div>
                        </div>
                        
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="update_sms_config">
                            
                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="sms_enabled" name="sms_enabled" 
                                           <?php echo $sms_config['status'] == 'active' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="sms_enabled">
                                        <strong>Enable PhilSMS Notifications</strong>
                                    </label>
                                </div>
                                <div class="form-text">Toggle SMS notifications on/off for the entire system</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="sms_api_url" class="form-label">
                                    <i class="fas fa-link me-1"></i>PhilSMS API URL
                                </label>
                                <input type="url" class="form-control" id="sms_api_url" name="sms_api_url" 
                                       value="<?php echo htmlspecialchars($sms_config['api_url'] ?: 'https://app.philsms.com/api/v3/sms/send'); ?>"
                                       placeholder="https://app.philsms.com/api/v3/sms/send">
                                <div class="form-text">
                                    <strong>Default:</strong> https://app.philsms.com/api/v3/sms/send<br>
                                    <small class="text-muted">Official PhilSMS API endpoint for sending SMS</small>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="sms_api_key" class="form-label">
                                    <i class="fas fa-key me-1"></i>PhilSMS API Token
                                </label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="sms_api_key" name="sms_api_key" 
                                           value="<?php echo htmlspecialchars($sms_config['api_key']); ?>"
                                           placeholder="Enter your PhilSMS API token">
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility('sms_api_key')">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div class="form-text">
                                    Get your API token from <a href="https://app.philsms.com" target="_blank">PhilSMS Dashboard</a> → API Settings<br>
                                    <small class="text-muted">Format: Usually starts with "philsms_" followed by alphanumeric characters</small>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="sms_sender_name" class="form-label">
                                    <i class="fas fa-signature me-1"></i>Sender Name/ID
                                </label>
                                <input type="text" class="form-control" id="sms_sender_name" name="sms_sender_name" 
                                       value="<?php echo htmlspecialchars($sms_config['sender_name'] ?: 'KES-SMART'); ?>"
                                       placeholder="KES-SMART" maxlength="11">
                                <div class="form-text">
                                    <strong>Requirements:</strong> Maximum 11 characters, alphanumeric only<br>
                                    <small class="text-muted">This appears as the sender name on recipient's phone</small>
                                </div>
                            </div>
                            
                            <!-- PhilSMS Specific Settings -->
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-cog me-1"></i>Message Type
                                    </label>
                                    <select class="form-select" disabled>
                                        <option selected>Plain SMS</option>
                                    </select>
                                    <div class="form-text">PhilSMS supports plain text SMS messages</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-globe me-1"></i>Coverage
                                    </label>
                                    <select class="form-select" disabled>
                                        <option selected>Philippines</option>
                                    </select>
                                    <div class="form-text">Optimized for Philippine mobile networks</div>
                                </div>
                            </div>
                            
                            <div class="text-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Save SMS Configuration
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- PhilSMS Test -->
            <div class="col-lg-4 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-paper-plane me-2"></i>Test PhilSMS
                        </h5>
                    </div>
                    <div class="card-body">
                        <!-- PhilSMS Status -->
                        <div class="mb-3">
                            <div class="d-flex align-items-center justify-content-between">
                                <span class="fw-bold">Provider Status:</span>
                                <span class="badge bg-<?php echo $sms_config['status'] == 'active' ? 'success' : 'warning'; ?>">
                                    <?php echo ucfirst($sms_config['status']); ?>
                                </span>
                            </div>
                        </div>
                        
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="test_sms">
                            
                            <div class="mb-3">
                                <label for="test_phone" class="form-label">
                                    <i class="fas fa-mobile-alt me-1"></i>Philippine Mobile Number
                                </label>
                                <input type="text" class="form-control" id="test_phone" name="test_phone" 
                                       placeholder="+639123456789" pattern="^\+639\d{9}$" required>
                                <div class="form-text">
                                    Format: +639XXXXXXXXX<br>
                                    <small class="text-muted">Use Philippine mobile numbers only</small>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="test_message" class="form-label">
                                    <i class="fas fa-comment me-1"></i>Test Message
                                </label>
                                <textarea class="form-control" id="test_message" name="test_message" rows="3" 
                                          maxlength="160" required>Hello! This is a test message from KES-SMART via PhilSMS.</textarea>
                                <div class="form-text">
                                    <span id="char-count">75</span>/160 characters
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-success" 
                                        <?php echo $sms_config['status'] != 'active' ? 'disabled' : ''; ?>>
                                    <i class="fas fa-paper-plane me-2"></i>Send via PhilSMS
                                </button>
                                
                                <?php if ($sms_config['status'] != 'active'): ?>
                                <div class="alert alert-warning mt-3 mb-0">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    Enable PhilSMS notifications first to test.
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info mt-3 mb-0">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <strong>Note:</strong> Test messages may incur charges based on your PhilSMS plan.
                                </div>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
                
                <!-- PhilSMS Quick Info -->
                <div class="card">
                    <div class="card-header">
                        <h6 class="card-title mb-0">
                            <i class="fas fa-info-circle me-2"></i>PhilSMS Quick Info
                        </h6>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled mb-0">
                            <li class="mb-2">
                                <i class="fas fa-check text-success me-2"></i>
                                <small>High delivery rate</small>
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-check text-success me-2"></i>
                                <small>Philippine network optimized</small>
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-check text-success me-2"></i>
                                <small>Real-time delivery reports</small>
                            </li>
                            <li class="mb-0">
                                <i class="fas fa-external-link-alt text-primary me-2"></i>
                                <a href="https://app.philsms.com" target="_blank" class="text-decoration-none">
                                    <small>Visit PhilSMS Dashboard</small>
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- System Information Tab -->
    <div class="tab-pane fade" id="system-tab">
        <div class="row g-3">
            <!-- System Statistics -->
            <div class="col-12 col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-chart-pie me-2"></i>System Statistics
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-6">
                                <div class="d-flex align-items-center">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-users fa-2x text-primary"></i>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <h4 class="fw-bold mb-0"><?php echo number_format($system_stats['total_users']); ?></h4>
                                        <p class="text-muted mb-0 small">Total Users</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-6">
                                <div class="d-flex align-items-center">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-user-graduate fa-2x text-info"></i>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <h4 class="fw-bold mb-0"><?php echo number_format($system_stats['total_students']); ?></h4>
                                        <p class="text-muted mb-0 small">Students</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-6">
                                <div class="d-flex align-items-center">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-calendar-check fa-2x text-success"></i>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <h4 class="fw-bold mb-0"><?php echo number_format($system_stats['total_attendance']); ?></h4>
                                        <p class="text-muted mb-0 small">Attendance Records</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-6">
                                <div class="d-flex align-items-center">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-sms fa-2x text-warning"></i>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <h4 class="fw-bold mb-0"><?php echo number_format($system_stats['total_sms']); ?></h4>
                                        <p class="text-muted mb-0 small">SMS Sent</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- System Information -->
            <div class="col-12 col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-info-circle me-2"></i>System Information
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-borderless align-middle mb-0">
                                <tr>
                                    <td class="ps-0"><strong>PHP Version:</strong></td>
                                    <td class="text-end pe-0"><?php echo phpversion(); ?></td>
                                </tr>
                                <tr>
                                    <td class="ps-0"><strong>Server:</strong></td>
                                    <td class="text-end pe-0"><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?></td>
                                </tr>
                                <tr>
                                    <td class="ps-0"><strong>Database Size:</strong></td>
                                    <td class="text-end pe-0"><?php echo $system_stats['database_size']; ?></td>
                                </tr>
                                <tr>
                                    <td class="ps-0"><strong>Last Backup:</strong></td>
                                    <td class="text-end pe-0"><?php echo $system_stats['last_backup']; ?></td>
                                </tr>
                                <tr>
                                    <td class="ps-0"><strong>Timezone:</strong></td>
                                    <td class="text-end pe-0"><?php echo $general_settings['timezone']; ?></td>
                                </tr>
                                <tr>
                                    <td class="ps-0"><strong>Current Time:</strong></td>
                                    <td class="text-end pe-0"><?php echo date('Y-m-d H:i:s'); ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Maintenance Tab -->
    <div class="tab-pane fade" id="maintenance-tab">
        <div class="row g-3">
            <!-- Database Maintenance -->
            <div class="col-12 col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-database me-2"></i>Database Maintenance
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-4">
                            <h6 class="mb-3">Clear Old Attendance Data</h6>
                            <p class="text-muted small mb-3">Remove attendance records older than specified days</p>
                            <form method="POST" action="" class="d-flex gap-2">
                                <input type="hidden" name="action" value="clear_attendance_data">
                                <input type="number" class="form-control" name="clear_days" value="365" min="30" style="max-width: 120px;">
                                <button type="submit" class="btn btn-warning" onclick="return confirm('Are you sure you want to clear old attendance data?')">
                                    <i class="fas fa-trash me-2"></i>Clear
                                </button>
                            </form>
                        </div>
                        
                        <div class="mb-4">
                            <h6 class="mb-3">Database Backup</h6>
                            <p class="text-muted small mb-3">Create a backup of the entire database</p>
                            <form method="POST" action="">
                                <input type="hidden" name="action" value="backup_database">
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-download me-2"></i>Create Backup
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- System Logs -->
            <div class="col-12 col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-file-alt me-2"></i>System Logs
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <h6 class="mb-3">Recent SMS Logs</h6>
                            <?php
                            try {
                                $recent_sms = $pdo->query("
                                    SELECT phone_number, message, status, sent_at 
                                    FROM sms_logs 
                                    ORDER BY sent_at DESC 
                                    LIMIT 5
                                ")->fetchAll(PDO::FETCH_ASSOC);
                                
                                if ($recent_sms):
                            ?>
                                <div class="table-responsive">
                                    <table class="table table-sm align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th>Phone</th>
                                                <th>Status</th>
                                                <th>Time</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_sms as $sms): ?>
                                                <tr>
                                                    <td><code class="small"><?php echo substr($sms['phone_number'], 0, 8) . '***'; ?></code></td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $sms['status'] == 'sent' ? 'success' : 'danger'; ?>">
                                                            <?php echo ucfirst($sms['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td><small class="text-muted"><?php echo date('M j, H:i', strtotime($sms['sent_at'])); ?></small></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-muted small">No SMS logs found.</p>
                            <?php endif;
                            } catch(PDOException $e) {
                                echo '<p class="text-muted small">Unable to load SMS logs.</p>';
                            }
                            ?>
                        </div>
                        
                        <div class="text-end">
                            <a href="reports.php?type=system_logs" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-external-link-alt me-2"></i>View All Logs
                            </a>
                        </div>
                    </div>
                </div>
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

// Character counter for SMS message
document.addEventListener('DOMContentLoaded', function() {
    const testMessage = document.getElementById('test_message');
    const charCount = document.getElementById('char-count');
    const testPhone = document.getElementById('test_phone');
    
    if (testMessage && charCount) {
        // Initial count
        charCount.textContent = testMessage.value.length;
        
        // Update on input
        testMessage.addEventListener('input', function() {
            const count = this.value.length;
            charCount.textContent = count;
            
            // Color coding
            if (count > 160) {
                charCount.className = 'text-danger fw-bold';
                charCount.textContent = count + ' (Over limit!)';
            } else if (count > 140) {
                charCount.className = 'text-warning fw-bold';
            } else {
                charCount.className = 'text-success';
            }
        });
    }
    
    // Phone number validation for PhilSMS
    if (testPhone) {
        testPhone.addEventListener('input', function() {
            const value = this.value;
            const isValid = /^\+639\d{9}$/.test(value);
            
            if (value && !isValid) {
                this.setCustomValidity('Please enter a valid Philippine mobile number (+639XXXXXXXXX)');
                this.classList.add('is-invalid');
            } else {
                this.setCustomValidity('');
                this.classList.remove('is-invalid');
                if (value) this.classList.add('is-valid');
            }
        });
        
        // Auto-format phone number
        testPhone.addEventListener('blur', function() {
            let value = this.value.replace(/\D/g, ''); // Remove non-digits
            
            if (value.startsWith('09') && value.length === 11) {
                this.value = '+63' + value.substring(1);
            } else if (value.startsWith('639') && value.length === 12) {
                this.value = '+' + value;
            }
        });
    }
});

// Tab switching
document.addEventListener('DOMContentLoaded', function() {
    // Handle tab navigation
    const tabs = document.querySelectorAll('[data-bs-toggle="tab"]');
    tabs.forEach(tab => {
        tab.addEventListener('shown.bs.tab', function(e) {
            const targetTab = e.target.getAttribute('href');
            history.replaceState(null, null, window.location.pathname + targetTab);
        });
    });
    
    // Show tab based on URL hash
    const hash = window.location.hash;
    if (hash) {
        const tab = document.querySelector(`[href="${hash}"]`);
        if (tab) {
            new bootstrap.Tab(tab).show();
        }
    }
});

// Auto-save draft for forms
let saveTimeout;
function autoSaveDraft(formId) {
    clearTimeout(saveTimeout);
    saveTimeout = setTimeout(() => {
        const form = document.getElementById(formId);
        const formData = new FormData(form);
        const data = Object.fromEntries(formData);
        
        localStorage.setItem(`draft_${formId}`, JSON.stringify(data));
        
        // Show save indicator
        const indicator = document.createElement('div');
        indicator.className = 'alert alert-info alert-dismissible fade show position-fixed top-0 end-0 m-3';
        indicator.style.zIndex = '9999';
        indicator.innerHTML = `
            <i class="fas fa-save me-2"></i>Draft saved automatically
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        document.body.appendChild(indicator);
        
        setTimeout(() => {
            if (indicator.parentNode) {
                indicator.remove();
            }
        }, 3000);
    }, 2000);
}

// Load draft on page load
document.addEventListener('DOMContentLoaded', function() {
    const forms = document.querySelectorAll('form[id]');
    forms.forEach(form => {
        const draftKey = `draft_${form.id}`;
        const draft = localStorage.getItem(draftKey);
        
        if (draft) {
            try {
                const data = JSON.parse(draft);
                Object.keys(data).forEach(key => {
                    const input = form.querySelector(`[name="${key}"]`);
                    if (input && input.type !== 'hidden') {
                        if (input.type === 'checkbox') {
                            input.checked = data[key] === 'on';
                        } else {
                            input.value = data[key];
                        }
                    }
                });
            } catch (e) {
                // Invalid draft data, ignore
            }
        }
        
        // Add auto-save listeners
        const inputs = form.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            if (input.type !== 'hidden' && input.type !== 'submit') {
                input.addEventListener('change', () => autoSaveDraft(form.id));
                input.addEventListener('input', () => autoSaveDraft(form.id));
            }
        });
        
        // Clear draft on successful submit
        form.addEventListener('submit', () => {
            localStorage.removeItem(draftKey);
        });
    });
});
</script>

<?php include 'footer.php'; ?>

<style>
@media (max-width: 576px) {
    .card-body {
        padding: 0.75rem !important;
    }
    
    .nav-pills .nav-link {
        padding: 0.5rem;
        font-size: 0.875rem;
    }
    
    .form-control,
    .form-select {
        font-size: 0.875rem;
        padding: 0.375rem 0.75rem;
    }
    
    .form-text {
        font-size: 0.75rem;
    }
    
    .btn-sm {
        padding: 0.25rem 0.5rem;
        font-size: 0.75rem;
    }
    
    .table {
        font-size: 0.875rem;
    }
    
    .table td {
        padding: 0.5rem;
    }
    
    .badge {
        font-size: 0.75rem;
    }
    
    h6 {
        font-size: 0.875rem;
    }
    
    .small {
        font-size: 0.75rem;
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
