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

// Disable any legacy notification functions that might be cached
if (typeof showUpdateNotification === 'function') {
  window.showUpdateNotification = function() {
    console.log('Update notification disabled');
  };
}

// Override any cached notification functions
window.showUpdateNotification = function() {
  console.log('Update notification disabled');
};

// Error handling for cached functions
window.addEventListener('error', function(event) {
  if (event.message && event.message.includes('showUpdateNotification')) {
    console.log('Caught and ignored showUpdateNotification error');
    event.preventDefault();
    return false;
  }
});

// Check for service worker updates when the page loads
document.addEventListener('DOMContentLoaded', () => {
  // Override any notification functions
  window.showUpdateNotification = function() {
    console.log('Update notification disabled');
  };
  
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

// Service worker update events are now handled silently
// The app will continue to update in the background without user notification
