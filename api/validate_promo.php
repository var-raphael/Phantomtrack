<?php
session_start();
require_once "../includes/functions.php";

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    jsonResponse(false, "Unauthorized");
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, "Invalid request method");
}

$promoCode = strtoupper(trim($_POST['promo_code'] ?? ''));

if (empty($promoCode)) {
    jsonResponse(false, "Promo code is required");
}


// Check if promo exists and is valid
$promo = fetchOne(
    "SELECT * FROM promo_codes 
     WHERE code = ? 
     AND is_active = 1 
     AND valid_from <= NOW() 
     AND valid_until >= NOW()
     AND (max_uses IS NULL OR used_count < max_uses)",
    [$promoCode]
);

if (!$promo) {
    jsonResponse(false, "Invalid or expired promo code");
}

// Check if user already used this promo
$userUsed = fetchOne(
    "SELECT * FROM promo_usage 
     WHERE user_id = ? AND promo_id = ?",
    [$_SESSION['user_id'], $promo['promo_id']]
);

if ($userUsed) {
    jsonResponse(false, "You've already used this promo code");
}

// Return promo details
jsonResponse(true, "Promo code applied successfully", [
    'code' => $promo['code'],
    'discount_type' => $promo['discount_type'],
    'discount_value' => (float)$promo['discount_value'],
    'applicable_plans' => json_decode($promo['applicable_plans'] ?? '[]')
]);
?>