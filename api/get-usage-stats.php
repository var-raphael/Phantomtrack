<?php
session_start();
require_once '../includes/functions.php';

header('Content-Type: text/html');

if (!isset($_SESSION["user_id"]) || !isset($_SESSION["website_id"])) {
    echo '<div style="color: #ef4444;">Unauthorized</div>';
    exit;
}

$user_id = $_SESSION["user_id"];
$website_id = $_SESSION["website_id"];

try {
    // Get website tier
    $website = fetchOne("SELECT tier FROM website WHERE website_id = ? AND user_id = ?", [$website_id, $user_id]);
    
    if (!$website) {
        echo '<div style="color: #ef4444;">Website not found</div>';
        exit;
    }
    
    // Fetch monthly usage
    $current_month = date('Y-m');
    $usage = fetchOne(
        "SELECT SUM(event_count) as total FROM monthly_usage WHERE website_id = ? AND month = ?", 
        [$website_id, $current_month]
    );
    
    $api_calls = $usage['total'] ?? 0;
    $limit = $website['tier'] === 'paid' ? 100000 : 10000;
    $percentage = min(($api_calls / $limit) * 100, 100);
    
    // Determine progress bar color based on usage
    $bar_color = 'linear-gradient(135deg, var(--accent1), var(--accent2))';
    if ($percentage > 90) {
        $bar_color = 'linear-gradient(135deg, #ef4444, #dc2626)';
    } elseif ($percentage > 75) {
        $bar_color = 'linear-gradient(135deg, #f59e0b, #d97706)';
    }
    
    ?>
    <div class="card-solid" id="usage-stats">
        <h3><i class="fas fa-chart-line"></i> API Usage Statistics</h3>
        
        <div style="margin-bottom: 24px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                <span style="font-size: 14px; color: var(--text); opacity: 0.8;">API Calls This Month</span>
                <span style="font-size: 14px; font-weight: 600; color: var(--accent1);">
                    <?php echo number_format($api_calls); ?> / <?php echo number_format($limit); ?>
                </span>
            </div>
            <div style="width: 100%; height: 8px; background: rgba(100, 116, 139, 0.2); border-radius: 4px; overflow: hidden;">
                <div style="width: <?php echo $percentage; ?>%; height: 100%; background: <?php echo $bar_color; ?>; border-radius: 4px; transition: width 0.3s ease;"></div>
            </div>
            
            <?php if ($percentage > 90): ?>
            <div style="margin-top: 12px; padding: 12px; background: rgba(239, 68, 68, 0.1); border-left: 3px solid #ef4444; border-radius: 4px;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-exclamation-triangle" style="color: #ef4444;"></i>
                    <span style="color: #ef4444; font-size: 13px; font-weight: 500;">
                        You've used <?php echo number_format($percentage, 1); ?>% of your monthly limit
                    </span>
                </div>
                <?php if ($website['tier'] === 'free'): ?>
                <button class="btn btn-sm" onclick="upgradePlan()" style="margin-top: 8px;">
                    <i class="fas fa-arrow-up"></i> Upgrade to Pro
                </button>
                <?php endif; ?>
            </div>
            <?php elseif ($percentage > 75): ?>
            <div style="margin-top: 12px; padding: 12px; background: rgba(245, 158, 11, 0.1); border-left: 3px solid #f59e0b; border-radius: 4px;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-info-circle" style="color: #f59e0b;"></i>
                    <span style="color: #f59e0b; font-size: 13px;">
                        You've used <?php echo number_format($percentage, 1); ?>% of your monthly limit
                    </span>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Additional Stats -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 16px; padding-top: 16px; border-top: 1px solid var(--border);">
            <div>
                <div style="font-size: 11px; opacity: 0.6; color: var(--text); margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.5px;">This Month</div>
                <div style="font-size: 18px; font-weight: 600; color: var(--accent1);"><?php echo number_format($api_calls); ?></div>
            </div>
            <div>
                <div style="font-size: 11px; opacity: 0.6; color: var(--text); margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.5px;">Remaining</div>
                <div style="font-size: 18px; font-weight: 600; color: var(--accent2);"><?php echo number_format($limit - $api_calls); ?></div>
            </div>
            <div>
                <div style="font-size: 11px; opacity: 0.6; color: var(--text); margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.5px;">Plan Limit</div>
                <div style="font-size: 18px; font-weight: 600; color: var(--text); opacity: 0.7;"><?php echo number_format($limit); ?></div>
            </div>
        </div>
    </div>
    <?php
    
} catch (Exception $e) {
    error_log("Get usage stats error: " . $e->getMessage());
    echo '<div class="card-solid" style="color: #ef4444;">Failed to load usage statistics</div>';
}
?>