<?php
// Page access validation
$current_page = basename($_SERVER['PHP_SELF']);
$public_pages = ['index.php', 'login.php', 'logout.php', 'offline-auth.php'];

// Check if current page requires authentication
if (!in_array($current_page, $public_pages)) {
    if (!isLoggedIn()) {
        // Clear any invalid session data
        session_unset();
        
        // Don't redirect if already on login page (prevent infinite redirect)
        if ($current_page !== 'login.php') {
            header('Location: login.php');
            exit();
        }
    } else {
        // User is logged in, update their session activity
        updateSessionActivity();
        
        // Check role-based page access
        if (!checkPageAccess($current_page)) {
            $_SESSION['error'] = 'Access denied. You do not have permission to access this page.';
            header('Location: dashboard.php');
            exit();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
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
            -webkit-tap-highlight-color: transparent; /* Remove tap highlight on mobile */
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
        
        /* Improved Dropdown Menu */
        .dropdown-menu {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            padding: 0.5rem;
            margin-top: 0.5rem;
            z-index: 1050; /* Higher z-index to ensure it appears above other elements */
        }
        
        .dropdown-item {
            border-radius: 10px;
            padding: 0.6rem 1rem;
            margin-bottom: 0.2rem;
            transition: all 0.2s;
        }
        
        .dropdown-item:hover {
            background-color: rgba(0,123,255,0.1);
        }
        
        .dropdown-item:active {
            background-color: var(--primary-color);
            color: white;
        }
        
        .dropdown-item i {
            width: 20px;
            text-align: center;
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
            
            /* Alerts for mobile */
            .alert {
                padding: 0.75rem;
                margin-bottom: 0.75rem;
            }
            
            /* Improved dropdown for mobile */
            .dropdown-menu {
                width: auto;
                min-width: 200px;
                position: absolute;
                right: 0;
                left: auto;
                margin-top: 0.5rem;
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
            bottom: 70px; /* Position above bottom nav */
            left: 0;
            right: 0;
            background-color: #ffc107;
            color: #212529;
            padding: 8px 16px;
            text-align: center;
            z-index: 999;
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
        
        /* Fix for navbar dropdown on mobile */
        .navbar .dropdown-menu {
            position: absolute;
            right: 0;
            left: auto;
            top: 100%;
        }
        
        /* Improved touch targets for mobile */
        @media (max-width: 768px) {
            .dropdown-item {
                padding: 0.75rem 1rem;
            }
            
            .navbar .dropdown-toggle::after {
                margin-left: 0.5rem;
            }
        }
    </style>
    
    <script>
    // Register service worker for PWA with automatic updates
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('sw.js')
                .then(registration => {
                    console.log('Service Worker registered with scope:', registration.scope);
                    
                    // Listen for updates
                    registration.addEventListener('updatefound', () => {
                        console.log('New service worker found, installing...');
                        const newWorker = registration.installing;
                        
                        if (newWorker) {
                            newWorker.addEventListener('statechange', () => {
                                if (newWorker.state === 'installed') {
                                    if (navigator.serviceWorker.controller) {
                                        console.log('New service worker installed, will activate automatically');
                                        // The new service worker will automatically take control
                                    } else {
                                        console.log('Service worker installed for the first time');
                                    }
                                }
                            });
                        }
                    });
                    
                    // Check for updates periodically (every 10 minutes)
                    setInterval(() => {
                        registration.update();
                    }, 10 * 60 * 1000);
                    
                    // Manual update check on visibility change (when user returns to tab)
                    document.addEventListener('visibilitychange', () => {
                        if (!document.hidden) {
                            registration.update();
                        }
                    });
                })
                .catch(error => {
                    console.error('Service Worker registration failed:', error);
                });
                
            // Service worker message listeners removed - updates now happen silently
        });
    }
    
    // Update notification function removed - updates now happen silently
    
    // Remove any lingering update notifications
    function removeUpdateNotifications() {
        // Only target specific notification elements with update-related content
        const specificSelectors = [
            '#pwa-install-banner',
            '.toast-container',
            '[id*="update-notification"]',
            '[class*="update-notification"]'
        ];
        
        specificSelectors.forEach(selector => {
            try {
                document.querySelectorAll(selector).forEach(element => {
                    element.remove();
                    console.log('Removed specific notification element:', element);
                });
            } catch (e) {
                // Ignore selector errors
            }
        });
        
        // Only check elements that are likely to be notifications (not form inputs, cards, etc.)
        const notificationElements = document.querySelectorAll('.alert, .toast, .notification, [role="alert"], .position-fixed');
        notificationElements.forEach(element => {
            if (element.textContent && 
                (element.textContent.includes('A new version of the app is available') ||
                 element.textContent.includes('version') && element.textContent.includes('available') ||
                 element.textContent.includes('reload') && element.textContent.includes('version'))) {
                // Double-check this isn't a form or main content
                if (!element.closest('form') && !element.closest('main') && !element.closest('.card-body')) {
                    element.remove();
                    console.log('Removed notification with version text:', element);
                }
            }
        });
    }

    // Disable any cached notification functions to prevent errors
    window.showUpdateNotification = function() {
        console.log('Update notification disabled');
    };
    
    // Override any service worker message handlers
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.addEventListener('message', function(event) {
            // Ignore all service worker messages about updates
            console.log('Service worker message ignored:', event.data);
        });
    }

    // Online/Offline detection
    document.addEventListener('DOMContentLoaded', function() {
        // Override any notification functions that might be loaded later
        window.showUpdateNotification = function() {
            console.log('Update notification disabled');
        };
        
        // Remove any update notifications immediately
        removeUpdateNotifications();
        
        // Also remove them periodically but less frequently and with safety checks
        setInterval(() => {
            // Only run if page is not a login or critical form page
            if (!document.querySelector('#login-form') && !document.querySelector('form[method="POST"]')) {
                removeUpdateNotifications();
            }
        }, 2000);
        
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
    <script src="assets/js/enhanced-cache-manager.js"></script>
    <script src="assets/js/offline-forms.js"></script>
    <script src="assets/js/cache-manager.js"></script>
    <script src="assets/js/cache-clear.js"></script>
    
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
            <div class="dropdown">
                <button class="btn btn-primary dropdown-toggle border-0" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-user-circle me-1"></i>
                    <span class="d-none d-md-inline">
                        <?php 
                        $current_user = getCurrentUser($pdo);
                        echo $current_user ? $current_user['full_name'] : 'User';
                        ?>
                    </span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                    <?php if (hasRole('admin')): ?>
                        <li><a class="dropdown-item" href="settings.php"><i class="fas fa-cog me-2"></i>Settings</a></li>
                        <li><a class="dropdown-item" href="sms-config.php"><i class="fas fa-sms me-2"></i>SMS Config</a></li>
                        <li><a class="dropdown-item" href="users.php"><i class="fas fa-users me-2"></i>Users</a></li>
                        <li><a class="dropdown-item" href="sections.php"><i class="fas fa-layer-group me-2"></i>Sections & Subjects</a></li>
                        <li><a class="dropdown-item" href="reports.php"><i class="fas fa-chart-bar me-2"></i>Reports</a></li>
                    <?php endif; ?>
                    <?php if (hasRole('teacher')): ?>
                        <li><a class="dropdown-item" href="sections.php"><i class="fas fa-layer-group me-2"></i>Sections & Subjects</a></li>
                        <li><a class="dropdown-item" href="reports.php"><i class="fas fa-chart-bar me-2"></i>Reports</a></li>
                    <?php endif; ?>
                    <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</nav>

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
