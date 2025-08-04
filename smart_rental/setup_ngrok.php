<?php
/**
 * ngrok Setup Script for M-Pesa Callbacks
 * This script helps you configure ngrok for local development
 */

echo "🚀 M-Pesa ngrok Setup Guide\n";
echo "=============================\n\n";

echo "📋 Step 1: Install ngrok\n";
echo "1. Go to https://ngrok.com/\n";
echo "2. Download ngrok for Windows\n";
echo "3. Extract ngrok.exe to C:\\ngrok\\\n";
echo "4. Add C:\\ngrok\\ to your PATH environment variable\n\n";

echo "📋 Step 2: Start ngrok\n";
echo "Open a new command prompt and run:\n";
echo "ngrok http 80\n\n";

echo "📋 Step 3: Update your callback URL\n";
echo "When ngrok starts, you'll see something like:\n";
echo "Forwarding    https://abc123.ngrok.io -> http://localhost:80\n\n";

echo "📋 Step 4: Update mpesa_config.php\n";
echo "Replace the callback URL in mpesa_config.php with your ngrok URL:\n";
echo "define('MPESA_CALLBACK_URL', 'https://YOUR-NGROK-URL.ngrok.io/rental_system_bse/smart_rental/mpesa_callback.php');\n\n";

echo "📋 Step 5: Test the callback\n";
echo "Your callback endpoint will be available at:\n";
echo "https://YOUR-NGROK-URL.ngrok.io/rental_system_bse/smart_rental/mpesa_callback.php\n\n";

echo "📋 Step 6: Monitor callbacks\n";
echo "Callbacks will be logged to: smart_rental/logs/mpesa_callback.log\n\n";

echo "🔧 Current Configuration:\n";
echo "========================\n";

// Check if callback file exists
if (file_exists(__DIR__ . '/mpesa_callback.php')) {
    echo "✅ Callback endpoint: mpesa_callback.php (exists)\n";
} else {
    echo "❌ Callback endpoint: mpesa_callback.php (missing)\n";
}

// Check if logs directory exists
$logsDir = __DIR__ . '/logs';
if (is_dir($logsDir)) {
    echo "✅ Logs directory: logs/ (exists)\n";
} else {
    echo "⚠️  Logs directory: logs/ (will be created automatically)\n";
}

// Check if config file exists
if (file_exists(__DIR__ . '/mpesa_config.php')) {
    echo "✅ Config file: mpesa_config.php (exists)\n";
} else {
    echo "❌ Config file: mpesa_config.php (missing)\n";
}

echo "\n🎯 Next Steps:\n";
echo "1. Start ngrok: ngrok http 80\n";
echo "2. Copy the ngrok URL\n";
echo "3. Update mpesa_config.php with the ngrok URL\n";
echo "4. Test M-Pesa payment\n";
echo "5. Check logs/mpesa_callback.log for callbacks\n\n";

echo "💡 Tips:\n";
echo "- Keep ngrok running while testing\n";
echo "- The ngrok URL changes each time you restart ngrok\n";
echo "- Use ngrok with a paid account for static URLs\n";
echo "- Check ngrok web interface at http://localhost:4040\n\n";

echo "🔗 Useful Links:\n";
echo "- ngrok: https://ngrok.com/\n";
echo "- M-Pesa API Docs: https://developer.safaricom.co.ke/\n";
echo "- ngrok Web Interface: http://localhost:4040 (when running)\n";
?> 