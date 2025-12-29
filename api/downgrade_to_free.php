<?php
session_start();
require_once "../includes/functions.php";

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['website_id'])) {
    jsonResponse(false, "Unauthorized");
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, "Invalid request method");
}

$user_id = $_SESSION['user_id'];
$website_id = $_SESSION['website_id'];

$paystackSecretKey = env('PAYSTACK_SECRET_KEY');

// Get current subscription
$website = fetchOne(
    "SELECT paystack_sub_code, tier, subscription_status FROM website 
     WHERE website_id = ? AND user_id = ?",
    [$website_id, $user_id]
);

if (!$website) {
    jsonResponse(false, "Website not found");
}

// Check if already on free
if ($website['tier'] === 'free') {
    jsonResponse(false, "You are already on the free plan");
}

$subCode = $website['paystack_sub_code'];

// Prevent downgrade from lifetime
if (!empty($subCode) && strpos($subCode, 'lifetime_') === 0) {
    jsonResponse(false, "Cannot downgrade from lifetime plan. You have unlimited access!");
}

// Cancel subscription if exists
if (!empty($subCode)) {
    error_log("Downgrading to free - Cancelling subscription: {$subCode} for website_id: {$website_id}");
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.paystack.co/subscription/disable");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'code' => $subCode,
        'token' => ''
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer {$paystackSecretKey}",
        "Content-Type: application/json"
    ]);
    
    $response = curl_exec($ch);
    $result = json_decode($response, true);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    // Log result
    if ($result['status']) {
        error_log("Successfully cancelled subscription for downgrade - website_id: {$website_id}");
    } else {
        error_log("Failed to cancel subscription: " . json_encode($result));
        // Don't fail - continue with downgrade
    }
}

// Downgrade to free tier
$downgraded = execQuery(
    "UPDATE website 
     SET tier = 'free',
         plan_type = 'free',
         subscription_status = 'cancelled',
         subscription_ends = NULL,
         paystack_sub_code = ''
     WHERE website_id = ?",
    [$website_id]
);

if (!$downgraded) {
    jsonResponse(false, "Failed to update website tier");
}

// Update monthly usage to free tier limit (10,000)
$currentMonth = date('Y-m');
execQuery(
    "UPDATE monthly_usage 
     SET req_limit = 10000 
     WHERE website_id = ? AND month = ?",
    [$website_id, $currentMonth]
);

// Log downgrade action
error_log("User {$user_id} downgraded website_id {$website_id} to free plan");

jsonResponse(true, "Successfully downgraded to free plan. Your request limit is now 10,000/month.");
?>