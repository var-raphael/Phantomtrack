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
$key_type = clean($_POST['key_type'] ?? '');

try {
    if ($key_type === 'production') {
        // Regenerate production API key
        $new_key = 'sk_live_' . randomStr(32);
        
        execQuery(
            "UPDATE api_key SET production_key = ? WHERE website_id = ? AND user_id = ?",
            [$new_key, $website_id, $user_id]
        );
        
        // Return updated HTML for production key section
        echo '
        <div class="settings-item" id="prod-key-section">
            <div class="settings-item-content">
                <div class="settings-item-title">Production API Key</div>
                <div class="settings-item-desc">Live key for production environment</div>
                <span class="badge badge-success">Live Mode</span>
                <span class="badge badge-warning" style="margin-left: 8px;">Just Regenerated</span>
                <div class="api-key-display">
                    <span class="api-key-text" id="prodKey">' . $new_key . '</span>
                    <button class="copy-btn" onclick="copyToClipboard(\'prodKey\')">
                        <i class="fas fa-copy"></i> Copy
                    </button>
                </div>
            </div>
        </div>
        <script>
            showNotification("Production key regenerated successfully!", "success");
            setTimeout(() => {
                const badge = document.querySelector("#prod-key-section .badge-warning");
                if (badge) badge.remove();
            }, 5000);
        </script>
        ';
        
    } elseif ($key_type === 'user_secret') {
        // Regenerate user secret key (in users table)
        $new_secret_key = 'sk_' . randomStr(20);
        
        execQuery(
            "UPDATE users SET secret_key = ? WHERE user_id = ?",
            [$new_secret_key, $user_id]
        );
        
        // Get website tracking ID for the dashboard URL
        $website = fetchOne("SELECT track_id FROM website WHERE website_id = ? AND user_id = ?", [$website_id, $user_id]);
        $dashboard_url = "dashboard.php?secretkey=" . urlencode($new_secret_key);
        
        // Return updated HTML for user secret key section
        echo '
        <div class="settings-item" id="user-secret-key-section">
            <div class="settings-item-content">
                <div class="settings-item-title">User Secret Key</div>
                <div class="settings-item-desc">Your personal authentication key - keep this secure!</div>
                <span class="badge badge-danger">Don\'t Expose</span>
                <span class="badge badge-warning" style="margin-left: 8px;">Just Regenerated</span>
                <div class="api-key-display">
                    <span class="api-key-text" id="userSecretKey">' . $new_secret_key . '</span>
                    <button class="copy-btn" onclick="copyToClipboard(\'userSecretKey\')">
                        <i class="fas fa-copy"></i> Copy
                    </button>
                </div>
            </div>
        </div>
        <script>
            // Create and download the secret key file
            (function() {
                const secretKey = "' . $new_secret_key . '";
                const dashboardUrl = "' . $dashboard_url . '";
                const fileContent = `PhantomTrack Secret Key
========================

Secret Key: ${secretKey}

Dashboard URL: 
${window.location.origin}/${dashboardUrl}

⚠️ IMPORTANT: Keep this key secure and private!
This key grants access to your PhantomTrack dashboard.

Generated: ' . date('Y-m-d H:i:s') . '
User ID: ' . $user_id . '
Website ID: ' . $website_id . '
`;

                // Create blob and download
                const blob = new Blob([fileContent], { type: "text/plain" });
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement("a");
                a.href = url;
                a.download = "phantomtrack-secret-key-" + Date.now() + ".txt";
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                window.URL.revokeObjectURL(url);
                
                // Show notification
                showNotification("Secret key downloaded! Redirecting to dashboard...", "success");
                
                // Redirect after 2 seconds
                setTimeout(() => {
                    window.location.href = dashboardUrl;
                }, 2000);
            })();
            
            // Remove warning badge after redirect timeout
            setTimeout(() => {
                const badge = document.querySelector("#user-secret-key-section .badge-warning");
                if (badge) badge.remove();
            }, 5000);
        </script>
        ';
        
    } elseif ($key_type === 'tracking') {
        // Regenerate tracking ID (website track_id)
        $new_track_id = 'track_' . randomStr(20);
        
        execQuery(
            "UPDATE website SET track_id = ? WHERE website_id = ? AND user_id = ?",
            [$new_track_id, $website_id, $user_id]
        );
        
        // Also update monthly_usage table tracking_id for consistency
        execQuery(
            "UPDATE monthly_usage SET tracking_id = ? WHERE website_id = ?",
            [$new_track_id, $website_id]
        );
        
        // Return updated HTML for tracking ID section
        echo '
        <div class="settings-item" id="tracking-id-section">
            <div class="settings-item-content">
                <div class="settings-item-title">Tracking ID</div>
                <div class="settings-item-desc">Use this ID in your tracking script on your website</div>
                <span class="badge badge-info">Public</span>
                <span class="badge badge-warning" style="margin-left: 8px;">Just Regenerated</span>
                <div class="api-key-display">
                    <span class="api-key-text" id="trackingId">' . $new_track_id . '</span>
                    <button class="copy-btn" onclick="copyToClipboard(\'trackingId\')">
                        <i class="fas fa-copy"></i> Copy
                    </button>
                </div>
            </div>
        </div>
        <script>
            showNotification("Tracking ID regenerated! Update your website script.", "success");
            setTimeout(() => {
                const badge = document.querySelector("#tracking-id-section .badge-warning");
                if (badge) badge.remove();
            }, 5000);
        </script>
        ';
        
    } else {
        echo '<div style="color: #ef4444; padding: 8px; background: rgba(239, 68, 68, 0.1); border-radius: 4px; margin-top: 8px;">Invalid key type</div>';
    }
    
} catch (Exception $e) {
    error_log("Regenerate key error: " . $e->getMessage());
    echo '<div style="color: #ef4444; padding: 8px; background: rgba(239, 68, 68, 0.1); border-radius: 4px; margin-top: 8px;">Failed to regenerate key. Please try again.</div>';
}
?>