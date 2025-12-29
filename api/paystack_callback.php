<?php
session_start();
require_once "../includes/functions.php";

// Paystack callback after payment
$reference = $_GET['reference'] ?? '';

if (empty($reference)) {
    header("Location: ../plan.php?status=error&message=Invalid reference");
    exit;
}

$paystackSecretKey = env('PAYSTACK_SECRET_KEY');

// Verify transaction
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://api.paystack.co/transaction/verify/{$reference}");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer {$paystackSecretKey}"
]);

$response = curl_exec($ch);
$result = json_decode($response, true);
curl_close($ch);

if ($result['status'] && $result['data']['status'] === 'success') {
    // Payment successful
    $metadata = $result['data']['metadata'];
    
    header("Location: ../dashboard.php?status=success&plan=" . ($metadata['plan'] ?? 'unknown'));
} else {
    // Payment failed
    header("Location: ../plan.php?status=failed&message=Payment verification failed");
}
exit;
?>