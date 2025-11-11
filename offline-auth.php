<?php
// Initialize the session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include required files if available online
if (file_exists('config.php')) {
    require_once 'config.php';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="KES-SMART - Offline Authentication">
    <meta name="theme-color" content="#007bff">
    
    <title>Processing Login - KES-SMART</title>
    
    <!-- PWA Meta Tags -->
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="KES-SMART">
    <meta name="mobile-web-app-capable" content="yes">
    
    <!-- PWA Icons -->
    <link rel="icon" type="image/png" sizes="32x32" href="https://img.icons8.com/color/32/000000/clipboard.png">
    <link rel="icon" type="image/png" sizes="16x16" href="https://img.icons8.com/color/16/000000/clipboard.png">
    <link rel="apple-touch-icon" href="https://img.icons8.com/color/192/000000/clipboard.png">
    <link rel="manifest" href="manifest.json">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            padding-bottom: 80px;
        }
        
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 1rem;
        }
        
        .hero-section {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            border-radius: 0 0 20px 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin: -1rem -15px 1.5rem -15px;
            padding: 2rem 0;
        }
        
        .loading-spinner {
            width: 3rem;
            height: 3rem;
        }
    </style>
</head>
<body>
    <!-- Hero Section -->
    <div class="hero-section text-center">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8 mx-auto">
                    <i class="fas fa-graduation-cap fa-4x mb-3"></i>
                    <h1 class="display-5 fw-bold mb-2">KES-SMART</h1>
                    <p class="lead mb-3">Processing Your Login</p>
                </div>
            </div>
        </div>
    </div>

    <div class="container px-3">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card text-center p-4 shadow-sm">
                    <div class="card-body" id="processing-card">
                        <div class="spinner-border text-primary loading-spinner mb-3" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <h2 class="h4 mb-3">Processing Offline Login</h2>
                        <p class="mb-0">Please wait while we verify your credentials...</p>
                    </div>
                    
                    <div class="card-body d-none" id="error-card">
                        <div class="text-danger mb-3">
                            <i class="fas fa-exclamation-circle fa-4x"></i>
                        </div>
                        <h2 class="h4 mb-3">Login Failed</h2>
                        <p class="mb-4" id="error-message">Unable to process your offline login request.</p>
                        <a href="login.php" class="btn btn-primary">Return to Login</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Process offline login
        document.addEventListener('DOMContentLoaded', function() {
            // Check if we have data from form submission
            const urlParams = new URLSearchParams(window.location.search);
            const formData = new FormData();
            let hasData = false;
            
            // Try to get data from the URL parameters first
            if (urlParams.has('username') && urlParams.has('role')) {
                const username = urlParams.get('username');
                const role = urlParams.get('role');
                const userData = urlParams.get('user_data');
                
                if (username && role && userData) {
                    hasData = true;
                    processOfflineLogin(username, role, userData);
                }
            }
            
            // If no URL parameters, try to get from localStorage (fallback)
            if (!hasData) {
                const offlineLoginData = localStorage.getItem('offline_login_data');
                if (offlineLoginData) {
                    try {
                        const data = JSON.parse(offlineLoginData);
                        if (data.username && data.role && data.userData) {
                            processOfflineLogin(data.username, data.role, data.userData);
                        } else {
                            showError('Missing required login data');
                        }
                    } catch (e) {
                        showError('Invalid login data format');
                    }
                } else {
                    showError('No offline login data found');
                }
            }
        });
        
        function processOfflineLogin(username, role, userData) {
            try {
                // Parse user data if it's a string
                let parsedUserData;
                if (typeof userData === 'string') {
                    parsedUserData = JSON.parse(userData);
                } else {
                    parsedUserData = userData;
                }
                
                // Store session data in localStorage with timestamp
                const sessionData = {
                    user_id: parsedUserData.id,
                    username: parsedUserData.username,
                    full_name: parsedUserData.full_name,
                    role: parsedUserData.role,
                    section_id: parsedUserData.section_id,
                    offline_mode: true,
                    offline_login: true,
                    timestamp: Date.now() // Add timestamp for session validation
                };
                
                // Store the session for offline use
                localStorage.setItem('kes_smart_session', JSON.stringify(sessionData));
                
                // Set cookie to indicate offline login
                document.cookie = "kes_smart_offline_logged_in=1; path=/; max-age=604800"; // 7 days
                
                // Show success message briefly before redirecting
                document.getElementById('processing-card').innerHTML = `
                    <div class="text-success mb-3">
                        <i class="fas fa-check-circle fa-4x"></i>
                    </div>
                    <h2 class="h4 mb-3">Login Successful</h2>
                    <p class="mb-4">Logged in as ${parsedUserData.full_name} (${parsedUserData.role})</p>
                    <div class="spinner-border spinner-border-sm text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="small text-muted">Redirecting to dashboard...</p>
                `;
                
                // Redirect to dashboard after a brief delay
                setTimeout(() => {
                    window.location.href = 'dashboard.php';
                }, 1500);
                
            } catch (error) {
                showError('Error processing login data: ' + error.message);
            }
        }
        
        function showError(message) {
            document.getElementById('processing-card').classList.add('d-none');
            document.getElementById('error-card').classList.remove('d-none');
            document.getElementById('error-message').textContent = message;
        }
    </script>
    
    <?php
    // Server-side processing (when online)
    if (isset($_POST['offline_login']) && $_POST['offline_login'] == '1') {
        $username = isset($_POST['username']) ? htmlspecialchars(strip_tags(trim($_POST['username']))) : '';
        $role = isset($_POST['role']) ? htmlspecialchars(strip_tags(trim($_POST['role']))) : '';
        $user_data = isset($_POST['user_data']) ? $_POST['user_data'] : '';
        
        if (empty($username) || empty($role) || empty($user_data)) {
            // Just let the client-side handle it when offline
            echo '<script>showError("Invalid offline login attempt. Missing required data.");</script>';
        } else {
            try {
                // Parse the user data from the offline storage
                $user = json_decode($user_data, true);
                
                if ($user) {
                    // Set session variables from stored offline data
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['section_id'] = $user['section_id'];
                    $_SESSION['offline_mode'] = true;
                    $_SESSION['LAST_ACTIVITY'] = time(); // Initialize session timeout tracking
                    
                    // Set a flag to indicate offline login
                    $_SESSION['offline_login'] = true;
                    $_SESSION['success'] = 'Logged in offline as ' . $user['full_name'] . '. Some features may be limited.';
                    
                    // Redirect to dashboard
                    echo '<script>window.location.href = "dashboard.php";</script>';
                } else {
                    echo '<script>showError("Invalid user data format.");</script>';
                }
            } catch (Exception $e) {
                echo '<script>showError("Offline login processing error: ' . addslashes($e->getMessage()) . '");</script>';
            }
        }
    }
    ?>
</body>
</html> 