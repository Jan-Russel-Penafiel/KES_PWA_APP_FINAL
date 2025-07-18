<?php
require_once 'config.php';
$page_title = 'Install KES-SMART App';
$site_description = 'Install KES-SMART as a Progressive Web App for offline access';
include 'header.php';
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-lg border-0 rounded-lg">
                <div class="card-header bg-primary text-white text-center py-4">
                    <h2 class="h4 mb-0"><i class="fas fa-download me-2"></i> Install KES-SMART App</h2>
                </div>
                <div class="card-body p-4">
                    <div class="text-center mb-4">
                        <img src="https://img.icons8.com/color/192/000000/clipboard.png" alt="KES-SMART Logo" width="96" height="96" class="mb-3">
                        <h3 class="h5">Install KES-SMART as an app on your device</h3>
                        <p class="text-muted">Enjoy the full app experience with offline capabilities</p>
                    </div>
                    
                    <div class="d-grid gap-3">
                        <button id="install-pwa-btn" class="btn btn-primary btn-lg">
                            <i class="fas fa-download me-2"></i> Install App
                        </button>
                        
                        <div class="alert alert-info">
                            <p class="mb-1"><strong>Benefits of installing:</strong></p>
                            <ul class="mb-0">
                                <li>Works offline</li>
                                <li>Faster loading times</li>
                                <li>App-like experience</li>
                                <li>Home screen icon</li>
                            </ul>
                        </div>
                        
                        <div id="installation-instructions" class="d-none">
                            <div class="alert alert-warning">
                                <p class="mb-1"><strong>Installation steps:</strong></p>
                                <ol class="mb-0">
                                    <li>Tap the menu button (⋮) in your browser</li>
                                    <li>Select "Install App" or "Add to Home Screen"</li>
                                    <li>Follow the on-screen prompts</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-light text-center py-3">
                    <a href="index.php" class="btn btn-link">Back to Home</a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let installButton = document.getElementById('install-pwa-btn');
    const instructionsBlock = document.getElementById('installation-instructions');
    
    let deferredPrompt;
    let installAvailable = false;
    
    // Initially mark the button as unavailable
    if (installButton) {
        installButton.disabled = true;
        installButton.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Checking installation options...';
    }
    
    // Detect if installation is available
    window.addEventListener('beforeinstallprompt', (e) => {
        console.log('Install prompt detected');
        // Prevent the mini-infobar from appearing on mobile
        e.preventDefault();
        // Stash the event so it can be triggered later
        deferredPrompt = e;
        installAvailable = true;
        
        // Enable the install button
        if (installButton) {
            installButton.disabled = false;
            installButton.innerHTML = '<i class="fas fa-download me-2"></i> Install App Now';
            
            // Remove any existing click handlers to prevent duplicates
            installButton.onclick = null;
            
            // Add click listener for the install button
            installButton.addEventListener('click', handleInstallClick);
        }
    });
    
    function handleInstallClick(clickEvent) {
        // This function is executed in the context of a user gesture (click)
        if (!installAvailable || !deferredPrompt) {
            // Show manual instructions if install prompt is not available
            if (instructionsBlock) {
                instructionsBlock.classList.remove('d-none');
            }
            return;
        }
        
        try {
            console.log('User clicked install button, showing prompt...');
            // Show the install prompt within user gesture context
            deferredPrompt.prompt();
            
            // Wait for the user to respond to the prompt
            deferredPrompt.userChoice
                .then(function(choiceResult) {
                    console.log('User choice result:', choiceResult.outcome);
                    
                    // We no longer need the prompt
                    deferredPrompt = null;
                    installAvailable = false;
                    
                    if (installButton) {
                        if (choiceResult.outcome === 'accepted') {
                            console.log('User accepted the install prompt');
                            installButton.innerHTML = '<i class="fas fa-check-circle me-2"></i> Installation Started';
                            installButton.classList.remove('btn-primary');
                            installButton.classList.add('btn-success');
                            installButton.disabled = true;
                        } else {
                            console.log('User dismissed the install prompt');
                            installButton.innerHTML = '<i class="fas fa-download me-2"></i> Install App';
                            if (instructionsBlock) {
                                instructionsBlock.classList.remove('d-none');
                            }
                        }
                    }
                })
                .catch(function(err) {
                    console.error('Error with installation choice:', err);
                    if (instructionsBlock) {
                        instructionsBlock.classList.remove('d-none');
                    }
                });
        } catch (err) {
            console.error('Error trying to show install prompt:', err);
            if (instructionsBlock) {
                instructionsBlock.classList.remove('d-none');
            }
        }
    }
    
    // Check if app is already installed or install not available
    window.addEventListener('appinstalled', (evt) => {
        if (installButton) {
            installButton.innerHTML = '<i class="fas fa-check-circle me-2"></i> App Installed';
            installButton.classList.remove('btn-primary');
            installButton.classList.add('btn-success');
            installButton.disabled = true;
        }
    });
    
    // Check if running in standalone mode
    if (window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true) {
        if (installButton) {
            installButton.innerHTML = '<i class="fas fa-check-circle me-2"></i> App Already Installed';
            installButton.classList.remove('btn-primary');
            installButton.classList.add('btn-success');
            installButton.disabled = true;
        }
    }
    
    // If no installation prompt after 3 seconds, show instructions
    setTimeout(() => {
        if (!installAvailable && 
            !window.matchMedia('(display-mode: standalone)').matches && 
            !window.navigator.standalone && 
            installButton && 
            instructionsBlock) {
            
            instructionsBlock.classList.remove('d-none');
            installButton.innerHTML = '<i class="fas fa-download me-2"></i> Install Manually';
            installButton.disabled = false;
            
            // Just show instructions when clicking the button
            installButton.onclick = function() {
                instructionsBlock.classList.remove('d-none');
            };
        }
    }, 3000);
});
</script>

<?php include 'footer.php'; ?> 