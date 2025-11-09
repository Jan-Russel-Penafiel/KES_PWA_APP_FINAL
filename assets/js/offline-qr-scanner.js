/**
 * KES-SMART Offline QR Scanner Enhancement
 * Handles offline QR scans and manual student selections with proper sync
 */

// Store offline attendance record with enhanced data
function storeOfflineQrAttendance(scanType, scanData) {
  return new Promise(async (resolve, reject) => {
    try {
      // Use enhanced cache manager if available
      if (typeof window.storeEnhancedOfflineAttendance === 'function') {
        console.log('🔄 Using enhanced cache manager for storage...');
        
        const attendanceRecord = {
          scan_type: scanType, // 'qr' or 'lrn' or 'manual'
          scan_data: scanData,
          timestamp: new Date().getTime(),
          date: new Date().toISOString().split('T')[0],
          synced: false,
          sync_attempts: 0
        };
        
        const result = await window.storeEnhancedOfflineAttendance(attendanceRecord);
        console.log('✅ Offline attendance stored with enhanced manager:', result);
        resolve(result);
        return;
      }
      
      // Fallback to original method
      console.log('⚠ Enhanced cache manager not available, using fallback...');
      
      // Check if database initialization function exists
      if (typeof initOfflineDB !== 'function') {
        reject(new Error('Offline database module not loaded. Please refresh the page.'));
        return;
      }
      
      if (!db) {
        // Try to initialize if not already done
        console.log('📦 Database not initialized, initializing now...');
        await initOfflineDB();
      }
      
      // Verify the ATTENDANCE store exists
      if (!db.objectStoreNames.contains(STORE_NAMES.ATTENDANCE)) {
        console.error('❌ ATTENDANCE store not found! Database version:', db.version);
        console.log('🔄 Attempting to reset database...');
        
        // Reset database and try again
        if (typeof resetOfflineDB === 'function') {
          await resetOfflineDB();
          console.log('✅ Database reset successful, retrying store...');
          const result = await storeOfflineQrAttendance(scanType, scanData);
          resolve(result);
          return;
        } else {
          reject(new Error('ATTENDANCE store not found. Database version: ' + db.version + '. Please close all tabs and refresh the page.'));
          return;
        }
      }
      
      const transaction = db.transaction([STORE_NAMES.ATTENDANCE], 'readwrite');
      const store = transaction.objectStore(STORE_NAMES.ATTENDANCE);
      
      const attendanceRecord = {
        scan_type: scanType, // 'qr' or 'lrn' or 'manual'
        scan_data: scanData,
        timestamp: new Date().getTime(),
        date: new Date().toISOString().split('T')[0],
        synced: false,
        sync_attempts: 0
      };
      
      const request = store.add(attendanceRecord);
      
      request.onsuccess = () => {
        console.log('✅ Offline attendance stored successfully:', attendanceRecord);
        resolve(attendanceRecord);
      };
      
      request.onerror = (event) => {
        console.error('❌ Error storing attendance record:', event.target.error);
        reject(new Error('Failed to store attendance: ' + event.target.error));
      };
      
      transaction.onerror = (event) => {
        console.error('❌ Transaction error:', event.target.error);
        reject(new Error('Transaction failed: ' + event.target.error));
      };
      
    } catch (error) {
      console.error('❌ Exception in storeOfflineQrAttendance:', error);
      reject(new Error('Exception: ' + error.message));
    }
  });
}

// Enhanced offline QR scan handler
function handleEnhancedOfflineQrScan(qrData, scanLocation, scanNotes) {
    try {
        // Get selected subject
        const subjectSelect = document.getElementById('subject-select');
        if (!subjectSelect || !subjectSelect.value) {
            console.log('[ERROR] Please select a subject first');
            return;
        }
        
        const subjectId = subjectSelect.value;
        const subjectName = subjectSelect.options[subjectSelect.selectedIndex].text;
        
        // Get current user data from PHP
        const teacherId = document.body.dataset.teacherId || '';
        const teacherName = document.body.dataset.teacherName || '';
        
        // Try to find student information from cached data
        let studentInfo = null;
        let studentName = 'Unknown Student';
        let studentLrn = null;
        let studentId = null;
        
        // First, try to decode the QR code if it's base64 encoded
        try {
            const decodedQR = atob(qrData);
            console.log('🔍 Decoded QR:', decodedQR);
            
            // Check if it matches the pattern: KES-SMART-STUDENT-{id}-{year}
            const qrMatch = decodedQR.match(/KES-SMART-STUDENT-(\d+)-\d{4}/);
            if (qrMatch && qrMatch[1]) {
                studentId = qrMatch[1];
                console.log('✅ Extracted student ID from QR:', studentId);
            }
        } catch (e) {
            console.log('⚠ QR not base64 encoded, using as-is');
        }
        
        // Check if studentsData is available (cached student list)
        if (typeof window.studentsData !== 'undefined' && Array.isArray(window.studentsData)) {
            console.log('🔍 Searching in', window.studentsData.length, 'cached students...');
            
            // Try to find student by:
            // 1. Extracted student ID from decoded QR
            // 2. Original QR data matching username
            // 3. Original QR data matching id
            // 4. Original QR data matching LRN
            studentInfo = window.studentsData.find(student => 
                (studentId && student.id == studentId) ||
                student.username === qrData || 
                student.id == qrData || 
                student.lrn === qrData ||
                student.qr_code === qrData
            );
            
            if (studentInfo) {
                studentName = studentInfo.full_name;
                studentLrn = studentInfo.lrn;
                studentId = studentInfo.id;
                console.log('✅ Found student in cache:', studentName, '(ID:', studentId, ', LRN:', studentLrn, ')');
            } else {
                console.log('⚠ Student not found in cache');
                console.log('   Searched for:', {
                    extractedId: studentId,
                    originalQR: qrData.substring(0, 20) + '...',
                    cacheSize: window.studentsData.length
                });
                
                // Show partial QR as fallback
                if (qrData.length > 20) {
                    studentName = 'Student (QR: ' + qrData.substring(0, 12) + '...)';
                } else {
                    studentName = 'Student (QR: ' + qrData + ')';
                }
            }
        } else {
            console.log('⚠ Students cache not available');
            studentName = 'Student (QR: ' + qrData.substring(0, 12) + '...)';
        }
        
        // Store offline scan data
        const scanData = {
            qr_data: qrData,
            student_name: studentName,
            student_id: studentId,
            student_lrn: studentLrn,
            subject_id: subjectId,
            subject_name: subjectName,
            scan_location: scanLocation || 'Main Gate',
            scan_notes: scanNotes || '',
            teacher_id: teacherId,
            teacher_name: teacherName,
            scan_method: 'QR Code',
            timestamp: new Date().getTime()
        };
        
        // Store in IndexedDB
        storeOfflineQrAttendance('qr', scanData).then(result => {
            console.log('✅ Offline QR scan stored:', result);
            
            // Show success message
            const offlineData = {
                success: true,
                offline: true,
                message: 'Attendance recorded offline and will sync when connection is restored',
                student_name: studentName,
                student_id: studentId || qrData,
                student_lrn: studentLrn,
                subject: subjectName,
                time: new Date().toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true }),
                date: new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }),
                attendance_date: new Date().toLocaleDateString(),
                time_in: new Date().toLocaleTimeString(),
                status: 'pending',
                scan_method: 'QR Code'
            };
            
            if (typeof showScanResult === 'function') {
                showScanResult(offlineData, 'warning');
            }
            
            // Update pending sync count
            updatePendingSyncCount();
            
        }).catch(error => {
            console.error('❌ Failed to store offline scan:', error);
            console.log('[ERROR] Failed to store offline attendance: ' + error.message);
        });
        
    } catch (e) {
        console.error('❌ Error in handleEnhancedOfflineQrScan:', e);
        console.log('[ERROR] Error processing offline scan: ' + e.message);
    }
}

// Enhanced offline LRN scan handler
function handleEnhancedOfflineLrnScan(lrn, scanLocation, scanNotes) {
    if (!lrn) {
        console.log('[ERROR] LRN is required');
        return;
    }
    
    if (!/^\d{12}$/.test(lrn)) {
        console.log('[ERROR] LRN must be exactly 12 digits');
        return;
    }
    
    // Get selected subject
    const subjectSelect = document.getElementById('subject-select');
    if (!subjectSelect || !subjectSelect.value) {
        console.log('[ERROR] Please select a subject first');
        return;
    }
    
    const subjectId = subjectSelect.value;
    const subjectName = subjectSelect.options[subjectSelect.selectedIndex].text;
    
    // Get current user data from PHP
    const teacherId = document.body.dataset.teacherId || '';
    const teacherName = document.body.dataset.teacherName || '';
    
    // Store the attendance record for syncing later
    const scanData = {
        lrn: lrn,
        subject_id: subjectId,
        subject_name: subjectName,
        scan_location: scanLocation || 'Main Gate',
        scan_notes: scanNotes || '',
        teacher_id: teacherId,
        teacher_name: teacherName,
        scan_method: 'Manual LRN',
        timestamp: new Date().getTime()
    };
    
    storeOfflineQrAttendance('lrn', scanData)
    .then(() => {
        console.log('[SUCCESS] LRN attendance recorded offline. Will sync when online.');
        
        // Clear the form
        const lrnInput = document.getElementById('lrn');
        if (lrnInput) lrnInput.value = '';
        
        // Show in results
        const offlineData = {
            success: true,
            offline: true,
            message: 'Attendance recorded offline and will sync when connection is restored',
            student_name: 'Student (LRN: ' + lrn + ')',
            student_id: lrn,
            student_lrn: lrn,
            subject: subjectName,
            time: new Date().toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true }),
            date: new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }),
            attendance_date: new Date().toLocaleDateString(),
            time_in: new Date().toLocaleTimeString(),
            status: 'pending',
            scan_method: 'Manual LRN'
        };
        
        if (typeof showScanResult === 'function') {
            showScanResult(offlineData, 'warning');
        }
        updatePendingSyncCount();
    })
    .catch(error => {
        console.log('[ERROR] Failed to store offline attendance: ' + error.message);
        console.error('Error storing offline LRN scan:', error);
    });
}

// Enhanced offline manual student selection handler
function handleEnhancedOfflineManualSelection(studentId, scanLocation, scanNotes) {
    if (!studentId) {
        console.log('[ERROR] Please select a student');
        return;
    }
    
    // Get selected subject
    const subjectSelect = document.getElementById('subject-select');
    if (!subjectSelect || !subjectSelect.value) {
        console.log('[ERROR] Please select a subject first');
        return;
    }
    
    const subjectId = subjectSelect.value;
    const subjectName = subjectSelect.options[subjectSelect.selectedIndex].text;
    
    // Get student name from select option
    const studentSelect = document.getElementById('student-select');
    const studentName = studentSelect ? studentSelect.options[studentSelect.selectedIndex].text : 'Unknown Student';
    
    // Get current user data from PHP
    const teacherId = document.body.dataset.teacherId || '';
    const teacherName = document.body.dataset.teacherName || '';
    
    // Store the attendance record for syncing later
    const scanData = {
        student_id: studentId,
        student_name: studentName,
        subject_id: subjectId,
        subject_name: subjectName,
        scan_location: scanLocation || 'Main Gate',
        scan_notes: scanNotes || '',
        teacher_id: teacherId,
        teacher_name: teacherName,
        scan_method: 'Manual Selection',
        timestamp: new Date().getTime()
    };
    
    storeOfflineQrAttendance('manual', scanData)
    .then(() => {
        console.log('[SUCCESS] Student attendance recorded offline. Will sync when online.');
        
        // Clear the selection
        if (studentSelect) {
            $(studentSelect).val(null).trigger('change');
        }
        
        // Show in results
        const offlineData = {
            success: true,
            offline: true,
            message: 'Attendance recorded offline and will sync when connection is restored',
            student_name: studentName,
            student_id: studentId,
            subject: subjectName,
            time: new Date().toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true }),
            date: new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }),
            attendance_date: new Date().toLocaleDateString(),
            time_in: new Date().toLocaleTimeString(),
            status: 'pending',
            scan_method: 'Manual Selection'
        };
        
        if (typeof showScanResult === 'function') {
            showScanResult(offlineData, 'warning');
        }
        updatePendingSyncCount();
    })
    .catch(error => {
        console.log('[ERROR] Failed to store offline attendance: ' + error.message);
        console.error('Error storing offline manual selection:', error);
    });
}

// Enhanced sync function for QR scanner attendance records
async function syncQrAttendanceRecords() {
    if (!navigator.onLine) {
        console.log('❌ Cannot sync while offline');
        return { success: 0, failed: 0, errors: [] };
    }
    
    if (!db) {
        try {
            console.log('🔄 Initializing database for sync...');
            await initOfflineDB();
        } catch (error) {
            console.error('❌ Failed to initialize database for sync:', error);
            return { success: 0, failed: 0, errors: [error] };
        }
    }
    
    try {
        // Use enhanced version if available
        const getRecordsFunction = window.getUnsyncedAttendanceRecords || getUnsyncedData;
        const attendanceRecords = await getRecordsFunction(STORE_NAMES.ATTENDANCE);
        
        if (!Array.isArray(attendanceRecords) || attendanceRecords.length === 0) {
            console.log('✓ No attendance records to sync');
            return { success: 0, failed: 0, errors: [] };
        }
        
        console.log(`🔄 Starting sync of ${attendanceRecords.length} attendance record(s)...`);
        
        const syncResults = {
            success: 0,
            failed: 0,
            errors: []
        };
        
        for (const record of attendanceRecords) {
            try {
                console.log(`📤 Syncing ${record.scan_type} record (ID: ${record.id})...`, record.scan_data);
                
                // Prepare form data based on scan type
                const formData = new FormData();
                
                if (record.scan_type === 'qr') {
                    formData.append('action', 'scan_qr');
                    formData.append('qr_data', record.scan_data.qr_data);
                    formData.append('subject_id', record.scan_data.subject_id);
                    formData.append('scan_location', record.scan_data.scan_location || 'Main Gate');
                    formData.append('scan_notes', record.scan_data.scan_notes || '');
                } else if (record.scan_type === 'lrn') {
                    formData.append('action', 'scan_lrn');
                    formData.append('lrn', record.scan_data.lrn);
                    formData.append('subject_id', record.scan_data.subject_id);
                    formData.append('scan_location', record.scan_data.scan_location || 'Main Gate');
                    formData.append('scan_notes', record.scan_data.scan_notes || '');
                } else if (record.scan_type === 'manual') {
                    formData.append('action', 'scan_manual');
                    formData.append('student_id', record.scan_data.student_id);
                    formData.append('subject_id', record.scan_data.subject_id);
                    formData.append('scan_location', record.scan_data.scan_location || 'Main Gate');
                    formData.append('scan_notes', record.scan_data.scan_notes || '');
                }
                
                // Add timestamp for server-side validation
                formData.append('offline_timestamp', record.timestamp);
                formData.append('offline_sync', 'true');
                
                // Send to qr-scanner.php
                console.log(`📡 Sending ${record.scan_type} to qr-scanner.php...`);
                const response = await fetch('qr-scanner.php', {
                    method: 'POST',
                    body: formData
                });
                
                console.log(`📥 Response status: ${response.status}`);
                
                if (response.ok) {
                    const result = await response.json();
                    console.log('📥 Server response:', result);
                    
                    if (result.success) {
                        await markAsSynced(STORE_NAMES.ATTENDANCE, record.id);
                        syncResults.success++;
                        console.log(`✅ Successfully synced record ID ${record.id} - Student: ${result.student_name || 'Unknown'}`);
                    } else {
                        syncResults.failed++;
                        syncResults.errors.push({
                            record: record,
                            error: result.message || 'Unknown error'
                        });
                        console.error(`❌ Server rejected record ID ${record.id}:`, result.message);
                    }
                } else {
                    const errorText = await response.text();
                    syncResults.failed++;
                    syncResults.errors.push({
                        record: record,
                        error: `HTTP ${response.status}: ${errorText.substring(0, 100)}`
                    });
                    console.error(`❌ HTTP error ${response.status} for record ID ${record.id}`);
                }
            } catch (error) {
                syncResults.failed++;
                syncResults.errors.push({
                    record: record,
                    error: error.message
                });
                console.error(`❌ Error syncing record ID ${record.id}:`, error);
            }
        }
        
        // Log final results
        console.log(`\n📊 SYNC SUMMARY:`);
        console.log(`✅ Success: ${syncResults.success}`);
        console.log(`❌ Failed: ${syncResults.failed}`);
        if (syncResults.errors.length > 0) {
            console.log(`⚠ Errors:`, syncResults.errors);
        }
        
        return syncResults;
        
    } catch (error) {
        console.error('❌ Error during sync process:', error);
        
        // Try to fix corrupted records if the enhanced manager is available
        if (typeof window.cleanCorruptedAttendanceRecords === 'function' && 
            error.message.includes('not a valid key')) {
            console.log('🔧 Detected corrupted records, attempting to fix...');
            try {
                const result = await window.cleanCorruptedAttendanceRecords();
                if (result.cleaned > 0) {
                    console.log(`✅ Fixed ${result.cleaned} corrupted records. Sync may work now.`);
                }
            } catch (fixError) {
                console.warn('⚠️ Could not fix corrupted records:', fixError.message);
            }
        }
        
        return { success: 0, failed: 0, errors: [error] };
    }
}

// Update pending sync count badge
function updatePendingSyncCount() {
    if (typeof getUnsyncedData !== 'function') {
        console.log('⚠ getUnsyncedData function not available');
        return;
    }
    
    if (!STORE_NAMES || !STORE_NAMES.ATTENDANCE) {
        console.log('⚠ STORE_NAMES not defined');
        return;
    }
    
    console.log('🔍 Checking for unsynced attendance records...');
    
    // Use enhanced version if available
    const getRecordsFunction = window.getUnsyncedAttendanceRecords || getUnsyncedData;
    
    getRecordsFunction(STORE_NAMES.ATTENDANCE).then(records => {
        const count = Array.isArray(records) ? records.length : 0;
        console.log(`📊 Found ${count} unsynced record(s):`, records);
        
        // Update sync status bar
        const syncStatusBar = document.getElementById('syncStatusBar');
        const pendingSyncText = document.getElementById('pendingSyncText');
        const syncNowBtn = document.getElementById('syncNowBtn');
        
        if (syncStatusBar && pendingSyncText) {
            if (count > 0) {
                pendingSyncText.textContent = `${count} scan${count > 1 ? 's' : ''} pending sync`;
                syncStatusBar.classList.remove('d-none');
                syncStatusBar.style.display = 'flex'; // Show as flex
                console.log(`✅ Sync status bar updated: ${count} pending`);
                
                // Disable sync button if offline
                if (syncNowBtn) {
                    if (navigator.onLine) {
                        syncNowBtn.disabled = false;
                        syncNowBtn.innerHTML = '<i class="fas fa-sync me-1"></i>Sync Now';
                    } else {
                        syncNowBtn.disabled = true;
                        syncNowBtn.innerHTML = '<i class="fas fa-wifi-slash me-1"></i>Offline';
                    }
                }
            } else {
                syncStatusBar.classList.add('d-none');
                syncStatusBar.style.display = 'none'; // Hide
                console.log('✅ No pending syncs, status bar hidden');
            }
        } else {
            console.log('⚠ Sync status bar elements not found in DOM');
        }
        
        // Also update old badge if it exists (for backward compatibility)
        let badge = document.getElementById('pending-sync-badge');
        
        if (!badge && count > 0) {
            // Create badge if it doesn't exist
            const scannerStatus = document.getElementById('scannerStatus');
            if (scannerStatus) {
                badge = document.createElement('span');
                badge.id = 'pending-sync-badge';
                badge.className = 'badge bg-warning text-dark ms-2';
                badge.style.cursor = 'pointer';
                badge.title = 'Click to view pending scans';
                badge.onclick = showPendingSyncs;
                scannerStatus.appendChild(badge);
                console.log('✅ Created pending sync badge');
            }
        }
        
        if (badge) {
            if (count > 0) {
                badge.textContent = `${count} pending sync${count > 1 ? 's' : ''}`;
                badge.style.display = 'inline-block';
                console.log(`✅ Badge updated: ${count} pending`);
            } else {
                badge.style.display = 'none';
                console.log('✅ Badge hidden (no pending)');
            }
        }
    }).catch(error => {
        console.error('❌ Error getting unsynced count:', error);
        
        // Try to fix corrupted records if the enhanced manager is available
        if (typeof window.cleanCorruptedAttendanceRecords === 'function') {
            console.log('🔧 Attempting to fix corrupted records...');
            window.cleanCorruptedAttendanceRecords().then(result => {
                if (result.cleaned > 0) {
                    console.log(`✅ Fixed ${result.cleaned} corrupted records. Retrying...`);
                    // Retry the count after a short delay
                    setTimeout(() => updatePendingSyncCount(), 1000);
                } else {
                    console.log('ℹ️ No corrupted records found to fix');
                }
            }).catch(fixError => {
                console.warn('⚠️ Could not fix corrupted records:', fixError.message);
            });
        }
        
        // Hide sync status bar on error
        const syncStatusBar = document.getElementById('syncStatusBar');
        if (syncStatusBar) {
            syncStatusBar.classList.add('d-none');
            syncStatusBar.style.display = 'none';
        }
    });
}

// Show pending syncs modal
function showPendingSyncs() {
    if (typeof getUnsyncedData !== 'function') return;
    
    // Use enhanced version if available
    const getRecordsFunction = window.getUnsyncedAttendanceRecords || getUnsyncedData;
    
    getRecordsFunction(STORE_NAMES.ATTENDANCE).then(records => {
        if (records.length === 0) {
            console.log('[INFO] No pending syncs');
            return;
        }
        
        let html = '<div class="list-group">';
        records.forEach((record, index) => {
            const date = new Date(record.timestamp);
            const timeStr = date.toLocaleString();
            const scanType = record.scan_type.toUpperCase();
            let scanInfo = '';
            let studentName = 'Unknown Student';
            
            if (record.scan_type === 'qr') {
                // Use stored student_name if available, otherwise show QR code
                studentName = record.scan_data.student_name || `QR: ${record.scan_data.qr_data.substring(0, 10)}...`;
                scanInfo = studentName;
            } else if (record.scan_type === 'lrn') {
                studentName = record.scan_data.student_name || `LRN: ${record.scan_data.lrn}`;
                scanInfo = studentName;
            } else if (record.scan_type === 'manual') {
                studentName = record.scan_data.student_name || record.scan_data.student_id;
                scanInfo = `Student: ${studentName}`;
            }
            
            html += `
                <div class="list-group-item">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="flex-grow-1">
                            <h6 class="mb-1"><i class="fas fa-user-check me-2"></i>${studentName}</h6>
                            <p class="mb-1 small"><strong>Scan Type:</strong> ${scanType}</p>
                            <p class="mb-1 small"><strong>Subject:</strong> ${record.scan_data.subject_name || 'Unknown'}</p>
                            <p class="mb-1 small"><strong>Location:</strong> ${record.scan_data.scan_location || 'Main Gate'}</p>
                            <small class="text-muted"><i class="far fa-clock me-1"></i>${timeStr}</small>
                        </div>
                        <span class="badge bg-warning text-dark">Pending</span>
                    </div>
                </div>
            `;
        });
        html += '</div>';
        
        // Show in modal
        const modalHtml = `
            <div class="modal fade" id="pendingSyncsModal" tabindex="-1">
                <div class="modal-dialog modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header bg-warning">
                            <h5 class="modal-title"><i class="fas fa-sync-alt me-2"></i>Pending Syncs (${records.length})</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            ${html}
                            <div class="alert alert-info mt-3 mb-0">
                                <i class="fas fa-info-circle me-2"></i>
                                These scans will be automatically synced when you're back online.
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            ${navigator.onLine ? '<button type="button" class="btn btn-primary" onclick="syncNow()"><i class="fas fa-sync me-2"></i>Sync Now</button>' : ''}
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Remove existing modal if any
        const existingModal = document.getElementById('pendingSyncsModal');
        if (existingModal) existingModal.remove();
        
        // Add modal to page
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        
        // Show modal
        const modal = new bootstrap.Modal(document.getElementById('pendingSyncsModal'));
        modal.show();
        
    }).catch(error => {
        console.error('Error showing pending syncs:', error);
        console.log('[ERROR] Error loading pending syncs');
    });
}

// Sync now button handler
function syncNow() {
    if (!navigator.onLine) {
        console.log('[WARNING] ❌ Cannot sync while offline. Please connect to the internet.');
        return;
    }
    
    // Disable sync button and show loading state
    const syncNowBtn = document.getElementById('syncNowBtn');
    if (syncNowBtn) {
        syncNowBtn.disabled = true;
        syncNowBtn.innerHTML = '<i class="fas fa-sync fa-spin me-1"></i>Syncing...';
    }
    
    console.log('[INFO] 🔄 Starting sync...');
    console.log('🔄 Manual sync initiated by user');
    
    syncQrAttendanceRecords().then(results => {
        console.log('✅ Manual sync completed:', results);
        updatePendingSyncCount();
        
        if (results.success > 0 && results.failed === 0) {
            console.log(`[SUCCESS] ✅ Successfully synced all ${results.success} record(s) to the server!`);
        } else if (results.success > 0 && results.failed > 0) {
            console.log(`[WARNING] ⚠ Synced ${results.success} record(s), but ${results.failed} failed. Will retry later.`);
        } else if (results.failed > 0) {
            console.log(`[ERROR] ❌ Failed to sync ${results.failed} record(s). Please check your connection.`);
        } else {
            console.log('[INFO] ℹ No records to sync');
        }
        
        // Re-enable button
        if (syncNowBtn) {
            syncNowBtn.disabled = false;
            syncNowBtn.innerHTML = '<i class="fas fa-sync me-1"></i>Sync Now';
        }
        
        // Close modal if open
        const modal = bootstrap.Modal.getInstance(document.getElementById('pendingSyncsModal'));
        if (modal) modal.hide();
        
    }).catch(error => {
        console.error('❌ Manual sync error:', error);
        console.log('[ERROR] ❌ Sync failed: ' + error.message);
        
        // Re-enable button
        if (syncNowBtn) {
            syncNowBtn.disabled = false;
            syncNowBtn.innerHTML = '<i class="fas fa-sync me-1"></i>Sync Now';
        }
    });
}

// Enhanced connection status update
function updateQrScannerOfflineStatus() {
    const isOnline = navigator.onLine;
    const offlineIndicator = document.getElementById('offline-mode-indicator');
    const scannerStatus = document.getElementById('scannerStatus');
    
    console.log('Connection status changed:', isOnline ? 'ONLINE' : 'OFFLINE');
    
    if (scannerStatus) {
        if (!isOnline) {
            scannerStatus.className = 'alert alert-warning';
            scannerStatus.innerHTML = '<i class="fas fa-wifi-slash me-2"></i><strong>Offline Mode:</strong> Scans will be stored locally and synced when connection is restored.';
        } else {
            scannerStatus.className = 'alert alert-info';
            scannerStatus.innerHTML = '<i class="fas fa-sync fa-spin me-2"></i><strong>Syncing pending scans...</strong>';
            
            // Trigger sync when back online with a small delay to ensure connection is stable
            console.log('Back online - triggering sync in 1 second...');
            setTimeout(() => {
                syncQrAttendanceRecords().then(results => {
                    console.log('Sync completed:', results);
                    updatePendingSyncCount();
                    
                    // Update status to show sync is complete
                    if (scannerStatus) {
                        scannerStatus.className = 'alert alert-success';
                        scannerStatus.innerHTML = '<i class="fas fa-wifi me-2"></i><strong>Online:</strong> Scans will be recorded immediately.';
                    }
                    
                    if (results.success > 0) {
                        console.log(`[SUCCESS] ✓ Successfully synced ${results.success} pending record(s) to server!`);
                        
                        // Reload recent scans to show the updated status
                        if (typeof loadRecentScansFromStorage === 'function') {
                            loadRecentScansFromStorage();
                        }
                    }
                    
                    if (results.failed > 0) {
                        console.log(`[WARNING] ⚠ ${results.failed} record(s) failed to sync. Will retry later.`);
                    }
                }).catch(error => {
                    console.error('Sync failed:', error);
                    if (scannerStatus) {
                        scannerStatus.className = 'alert alert-success';
                        scannerStatus.innerHTML = '<i class="fas fa-wifi me-2"></i><strong>Online:</strong> Scans will be recorded immediately.';
                    }
                    console.log('[WARNING] Sync encountered an error. Will retry automatically.');
                });
            }, 1000); // Wait 1 second for connection to stabilize
        }
    }
    
    if (offlineIndicator) {
        if (!isOnline) {
            offlineIndicator.classList.remove('d-none');
            document.body.classList.add('offline-mode');
        } else {
            offlineIndicator.classList.add('d-none');
            document.body.classList.remove('offline-mode');
        }
    }
    
    // Update pending sync count
    updatePendingSyncCount();
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    // Wait a bit for enhanced cache manager to load
    setTimeout(() => {
        console.log('🚀 Initializing QR scanner with enhanced database support...');
        
        // Try enhanced cache manager first
        if (typeof window.initEnhancedCacheDB === 'function') {
            console.log('✅ Enhanced cache manager available, using it...');
            window.initEnhancedCacheDB()
                .then(() => {
                    console.log('✅ Enhanced cache database ready for QR scanner');
                    updateQrScannerOfflineStatus();
                    updatePendingSyncCount();
                })
                .catch(error => {
                    console.error('❌ Enhanced cache database failed, trying fallback:', error);
                    // Try fallback
                    tryFallbackInit();
                });
        } else {
            console.log('⚠ Enhanced cache manager not available, using fallback...');
            tryFallbackInit();
        }
    }, 500); // Give enhanced cache manager time to load
    
    function tryFallbackInit() {
        // Initialize database first
        if (typeof initOfflineDB === 'function') {
            console.log('🔄 Initializing offline database for QR scanner...');
            initOfflineDB()
                .then(() => {
                    console.log('✅ Offline database ready for QR scanner');
                    // Update status on load
                    updateQrScannerOfflineStatus();
                    updatePendingSyncCount();
                })
                .catch(error => {
                    console.error('❌ Failed to initialize offline database:', error);
                    // Show user-friendly error message
                    console.log('[WARNING] Database initialization failed. Some offline features may not work.');
                    // Still update status even if DB fails
                    updateQrScannerOfflineStatus();
                });
        } else {
            console.error('❌ initOfflineDB function not found. Please ensure offline-forms.js is loaded.');
            console.log('[WARNING] Offline functionality not available. Please refresh the page.');
        }
    }
    
    // Listen for online/offline events
    window.addEventListener('online', updateQrScannerOfflineStatus);
    window.addEventListener('offline', updateQrScannerOfflineStatus);
    
    // Periodic sync check (every 30 seconds when online)
    setInterval(() => {
        if (navigator.onLine) {
            syncQrAttendanceRecords().then(results => {
                if (results.success > 0) {
                    updatePendingSyncCount();
                }
            });
        }
    }, 30000);
});

// Export functions for global use
window.handleEnhancedOfflineQrScan = handleEnhancedOfflineQrScan;
window.handleEnhancedOfflineLrnScan = handleEnhancedOfflineLrnScan;
window.handleEnhancedOfflineManualSelection = handleEnhancedOfflineManualSelection;
window.syncQrAttendanceRecords = syncQrAttendanceRecords;
window.updatePendingSyncCount = updatePendingSyncCount;
window.showPendingSyncs = showPendingSyncs;
window.syncNow = syncNow;
window.storeOfflineQrAttendance = storeOfflineQrAttendance;
