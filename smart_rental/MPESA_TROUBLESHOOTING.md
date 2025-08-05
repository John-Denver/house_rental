# M-Pesa STK Push Troubleshooting Guide

## Issue: "Payment Failed - Unknown error occurred" before entering PIN

### Root Causes Identified:

1. **Nested Callback Structure**: M-Pesa sends callbacks in a nested format that wasn't being handled properly
2. **Expired/Invalid ngrok URL**: The callback URL might be using an expired ngrok tunnel
3. **M-Pesa Test Environment Issues**: Test credentials might have limitations

### Solutions Implemented:

#### 1. Fixed Callback Structure Handling ✅

**File**: `mpesa_callback.php`
**Issue**: M-Pesa sends callbacks in this format:
```json
{
  "Body": {
    "stkCallback": {
      "MerchantRequestID": "...",
      "CheckoutRequestID": "...",
      "ResultCode": 1032,
      "ResultDesc": "Request Cancelled by user"
    }
  }
}
```

**Fix**: Added code to handle nested structure:
```php
// Handle nested callback structure
if (isset($callbackData['Body']['stkCallback'])) {
    $callbackData = $callbackData['Body']['stkCallback'];
}
```

#### 2. Updated ngrok URL Configuration ✅

**File**: `mpesa_config.php`
**Issue**: Using expired ngrok URL
**Fix**: Updated to use placeholder that needs to be updated with current ngrok URL

#### 3. Enhanced Error Handling ✅

**Files**: `mpesa_stk_push.php`, `booking_payment.php`
**Improvements**:
- Better error messages for specific M-Pesa error codes
- More detailed logging
- User-friendly error messages in the frontend

### Steps to Fix Your Issue:

#### Step 1: Update ngrok URL
1. Start ngrok: `ngrok http 80`
2. Copy the new URL (e.g., `https://abc123.ngrok-free.app`)
3. Update `mpesa_config.php`:
```php
define('MPESA_CALLBACK_URL', 'https://your-new-ngrok-url.ngrok-free.app/rental_system_bse/smart_rental/mpesa_callback.php');
```

#### Step 2: Test M-Pesa Configuration
Run the test script:
```bash
php test_mpesa_config.php
```

#### Step 3: Test STK Push
Run the debug script:
```bash
php debug_mpesa_stk_push.php
```

#### Step 4: Check Logs
Monitor these log files:
- `logs/mpesa_callback.log` - Callback responses
- `logs/mpesa_debug.log` - STK push requests

### Common M-Pesa Error Codes:

| Code | Meaning | Solution |
|------|---------|----------|
| 0 | Success | Payment completed |
| 1 | Insufficient funds | User needs more money in M-Pesa |
| 1032 | Request cancelled by user | User cancelled the payment |
| 1037 | Timeout | Request expired, try again |
| 1038 | Transaction failed | Technical issue, try again |
| 1001 | Invalid request | Check phone number and amount |
| 1002 | Invalid credentials | Check M-Pesa API credentials |
| 1003 | Invalid amount | Amount must be > 0 |
| 1004 | Invalid phone number | Check phone number format |

### Testing Checklist:

- [ ] ngrok is running and accessible
- [ ] Callback URL is updated with current ngrok URL
- [ ] M-Pesa test credentials are valid
- [ ] Phone number is in correct format (254XXXXXXXXX)
- [ ] Amount is valid (> 0)
- [ ] Network connection is stable

### Debug Commands:

```bash
# Test configuration
php test_mpesa_config.php

# Test STK push
php debug_mpesa_stk_push.php

# Check callback logs
tail -f logs/mpesa_callback.log

# Check debug logs
tail -f logs/mpesa_debug.log
```

### If Still Having Issues:

1. **Check ngrok tunnel**: Make sure ngrok is running and the URL is accessible
2. **Verify M-Pesa credentials**: Test credentials might have expired
3. **Check phone number**: Must be registered with M-Pesa
4. **Test with small amount**: Try with KSh 1 first
5. **Check network**: Ensure stable internet connection

### Production Considerations:

- Use live M-Pesa credentials instead of test credentials
- Use a proper domain instead of ngrok
- Implement proper SSL certificates
- Add rate limiting and security measures
- Monitor payment logs regularly 