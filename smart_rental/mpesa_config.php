<?php
/**
 * M-Pesa Configuration File
 * Test Environment Credentials
 */

// M-Pesa API Configuration
// These are test credentials - replace with your actual credentials
define('MPESA_CONSUMER_KEY', 'F2VHzOVLYbdDBYXGXlfPkFbjAhbvJlPXzHXwkzUveUAm4ofQ');
define('MPESA_CONSUMER_SECRET', 'qET5Kmd6sD9Q8AGRk5KhLt6A1aD9UILcnvbQFukTSZZFiYFeNXckodggDezYA6wz');

// Note: If you're getting 403 errors, these credentials might be expired or incorrect
// You need to get fresh credentials from Safaricom Developer Portal

// M-Pesa API URLs (Test Environment)
define('MPESA_BASE_URL', 'https://sandbox.safaricom.co.ke');
define('MPESA_AUTH_URL', MPESA_BASE_URL . '/oauth/v1/generate?grant_type=client_credentials');
define('MPESA_STK_PUSH_URL', MPESA_BASE_URL . '/mpesa/stkpush/v1/processrequest');
define('MPESA_STK_QUERY_URL', MPESA_BASE_URL . '/mpesa/stkpushquery/v1/query');

// Business Configuration
define('MPESA_BUSINESS_SHORTCODE', '174379'); // Test till number
define('MPESA_PASSKEY', 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919');
define('MPESA_TRANSACTION_TYPE', 'CustomerPayBillOnline');
define('MPESA_PARTYB', '174379'); // Same as shortcode for till payments

// Callback URL - Using ngrok for local development
// Callback URL Configuration
define('MPESA_CALLBACK_URL', 'https://3d666ff3fc03.ngrok-free.app/rental_system_bse/smart_rental/mpesa_callback.php');

// define('MPESA_CALLBACK_URL', 'https://yourdomain.com/smart_rental/mpesa_callback.php');

// Environment
define('MPESA_ENVIRONMENT', 'sandbox'); // Change to 'live' for production

// Error Messages
define('MPESA_ERROR_MESSAGES', [
    '0' => 'Success',
    '1' => 'Insufficient funds',
    '2' => 'Less than minimum transaction value',
    '3' => 'More than maximum transaction value',
    '4' => 'Would exceed daily transfer limit',
    '5' => 'Would exceed minimum balance',
    '20' => 'Request timeout',
    '26' => 'Unable to process the request',
    '1032' => 'Request cancelled by user',
    '1037' => 'Timeout - unable to process request',
    '1038' => 'Transaction failed',
    '1039' => 'Request cancelled by user',
    '2001' => 'The transaction is still under processing',
    '2002' => 'Transaction is being processed',
    '2003' => 'Transaction is pending',
    '2004' => 'Transaction is in progress',
    '1001' => 'Invalid request',
    '1002' => 'Invalid credentials',
    '1003' => 'Invalid amount',
    '1004' => 'Invalid phone number',
    '1005' => 'Invalid shortcode',
    '1006' => 'Invalid reference',
    '1007' => 'Invalid callback URL',
    '1008' => 'Invalid passkey',
    '1009' => 'Invalid timestamp',
    '1010' => 'Invalid password',
    '1011' => 'Invalid username',
    '1012' => 'Invalid account',
    '1013' => 'Invalid transaction type',
    '1014' => 'Invalid party A',
    '1015' => 'Invalid party B',
    '1016' => 'Invalid party C',
    '1017' => 'Invalid account reference',
    '1018' => 'Invalid transaction description',
    '1019' => 'Invalid business shortcode',
    '1020' => 'Invalid till number',
    '1021' => 'Invalid paybill number',
    '1022' => 'Invalid organization',
    '1023' => 'Invalid service provider',
    '1024' => 'Invalid service name',
    '1025' => 'Invalid service code',
    '1026' => 'Invalid service type',
    '1027' => 'Invalid service category',
    '1028' => 'Invalid service subcategory',
    '1029' => 'Invalid service description',
    '1030' => 'Invalid service amount',
    '1031' => 'Invalid service currency',
    '1033' => 'Invalid service provider code',
    '1034' => 'Invalid service provider name',
    '1035' => 'Invalid service provider type',
    '1036' => 'Invalid service provider category',
    '1040' => 'Invalid service provider subcategory',
    '1041' => 'Invalid service provider description',
    '1042' => 'Invalid service provider amount',
    '1043' => 'Invalid service provider currency',
    '1044' => 'Invalid service provider reference',
    '1045' => 'Invalid service provider transaction type',
    '1046' => 'Invalid service provider party A',
    '1047' => 'Invalid service provider party B',
    '1048' => 'Invalid service provider party C',
    '1049' => 'Invalid service provider account reference',
    '1050' => 'Invalid service provider transaction description',
    '1051' => 'Invalid service provider business shortcode',
    '1052' => 'Invalid service provider till number',
    '1053' => 'Invalid service provider paybill number',
    '1054' => 'Invalid service provider organization',
    '1055' => 'Invalid service provider service name',
    '1056' => 'Invalid service provider service code',
    '1057' => 'Invalid service provider service type',
    '1058' => 'Invalid service provider service category',
    '1059' => 'Invalid service provider service subcategory',
    '1060' => 'Invalid service provider service description',
    '1061' => 'Invalid service provider service amount',
    '1062' => 'Invalid service provider service currency',
    '1063' => 'Invalid service provider service reference',
    '1064' => 'Invalid service provider service transaction type',
    '1065' => 'Invalid service provider service party A',
    '1066' => 'Invalid service provider service party B',
    '1067' => 'Invalid service provider service party C',
    '1068' => 'Invalid service provider service account reference',
    '1069' => 'Invalid service provider service transaction description',
    '1070' => 'Invalid service provider service business shortcode',
    '1071' => 'Invalid service provider service till number',
    '1072' => 'Invalid service provider service paybill number',
    '1073' => 'Invalid service provider service organization',
    '1074' => 'Invalid service provider service service name',
    '1075' => 'Invalid service provider service service code',
    '1076' => 'Invalid service provider service service type',
    '1077' => 'Invalid service provider service service category',
    '1078' => 'Invalid service provider service service subcategory',
    '1079' => 'Invalid service provider service service description',
    '1080' => 'Invalid service provider service service amount',
    '1081' => 'Invalid service provider service service currency',
    '1082' => 'Invalid service provider service service reference',
    '1083' => 'Invalid service provider service service transaction type',
    '1084' => 'Invalid service provider service service party A',
    '1085' => 'Invalid service provider service service party B',
    '1086' => 'Invalid service provider service service party C',
    '1087' => 'Invalid service provider service service account reference',
    '1088' => 'Invalid service provider service service transaction description',
    '1089' => 'Invalid service provider service service business shortcode',
    '1090' => 'Invalid service provider service service till number',
    '1091' => 'Invalid service provider service service paybill number',
    '1092' => 'Invalid service provider service service organization',
    '1093' => 'Invalid service provider service service service name',
    '1094' => 'Invalid service provider service service service code',
    '1095' => 'Invalid service provider service service service type',
    '1096' => 'Invalid service provider service service service category',
    '1097' => 'Invalid service provider service service service subcategory',
    '1098' => 'Invalid service provider service service service description',
    '1099' => 'Invalid service provider service service service amount',
    '1100' => 'Invalid service provider service service service currency'
]);

/**
 * Get M-Pesa Access Token
 */
function getMpesaAccessToken() {
    $credentials = base64_encode(MPESA_CONSUMER_KEY . ':' . MPESA_CONSUMER_SECRET);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, MPESA_AUTH_URL);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Basic ' . $credentials,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    // Log the request details
    error_log("M-Pesa Auth Request - URL: " . MPESA_AUTH_URL . ", HTTP Code: $httpCode");
    error_log("M-Pesa Auth Request - Credentials: " . substr($credentials, 0, 20) . "...");
    error_log("M-Pesa Auth Response: " . substr($response, 0, 500) . "...");
    
    if ($curlError) {
        error_log("M-Pesa Auth cURL Error: $curlError");
        return null;
    }
    
    if ($httpCode === 200) {
        $result = json_decode($response, true);
        if (isset($result['access_token'])) {
            error_log("M-Pesa Auth Success - Token obtained");
            return $result['access_token'];
        } else {
            error_log("M-Pesa Auth Error - No access_token in response: " . json_encode($result));
        }
    } elseif ($httpCode === 403) {
        error_log("M-Pesa Auth Error - 403 Forbidden. This usually means:");
        error_log("1. Invalid or expired credentials");
        error_log("2. IP not whitelisted");
        error_log("3. Account suspended");
        error_log("4. Incorrect API endpoint");
    } else {
        error_log("M-Pesa Auth Error - HTTP Code: $httpCode, Response: $response");
    }
    
    return null;
}

/**
 * Generate M-Pesa Password
 */
function generateMpesaPassword() {
    $timestamp = date('YmdHis');
    $password = base64_encode(MPESA_BUSINESS_SHORTCODE . MPESA_PASSKEY . $timestamp);
    return [
        'password' => $password,
        'timestamp' => $timestamp
    ];
}

/**
 * Format Phone Number for M-Pesa
 */
function formatPhoneNumber($phone) {
    // Remove any non-digit characters
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // If number starts with 0, replace with 254
    if (substr($phone, 0, 1) === '0') {
        $phone = '254' . substr($phone, 1);
    }
    
    // If number starts with +254, remove the +
    if (substr($phone, 0, 4) === '+254') {
        $phone = substr($phone, 1);
    }
    
    // If number doesn't start with 254, add it
    if (substr($phone, 0, 3) !== '254') {
        $phone = '254' . $phone;
    }
    
    return $phone;
}

/**
 * Get Error Message by Code
 */
function getMpesaErrorMessage($errorCode) {
    return MPESA_ERROR_MESSAGES[$errorCode] ?? 'Unknown error occurred';
}
?> 