// Service worker update check
function checkForServiceWorkerUpdates() {
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.ready
      .then(registration => {
        // Check for updates
        registration.update();
      })
      .catch(error => {
        console.error('Error updating service worker:', error);
      });
  }
}

// Check for service worker updates when the page loads
document.addEventListener('DOMContentLoaded', () => {
  // Initial check
  checkForServiceWorkerUpdates();
  
  // Set up periodic checks (every hour)
  setInterval(checkForServiceWorkerUpdates, 60 * 60 * 1000);
  
  // PWA Installation Handlers
  handlePWAInstall();
});

// PWA Installation
function handlePWAInstall() {
  console.log("Setting up PWA installation handlers");
  let deferredPrompt;
  const installBtn = document.getElementById('install-btn');
  
  if (!installBtn) {
    console.log("Install button not found on this page");
    return;
  }
  
  // Initially hide the install button as it will be shown when prompt is available
  installBtn.style.display = 'none';
  
  window.addEventListener('beforeinstallprompt', (e) => {
    console.log("Install prompt detected");
    // Prevent the mini-infobar from appearing on mobile
    e.preventDefault();
    // Stash the event so it can be triggered later
    deferredPrompt = e;
    // Show the install button
    installBtn.style.display = 'inline-flex';
    
    // Add the event listener without replacing the button (which causes errors)
    // Remove existing click listeners if any
    installBtn.onclick = null;
    
    // Add the event listener directly to handle user gesture
    installBtn.addEventListener('click', () => {
      // Only trigger prompt during actual click event (user gesture)
      if (deferredPrompt) {
        console.log("User clicked install button - showing prompt");
        // Show the install prompt
        deferredPrompt.prompt();
        // Wait for the user to respond to the prompt
        deferredPrompt.userChoice.then((choiceResult) => {
          if (choiceResult.outcome === 'accepted') {
            console.log('User accepted the PWA installation');
            installBtn.style.display = 'none';
          } else {
            console.log('User dismissed the PWA installation');
          }
          // Clear the saved prompt
          deferredPrompt = null;
        }).catch(err => {
          console.error('Error with install prompt:', err);
        });
      } else {
        console.warn('Install prompt not available when button was clicked');
      }
    });
  });
  
  // Handle case where the app is already installed
  window.addEventListener('appinstalled', (evt) => {
    console.log('PWA was installed');
    // Hide the install button
    if (installBtn) {
      installBtn.style.display = 'none';
    }
    // Save to localStorage that app is installed
    localStorage.setItem('pwaInstalled', 'true');
  });
  
  // Check if running in standalone mode (already installed)
  if (window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true) {
    console.log('App is running in standalone mode (installed)');
    if (installBtn) {
      installBtn.style.display = 'none';
    }
  }
}

// Create a floating install banner
function createInstallBanner(deferredPrompt) {
  // Check if banner already exists
  if (document.getElementById('pwa-install-banner')) {
    return;
  }
  
  const banner = document.createElement('div');
  banner.id = 'pwa-install-banner';
  banner.innerHTML = `
    <div>
      <strong>Install KES-SMART App</strong>
      <p>Add to your home screen for offline access</p>
    </div>
    <div>
      <button id="pwa-install-btn">Install</button>
      <button id="pwa-close-btn">Later</button>
    </div>
  `;
  
  document.body.appendChild(banner);
  
  const installBannerBtn = document.getElementById('pwa-install-btn');
  const closeBannerBtn = document.getElementById('pwa-close-btn');
  
  // Safely add event listeners
  if (installBannerBtn) {
    installBannerBtn.onclick = function(event) {
      if (deferredPrompt) {
        // This is called during the click event - user gesture context
        deferredPrompt.prompt();
        
        deferredPrompt.userChoice.then((choiceResult) => {
          if (choiceResult.outcome === 'accepted') {
            console.log('User accepted the PWA installation from banner');
            banner.style.display = 'none';
            localStorage.setItem('pwaInstallDismissed', 'true');
          }
          deferredPrompt = null;
        }).catch(err => {
          console.error('Error with banner install prompt:', err);
        });
      }
    };
  }
  
  if (closeBannerBtn) {
    closeBannerBtn.onclick = function() {
      banner.style.display = 'none';
      localStorage.setItem('pwaInstallDismissed', Date.now().toString());
    };
  }
}

// Register for Periodic Sync if supported
if ('serviceWorker' in navigator && 'periodicSync' in navigator) {
  navigator.serviceWorker.ready.then(async registration => {
    try {
      // Register a periodic sync task to check for updates every 4 hours
      await registration.periodicSync.register('update-check', {
        minInterval: 4 * 60 * 60 * 1000 // 4 hours in milliseconds
      });
      console.log('Periodic sync registered');
    } catch (error) {
      console.error('Periodic sync could not be registered:', error);
    }
  });
}

// Auto-reload configuration
const AUTO_RELOAD_CONFIG = {
  enabled: true, // Set to false to disable auto-reload
  delay: 3000, // Delay in milliseconds before reload (3 seconds)
  showCountdown: true // Show countdown notification
};

// Update notification for users with countdown
function showUpdateNotification(autoReload = true) {
  // Remove any existing notification
  const existingNotification = document.getElementById('update-notification');
  if (existingNotification) {
    document.body.removeChild(existingNotification);
  }
  
  const notification = document.createElement('div');
  notification.id = 'update-notification';
  notification.style.position = 'fixed';
  notification.style.bottom = '20px';
  notification.style.right = '20px';
  notification.style.padding = '15px 20px';
  notification.style.backgroundColor = '#4CAF50';
  notification.style.color = 'white';
  notification.style.borderRadius = '8px';
  notification.style.boxShadow = '0 4px 12px rgba(0,0,0,0.3)';
  notification.style.zIndex = '10001';
  notification.style.maxWidth = '350px';
  notification.style.fontFamily = 'Arial, sans-serif';
  notification.style.fontSize = '14px';
  notification.style.transition = 'all 0.3s ease';
  
  if (autoReload && AUTO_RELOAD_CONFIG.enabled) {
    let countdown = Math.floor(AUTO_RELOAD_CONFIG.delay / 1000);
    
    notification.innerHTML = `
      <div style="display: flex; align-items: center; gap: 12px;">
        <i class="fas fa-sync-alt" style="font-size: 24px;"></i>
        <div style="flex: 1;">
          <strong style="display: block; margin-bottom: 4px;">New Version Available!</strong>
          <span id="countdown-text">Updating in ${countdown} seconds...</span>
        </div>
        <button id="cancel-reload-btn" style="padding: 6px 12px; background-color: white; color: #4CAF50; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; font-size: 12px;">Cancel</button>
      </div>
    `;
    
    document.body.appendChild(notification);
    
    // Handle cancel button
    const cancelBtn = document.getElementById('cancel-reload-btn');
    if (cancelBtn) {
      cancelBtn.onclick = () => {
        clearInterval(countdownInterval);
        clearTimeout(reloadTimeout);
        notification.style.opacity = '0';
        setTimeout(() => {
          if (document.body.contains(notification)) {
            document.body.removeChild(notification);
          }
        }, 300);
      };
    }
    
    // Countdown timer
    const countdownInterval = setInterval(() => {
      countdown--;
      const countdownText = document.getElementById('countdown-text');
      if (countdownText) {
        if (countdown > 0) {
          countdownText.textContent = `Updating in ${countdown} second${countdown !== 1 ? 's' : ''}...`;
        } else {
          countdownText.textContent = 'Updating now...';
        }
      }
      
      if (countdown <= 0) {
        clearInterval(countdownInterval);
      }
    }, 1000);
    
    // Auto-reload after delay
    const reloadTimeout = setTimeout(() => {
      clearInterval(countdownInterval);
      notification.style.opacity = '0';
      setTimeout(() => {
        window.location.reload();
      }, 300);
    }, AUTO_RELOAD_CONFIG.delay);
    
  } else {
    // Manual reload option
    notification.innerHTML = `
      <div style="display: flex; align-items: center; gap: 12px;">
        <i class="fas fa-info-circle" style="font-size: 24px;"></i>
        <div style="flex: 1;">
          <strong style="display: block; margin-bottom: 4px;">New Version Available!</strong>
          <span>Click reload to update the app</span>
        </div>
        <button id="reload-btn" style="padding: 6px 12px; background-color: white; color: #4CAF50; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; font-size: 12px;">Reload</button>
      </div>
    `;
    
    document.body.appendChild(notification);
    
    const reloadBtn = document.getElementById('reload-btn');
    if (reloadBtn) {
      reloadBtn.onclick = () => {
        notification.style.opacity = '0';
        setTimeout(() => {
          window.location.reload();
        }, 300);
      };
    }
  }
}

// Listen for service worker update events
if ('serviceWorker' in navigator) {
  let refreshing = false;
  
  navigator.serviceWorker.addEventListener('controllerchange', () => {
    // A new service worker has taken control
    if (refreshing) return;
    refreshing = true;
    
    if (AUTO_RELOAD_CONFIG.enabled) {
      console.log('New service worker activated, reloading page...');
      window.location.reload();
    } else {
      showUpdateNotification(false);
    }
  });
  
  // Enhanced update detection
  navigator.serviceWorker.ready.then(registration => {
    // Check for updates periodically
    setInterval(() => {
      registration.update();
    }, 60000); // Check every minute
    
    // Listen for waiting service worker
    registration.addEventListener('updatefound', () => {
      const newWorker = registration.installing;
      
      if (newWorker) {
        newWorker.addEventListener('statechange', () => {
          if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
            // New service worker is installed and waiting
            console.log('New version available!');
            
            if (AUTO_RELOAD_CONFIG.enabled && AUTO_RELOAD_CONFIG.showCountdown) {
              showUpdateNotification(true);
            }
            
            // Tell the service worker to skip waiting
            newWorker.postMessage({ type: 'SKIP_WAITING' });
          }
        });
      }
    });
  });
}
