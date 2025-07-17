<?php
/**
 * Cron job to process scheduled SMS notifications
 * This file should be run every minute by a cron job
 * 
 * Example cron entry (run every minute):
 * * * * * * /usr/bin/php /path/to/smart/cron/process_sms.php
 */

// Include the necessary files
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/sms_functions.php';

// Set time limit for the script
set_time_limit(60);

// Log the start of the process
error_log('SMS Processing Cron Job Started: ' . date('Y-m-d H:i:s'));

try {
    // Process scheduled SMS notifications
    processScheduledSMS();
    
    // Log successful completion
    error_log('SMS Processing Cron Job Completed Successfully: ' . date('Y-m-d H:i:s'));
    
} catch (Exception $e) {
    // Log any errors
    error_log('SMS Processing Cron Job Error: ' . $e->getMessage() . ' at ' . date('Y-m-d H:i:s'));
}

?>
