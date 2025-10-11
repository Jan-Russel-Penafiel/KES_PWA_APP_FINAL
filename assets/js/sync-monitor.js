/**
 * KES-SMART Sync Monitor
 * Provides visual feedback and monitoring for offline data synchronization
 */

(function() {
  'use strict';
  
  // Sync monitor state
  const syncMonitor = {
    isVisible: false,
    currentSync: null,
    toastTimeout: null
  };
  
  // Initialize sync monitor UI
  function initSyncMonitor() {
    // Create sync status indicator
    createSyncStatusBadge();
    
    // Create sync toast notification
    createSyncToast();
    
    // Listen for sync events
    attachSyncEventListeners();
    
    // Check for pending items on load
    checkPendingItems();
    
    console.log('Sync monitor initialized');
  }
  
  // Create floating sync status badge
  function createSyncStatusBadge() {
    // Check if badge already exists
    if (document.getElementById('sync-status-badge')) {
      return;
    }
    
    const badge = document.createElement('div');
    badge.id = 'sync-status-badge';
    badge.className = 'sync-status-badge';
    badge.innerHTML = `
      <div class="sync-badge-content">
        <i class="fas fa-sync-alt sync-icon"></i>
        <span class="sync-count" id="sync-pending-count">0</span>
      </div>
      <div class="sync-badge-tooltip">
        <div class="tooltip-header">Pending Sync Items</div>
        <div class="tooltip-content">
          <div class="sync-stat">
            <span>Attendance:</span>
            <strong id="pending-attendance">0</strong>
          </div>
          <div class="sync-stat">
            <span>Forms:</span>
            <strong id="pending-forms">0</strong>
          </div>
        </div>
        <button class="btn-sync-now" id="btn-sync-now">
          <i class="fas fa-sync"></i> Sync Now
        </button>
      </div>
    `;
    
    document.body.appendChild(badge);
    
    // Add click handler for manual sync
    document.getElementById('btn-sync-now').addEventListener('click', function() {
      if (navigator.onLine) {
        if (typeof window.syncOfflineData === 'function') {
          window.syncOfflineData();
          showSyncToast('Syncing offline data...', 'info');
        }
      } else {
        showSyncToast('Cannot sync while offline', 'error');
      }
    });
    
    // Show/hide tooltip on hover
    badge.addEventListener('mouseenter', function() {
      this.classList.add('show-tooltip');
    });
    
    badge.addEventListener('mouseleave', function() {
      this.classList.remove('show-tooltip');
    });
  }
  
  // Create sync toast notification
  function createSyncToast() {
    // Check if toast already exists
    if (document.getElementById('sync-toast')) {
      return;
    }
    
    const toast = document.createElement('div');
    toast.id = 'sync-toast';
    toast.className = 'sync-toast';
    toast.innerHTML = `
      <div class="sync-toast-content">
        <div class="sync-toast-icon">
          <i class="fas fa-sync-alt"></i>
        </div>
        <div class="sync-toast-body">
          <div class="sync-toast-title"></div>
          <div class="sync-toast-message"></div>
          <div class="sync-toast-progress">
            <div class="sync-progress-bar">
              <div class="sync-progress-fill"></div>
            </div>
            <div class="sync-progress-text"></div>
          </div>
        </div>
        <button class="sync-toast-close">
          <i class="fas fa-times"></i>
        </button>
      </div>
    `;
    
    document.body.appendChild(toast);
    
    // Close button handler
    toast.querySelector('.sync-toast-close').addEventListener('click', function() {
      hideSyncToast();
    });
  }
  
  // Show sync toast
  function showSyncToast(message, type = 'info', title = null) {
    const toast = document.getElementById('sync-toast');
    if (!toast) return;
    
    const iconMap = {
      'info': 'fa-info-circle',
      'success': 'fa-check-circle',
      'error': 'fa-exclamation-circle',
      'warning': 'fa-exclamation-triangle'
    };
    
    const titleMap = {
      'info': 'Sync Info',
      'success': 'Sync Complete',
      'error': 'Sync Error',
      'warning': 'Sync Warning'
    };
    
    // Update content
    const icon = toast.querySelector('.sync-toast-icon i');
    icon.className = `fas ${iconMap[type] || iconMap.info}`;
    
    const titleEl = toast.querySelector('.sync-toast-title');
    titleEl.textContent = title || titleMap[type] || titleMap.info;
    
    const messageEl = toast.querySelector('.sync-toast-message');
    messageEl.textContent = message;
    
    // Hide progress bar by default
    const progressEl = toast.querySelector('.sync-toast-progress');
    progressEl.style.display = 'none';
    
    // Set type class
    toast.className = `sync-toast sync-toast-${type}`;
    
    // Show toast
    setTimeout(() => toast.classList.add('show'), 10);
    
    // Auto-hide after 5 seconds (except for error)
    clearTimeout(syncMonitor.toastTimeout);
    if (type !== 'error') {
      syncMonitor.toastTimeout = setTimeout(() => hideSyncToast(), 5000);
    }
  }
  
  // Hide sync toast
  function hideSyncToast() {
    const toast = document.getElementById('sync-toast');
    if (toast) {
      toast.classList.remove('show');
    }
    clearTimeout(syncMonitor.toastTimeout);
  }
  
  // Update sync progress
  function updateSyncProgress(stage, completed, total) {
    const toast = document.getElementById('sync-toast');
    if (!toast) return;
    
    const progressEl = toast.querySelector('.sync-toast-progress');
    const progressFill = toast.querySelector('.sync-progress-fill');
    const progressText = toast.querySelector('.sync-progress-text');
    
    if (total > 0) {
      const percentage = Math.round((completed / total) * 100);
      progressFill.style.width = percentage + '%';
      progressText.textContent = `${completed} / ${total} ${stage}`;
      progressEl.style.display = 'block';
    } else {
      progressEl.style.display = 'none';
    }
  }
  
  // Update pending count badge
  function updatePendingBadge(counts) {
    const badge = document.getElementById('sync-status-badge');
    const countEl = document.getElementById('sync-pending-count');
    const attendanceEl = document.getElementById('pending-attendance');
    const formsEl = document.getElementById('pending-forms');
    
    if (!badge || !countEl) return;
    
    const total = (counts.attendance || 0) + (counts.forms || 0) + (counts.login || 0);
    
    countEl.textContent = total;
    
    if (attendanceEl) attendanceEl.textContent = counts.attendance || 0;
    if (formsEl) formsEl.textContent = counts.forms || 0;
    
    // Show/hide badge based on count
    if (total > 0) {
      badge.classList.add('has-pending');
    } else {
      badge.classList.remove('has-pending');
    }
  }
  
  // Attach sync event listeners
  function attachSyncEventListeners() {
    // Listen for pending count updates
    window.addEventListener('pending-count-updated', function(event) {
      if (event.detail && event.detail.counts) {
        updatePendingBadge(event.detail.counts);
      }
    });
    
    // Listen for sync started
    window.addEventListener('sync-started', function() {
      showSyncToast('Starting offline data sync...', 'info');
      const badge = document.getElementById('sync-status-badge');
      if (badge) badge.classList.add('syncing');
    });
    
    // Listen for sync progress
    window.addEventListener('sync-progress', function(event) {
      if (event.detail) {
        const { stage, completed, total } = event.detail;
        updateSyncProgress(stage, completed, total);
      }
    });
    
    // Listen for sync completed
    window.addEventListener('sync-completed', function(event) {
      const badge = document.getElementById('sync-status-badge');
      if (badge) badge.classList.remove('syncing');
      
      if (event.detail && event.detail.results) {
        const results = event.detail.results;
        const totalSynced = (results.attendance?.success || 0) + (results.forms?.success || 0);
        const totalFailed = (results.attendance?.failed || 0) + (results.forms?.failed || 0);
        
        if (totalSynced > 0) {
          showSyncToast(
            `Successfully synced ${totalSynced} items${totalFailed > 0 ? `, ${totalFailed} failed` : ''}`,
            totalFailed > 0 ? 'warning' : 'success'
          );
        }
      } else {
        showSyncToast('Sync completed', 'success');
      }
      
      // Refresh pending count
      checkPendingItems();
    });
    
    // Listen for sync failed
    window.addEventListener('sync-failed', function(event) {
      const badge = document.getElementById('sync-status-badge');
      if (badge) badge.classList.remove('syncing');
      
      const reason = event.detail?.reason || 'Unknown error';
      showSyncToast(`Sync failed: ${reason}`, 'error');
    });
    
    // Listen for sync registered (Background Sync)
    window.addEventListener('sync-registered', function(event) {
      const tag = event.detail?.tag || 'sync';
      console.log('Background sync registered:', tag);
    });
    
    // Listen for online/offline events
    window.addEventListener('online', function() {
      showSyncToast('Back online! Syncing data...', 'success');
      checkPendingItems();
    });
    
    window.addEventListener('offline', function() {
      showSyncToast('You are offline. Data will sync when connection is restored.', 'warning');
    });
    
    // Listen for service worker messages
    if ('serviceWorker' in navigator) {
      navigator.serviceWorker.addEventListener('message', function(event) {
        if (event.data && event.data.type === 'sync-success') {
          showSyncToast('Background sync completed', 'success');
          checkPendingItems();
        } else if (event.data && event.data.type === 'sync-failed') {
          showSyncToast('Background sync failed', 'error');
        }
      });
    }
  }
  
  // Check for pending items
  function checkPendingItems() {
    if (typeof window.getPendingSyncCount === 'function') {
      window.getPendingSyncCount().then(counts => {
        updatePendingBadge(counts);
      }).catch(error => {
        console.error('Error checking pending items:', error);
      });
    }
  }
  
  // Initialize when DOM is ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initSyncMonitor);
  } else {
    initSyncMonitor();
  }
  
  // Expose functions globally
  window.syncMonitor = {
    showToast: showSyncToast,
    hideToast: hideSyncToast,
    updateProgress: updateSyncProgress,
    updateBadge: updatePendingBadge,
    checkPending: checkPendingItems
  };
  
})();
