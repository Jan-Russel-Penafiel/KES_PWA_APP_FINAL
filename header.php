<?php
// Page access validation
$current_page = basename($_SERVER['PHP_SELF']);
$public_pages = ['index.php', 'login.php', 'logout.php'];

// Check if current page requires authentication
if (!in_array($current_page, $public_pages)) {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
    
    // Check role-based page access
    if (!checkPageAccess($current_page)) {
        $_SESSION['error'] = 'Access denied. You do not have permission to access this page.';
        header('Location: dashboard.php');
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?php echo $site_description; ?>">
    <meta name="theme-color" content="#007bff">
    
    <!-- PWA Meta Tags -->
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="KES-SMART">
    <meta name="mobile-web-app-capable" content="yes">
    
    <!-- PWA Icons -->
    <link rel="icon" type="image/png" sizes="32x32" href="https://img.icons8.com/color/32/000000/clipboard.png">
    <link rel="icon" type="image/png" sizes="16x16" href="https://img.icons8.com/color/16/000000/clipboard.png">
    <link rel="apple-touch-icon" href="https://img.icons8.com/color/192/000000/clipboard.png">
    <link rel="manifest" href="manifest.json" crossorigin="use-credentials">
    
    <title><?php echo isset($page_title) ? $page_title . ' - ' . $site_name : $site_name; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet">
    
    <!-- PWA CSS -->
    <link href="assets/css/pwa.css" rel="stylesheet">
    
    <!-- Additional CSS -->
    <?php if (isset($additional_css)) echo $additional_css; ?>
    
    <style>
        :root {
            --primary-color: #007bff;
            --secondary-color: #6c757d;
            --success-color: #28a745;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --info-color: #17a2b8;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            padding-bottom: 80px; /* Space for bottom navigation */
            overflow-x: hidden; /* Prevent horizontal scrolling on mobile */
        }
        
        .navbar-brand {
            font-weight: bold;
            font-size: 1.5rem;
        }
        
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 1rem;
            transition: transform 0.2s ease-in-out;
        }
        
        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }
        
        .btn {
            border-radius: 25px;
            padding: 0.6rem 1.5rem;
            font-weight: 500;
        }
        
        .btn-primary {
            background: linear-gradient(45deg, #007bff, #0056b3);
            border: none;
        }
        
        .btn-success {
            background: linear-gradient(45deg, #28a745, #1e7e34);
            border: none;
        }
        
        .badge {
            border-radius: 25px;
            padding: 0.4rem 0.8rem;
        }
        
        /* Offline Mode Styles */
        .offline-indicator {
            display: none;
        }
        
        .offline-indicator.active {
            display: flex;
            align-items: center;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
        
        .offline-mode .online-only {
            opacity: 0.5;
            pointer-events: none;
        }
        
        .offline-mode .offline-message {
            display: block !important;
        }
        
        .offline-message {
            display: none;
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 0.75rem 1.25rem;
            margin-bottom: 1rem;
            border-radius: 0.25rem;
        }
        
        .offline-alert {
            padding: 10px 15px;
            animation: slideIn 0.3s ease-out;
        }
        
        @keyframes slideIn {
            from { transform: translateY(100%); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        .status-indicator {
            position: absolute;
            bottom: 5px;
            right: 5px;
        }
        
        /* Mobile Optimizations */
        @media (max-width: 768px) {
            body {
                padding-bottom: 70px; /* Adjusted for smaller bottom nav */
            }
            
            .container {
                padding-left: 12px;
                padding-right: 12px;
            }
            
            .navbar-brand {
                font-size: 1.3rem;
            }
            
            .bottom-nav {
                padding: 0.3rem 0;
            }
            
            .bottom-nav .nav-link i {
                font-size: 1.1rem;
                margin-bottom: 0.1rem;
            }
            
            .bottom-nav .nav-link span {
                font-size: 0.7rem;
            }
            
            .btn {
                padding: 0.5rem 1.2rem;
            }
            
            .card {
                margin-bottom: 0.75rem;
            }
            
            /* Improve touch targets */
            .nav-link, .btn, .dropdown-item {
                min-height: 44px;
                display: flex;
                align-items: center;
            }
            
            .dropdown-menu {
                box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            }
            
            /* Alerts for mobile */
            .alert {
                padding: 0.75rem;
                margin-bottom: 0.75rem;
            }
        }
        
        /* Small mobile devices */
        @media (max-width: 576px) {
            .navbar-brand {
                font-size: 1.2rem;
            }
            
            .mt-4 {
                margin-top: 1rem !important;
            }
            
            .bottom-nav .nav-link span {
                font-size: 0.65rem;
            }
            
            /* Optimize alerts for very small screens */
            .alert {
                padding: 0.6rem;
                font-size: 0.9rem;
            }
            
            /* Adjust profile dropdown on small screens */
            .dropdown-menu {
                width: 100%;
                left: 0 !important;
                right: 0 !important;
            }
        }
        
        /* Bottom Navigation */
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            border-top: 1px solid #dee2e6;
            z-index: 1000;
            padding: 0.5rem 0;
            box-shadow: 0 -4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .bottom-nav .nav-item {
            flex: 1;
            text-align: center;
        }
        
        .bottom-nav .nav-link {
            color: #6c757d;
            padding: 0.5rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-decoration: none;
            transition: color 0.2s;
        }
        
        .bottom-nav .nav-link.active,
        .bottom-nav .nav-link:hover {
            color: var(--primary-color);
        }
        
        .bottom-nav .nav-link i {
            font-size: 1.2rem;
            margin-bottom: 0.2rem;
        }
        
        .bottom-nav .nav-link span {
            font-size: 0.8rem;
        }
        
        .qr-scanner {
            background: #000;
            border-radius: 15px;
            overflow: hidden;
        }
        
        .attendance-card {
            border-left: 4px solid var(--primary-color);
        }
        
        .attendance-card.present {
            border-left-color: var(--success-color);
        }
        
        .attendance-card.absent {
            border-left-color: var(--danger-color);
        }
        
        .attendance-card.late {
            border-left-color: var(--warning-color);
        }
        
        .student-card {
            position: relative;
            overflow: hidden;
        }
        
        .student-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--primary-color), var(--info-color));
        }
        
        .profile-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid white;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        /* Offline banner styles */
        #offline-banner {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background-color: #ffc107;
            color: #212529;
            padding: 8px 16px;
            text-align: center;
            z-index: 9999;
            font-weight: bold;
            display: none;
            transform: translateY(100%);
            transition: transform 0.3s ease-in-out;
            box-shadow: 0 -2px 5px rgba(0,0,0,0.1);
        }
        
        #offline-banner.visible {
            transform: translateY(0);
            display: block;
        }
        
        /* Add more space when offline banner is visible */
        body.has-offline-banner {
            padding-bottom: 120px;
        }
    </style>
    
    <script>
    // Register service worker for PWA
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('sw.js')
                .then(registration => {
                    console.log('Service Worker registered with scope:', registration.scope);
                })
                .catch(error => {
                    console.error('Service Worker registration failed:', error);
                });
        });
    }
    
    // Online/Offline detection
    document.addEventListener('DOMContentLoaded', function() {
        const offlineBanner = document.createElement('div');
        offlineBanner.id = 'offline-banner';
        offlineBanner.innerHTML = '<i class="fas fa-wifi-slash me-2"></i> You are currently offline. Some features may be limited.';
        document.body.appendChild(offlineBanner);
        
        function updateOnlineStatus() {
            const isOnline = navigator.onLine;
            
            // Update body class
            if (!isOnline) {
                document.body.classList.add('offline-mode');
                document.body.classList.add('has-offline-banner');
                offlineBanner.classList.add('visible');
            } else {
                document.body.classList.remove('offline-mode');
                document.body.classList.remove('has-offline-banner');
                offlineBanner.classList.remove('visible');
            }
            
            // Update all offline indicators
            document.querySelectorAll('.offline-indicator').forEach(el => {
                if (!isOnline) {
                    el.classList.add('active');
                } else {
                    el.classList.remove('active');
                }
            });
            
            // Disable online-only elements when offline
            document.querySelectorAll('.online-only').forEach(el => {
                el.disabled = !isOnline;
            });
            
            // Store the current status in localStorage
            localStorage.setItem('kes_smart_online_status', isOnline ? 'online' : 'offline');
        }
        
        // Listen for online/offline events
        window.addEventListener('online', updateOnlineStatus);
        window.addEventListener('offline', updateOnlineStatus);
        
        // Initial check
        updateOnlineStatus();
        
        // Initialize IndexedDB for offline data if available
        if ('indexedDB' in window && typeof initOfflineDB === 'function') {
            initOfflineDB().catch(error => {
                console.error('Failed to initialize offline database:', error);
            });
        }
    });
    </script>
    
    <!-- Offline Support Scripts -->
    <script src="assets/js/sw-updater.js"></script>
    <script src="assets/js/offline-forms.js"></script>
    
    <?php if (isset($additional_head)) echo $additional_head; ?>
</head>
<body class="<?php echo isOfflineMode() ? 'offline-mode' : ''; ?>">
<?php if (isOfflineMode()): ?><div id="offline-banner" class="visible"><i class="fas fa-wifi-slash me-2"></i> You are currently offline. Some features may be limited.</div><?php endif; ?>

<!-- Top Navigation -->
<nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top">
    <div class="container">
        <a class="navbar-brand" href="index.php">
            <i class="fas fa-graduation-cap me-2"></i>
            KES-SMART
        </a>
        
        <?php if (isLoggedIn()): ?>
            <div class="navbar-nav ms-auto">
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle text-white" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <?php 
                        $current_user = getCurrentUser($pdo);
                        echo $current_user ? $current_user['full_name'] : 'User';
                        ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <?php if (hasRole('student')): ?>
                            <li><a class="dropdown-item" href="student-profile.php"><i class="fas fa-user-graduate me-2"></i>My Profile</a></li>
                        <?php else: ?>
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                        <?php endif; ?>
                        <?php if (hasRole('admin')): ?>
                            <li><a class="dropdown-item" href="settings.php"><i class="fas fa-cog me-2"></i>Settings</a></li>
                            <li><a class="dropdown-item" href="sms-config.php"><i class="fas fa-sms me-2"></i>SMS Config</a></li>
                            <li><a class="dropdown-item" href="users.php"><i class="fas fa-users me-2"></i>Users</a></li>
                            <li><a class="dropdown-item" href="sections.php"><i class="fas fa-sms me-2"></i>Sections</a></li>
                        <?php endif; ?>
                        <?php if (hasRole('teacher')): ?>
                          
                            <li><a class="dropdown-item" href="sections.php"><i class="fas fa-sms me-2"></i>Sections</a></li>
                        <?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        <?php endif; ?>
    </div>
</nav>

<!-- Offline Status Indicator -->
<div id="offline-indicator" class="offline-indicator bg-warning text-dark py-1 px-3" style="display: none;">
    <i class="fas fa-wifi-slash me-2"></i> You are offline. Some features may be limited.
</div>

<?php if (isset($_SESSION['offline_login']) && $_SESSION['offline_login']): ?>
<!-- Offline Login Indicator -->
<div class="alert alert-warning text-center py-1 mb-0">
    <i class="fas fa-user-clock me-2"></i> You are logged in using offline credentials. Some features may be unavailable.
</div>
<?php endif; ?>

<!-- Main Content -->
<main class="container mt-4">
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i>
            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['warning'])): ?>
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <?php echo $_SESSION['warning']; unset($_SESSION['warning']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['info'])): ?>
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            <i class="fas fa-info-circle me-2"></i>
            <?php echo $_SESSION['info']; unset($_SESSION['info']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
<!-- Bottom Navigation for Mobile -->
<?php if (isLoggedIn()): ?>
<nav class="bottom-nav d-md-none">
    <div class="container">
        <div class="d-flex justify-content-between">
            <a href="dashboard.php" class="nav-link <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            <a href="attendance.php" class="nav-link <?php echo $current_page == 'attendance.php' ? 'active' : ''; ?>">
                <i class="fas fa-clipboard-check"></i>
                <span>Attendance</span>
            </a>
            <a href="qr-scanner.php" class="nav-link <?php echo $current_page == 'qr-scanner.php' ? 'active' : ''; ?>">
                <i class="fas fa-qrcode"></i>
                <span>Scan</span>
            </a>
            <a href="students.php" class="nav-link <?php echo $current_page == 'students.php' ? 'active' : ''; ?>">
                <i class="fas fa-user-graduate"></i>
                <span>Students</span>
            </a>
            <a href="profile.php" class="nav-link <?php echo $current_page == 'profile.php' ? 'active' : ''; ?>">
                <i class="fas fa-user"></i>
                <span>Profile</span>
            </a>
        </div>
    </div>
</nav>
<?php endif; ?>

<script>
    // Offline status detection and indicator
    function updateOnlineStatus() {
        const indicator = document.getElementById('offline-indicator');
        const body = document.body;
        const isOfflineLogin = <?php echo isset($_SESSION['offline_login']) && $_SESSION['offline_login'] ? 'true' : 'false'; ?>;
        
        if (!navigator.onLine) {
            indicator.style.display = 'block';
            body.classList.add('offline-mode');
            
            // Store offline state in local storage
            localStorage.setItem('kes_smart_offline_mode', 'true');
        } else {
            // Only hide offline indicator if not in offline login mode
            if (!isOfflineLogin) {
                indicator.style.display = 'none';
                body.classList.remove('offline-mode');
            }
            
            // Check for stored data to sync
            if (localStorage.getItem('kes_smart_offline_mode') === 'true') {
                localStorage.removeItem('kes_smart_offline_mode');
                
                // Trigger sync if back online after being offline
                if (typeof syncOfflineData === 'function') {
                    syncOfflineData();
                }
            }
        }
    }

    window.addEventListener('online', updateOnlineStatus);
    window.addEventListener('offline', updateOnlineStatus);
    
    // Initial check
    document.addEventListener('DOMContentLoaded', updateOnlineStatus);
    
    // Highlight active nav item in bottom navigation
    document.addEventListener('DOMContentLoaded', function() {
        const currentPage = '<?php echo $current_page; ?>';
        const navLinks = document.querySelectorAll('.bottom-nav .nav-link');
        
        navLinks.forEach(link => {
            const href = link.getAttribute('href');
            if (href === currentPage) {
                link.classList.add('active');
            }
        });
    });
</script>
