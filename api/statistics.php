<?php
session_start();
include "../includes/functions.php";

$type = $_GET["type"] ?? "visitors";
$website_id = $_SESSION["website_id"] ?? null;

/* if(isset($_SESSION["website_id"])) {

$website_id = $_SESSION["website_id"];

}elseif(isset($_SESSION["track_id"])) {
$track_id = clean($_SESSION["track_id"]);
 
 $checkTrackId = fetchOne("SELECT website_id FROM website WHERE track_id = ?", [$track_id]);
 
 if($checkTrackId){
 	$website_id = $checkTrackId["website_id"];
 }
 
} */

function getCard($type, $website_id) {
    // Today's date range
    $todayStart = date('Y-m-d 00:00:00');
    $todayEnd = date('Y-m-d 23:59:59');
    
    // Yesterday's date range
    $yesterdayStart = date('Y-m-d 00:00:00', strtotime('-1 day'));
    $yesterdayEnd = date('Y-m-d 23:59:59', strtotime('-1 day'));
    
    switch($type) {
        case 'unique':
            // Today
            $today = fetchOne(
                "SELECT COUNT(DISTINCT ip_hash) as total FROM analytics 
                 WHERE website_id = ? 
                 AND created_at >= ? AND created_at <= ?",
                [$website_id, $todayStart, $todayEnd]
            );
            $todayTotal = $today['total'] ?? 0;
            
            // Yesterday
            $yesterday = fetchOne(
                "SELECT COUNT(DISTINCT ip_hash) as total FROM analytics 
                 WHERE website_id = ? 
                 AND created_at >= ? AND created_at <= ?",
                [$website_id, $yesterdayStart, $yesterdayEnd]
            );
            $yesterdayTotal = $yesterday['total'] ?? 0;
            
            $change = $yesterdayTotal > 0 ? round((($todayTotal - $yesterdayTotal) / $yesterdayTotal) * 100, 1) : 0;
            $changeText = $change >= 0 ? "+{$change}%" : "{$change}%";
            
            return [
                'label' => 'Unique Visitors Today',
                'value' => number_format($todayTotal),
                'subtext' => "{$changeText} from yesterday",
                'icon' => 'fa-users',
                'icon_class' => 'icon-purple',
                'modal_title' => 'Unique Visitors Details'
            ];
            
        case 'pageviews':
            // Today
            $today = fetchOne(
                "SELECT COUNT(*) as total FROM analytics 
                 WHERE website_id = ? 
                 AND created_at >= ? AND created_at <= ?",
                [$website_id, $todayStart, $todayEnd]
            );
            $todayTotal = $today['total'] ?? 0;
            
            // Yesterday
            $yesterday = fetchOne(
                "SELECT COUNT(*) as total FROM analytics 
                 WHERE website_id = ? 
                 AND created_at >= ? AND created_at <= ?",
                [$website_id, $yesterdayStart, $yesterdayEnd]
            );
            $yesterdayTotal = $yesterday['total'] ?? 0;
            
            $change = $yesterdayTotal > 0 ? round((($todayTotal - $yesterdayTotal) / $yesterdayTotal) * 100, 1) : 0;
            $changeText = $change >= 0 ? "+{$change}%" : "{$change}%";
            
            return [
                'label' => 'Page Views Today',
                'value' => number_format($todayTotal),
                'subtext' => "{$changeText} from yesterday",
                'icon' => 'fa-eye',
                'icon_class' => 'icon-cyan',
                'modal_title' => 'Page Views Details'
            ];
            
        case 'live':
            // Live visitors (from live_user_sessions within last 2 minutes)
            $result = fetchOne(
                "SELECT COUNT(DISTINCT session_id) as total 
                 FROM live_user_sessions 
                 WHERE website_id = ? AND last_ping >= DATE_SUB(NOW(), INTERVAL 2 MINUTE)",
                [$website_id]
            );
            $total = $result['total'] ?? 0;
            
            return [
                'label' => 'Live Visitors',
                'value' => number_format($total),
                'subtext' => 'Currently active',
                'icon' => 'fa-circle',
                'icon_class' => 'icon-green',
                'modal_title' => 'Live Visitors Details'
            ];
            
      

case 'usage':
    $currentMonth = date('Y-m');
    
    // ✅ Simple query - just get current month
    $result = fetchOne(
        "SELECT event_count, req_limit FROM monthly_usage 
         WHERE website_id = ? AND month = ?",
        [$website_id, $currentMonth]
    );
    
    if (!$result) {
        $websiteData = fetchOne(
            "SELECT plan_type FROM website WHERE website_id = ?",
            [$website_id]
        );
        $planType = $websiteData['plan_type'] ?? 'free';
        
        $planLimits = [
            'free' => 10000,
            'pro' => 30000,
            'premium' => 60000,
            'enterprise' => 100000,
            'lifetime' => 300000
        ];
        
        $total = 0;
        $limit = $planLimits[$planType] ?? 10000;
    } else {
        $total = (int)$result['event_count'];
        $limit = (int)$result['req_limit'];
    }
    
    $isUnlimited = ($limit >= 300000);
    
    if ($isUnlimited) {
        $percentUsed = round(($total / $limit) * 100, 1);
        $limitText = "Unlimited ♾️";
    } else {
        $percentUsed = $limit > 0 ? round(($total / $limit) * 100, 1) : 0;
        $limitText = number_format($limit) . " limit";
    }
    
    return [
        'label' => 'Monthly Usage',
        'value' => number_format($total),
        'subtext' => "{$percentUsed}% of {$limitText}",
        'icon' => 'fa-chart-pie',
        'icon_class' => $percentUsed >= 90 ? 'icon-red' : 'icon-orange',
        'modal_title' => 'Monthly Usage Details'
    ];
      
    
            
        case 'time-on-page':
            // Today
            $today = fetchOne(
                "SELECT AVG(CAST(timespent AS UNSIGNED)) as avg_time FROM analytics 
                 WHERE website_id = ? AND timespent != '' AND timespent != '0'
                 AND created_at >= ? AND created_at <= ?",
                [$website_id, $todayStart, $todayEnd]
            );
            $todayAvg = round($today['avg_time'] ?? 0);
            
            // Yesterday
            $yesterday = fetchOne(
                "SELECT AVG(CAST(timespent AS UNSIGNED)) as avg_time FROM analytics 
                 WHERE website_id = ? AND timespent != '' AND timespent != '0'
                 AND created_at >= ? AND created_at <= ?",
                [$website_id, $yesterdayStart, $yesterdayEnd]
            );
            $yesterdayAvg = round($yesterday['avg_time'] ?? 0);
            
            $minutes = floor($todayAvg / 60);
            $seconds = $todayAvg % 60;
            $formattedTime = "{$minutes}m {$seconds}s";
            
            $change = $yesterdayAvg > 0 ? round((($todayAvg - $yesterdayAvg) / $yesterdayAvg) * 100, 1) : 0;
            $changeText = $change >= 0 ? "+{$change}%" : "{$change}%";
            
            return [
                'label' => 'Avg Time On Page Today',
                'value' => $formattedTime,
                'subtext' => "{$changeText} from yesterday",
                'icon' => 'fa-stopwatch',
                'icon_class' => 'icon-purple',
                'modal_title' => 'Time On Page Details'
            ];
            
        case 'time-on-site':
            // Today - average session time (per user)
            $todaySessions = fetchAll(
                "SELECT SUM(CAST(timespent AS UNSIGNED)) as session_time
                FROM analytics 
                WHERE website_id = ? AND timespent != '' AND timespent != '0'
                AND created_at >= ? AND created_at <= ?
                GROUP BY ip_hash",
                [$website_id, $todayStart, $todayEnd]
            );
            
            $todayAvg = 0;
            if (!empty($todaySessions)) {
                $totalTime = array_sum(array_column($todaySessions, 'session_time'));
                $todayAvg = round($totalTime / count($todaySessions));
            }
            
            // Yesterday
            $yesterdaySessions = fetchAll(
                "SELECT SUM(CAST(timespent AS UNSIGNED)) as session_time
                FROM analytics 
                WHERE website_id = ? AND timespent != '' AND timespent != '0'
                AND created_at >= ? AND created_at <= ?
                GROUP BY ip_hash",
                [$website_id, $yesterdayStart, $yesterdayEnd]
            );
            
            $yesterdayAvg = 0;
            if (!empty($yesterdaySessions)) {
                $totalTime = array_sum(array_column($yesterdaySessions, 'session_time'));
                $yesterdayAvg = round($totalTime / count($yesterdaySessions));
            }
            
            $minutes = floor($todayAvg / 60);
            $seconds = $todayAvg % 60;
            $formattedTime = "{$minutes}m {$seconds}s";
            
            $change = $yesterdayAvg > 0 ? round((($todayAvg - $yesterdayAvg) / $yesterdayAvg) * 100, 1) : 0;
            $changeText = $change >= 0 ? "+{$change}%" : "{$change}%";
            
            return [
                'label' => 'Avg Time On Site Today',
                'value' => $formattedTime,
                'subtext' => "{$changeText} from yesterday",
                'icon' => 'fa-hourglass-half',
                'icon_class' => 'icon-cyan',
                'modal_title' => 'Time On Site Details'
            ];
            
        case 'bounce':
            // Today - sessions with only 1 pageview
            $todayTotal = fetchOne(
                "SELECT COUNT(DISTINCT ip_hash) as total FROM analytics 
                 WHERE website_id = ? 
                 AND created_at >= ? AND created_at <= ?",
                [$website_id, $todayStart, $todayEnd]
            );
            $todayCount = $todayTotal['total'] ?? 1;
            
            $todayBounced = fetchOne(
                "SELECT COUNT(*) as total FROM (
                    SELECT ip_hash, COUNT(*) as page_count
                    FROM analytics 
                    WHERE website_id = ? 
                    AND created_at >= ? AND created_at <= ?
                    GROUP BY ip_hash
                    HAVING page_count = 1
                ) as bounces",
                [$website_id, $todayStart, $todayEnd]
            );
            $todayBouncedCount = $todayBounced['total'] ?? 0;
            
            $todayRate = $todayCount > 0 ? round(($todayBouncedCount / $todayCount) * 100, 1) : 0;
            
            // Yesterday
            $yesterdayTotal = fetchOne(
                "SELECT COUNT(DISTINCT ip_hash) as total FROM analytics 
                 WHERE website_id = ? 
                 AND created_at >= ? AND created_at <= ?",
                [$website_id, $yesterdayStart, $yesterdayEnd]
            );
            $yesterdayCount = $yesterdayTotal['total'] ?? 1;
            
            $yesterdayBounced = fetchOne(
                "SELECT COUNT(*) as total FROM (
                    SELECT ip_hash, COUNT(*) as page_count
                    FROM analytics 
                    WHERE website_id = ? 
                    AND created_at >= ? AND created_at <= ?
                    GROUP BY ip_hash
                    HAVING page_count = 1
                ) as bounces",
                [$website_id, $yesterdayStart, $yesterdayEnd]
            );
            $yesterdayBouncedCount = $yesterdayBounced['total'] ?? 0;
            
            $yesterdayRate = $yesterdayCount > 0 ? ($yesterdayBouncedCount / $yesterdayCount) * 100 : 0;
            
            $change = round($todayRate - $yesterdayRate, 1);
            $changeText = $change >= 0 ? "+{$change}%" : "{$change}%";
            
            return [
                'label' => 'Bounce Rate Today',
                'value' => "{$todayRate}%",
                'subtext' => "{$changeText} from yesterday",
                'icon' => 'fa-sign-out-alt',
                'icon_class' => 'icon-orange',
                'modal_title' => 'Bounce Rate Details'
            ];
            
        case 'retention':
            // For retention, let's use last 7 days vs previous 7 days
            $last7Start = date('Y-m-d 00:00:00', strtotime('-7 days'));
            $last14Start = date('Y-m-d 00:00:00', strtotime('-14 days'));
            
            // Last 7 days
            $currentTotal = fetchOne(
                "SELECT COUNT(DISTINCT ip_hash) as total FROM analytics 
                 WHERE website_id = ? 
                 AND created_at >= ?",
                [$website_id, $last7Start]
            );
            $currentCount = $currentTotal['total'] ?? 1;
            
            $currentReturning = fetchOne(
                "SELECT COUNT(*) as total FROM (
                    SELECT ip_hash
                    FROM analytics 
                    WHERE website_id = ? 
                    AND created_at >= ?
                    GROUP BY ip_hash
                    HAVING COUNT(DISTINCT DATE(created_at)) > 1
                ) as returning",
                [$website_id, $last7Start]
            );
            $currentReturningCount = $currentReturning['total'] ?? 0;
            
            $currentRate = $currentCount > 0 ? round(($currentReturningCount / $currentCount) * 100, 1) : 0;
            
            // Previous 7 days (8-14 days ago)
            $previousTotal = fetchOne(
                "SELECT COUNT(DISTINCT ip_hash) as total FROM analytics 
                 WHERE website_id = ? 
                 AND created_at >= ? AND created_at < ?",
                [$website_id, $last14Start, $last7Start]
            );
            $previousCount = $previousTotal['total'] ?? 1;
            
            $previousReturning = fetchOne(
                "SELECT COUNT(*) as total FROM (
                    SELECT ip_hash
                    FROM analytics 
                    WHERE website_id = ? 
                    AND created_at >= ? AND created_at < ?
                    GROUP BY ip_hash
                    HAVING COUNT(DISTINCT DATE(created_at)) > 1
                ) as returning",
                [$website_id, $last14Start, $last7Start]
            );
            $previousReturningCount = $previousReturning['total'] ?? 0;
            
            $previousRate = $previousCount > 0 ? ($previousReturningCount / $previousCount) * 100 : 0;
            
            $change = round($currentRate - $previousRate, 1);
            $changeText = $change >= 0 ? "+{$change}%" : "{$change}%";
            
            return [
                'label' => 'Retention Rate (7 Days)',
                'value' => "{$currentRate}%",
                'subtext' => "{$changeText} from prev 7 days",
                'icon' => 'fa-redo-alt',
                'icon_class' => 'icon-green',
                'modal_title' => 'Retention Rate Details'
            ];
            
        default:
            return [
                'label' => 'Unknown',
                'value' => '--',
                'subtext' => 'No data',
                'icon' => 'fa-question',
                'icon_class' => 'icon-purple',
                'modal_title' => 'Details'
            ];
    }
} 

$data = getCard($type, $website_id);

echo <<<HTML
<div 
  hx-get="api/statistics-modal?type={$type}" 
  hx-target="#modal-content"
  hx-trigger="click"
  data-modal-title="{$data['modal_title']}">
  <div class="stat-header">
    <span class="stat-label">{$data['label']}</span>
    <div class="stat-icon {$data['icon_class']}">
      <i class="fas {$data['icon']}"></i>
    </div>
  </div>
  <div class="stat-value">{$data['value']}</div>
  <div class="stat-subtext">{$data['subtext']}</div>
</div>
HTML;
?>