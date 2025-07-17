<?php
require_once 'config.php';
require_once 'vendor/autoload.php'; // For Endroid QR Code

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('login.php');
}

$current_user = getCurrentUser($pdo);
$user_role = $_SESSION['role'];

// Determine which student's QR code to display
$student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : null;

// If no student_id is provided and current user is a student, use their own ID
if (!$student_id && $user_role == 'student') {
    $student_id = $current_user['id'];
}

if ($student_id) {
    // Verify access permissions
    if ($user_role == 'admin') {
        // Admin has full access to all student QR codes
    } elseif ($user_role == 'student' && $student_id == $current_user['id']) {
        // Students can access their own QR code - allow access
    } elseif ($user_role == 'student' && $student_id != $current_user['id']) {
        $_SESSION['error'] = 'Access denied.';
        redirect('dashboard.php');
    } elseif ($user_role == 'parent') {
        // Check if this student is linked to the parent
        $check_stmt = $pdo->prepare("SELECT 1 FROM student_parents WHERE parent_id = ? AND student_id = ?");
        $check_stmt->execute([$current_user['id'], $student_id]);
        if (!$check_stmt->fetch()) {
            $_SESSION['error'] = 'Access denied.';
            redirect('dashboard.php');
        }
    } elseif ($user_role == 'teacher') {
        // Check if student is in teacher's section
        $check_stmt = $pdo->prepare("SELECT 1 FROM users u JOIN sections s ON u.section_id = s.id WHERE u.id = ? AND s.teacher_id = ?");
        $check_stmt->execute([$student_id, $current_user['id']]);
        if (!$check_stmt->fetch()) {
            $_SESSION['error'] = 'Access denied.';
            redirect('dashboard.php');
        }
    } else {
        // Catch any other unauthorized access
        $_SESSION['error'] = 'Access denied. You do not have permission to access this page.';
        redirect('dashboard.php');
    }
    
    // Get student information
    $stmt = $pdo->prepare("SELECT u.*, s.section_name, s.grade_level FROM users u LEFT JOIN sections s ON u.section_id = s.id WHERE u.id = ? AND u.role = 'student'");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$student) {
        $_SESSION['error'] = 'Student not found.';
        redirect('dashboard.php');
    }
    
    // Ensure section_name is not null
    $student['section_name'] = $student['section_name'] ?? 'No Section Assigned';
    $student['grade_level'] = $student['grade_level'] ?? '';
} else {
    // Default to current user if they are a student
    if ($user_role != 'student') {
        $_SESSION['error'] = 'Student ID required.';
        redirect('dashboard.php');
    }
    $student = $current_user;
    $student_id = $current_user['id'];
    
    // Get section information for current user
    if ($student['section_id']) {
        $section_stmt = $pdo->prepare("SELECT section_name, grade_level FROM sections WHERE id = ?");
        $section_stmt->execute([$student['section_id']]);
        $section_info = $section_stmt->fetch(PDO::FETCH_ASSOC);
        
        $student['section_name'] = $section_info['section_name'] ?? 'No Section Assigned';
        $student['grade_level'] = $section_info['grade_level'] ?? '';
    } else {
        $student['section_name'] = 'No Section Assigned';
        $student['grade_level'] = '';
    }
}

// Generate or get QR code data
$qr_data = $student['qr_code'] ?? generateStudentQR($student_id);

// Update QR code in database if it doesn't exist
if (!$student['qr_code']) {
    $update_stmt = $pdo->prepare("UPDATE users SET qr_code = ? WHERE id = ?");
    $update_stmt->execute([$qr_data, $student_id]);
}

$page_title = 'QR Code - ' . $student['full_name'];
include 'header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 fw-bold text-primary">
                    <i class="fas fa-qrcode me-2"></i>Student QR Code
                </h1>
                <p class="text-muted mb-0">Personal QR code for attendance scanning</p>
            </div>
            <div class="text-end">
                <button onclick="downloadQR()" class="btn btn-outline-primary">
                    <i class="fas fa-download me-2"></i>Download
                </button>
                <button onclick="printQR()" class="btn btn-outline-secondary">
                    <i class="fas fa-print me-2"></i>Print
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Student Information Card -->
<div class="row mb-4">
    <div class="col-lg-8 mx-auto">
        <div class="card">
            <div class="card-header text-center bg-primary text-white">
                <h5 class="card-title mb-0">
                    <i class="fas fa-user-graduate me-2"></i>Student Information
                </h5>
            </div>
            <div class="card-body text-center">
                <div class="profile-avatar mx-auto mb-3" style="width: 100px; height: 100px; background: linear-gradient(45deg, #007bff, #0056b3); display: flex; align-items: center; justify-content: center; border-radius: 50%; color: white; font-size: 3rem; font-weight: bold;">
                    <?php echo strtoupper(substr($student['full_name'], 0, 1)); ?>
                </div>
                
                <h4 class="fw-bold text-primary mb-2"><?php echo $student['full_name']; ?></h4>
                <p class="text-muted mb-3"><?php echo $student['username']; ?></p>
                
                <?php if ($student['lrn']): ?>
                    <p class="text-muted mb-3">
                        <i class="fas fa-id-card me-2 text-warning"></i>
                        <strong>LRN:</strong> <span class="font-monospace"><?php echo $student['lrn']; ?></span>
                    </p>
                <?php endif; ?>
                
                <?php if ($student['section_name']): ?>
                    <div class="mb-3">
                        <span class="badge bg-info fs-6">
                            <i class="fas fa-school me-1"></i><?php echo $student['section_name']; ?>
                        </span>
                    </div>
                <?php endif; ?>
                
                <?php if ($student['email']): ?>
                    <p class="mb-2">
                        <i class="fas fa-envelope me-2 text-info"></i>
                        <?php echo $student['email']; ?>
                    </p>
                <?php endif; ?>
                
                <?php if ($student['phone']): ?>
                    <p class="mb-2">
                        <i class="fas fa-phone me-2 text-success"></i>
                        <?php echo $student['phone']; ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- QR Code Display -->
<div class="row mb-4">
    <div class="col-lg-6 mx-auto">
        <div class="card" id="qr-card">
            <div class="card-header text-center">
                <h5 class="card-title mb-0">
                    <i class="fas fa-qrcode me-2"></i>Your QR Code
                </h5>
            </div>
            <div class="card-body text-center p-4">
                <div class="qr-code-container" id="qr-container">
                    <div id="qr-code" class="mx-auto mb-3"></div>
                    <p class="text-muted small mb-3">
                        Show this QR code to your teacher for attendance scanning
                    </p>
                    
                    <!-- QR Code Data -->
                    <div class="bg-light p-3 rounded mb-3">
                        <small class="text-muted d-block mb-1">QR Code Data:</small>
                        <code class="text-break"><?php echo $qr_data; ?></code>
                    </div>
                    
                    <!-- School Logo/Name -->
                    <div class="school-info mt-3 pt-3 border-top">
                        <h6 class="fw-bold text-primary">KES-SMART</h6>
                        <small class="text-muted">Student Monitoring System</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Instructions -->
<div class="row mb-4">
    <div class="col-lg-8 mx-auto">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-info-circle me-2"></i>How to Use Your QR Code
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <div class="d-flex">
                            <div class="flex-shrink-0">
                                <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                    <span class="fw-bold">1</span>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="fw-bold">Show to Teacher</h6>
                                <p class="text-muted small mb-0">Present this QR code to your teacher when you arrive at school.</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <div class="d-flex">
                            <div class="flex-shrink-0">
                                <div class="rounded-circle bg-success text-white d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                    <span class="fw-bold">2</span>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="fw-bold">Get Scanned</h6>
                                <p class="text-muted small mb-0">Your teacher will scan your QR code to mark your attendance.</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <div class="d-flex">
                            <div class="flex-shrink-0">
                                <div class="rounded-circle bg-info text-white d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                    <span class="fw-bold">3</span>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="fw-bold">Automatic SMS</h6>
                                <p class="text-muted small mb-0">Your parents will receive an SMS notification about your attendance.</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <div class="d-flex">
                            <div class="flex-shrink-0">
                                <div class="rounded-circle bg-warning text-white d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                    <span class="fw-bold">4</span>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="fw-bold">Keep Safe</h6>
                                <p class="text-muted small mb-0">Save this QR code to your phone or print it for easy access.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Actions -->
<div class="row mb-4">
    <div class="col-lg-8 mx-auto">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-tools me-2"></i>Actions
                </h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-6 col-md-3 mb-3">
                        <button onclick="downloadQR()" class="btn btn-primary w-100">
                            <i class="fas fa-download fa-2x mb-2"></i><br>
                            <span>Download</span>
                        </button>
                    </div>
                    
                    <div class="col-6 col-md-3 mb-3">
                        <button onclick="printQR()" class="btn btn-success w-100">
                            <i class="fas fa-print fa-2x mb-2"></i><br>
                            <span>Print</span>
                        </button>
                    </div>
                    
                    <div class="col-6 col-md-3 mb-3">
                        <button onclick="shareQR()" class="btn btn-info w-100">
                            <i class="fas fa-share-alt fa-2x mb-2"></i><br>
                            <span>Share</span>
                        </button>
                    </div>
                    
                    <div class="col-6 col-md-3 mb-3">
                        <button onclick="saveToPhone()" class="btn btn-warning w-100">
                            <i class="fas fa-mobile-alt fa-2x mb-2"></i><br>
                            <span>Save</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Include QR Code Library -->
<script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>

<script>
// Generate QR Code
document.addEventListener('DOMContentLoaded', function() {
    const qrData = '<?php echo $qr_data; ?>';
    const qrContainer = document.getElementById('qr-code');
    
    console.log('QR Data:', qrData);
    console.log('QR Container:', qrContainer);
    
    // Check if QRCode library is loaded
    if (typeof QRCode === 'undefined') {
        console.error('QRCode library not loaded');
        qrContainer.innerHTML = '<div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> QR Code library loading...</div>';
        
        // Try alternative method
        setTimeout(function() {
            generateQRCodeAlternative();
        }, 2000);
        return;
    }
    
    // Create canvas element
    const canvas = document.createElement('canvas');
    qrContainer.appendChild(canvas);
    
    QRCode.toCanvas(canvas, qrData, {
        width: 300,
        height: 300,
        colorDark: '#000000',
        colorLight: '#ffffff',
        correctLevel: QRCode.CorrectLevel.H,
        margin: 4
    }, function (error) {
        if (error) {
            console.error('QR Code generation error:', error);
            qrContainer.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> Failed to generate QR code: ' + error.message + '</div>';
            generateQRCodeAlternative();
        } else {
            console.log('QR Code generated successfully');
            
            // Add styling to canvas
            canvas.style.border = '3px solid #007bff';
            canvas.style.borderRadius = '15px';
            canvas.style.boxShadow = '0 4px 6px rgba(0, 0, 0, 0.1)';
            canvas.style.maxWidth = '100%';
            canvas.style.height = 'auto';
        }
    });
});

// Alternative QR Code generation using Google Charts API
function generateQRCodeAlternative() {
    const qrData = '<?php echo $qr_data; ?>';
    const qrContainer = document.getElementById('qr-code');
    
    console.log('Using alternative QR generation method');
    
    const qrSize = 300;
    const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=${qrSize}x${qrSize}&data=${encodeURIComponent(qrData)}&format=png&margin=10`;
    
    const img = document.createElement('img');
    img.src = qrUrl;
    img.alt = 'QR Code';
    img.style.border = '3px solid #007bff';
    img.style.borderRadius = '15px';
    img.style.boxShadow = '0 4px 6px rgba(0, 0, 0, 0.1)';
    img.style.maxWidth = '100%';
    img.style.height = 'auto';
    img.style.width = '300px';
    
    img.onload = function() {
        qrContainer.innerHTML = '';
        qrContainer.appendChild(img);
        console.log('Alternative QR Code generated successfully');
    };
    
    img.onerror = function() {
        console.error('Alternative QR Code generation failed');
        qrContainer.innerHTML = `
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                <strong>QR Code Data:</strong><br>
                <div class="mt-2 p-2 bg-white rounded border">
                    <code style="word-break: break-all;">${qrData}</code>
                </div>
                <small class="d-block mt-2">Use a QR code scanner app to scan this data manually.</small>
            </div>
        `;
    };
}</script>

// Download QR Code
function downloadQR() {
    const canvas = document.querySelector('#qr-code canvas');
    const img = document.querySelector('#qr-code img');
    
    if (canvas) {
        // Canvas method
        const link = document.createElement('a');
        link.download = '<?php echo $student['username']; ?>_qr_code.png';
        link.href = canvas.toDataURL();
        link.click();
    } else if (img) {
        // Image method
        const link = document.createElement('a');
        link.download = '<?php echo $student['username']; ?>_qr_code.png';
        link.href = img.src;
        link.target = '_blank';
        link.click();
    } else {
        alert('QR code not found. Please wait for it to load or refresh the page.');
    }
}

// Print QR Code
function printQR() {
    const canvas = document.querySelector('#qr-code canvas');
    const img = document.querySelector('#qr-code img');
    
    const printWindow = window.open('', '_blank');
    let qrElement = '';
    
    if (canvas) {
        qrElement = canvas.outerHTML;
    } else if (img) {
        qrElement = `<img src="${img.src}" style="border: 3px solid #000; border-radius: 15px; max-width: 300px;">`;
    } else {
        qrElement = '<p>QR Code could not be loaded</p>';
    }
    
    const studentInfo = `
        <div style="text-align: center; padding: 20px; font-family: Arial, sans-serif;">
            <h1 style="color: #007bff; margin-bottom: 10px;">KES-SMART</h1>
            <h2 style="margin-bottom: 20px;"><?php echo $student['full_name']; ?></h2>
            <p style="margin-bottom: 5px;"><strong>Student ID:</strong> <?php echo $student['username']; ?></p>
            <?php if ($student['lrn']): ?>
            <p style="margin-bottom: 5px;"><strong>LRN:</strong> <?php echo $student['lrn']; ?></p>
            <?php endif; ?>
            <?php if ($student['section_name']): ?>
            <p style="margin-bottom: 20px;"><strong>Section:</strong> <?php echo $student['section_name']; ?></p>
            <?php endif; ?>
            <div style="margin: 20px 0;">
                ${qrElement}
            </div>
            <p style="margin-top: 20px; font-size: 12px; color: #666;">
                Show this QR code to your teacher for attendance scanning
            </p>
            <p style="font-size: 10px; color: #999;">
                Generated on: ${new Date().toLocaleDateString()}
            </p>
        </div>
    `;
    
    printWindow.document.write(`
        <html>
            <head>
                <title>QR Code - <?php echo $student['full_name']; ?></title>
                <style>
                    @media print {
                        body { margin: 0; }
                        canvas, img { border: 2px solid #000; border-radius: 10px; }
                    }
                </style>
            </head>
            <body>
                ${studentInfo}
            </body>
        </html>
    `);
    printWindow.document.close();
    printWindow.print();
}

// Share QR Code
async function shareQR() {
    const canvas = document.querySelector('#qr-code canvas');
    const img = document.querySelector('#qr-code img');
    
    if ((canvas || img) && navigator.share) {
        try {
            if (canvas) {
                canvas.toBlob(async (blob) => {
                    const file = new File([blob], '<?php echo $student['username']; ?>_qr_code.png', { type: 'image/png' });
                    await navigator.share({
                        title: 'My QR Code - <?php echo $student['full_name']; ?>',
                        text: 'My KES-SMART attendance QR code',
                        files: [file]
                    });
                });
            } else if (img) {
                // Convert image to blob for sharing
                const response = await fetch(img.src);
                const blob = await response.blob();
                const file = new File([blob], '<?php echo $student['username']; ?>_qr_code.png', { type: 'image/png' });
                await navigator.share({
                    title: 'My QR Code - <?php echo $student['full_name']; ?>',
                    text: 'My KES-SMART attendance QR code',
                    files: [file]
                });
            }
        } catch (error) {
            console.error('Error sharing:', error);
            fallbackShare();
        }
    } else {
        fallbackShare();
    }
}

function fallbackShare() {
    const canvas = document.querySelector('#qr-code canvas');
    const img = document.querySelector('#qr-code img');
    
    if (canvas || img) {
        // Copy QR data to clipboard
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText('<?php echo $qr_data; ?>').then(() => {
                alert('QR code data copied to clipboard!');
            });
        } else {
            // Create temporary textarea for copying
            const textarea = document.createElement('textarea');
            textarea.value = '<?php echo $qr_data; ?>';
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);
            alert('QR code data copied to clipboard!');
        }
    } else {
        alert('QR code not found. Please wait for it to load or refresh the page.');
    }
}

// Save to Phone
function saveToPhone() {
    const canvas = document.querySelector('#qr-code canvas');
    const img = document.querySelector('#qr-code img');
    
    if (canvas || img) {
        // For mobile devices, try to save to photos
        if (/Android|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)) {
            // Mobile device - download will trigger save to photos
            downloadQR();
            alert('QR code downloaded! You can now save it to your photos or set as wallpaper.');
        } else {
            // Desktop - show instructions
            alert('To save to your phone:\n1. Download the QR code\n2. Transfer to your phone\n3. Save to photos or set as wallpaper');
            downloadQR();
        }
    } else {
        alert('QR code not found. Please wait for it to load or refresh the page.');
    }
}

// PWA Install prompt for QR code page
let deferredPrompt;
window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    deferredPrompt = e;
    
    // Show install suggestion for QR code access
    const suggestion = document.createElement('div');
    suggestion.className = 'alert alert-info alert-dismissible fade show mt-3';
    suggestion.innerHTML = `
        <i class="fas fa-mobile-alt me-2"></i>
        <strong>Quick Access Tip:</strong> Install KES-SMART as an app for faster QR code access!
        <button type="button" class="btn btn-sm btn-outline-info ms-2" onclick="installApp()">Install</button>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    document.querySelector('#qr-card .card-body').appendChild(suggestion);
});

function installApp() {
    if (deferredPrompt) {
        deferredPrompt.prompt();
        deferredPrompt.userChoice.then((choiceResult) => {
            if (choiceResult.outcome === 'accepted') {
                console.log('User accepted the install prompt');
            }
            deferredPrompt = null;
        });
    }
}

// Auto-refresh QR code every hour to prevent stale codes
setInterval(() => {
    if (document.visibilityState === 'visible') {
        location.reload();
    }
}, 3600000); // 1 hour
</script>
</script>

<!-- Print-specific styles -->
<style media="print">
    .navbar, .bottom-nav, .btn, .alert, .card-header {
        display: none !important;
    }
    
    .card {
        border: none !important;
        box-shadow: none !important;
    }
    
    #qr-code canvas {
        border: 2px solid #000 !important;
        border-radius: 10px !important;
    }
    
    body {
        padding-bottom: 0 !important;
    }
</style>

<?php include 'footer.php'; ?>
