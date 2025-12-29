<?php
session_start();
require_once "../includes/functions.php";

if (!isset($_SESSION['user_id']) || !isset($_SESSION['website_id'])) {
    echo '<div style="color: #EF4444;">Unauthorized</div>';
    exit;
}

$user_id = $_SESSION['user_id'];
$website_id = $_SESSION['website_id'];

// Get current website/subscription info
$website = fetchOne(
    "SELECT tier, plan_type, subscription_status, subscription_ends, paystack_sub_code 
     FROM website 
     WHERE website_id = ? AND user_id = ?",
    [$website_id, $user_id]
);

if (!$website) {
    echo '<div style="color: #EF4444;">Website not found</div>';
    exit;
}

// Get monthly usage WITH limit - pick the one with highest limit to handle duplicates
$currentMonth = date('Y-m');
$usage = fetchOne(
    "SELECT event_count, req_limit FROM monthly_usage 
     WHERE website_id = ? AND month = ?
     ORDER BY req_limit DESC, usage_id DESC
     LIMIT 1",
    [$website_id, $currentMonth]
);

$eventCount = $usage ? (int)$usage['event_count'] : 0;
$reqLimit = $usage ? (int)$usage['req_limit'] : 10000;

// Get actual plan type
$planType = $website['plan_type'] ?? 'free';
$displayPlan = ucfirst($planType);
$tier = $website['tier'];
$status = $website['subscription_status'] ?? 'active';
$ends = $website['subscription_ends'];

// Calculate usage percentage
$usagePercentage = $reqLimit > 0 ? min(100, ($eventCount / $reqLimit) * 100) : 0;

// Determine display values
$statusColor = $status === 'active' ? '#10B981' : '#EF4444';
$usageColor = $usagePercentage > 80 ? '#EF4444' : '#10B981';
$tierEmoji = $tier === 'paid' ? '💎' : '🆓';

// Format request limit display (show "Unlimited" for very high limits)
$limitDisplay = $reqLimit >= 999999999 ? 'Unlimited' : number_format($reqLimit);

// Build HTML response
$html = '<div style="background: var(--card-bg); padding: 15px; border-radius: 8px; margin: 20px 0; border: 2px solid var(--accent1);">';
$html .= "<strong>Current Plan:</strong> {$displayPlan} {$tierEmoji} | ";
$html .= '<strong>Status:</strong> <span style="color: ' . $statusColor . '">' . strtoupper($status) . '</span> | ';
$html .= "<strong>Usage:</strong> " . number_format($eventCount) . " / " . $limitDisplay . " requests ";

// Only show percentage if not unlimited
if ($reqLimit < 999999999) {
    $html .= '<span style="color: ' . $usageColor . '">(' . number_format($usagePercentage, 1) . '%)</span>';
}

if ($ends) {
    $html .= '<br><strong>Renews:</strong> ' . date('M d, Y', strtotime($ends));
}

$html .= '</div>';

// Store tier in a data attribute for JavaScript
$html .= '<script>userCurrentTier = "' . htmlspecialchars($tier, ENT_QUOTES) . '";</script>';

// Show/hide buttons based on tier
if ($tier === 'paid') {
    $html .= '<script>
        document.getElementById("freeBtn").style.display = "block";
        document.getElementById("currentPlanBtn").style.display = "none";
    </script>';
} else {
    $html .= '<script>
        document.getElementById("freeBtn").style.display = "none";
        document.getElementById("currentPlanBtn").style.display = "block";
    </script>';
}

echo $html;
?>
