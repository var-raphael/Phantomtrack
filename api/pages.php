<?php
session_start();
include "../includes/functions.php";

$type = $_GET["type"] ?? "pages";
$view = $_GET["view"] ?? "default"; // 'default' or 'all'
$website_id = $_SESSION["website_id"] ?? 1;

// Helper function to clean URLs and extract paths
function cleanUrl($url) {
    // If it's 'direct' or already a path, return as-is
    if ($url === 'direct' || strpos($url, 'http') !== 0) {
        return $url;
    }
    
    // Parse the URL and get the path
    $parsed = parse_url($url);
    $path = $parsed['path'] ?? '/';
    
    // Remove trailing slashes except for root
    if ($path !== '/' && substr($path, -1) === '/') {
        $path = rtrim($path, '/');
    }
    
    return $path;
}

// Helper function to get referrer icon and display name
function getReferrerInfo($referrer) {
    $referrerLower = strtolower($referrer);
    
    // Check for specific platforms
    if ($referrer === 'direct') {
        return [
            'icon' => 'fa-arrow-right',
            'name' => 'Direct',
            'color' => '#10b981' // green
        ];
    } elseif (strpos($referrerLower, 'google') !== false) {
        return [
            'icon' => 'fab fa-google',
            'name' => 'Google',
            'color' => '#4285f4' // google blue
        ];
    } elseif (strpos($referrerLower, 'facebook') !== false || strpos($referrerLower, 'fb.com') !== false) {
        return [
            'icon' => 'fab fa-facebook',
            'name' => 'Facebook',
            'color' => '#1877f2' // facebook blue
        ];
    } elseif (strpos($referrerLower, 'twitter') !== false || strpos($referrerLower, 'x.com') !== false) {
        return [
            'icon' => 'fab fa-x-twitter',
            'name' => 'X (Twitter)',
            'color' => '#000000' // black
        ];
    } elseif (strpos($referrerLower, 'linkedin') !== false) {
        return [
            'icon' => 'fab fa-linkedin',
            'name' => 'LinkedIn',
            'color' => '#0a66c2' // linkedin blue
        ];
    } elseif (strpos($referrerLower, 'instagram') !== false) {
        return [
            'icon' => 'fab fa-instagram',
            'name' => 'Instagram',
            'color' => '#e4405f' // instagram pink
        ];
    } elseif (strpos($referrerLower, 'youtube') !== false) {
        return [
            'icon' => 'fab fa-youtube',
            'name' => 'YouTube',
            'color' => '#ff0000' // youtube red
        ];
    } elseif (strpos($referrerLower, 'reddit') !== false) {
        return [
            'icon' => 'fab fa-reddit',
            'name' => 'Reddit',
            'color' => '#ff4500' // reddit orange
        ];
    } elseif (strpos($referrerLower, 'github') !== false) {
        return [
            'icon' => 'fab fa-github',
            'name' => 'GitHub',
            'color' => '#333333' // github dark
        ];
    } elseif (strpos($referrerLower, 'tiktok') !== false) {
        return [
            'icon' => 'fab fa-tiktok',
            'name' => 'TikTok',
            'color' => '#000000' // tiktok black
        ];
    } elseif (strpos($referrerLower, 'pinterest') !== false) {
        return [
            'icon' => 'fab fa-pinterest',
            'name' => 'Pinterest',
            'color' => '#e60023' // pinterest red
        ];
    } elseif (strpos($referrerLower, 'whatsapp') !== false) {
        return [
            'icon' => 'fab fa-whatsapp',
            'name' => 'WhatsApp',
            'color' => '#25d366' // whatsapp green
        ];
    } elseif (strpos($referrerLower, 'telegram') !== false) {
        return [
            'icon' => 'fab fa-telegram',
            'name' => 'Telegram',
            'color' => '#0088cc' // telegram blue
        ];
    } elseif (strpos($referrerLower, 'bing') !== false) {
        return [
            'icon' => 'fa-magnifying-glass',
            'name' => 'Bing',
            'color' => '#008373' // bing teal
        ];
    } elseif (strpos($referrerLower, 'yahoo') !== false) {
        return [
            'icon' => 'fab fa-yahoo',
            'name' => 'Yahoo',
            'color' => '#720e9e' // yahoo purple
        ];
    } else {
        // Generic referral
        $domain = parse_url($referrer, PHP_URL_HOST) ?? $referrer;
        return [
            'icon' => 'fa-globe',
            'name' => $domain,
            'color' => '#6b7280' // gray
        ];
    }
}

function getPageData($type, $view, $website_id) {
    $limit = ($view === 'all') ? 100 : 5; // Show 5 by default, 100 for 'all'
    
    switch($type) {
        case 'pages':
            // Get page statistics
            $pages = fetchAll(
                "SELECT 
                    page_url as path,
                    COUNT(*) as view_count,
                    ROUND(AVG(CAST(timespent AS UNSIGNED))) as avg_seconds,
                    ROUND((SUM(CASE WHEN CAST(timespent AS UNSIGNED) < 5 OR timespent = '' OR timespent = '0' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 1) as bounce_rate
                FROM analytics 
                WHERE website_id = ?
                GROUP BY page_url
                ORDER BY view_count DESC
                LIMIT " . (int)$limit,
                [$website_id]
            );
            
            $result = [];
            foreach ($pages as $page) {
                $minutes = floor($page['avg_seconds'] / 60);
                $seconds = $page['avg_seconds'] % 60;
                
                $result[] = [
                    'path' => cleanUrl($page['path']),
                    'views' => number_format($page['view_count']),
                    'bounce' => $page['bounce_rate'] . '%',
                    'avg_time' => "{$minutes}m {$seconds}s"
                ];
            }
            
            return $result;
            
        case 'referrers':
            // Get referrer statistics
            $referrers = fetchAll(
                "SELECT 
                    referrer as path,
                    COUNT(*) as view_count,
                    ROUND(AVG(CAST(timespent AS UNSIGNED))) as avg_seconds,
                    ROUND((SUM(CASE WHEN CAST(timespent AS UNSIGNED) < 5 OR timespent = '' OR timespent = '0' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 1) as bounce_rate
                FROM analytics 
                WHERE website_id = ?
                GROUP BY referrer
                ORDER BY view_count DESC
                LIMIT " . (int)$limit,
                [$website_id]
            );
            
            $result = [];
            foreach ($referrers as $referrer) {
                $minutes = floor($referrer['avg_seconds'] / 60);
                $seconds = $referrer['avg_seconds'] % 60;
                
                $referrerInfo = getReferrerInfo($referrer['path']);
                
                $result[] = [
                    'path' => $referrer['path'],
                    'display_name' => $referrerInfo['name'],
                    'icon' => $referrerInfo['icon'],
                    'color' => $referrerInfo['color'],
                    'views' => number_format($referrer['view_count']),
                    'bounce' => $referrer['bounce_rate'] . '%',
                    'avg_time' => "{$minutes}m {$seconds}s"
                ];
            }
            
            return $result;
            
        case 'countries':
            // Get country statistics
            $countries = fetchAll(
                "SELECT 
                    country as path,
                    COUNT(*) as view_count,
                    ROUND(AVG(CAST(timespent AS UNSIGNED))) as avg_seconds,
                    ROUND((SUM(CASE WHEN CAST(timespent AS UNSIGNED) < 5 OR timespent = '' OR timespent = '0' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 1) as bounce_rate
                FROM analytics 
                WHERE website_id = ?
                GROUP BY country
                ORDER BY view_count DESC
                LIMIT " . (int)$limit,
                [$website_id]
            );
            
            $result = [];
            foreach ($countries as $country) {
                $minutes = floor($country['avg_seconds'] / 60);
                $seconds = $country['avg_seconds'] % 60;
                
                $result[] = [
                    'path' => $country['path'],
                    'views' => number_format($country['view_count']),
                    'bounce' => $country['bounce_rate'] . '%',
                    'avg_time' => "{$minutes}m {$seconds}s"
                ];
            }
            
            return $result;
            
        case 'devices':
            // Get device statistics
            $devices = fetchAll(
                "SELECT 
                    device_type as path,
                    COUNT(*) as view_count,
                    ROUND(AVG(CAST(timespent AS UNSIGNED))) as avg_seconds,
                    ROUND((SUM(CASE WHEN CAST(timespent AS UNSIGNED) < 5 OR timespent = '' OR timespent = '0' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 1) as bounce_rate
                FROM analytics 
                WHERE website_id = ?
                GROUP BY device_type
                ORDER BY view_count DESC
                LIMIT " . (int)$limit,
                [$website_id]
            );
            
            $result = [];
            foreach ($devices as $device) {
                $minutes = floor($device['avg_seconds'] / 60);
                $seconds = $device['avg_seconds'] % 60;
                
                $result[] = [
                    'path' => ucfirst($device['path']),
                    'views' => number_format($device['view_count']),
                    'bounce' => $device['bounce_rate'] . '%',
                    'avg_time' => "{$minutes}m {$seconds}s"
                ];
            }
            
            return $result;
            
        case 'browsers':
            // Get browser statistics
            $browsers = fetchAll(
                "SELECT 
                    browser_type as path,
                    COUNT(*) as view_count,
                    ROUND(AVG(CAST(timespent AS UNSIGNED))) as avg_seconds,
                    ROUND((SUM(CASE WHEN CAST(timespent AS UNSIGNED) < 5 OR timespent = '' OR timespent = '0' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 1) as bounce_rate
                FROM analytics 
                WHERE website_id = ?
                GROUP BY browser_type
                ORDER BY view_count DESC
                LIMIT " . (int)$limit,
                [$website_id]
            );
            
            $result = [];
            foreach ($browsers as $browser) {
                $minutes = floor($browser['avg_seconds'] / 60);
                $seconds = $browser['avg_seconds'] % 60;
                
                $result[] = [
                    'path' => $browser['path'],
                    'views' => number_format($browser['view_count']),
                    'bounce' => $browser['bounce_rate'] . '%',
                    'avg_time' => "{$minutes}m {$seconds}s"
                ];
            }
            
            return $result;
            
        default:
            return [];
    }
}

$data = getPageData($type, $view, $website_id);

// Check if detailed view
$isDetailed = $view === 'all';

// Generate the list items
if (empty($data)) {
    echo '<li class="data-item"><span class="data-label">No data available</span></li>';
} else {
    foreach ($data as $item) {
        // Special handling for referrers with icons
        if ($type === 'referrers' && isset($item['icon'])) {
            if ($isDetailed) {
                // Detailed view with icon
                echo <<<HTML
                <li class="data-item data-item-detailed">
                  <span class="data-label">
                    <i class="fas {$item['icon']}" style="color: {$item['color']}; margin-right: 8px; font-size: 20px;"></i>
                    {$item['display_name']}
                  </span>
                  <span class="data-metrics">
                    <span class="data-metric">
                      <small>Views:</small> {$item['views']}
                    </span>
                    <span class="data-metric">
                      <small>Bounce:</small> {$item['bounce']}
                    </span>
                    <span class="data-metric">
                      <small>Avg Time:</small> {$item['avg_time']}
                    </span>
                  </span>
                </li>
                
                HTML;
            } else {
                // Simple view with icon
                echo <<<HTML
                <li class="data-item">
                  <span class="data-label">
                    <i class="fas {$item['icon']}" style="color: {$item['color']}; margin-right: 8px; font-size: 20px;"></i>
                    {$item['display_name']}
                  </span>
                  <span class="data-value">{$item['views']}</span>
                </li>
                
                HTML;
            }
        } else {
            // Regular items (pages, countries, devices, browsers)
            if ($isDetailed) {
                // Detailed view
                echo <<<HTML
                <li class="data-item data-item-detailed">
                  <span class="data-label">{$item['path']}</span>
                  <span class="data-metrics">
                    <span class="data-metric">
                      <small>Views:</small> {$item['views']}
                    </span>
                    <span class="data-metric">
                      <small>Bounce:</small> {$item['bounce']}
                    </span>
                    <span class="data-metric">
                      <small>Avg Time:</small> {$item['avg_time']}
                    </span>
                  </span>
                </li>
                
                HTML;
            } else {
                // Simple view
                echo <<<HTML
                <li class="data-item">
                  <span class="data-label">{$item['path']}</span>
                  <span class="data-value">{$item['views']}</span>
                </li>
                
                HTML;
            }
        }
    }
}
?>