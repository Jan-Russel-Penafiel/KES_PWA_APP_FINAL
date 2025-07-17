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
});

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

// Update notification for users
function showUpdateNotification() {
  const notification = document.createElement('div');
  notification.style.position = 'fixed';
  notification.style.bottom = '20px';
  notification.style.right = '20px';
  notification.style.padding = '15px';
  notification.style.backgroundColor = '#4CAF50';
  notification.style.color = 'white';
  notification.style.borderRadius = '5px';
  notification.style.boxShadow = '0 2px 5px rgba(0,0,0,0.2)';
  notification.style.zIndex = '9999';
  notification.innerHTML = 'A new version of the app is available! <button id="reload-btn" style="margin-left:10px;padding:5px 10px;background-color:white;color:#4CAF50;border:none;border-radius:3px;cursor:pointer;">Reload</button>';
  
  document.body.appendChild(notification);
  
  document.getElementById('reload-btn').addEventListener('click', () => {
    window.location.reload();
  });
  
  // Auto-hide after 10 seconds
  setTimeout(() => {
    if (document.body.contains(notification)) {
      document.body.removeChild(notification);
    }
  }, 10000);
}

// Listen for service worker update events
if ('serviceWorker' in navigator) {
  navigator.serviceWorker.addEventListener('controllerchange', () => {
    // A new service worker has taken control
    if (!window.isReloading) {
      window.isReloading = true;
      showUpdateNotification();
    }
  });
}
