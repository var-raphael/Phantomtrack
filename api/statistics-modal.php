<?php
session_start();
include "../includes/functions.php";

$type = $_GET['type'] ?? 'visitors';
$website_id = $_SESSION["website_id"] ?? 1;

// Calculate date ranges - using 30 days to match cards
$date30DaysAgo = date('Y-m-d H:i:s', strtotime('-30 days'));
$dateToday = date('Y-m-d 00:00:00');
$dateThisWeek = date('Y-m-d 00:00:00', strtotime('monday this week'));
$dateThisMonth = date('Y-m-01 00:00:00');
$dateThisYear = date('Y-01-01 00:00:00');

function getStatsDetail($type, $website_id, $date30DaysAgo, $dateToday, $dateThisWeek, $dateThisMonth, $dateThisYear) {
    switch($type) {
        case 'unique':
            // Get unique visitors by period (last 30 days)
            $todayUnique = fetchOne(
                "SELECT COUNT(DISTINCT ip_hash) as count FROM analytics 
                WHERE website_id = ? AND created_at >= ? AND created_at >= ?",
                [$website_id, $date30DaysAgo, $dateToday]
            )['count'] ?? 0;
            
            $weekUnique = fetchOne(
                "SELECT COUNT(DISTINCT ip_hash) as count FROM analytics 
                WHERE website_id = ? AND created_at >= ? AND created_at >= ?",
                [$website_id, $date30DaysAgo, $dateThisWeek]
            )['count'] ?? 0;
            
            $monthUnique = fetchOne(
                "SELECT COUNT(DISTINCT ip_hash) as count FROM analytics 
                WHERE website_id = ? AND created_at >= ? AND created_at >= ?",
                [$website_id, $date30DaysAgo, $dateThisMonth]
            )['count'] ?? 0;
            
            $yearUnique = fetchOne(
                "SELECT COUNT(DISTINCT ip_hash) as count FROM analytics 
                WHERE website_id = ? AND created_at >= ?",
                [$website_id, $dateThisYear]
            )['count'] ?? 0;
            
            // Calculate growth rate (compare this month vs last month)
            $lastMonthStart = date('Y-m-d 00:00:00', strtotime('-1 month', strtotime($dateThisMonth)));
            $lastMonthEnd = date('Y-m-d 23:59:59', strtotime('-1 day', strtotime($dateThisMonth)));
            
            $lastMonthUnique = fetchOne(
                "SELECT COUNT(DISTINCT ip_hash) as count FROM analytics 
                WHERE website_id = ? AND created_at BETWEEN ? AND ?",
                [$website_id, $lastMonthStart, $lastMonthEnd]
            )['count'] ?? 1;
            
            $growthRate = $lastMonthUnique > 0 
                ? round((($monthUnique - $lastMonthUnique) / $lastMonthUnique) * 100, 1) 
                : 0;
            
            // Get traffic sources (last 30 days)
            $totalVisits = fetchOne(
                "SELECT COUNT(*) as count FROM analytics 
                WHERE website_id = ? AND created_at >= ?",
                [$website_id, $date30DaysAgo]
            )['count'] ?? 1;
            
            $sources = fetchAll(
                "SELECT 
                    CASE 
                        WHEN referrer = 'direct' THEN 'Direct'
                        WHEN referrer LIKE '%google%' THEN 'Google Search'
                        WHEN referrer LIKE '%facebook%' OR referrer LIKE '%twitter%' OR referrer LIKE '%instagram%' THEN 'Social Media'
                        ELSE 'Referral'
                    END as source_type,
                    COUNT(*) as count
                FROM analytics 
                WHERE website_id = ? AND created_at >= ?
                GROUP BY source_type",
                [$website_id, $date30DaysAgo]
            );
            
            $sourcesFormatted = [];
            foreach ($sources as $source) {
                $percentage = round(($source['count'] / $totalVisits) * 100, 1);
                $sourcesFormatted[$source['source_type']] = $percentage . '%';
            }
            
            return [
                'title' => 'Unique Visitors Breakdown',
                'stats' => [
                    'Today' => number_format($todayUnique),
                    'This Week' => number_format($weekUnique),
                    'This Month' => number_format($monthUnique),
                    'This Year' => number_format($yearUnique),
                    'Growth Rate' => ($growthRate >= 0 ? '+' : '') . $growthRate . '%'
                ],
                'sources' => $sourcesFormatted
            ];
            
        case 'pageviews':
            // Get pageviews by period (last 30 days)
            $todayViews = fetchOne(
                "SELECT COUNT(*) as count FROM analytics 
                WHERE website_id = ? AND created_at >= ? AND created_at >= ?",
                [$website_id, $date30DaysAgo, $dateToday]
            )['count'] ?? 0;
            
            $weekViews = fetchOne(
                "SELECT COUNT(*) as count FROM analytics 
                WHERE website_id = ? AND created_at >= ? AND created_at >= ?",
                [$website_id, $date30DaysAgo, $dateThisWeek]
            )['count'] ?? 0;
            
            $monthViews = fetchOne(
                "SELECT COUNT(*) as count FROM analytics 
                WHERE website_id = ? AND created_at >= ? AND created_at >= ?",
                [$website_id, $date30DaysAgo, $dateThisMonth]
            )['count'] ?? 0;
            
            // Calculate average per visitor
            $monthVisitors = fetchOne(
                "SELECT COUNT(DISTINCT ip_hash) as count FROM analytics 
                WHERE website_id = ? AND created_at >= ? AND created_at >= ?",
                [$website_id, $date30DaysAgo, $dateThisMonth]
            )['count'] ?? 1;
            
            $avgPerVisitor = $monthVisitors > 0 ? round($monthViews / $monthVisitors, 1) : 0;
            
            // Calculate growth rate
            $lastMonthStart = date('Y-m-d 00:00:00', strtotime('-1 month', strtotime($dateThisMonth)));
            $lastMonthEnd = date('Y-m-d 23:59:59', strtotime('-1 day', strtotime($dateThisMonth)));
            
            $lastMonthViews = fetchOne(
                "SELECT COUNT(*) as count FROM analytics 
                WHERE website_id = ? AND created_at BETWEEN ? AND ?",
                [$website_id, $lastMonthStart, $lastMonthEnd]
            )['count'] ?? 1;
            
            $growthRate = $lastMonthViews > 0 ? round((($monthViews - $lastMonthViews) / $lastMonthViews) * 100, 1) : 0;
            
            // Get top pages (last 30 days)
            $topPages = fetchAll(
                "SELECT page_url, COUNT(*) as views 
                FROM analytics 
                WHERE website_id = ? AND created_at >= ?
                GROUP BY page_url 
                ORDER BY views DESC 
                LIMIT 4",
                [$website_id, $date30DaysAgo]
            );
            
            $topPagesFormatted = [];
            foreach ($topPages as $page) {
                $url = parse_url($page['page_url'], PHP_URL_PATH) ?: '/';
                $topPagesFormatted[$url] = number_format($page['views']) . ' views';
            }
            
            return [
                'title' => 'Page Views Analysis',
                'stats' => [
                    'Today' => number_format($todayViews),
                    'This Week' => number_format($weekViews),
                    'This Month' => number_format($monthViews),
                    'Average per Visitor' => $avgPerVisitor,
                    'Growth Rate' => ($growthRate >= 0 ? '+' : '') . $growthRate . '%'
                ],
                'top_pages' => $topPagesFormatted
            ];
            
        case 'live':
            // Get current live users
            $currentLive = fetchOne(
                "SELECT COUNT(DISTINCT session_id) as total 
                FROM live_user_sessions 
                WHERE website_id = ? AND last_ping >= DATE_SUB(NOW(), INTERVAL 2 MINUTE)",
                [$website_id]
            )['total'] ?? 0;
            
            // Get active sessions by time period
            $last1Min = date('Y-m-d H:i:s', strtotime('-1 minute'));
            $last5Min = date('Y-m-d H:i:s', strtotime('-5 minutes'));
            $last30Min = date('Y-m-d H:i:s', strtotime('-30 minutes'));
            
            $active1Min = fetchOne(
                "SELECT COUNT(DISTINCT session_id) as count FROM live_user_sessions 
                WHERE website_id = ? AND last_ping >= ?",
                [$website_id, $last1Min]
            )['count'] ?? 0;
            
            $active5Min = fetchOne(
                "SELECT COUNT(DISTINCT session_id) as count FROM live_user_sessions 
                WHERE website_id = ? AND last_ping >= ?",
                [$website_id, $last5Min]
            )['count'] ?? 0;
            
            $active30Min = fetchOne(
                "SELECT COUNT(DISTINCT session_id) as count FROM live_user_sessions 
                WHERE website_id = ? AND last_ping >= ?",
                [$website_id, $last30Min]
            )['count'] ?? 0;
            
            // Get geographic distribution (from last 30 minutes of analytics)
            $recentLocations = fetchAll(
                "SELECT country, COUNT(*) as count 
                FROM analytics 
                WHERE website_id = ? AND created_at >= ?
                GROUP BY country 
                ORDER BY count DESC 
                LIMIT 5",
                [$website_id, $last30Min]
            );
            
            $locationsFormatted = [];
            foreach ($recentLocations as $location) {
                $countryName = $location['country'];
                $locationsFormatted[$countryName] = $location['count'] . ' users';
            }
            
            return [
                'title' => 'Live Visitors Details',
                'stats' => [
                    'Currently Active' => number_format($currentLive),
                    'Active in Last Minute' => number_format($active1Min),
                    'Active in Last 5 Minutes' => number_format($active5Min),
                    'Active in Last 30 Minutes' => number_format($active30Min)
                ],
                'locations' => $locationsFormatted
            ];
            
        case 'bounce':
            // Calculate bounce rate (sessions with only 1 pageview) - last 30 days
            $totalSessions = fetchOne(
                "SELECT COUNT(DISTINCT CONCAT(ip_hash, DATE(created_at))) as count 
                FROM analytics 
                WHERE website_id = ? AND created_at >= ?",
                [$website_id, $date30DaysAgo]
            )['count'] ?? 1;
            
            // Count sessions with only 1 pageview
            $bouncedSessions = fetchOne(
                "SELECT COUNT(*) as count FROM (
                    SELECT ip_hash, DATE(created_at) as session_date, COUNT(*) as page_count
                    FROM analytics 
                    WHERE website_id = ? AND created_at >= ?
                    GROUP BY ip_hash, DATE(created_at)
                    HAVING page_count = 1
                ) as bounces",
                [$website_id, $date30DaysAgo]
            )['count'] ?? 0;
            
            $overallBounce = $totalSessions > 0 ? round(($bouncedSessions / $totalSessions) * 100, 1) : 0;
            
            // Bounce by device type
            $devices = ['desktop', 'mobile', 'tablet'];
            $deviceBounceRates = [];
            
            foreach ($devices as $device) {
                $deviceTotal = fetchOne(
                    "SELECT COUNT(DISTINCT CONCAT(ip_hash, DATE(created_at))) as count 
                    FROM analytics 
                    WHERE website_id = ? AND created_at >= ? AND device_type = ?",
                    [$website_id, $date30DaysAgo, $device]
                )['count'] ?? 1;
                
                $deviceBounced = fetchOne(
                    "SELECT COUNT(*) as count FROM (
                        SELECT ip_hash, DATE(created_at) as session_date, COUNT(*) as page_count
                        FROM analytics 
                        WHERE website_id = ? AND created_at >= ? AND device_type = ?
                        GROUP BY ip_hash, DATE(created_at)
                        HAVING page_count = 1
                    ) as bounces",
                    [$website_id, $date30DaysAgo, $device]
                )['count'] ?? 0;
                
                $rate = $deviceTotal > 0 ? round(($deviceBounced / $deviceTotal) * 100, 1) : 0;
                $deviceBounceRates[ucfirst($device)] = $rate . '%';
            }
            
            // Get pages with high bounce rate using subquery
            $highBouncePage = fetchAll(
                "SELECT page_url, total, quick_exits
                FROM (
                    SELECT page_url, 
                        COUNT(*) as total,
                        SUM(CASE WHEN CAST(timespent AS UNSIGNED) < 5 THEN 1 ELSE 0 END) as quick_exits
                    FROM analytics 
                    WHERE website_id = ? AND created_at >= ?
                    GROUP BY page_url 
                    HAVING total > 5
                ) as page_stats
                ORDER BY (quick_exits / total) DESC 
                LIMIT 4",
                [$website_id, $date30DaysAgo]
            );
            
            $highBounceFormatted = [];
            foreach ($highBouncePage as $page) {
                $url = parse_url($page['page_url'], PHP_URL_PATH) ?: '/';
                $bounceRate = $page['total'] > 0 ? round(($page['quick_exits'] / $page['total']) * 100) : 0;
                $highBounceFormatted[$url] = $bounceRate . '%';
            }
            
            return [
                'title' => 'Bounce Rate Analysis',
                'stats' => [
                    'Overall Bounce Rate' => $overallBounce . '%',
                    'Desktop' => $deviceBounceRates['Desktop'] ?? '0%',
                    'Mobile' => $deviceBounceRates['Mobile'] ?? '0%',
                    'Tablet' => $deviceBounceRates['Tablet'] ?? '0%',
                    'Period' => 'Last 30 days'
                ],
                'high_bounce_pages' => $highBounceFormatted
            ];
            
        case 'time-on-page':
            // Average time on page (last 30 days)
            $overallAvg = fetchOne(
                "SELECT AVG(CAST(timespent AS UNSIGNED)) as avg_time 
                FROM analytics 
                WHERE website_id = ? AND created_at >= ? AND timespent != '' AND timespent != '0'",
                [$website_id, $date30DaysAgo]
            )['avg_time'] ?? 0;
            
            $overallAvg = round((float)$overallAvg);
            
            // By device type
            $deviceTimes = fetchAll(
                "SELECT device_type, AVG(CAST(timespent AS UNSIGNED)) as avg_time 
                FROM analytics 
                WHERE website_id = ? AND created_at >= ? AND timespent != '' AND timespent != '0'
                GROUP BY device_type",
                [$website_id, $date30DaysAgo]
            );
            
            $deviceTimeFormatted = [
                'Desktop' => '0m 0s',
                'Mobile' => '0m 0s',
                'Tablet' => '0m 0s'
            ];
            
            foreach ($deviceTimes as $device) {
                $deviceType = ucfirst($device['device_type']);
                $avgTime = round((float)$device['avg_time']);
                $minutes = floor($avgTime / 60);
                $seconds = $avgTime % 60;
                $deviceTimeFormatted[$deviceType] = "{$minutes}m {$seconds}s";
            }
            
            $overallMin = floor($overallAvg / 60);
            $overallSec = $overallAvg % 60;
            
            return [
                'title' => 'Average Time on Page',
                'stats' => [
                    'Overall Average' => "{$overallMin}m {$overallSec}s",
                    'Desktop' => $deviceTimeFormatted['Desktop'],
                    'Mobile' => $deviceTimeFormatted['Mobile'],
                    'Tablet' => $deviceTimeFormatted['Tablet'],
                    'Period' => 'Last 30 days'
                ]
            ];
            
        case 'time-on-site':
            // Average session time (per user per day) - last 30 days
            $sessionTimes = fetchAll(
                "SELECT ip_hash, DATE(created_at) as session_date, 
                    SUM(CAST(timespent AS UNSIGNED)) as total_time,
                    device_type
                FROM analytics 
                WHERE website_id = ? AND created_at >= ?
                GROUP BY ip_hash, DATE(created_at), device_type",
                [$website_id, $date30DaysAgo]
            );
            
            if (empty($sessionTimes)) {
                return [
                    'title' => 'Average Time on Site',
                    'stats' => [
                        'Overall Average' => '0m 0s',
                        'Desktop' => '0m 0s',
                        'Mobile' => '0m 0s',
                        'Tablet' => '0m 0s',
                        'Period' => 'Last 30 days'
                    ]
                ];
            }
            
            $totalTimeSum = 0;
            $deviceTotals = ['desktop' => [], 'mobile' => [], 'tablet' => []];
            
            foreach ($sessionTimes as $session) {
                $totalTimeSum += $session['total_time'];
                $deviceTotals[$session['device_type']][] = $session['total_time'];
            }
            
            $overallAvg = count($sessionTimes) > 0 ? round($totalTimeSum / count($sessionTimes)) : 0;
            $overallMin = floor($overallAvg / 60);
            $overallSec = $overallAvg % 60;
            
            $deviceTimeFormatted = [];
            foreach (['desktop', 'mobile', 'tablet'] as $device) {
                if (!empty($deviceTotals[$device])) {
                    $avg = round(array_sum($deviceTotals[$device]) / count($deviceTotals[$device]));
                    $minutes = floor($avg / 60);
                    $seconds = $avg % 60;
                    $deviceTimeFormatted[ucfirst($device)] = "{$minutes}m {$seconds}s";
                } else {
                    $deviceTimeFormatted[ucfirst($device)] = '0m 0s';
                }
            }
            
            return [
                'title' => 'Average Time on Site',
                'stats' => [
                    'Overall Average' => "{$overallMin}m {$overallSec}s",
                    'Desktop' => $deviceTimeFormatted['Desktop'] ?? '0m 0s',
                    'Mobile' => $deviceTimeFormatted['Mobile'] ?? '0m 0s',
                    'Tablet' => $deviceTimeFormatted['Tablet'] ?? '0m 0s',
                    'Period' => 'Last 30 days'
                ]
            ];
            
        case 'retention':
            // Calculate retention rate (visitors who visited on multiple days) - last 30 days
            $returningVisitors = fetchOne(
                "SELECT COUNT(*) as count FROM (
                    SELECT ip_hash
                    FROM analytics 
                    WHERE website_id = ? AND created_at >= ?
                    GROUP BY ip_hash
                    HAVING COUNT(DISTINCT DATE(created_at)) > 1
                ) as returning",
                [$website_id, $date30DaysAgo]
            )['count'] ?? 0;
            
            $totalVisitors = fetchOne(
                "SELECT COUNT(DISTINCT ip_hash) as count 
                FROM analytics 
                WHERE website_id = ? AND created_at >= ?",
                [$website_id, $date30DaysAgo]
            )['count'] ?? 1;
            
            $overallRetention = $totalVisitors > 0 ? round(($returningVisitors / $totalVisitors) * 100, 1) : 0;
            
            // 7-day retention
            $date7Days = date('Y-m-d H:i:s', strtotime('-7 days'));
            $visitors7DaysAgo = fetchAll(
                "SELECT DISTINCT ip_hash FROM analytics 
                WHERE website_id = ? AND created_at BETWEEN ? AND ?",
                [$website_id, date('Y-m-d H:i:s', strtotime('-14 days')), $date7Days]
            );
            
            $returned7Days = 0;
            foreach ($visitors7DaysAgo as $visitor) {
                $hasReturned = fetchOne(
                    "SELECT COUNT(*) as count FROM analytics 
                    WHERE website_id = ? AND ip_hash = ? AND created_at >= ?",
                    [$website_id, $visitor['ip_hash'], $date7Days]
                )['count'] ?? 0;
                
                if ($hasReturned > 0) $returned7Days++;
            }
            
            $retention7Day = count($visitors7DaysAgo) > 0 ? round(($returned7Days / count($visitors7DaysAgo)) * 100, 1) : 0;
            
            return [
                'title' => 'Retention Rate Analysis',
                'stats' => [
                    'Overall Retention' => $overallRetention . '%',
                    '7-Day Retention' => $retention7Day . '%',
                    'Returning Visitors' => number_format($returningVisitors),
                    'Total Unique Visitors' => number_format($totalVisitors),
                    'Period' => 'Last 30 days'
                ]
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
        $remaining = '∞';
        $projected = 'N/A';
    } else {
        $percentUsed = $limit > 0 ? round(($total / $limit) * 100, 1) : 0;
        $daysInMonth = date('t');
        $currentDay = date('j');
        $daysLeft = max(0, $daysInMonth - $currentDay);
        $dailyAvg = $currentDay > 0 ? ($total / $currentDay) : 0;
        $projected = number_format(round($total + ($dailyAvg * $daysLeft)));
        $remaining = number_format(max(0, $limit - $total));
    }
    
    return [
        'title' => 'Usage Statistics',
        'stats' => [
            'Current Usage' => number_format($total),
            'Monthly Limit' => $isUnlimited ? '300,000 (Lifetime)' : number_format($limit),
            'Remaining' => $remaining,
            'Percentage Used' => $percentUsed . '%',
            'Projected End of Month' => $projected
        ]
    ];
            
        default:
            return [
                'title' => 'Unknown Type',
                'stats' => [],
                'message' => 'No data available'
            ];
    }
}

$data = getStatsDetail($type, $website_id, $date30DaysAgo, $dateToday, $dateThisWeek, $dateThisMonth, $dateThisYear);

// Generate HTML with theme-aware classes
echo "<div class='stats-detail-container'>";

echo "<div class='detail-section'>";
echo "<h4 class='section-heading'>Statistics</h4>";
foreach($data['stats'] as $label => $value) {
    echo <<<HTML
<div class="detail-item">
    <span class="detail-label">{$label}</span>
    <span class="detail-value">{$value}</span>
</div>
HTML;
}
echo "</div>";

// Additional sections
if (isset($data['sources'])) {
    echo "<div class='detail-section'>";
    echo "<h4 class='section-heading'>Traffic Sources</h4>";
    foreach($data['sources'] as $source => $percentage) {
        echo <<<HTML
<div class="detail-item">
    <span class="detail-label">{$source}</span>
    <span class="detail-value">{$percentage}</span>
</div>
HTML;
    }
    echo "</div>";
}

if (isset($data['top_pages'])) {
    echo "<div class='detail-section'>";
    echo "<h4 class='section-heading'>Top Pages</h4>";
    foreach($data['top_pages'] as $page => $views) {
        echo <<<HTML
<div class="detail-item">
    <span class="detail-label">{$page}</span>
    <span class="detail-value">{$views}</span>
</div>
HTML;
    }
    echo "</div>";
}

if (isset($data['locations'])) {
    echo "<div class='detail-section'>";
    echo "<h4 class='section-heading'>Geographic Distribution</h4>";
    foreach($data['locations'] as $country => $users) {
        echo <<<HTML
<div class="detail-item">
    <span class="detail-label">{$country}</span>
    <span class="detail-value">{$users}</span>
</div>
HTML;
    }
    echo "</div>";
}

if (isset($data['high_bounce_pages'])) {
    echo "<div class='detail-section'>";
    echo "<h4 class='section-heading'>High Bounce Rate Pages</h4>";
    foreach($data['high_bounce_pages'] as $page => $rate) {
        echo <<<HTML
<div class="detail-item">
    <span class="detail-label">{$page}</span>
    <span class="detail-value detail-value-warning">{$rate}</span>
</div>
HTML;
    }
    echo "</div>";
}

echo "</div>";
?>

<style>

.stats-detail-container {
    width: 100%;
}

.detail-section {
    margin-bottom: 70px;
}

.detail-section:last-child {
    margin-bottom: 0;
}

.section-heading {
    font-size: 13px;
    font-weight: 600;
    color: var(--text);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin: 0 0 12px 0;
    padding-bottom: 8px;
    border-bottom: 1px solid var(--border);
    opacity: 0.7;
}

.detail-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 12px;
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 6px;
    margin-bottom: 8px;
    transition: all 0.2s ease;
}

.detail-item:hover {
    background: var(--hover);
}

.detail-item:last-child {
    margin-bottom: 0;
}

.detail-label {
    font-size: 12px;
    color: var(--text);
    opacity: 0.7;
    font-weight: 500;
}

.detail-value {
    font-size: 13px;
    color: var(--text);
    font-weight: 600;
}

.detail-value-warning {
    color: #ef4444;
}

/* Color coding for growth/retention */
.detail-value:contains('+') {
    color: #10b981;
}

/* Responsive */
@media (max-width: 640px) {
    .detail-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 6px;
    }
    
    .detail-label {
        font-size: 11px;
    }
    
    .detail-value {
        font-size: 14px;
    }
}
</style>