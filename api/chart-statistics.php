<?php
session_start();
include "../includes/functions.php";

$chartType = $_GET['chart'] ?? 'empty';
$range = $_GET['range'] ?? 'today';
$website_id = $_SESSION["website_id"] ?? 1;

function chart(string $chartType, string $range, int $website_id): string
{
    if ($chartType !== 'chart') {
        return '';
    }

    $line = [];
    $pie = [];
    $lineLabels = []; // Added for dynamic line chart labels
    $pieLabels = ['Google', 'Direct', 'Social', 'Referral', 'Email'];
    
    // Date range for pie chart (matches the line chart range)
    $pieStartDate = '';

    switch ($range) {
        case 'today':
            // Get hourly data for today (24 hours)
            $hourlyData = fetchAll(
                "SELECT 
                    HOUR(created_at) as hour,
                    COUNT(*) as count
                FROM analytics
                WHERE website_id = ? 
                AND DATE(created_at) = CURDATE()
                GROUP BY HOUR(created_at)
                ORDER BY hour",
                [$website_id]
            );
            
            // Fill 24 hours with data and labels
            $line = array_fill(0, 24, 0);
            foreach ($hourlyData as $row) {
                $line[$row['hour']] = (int)$row['count'];
            }
            
            // Create hour labels: 1hr, 2hr, 3hr, ..., 24hr
            for ($i = 1; $i <= 24; $i++) {
                $lineLabels[] = $i . 'hr';
            }
            
            $pieStartDate = date('Y-m-d 00:00:00');
            break;

        case 'yesterday':
            // Get hourly data for yesterday
            $hourlyData = fetchAll(
                "SELECT 
                    HOUR(created_at) as hour,
                    COUNT(*) as count
                FROM analytics
                WHERE website_id = ? 
                AND DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
                GROUP BY HOUR(created_at)
                ORDER BY hour",
                [$website_id]
            );
            
            $line = array_fill(0, 24, 0);
            foreach ($hourlyData as $row) {
                $line[$row['hour']] = (int)$row['count'];
            }
            
            // Create hour labels: 1hr, 2hr, 3hr, ..., 24hr
            for ($i = 1; $i <= 24; $i++) {
                $lineLabels[] = $i . 'hr';
            }
            
            $pieStartDate = date('Y-m-d 00:00:00', strtotime('-1 day'));
            break;

        case 'week':
            // Get daily data for last 7 days
            $dailyData = fetchAll(
                "SELECT 
                    DATE(created_at) as date,
                    COUNT(*) as count
                FROM analytics
                WHERE website_id = ? 
                AND created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                GROUP BY DATE(created_at)
                ORDER BY date",
                [$website_id]
            );
            
            // Create array with dates as keys to ensure proper ordering
            $dateArray = [];
            $dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
            
            for ($i = 6; $i >= 0; $i--) {
                $date = date('Y-m-d', strtotime("-$i days"));
                $dayOfWeek = date('w', strtotime("-$i days")); // 0 (Sun) to 6 (Sat)
                $dateArray[$date] = 0;
                $lineLabels[] = $dayNames[$dayOfWeek];
            }
            
            // Fill with actual data
            foreach ($dailyData as $row) {
                if (isset($dateArray[$row['date']])) {
                    $dateArray[$row['date']] = (int)$row['count'];
                }
            }
            
            $line = array_values($dateArray);
            $pieStartDate = date('Y-m-d 00:00:00', strtotime('-7 days'));
            break;

        case 'month':
            // Get weekly data for last 30 days (group by week)
            $weeklyData = fetchAll(
                "SELECT 
                    WEEK(created_at, 1) as week_num,
                    MIN(DATE(created_at)) as week_start,
                    COUNT(*) as count
                FROM analytics
                WHERE website_id = ? 
                AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                GROUP BY WEEK(created_at, 1)
                ORDER BY week_start",
                [$website_id]
            );
            
            // Create labels for weeks (Week1, Week2, Week3, Week4)
            $weekCount = 1;
            foreach ($weeklyData as $row) {
                $line[] = (int)$row['count'];
                $lineLabels[] = 'Week' . $weekCount;
                $weekCount++;
            }
            
            // If no data, create 4 empty weeks
            if (empty($line)) {
                for ($i = 1; $i <= 4; $i++) {
                    $line[] = 0;
                    $lineLabels[] = 'Week' . $i;
                }
            }
            
            $pieStartDate = date('Y-m-d 00:00:00', strtotime('-30 days'));
            break;

        case '90days':
            // Get monthly data for last 90 days (group by month)
            $monthlyData = fetchAll(
                "SELECT 
                    DATE_FORMAT(created_at, '%Y-%m') as month,
                    COUNT(*) as count
                FROM analytics
                WHERE website_id = ? 
                AND created_at >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
                GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                ORDER BY month",
                [$website_id]
            );
            
            $monthCount = 1;
            foreach ($monthlyData as $row) {
                $line[] = (int)$row['count'];
                $lineLabels[] = $monthCount . 'st month';
                if ($monthCount == 2) $lineLabels[count($lineLabels) - 1] = '2nd month';
                if ($monthCount == 3) $lineLabels[count($lineLabels) - 1] = '3rd month';
                $monthCount++;
            }
            
            // If no data, create 3 empty months
            if (empty($line)) {
                $lineLabels = ['1st month', '2nd month', '3rd month'];
                $line = [0, 0, 0];
            }
            
            $pieStartDate = date('Y-m-d 00:00:00', strtotime('-90 days'));
            break;
    }

    // Get pie chart data (referrer distribution) for the same time period
    $referrerData = fetchAll(
        "SELECT 
            CASE 
                WHEN referrer LIKE '%google%' THEN 'Google'
                WHEN referrer = 'direct' THEN 'Direct'
                WHEN referrer LIKE '%facebook%' OR referrer LIKE '%twitter%' OR referrer LIKE '%instagram%' THEN 'Social'
                WHEN referrer LIKE '%mail%' THEN 'Email'
                ELSE 'Referral'
            END as source,
            COUNT(*) as count
        FROM analytics
        WHERE website_id = ? AND created_at >= ?
        GROUP BY source
        ORDER BY count DESC
        LIMIT 5",
        [$website_id, $pieStartDate]
    );
    
    // Create a map of actual sources to their counts
    $sourceMap = [];
    $totalReferrers = 0;
    foreach ($referrerData as $row) {
        $sourceMap[$row['source']] = (int)$row['count'];
        $totalReferrers += (int)$row['count'];
    }
    
    // If no data, use default values
    if ($totalReferrers === 0) {
        $pie = [20, 20, 20, 20, 20];
    } else {
        // Calculate percentages for each label in order
        foreach ($pieLabels as $label) {
            if (isset($sourceMap[$label])) {
                $pie[] = round(($sourceMap[$label] / $totalReferrers) * 100, 1);
            } else {
                $pie[] = 0;
            }
        }
    }

    $data = [
        'line' => $line,
        'lineLabels' => $lineLabels, // Added line chart labels
        'pie' => $pie,
        'pieLabels' => $pieLabels // Renamed for clarity
    ];

    $json = json_encode($data, JSON_HEX_APOS | JSON_HEX_QUOT);

    return <<<HTML
<input
  type="hidden"
  id="chartData"
  value='{$json}'
>
HTML;
}

echo chart($chartType, $range, $website_id);
?>