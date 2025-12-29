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
$action = $_POST['action'] ?? '';

$paystackSecretKey = env('PAYSTACK_SECRET_KEY');

// Get website subscription info
$website = fetchOne(
    "SELECT paystack_sub_code, subscription_status, tier 
     FROM website 
     WHERE website_id = ? AND user_id = ?",
    [$website_id, $user_id]
);

if (!$website) {
    jsonResponse(false, "Website not found");
}

$subscriptionCode = $website['paystack_sub_code'];

if (empty($subscriptionCode) || strpos($subscriptionCode, 'lifetime_') === 0) {
    jsonResponse(false, "No active subscription to manage");
}

switch ($action) {
    case 'cancel':
        // Cancel subscription
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.paystack.co/subscription/disable");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'code' => $subscriptionCode,
            'token' => '' // Email token if required
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer {$paystackSecretKey}",
            "Content-Type: application/json"
        ]);
        
        $response = curl_exec($ch);
        $result = json_decode($response, true);
        curl_close($ch);
        
        if ($result['status']) {
            execQuery(
                "UPDATE website 
                 SET subscription_status = 'cancelled'
                 WHERE website_id = ?",
                [$website_id]
            );
            
            jsonResponse(true, "Subscription cancelled successfully");
        } else {
            jsonResponse(false, "Failed to cancel subscription: " . ($result['message'] ?? 'Unknown error'));
        }
        break;
        
    case 'reactivate':
        // Enable subscription
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.paystack.co/subscription/enable");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'code' => $subscriptionCode,
            'token' => ''
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer {$paystackSecretKey}",
            "Content-Type: application/json"
        ]);
        
        $response = curl_exec($ch);
        $result = json_decode($response, true);
        curl_close($ch);
        
        if ($result['status']) {
            execQuery(
                "UPDATE website 
                 SET subscription_status = 'active'
                 WHERE website_id = ?",
                [$website_id]
            );
            
            jsonResponse(true, "Subscription reactivated successfully");
        } else {
            jsonResponse(false, "Failed to reactivate subscription");
        }
        break;
        
    default:
        jsonResponse(false, "Invalid action");
}
?>