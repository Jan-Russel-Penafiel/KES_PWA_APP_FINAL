<?php
require_once 'config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

// Update session activity
updateSessionActivity();

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
            // Remove password change functionality since it was in removed tabs
            $_SESSION['error'] = 'Password change functionality not available.';
            
        } elseif ($action == 'update_preferences') {
            // Remove preferences update functionality since it was in removed tabs
            $_SESSION['error'] = 'Preferences update functionality not available.';
        }
    }
    
    redirect('profile.php');
}

$page_title = 'My Profile';
include 'header.php';

// Remove user statistics functionality

// Get user preferences (minimal for simplified profile)
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



<!-- Profile Tabs -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-user me-2"></i>Personal Information
                </h5>
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
    
    /* Bottom navigation mobile-specific improvements */
    body.has-bottom-nav {
        padding-bottom: 80px;
    }
    
    /* Remove offline banner spacing conflicts with bottom nav */
    body.offline-mode.has-bottom-nav {
        padding-bottom: 80px; /* Keep consistent with online mode */
    }
    
    /* Ensure proper spacing for main content */
    main.container {
        margin-bottom: 1rem;
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
    // Add bottom nav body class for proper spacing
    document.body.classList.add('has-bottom-nav');
    
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
}

// Hide offline mode UI
function hideOfflineMode() {
    // Remove offline mode class from body
    document.body.classList.remove('offline-mode');
    
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
    
    // Store preferences in localStorage (minimal for simplified profile)
    const preferences = <?php echo json_encode(['timezone' => $preferences['timezone']]); ?>;
    localStorage.setItem('kes_smart_preferences', JSON.stringify(preferences));
    
    console.log('Profile data stored for offline use');
}
</script>
