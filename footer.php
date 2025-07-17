    </main>

    <?php if (isLoggedIn()): ?>
        <!-- Bottom Navigation -->
        <nav class="bottom-nav">
            <div class="d-flex">
                <?php
                $current_page = basename($_SERVER['PHP_SELF']);
                $user_role = $_SESSION['role'] ?? '';
                ?>
                
                <!-- Dashboard -->
                <div class="nav-item">
                    <a href="dashboard.php" class="nav-link <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
                        <i class="fas fa-home"></i>
                        <span>Home</span>
                    </a>
                </div>
                
                <?php if ($user_role == 'teacher' || $user_role == 'admin'): ?>
                    <!-- QR Scanner - Only for teachers and admins -->
                    <div class="nav-item">
                        <a href="qr-scanner.php" class="nav-link <?php echo $current_page == 'qr-scanner.php' ? 'active' : ''; ?>">
                            <i class="fas fa-qrcode"></i>
                            <span>Scanner</span>
                        </a>
                    </div>
                    
                    <!-- Students -->
                    <div class="nav-item">
                        <a href="students.php" class="nav-link <?php echo $current_page == 'students.php' ? 'active' : ''; ?>">
                            <i class="fas fa-users"></i>
                            <span>Students</span>
                        </a>
                    </div>

                    <!-- Reports for teachers and admins -->
                    <div class="nav-item">
                        <a href="reports.php" class="nav-link <?php echo $current_page == 'reports.php' ? 'active' : ''; ?>">
                            <i class="fas fa-chart-bar"></i>
                            <span>Reports</span>
                        </a>
                    </div>
                <?php endif; ?>
                
                <?php if ($user_role == 'student' || $user_role == 'parent'): ?>
                    <!-- Attendance -->
                    <div class="nav-item">
                        <a href="attendance.php" class="nav-link <?php echo $current_page == 'attendance.php' ? 'active' : ''; ?>">
                            <i class="fas fa-calendar-check"></i>
                            <span>Attendance</span>
                        </a>
                    </div>
                <?php endif; ?>
                
                <?php if ($user_role == 'admin'): ?>
                    <!-- Users Management - Only for admins -->
                    <div class="nav-item">
                        <a href="users.php" class="nav-link <?php echo $current_page == 'users.php' ? 'active' : ''; ?>">
                            <i class="fas fa-user-cog"></i>
                            <span>Users</span>
                        </a>
                    </div>
                    
                    <!-- SMS Config - Only for admins -->
                    <div class="nav-item">
                        <a href="sms-config.php" class="nav-link <?php echo $current_page == 'sms-config.php' ? 'active' : ''; ?>">
                            <i class="fas fa-sms"></i>
                            <span>SMS</span>
                        </a>
                    </div>
                <?php endif; ?>
                
                <!-- Reports -->
              
                
                <!-- Profile -->
                <div class="nav-item">
                    <a href="profile.php" class="nav-link <?php echo $current_page == 'profile.php' ? 'active' : ''; ?>">
                        <i class="fas fa-user"></i>
                        <span>Profile</span>
                    </a>
                </div>
            </div>
        </nav>
    <?php endif; ?>

    <?php if (isLoggedIn()): ?>
    <!-- Bottom Navigation for Mobile -->
    <nav class="bottom-nav d-md-none">
        <div class="container">
            <div class="d-flex justify-content-between">
                <a href="dashboard.php" class="nav-link <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
                <a href="attendance.php" class="nav-link <?php echo ($current_page == 'attendance.php') ? 'active' : ''; ?>">
                    <i class="fas fa-clipboard-check"></i>
                    <span>Attendance</span>
                </a>
                <a href="qr-scanner.php" class="nav-link <?php echo ($current_page == 'qr-scanner.php') ? 'active' : ''; ?>">
                    <i class="fas fa-qrcode"></i>
                    <span>Scan</span>
                </a>
                <a href="students.php" class="nav-link <?php echo ($current_page == 'students.php') ? 'active' : ''; ?>">
                    <i class="fas fa-user-graduate"></i>
                    <span>Students</span>
                </a>
                <a href="profile.php" class="nav-link <?php echo ($current_page == 'profile.php') ? 'active' : ''; ?>">
                    <i class="fas fa-user-circle"></i>
                    <span>Profile</span>
                </a>
            </div>
        </div>
    </nav>
    <?php endif; ?>
    
    <!-- Offline Status Indicator -->
    <div class="offline-indicator">
        <i class="fas fa-wifi-slash me-2"></i> Offline
    </div>
    
    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Select2 -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
    <script>
        // Initialize Select2
        $(document).ready(function() {
            $('.select2').select2({
                theme: 'bootstrap-5'
            });
            
            // Show/hide password toggle
            $('.password-toggle').on('click', function() {
                const passwordInput = $(this).siblings('input');
                const icon = $(this).find('i');
                
                if (passwordInput.attr('type') === 'password') {
                    passwordInput.attr('type', 'text');
                    icon.removeClass('fa-eye').addClass('fa-eye-slash');
                } else {
                    passwordInput.attr('type', 'password');
                    icon.removeClass('fa-eye-slash').addClass('fa-eye');
                }
            });
            
            // Offline mode handling
            function updateOfflineStatus() {
                if (!navigator.onLine) {
                    // We're offline
                    $('.online-only').addClass('disabled').attr('disabled', true);
                    $('.offline-indicator').addClass('active');
                    
                    // Show offline message if not already shown
                    if ($('.offline-message:visible').length === 0) {
                        const offlineAlert = $('<div class="offline-message mb-3" role="alert">' +
                            '<i class="fas fa-wifi-slash me-2"></i>' +
                            'You are currently offline. Some features may be limited.' +
                            '</div>');
                        
                        // Add to the top of the main content
                        $('.container:first').prepend(offlineAlert);
                    }
                } else {
                    // We're online
                    $('.online-only').removeClass('disabled').attr('disabled', false);
                    $('.offline-indicator').removeClass('active');
                    $('.offline-message').remove();
                }
            }
            
            // Check offline status on page load
            updateOfflineStatus();
            
            // Listen for online/offline events
            window.addEventListener('online', updateOfflineStatus);
            window.addEventListener('offline', updateOfflineStatus);
        });
    </script>
    
    <?php if (isset($additional_scripts)) echo $additional_scripts; ?>
    
    <script>
        // Initialize Select2
        $(document).ready(function() {
            $('.select2').select2({
                theme: 'bootstrap-5',
                width: '100%'
            });
        });
        
        // PWA Installation
        let deferredPrompt;
        
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;
            showInstallPromotion();
        });
        
        function showInstallPromotion() {
            // Show install button or banner
            const installBtn = document.getElementById('install-btn');
            if (installBtn) {
                installBtn.style.display = 'block';
                installBtn.addEventListener('click', () => {
                    deferredPrompt.prompt();
                    deferredPrompt.userChoice.then((choiceResult) => {
                        if (choiceResult.outcome === 'accepted') {
                            console.log('User accepted the A2HS prompt');
                        }
                        deferredPrompt = null;
                    });
                });
            }
        }
        
        // Service Worker Registration
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('sw.js')
                    .then((registration) => {
                        console.log('ServiceWorker registration successful');
                    }, (error) => {
                        console.log('ServiceWorker registration failed');
                    });
            });
        }
        
        // Auto-refresh notifications
        function checkNotifications() {
            if ('Notification' in window && Notification.permission === 'granted') {
                // Check for new notifications via AJAX
                fetch('api/check-notifications.php')
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }
                        return response.text();
                    })
                    .then(text => {
                        if (!text.trim()) {
                            console.warn('Empty response from check-notifications.php');
                            return { hasNew: false, notifications: [] };
                        }
                        try {
                            return JSON.parse(text);
                        } catch (parseError) {
                            console.error('JSON parse error:', parseError, 'Response text:', text);
                            return { hasNew: false, notifications: [] };
                        }
                    })
                    .then(data => {
                        if (data && data.hasNew) {
                            data.notifications.forEach(notification => {
                                new Notification(notification.title, {
                                    body: notification.message,
                                    icon: 'assets/icons/icon-192x192.png'
                                });
                            });
                        }
                    })
                    .catch(error => console.error('Error checking notifications:', error));
            }
        }
        
        // Request notification permission
        if ('Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission();
        }
        
        // Check notifications every 30 seconds
        setInterval(checkNotifications, 30000);
        
        // Smooth card animations
        function animateCards() {
            const cards = document.querySelectorAll('.card');
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('fade-in');
                    }
                });
            });
            
            cards.forEach(card => {
                observer.observe(card);
            });
        }
        
        // Initialize animations
        document.addEventListener('DOMContentLoaded', animateCards);
        
        // Auto-dismiss alerts
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
        
        // Loading states for buttons
        function showLoading(button) {
            const originalText = button.innerHTML;
            button.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Loading...';
            button.disabled = true;
            
            return function hideLoading() {
                button.innerHTML = originalText;
                button.disabled = false;
            };
        }
        
        // AJAX form submission helper
        function submitForm(formElement, successCallback = null) {
            const formData = new FormData(formElement);
            const hideLoading = showLoading(formElement.querySelector('button[type="submit"]'));
            
            fetch(formElement.action, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    if (successCallback) {
                        successCallback(data);
                    } else {
                        location.reload();
                    }
                } else {
                    alert(data.error || 'An error occurred');
                }
            })
            .catch(error => {
                hideLoading();
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            });
        }
        
        // Real-time date and time
        function updateDateTime() {
            const now = new Date();
            const options = { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            };
            const dateTimeElement = document.getElementById('current-datetime');
            if (dateTimeElement) {
                dateTimeElement.textContent = now.toLocaleDateString('en-US', options);
            }
        }
        
        // Update time every minute
        setInterval(updateDateTime, 60000);
        updateDateTime();
    </script>
</body>
</html>
