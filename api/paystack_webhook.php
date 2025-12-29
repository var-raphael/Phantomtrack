<?php
require_once "../includes/functions.php";

// Paystack webhook handler
$paystackSecretKey = env('PAYSTACK_SECRET_KEY');

// Retrieve the request's body
$input = @file_get_contents("php://input");
$event = json_decode($input, true);

// Verify signature
$signature = $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? '';
$computedSignature = hash_hmac('sha512', $input, $paystackSecretKey);

if ($signature !== $computedSignature) {
    error_log("Paystack webhook: Invalid signature");
    http_response_code(400);
    exit;
}

// Log webhook for debugging
error_log("Paystack Webhook: " . json_encode($event));

// Handle different event types
$eventType = $event['event'] ?? '';

switch ($eventType) {
    case 'charge.success':
        handleChargeSuccess($event['data']);
        break;
        
    case 'subscription.create':
        handleSubscriptionCreate($event['data']);
        break;
        
    case 'subscription.disable':
        handleSubscriptionDisable($event['data']);
        break;
        
    case 'subscription.not_renew':
        handleSubscriptionNotRenew($event['data']);
        break;
        
    default:
        error_log("Unhandled webhook event: " . $eventType);
}

http_response_code(200);
exit;

function handleChargeSuccess($data) {
    $reference = $data['reference'];
    $status = $data['status'];
    $metadata = $data['metadata'];
    
    if ($status !== 'success') {
        return;
    }
    
    $userId = $metadata['user_id'] ?? null;
    $websiteId = $metadata['website_id'] ?? null;
    $plan = $metadata['plan'] ?? null;
    $planType = $metadata['plan_type'] ?? 'subscription';
    $requestsLimit = $metadata['requests_limit'] ?? 10000;
    $promoId = $metadata['promo_id'] ?? null;
    
    if (!$userId || !$websiteId || !$plan) {
        error_log("Missing metadata in charge.success");
        return; 
    }
    
    // Ensure lifetime plan gets unlimited requests
    if ($plan === 'lifetime' || $planType === 'one-time') {
        $requestsLimit = 300000; // Force unlimited for lifetime
    }
    
    error_log("Processing payment - Plan: {$plan}, Type: {$planType}, Limit: {$requestsLimit}");
    
    // Update transaction status
    execQuery(
        "UPDATE payment_transactions 
         SET status = 'success', updated_at = NOW() 
         WHERE reference = ?",
        [$reference]
    );
    
    // Update website subscription
    if ($planType === 'one-time' || $plan === 'lifetime') {
        // Lifetime plan
        execQuery(
            "UPDATE website 
             SET tier = 'paid',
                 plan_type = 'lifetime',
                 subscription_status = 'active',
                 subscription_ends = NULL,
                 paystack_sub_code = ?
             WHERE website_id = ?",
            ['lifetime_' . $reference, $websiteId]
        );
    } else {
        // Monthly subscription
        $subscriptionCode = $data['subscription']['subscription_code'] ?? '';
        $nextPaymentDate = $data['subscription']['next_payment_date'] ?? date('Y-m-d', strtotime('+1 month'));
        
        execQuery(
            "UPDATE website 
             SET tier = 'paid',
                 plan_type = ?,
                 subscription_status = 'active',
                 subscription_ends = ?,
                 paystack_sub_code = ?
             WHERE website_id = ?",
            [$plan, $nextPaymentDate, $subscriptionCode, $websiteId]
        );
    }
    
    // Update monthly usage limit - FIXED VERSION
    $currentMonth = date('Y-m');
    $trackInfo = fetchOne("SELECT track_id FROM website WHERE website_id = ?", [$websiteId]);
    
    if ($trackInfo) {
        $trackingId = $trackInfo['track_id'];
        
        // Check if entry exists
        $existingUsage = fetchOne(
            "SELECT usage_id, event_count FROM monthly_usage 
             WHERE website_id = ? AND month = ?
             ORDER BY usage_id DESC LIMIT 1",
            [$websiteId, $currentMonth]
        );
        
        if ($existingUsage) {
            // UPDATE existing entry, preserve event_count
            execQuery(
                "UPDATE monthly_usage 
                 SET req_limit = ?, tracking_id = ?
                 WHERE usage_id = ?",
                [$requestsLimit, $trackingId, $existingUsage['usage_id']]
            );
            
            error_log("Updated monthly_usage: usage_id={$existingUsage['usage_id']}, req_limit={$requestsLimit}");
        } else {
            // INSERT new entry
            quickInsert('monthly_usage', [
                'website_id' => $websiteId,
                'tracking_id' => $trackingId,
                'month' => $currentMonth,
                'event_count' => 0,
                'req_limit' => $requestsLimit
            ]);
            
            error_log("Inserted monthly_usage: website_id={$websiteId}, req_limit={$requestsLimit}");
        }
        
        // Clean up any duplicate entries for this month (keep only the one we just updated/created)
        execQuery(
            "DELETE FROM monthly_usage 
             WHERE website_id = ? 
             AND month = ? 
             AND usage_id NOT IN (
                 SELECT * FROM (
                     SELECT MAX(usage_id) FROM monthly_usage 
                     WHERE website_id = ? AND month = ?
                 ) as temp
             )",
            [$websiteId, $currentMonth, $websiteId, $currentMonth]
        );
    }
    
    // Update promo code usage if applicable
    if ($promoId) {
        execQuery(
            "UPDATE promo_codes 
             SET used_count = used_count + 1 
             WHERE promo_id = ?",
            [$promoId]
        );
        
        // Record user's promo usage
        quickInsert('promo_usage', [
            'user_id' => $userId,
            'promo_id' => $promoId,
            'used_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    // Create payment record
    quickInsert('payments', [
        'user_id' => $userId,
        'website_id' => $websiteId,
        'plan' => $plan,
        'amount' => $data['amount'] / 100,
        'currency' => $data['currency'],
        'reference' => $reference,
        'status' => 'completed',
        'payment_date' => date('Y-m-d H:i:s')
    ]);
    
    error_log("Payment successful for website_id: {$websiteId}, plan: {$plan}, limit: {$requestsLimit}");
}

function handleSubscriptionCreate($data) {
    $subscriptionCode = $data['subscription_code'];
    $customerCode = $data['customer']['customer_code'];
    $status = $data['status'];
    
    error_log("Subscription created: {$subscriptionCode}, status: {$status}");
}

// REPLACE these two functions in your webhook.php

function handleSubscriptionDisable($data) {
    $subscriptionCode = $data['subscription_code'];
    
    // Get website_id before updating
    $website = fetchOne(
        "SELECT website_id FROM website WHERE paystack_sub_code = ?",
        [$subscriptionCode]
    );
    
    if (!$website) {
        error_log("Subscription disable failed: website not found for code {$subscriptionCode}");
        return;
    }
    
    $websiteId = $website['website_id'];
    
    // Downgrade to free plan
    execQuery(
        "UPDATE website 
         SET subscription_status = 'cancelled',
             plan_type = 'free',
             tier = 'free'
         WHERE paystack_sub_code = ?",
        [$subscriptionCode]
    );
    
    // Update monthly usage to free tier limit (10k)
    $currentMonth = date('Y-m');
    execQuery(
        "UPDATE monthly_usage 
         SET req_limit = 10000
         WHERE website_id = ? AND month = ?",
        [$websiteId, $currentMonth]
    );
    
    error_log("Subscription disabled and downgraded to FREE: website_id={$websiteId}, code={$subscriptionCode}");
}

function handleSubscriptionNotRenew($data) {
    $subscriptionCode = $data['subscription_code'];
    
    // Get website_id before updating
    $website = fetchOne(
        "SELECT website_id FROM website WHERE paystack_sub_code = ?",
        [$subscriptionCode]
    );
    
    if (!$website) {
        error_log("Subscription not_renew failed: website not found for code {$subscriptionCode}");
        return;
    }
    
    $websiteId = $website['website_id'];
    
    // Mark subscription as cancelled and downgrade
    execQuery(
        "UPDATE website 
         SET subscription_status = 'cancelled',
             plan_type = 'free',
             tier = 'free'
         WHERE paystack_sub_code = ?",
        [$subscriptionCode]
    );
    
    // Update monthly usage to free tier limit (10k)
    $currentMonth = date('Y-m');
    execQuery(
        "UPDATE monthly_usage 
         SET req_limit = 10000
         WHERE website_id = ? AND month = ?",
        [$websiteId, $currentMonth]
    );
    
    error_log("Subscription not renewing, downgraded to FREE: website_id={$websiteId}, code={$subscriptionCode}");
}
?>