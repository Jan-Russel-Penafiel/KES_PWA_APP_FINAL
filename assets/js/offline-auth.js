/**
 * KES-SMART Offline Authentication System
 * Handles role-based authentication without redirect loops
 */

// Initialize offline authentication on page load
document.addEventListener('DOMContentLoaded', function() {
    // Check if we need to verify offline authentication
    const needsOfflineAuth = document.body.dataset.checkOfflineAuth === 'true';
    
    if (needsOfflineAuth) {
        checkOfflineSession();
    }
    
    // Listen for online/offline events
    window.addEventListener('online', handleOnlineStatus);
    window.addEventListener('offline', handleOfflineStatus);
    
    // Check initial connection status
    updateConnectionStatus();
});

/**
 * Check if user has a valid offline session
 */
function checkOfflineSession() {
    try {
        const sessionData = localStorage.getItem('kes_smart_session');
        
        if (sessionData) {
            const userData = JSON.parse(sessionData);
            
            // Validate session data
            if (!userData.user_id || !userData.username || !userData.role) {
                console.error('Invalid offline session data');
                redirectToLogin();
                return;
            }
            
            // Validate session hasn't expired (7 days)
            const sessionAge = Date.now() - (userData.timestamp || 0);
            const maxAge = 7 * 24 * 60 * 60 * 1000; // 7 days
            
            if (sessionAge > maxAge) {
                console.log('Offline session expired');
                localStorage.removeItem('kes_smart_session');
                redirectToLogin();
                return;
            }
            
            // Session is valid, show dashboard content
            showDashboardContent(userData);
            
            console.log('Offline session loaded successfully for:', userData.username, '(', userData.role, ')');
            
            // If we're back online, try to sync the session without redirect loop
            if (navigator.onLine) {
                syncSessionWithServer(userData);
            }
        } else {
            // No offline session found, redirect to login
            console.log('No offline session found');
            redirectToLogin();
        }
    } catch (error) {
        console.error('Error checking offline session:', error);
        redirectToLogin();
    }
}

/**
 * Show dashboard content with user data
 */
function showDashboardContent(userData) {
    // Show dashboard content
    const dashboardContent = document.getElementById('dashboard-content');
    if (dashboardContent) {
        dashboardContent.style.display = 'block';
    }
    
    // Hide auth required message
    const authRequired = document.getElementById('offline-auth-required');
    if (authRequired) {
        authRequired.classList.add('d-none');
    }
    
    // Update the UI with user data
    const welcomeMessage = document.querySelector('#welcome-message');
    if (welcomeMessage) {
        welcomeMessage.textContent = 'Welcome back, ' + userData.full_name + '!';
    }
    
    // Set user role
    const roleBadge = document.querySelector('#role-badge');
    if (roleBadge) {
        roleBadge.textContent = userData.role.charAt(0).toUpperCase() + userData.role.slice(1);
    }
    
    // Show offline indicator
    const offlineIndicator = document.getElementById('offline-indicator');
    if (offlineIndicator) {
        offlineIndicator.classList.remove('d-none');
    }
    
    // Add offline mode notification
    const dashboardContainer = document.getElementById('dashboard-content');
    if (dashboardContainer && !document.getElementById('offline-mode-alert')) {
        const offlineAlert = document.createElement('div');
        offlineAlert.id = 'offline-mode-alert';
        offlineAlert.className = 'alert alert-warning alert-dismissible fade show mb-3';
        offlineAlert.innerHTML = `
            <i class="fas fa-wifi-slash me-2"></i>
            <strong>Offline Mode:</strong> You're browsing with cached data. Some features may be limited.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        dashboardContainer.insertBefore(offlineAlert, dashboardContainer.firstChild);
    }
    
    // Load role-specific content
    loadOfflineRoleContent(userData.role, userData);
}

/**
 * Sync session with server when back online
 */
function syncSessionWithServer(userData) {
    // Set a flag to prevent multiple sync attempts
    if (window.sessionSyncInProgress) return;
    window.sessionSyncInProgress = true;
    
    fetch('api/auth.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            action: 'verify',
            username: userData.username,
            role: userData.role
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('Session synced with server successfully');
            
            // Update the offline indicator to show we're back online
            const offlineIndicator = document.getElementById('offline-indicator');
            if (offlineIndicator) {
                offlineIndicator.innerHTML = `
                    <span class="badge bg-success mt-1">
                        <i class="fas fa-wifi me-1"></i>Back Online - Synced
                    </span>
                `;
                
                // Hide after 3 seconds
                setTimeout(() => {
                    offlineIndicator.classList.add('d-none');
                }, 3000);
            }
            
            // Remove offline mode alert
            const offlineAlert = document.getElementById('offline-mode-alert');
            if (offlineAlert) {
                offlineAlert.remove();
            }
            
            // Show success toast
            console.log('[SUCCESS] Session synchronized with server');
        } else {
            console.log('Session verification failed:', data.message);
        }
    })
    .catch(error => {
        console.log('Could not sync session (still offline):', error);
    })
    .finally(() => {
        window.sessionSyncInProgress = false;
    });
}

/**
 * Load role-specific content for offline mode
 */
function loadOfflineRoleContent(role, userData) {
    console.log('Loading offline content for role:', role);
    
    // Update statistics based on cached data
    const cachedStats = localStorage.getItem(`kes_smart_stats_${userData.user_id}`);
    if (cachedStats) {
        try {
            const stats = JSON.parse(cachedStats);
            updateDashboardStats(stats);
        } catch (e) {
            console.error('Error loading cached stats:', e);
        }
    }
    
    // Load cached attendance records if student
    if (role === 'student') {
        loadCachedAttendance(userData.user_id);
    }
}

/**
 * Update dashboard statistics
 */
function updateDashboardStats(stats) {
    if (stats && typeof stats === 'object') {
        for (const [key, value] of Object.entries(stats)) {
            const element = document.getElementById(`stat-${key}`);
            if (element) {
                element.textContent = value;
            }
        }
    }
}

/**
 * Load cached attendance records
 */
function loadCachedAttendance(userId) {
    const cachedAttendance = localStorage.getItem(`kes_smart_attendance_${userId}`);
    if (cachedAttendance) {
        try {
            const attendance = JSON.parse(cachedAttendance);
            // Update attendance UI if applicable
            console.log('Loaded cached attendance:', attendance);
        } catch (e) {
            console.error('Error loading cached attendance:', e);
        }
    }
}

/**
 * Redirect to login without causing loops
 */
function redirectToLogin() {
    // Check if we're already on the login page
    if (window.location.pathname.includes('login.php')) {
        return;
    }
    
    // Set a flag to prevent redirect loops
    const redirectAttempts = parseInt(sessionStorage.getItem('redirect_attempts') || '0');
    
    if (redirectAttempts < 3) {
        sessionStorage.setItem('redirect_attempts', (redirectAttempts + 1).toString());
        
        // Show auth required message instead of redirecting immediately
        const authRequired = document.getElementById('offline-auth-required');
        if (authRequired) {
            authRequired.classList.remove('d-none');
        }
        
        // Delay redirect to allow user to see the message
        setTimeout(() => {
            window.location.href = 'login.php';
        }, 2000);
    } else {
        // Too many redirect attempts, show error
        const authRequired = document.getElementById('offline-auth-required');
        if (authRequired) {
            authRequired.classList.remove('d-none');
            authRequired.innerHTML = `
                <div class="alert alert-danger" role="alert">
                    <h4 class="alert-heading"><i class="fas fa-exclamation-circle me-2"></i>Session Error</h4>
                    <p>Unable to verify your session. Please clear your browser data and try logging in again.</p>
                    <hr>
                    <p class="mb-0">
                        <button class="btn btn-danger btn-sm me-2" onclick="clearSessionData()">Clear Session Data</button>
                        <a href="login.php" class="btn btn-primary btn-sm">Go to Login</a>
                    </p>
                </div>
            `;
        }
        
        // Reset redirect counter after showing error
        sessionStorage.setItem('redirect_attempts', '0');
    }
}

/**
 * Clear session data
 */
function clearSessionData() {
    // Clear localStorage
    localStorage.removeItem('kes_smart_session');
    localStorage.removeItem('offline_login_data');
    
    // Clear sessionStorage
    sessionStorage.clear();
    
    // Clear cookies
    document.cookie.split(";").forEach(function(c) { 
        document.cookie = c.replace(/^ +/, "").replace(/=.*/, "=;expires=" + new Date().toUTCString() + ";path=/"); 
    });
    
    alert('Session data cleared. You will now be redirected to the login page.');
    window.location.href = 'login.php';
}

/**
 * Handle online status change
 */
function handleOnlineStatus() {
    console.log('Connection restored');
    
    const offlineIndicator = document.getElementById('offline-indicator');
    if (offlineIndicator && !offlineIndicator.classList.contains('d-none')) {
        offlineIndicator.innerHTML = `
            <span class="badge bg-success mt-1">
                <i class="fas fa-wifi me-1"></i>Back Online
            </span>
        `;
    }
    
    // Try to sync session if we have offline session data
    const sessionData = localStorage.getItem('kes_smart_session');
    if (sessionData) {
        try {
            const userData = JSON.parse(sessionData);
            syncSessionWithServer(userData);
        } catch (e) {
            console.error('Error parsing session data:', e);
        }
    }
    
    // Show toast notification
    console.log('[SUCCESS] Connection restored');
}

/**
 * Handle offline status change
 */
function handleOfflineStatus() {
    console.log('Connection lost');
    
    const offlineIndicator = document.getElementById('offline-indicator');
    if (offlineIndicator) {
        offlineIndicator.classList.remove('d-none');
        offlineIndicator.innerHTML = `
            <span class="badge bg-warning text-dark mt-1">
                <i class="fas fa-wifi-slash me-1"></i>Offline Mode
            </span>
        `;
    }
    
    // Show offline notification if not already shown
    const dashboardContainer = document.getElementById('dashboard-content');
    if (dashboardContainer && !document.getElementById('offline-mode-alert')) {
        const offlineAlert = document.createElement('div');
        offlineAlert.id = 'offline-mode-alert';
        offlineAlert.className = 'alert alert-warning alert-dismissible fade show mb-3';
        offlineAlert.innerHTML = `
            <i class="fas fa-wifi-slash me-2"></i>
            <strong>Offline Mode:</strong> You're now offline. Some features may be limited.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        dashboardContainer.insertBefore(offlineAlert, dashboardContainer.firstChild);
    }
    
    // Show toast notification
    console.log('[WARNING] You are now offline');
}

/**
 * Update connection status
 */
function updateConnectionStatus() {
    if (navigator.onLine) {
        handleOnlineStatus();
    } else {
        handleOfflineStatus();
    }
}

// Export functions for use in other scripts
window.offlineAuth = {
    checkSession: checkOfflineSession,
    clearSession: clearSessionData,
    syncSession: syncSessionWithServer
};
