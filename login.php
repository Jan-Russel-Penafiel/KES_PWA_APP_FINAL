<?php
require_once 'config.php';

// Redirect to dashboard if already logged in
if (isLoggedIn()) {
    redirect('dashboard.php');
}

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = sanitize_input($_POST['username']);
    $role = sanitize_input($_POST['role']);
    
    if (empty($username)) {
        $error_message = 'Please enter your username.';
    } elseif (empty($role)) {
        $error_message = 'Please select your role.';
    } else {
        try {
            // Check user credentials with role
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND role = ? AND status = 'active'");
            $stmt->execute([$username, $role]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['section_id'] = $user['section_id'];
                
                // Update last login
                $stmt = $pdo->prepare("UPDATE users SET updated_at = NOW() WHERE id = ?");
                $stmt->execute([$user['id']]);
                
                // Redirect based on role
                $_SESSION['success'] = 'Welcome back, ' . $user['full_name'] . '!';
                redirect('dashboard.php');
            } else {
                $error_message = 'Invalid username, role combination, or account is inactive.';
            }
        } catch(PDOException $e) {
            // Try offline login when database connection fails
            $error_message = 'Login failed. Attempting offline login...';
        }
    }
}

$page_title = 'Login';
include 'header.php';
?>

<div class="container-fluid p-0">
    <div class="row g-0 min-vh-100">
        <!-- Left Side - Login Form -->
        <div class="col-lg-6 d-flex align-items-center justify-content-center px-3 py-4">
            <div class="w-100" style="max-width: 360px;">
                <div class="text-center mb-4">
                    <a href="index.php" class="d-inline-block mb-3">
                        <i class="fas fa-graduation-cap fa-3x text-primary"></i>
                    </a>
                    <h1 class="h3 fw-bold text-primary mb-1">KES-SMART</h1>
                    <p class="text-muted small">Student Monitoring System</p>
                </div>
                
                <div class="card shadow-sm border-0 rounded-3">
                    <div class="card-body p-4">
                        <h4 class="text-center h5 fw-bold mb-4">Login to Your Account</h4>
                        
                        <div id="connection-status" class="alert alert-warning py-2 small mb-3 d-none" role="alert">
                            <i class="fas fa-wifi-slash me-2"></i>
                            You're offline. Using cached credentials.
                        </div>
                        
                        <?php if ($error_message): ?>
                            <div class="alert alert-danger py-2 small" role="alert">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                <?php echo $error_message; ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="" class="needs-validation" novalidate id="login-form">
                            <div class="mb-3">
                                <label for="username" class="form-label small fw-medium">
                                    <i class="fas fa-user me-2"></i>Username
                                </label>
                                <input type="text" 
                                       class="form-control" 
                                       id="username" 
                                       name="username" 
                                       required 
                                       autofocus
                                       placeholder="Enter your username"
                                       value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                                <div class="invalid-feedback">
                                    Please enter your username
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="role" class="form-label small fw-medium">
                                    <i class="fas fa-user-tag me-2"></i>Role
                                </label>
                                <select class="form-select" 
                                        id="role" 
                                        name="role" 
                                        required>
                                    <option value="">Select your role</option>
                                    <option value="admin" <?php echo (isset($_POST['role']) && $_POST['role'] == 'admin') ? 'selected' : ''; ?>>
                                        Administrator
                                    </option>
                                    <option value="teacher" <?php echo (isset($_POST['role']) && $_POST['role'] == 'teacher') ? 'selected' : ''; ?>>
                                        Teacher
                                    </option>
                                    <option value="student" <?php echo (isset($_POST['role']) && $_POST['role'] == 'student') ? 'selected' : ''; ?>>
                                        Student
                                    </option>
                                    <option value="parent" <?php echo (isset($_POST['role']) && $_POST['role'] == 'parent') ? 'selected' : ''; ?>>
                                        Parent
                                    </option>
                                </select>
                                <div class="invalid-feedback">
                                    Please select your role
                                </div>
                            </div>
                            
                            <div class="d-grid mb-3">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-sign-in-alt me-2"></i>Login
                                </button>
                            </div>
                            
                            <div class="text-center">
                                <small class="text-muted">
                                    Don't have an account? Contact your administrator.
                                </small>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Back to home link for mobile -->
                <div class="mt-3 text-center d-lg-none">
                    <a href="index.php" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Home
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Right Side - Information Panel -->
        <div class="col-lg-6 d-none d-lg-block" 
             style="background: linear-gradient(135deg, #007bff, #0056b3);">
            <div class="d-flex flex-column h-100">
                <div class="p-4 mt-auto mb-auto text-white text-center">
                    <i class="fas fa-mobile-alt fa-4x mb-4 opacity-75"></i>
                    <h2 class="fw-bold h3 mb-3">Mobile-First Design</h2>
                    <p class="lead mb-4">
                        Experience seamless student monitoring with our progressive web application 
                        designed specifically for mobile devices.
                    </p>
                    
                    <div class="row justify-content-center">
                        <div class="col-md-4 mb-4">
                            <div class="p-3 bg-white bg-opacity-10 rounded-3">
                                <i class="fas fa-qrcode fa-2x mb-2 text-white"></i>
                                <h5 class="h6 fw-bold">QR Scanning</h5>
                                <p class="small opacity-75">Quick attendance tracking with QR codes</p>
                            </div>
                        </div>
                        <div class="col-md-4 mb-4">
                            <div class="p-3 bg-white bg-opacity-10 rounded-3">
                                <i class="fas fa-sms fa-2x mb-2 text-white"></i>
                                <h5 class="h6 fw-bold">SMS Alerts</h5>
                                <p class="small opacity-75">Instant notifications to parents</p>
                            </div>
                        </div>
                        <div class="col-md-4 mb-4">
                            <div class="p-3 bg-white bg-opacity-10 rounded-3">
                                <i class="fas fa-chart-line fa-2x mb-2 text-white"></i>
                                <h5 class="h6 fw-bold">Auto Evaluation</h5>
                                <p class="small opacity-75">Intelligent attendance analysis</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Current Date and Time Display -->
<div class="position-fixed bottom-0 end-0 p-2 text-muted small" style="z-index: 100;">
    <i class="fas fa-clock me-1"></i>
    <span id="current-datetime"></span>
</div>

<script>
// IndexedDB for offline authentication
const DB_NAME = 'kes-smart-offline-auth';
const DB_VERSION = 1;
const STORE_NAME = 'credentials';
let db;

// Check if browser supports IndexedDB
function isIndexedDBSupported() {
    return window.indexedDB !== undefined && 
           window.indexedDB !== null && 
           typeof window.indexedDB === 'object';
}

// Initialize IndexedDB
function initIndexedDB() {
    return new Promise((resolve, reject) => {
        try {
            // Check if IndexedDB is available
            if (!window.indexedDB) {
                console.error('IndexedDB is not supported in this browser');
                reject('IndexedDB is not supported in this browser');
                return;
            }
            
            // Open IndexedDB database
            const request = indexedDB.open(DB_NAME, DB_VERSION);
            
            request.onerror = (event) => {
                console.error('IndexedDB error:', event.target.error);
                reject('Could not initialize offline login database.');
            };
            
            request.onsuccess = (event) => {
                db = event.target.result;
                console.log('IndexedDB initialized successfully');
                resolve(db);
            };
            
            request.onupgradeneeded = (event) => {
                try {
                    const db = event.target.result;
                    
                    // Create object store for credentials if it doesn't exist
                    if (!db.objectStoreNames.contains(STORE_NAME)) {
                        const store = db.createObjectStore(STORE_NAME, { keyPath: 'username' });
                        store.createIndex('role', 'role', { unique: false });
                        console.log('Created credentials store');
                    }
                } catch (error) {
                    console.error('Error during database upgrade:', error);
                    reject(error);
                }
            };
        } catch (error) {
            console.error('Error initializing IndexedDB:', error);
            reject(error);
        }
    });
}

// Store user credentials in IndexedDB for offline use
function storeCredentials(username, role, userData) {
    return new Promise((resolve, reject) => {
        if (!db) {
            console.error('Cannot store credentials: Database not initialized');
            reject(new Error('Database not initialized'));
            return;
        }
        
        try {
            // Start a transaction
            const transaction = db.transaction([STORE_NAME], 'readwrite');
            const store = transaction.objectStore(STORE_NAME);
            
            // Store the credentials
            const request = store.put({
                username: username,
                role: role,
                userData: userData,
                timestamp: new Date().getTime()
            });
            
            request.onsuccess = () => {
                console.log(`Credentials stored successfully for ${username}`);
                resolve(true);
            };
            
            request.onerror = (event) => {
                console.error('Error storing credentials:', event.target.error);
                reject(event.target.error);
            };
            
            // Add transaction complete handler
            transaction.oncomplete = () => {
                console.log('Transaction completed successfully');
            };
            
            transaction.onerror = (event) => {
                console.error('Transaction error:', event.target.error);
                reject(event.target.error);
            };
        } catch (error) {
            console.error('Error in storeCredentials:', error);
            reject(error);
        }
    });
}

// Check if credentials exist and are valid
function checkOfflineCredentials(username, role) {
    return new Promise((resolve, reject) => {
        if (!db) {
            console.warn('Database not initialized when checking credentials');
            resolve(false);
            return;
        }
        
        try {
            const transaction = db.transaction([STORE_NAME], 'readonly');
            const store = transaction.objectStore(STORE_NAME);
            const request = store.get(username);
            
            request.onsuccess = (event) => {
                const data = event.target.result;
                if (data && data.role === role) {
                    resolve(data);
                } else {
                    resolve(false);
                }
            };
            
            request.onerror = (event) => {
                console.error('Error retrieving credentials:', event.target.error);
                reject(event.target.error);
            };
        } catch (error) {
            console.error('Error during credential check:', error);
            reject(error);
        }
    });
}

// Perform offline login
async function performOfflineLogin(username, role) {
    try {
        // Check if IndexedDB is initialized
        if (!db) {
            console.error('Database not initialized for offline login');
            await initIndexedDB().catch(err => {
                throw new Error('Failed to initialize database: ' + err.message);
            });
            
            if (!db) {
                throw new Error('Database initialization failed');
            }
        }
        
        const credentials = await checkOfflineCredentials(username, role);
        if (credentials) {
            // Store login data in localStorage for the offline auth page
            localStorage.setItem('offline_login_data', JSON.stringify({
                username: username,
                role: role,
                userData: credentials.userData,
                timestamp: new Date().getTime()
            }));
            
            // Set offline login cookie
            document.cookie = "kes_smart_offline_logged_in=1; path=/; max-age=86400";
            
            // If we're online, use form POST submission
            if (navigator.onLine) {
                // Create a form to submit the data for offline login
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'offline-auth.php';
                
                // Add fields
                const usernameField = document.createElement('input');
                usernameField.type = 'hidden';
                usernameField.name = 'username';
                usernameField.value = username;
                
                const roleField = document.createElement('input');
                roleField.type = 'hidden';
                roleField.name = 'role';
                roleField.value = role;
                
                const userDataField = document.createElement('input');
                userDataField.type = 'hidden';
                userDataField.name = 'user_data';
                userDataField.value = JSON.stringify(credentials.userData);
                
                const offlineField = document.createElement('input');
                offlineField.type = 'hidden';
                offlineField.name = 'offline_login';
                offlineField.value = '1';
                
                // Append fields to form
                form.appendChild(usernameField);
                form.appendChild(roleField);
                form.appendChild(userDataField);
                form.appendChild(offlineField);
                
                // Append form to document and submit
                document.body.appendChild(form);
                form.submit();
            } else {
                // If offline, redirect to offline auth page with parameters
                window.location.href = `offline-auth.php?username=${encodeURIComponent(username)}&role=${encodeURIComponent(role)}&user_data=${encodeURIComponent(JSON.stringify(credentials.userData))}`;
            }
            
            return true;
        } else {
            return false;
        }
    } catch (error) {
        console.error('Offline login error:', error);
        
        // Show the database error notification if it exists
        const dbErrorNotification = document.getElementById('db-error-notification');
        if (dbErrorNotification) {
            dbErrorNotification.classList.remove('d-none');
            dbErrorNotification.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i>Error during offline login: ' + error.message;
        }
        
        return false;
    }
}

// Check connection status
function checkOnlineStatus() {
    const isOnline = navigator.onLine;
    const statusElement = document.getElementById('connection-status');
    
    if (statusElement) {
        if (!isOnline) {
            statusElement.classList.remove('d-none');
        } else {
            statusElement.classList.add('d-none');
        }
    }
    
    return isOnline;
}

// Sync login attempts from IndexedDB when back online
function syncOfflineLogins() {
    // This would sync any pending login attempts stored in IndexedDB
    // Implementation would depend on how you want to handle offline sessions
}

// Form validation and submission
document.addEventListener('DOMContentLoaded', function() {
    // Check if IndexedDB is supported
    if (!isIndexedDBSupported()) {
        console.error('IndexedDB is not supported in this browser');
        
        // Show a warning about offline login not being available
        const dbErrorNotification = document.createElement('div');
        dbErrorNotification.id = 'db-error-notification';
        dbErrorNotification.className = 'alert alert-warning py-2 small';
        dbErrorNotification.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i>Your browser does not support offline login. Please use a modern browser for full functionality.';
        
        const form = document.querySelector('form');
        if (form) {
            form.insertBefore(dbErrorNotification, form.firstChild);
        }
        
        return;
    }
    
    // Initialize IndexedDB
    initIndexedDB()
        .then(() => {
            console.log('IndexedDB successfully initialized');
            // Try to fetch credentials from server if online
            if (navigator.onLine) {
                fetchAndStoreCredentials();
            }
        })
        .catch(error => {
            console.error('Failed to initialize IndexedDB:', error);
            // Create a hidden notification that will be shown if offline login is attempted
            const dbErrorNotification = document.createElement('div');
            dbErrorNotification.id = 'db-error-notification';
            dbErrorNotification.className = 'alert alert-warning py-2 small d-none';
            dbErrorNotification.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i>Offline storage is not available. Login may not work offline.';
            
            const form = document.querySelector('form');
            if (form) {
                form.insertBefore(dbErrorNotification, form.firstChild);
            }
        });
    
    // Check connection status on page load
    checkOnlineStatus();
    
    // Update connection status when online/offline events are triggered
    window.addEventListener('online', function() {
        checkOnlineStatus();
        // Try to sync any pending data when back online
        if (typeof syncOfflineLogins === 'function') {
            syncOfflineLogins();
        }
    });
    window.addEventListener('offline', checkOnlineStatus);
    
    // Bootstrap form validation
    const forms = document.querySelectorAll('.needs-validation');
    
    Array.from(forms).forEach(form => {
        form.addEventListener('submit', async function(event) {
            event.preventDefault();
            
            if (!form.checkValidity()) {
                event.stopPropagation();
            } else {
                // Show loading state
                const submitBtn = form.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Logging in...';
                submitBtn.disabled = true;
                
                const username = document.getElementById('username').value;
                const role = document.getElementById('role').value;
                
                // Check if online or offline
                if (navigator.onLine) {
                    // If online, try regular login and store credentials for offline use
                    fetch('api/auth.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: new URLSearchParams({
                            username: username,
                            role: role,
                            action: 'login'
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Store credentials for offline use
                            storeCredentials(username, role, data.user)
                                .then(() => {
                                    console.log('Credentials stored for offline use');
                                    // Mark that we've successfully logged in at least once
                                    localStorage.setItem('has_attempted_login', 'true');
                                })
                                .catch(error => {
                                    console.error('Failed to store credentials:', error);
                                });
                            
                            // Regular form submission for login
                            form.removeEventListener('submit', arguments.callee);
                            form.submit();
                        } else {
                            // Show error
                            const errorDiv = document.createElement('div');
                            errorDiv.className = 'alert alert-danger py-2 small';
                            errorDiv.innerHTML = `<i class="fas fa-exclamation-circle me-2"></i>${data.message}`;
                            
                            // Remove any existing error messages
                            const existingErrors = form.querySelectorAll('.alert-danger');
                            existingErrors.forEach(el => el.remove());
                            
                            form.insertBefore(errorDiv, form.firstChild);
                            
                            // Reset button
                            submitBtn.innerHTML = originalText;
                            submitBtn.disabled = false;
                        }
                    })
                    .catch(error => {
                        console.error('Login error:', error);
                        
                        // Try offline login as fallback
                        performOfflineLogin(username, role).then(success => {
                            if (!success) {
                                // Show offline login failure
                                const errorDiv = document.createElement('div');
                                errorDiv.className = 'alert alert-danger py-2 small';
                                errorDiv.innerHTML = '<i class="fas fa-exclamation-circle me-2"></i>Offline login failed. No stored credentials found.';
                                
                                // Remove any existing error messages
                                const existingErrors = form.querySelectorAll('.alert-danger');
                                existingErrors.forEach(el => el.remove());
                                
                                form.insertBefore(errorDiv, form.firstChild);
                            }
                            
                            // Reset button after 3 seconds if no redirect happens
                            setTimeout(() => {
                                submitBtn.innerHTML = originalText;
                                submitBtn.disabled = false;
                            }, 3000);
                        });
                    });
                } else {
                    // If offline, try login with stored credentials
                    performOfflineLogin(username, role).then(success => {
                        if (!success) {
                            // Show offline login failure
                            const errorDiv = document.createElement('div');
                            errorDiv.className = 'alert alert-danger py-2 small';
                            
                            // Check if this is a first-time login or if we've logged in before
                            const hasAttemptedLogin = localStorage.getItem('has_attempted_login');
                            
                            if (!hasAttemptedLogin) {
                                errorDiv.innerHTML = '<i class="fas fa-exclamation-circle me-2"></i>Offline login failed. You need to login online at least once to use offline login.';
                            } else {
                                errorDiv.innerHTML = '<i class="fas fa-exclamation-circle me-2"></i>Offline login failed. No stored credentials found for this username and role.';
                            }
                            
                            // Remove any existing error messages
                            const existingErrors = form.querySelectorAll('.alert-danger');
                            existingErrors.forEach(el => el.remove());
                            
                            form.insertBefore(errorDiv, form.firstChild);
                            
                            // Reset button
                            submitBtn.innerHTML = originalText;
                            submitBtn.disabled = false;
                        }
                    });
                }
            }
            
            form.classList.add('was-validated');
        }, false);
    });
    
    // Auto-focus username field
    document.getElementById('username').focus();
    
    // Display current date and time
    const datetimeElement = document.getElementById('current-datetime');
    if (datetimeElement) {
        const updateDateTime = () => {
            const now = new Date();
            datetimeElement.textContent = now.toLocaleString();
        };
        updateDateTime();
        setInterval(updateDateTime, 1000);
    }
    
    // Add animation to form elements
    const card = document.querySelector('.card');
    if (card) {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        
        setTimeout(() => {
            card.style.transition = 'all 0.5s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, 100);
    }
});

// Function to proactively fetch and store credentials
function fetchAndStoreCredentials() {
    // Only attempt if the user has logged in before
    const hasAttemptedLogin = localStorage.getItem('has_attempted_login');
    if (!hasAttemptedLogin) return;
    
    // Check if we have a stored username and role from previous login
    const offlineLoginData = localStorage.getItem('offline_login_data');
    if (offlineLoginData) {
        try {
            const data = JSON.parse(offlineLoginData);
            if (data.username && data.role) {
                // Quietly refresh the stored credentials in the background
                fetch('api/auth.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        username: data.username,
                        role: data.role,
                        action: 'refresh_credentials'
                    })
                })
                .then(response => response.json())
                .then(result => {
                    if (result.success && result.user) {
                        // Update stored credentials
                        storeCredentials(data.username, data.role, result.user)
                            .then(() => console.log('Credentials refreshed for offline use'))
                            .catch(error => console.error('Failed to refresh credentials:', error));
                    }
                })
                .catch(error => {
                    console.log('Could not refresh credentials:', error);
                });
            }
        } catch (e) {
            console.error('Error parsing stored login data:', e);
        }
    }
}
</script>

<?php include 'footer.php'; ?>
