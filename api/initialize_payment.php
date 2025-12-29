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
$plan = clean($_POST['plan'] ?? '');
$promoCode = strtoupper(trim($_POST['promo_code'] ?? ''));

// Get user info
$user = fetchOne("SELECT email FROM users WHERE user_id = ?", [$user_id]);
if (!$user) {
    jsonResponse(false, "User not found");
}

// Define plan prices in USD
$planPrices = [
    'pro' => ['usd' => 3, 'type' => 'subscription', 'requests' => 30000],
    'premium' => ['usd' => 5, 'type' => 'subscription', 'requests' => 60000],
    'enterprise' => ['usd' => 8, 'type' => 'subscription', 'requests' => 100000],
    'lifetime' => ['usd' => 20, 'type' => 'one-time', 'requests' => 300000]
];

if (!isset($planPrices[$plan])) {
    jsonResponse(false, "Invalid plan selected");
}

$planInfo = $planPrices[$plan];
$priceUSD = $planInfo['usd'];
$discount = 0;
$promoDetails = null;

// Apply promo code if provided
if (!empty($promoCode)) {
    $promo = fetchOne(
        "SELECT * FROM promo_codes 
         WHERE code = ? 
         AND is_active = 1 
         AND valid_from <= NOW() 
         AND valid_until >= NOW()
         AND (max_uses IS NULL OR used_count < max_uses)",
        [$promoCode]
    );
    
    if ($promo) {
        $applicablePlans = json_decode($promo['applicable_plans'] ?? '[]', true) ?: [];
        
        if (empty($applicablePlans) || in_array($plan, $applicablePlans)) {
            if ($promo['discount_type'] === 'percentage') {
                $discount = ($priceUSD * $promo['discount_value']) / 100;
            } else {
                $discount = min($priceUSD, $promo['discount_value']);
            }
            $promoDetails = [
                'id' => $promo['promo_id'],
                'code' => $promo['code'],
                'discount' => $discount
            ];
        }
    }
}

$finalPriceUSD = max(0, $priceUSD - $discount);

// USD to NGN conversion (you should use a live rate API or update this regularly)
$usdToNgn = 1650; // Update this rate regularly or fetch from API
$priceNGN = (int)($finalPriceUSD * $usdToNgn * 100); // Paystack uses kobo (NGN * 100)

// Paystack API credentials
$paystackSecretKey = env('PAYSTACK_SECRET_KEY');

$callbackUrl = env('PAYSTACK_CALLBACK_URL');

// Check if user has a Paystack customer code
$website = fetchOne(
    "SELECT paystack_customer_code FROM website WHERE website_id = ?",
    [$website_id]
);

$customerCode = $website['paystack_customer_code'] ?? '';

// If no customer code, create customer first
if (empty($customerCode)) {
    $customerData = [
        'email' => $user['email'],
        'first_name' => 'User',
        'last_name' => $user_id
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.paystack.co/customer");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($customerData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer {$paystackSecretKey}",
        "Content-Type: application/json"
    ]);
    
    $customerResponse = curl_exec($ch);
    $customerResult = json_decode($customerResponse, true);
    curl_close($ch);
    
    if ($customerResult['status']) {
        $customerCode = $customerResult['data']['customer_code'];
        
        // Update website with customer code
        execQuery(
            "UPDATE website SET paystack_customer_code = ? WHERE website_id = ?",
            [$customerCode, $website_id]
        );
    }
}

// CRITICAL: Cancel existing subscription before creating new one to prevent double charging
if ($planInfo['type'] === 'subscription') {
    $existingSub = fetchOne(
        "SELECT paystack_sub_code, tier FROM website 
         WHERE website_id = ? AND paystack_sub_code IS NOT NULL 
         AND paystack_sub_code != ''
         AND paystack_sub_code NOT LIKE 'lifetime_%'",
        [$website_id]
    );
    
    if ($existingSub && !empty($existingSub['paystack_sub_code'])) {
        // User is upgrading/downgrading - cancel old subscription first
        error_log("Cancelling old subscription: {$existingSub['paystack_sub_code']} for website_id: {$website_id}");
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.paystack.co/subscription/disable");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'code' => $existingSub['paystack_sub_code'],
            'token' => ''
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer {$paystackSecretKey}",
            "Content-Type: application/json"
        ]);
        
        $cancelResponse = curl_exec($ch);
        $cancelResult = json_decode($cancelResponse, true);
        curl_close($ch);
        
        // Log cancellation result
        if ($cancelResult['status']) {
            error_log("Successfully cancelled old subscription for website_id: {$website_id}");
        } else {
            error_log("Failed to cancel old subscription: " . json_encode($cancelResult));
            // Continue anyway - webhook will handle cleanup
        }
    }
}

// Check if user has lifetime - prevent downgrade
$checkLifetime = fetchOne(
    "SELECT paystack_sub_code FROM website 
     WHERE website_id = ? AND paystack_sub_code LIKE 'lifetime_%'",
    [$website_id]
);

if ($checkLifetime && $plan !== 'lifetime') {
    jsonResponse(false, "You already have lifetime access. No need to subscribe!");
}

// Prepare metadata
$metadata = [
    'user_id' => $user_id,
    'website_id' => $website_id,
    'plan' => $plan,
    'plan_type' => $planInfo['type'],
    'requests_limit' => $planInfo['requests'],
    'price_usd' => $finalPriceUSD,
    'original_price_usd' => $priceUSD,
    'discount' => $discount,
    'promo_code' => $promoCode ?: null,
    'promo_id' => $promoDetails['id'] ?? null
];

// Initialize transaction or subscription
if ($planInfo['type'] === 'subscription') {


$planCodes = [
    'pro' => 'PLN_awhy79fchvijdve',
    'premium' => 'PLN_q686x60nouviiph', 
    'enterprise' => 'PLN_4d9q7fzcaz489k5'
];

$planCode = $planCodes[$plan] ?? '';

if (empty($planCode)) {
    jsonResponse(false, "Invalid plan configuration");
}
    
    $paymentData = [
        'email' => $user['email'],
        'amount' => $priceNGN,
        'currency' => 'NGN',
        'plan' => $planCode, // This should match your Paystack plan code
        'metadata' => $metadata,
        'callback_url' => $callbackUrl,
        'channels' => ['card']
    ];
} else {
    // One-time payment for lifetime
    $paymentData = [
        'email' => $user['email'],
        'amount' => $priceNGN,
        'currency' => 'NGN',
        'metadata' => $metadata,
        'callback_url' => $callbackUrl,
        'channels' => ['card']
    ];
}

// Initialize transaction
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://api.paystack.co/transaction/initialize");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($paymentData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer {$paystackSecretKey}",
    "Content-Type: application/json"
]);

$response = curl_exec($ch);
$result = json_decode($response, true);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200 && $result['status']) {
    // Store transaction reference
    quickInsert('payment_transactions', [
        'user_id' => $user_id,
        'website_id' => $website_id,
        'reference' => $result['data']['reference'],
        'plan' => $plan,
        'amount_usd' => $finalPriceUSD,
        'amount_ngn' => $priceNGN / 100,
        'promo_code' => $promoCode ?: null,
        'status' => 'pending'
    ]);
    
    jsonResponse(true, "Payment initialized", [
        'authorization_url' => $result['data']['authorization_url'],
        'access_code' => $result['data']['access_code'],
        'reference' => $result['data']['reference']
    ]);
} else {
    error_log("Paystack Error: " . json_encode($result));
    jsonResponse(false, "Payment initialization failed: " . ($result['message'] ?? 'Unknown error'));
}
?>