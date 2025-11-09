/*
 * Emergency Fix for IndexedDB "not a valid key" Error
 * 
 * This script provides immediate solutions for users experiencing
 * the "Failed to execute 'getAll' on 'IDBIndex': The parameter is not a valid key" error.
 * 
 * INSTRUCTIONS:
 * 1. Open browser DevTools (F12)
 * 2. Go to Console tab
 * 3. Copy and paste this entire script
 * 4. Press Enter to run
 * 5. Refresh the page
 */

console.log('🚑 Emergency IndexedDB Fix Script Starting...');

// Function to immediately fix the database
async function emergencyDatabaseFix() {
    try {
        console.log('🔧 Step 1: Attempting to clean corrupted records...');
        
        // Try the new cleaning function if available
        if (typeof window.cleanCorruptedAttendanceRecords === 'function') {
            const result = await window.cleanCorruptedAttendanceRecords();
            console.log(`✅ Fixed ${result.cleaned} of ${result.total} records`);
            
            if (result.cleaned > 0) {
                console.log('✅ Database cleaning successful! Try using the scanner again.');
                return true;
            }
        }
        
        console.log('🔧 Step 2: Attempting database reset...');
        
        // Try reset if cleaning didn't work
        if (typeof window.resetEnhancedCacheDB === 'function') {
            await window.resetEnhancedCacheDB();
            console.log('✅ Database reset successful! Please refresh the page.');
            return true;
        }
        
        // Fallback manual fix
        console.log('🔧 Step 3: Manual database fix...');
        
        // Delete the problematic database
        const dbName = 'kes-smart-offline-data';
        
        return new Promise((resolve) => {
            const deleteRequest = indexedDB.deleteDatabase(dbName);
            
            deleteRequest.onsuccess = () => {
                console.log('✅ Manual database deletion successful!');
                console.log('🔄 Please refresh the page to recreate the database.');
                resolve(true);
            };
            
            deleteRequest.onerror = (event) => {
                console.error('❌ Manual fix failed:', event.target.error);
                resolve(false);
            };
            
            deleteRequest.onblocked = () => {
                console.warn('⚠️ Database deletion blocked.');
                console.log('📋 MANUAL STEPS:');
                console.log('   1. Close all other tabs with this website');
                console.log('   2. Run this script again');
                console.log('   3. Or restart your browser');
                resolve(false);
            };
        });
        
    } catch (error) {
        console.error('❌ Emergency fix failed:', error);
        console.log('📋 MANUAL STEPS TO FIX:');
        console.log('   1. Close all tabs with this website');
        console.log('   2. Clear browser data for this site:');
        console.log('      - Press F12 → Application tab → Storage → Clear site data');
        console.log('   3. Refresh the page');
        console.log('   4. If still not working, restart your browser');
        return false;
    }
}

// Function to check current database status
function checkDatabaseStatus() {
    console.log('🔍 Checking database status...');
    
    if (typeof window.db !== 'undefined' && window.db) {
        console.log('✅ Database connection exists');
        console.log('   - Database name:', window.db.name);
        console.log('   - Database version:', window.db.version);
        console.log('   - Object stores:', Array.from(window.db.objectStoreNames));
    } else {
        console.log('❌ No database connection found');
    }
    
    if (typeof window.STORE_NAMES !== 'undefined') {
        console.log('✅ Store names available:', window.STORE_NAMES);
    } else {
        console.log('❌ Store names not available');
    }
    
    // Test the problematic function
    if (typeof window.getUnsyncedAttendanceRecords === 'function') {
        console.log('🧪 Testing the problematic function...');
        window.getUnsyncedAttendanceRecords()
            .then(records => {
                console.log('✅ Function working! Found', records.length, 'records');
            })
            .catch(error => {
                console.log('❌ Function still failing:', error.message);
                console.log('🚑 Running emergency fix...');
                emergencyDatabaseFix();
            });
    } else {
        console.log('❌ getUnsyncedAttendanceRecords function not available');
    }
}

// Main execution
(function() {
    console.log('='.repeat(60));
    console.log('🚑 EMERGENCY INDEXEDDB FIX SCRIPT');
    console.log('='.repeat(60));
    
    // Check current status first
    checkDatabaseStatus();
    
    // Wait a moment then offer manual fix
    setTimeout(() => {
        console.log('\n🤔 If you\'re still seeing errors, type this command:');
        console.log('💡 emergencyDatabaseFix()');
        console.log('\n📋 Or copy-paste this for immediate fix:');
        console.log('🔧 window.resetEnhancedCacheDB().then(() => location.reload())');
        
        // Make functions available globally
        window.emergencyDatabaseFix = emergencyDatabaseFix;
        window.checkDatabaseStatus = checkDatabaseStatus;
        
        console.log('\n✅ Emergency functions now available:');
        console.log('   - emergencyDatabaseFix()');
        console.log('   - checkDatabaseStatus()');
        
    }, 2000);
})();

console.log('🚑 Emergency script loaded. Check messages above for next steps.');