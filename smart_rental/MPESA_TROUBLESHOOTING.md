# M-Pesa Integration Troubleshooting Guide

## Current Issues Identified

### 1. 403 Forbidden Error
**Problem**: M-Pesa API returning 403 error with Incapsula security page
**Cause**: Invalid or expired API credentials
**Solution**: 
- Get fresh credentials from Safaricom Developer Portal
- Ensure your IP is whitelisted
- Check if your account is active

### 2. Callback URL HTTP 405 Error
**Problem**: Callback URL returning "Method Not Allowed"
**Cause**: The callback endpoint doesn't accept POST requests properly
**Solution**: 
- Test callback URL: `test_callback.php`
- Ensure ngrok tunnel is active
- Check if callback URL is accessible

## Testing Steps

### Step 1: Test Callback URL
```bash
# Test if callback URL is accessible
curl -X POST https://71697fa889e3.ngrok-free.app/rental_system_bse/smart_rental/test_callback.php
```

### Step 2: Test M-Pesa API Credentials
1. Go to: `test_mpesa_connection.php`
2. Check if access token is obtained
3. If 403 error persists, credentials need updating

### Step 3: Manual Payment Testing
1. Use: `manual_payment_status.php` for testing
2. Make a payment and manually update status
3. Verify booking status updates correctly

## Common Solutions

### For 403 Error:
1. **Get Fresh Credentials**:
   - Visit: https://developer.safaricom.co.ke/
   - Create new app or regenerate credentials
   - Update `MPESA_CONSUMER_KEY` and `MPESA_CONSUMER_SECRET`

2. **Whitelist Your IP**:
   - Add your server IP to Safaricom whitelist
   - For ngrok, use the ngrok IP

3. **Check Account Status**:
   - Ensure your Safaricom developer account is active
   - Verify you have sufficient API credits

### For Callback Issues:
1. **Test Callback URL**:
   - Visit: `test_callback.php` directly
   - Should return JSON response

2. **Check Ngrok Tunnel**:
   - Ensure ngrok is running: `ngrok http 80`
   - Update callback URL if tunnel changes

3. **Verify File Permissions**:
   - Ensure `mpesa_callback.php` is accessible
   - Check file permissions (644 or 755)

## Alternative Testing Approach

Since M-Pesa API has issues, use manual testing:

1. **Make Payment**: Complete payment on phone
2. **Manual Update**: Go to `manual_payment_status.php`
3. **Mark Complete**: Click "Mark Completed" for your payment
4. **Verify**: Check booking status updates

## Debug Tools Available

1. **`test_mpesa_connection.php`** - Test API connectivity
2. **`test_mpesa_status.php`** - Test payment status
3. **`manual_payment_status.php`** - Manual status updates
4. **`test_callback.php`** - Test callback URL

## Next Steps

1. **Get Fresh Credentials** from Safaricom
2. **Test Callback URL** accessibility
3. **Use Manual Testing** until API issues are resolved
4. **Monitor Error Logs** for detailed debugging

## Error Log Locations

- **PHP Error Log**: Check your server's error log
- **M-Pesa Logs**: `logs/mpesa_debug.log`
- **Callback Logs**: `logs/mpesa_callback.log`

## Contact Information

If issues persist:
- Safaricom Developer Support: https://developer.safaricom.co.ke/support
- Check API documentation: https://developer.safaricom.co.ke/docs 