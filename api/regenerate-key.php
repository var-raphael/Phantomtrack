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
        // Get user email before regenerating
        $user = fetchOne("SELECT email FROM users WHERE user_id = ?", [$user_id]);
        
        if (!$user || empty($user['email'])) {
            echo '<div style="color: #ef4444; padding: 8px; background: rgba(239, 68, 68, 0.1); border-radius: 4px; margin-top: 8px;">User email not found</div>';
            exit;
        }
        
        // Regenerate user secret key (in users table)
        $new_secret_key = 'sk_' . randomStr(20);
        
        execQuery(
            "UPDATE users SET secret_key = ? WHERE user_id = ?",
            [$new_secret_key, $user_id]
        );
        
        // Generate dashboard URL
        $base_url = rtrim(env('PHANTOMTRACK_URL'), '/');
        $dashboard_url = $base_url . '/dashboard?secretkey=' . urlencode($new_secret_key);
        
        // Prepare email HTML
        $emailHtml = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .container {
            background: #ffffff;
            border-radius: 12px;
            padding: 40px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .header h1 {
            color: #6366f1;
            margin: 0;
            font-size: 28px;
        }
        .alert-box {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .alert-box strong {
            color: #92400e;
        }
        .content {
            margin: 20px 0;
        }
        .secret-key-box {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 15px;
            margin: 20px 0;
            font-family: monospace;
            font-size: 14px;
            word-break: break-all;
        }
        .button {
            display: inline-block;
            background: #6366f1;
            color: white;
            text-decoration: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 500;
            margin: 20px 0;
        }
        .button:hover {
            background: #4f46e5;
        }
        .warning {
            background: #fee2e2;
            border-left: 4px solid #ef4444;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
            font-size: 14px;
            color: #6c757d;
            text-align: center;
        }
        .info-grid {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .info-row {
            display: flex;
            padding: 8px 0;
            border-bottom: 1px solid #e9ecef;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .info-label {
            font-weight: 600;
            width: 120px;
            color: #495057;
        }
        .info-value {
            color: #212529;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔑 Secret Key Regenerated</h1>
        </div>
        
        <div class="alert-box">
            <strong>⚠️ Security Alert:</strong> Your PhantomTrack secret key has been regenerated.
        </div>
        
        <div class="content">
            <p>Hi there,</p>
            <p>Your secret key has been successfully regenerated. This means your old secret key will no longer work.</p>
            
            <h3>Your New Secret Key:</h3>
            <div class="secret-key-box">
                ' . htmlspecialchars($new_secret_key) . '
            </div>
            
            <div class="warning">
                <strong>🔒 Important:</strong> Keep this secret key safe! You will need it to access your dashboard. Do not share it with anyone.
            </div>
            
            <p>Click the button below to access your dashboard with your new key:</p>
            
            <div style="text-align: center;">
                <a href="' . htmlspecialchars($dashboard_url) . '" class="button">Access Dashboard</a>
            </div>
            
            <p>Or copy and paste this link into your browser:</p>
            <div class="secret-key-box" style="font-size: 12px;">
                ' . htmlspecialchars($dashboard_url) . '
            </div>
            
            <h3>What this means:</h3>
            <ul>
                <li>Your old secret key is now invalid</li>
                <li>Use the new key above to access your dashboard</li>
                <li>Update any saved bookmarks or links</li>
                <li>Store this key securely (password manager recommended)</li>
            </ul>
            
            <div class="info-grid">
                <div class="info-row">
                    <div class="info-label">Regenerated:</div>
                    <div class="info-value">' . date('F d, Y \a\t H:i:s T') . '</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Account Email:</div>
                    <div class="info-value">' . htmlspecialchars($user['email']) . '</div>
                </div>
            </div>
            
            <p><strong>Didn\'t request this change?</strong> If you didn\'t regenerate your secret key, please contact our support team immediately.</p>
        </div>
        
        <div class="footer">
            <p>PhantomTrack - Analytics Made Simple</p>
            <p>This email was sent to ' . htmlspecialchars($user['email']) . '</p>
            <p>Need help? Contact us at phantomdev17@gmail.com</p>
        </div>
    </div>
</body>
</html>
';
        
        // Send email via Brevo
        $emailSent = sendBrevoEmail(
            $user['email'],
            $user['email'],
            '🔑 Your PhantomTrack Secret Key Has Been Regenerated',
            $emailHtml
        );
        
        if (!$emailSent) {
            error_log("Failed to send secret key regeneration email to: " . $user['email']);
        }
        
        // Get website tracking ID for the dashboard URL
        $website = fetchOne("SELECT track_id FROM website WHERE website_id = ? AND user_id = ?", [$website_id, $user_id]);
        $dashboard_redirect = "dashboard?secretkey=" . urlencode($new_secret_key);
        
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
                const dashboardUrl = "' . $dashboard_redirect . '";
                const baseUrl = "' . $base_url . '";
                const fileContent = `═══════════════════════════════════════════════════════════
  PHANTOMTRACK SECRET KEY - CONFIDENTIAL
═══════════════════════════════════════════════════════════

⚠️  IMPORTANT: Keep this file secure and private!
This key grants access to your PhantomTrack dashboard.

───────────────────────────────────────────────────────────
SECRET KEY
───────────────────────────────────────────────────────────
${secretKey}

───────────────────────────────────────────────────────────
DASHBOARD ACCESS
───────────────────────────────────────────────────────────
${baseUrl}/${dashboardUrl}

───────────────────────────────────────────────────────────
ACCOUNT EMAIL
───────────────────────────────────────────────────────────
' . $user['email'] . '

───────────────────────────────────────────────────────────
SECURITY NOTES
───────────────────────────────────────────────────────────
• Never share this key with anyone
• Store this file in a secure location
• If compromised, contact support immediately

───────────────────────────────────────────────────────────
SUPPORT
───────────────────────────────────────────────────────────
Email:         phantomdev17@gmail.com
Documentation: https://phantomtrack-docs.vercel.app/docs

═══════════════════════════════════════════════════════════
  © ' . date('Y') . ' PhantomTrack - All Rights Reserved
═══════════════════════════════════════════════════════════
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
                showNotification("Secret key regenerated! Email sent. Redirecting...", "success");
                
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
