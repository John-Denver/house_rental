# Smart Rental System - M-Pesa Integration

## 🏠 Project Overview
Smart Rental is a property rental management system with integrated M-Pesa payment processing for the Kenyan market.

## 🚀 Quick Start

### Prerequisites
- XAMPP (Apache + MySQL + PHP)
- ngrok (for M-Pesa callbacks)
- M-Pesa Developer Account

### Installation
1. Clone/Download this project to `C:\xampp\htdocs\rental_system_bse\`
2. Start XAMPP (Apache + MySQL)
3. Import database files from `smart_rental/database/`
4. Configure M-Pesa integration (see below)

## 💳 M-Pesa Integration Setup

### Step 1: Install ngrok
1. Download ngrok from https://ngrok.com/
2. Extract `ngrok.exe` to `C:\ngrok\`
3. Add `C:\ngrok\` to your PATH environment variable

### Step 2: Start ngrok
```bash
ngrok http 80
```
You'll see output like:
```
Forwarding    https://abc123.ngrok.io -> http://localhost:80
```

### Step 3: Update M-Pesa Configuration
**⚠️ IMPORTANT: This step must be repeated every time you restart ngrok!**

When ngrok starts, copy the new URL and update this file:
```
File: smart_rental/mpesa_config.php
Line: 24
```

**Current configuration:**
```php
define('MPESA_CALLBACK_URL', 'https://YOUR-NGROK-URL.ngrok.io/rental_system_bse/smart_rental/mpesa_callback.php');
```

**Replace `YOUR-NGROK-URL` with your actual ngrok URL.**

### Step 4: Test the Setup
Run the test script to verify everything is working:
```bash
cd smart_rental
php test_ngrok_url.php
```

## 📁 Project Structure

```
rental_system_bse/
├── smart_rental/                 # Main rental system
│   ├── mpesa_config.php         # M-Pesa configuration (UPDATE THIS!)
│   ├── mpesa_callback.php       # Callback endpoint
│   ├── test_ngrok_url.php       # Test script
│   ├── setup_ngrok.php          # Setup guide
│   └── logs/                    # Callback logs
├── smart_landlords/             # Landlord management system
└── README.md                    # This file
```

## 🔧 Configuration Files

### M-Pesa Configuration (`smart_rental/mpesa_config.php`)
**⚠️ UPDATE REQUIRED WHEN NGROK RESTARTS**

Key settings to update:
- `MPESA_CALLBACK_URL`: Your ngrok URL + callback path
- `MPESA_ENVIRONMENT`: 'sandbox' for testing, 'live' for production

### Database Configuration
- Database files: `smart_rental/database/`
- Import all SQL files to your MySQL database

## 📊 Monitoring & Testing

### ngrok Web Interface
- URL: http://localhost:4040
- Shows all incoming requests to your ngrok URL

### Callback Logs
- Location: `smart_rental/logs/mpesa_callback.log`
- Contains all M-Pesa callback data

### Test Scripts
```bash
# Test ngrok URL accessibility
php test_ngrok_url.php

# Test callback endpoint
php test_ngrok_callback.php

# View setup guide
php setup_ngrok.php
```

## 🔄 When ngrok URL Changes

**Every time you restart ngrok, you MUST update the callback URL:**

1. **Start ngrok:**
   ```bash
   ngrok http 80
   ```

2. **Copy the new URL** (e.g., `https://xyz789.ngrok.io`)

3. **Update the configuration file:**
   ```
   File: smart_rental/mpesa_config.php
   Line: 24
   ```
   
   Change:
   ```php
   define('MPESA_CALLBACK_URL', 'https://OLD-URL.ngrok.io/rental_system_bse/smart_rental/mpesa_callback.php');
   ```
   
   To:
   ```php
   define('MPESA_CALLBACK_URL', 'https://NEW-URL.ngrok.io/rental_system_bse/smart_rental/mpesa_callback.php');
   ```

4. **Test the new URL:**
   ```bash
   php test_ngrok_url.php
   ```

## 🎯 Current Configuration Status

- ✅ **ngrok URL**: `https://b49f2da54ab7.ngrok-free.app`
- ✅ **Callback endpoint**: `/rental_system_bse/smart_rental/mpesa_callback.php`
- ✅ **Full callback URL**: `https://b49f2da54ab7.ngrok-free.app/rental_system_bse/smart_rental/mpesa_callback.php`
- ✅ **M-Pesa config**: Updated and working
- ✅ **Test results**: All tests passing

## 🚨 Troubleshooting

### ngrok URL Not Working
1. Check if ngrok is running: `ngrok http 80`
2. Verify the URL in ngrok output
3. Update `mpesa_config.php` with the correct URL
4. Test with `php test_ngrok_url.php`

### Callback Not Receiving Data
1. Check ngrok web interface: http://localhost:4040
2. Verify callback logs: `smart_rental/logs/mpesa_callback.log`
3. Ensure XAMPP is running on port 80
4. Test callback endpoint manually

### M-Pesa Payment Issues
1. Verify sandbox credentials in `mpesa_config.php`
2. Check M-Pesa API documentation
3. Monitor callback logs for error messages
4. Test with M-Pesa sandbox environment

## 📞 Support

- **M-Pesa API Docs**: https://developer.safaricom.co.ke/
- **ngrok Documentation**: https://ngrok.com/docs
- **Project Issues**: Check the logs in `smart_rental/logs/`

## 🔐 Security Notes

- Keep ngrok running only during development/testing
- Use paid ngrok accounts for production (static URLs)
- Never commit real M-Pesa credentials to version control
- Monitor callback logs for suspicious activity

---

**Last Updated**: August 2025  
**ngrok URL**: `https://b49f2da54ab7.ngrok-free.app`  
**Status**: ✅ Ready for M-Pesa testing 