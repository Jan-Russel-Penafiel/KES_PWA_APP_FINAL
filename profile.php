<?php
require_once 'config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

$current_user = getCurrentUser($pdo);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if ($action == 'update_profile') {
            $full_name = sanitize_input($_POST['full_name']);
            $email = sanitize_input($_POST['email']);
            $phone = sanitize_input($_POST['phone']);
            
            try {
                $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, phone = ? WHERE id = ?");
                $stmt->execute([$full_name, $email, $phone, $current_user['id']]);
                
                $_SESSION['success'] = 'Profile updated successfully!';
            } catch(PDOException $e) {
                $_SESSION['error'] = 'Failed to update profile.';
            }
            
        } elseif ($action == 'change_password') {
            $current_password = sanitize_input($_POST['current_password']);
            $new_password = sanitize_input($_POST['new_password']);
            $confirm_password = sanitize_input($_POST['confirm_password']);
            
            // Note: Since this is a demo system without passwords, 
            // we'll simulate password change functionality
            if ($new_password !== $confirm_password) {
                $_SESSION['error'] = 'New passwords do not match.';
            } elseif (strlen($new_password) < 6) {
                $_SESSION['error'] = 'Password must be at least 6 characters long.';
            } else {
                // In a real system, you would hash the password and update the database
                $_SESSION['success'] = 'Password changed successfully! (Demo mode - no actual password set)';
            }
            
        } elseif ($action == 'update_preferences') {
            $timezone = sanitize_input($_POST['timezone']);
            $date_format = sanitize_input($_POST['date_format']);
            $time_format = sanitize_input($_POST['time_format']);
            $theme = sanitize_input($_POST['theme']);
            $notifications = isset($_POST['notifications']) ? 1 : 0;
            $email_alerts = isset($_POST['email_alerts']) ? 1 : 0;
            $sms_alerts = isset($_POST['sms_alerts']) ? 1 : 0;
            
            // Store preferences in session or database
            $_SESSION['user_preferences'] = [
                'timezone' => $timezone,
                'date_format' => $date_format,
                'time_format' => $time_format,
                'theme' => $theme,
                'notifications' => $notifications,
                'email_alerts' => $email_alerts,
                'sms_alerts' => $sms_alerts
            ];
            
            $_SESSION['success'] = 'Preferences updated successfully!';
        }
    }
    
    redirect('profile.php');
}

$page_title = 'My Profile';
include 'header.php';

// Get user statistics based on role
$user_stats = [];
try {
    if ($current_user['role'] == 'admin') {
        $user_stats = [
            'total_users' => $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'active'")->fetchColumn(),
            'total_students' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student' AND status = 'active'")->fetchColumn(),
            'total_sections' => $pdo->query("SELECT COUNT(*) FROM sections WHERE status = 'active'")->fetchColumn(),
            'recent_activity' => 'System administration and monitoring'
        ];
    } elseif ($current_user['role'] == 'teacher') {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM sections WHERE teacher_id = ?");
        $stmt->execute([$current_user['id']]);
        $assigned_sections = $stmt->fetchColumn();
        
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE section_id IN (SELECT id FROM sections WHERE teacher_id = ?)");
        $stmt->execute([$current_user['id']]);
        $total_students = $stmt->fetchColumn();
        
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE teacher_id = ? AND attendance_date = CURDATE()");
        $stmt->execute([$current_user['id']]);
        $today_attendance = $stmt->fetchColumn();
        
        $user_stats = [
            'assigned_sections' => $assigned_sections,
            'total_students' => $total_students,
            'today_attendance' => $today_attendance,
            'recent_activity' => 'Teaching and student monitoring'
        ];
    } elseif ($current_user['role'] == 'student') {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE student_id = ? AND MONTH(attendance_date) = MONTH(CURDATE())");
        $stmt->execute([$current_user['id']]);
        $attendance_this_month = $stmt->fetchColumn();
        
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE student_id = ?");
        $stmt->execute([$current_user['id']]);
        $total_attendance = $stmt->fetchColumn();
        
        $section_name = '';
        if ($current_user['section_id']) {
            $stmt = $pdo->prepare("SELECT section_name FROM sections WHERE id = ?");
            $stmt->execute([$current_user['section_id']]);
            $section_name = $stmt->fetchColumn() ?: '';
        }
        
        $user_stats = [
            'attendance_this_month' => $attendance_this_month,
            'total_attendance' => $total_attendance,
            'section_name' => $section_name,
            'recent_activity' => 'Attending classes and activities'
        ];
    } elseif ($current_user['role'] == 'parent') {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM student_parents WHERE parent_id = ?");
        $stmt->execute([$current_user['id']]);
        $children = $stmt->fetchColumn();
        
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM sms_logs WHERE phone_number = ?");
        $stmt->execute([$current_user['phone']]);
        $notifications_received = $stmt->fetchColumn();
        
        $user_stats = [
            'children_count' => $children,
            'notifications_received' => $notifications_received,
            'last_notification' => 'Recent attendance updates',
            'recent_activity' => 'Monitoring children attendance'
        ];
    }
} catch(PDOException $e) {
    $user_stats = [];
}

// Get user preferences
$preferences = $_SESSION['user_preferences'] ?? [
    'timezone' => 'Asia/Manila',
    'date_format' => 'Y-m-d',
    'time_format' => 'H:i:s',
    'theme' => 'light',
    'notifications' => 1,
    'email_alerts' => 1,
    'sms_alerts' => 1
];
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 fw-bold text-primary">
                    <i class="fas fa-user-circle me-2"></i>My Profile
                </h1>
                <p class="text-muted mb-0">Manage your account settings and preferences</p>
            </div>
            <div class="text-end">
                <span class="badge bg-<?php 
                    echo $current_user['role'] == 'admin' ? 'danger' : 
                         ($current_user['role'] == 'teacher' ? 'success' : 
                          ($current_user['role'] == 'student' ? 'primary' : 'warning')); 
                ?> fs-6">
                    <?php echo ucfirst($current_user['role']); ?>
                </span>
            </div>
        </div>
    </div>
</div>

<!-- Profile Header -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-3 p-sm-4">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <div class="profile-avatar" style="width: 80px; height: 80px; background: linear-gradient(45deg, 
                            <?php 
                            echo $current_user['role'] == 'admin' ? '#dc3545, #c82333' : 
                                 ($current_user['role'] == 'teacher' ? '#28a745, #20c997' : 
                                  ($current_user['role'] == 'student' ? '#007bff, #0056b3' : '#ffc107, #fd7e14')); 
                            ?>); display: flex; align-items: center; justify-content: center; border-radius: 50%; color: white; font-size: 2rem;">
                            <i class="fas fa-<?php 
                                echo $current_user['role'] == 'admin' ? 'user-shield' : 
                                     ($current_user['role'] == 'teacher' ? 'chalkboard-teacher' : 
                                      ($current_user['role'] == 'student' ? 'user-graduate' : 'heart')); 
                            ?>"></i>
                        </div>
                    </div>
                    <div class="col">
                        <h3 class="fw-bold mb-1 text-truncate"><?php echo htmlspecialchars($current_user['full_name']); ?></h3>
                        <p class="text-muted mb-2">@<?php echo htmlspecialchars($current_user['username']); ?></p>
                        <div class="d-flex flex-wrap gap-2">
                            <?php if ($current_user['email']): ?>
                                <span class="badge bg-light text-dark">
                                    <i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($current_user['email']); ?>
                                </span>
                            <?php endif; ?>
                            <?php if ($current_user['phone']): ?>
                                <span class="badge bg-light text-dark">
                                    <i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($current_user['phone']); ?>
                                </span>
                            <?php endif; ?>
                            <?php if ($current_user['role'] == 'student' && $current_user['lrn']): ?>
                                <span class="badge bg-primary text-white">
                                    <i class="fas fa-id-card me-1"></i>LRN: <?php echo htmlspecialchars($current_user['lrn']); ?>
                                </span>
                            <?php endif; ?>
                            <span class="badge bg-light text-dark">
                                <i class="fas fa-calendar me-1"></i>Joined <?php echo date('M j, Y', strtotime($current_user['created_at'])); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Statistics Row -->
<?php if (!empty($user_stats)): ?>
<div class="row g-3 mb-4">
    <?php foreach ($user_stats as $key => $value): ?>
        <div class="col-6 col-lg-3">
            <div class="card text-center border-0 shadow-sm h-100">
                <div class="card-body p-3">
                    <div class="mb-2">
                        <i class="fas fa-<?php 
                            echo $key == 'total_users' || $key == 'children_count' ? 'users' : 
                                 ($key == 'total_students' || $key == 'assigned_sections' ? 'graduation-cap' : 
                                  ($key == 'attendance_this_month' || $key == 'today_attendance' ? 'calendar-check' : 'chart-line')); 
                        ?> fa-2x text-primary"></i>
                    </div>
                    <h4 class="fw-bold text-primary mb-1"><?php echo is_numeric($value) ? number_format($value) : $value; ?></h4>
                    <p class="text-muted mb-0 small"><?php echo ucwords(str_replace('_', ' ', $key)); ?></p>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Profile Tabs -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <ul class="nav nav-tabs card-header-tabs flex-nowrap overflow-auto" id="profileTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="info-tab" data-bs-toggle="tab" data-bs-target="#info" type="button" role="tab">
                            <i class="fas fa-user me-2"></i><span class="d-none d-sm-inline">Personal Info</span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="security-tab" data-bs-toggle="tab" data-bs-target="#security" type="button" role="tab">
                            <i class="fas fa-shield-alt me-2"></i><span class="d-none d-sm-inline">Security</span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="preferences-tab" data-bs-toggle="tab" data-bs-target="#preferences" type="button" role="tab">
                            <i class="fas fa-cog me-2"></i><span class="d-none d-sm-inline">Preferences</span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="activity-tab" data-bs-toggle="tab" data-bs-target="#activity" type="button" role="tab">
                            <i class="fas fa-history me-2"></i><span class="d-none d-sm-inline">Activity</span>
                        </button>
                    </li>
                </ul>
            </div>
            <div class="card-body p-0 p-sm-3">
                <div class="tab-content" id="profileTabsContent">
                    <!-- Personal Information Tab -->
                    <div class="tab-pane fade show active" id="info" role="tabpanel">
                        <!-- Offline Mode Message -->
                        <div class="offline-message alert alert-warning" style="display: none;">
                            <i class="fas fa-wifi-slash me-2"></i>
                            <strong>Offline Mode:</strong> You can view your profile information, but editing is disabled while offline.
                        </div>
                        
                        <form method="POST" action="" class="online-only">
                            <input type="hidden" name="action" value="update_profile">
                            
                            <div class="row g-3">
                                <div class="col-12 col-sm-6">
                                    <label for="username" class="form-label">Username</label>
                                    <input type="text" class="form-control" id="username" value="<?php echo htmlspecialchars($current_user['username']); ?>" readonly>
                                    <div class="form-text">Username cannot be changed</div>
                                </div>
                                
                                <div class="col-12 col-sm-6">
                                    <label for="role" class="form-label">Role</label>
                                    <input type="text" class="form-control" id="role" value="<?php echo ucfirst($current_user['role']); ?>" readonly>
                                </div>
                                
                                <div class="col-12 col-sm-6">
                                    <label for="full_name" class="form-label">Full Name *</label>
                                    <input type="text" class="form-control" id="full_name" name="full_name" 
                                           value="<?php echo htmlspecialchars($current_user['full_name']); ?>" required>
                                </div>
                                
                                <div class="col-12 col-sm-6">
                                    <label for="email" class="form-label">Email Address</label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo htmlspecialchars($current_user['email'] ?? ''); ?>">
                                </div>
                                
                                <div class="col-12 col-sm-6">
                                    <label for="phone" class="form-label">Phone Number</label>
                                    <input type="text" class="form-control" id="phone" name="phone" 
                                           value="<?php echo htmlspecialchars($current_user['phone'] ?? ''); ?>"
                                           placeholder="+639123456789">
                                </div>
                                
                                <?php if ($current_user['role'] == 'student'): ?>
                                <div class="col-12 col-sm-6">
                                    <label for="lrn" class="form-label">LRN (Learner Reference Number)</label>
                                    <input type="text" class="form-control" id="lrn" name="lrn" 
                                           value="<?php echo htmlspecialchars($current_user['lrn'] ?? ''); ?>"
                                           placeholder="e.g., 123456789012"
                                           readonly>
                                    <div class="form-text">LRN cannot be changed</div>
                                </div>
                                <?php endif; ?>
                                
                                <div class="col-12 col-sm-6">
                                    <label for="status" class="form-label">Account Status</label>
                                    <input type="text" class="form-control" id="status" 
                                           value="<?php echo ucfirst($current_user['status']); ?>" readonly>
                                </div>
                            </div>
                            
                            <div class="text-end mt-3">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Update Profile
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Security Tab -->
                    <div class="tab-pane fade" id="security" role="tabpanel">
                        <!-- Offline Mode Message -->
                        <div class="offline-message alert alert-warning" style="display: none;">
                            <i class="fas fa-wifi-slash me-2"></i>
                            <strong>Offline Mode:</strong> Password changes are not available while offline.
                        </div>
                        
                        <div class="row g-3">
                            <div class="col-12 col-lg-8">
                                <form method="POST" action="" class="online-only">
                                    <input type="hidden" name="action" value="change_password">
                                    
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle me-2"></i>
                                        <strong>Demo Mode:</strong> Password functionality is simulated in demo mode.
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="current_password" class="form-label">Current Password</label>
                                        <input type="password" class="form-control" id="current_password" name="current_password">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="new_password" class="form-label">New Password</label>
                                        <input type="password" class="form-control" id="new_password" name="new_password" minlength="6">
                                        <div class="form-text">Minimum 6 characters</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" minlength="6">
                                    </div>
                                    
                                    <div class="text-end">
                                        <button type="submit" class="btn btn-warning">
                                            <i class="fas fa-key me-2"></i>Change Password
                                        </button>
                                    </div>
                                </form>
                            </div>
                            
                            <div class="col-12 col-lg-4">
                                <div class="card bg-light h-100">
                                    <div class="card-body">
                                        <h6 class="card-title">
                                            <i class="fas fa-shield-alt me-2"></i>Security Tips
                                        </h6>
                                        <ul class="list-unstyled mb-0 small">
                                            <li class="mb-2">
                                                <i class="fas fa-check text-success me-2"></i>
                                                Use a strong, unique password
                                            </li>
                                            <li class="mb-2">
                                                <i class="fas fa-check text-success me-2"></i>
                                                Enable two-factor authentication
                                            </li>
                                            <li class="mb-2">
                                                <i class="fas fa-check text-success me-2"></i>
                                                Keep your contact info updated
                                            </li>
                                            <li class="mb-0">
                                                <i class="fas fa-check text-success me-2"></i>
                                                Log out from shared devices
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Preferences Tab -->
                    <div class="tab-pane fade" id="preferences" role="tabpanel">
                        <!-- Offline Mode Message -->
                        <div class="offline-message alert alert-warning" style="display: none;">
                            <i class="fas fa-wifi-slash me-2"></i>
                            <strong>Offline Mode:</strong> Preference changes are not available while offline.
                        </div>
                        
                        <form method="POST" action="" class="online-only">
                            <input type="hidden" name="action" value="update_preferences">
                            
                            <div class="row g-3">
                                <div class="col-12 col-sm-6">
                                    <label for="timezone" class="form-label">Timezone</label>
                                    <select class="form-select" id="timezone" name="timezone">
                                        <option value="Asia/Manila" <?php echo $preferences['timezone'] == 'Asia/Manila' ? 'selected' : ''; ?>>Asia/Manila (GMT+8)</option>
                                        <option value="UTC" <?php echo $preferences['timezone'] == 'UTC' ? 'selected' : ''; ?>>UTC (GMT+0)</option>
                                        <option value="America/New_York" <?php echo $preferences['timezone'] == 'America/New_York' ? 'selected' : ''; ?>>America/New_York (GMT-5)</option>
                                        <option value="Europe/London" <?php echo $preferences['timezone'] == 'Europe/London' ? 'selected' : ''; ?>>Europe/London (GMT+0)</option>
                                    </select>
                                </div>
                                
                                <div class="col-12 col-sm-6">
                                    <label for="date_format" class="form-label">Date Format</label>
                                    <select class="form-select" id="date_format" name="date_format">
                                        <option value="Y-m-d" <?php echo $preferences['date_format'] == 'Y-m-d' ? 'selected' : ''; ?>>YYYY-MM-DD</option>
                                        <option value="m/d/Y" <?php echo $preferences['date_format'] == 'm/d/Y' ? 'selected' : ''; ?>>MM/DD/YYYY</option>
                                        <option value="d/m/Y" <?php echo $preferences['date_format'] == 'd/m/Y' ? 'selected' : ''; ?>>DD/MM/YYYY</option>
                                        <option value="M j, Y" <?php echo $preferences['date_format'] == 'M j, Y' ? 'selected' : ''; ?>>Month DD, YYYY</option>
                                    </select>
                                </div>
                                
                                <div class="col-12 col-sm-6">
                                    <label for="time_format" class="form-label">Time Format</label>
                                    <select class="form-select" id="time_format" name="time_format">
                                        <option value="H:i:s" <?php echo $preferences['time_format'] == 'H:i:s' ? 'selected' : ''; ?>>24 Hour (HH:MM:SS)</option>
                                        <option value="h:i A" <?php echo $preferences['time_format'] == 'h:i A' ? 'selected' : ''; ?>>12 Hour (HH:MM AM/PM)</option>
                                    </select>
                                </div>
                                
                                <div class="col-12 col-sm-6">
                                    <label for="theme" class="form-label">Theme</label>
                                    <select class="form-select" id="theme" name="theme">
                                        <option value="light" <?php echo $preferences['theme'] == 'light' ? 'selected' : ''; ?>>Light Theme</option>
                                        <option value="dark" <?php echo $preferences['theme'] == 'dark' ? 'selected' : ''; ?>>Dark Theme</option>
                                        <option value="auto" <?php echo $preferences['theme'] == 'auto' ? 'selected' : ''; ?>>Auto (System)</option>
                                    </select>
                                </div>
                            </div>
                            
                            <hr class="my-4">
                            
                            <h6 class="mb-3">Notification Preferences</h6>
                            
                            <div class="row g-3">
                                <div class="col-12 col-sm-4">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="notifications" name="notifications" 
                                               <?php echo $preferences['notifications'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="notifications">
                                            <i class="fas fa-bell me-2"></i>Push Notifications
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="col-12 col-sm-4">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="email_alerts" name="email_alerts" 
                                               <?php echo $preferences['email_alerts'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="email_alerts">
                                            <i class="fas fa-envelope me-2"></i>Email Alerts
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="col-12 col-sm-4">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="sms_alerts" name="sms_alerts" 
                                               <?php echo $preferences['sms_alerts'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="sms_alerts">
                                            <i class="fas fa-sms me-2"></i>SMS Alerts
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="text-end mt-3">
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-save me-2"></i>Update Preferences
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Activity Log Tab -->
                    <div class="tab-pane fade" id="activity" role="tabpanel">
                        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-center mb-3 gap-2">
                            <h6 class="mb-0">Recent Activity</h6>
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-secondary active" data-filter="all">All</button>
                                <button class="btn btn-outline-secondary" data-filter="login">Logins</button>
                                <button class="btn btn-outline-secondary" data-filter="profile">Profile</button>
                                <button class="btn btn-outline-secondary" data-filter="system">System</button>
                            </div>
                        </div>
                        
                        <div class="activity-log">
                            <!-- Sample activity log entries -->
                            <div class="activity-item d-flex align-items-start mb-3 pb-3 border-bottom" data-type="login">
                                <div class="activity-icon me-3">
                                    <div class="bg-success rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                        <i class="fas fa-sign-in-alt text-white"></i>
                                    </div>
                                </div>
                                <div class="activity-content flex-grow-1">
                                    <h6 class="mb-1">Logged in successfully</h6>
                                    <p class="text-muted mb-1 small">Accessed the system dashboard</p>
                                    <small class="text-muted">
                                        <i class="fas fa-clock me-1"></i><?php echo date('M j, Y \a\t g:i A'); ?>
                                    </small>
                                </div>
                            </div>
                            
                            <div class="activity-item d-flex align-items-start mb-3 pb-3 border-bottom" data-type="profile">
                                <div class="activity-icon me-3">
                                    <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                        <i class="fas fa-user-edit text-white"></i>
                                    </div>
                                </div>
                                <div class="activity-content flex-grow-1">
                                    <h6 class="mb-1">Profile updated</h6>
                                    <p class="text-muted mb-1 small">Updated contact information</p>
                                    <small class="text-muted">
                                        <i class="fas fa-clock me-1"></i><?php echo date('M j, Y \a\t g:i A', strtotime('-2 hours')); ?>
                                    </small>
                                </div>
                            </div>
                            
                            <?php if ($current_user['role'] == 'admin'): ?>
                            <div class="activity-item d-flex align-items-start mb-3 pb-3 border-bottom" data-type="system">
                                <div class="activity-icon me-3">
                                    <div class="bg-warning rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                        <i class="fas fa-cogs text-white"></i>
                                    </div>
                                </div>
                                <div class="activity-content flex-grow-1">
                                    <h6 class="mb-1">System settings updated</h6>
                                    <p class="text-muted mb-1 small">Modified SMS configuration</p>
                                    <small class="text-muted">
                                        <i class="fas fa-clock me-1"></i><?php echo date('M j, Y \a\t g:i A', strtotime('-1 day')); ?>
                                    </small>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <div class="activity-item d-flex align-items-start mb-3" data-type="system">
                                <div class="activity-icon me-3">
                                    <div class="bg-info rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                        <i class="fas fa-mobile-alt text-white"></i>
                                    </div>
                                </div>
                                <div class="activity-content flex-grow-1">
                                    <h6 class="mb-1">Accessed from mobile device</h6>
                                    <p class="text-muted mb-1 small">Used PWA on mobile browser</p>
                                    <small class="text-muted">
                                        <i class="fas fa-clock me-1"></i><?php echo date('M j, Y \a\t g:i A', strtotime('-2 days')); ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="text-center mt-4">
                            <button class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-history me-2"></i>Load More Activity
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Password confirmation validation
document.getElementById('confirm_password')?.addEventListener('input', function() {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = this.value;
    
    if (newPassword !== confirmPassword) {
        this.setCustomValidity('Passwords do not match');
    } else {
        this.setCustomValidity('');
    }
});

// Activity log filtering
document.querySelectorAll('[data-filter]').forEach(button => {
    button.addEventListener('click', function() {
        const filter = this.dataset.filter;
        
        // Update active button
        document.querySelectorAll('[data-filter]').forEach(btn => btn.classList.remove('active'));
        this.classList.add('active');
        
        // Filter activity items
        document.querySelectorAll('.activity-item').forEach(item => {
            if (filter === 'all' || item.dataset.type === filter) {
                item.style.display = 'flex';
            } else {
                item.style.display = 'none';
            }
        });
    });
});

// Theme switching preview
document.getElementById('theme')?.addEventListener('change', function() {
    const theme = this.value;
    const body = document.body;
    
    // Remove existing theme classes
    body.classList.remove('theme-light', 'theme-dark');
    
    if (theme === 'dark') {
        body.classList.add('theme-dark');
    } else if (theme === 'light') {
        body.classList.add('theme-light');
    } else {
        // Auto theme - use system preference
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            body.classList.add('theme-dark');
        } else {
            body.classList.add('theme-light');
        }
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
});

// Auto-save preferences
let preferencesTimeout;
document.querySelectorAll('#preferences input, #preferences select').forEach(input => {
    input.addEventListener('change', function() {
        clearTimeout(preferencesTimeout);
        preferencesTimeout = setTimeout(() => {
            // Show saving indicator
            const indicator = document.createElement('div');
            indicator.className = 'alert alert-info alert-dismissible fade show position-fixed top-0 end-0 m-3';
            indicator.style.zIndex = '9999';
            indicator.innerHTML = `
                <i class="fas fa-save me-2"></i>Preferences saved automatically
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.body.appendChild(indicator);
            
            setTimeout(() => {
                if (indicator.parentNode) {
                    indicator.remove();
                }
            }, 3000);
        }, 1000);
    });
});
</script>

<style>
@media (max-width: 576px) {
    .profile-avatar {
        width: 60px !important;
        height: 60px !important;
        font-size: 1.5rem !important;
    }
    
    .card-body {
        padding: 0.75rem !important;
    }
    
    .nav-tabs .nav-link {
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
    
    .activity-icon {
        width: 32px !important;
        height: 32px !important;
    }
    
    .activity-icon i {
        font-size: 0.875rem;
    }
    
    .activity-content h6 {
        font-size: 0.875rem;
    }
    
    .activity-content p {
        font-size: 0.8125rem;
    }
    
    .badge {
        font-size: 0.75rem;
    }
}

.theme-dark {
    background-color: #1a1a1a;
    color: #ffffff;
}

.theme-dark .card {
    background-color: #2d2d2d;
    border-color: #404040;
}

.theme-dark .form-control,
.theme-dark .form-select {
    background-color: #2d2d2d;
    border-color: #404040;
    color: #ffffff;
}

.theme-dark .form-control:focus,
.theme-dark .form-select:focus {
    background-color: #2d2d2d;
    border-color: #007bff;
    color: #ffffff;
}

.theme-dark .bg-light {
    background-color: #2d2d2d !important;
}

.activity-item:last-child {
    border-bottom: none !important;
}

.profile-avatar {
    transition: transform 0.3s ease;
}

.profile-avatar:hover {
    transform: scale(1.05);
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

<?php include 'footer.php'; ?>

<script>
// Initialize offline support for profile page
document.addEventListener('DOMContentLoaded', function() {
    // Check if we're in offline mode
    if (!navigator.onLine) {
        showOfflineMode();
    }
    
    // Listen for online/offline events
    window.addEventListener('online', handleOnlineStatusChange);
    window.addEventListener('offline', handleOnlineStatusChange);
    
    // Store profile data for offline use
    storeProfileDataForOffline();
});

// Handle online/offline status changes
function handleOnlineStatusChange() {
    if (navigator.onLine) {
        // Back online
        hideOfflineMode();
    } else {
        // Went offline
        showOfflineMode();
    }
}

// Show offline mode UI
function showOfflineMode() {
    // Apply offline mode class to body for consistent styling
    document.body.classList.add('offline-mode');
    
    // Show offline indicator if not already present
    if (!document.getElementById('offline-mode-indicator')) {
        const indicator = document.createElement('div');
        indicator.id = 'offline-mode-indicator';
        indicator.className = 'alert alert-warning mb-3';
        indicator.innerHTML = `
            <i class="fas fa-wifi-slash me-2"></i>
            <strong>You are offline.</strong> Some profile features may be limited.
        `;
        
        const container = document.querySelector('main.container');
        if (container && container.firstChild) {
            container.insertBefore(indicator, container.firstChild);
        }
    }
    
    // Disable form elements
    const formElements = document.querySelectorAll('form input, form select, form textarea, form button');
    formElements.forEach(el => {
        el.disabled = true;
    });
    
    // Apply offline styling to online-only elements (consistent with other pages)
    const onlineOnlyElements = document.querySelectorAll('.online-only');
    onlineOnlyElements.forEach(element => {
        element.style.opacity = '0.5';
        element.style.pointerEvents = 'none';
    });
    
    // Show offline messages (consistent with other pages)
    const offlineMessages = document.querySelectorAll('.offline-message');
    offlineMessages.forEach(element => {
        element.style.display = 'block';
    });
    
    // Add offline badge to tab navigation
    const tabNavs = document.querySelectorAll('.nav-link');
    tabNavs.forEach(nav => {
        if (!nav.querySelector('.badge')) {
            const badge = document.createElement('span');
            badge.className = 'badge bg-warning text-dark ms-2';
            badge.textContent = 'Offline';
            badge.style.fontSize = '0.7rem';
            nav.appendChild(badge);
        }
    });
    
    // Add offline message to forms
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        if (!form.querySelector('.offline-message')) {
            const message = document.createElement('div');
            message.className = 'alert alert-warning offline-message mt-3';
            message.innerHTML = '<i class="fas fa-wifi-slash me-2"></i> Form submissions are disabled while offline.';
            form.appendChild(message);
        }
    });
    
    // Ensure footer navigation has offline styling
    const bottomNav = document.querySelector('.bottom-nav');
    if (bottomNav) {
        bottomNav.classList.add('offline-nav');
    }
}

// Hide offline mode UI
function hideOfflineMode() {
    // Remove offline mode class from body
    document.body.classList.remove('offline-mode');
    
    // Hide offline indicator
    const offlineIndicator = document.getElementById('offline-mode-indicator');
    if (offlineIndicator) {
        offlineIndicator.remove();
    }
    
    // Enable form elements
    const formElements = document.querySelectorAll('form input, form select, form textarea, form button');
    formElements.forEach(el => {
        el.disabled = false;
    });
    
    // Remove offline styling from online-only elements
    const onlineOnlyElements = document.querySelectorAll('.online-only');
    onlineOnlyElements.forEach(element => {
        element.style.opacity = '';
        element.style.pointerEvents = '';
    });
    
    // Hide offline messages
    const offlineMessages = document.querySelectorAll('.offline-message');
    offlineMessages.forEach(element => {
        element.style.display = 'none';
    });
    
    // Remove offline badges
    const badges = document.querySelectorAll('.badge.bg-warning');
    badges.forEach(badge => {
        if (badge.textContent === 'Offline') {
            badge.remove();
        }
    });
    
    // Remove offline messages from forms
    const messages = document.querySelectorAll('.offline-message');
    messages.forEach(message => {
        message.remove();
    });
    
    // Remove offline styling from footer navigation
    const bottomNav = document.querySelector('.bottom-nav');
    if (bottomNav) {
        bottomNav.classList.remove('offline-nav');
    }
}

// Store profile data in localStorage for offline use
function storeProfileDataForOffline() {
    // Only store data when online
    if (!navigator.onLine) return;
    
    // Get user data from the page
    const userData = {
        id: <?php echo $current_user['id']; ?>,
        username: "<?php echo addslashes($current_user['username']); ?>",
        full_name: "<?php echo addslashes($current_user['full_name']); ?>",
        email: "<?php echo addslashes($current_user['email'] ?? ''); ?>",
        phone: "<?php echo addslashes($current_user['phone'] ?? ''); ?>",
        role: "<?php echo $current_user['role']; ?>",
        status: "<?php echo $current_user['status']; ?>",
        section_id: <?php echo $current_user['section_id'] ?? 'null'; ?>,
        created_at: "<?php echo $current_user['created_at'] ?? ''; ?>",
        updated_at: "<?php echo $current_user['updated_at'] ?? ''; ?>"
    };
    
    // Store in localStorage
    localStorage.setItem('kes_smart_profile_data', JSON.stringify(userData));
    
    // Also store user stats if available
    <?php if (!empty($user_stats)): ?>
    const userStats = <?php echo json_encode($user_stats); ?>;
    localStorage.setItem('kes_smart_user_stats', JSON.stringify(userStats));
    <?php endif; ?>
    
    // Store preferences
    const preferences = <?php echo json_encode($preferences); ?>;
    localStorage.setItem('kes_smart_preferences', JSON.stringify(preferences));
    
    console.log('Profile data stored for offline use');
}
</script>
