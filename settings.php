<?php
session_start();
require_once 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION["user_id"])) {
    header("Location: index");
    exit;
}

$user_id = $_SESSION["user_id"];
$website_id = $_SESSION["website_id"];

// Fetch website data
$website = fetchOne("SELECT * FROM website WHERE website_id = ? AND user_id = ?", [$website_id, $user_id]);
if (!$website) {
    die("Website not found");
}

// Fetch or create API keys
$api_keys = fetchOne("SELECT * FROM api_key WHERE website_id = ? AND user_id = ?", [$website_id, $user_id]);
if (!$api_keys) {
    // Create new API keys
    $test_key = 'sk_test_' . randomStr(32);
    $prod_key = 'sk_live_' . randomStr(32);
    quickInsert('api_key', [
        'user_id' => $user_id,
        'website_id' => $website_id,
        'test_key' => $test_key,
        'production_key' => $prod_key,
        'test_key_count' => 0,
        'production_key_count' => 0
    ]);
    $api_keys = fetchOne("SELECT * FROM api_key WHERE website_id = ? AND user_id = ?", [$website_id, $user_id]);
}

// Fetch monthly usage with limit
$current_month = date('Y-m');
$usage = fetchOne(
    "SELECT event_count, req_limit FROM monthly_usage 
     WHERE website_id = ? AND month = ?
     ORDER BY req_limit DESC, usage_id DESC
     LIMIT 1",
    [$website_id, $current_month]
);

$api_calls = $usage ? (int)$usage['event_count'] : 0;
$req_limit = $usage ? (int)$usage['req_limit'] : 10000;

// Define plan details
$planDetails = [
    'free' => ['name' => 'Free Plan', 'price' => '$0', 'limit' => 10000, 'billing' => 'N/A'],
    'pro' => ['name' => 'Pro Plan', 'price' => '$3', 'limit' => 30000, 'billing' => 'Monthly'],
    'premium' => ['name' => 'Premium Plan', 'price' => '$5', 'limit' => 60000, 'billing' => 'Monthly'],
    'enterprise' => ['name' => 'Enterprise Plan', 'price' => '$8', 'limit' => 100000, 'billing' => 'Monthly'],
    'lifetime' => ['name' => 'Lifetime Plan', 'price' => '$20', 'limit' => 999999999, 'billing' => 'One-time']
];

$plan_type = $website['plan_type'] ?? 'free';
$currentPlan = $planDetails[$plan_type] ?? $planDetails['free'];

// Build subscription data
$subscription = [
    'plan' => $currentPlan['name'],
    'status' => $website['subscription_status'] ?? ($website['tier'] === 'paid' ? 'Active' : 'Free'),
    'price' => $currentPlan['price'],
    'billing_cycle' => $currentPlan['billing'],
    'next_billing' => $website['subscription_ends'] ? date('M d', strtotime($website['subscription_ends'])) : 'N/A',
    'expires' => $website['subscription_ends'] ? date('F d, Y', strtotime($website['subscription_ends'])) : 'N/A',
    'is_lifetime' => $plan_type === 'lifetime',
    'has_subscription' => !empty($website['paystack_sub_code']) && strpos($website['paystack_sub_code'], 'lifetime_') !== 0
];

// Get user data from existing users table
$user_data = fetchOne("SELECT email, secret_key FROM users WHERE user_id = ?", [$user_id]);
$user_email = $user_data['email'] ?? "user@phantomtrack.com";
$user_secret_key = $user_data['secret_key'] ?? '';

// Format limit display
$limitDisplay = $req_limit >= 999999999 ? 'Unlimited' : number_format($req_limit);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Settings - Phantom Track</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/settings.css">
    <link rel="stylesheet" href="assets/font-awesome/icons/css/all.min.css">
</head>
<body data-theme="dark">

    <!-- Header -->
    <div class="header">
        <div class="header-content">
            <h1><i class="fas fa-cog"></i> Settings</h1>
            <button class="theme-toggle" onclick="toggleTheme()" aria-label="Toggle theme">
                <span id="themeIcon"></span>
            </button>
        </div>
    </div>

    <!-- Content -->
    <div class="content">
        <div class="settings-container">

            <!-- Current Plan Section -->
            <div class="settings-section">
                <div class="card-solid">
                    <h3><i class="fas fa-crown"></i> Current Plan</h3>
                    
                    <div class="settings-item">
                        <div class="settings-item-content">
                            <div class="settings-item-title">Documentation</div>
                            <div class="settings-item-desc">View API documentation and integration guides</div>
                        </div>
                        <button class="btn-secondary btn-sm" onclick="openDocs()">
                            <i class="fas fa-file-alt"></i> View Docs
                        </button>
                    </div>

                    <div class="settings-item" id="subscription-info">
                        <div class="settings-item-content">
                            <div class="settings-item-title"><?php echo $subscription['plan']; ?></div>
                            <?php if ($subscription['is_lifetime']): ?>
                                <div class="settings-item-desc">Lifetime access - No expiration</div>
                            <?php elseif ($website['tier'] === 'paid'): ?>
                                <div class="settings-item-desc">
                                    <?php if ($subscription['status'] === 'cancelled'): ?>
                                        Access until <?php echo $subscription['expires']; ?>
                                    <?php else: ?>
                                        Renews <?php echo $subscription['expires']; ?>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <div class="settings-item-desc">Limited to <?php echo number_format(10000); ?> requests/month</div>
                            <?php endif; ?>
                            
                            <div style="margin-top: 12px;">
                                <?php if ($subscription['is_lifetime']): ?>
                                    <span class="badge badge-success">
                                        <i class="fas fa-crown"></i> Lifetime
                                    </span>
                                <?php elseif ($subscription['status'] === 'cancelled'): ?>
                                    <span class="badge badge-warning">Cancelled</span>
                                    <span class="badge badge-info" style="margin-left: 8px;">
                                        Active until <?php echo $subscription['next_billing']; ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-<?php echo $website['tier'] === 'paid' ? 'success' : 'warning'; ?>">
                                        <?php echo ucfirst($subscription['status']); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if ($website['tier'] === 'free' || $subscription['status'] === 'cancelled'): ?>
                        <button class="btn-outline btn-sm" onclick="upgradePlan()">
                            <i class="fas fa-arrow-up"></i> <?php echo $website['tier'] === 'free' ? 'Upgrade' : 'Reactivate'; ?>
                        </button>
                        <?php endif; ?>
                    </div>

                    <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--border);">
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 16px;">
                            <div>
                                <div style="font-size: 12px; opacity: 0.7; color: var(--text); margin-bottom: 4px;">
                                    <?php echo $subscription['is_lifetime'] ? 'One-time Price' : 'Monthly Price'; ?>
                                </div>
                                <div style="font-size: 20px; font-weight: 600; color: var(--accent1);"><?php echo $subscription['price']; ?></div>
                            </div>
                            <div>
                                <div style="font-size: 12px; opacity: 0.7; color: var(--text); margin-bottom: 4px;">Billing Cycle</div>
                                <div style="font-size: 20px; font-weight: 600; color: var(--accent1);"><?php echo $subscription['billing_cycle']; ?></div>
                            </div>
                            <div>
                                <div style="font-size: 12px; opacity: 0.7; color: var(--text); margin-bottom: 4px;">
                                    <?php echo $subscription['is_lifetime'] ? 'Status' : 'Next Billing'; ?>
                                </div>
                                <div style="font-size: 20px; font-weight: 600; color: var(--accent1);">
                                    <?php echo $subscription['is_lifetime'] ? 'Active' : $subscription['next_billing']; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- API Usage Stats Section -->
            <div class="settings-section">
                <div class="card-solid" id="usage-stats">
                    <h3><i class="fas fa-chart-line"></i> Usage Statistics</h3>
                    
                    <div style="margin-bottom: 24px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                            <span style="font-size: 14px; color: var(--text); opacity: 0.8;">API Calls This Month</span>
                            <span style="font-size: 14px; font-weight: 600; color: var(--accent1);">
                                <?php echo number_format($api_calls); ?> / <?php echo $limitDisplay; ?>
                            </span>
                        </div>
                        <?php 
                        $percentage = $req_limit >= 999999999 ? 1 : min(($api_calls / $req_limit) * 100, 100);
                        ?>
                        <div style="width: 100%; height: 8px; background: rgba(100, 116, 139, 0.2); border-radius: 4px; overflow: hidden;">
                            <div style="width: <?php echo $percentage; ?>%; height: 100%; background: linear-gradient(135deg, var(--accent1), var(--accent2)); border-radius: 4px; transition: width 0.3s ease;"></div>
                        </div>
                        
                        <?php if ($req_limit >= 999999999): ?>
                        <div style="margin-top: 8px; font-size: 12px; color: var(--accent1); opacity: 0.8;">
                            <i class="fas fa-infinity"></i> Unlimited requests
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- API Keys Section -->
            <div class="settings-section">
                <div class="card-solid">
                    <h3><i class="fas fa-key"></i> API Keys</h3>
                    
                    <div class="settings-item">
                        <div class="settings-item-content">
                            <div class="settings-item-title">Test API Key</div>
                            <div class="settings-item-desc">Use this key for development and testing</div>
                            <span class="badge badge-warning">Test Mode</span>
                            <div class="api-key-display">
                                <span class="api-key-text" id="testKey"><?php echo $api_keys['test_key']; ?></span>
                                <button class="copy-btn" onclick="copyToClipboard('testKey')">
                                    <i class="fas fa-copy"></i> Copy
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="settings-item" id="prod-key-section">
                        <div class="settings-item-content">
                            <div class="settings-item-title">Production API Key</div>
                            <div class="settings-item-desc">Live key for production environment</div>
                            <span class="badge badge-success">Live Mode</span>
                            <div class="api-key-display">
                                <span class="api-key-text" id="prodKey"><?php echo $api_keys['production_key']; ?></span>
                                <button class="copy-btn" onclick="copyToClipboard('prodKey')">
                                    <i class="fas fa-copy"></i> Copy
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="settings-item">
                        <div class="settings-item-content">
                            <div class="settings-item-title">Regenerate Production Key</div>
                            <div class="settings-item-desc">Generate a new production key (this will invalidate the old one but your monthly usage will remain the same)</div>
                        </div>
                        <button class="btn-outline btn-sm" onclick="openModal('regenerateProdModal')">
                            <i class="fas fa-sync-alt"></i> Regenerate
                        </button>
                    </div>
                </div>
            </div>

            <!-- Secret Keys Section -->
            <div class="settings-section">
                <div class="card-solid">
                    <h3><i class="fas fa-key"></i> User Secret Key</h3>
                    
                    <div class="settings-item" id="user-secret-key-section">
                        <div class="settings-item-content">
                            <div class="settings-item-title">User Secret Key</div>
                            <div class="settings-item-desc">Your personal authentication key - keep this secure!</div>
                            <span class="badge badge-danger">Don't Expose</span>
                            <div class="api-key-display">
                                <span class="api-key-text" id="userSecretKey"><?php echo $user_secret_key; ?></span>
                                <button class="copy-btn" onclick="copyToClipboard('userSecretKey')">
                                    <i class="fas fa-copy"></i> Copy
                                </button>
                                <button class="copy-btn" onclick="downloadSecretKey('<?php echo $user_secret_key; ?>')" style="margin-left: 8px;">
                                    <i class="fas fa-download"></i> Download
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="settings-item">
                        <div class="settings-item-content">
                            <div class="settings-item-title">Regenerate User Secret Key</div>
                            <div class="settings-item-desc">Generate a new user secret key (this will require you to re-authenticate).</div>
                        </div>
                        <button class="btn-outline btn-sm" onclick="openModal('regenerateUserSecretModal')">
                            <i class="fas fa-sync-alt"></i> Regenerate
                        </button>
                    </div>
                </div>
            </div>

            <!-- Website Tracking ID Section -->
            <div class="settings-section">
                <div class="card-solid">
                    <h3><i class="fas fa-code"></i> Website Tracking ID</h3>
                    
                    <div class="settings-item" id="tracking-id-section">
                        <div class="settings-item-content">
                            <div class="settings-item-title">Tracking ID</div>
                            <div class="settings-item-desc">Use this ID in your tracking script on your website</div>
                            <span class="badge badge-info">Public</span>
                            <div class="api-key-display">
                                <span class="api-key-text" id="trackingId"><?php echo $website['track_id']; ?></span>
                                <button class="copy-btn" onclick="copyToClipboard('trackingId')">
                                    <i class="fas fa-copy"></i> Copy
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="settings-item">
                        <div class="settings-item-content">
                            <div class="settings-item-title">Regenerate Tracking ID</div>
                            <div class="settings-item-desc">Generate a new tracking ID (you'll need to update your website's tracking script).</div>
                        </div>
                        <button class="btn-outline btn-sm" onclick="openModal('regenerateTrackingModal')">
                            <i class="fas fa-sync-alt"></i> Regenerate
                        </button>
                    </div>
                </div>
            </div>

            <!-- Account Settings -->
            <div class="settings-section">
                <div class="card-solid">
                    <h3><i class="fas fa-user"></i> Account Settings</h3>
                    
                    <div class="settings-item">
                        <div class="settings-item-content">
                            <div class="settings-item-title">Email Address</div>
                            <div class="settings-item-desc">Update your account email address</div>
                            <form hx-post="api/update-email" hx-target="#email-response" hx-swap="innerHTML">
                                <div class="input-group">
                                    <input type="email" name="email" class="input-field" id="emailInput" 
                                           placeholder="your@email.com" value="<?php echo $user_email; ?>" required>
                                    <button type="submit" class="btn btn-sm">
                                        <i class="fas fa-save"></i> Save
                                    </button>
                                </div>
                            </form>
                            <div id="email-response" style="margin-top: 8px;"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Subscription Section -->
            <?php if ($website['tier'] === 'paid' && $subscription['has_subscription']): ?>
            <div class="settings-section">
                <div class="card-solid">
                    <h3><i class="fas fa-credit-card"></i> Subscription</h3>
                    
                    <div class="settings-item">
                        <div class="settings-item-content">
                            <div class="settings-item-title">Cancel Subscription</div>
                            <div class="settings-item-desc">Cancel your current subscription plan (does not apply to lifetime plans)</div>
                        </div>
                        <button class="btn-danger btn-sm" onclick="openModal('cancelModal')">
                            <i class="fas fa-times-circle"></i> Cancel Plan
                        </button>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Support & Info Section -->
            <div class="settings-section">
                <div class="card-solid">
                    <h3><i class="fas fa-info-circle"></i> Support & Information</h3>
                    
                    <div class="settings-item">
                        <div class="settings-item-content">
                            <div class="settings-item-title">Report a Bug</div>
                            <div class="settings-item-desc">Found an issue? Let us know</div>
                        </div>
                        <button class="btn-outline btn-sm" onclick="reportBug()">
                            <i class="fas fa-bug"></i> Report
                        </button>
                    </div>

                    <div class="settings-item">
                        <div class="settings-item-content">
                            <div class="settings-item-title">About</div>
                            <div class="settings-item-desc">Learn more about Phantom Track</div>
                        </div>
                        <button class="btn-secondary btn-sm" onclick="openModal('aboutModal')">
                            <i class="fas fa-book"></i> View
                        </button>
                    </div>

                    <div class="settings-item">
                        <div class="settings-item-content">
                            <div class="settings-item-title">Hire Me</div>
                            <div class="settings-item-desc">Interested in working together?</div>
                        </div>
                        <button class="btn btn-sm" onclick="hireMe()">
                            <i class="fas fa-briefcase"></i> Get in Touch
                        </button>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- Regenerate Production Key Modal -->
    <div id="regenerateProdModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-exclamation-triangle"></i> Regenerate Production Key</h3>
            </div>
            <p style="color: var(--text); opacity: 0.8; line-height: 1.6;">
                Are you sure you want to regenerate your production API key? This action cannot be undone and will invalidate your current key immediately.
            </p>
            <div id="regenerate-prod-response"></div>
            <div class="modal-footer">
                <button class="btn-secondary" onclick="closeModal('regenerateProdModal')">Cancel</button>
                <button class="btn" 
                        hx-post="api/regenerate-key" 
                        hx-vals='{"key_type": "production"}'
                        hx-target="#prod-key-section"
                        hx-swap="outerHTML"
                        onclick="closeModal('regenerateProdModal')">
                    <i class="fas fa-sync-alt"></i> Regenerate
                </button>
            </div>
        </div>
    </div>

    <!-- Regenerate User Secret Key Modal -->
    <div id="regenerateUserSecretModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-exclamation-triangle"></i> Regenerate User Secret Key</h3>
            </div>
            <p style="color: var(--text); opacity: 0.8; line-height: 1.6;">
                Are you sure you want to regenerate your user secret key? This action cannot be undone and you may need to re-authenticate your account.
            </p>
            <div style="padding: 12px; background: rgba(59, 130, 246, 0.1); border-left: 3px solid #3b82f6; border-radius: 4px; margin: 16px 0;">
                <div style="display: flex; align-items: start; gap: 8px;">
                    <i class="fas fa-info-circle" style="color: #3b82f6; margin-top: 2px;"></i>
                    <div style="color: var(--text); font-size: 13px; line-height: 1.5;">
                        <strong>After regeneration:</strong><br>
                        • A text file with your new key will automatically download<br>
                        • You'll be redirected to the dashboard with your new key<br>
                        • The old key will be immediately invalidated
                    </div>
                </div>
            </div>
            <div id="regenerate-user-secret-response"></div>
            <div class="modal-footer">
                <button class="btn-secondary" onclick="closeModal('regenerateUserSecretModal')">Cancel</button>
                <button class="btn" 
                        hx-post="api/regenerate-key" 
                        hx-vals='{"key_type": "user_secret"}'
                        hx-target="#user-secret-key-section"
                        hx-swap="outerHTML"
                        onclick="closeModal('regenerateUserSecretModal')">
                    <i class="fas fa-sync-alt"></i> Regenerate & Download
                </button>
            </div>
        </div>
    </div>

    <!-- Regenerate Tracking ID Modal -->
    <div id="regenerateTrackingModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-exclamation-triangle"></i> Regenerate Tracking ID</h3>
            </div>
            <p style="color: var(--text); opacity: 0.8; line-height: 1.6;">
                Are you sure you want to regenerate your tracking ID? This action cannot be undone and will invalidate your current tracking script. You'll need to update the script on your website.
            </p>
            <div id="regenerate-tracking-response"></div>
            <div class="modal-footer">
                <button class="btn-secondary" onclick="closeModal('regenerateTrackingModal')">Cancel</button>
                <button class="btn" 
                        hx-post="api/regenerate-key" 
                        hx-vals='{"key_type": "tracking"}'
                        hx-target="#tracking-id-section"
                        hx-swap="outerHTML"
                        onclick="closeModal('regenerateTrackingModal')">
                    <i class="fas fa-sync-alt"></i> Regenerate
                </button>
            </div>
        </div>
    </div>

    <!-- Cancel Subscription Modal -->
    <div id="cancelModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-times-circle"></i> Cancel Subscription</h3>
            </div>
            <p style="color: var(--text); opacity: 0.8; line-height: 1.6;">
                We're sorry to see you go! Your subscription will remain active until the end of your billing period. Are you sure you want to cancel?
            </p>
            <div id="cancel-response"></div>
            <div class="modal-footer">
                <button class="btn-secondary" onclick="closeModal('cancelModal')">Keep Subscription</button>
                <button class="btn-danger" 
                        hx-post="api/cancel-subscription"
                        hx-target="#subscription-info"
                        hx-swap="outerHTML"
                        onclick="closeModal('cancelModal')">
                    <i class="fas fa-times"></i> Cancel Subscription
                </button>
            </div>
        </div>
    </div>

    <!-- About Modal -->
    <div id="aboutModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-info-circle"></i> About Phantom Track</h3>
            </div>
            <div style="color: var(--text); opacity: 0.8; line-height: 1.8;">
                <p style="margin-bottom: 16px;">
                    <strong>Phantom Track</strong> is a powerful tracking and analytics platform designed to help you monitor and optimize your digital presence.
                </p>
                <p style="margin-bottom: 16px;">
                    Version: 1.0.0<br>
                    Last Updated: December 2025
                </p>
                <p>
                    For support, contact us at: <strong style="color: var(--accent1);">support@phantomtrack.com</strong>
                </p>
            </div>
            <div class="modal-footer">
                <button class="btn" onclick="closeModal('aboutModal')">Close</button>
            </div>
        </div>
    </div>

    <script src="assets/js/htmx.min.js"></script>
    <script src="assets/js/toogle.js"></script>
    <script>
        function copyToClipboard(elementId) {
            const text = document.getElementById(elementId).textContent;
            navigator.clipboard.writeText(text).then(() => {
                showNotification('Your key has been copied to clipboard!', 'success');
            });
        }

        function downloadSecretKey(secretKey) {
            window.open('api/download-secret-key?key=' + encodeURIComponent(secretKey), '_blank');
            showNotification('Secret key file downloaded!', 'success');
        }

        function openModal(modalId) {
            document.getElementById(modalId).classList.add('active');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }

        function reportBug() {
            window.open('mailto:support@phantomtrack.com?subject=Bug Report', '_blank');
        }

        function hireMe() {
            window.open('mailto:hire@phantomtrack.com?subject=Hire Inquiry', '_blank');
        }

        function upgradePlan() {
            showNotification('Redirecting to upgrade page...', 'info');
            setTimeout(() => {
                window.location.href = 'plan';
            }, 1000);
        }

        function openDocs() {
            window.open('https://docs.phantomtrack.com', '_blank');
        }

        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: ${type === 'success' ? 'var(--accent1)' : type === 'error' ? '#ef4444' : 'var(--accent2)'};
                color: white;
                padding: 16px 24px;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.2);
                z-index: 10000;
                animation: slideIn 0.3s ease;
            `;
            notification.textContent = message;
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        }

        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('active');
            }
        }

        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideIn {
                from { transform: translateX(400px); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            @keyframes slideOut {
                from { transform: translateX(0); opacity: 1; }
                to { transform: translateX(400px); opacity: 0; }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>
