<?php
/**
 * Debug Payment Response
 * Test the M-Pesa payment response handling
 */

// Simulate different M-Pesa responses to test the frontend logic
$testResponses = [
    'success' => [
        'success' => true,
        'data' => [
            'checkout_request_id' => 'ws_CO_050820251712123456',
            'status' => 'pending'
        ]
    ],
    'processing' => [
        'success' => false,
        'error' => [
            'ResultCode' => '4999',
            'ResultDesc' => 'The transaction is still under processing'
        ]
    ],
    'completed' => [
        'success' => true,
        'data' => [
            'status' => 'completed',
            'receipt_number' => 'TEST123456',
            'amount' => 1000
        ]
    ],
    'failed' => [
        'success' => false,
        'message' => 'Payment failed'
    ]
];

$testType = $_GET['test'] ?? 'success';
$response = $testResponses[$testType] ?? $testResponses['success'];

header('Content-Type: application/json');
echo json_encode($response);
?> 