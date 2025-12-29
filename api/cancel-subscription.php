<?php
session_start();
require_once '../includes/functions.php';

header('Content-Type: text/html');

if (!isset($_SESSION["user_id"]) || !isset($_SESSION["website_id"])) {
    echo '<div style="color: #ef4444; padding: 8px; background: rgba(239, 68, 68, 0.1); border-radius: 4px; margin-top: 8px;">Unauthorized access</div>';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo '<div style="color: #ef4444; padding: 8px; background: rgba(239, 68, 68, 0.1); border-radius: 4px; margin-top: 8px;">Invalid request method</div>';
    exit;
}

$user_id = $_SESSION["user_id"];
$website_id = $_SESSION["website_id"];

$paystackSecretKey = env('PAYSTACK_SECRET_KEY');

try {
    // Get current website data
    $website = fetchOne("SELECT * FROM website WHERE website_id = ? AND user_id = ?", [$website_id, $user_id]);
    
    if (!$website) {
        echo '<div style="color: #ef4444; padding: 8px; background: rgba(239, 68, 68, 0.1); border-radius: 4px; margin-top: 8px;">Website not found</div>';
        exit;
    }
    
    if ($website['tier'] !== 'paid') {
        echo '<div style="color: #f59e0b; padding: 8px; background: rgba(245, 158, 11, 0.1); border-radius: 4px; margin-top: 8px;">You are not on a paid plan</div>';
        exit;
    }
    
    $subscriptionCode = $website['paystack_sub_code'];
    
    // Check if it's a lifetime plan
    if (strpos($subscriptionCode, 'lifetime_') === 0) {
        echo '<div style="color: #ef4444; padding: 8px; background: rgba(239, 68, 68, 0.1); border-radius: 4px; margin-top: 8px;">Lifetime plans cannot be cancelled</div>';
        exit;
    }
    
    if (empty($subscriptionCode)) {
        echo '<div style="color: #ef4444; padding: 8px; background: rgba(239, 68, 68, 0.1); border-radius: 4px; margin-top: 8px;">No active subscription found</div>';
        exit;
    }
    
    // Call Paystack API to cancel subscription
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.paystack.co/subscription/disable");
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
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200 && $result['status']) {
        // Update database - mark as cancelled but keep tier until expiry
        execQuery(
            "UPDATE website 
             SET subscription_status = 'cancelled'
             WHERE website_id = ? AND user_id = ?",
            [$website_id, $user_id]
        );
        
        $expires_date = $website['subscription_ends'] ?? date('Y-m-d', strtotime('+1 month'));
        $plan_name = ucfirst($website['plan_type'] ?? 'Pro') . ' Plan';
        
        // Return updated subscription info HTML
        echo '
        <div class="settings-item" id="subscription-info">
            <div class="settings-item-content">
                <div class="settings-item-title">' . htmlspecialchars($plan_name) . '</div>
                <div class="settings-item-desc">Access until ' . date('F d, Y', strtotime($expires_date)) . '</div>
                <div style="margin-top: 12px;">
                    <span class="badge badge-warning">Cancelled</span>
                    <span class="badge badge-info" style="margin-left: 8px;">Active until ' . date('M d', strtotime($expires_date)) . '</span>
                </div>
            </div>
            <button class="btn-outline btn-sm" onclick="upgradePlan()">
                <i class="fas fa-arrow-up"></i> Reactivate
            </button>
        </div>
        <script>
            showNotification("Subscription cancelled. Access continues until ' . date('M d, Y', strtotime($expires_date)) . '", "success");
        </script>
        ';
        
        error_log("Subscription cancelled successfully for website_id: {$website_id}");
    } else {
        error_log("Paystack cancellation failed: " . json_encode($result));
        echo '<div style="color: #ef4444; padding: 8px; background: rgba(239, 68, 68, 0.1); border-radius: 4px; margin-top: 8px;">Failed to cancel subscription: ' . htmlspecialchars($result['message'] ?? 'Unknown error') . '</div>';
    }
    
} catch (Exception $e) {
    error_log("Cancel subscription error: " . $e->getMessage());
    echo '<div style="color: #ef4444; padding: 8px; background: rgba(239, 68, 68, 0.1); border-radius: 4px; margin-top: 8px;">Failed to cancel subscription. Please contact support.</div>';
}
?>