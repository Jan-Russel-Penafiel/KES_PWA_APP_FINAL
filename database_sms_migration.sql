-- Database update script to migrate from PhilSMS to IPROG SMS
-- Run this script on existing databases to update SMS configuration

-- Update existing SMS configuration to use IPROG SMS
UPDATE sms_config 
SET 
    provider_name = 'IPROG SMS',
    api_url = 'https://sms.iprogtech.com/api/v1/sms_messages',
    api_key = '1ef3b27ea753780a90cbdf07d027fb7b52791004',
    sender_name = 'KES-SMART',
    status = 'active'
WHERE id = 1;

-- Configuration is now ready to use with IPROG SMS API