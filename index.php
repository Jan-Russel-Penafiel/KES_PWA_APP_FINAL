<?php
require_once 'config.php';

// Redirect to dashboard if already logged in
if (isLoggedIn()) {
    redirect('dashboard.php');
}

$page_title = 'QR code with real time sms Notification in Tacurong City';
include 'header.php';
?>

<!-- Hero Section - Updated for better mobile experience -->
<div class="hero-section text-center py-4 py-md-5" style="background: linear-gradient(135deg, #007bff, #0056b3); color: white; margin: -2rem -15px 1.5rem -15px; border-radius: 0 0 20px 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-8 mx-auto">
                <i class="fas fa-graduation-cap fa-4x mb-3"></i>
                <h1 class="display-5 fw-bold mb-2">TAC-QR</h1>
                <p class="lead mb-3">QR code with real time SMS Notification in Tacurong City</p>
                <p class="mb-4 d-none d-md-block">Efficiently monitor student attendance using QR code scanning technology with automated SMS notifications to parents.</p>
                <div class="d-grid gap-2 d-sm-flex justify-content-center">
                    <a href="login.php" class="btn btn-light btn-lg px-4 py-2">
                        <i class="fas fa-sign-in-alt me-2"></i>Login
                    </a>
                    <button id="install-btn" class="btn btn-outline-light btn-lg px-4 py-2">
                        <i class="fas fa-download me-2"></i>Install App
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container px-3">
    <!-- Features Section - Improved for mobile -->
    <div class="row mb-4">
        <div class="col-12 text-center mb-3">
            <h2 class="h3 fw-bold text-primary">Key Features</h2>
            <p class="text-muted small">Comprehensive student monitoring solution</p>
        </div>
        
        <div class="col-6 col-md-6 col-lg-4 mb-3">
            <div class="card h-100 text-center shadow-sm rounded-3 border-0">
                <div class="card-body p-3">
                    <div class="feature-icon mb-2">
                        <i class="fas fa-qrcode fa-2x text-primary"></i>
                    </div>
                    <h5 class="card-title h6 fw-bold">QR Scanning</h5>
                    <p class="card-text small">Quick attendance tracking with real-time processing.</p>
                </div>
            </div>
        </div>
        
        <div class="col-6 col-md-6 col-lg-4 mb-3">
            <div class="card h-100 text-center shadow-sm rounded-3 border-0">
                <div class="card-body p-3">
                    <div class="feature-icon mb-2">
                        <i class="fas fa-sms fa-2x text-success"></i>
                    </div>
                    <h5 class="card-title h6 fw-bold">SMS Alerts</h5>
                    <p class="card-text small">Automated notifications for instant updates.</p>
                </div>
            </div>
        </div>
        
        <div class="col-6 col-md-6 col-lg-4 mb-3">
            <div class="card h-100 text-center shadow-sm rounded-3 border-0">
                <div class="card-body p-3">
                    <div class="feature-icon mb-2">
                        <i class="fas fa-chart-line fa-2x text-info"></i>
                    </div>
                    <h5 class="card-title h6 fw-bold">Auto Evaluation</h5>
                    <p class="card-text small">Intelligent attendance analysis with insights.</p>
                </div>
            </div>
        </div>
        
        <div class="col-6 col-md-6 col-lg-4 mb-3">
            <div class="card h-100 text-center shadow-sm rounded-3 border-0">
                <div class="card-body p-3">
                    <div class="feature-icon mb-2">
                        <i class="fas fa-users fa-2x text-warning"></i>
                    </div>
                    <h5 class="card-title h6 fw-bold">Multi-User Roles</h5>
                    <p class="card-text small">Support for Admin, Teachers, Students, and Parents.</p>
                </div>
            </div>
        </div>
        
        <div class="col-6 col-md-6 col-lg-4 mb-3">
            <div class="card h-100 text-center shadow-sm rounded-3 border-0">
                <div class="card-body p-3">
                    <div class="feature-icon mb-2">
                        <i class="fas fa-mobile-alt fa-2x text-danger"></i>
                    </div>
                    <h5 class="card-title h6 fw-bold">Mobile PWA</h5>
                    <p class="card-text small">Optimized for mobile with offline capabilities.</p>
                </div>
            </div>
        </div>
        
        <div class="col-6 col-md-6 col-lg-4 mb-3">
            <div class="card h-100 text-center shadow-sm rounded-3 border-0">
                <div class="card-body p-3">
                    <div class="feature-icon mb-2">
                        <i class="fas fa-file-alt fa-2x text-secondary"></i>
                    </div>
                    <h5 class="card-title h6 fw-bold">Reports</h5>
                    <p class="card-text small">Detailed reports for students and attendance.</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- User Roles Section - Mobile optimized -->
    <div class="row mb-4">
        <div class="col-12 text-center mb-3">
            <h2 class="h3 fw-bold text-primary">User Roles</h2>
            <p class="text-muted small">Designed for different user types</p>
        </div>
        
        <div class="col-6 col-md-6 col-lg-3 mb-3">
            <div class="card text-center border-0 shadow-sm rounded-3">
                <div class="card-body p-3">
                    <div class="rounded-circle bg-primary bg-opacity-10 p-2 d-inline-block mb-2">
                        <i class="fas fa-user-shield fa-lg text-primary"></i>
                    </div>
                    <h5 class="card-title h6 fw-bold">Admin</h5>
                    <p class="card-text small">Manage users and system settings.</p>
                </div>
            </div>
        </div>
        
        <div class="col-6 col-md-6 col-lg-3 mb-3">
            <div class="card text-center border-0 shadow-sm rounded-3">
                <div class="card-body p-3">
                    <div class="rounded-circle bg-success bg-opacity-10 p-2 d-inline-block mb-2">
                        <i class="fas fa-chalkboard-teacher fa-lg text-success"></i>
                    </div>
                    <h5 class="card-title h6 fw-bold">Teacher</h5>
                    <p class="card-text small">Track attendance and manage records.</p>
                </div>
            </div>
        </div>
        
        <div class="col-6 col-md-6 col-lg-3 mb-3">
            <div class="card text-center border-0 shadow-sm rounded-3">
                <div class="card-body p-3">
                    <div class="rounded-circle bg-info bg-opacity-10 p-2 d-inline-block mb-2">
                        <i class="fas fa-user-graduate fa-lg text-info"></i>
                    </div>
                    <h5 class="card-title h6 fw-bold">Student</h5>
                    <p class="card-text small">View attendance and QR codes.</p>
                </div>
            </div>
        </div>
        
        <div class="col-6 col-md-6 col-lg-3 mb-3">
            <div class="card text-center border-0 shadow-sm rounded-3">
                <div class="card-body p-3">
                    <div class="rounded-circle bg-warning bg-opacity-10 p-2 d-inline-block mb-2">
                        <i class="fas fa-heart fa-lg text-warning"></i>
                    </div>
                    <h5 class="card-title h6 fw-bold">Parent</h5>
                    <p class="card-text small">Monitor attendance and receive alerts.</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Statistics Section - Mobile optimized -->
    <div class="row mb-4">
        <div class="col-12 text-center mb-3">
            <h2 class="h3 fw-bold text-primary">System Statistics</h2>
        </div>
        
        <?php
        // Get system statistics
        $total_users = 0;
        $total_students = 0;
        $total_teachers = 0;
        $total_attendance = 0;
        
        // Check if we're online
        $is_online = true;
        
        try {
            // Try connecting to the database
            $total_users = $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'active'")->fetchColumn();
            $total_students = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student' AND status = 'active'")->fetchColumn();
            $total_teachers = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'teacher' AND status = 'active'")->fetchColumn();
            $total_attendance = $pdo->query("SELECT COUNT(*) FROM attendance WHERE attendance_date = CURDATE()")->fetchColumn();
        } catch(PDOException $e) {
            // If database connection fails, use static values for offline mode
            $is_online = false;
            $total_users = 250;
            $total_students = 200;
            $total_teachers = 30;
            $total_attendance = 180;
        }
        ?>
        
        <div class="col-6 col-md-6 col-lg-3 mb-3">
            <div class="card bg-primary text-white text-center rounded-3 shadow-sm border-0">
                <div class="card-body p-3">
                    <i class="fas fa-users fa-lg mb-1"></i>
                    <h4 class="fw-bold h5 mb-0"><?php echo number_format($total_users); ?></h4>
                    <p class="mb-0 small">Total Users</p>
                    <?php if (!$is_online): ?><span class="badge bg-light text-primary mt-1 small">Offline Data</span><?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-6 col-md-6 col-lg-3 mb-3">
            <div class="card bg-success text-white text-center rounded-3 shadow-sm border-0">
                <div class="card-body p-3">
                    <i class="fas fa-user-graduate fa-lg mb-1"></i>
                    <h4 class="fw-bold h5 mb-0"><?php echo number_format($total_students); ?></h4>
                    <p class="mb-0 small">Students</p>
                    <?php if (!$is_online): ?><span class="badge bg-light text-success mt-1 small">Offline Data</span><?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-6 col-md-6 col-lg-3 mb-3">
            <div class="card bg-info text-white text-center rounded-3 shadow-sm border-0">
                <div class="card-body p-3">
                    <i class="fas fa-chalkboard-teacher fa-lg mb-1"></i>
                    <h4 class="fw-bold h5 mb-0"><?php echo number_format($total_teachers); ?></h4>
                    <p class="mb-0 small">Teachers</p>
                    <?php if (!$is_online): ?><span class="badge bg-light text-info mt-1 small">Offline Data</span><?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-6 col-md-6 col-lg-3 mb-3">
            <div class="card bg-warning text-white text-center rounded-3 shadow-sm border-0">
                <div class="card-body p-3">
                    <i class="fas fa-calendar-check fa-lg mb-1"></i>
                    <h4 class="fw-bold h5 mb-0"><?php echo number_format($total_attendance); ?></h4>
                    <p class="mb-0 small">Today</p>
                    <?php if (!$is_online): ?><span class="badge bg-light text-warning mt-1 small">Offline Data</span><?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <?php if (!$is_online): ?>
    <!-- Offline Notice -->
    <div class="alert alert-info text-center mb-4">
        <i class="fas fa-wifi-slash me-2"></i>
        <strong>You're currently offline.</strong>
        <p class="mb-0 small">Some features may be limited. Changes will sync when you're back online.</p>
    </div>
    <?php endif; ?>
</div>

<!-- Demo Modal - Mobile optimized -->
<div class="modal fade" id="demoModal" tabindex="-1">
    <div class="modal-dialog modal-fullscreen-sm-down modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">TAC-QR Demo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="text-center">
                    <i class="fas fa-mobile-alt fa-4x text-primary mb-3"></i>
                    <h4 class="h5">Mobile-First Design</h4>
                    <p class="text-muted small">Experience TAC-QR's intuitive mobile interface designed for efficiency.</p>
                    
                    <div class="row mt-3">
                        <div class="col-4 text-center mb-3">
                            <i class="fas fa-qrcode fa-lg text-primary mb-1"></i>
                            <h6 class="small fw-bold">QR Scanning</h6>
                            <small class="text-muted d-none d-md-block">Quick attendance tracking</small>
                        </div>
                        <div class="col-4 text-center mb-3">
                            <i class="fas fa-sms fa-lg text-success mb-1"></i>
                            <h6 class="small fw-bold">SMS Alerts</h6>
                            <small class="text-muted d-none d-md-block">Instant notifications</small>
                        </div>
                        <div class="col-4 text-center mb-3">
                            <i class="fas fa-chart-bar fa-lg text-info mb-1"></i>
                            <h6 class="small fw-bold">Reports</h6>
                            <small class="text-muted d-none d-md-block">Comprehensive analytics</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <a href="login.php" class="btn btn-primary">Get Started</a>
            </div>
        </div>
    </div>
</div>

<script>
// Add animation to feature cards
document.addEventListener('DOMContentLoaded', function() {
    const cards = document.querySelectorAll('.card');
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '0';
                entry.target.style.transform = 'translateY(20px)';
                entry.target.style.transition = 'all 0.6s ease';
                
                setTimeout(() => {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }, 100);
                
                observer.unobserve(entry.target);
            }
        });
    });
    
    cards.forEach(card => {
        observer.observe(card);
    });
    
    // PWA install button display
    if ('serviceWorker' in navigator) {
        let deferredPrompt;
        const installBtn = document.getElementById('install-btn');
        
        // Create a floating install banner
        const createInstallBanner = () => {
            const banner = document.createElement('div');
            banner.id = 'pwa-install-banner';
            banner.style.position = 'fixed';
            banner.style.bottom = '20px';
            banner.style.left = '20px';
            banner.style.right = '20px';
            banner.style.backgroundColor = '#007bff';
            banner.style.color = 'white';
            banner.style.padding = '15px';
            banner.style.borderRadius = '10px';
            banner.style.boxShadow = '0 4px 10px rgba(0,0,0,0.2)';
            banner.style.display = 'flex';
            banner.style.justifyContent = 'space-between';
            banner.style.alignItems = 'center';
            banner.style.zIndex = '9999';
            
            banner.innerHTML = `
                <div>
                    <strong>Install TAC-QR</strong>
                    <p style="margin: 0; font-size: 14px;">Add to your home screen for offline access</p>
                </div>
                <div>
                    <button id="pwa-install-btn" style="background: white; color: #007bff; border: none; padding: 8px 15px; border-radius: 5px; font-weight: bold;">Install</button>
                    <button id="pwa-close-btn" style="background: transparent; color: white; border: 1px solid white; padding: 8px 15px; border-radius: 5px; margin-left: 10px;">Later</button>
                </div>
            `;
            
            document.body.appendChild(banner);
            
            // Add event listeners
            document.getElementById('pwa-install-btn').addEventListener('click', () => {
                if (deferredPrompt) {
                    deferredPrompt.prompt();
                    deferredPrompt.userChoice.then((choiceResult) => {
                        if (choiceResult.outcome === 'accepted') {
                            banner.style.display = 'none';
                            localStorage.setItem('pwaInstallDismissed', 'true');
                        }
                        deferredPrompt = null;
                    });
                }
            });
            
            document.getElementById('pwa-close-btn').addEventListener('click', () => {
                banner.style.display = 'none';
                localStorage.setItem('pwaInstallDismissed', Date.now().toString());
            });
            
            return banner;
        };
        
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;
            
            // Show the install button in the hero section
            if (installBtn) {
                installBtn.style.display = 'block';
                
                installBtn.addEventListener('click', () => {
                    deferredPrompt.prompt();
                    deferredPrompt.userChoice.then((choiceResult) => {
                        if (choiceResult.outcome === 'accepted') {
                            installBtn.style.display = 'none';
                            localStorage.setItem('pwaInstallDismissed', 'true');
                        }
                        deferredPrompt = null;
                    });
                });
            }
            
            // Check if we should show the banner
            const lastDismissed = localStorage.getItem('pwaInstallDismissed');
            const now = Date.now();
            const oneDay = 24 * 60 * 60 * 1000;
            
            if (!lastDismissed || (lastDismissed !== 'true' && now - parseInt(lastDismissed) > oneDay)) {
                // Wait a few seconds before showing the banner
                setTimeout(() => {
                    createInstallBanner();
                }, 3000);
            }
        });
    }
    
    // Check online status and update UI
    function updateOnlineStatus() {
        const offlineNotice = document.querySelector('.alert-info');
        if (offlineNotice) {
            if (navigator.onLine) {
                offlineNotice.classList.add('d-none');
            } else {
                offlineNotice.classList.remove('d-none');
            }
        }
    }
    
    // Listen for online/offline events
    window.addEventListener('online', updateOnlineStatus);
    window.addEventListener('offline', updateOnlineStatus);
    
    // Initial check
    if ('onLine' in navigator) {
        updateOnlineStatus();
    }
});

// Counter animation for statistics
function animateCounter(element, target) {
    let current = 0;
    const increment = target / 50;
    const timer = setInterval(() => {
        current += increment;
        if (current >= target) {
            current = target;
            clearInterval(timer);
        }
        element.textContent = Math.floor(current).toLocaleString();
    }, 20);
}

// Start counter animation when statistics section is visible
const statsCards = document.querySelectorAll('.card.bg-primary, .card.bg-success, .card.bg-info, .card.bg-warning');
const statsObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            const counter = entry.target.querySelector('h4');
            const target = parseInt(counter.textContent.replace(/,/g, ''));
            animateCounter(counter, target);
            statsObserver.unobserve(entry.target);
        }
    });
});

statsCards.forEach(card => {
    statsObserver.observe(card);
});
</script>

<script>
// Add direct PWA installation support
document.addEventListener('DOMContentLoaded', function() {
    const installButton = document.getElementById('install-btn');
    
    if (!installButton) return;
    
    // Initially hide the button until we know installation is available
    installButton.style.display = 'none';
    
    let deferredPrompt;
    
    window.addEventListener('beforeinstallprompt', (e) => {
        console.log('Install prompt available!');
        
        // Prevent Chrome 67 and earlier from automatically showing the prompt
        e.preventDefault();
        
        // Stash the event so it can be triggered later
        deferredPrompt = e;
        
        // Show the install button
        installButton.style.display = 'inline-flex';
        
        // Remove existing event listeners to prevent duplicates
        const newInstallButton = installButton.cloneNode(true);
        installButton.parentNode.replaceChild(newInstallButton, installButton);
        
        // Handle the install button click
        newInstallButton.addEventListener('click', function(event) {
            // This function is called during user interaction (the click event)
            console.log("Install button clicked, showing prompt");
            
            if (!deferredPrompt) {
                console.warn("Install prompt not available anymore");
                return;
            }
            
            // Show the install prompt during user gesture
            try {
                deferredPrompt.prompt();
                
                deferredPrompt.userChoice
                    .then(function(choiceResult) {
                        console.log(`User response: ${choiceResult.outcome}`);
                        
                        if (choiceResult.outcome === 'accepted') {
                            console.log('User accepted the install prompt');
                            newInstallButton.style.display = 'none';
                            
                            // Hide the promotion after installation
                            const promoEl = document.getElementById('pwa-promotion');
                            if (promoEl) promoEl.style.display = 'none';
                        }
                        
                        // Clear the deferred prompt variable
                        deferredPrompt = null;
                    })
                    .catch(function(err) {
                        console.error('Error with install prompt:', err);
                    });
            } catch (err) {
                console.error('Error trying to show install prompt:', err);
            }
        });
        
        // Show the promotion
        const promoEl = document.getElementById('pwa-promotion');
        if (promoEl) promoEl.style.display = 'flex';
    });
    
    // Hide installation UI if app is already installed
    window.addEventListener('appinstalled', (evt) => {
        console.log('App was installed');
        if (installButton) installButton.style.display = 'none';
        
        const promoEl = document.getElementById('pwa-promotion');
        if (promoEl) promoEl.style.display = 'none';
    });
    
    // Check if the app is already installed
    if (window.matchMedia('(display-mode: standalone)').matches || 
        window.navigator.standalone === true) {
        console.log('App is running in standalone mode (already installed)');
        if (installButton) installButton.style.display = 'none';
        
        const promoEl = document.getElementById('pwa-promotion');
        if (promoEl) promoEl.style.display = 'none';
    }
});
</script>

<?php include 'footer.php'; ?>

