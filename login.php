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
            $error_message = 'Login failed. Please try again.';
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
                        
                        <?php if ($error_message): ?>
                            <div class="alert alert-danger py-2 small" role="alert">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                <?php echo $error_message; ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="" class="needs-validation" novalidate>
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
// Form validation and submission
document.addEventListener('DOMContentLoaded', function() {
    // Bootstrap form validation
    const forms = document.querySelectorAll('.needs-validation');
    
    Array.from(forms).forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            } else {
                // Show loading state
                const submitBtn = form.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Logging in...';
                submitBtn.disabled = true;
                
                // Re-enable button after 5 seconds (in case of errors)
                setTimeout(() => {
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }, 5000);
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

// PWA install prompt for login page
window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    
    // Show custom install prompt
    const installBanner = document.createElement('div');
    installBanner.className = 'alert alert-info alert-dismissible fade show position-fixed start-50 translate-middle-x mt-2 shadow-sm';
    installBanner.style.zIndex = '9999';
    installBanner.style.maxWidth = '90%';
    installBanner.style.top = '0';
    installBanner.innerHTML = `
        <div class="d-flex align-items-center">
            <i class="fas fa-download me-2"></i>
            <span class="small">Install KES-SMART for better experience</span>
            <button type="button" class="btn btn-sm btn-info ms-3" id="install-app-btn">Install</button>
            <button type="button" class="btn-close ms-2" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    document.body.appendChild(installBanner);
    
    document.getElementById('install-app-btn').addEventListener('click', () => {
        e.prompt();
        e.userChoice.then((choiceResult) => {
            if (choiceResult.outcome === 'accepted') {
                console.log('User accepted the install prompt');
            }
            installBanner.remove();
        });
    });
});
</script>

<?php include 'footer.php'; ?>
