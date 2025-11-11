# IPROG SMS API Migration

This document explains the migration from PhilSMS to IPROG SMS API.

## Changes Made

### 1. SMS Functions (`sms_functions.php`)
- Replaced `sendSMSUsingPhilSMS()` with `sendSMSUsingIPROG()`
- Updated API endpoint from PhilSMS to IPROG SMS
- Changed request format from JSON to form-urlencoded
- Updated phone number formatting (removed + sign for IPROG)
- Modified response handling for IPROG SMS format

### 2. SMS Configuration (`sms-config.php`)
- Updated default provider name to "IPROG SMS"
- Changed default API URL to IPROG SMS endpoint
- Updated test SMS function to use IPROG implementation
- Modified help text references from PhilSMS to IPROG

### 3. Core Configuration (`config.php`)
- Updated SMS notification function to use IPROG implementation

## IPROG SMS API Details

**Endpoint:** `https://sms.iprogtech.com/api/v1/sms_messages`

**Method:** POST

**Content-Type:** application/x-www-form-urlencoded

**Required Parameters:**
- `api_token` - Your IPROG SMS API token
- `message` - SMS message content
- `phone_number` - Recipient phone number (format: 639XXXXXXXXX)

**Example Request:**
```php
$data = [
    'api_token' => 'your_api_token',
    'message' => 'Hello World!',
    'phone_number' => '639171071234'
];
```

## Phone Number Formatting

The implementation automatically formats phone numbers:
- Input: `09171071234` → Output: `639171071234`
- Input: `+639171071234` → Output: `639171071234`
- Input: `639171071234` → Output: `639171071234` (no change)

## Configuration Steps

1. **Get IPROG SMS API Token:**
   - Sign up at IPROG SMS dashboard
   - Generate your API token

2. **Update SMS Configuration:**
   - Go to SMS Configuration page in admin panel
   - Set Provider Name: "IPROG SMS"
   - Set API URL: "https://sms.iprogtech.com/api/v1/sms_messages"
   - Enter your API token
   - Set status to "Active"

3. **Test Implementation:**
   - Use the test SMS feature in the configuration page
   - Or run the test file: `test-iprog-sms.php`

## Files Modified

- `sms_functions.php` - Core SMS sending functions
- `sms-config.php` - SMS configuration interface
- `config.php` - Core notification function
- `test-iprog-sms.php` - Test file (new)

## Backward Compatibility

The changes maintain the same function signatures and return formats, so existing code calling SMS functions will continue to work without modification.

## Error Handling

The IPROG implementation includes comprehensive error handling:
- Connection errors
- API response errors
- Phone number validation
- Detailed logging for debugging

## Testing

Use the `test-iprog-sms.php` file to verify the implementation works correctly with your IPROG SMS credentials.