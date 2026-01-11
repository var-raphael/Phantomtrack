<?php
session_start();
include "../includes/functions.php";

$type = $_GET["type"] ?? "pages";
$view = $_GET["view"] ?? "default"; // 'default' or 'all'
$website_id = $_SESSION["website_id"] ?? 1;

// Helper function to normalize URLs - extract and clean paths
function normalizeUrl($url) {
    // Handle empty or direct
    if (empty($url) || $url === 'direct') {
        return $url;
    }
    
    // If it's already a path (starts with /), clean it
    if (strpos($url, '/') === 0) {
        $path = $url;
    } else {
        // Parse full URL to get path
        $parsed = parse_url($url);
        $path = $parsed['path'] ?? '/';
    }
    
    // Normalize the path
    // Remove trailing slashes except for root
    if ($path !== '/' && substr($path, -1) === '/') {
        $path = rtrim($path, '/');
    }
    
    // If empty after trimming, return root
    if (empty($path)) {
        $path = '/';
    }
    
    return $path;
}

// Helper function to normalize referrers
function normalizeReferrer($referrer) {
    // Handle direct/empty
    if (empty($referrer) || $referrer === 'direct') {
        return 'direct';
    }
    
    // Parse the referrer URL
    $parsed = parse_url($referrer);
    
    // If we can't parse it, return as-is
    if (!$parsed || !isset($parsed['host'])) {
        return $referrer;
    }
    
    // Extract the domain (remove www. for consistency)
    $domain = $parsed['host'];
    $domain = preg_replace('/^www\./i', '', $domain);
    
    return $domain;
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
    } 
    // Google
    elseif (strpos($referrerLower, 'google') !== false) {
        return [
            'icon' => 'fab fa-google',
            'name' => 'Google',
            'color' => '#4285f4'
        ];
    } 
    // Facebook
    elseif (strpos($referrerLower, 'facebook') !== false || strpos($referrerLower, 'fb.com') !== false || strpos($referrerLower, 'fb.me') !== false) {
        return [
            'icon' => 'fab fa-facebook',
            'name' => 'Facebook',
            'color' => '#1877f2'
        ];
    } 
    // Twitter/X (including t.co)
    elseif (strpos($referrerLower, 'twitter') !== false || strpos($referrerLower, 'x.com') !== false || strpos($referrerLower, 't.co') !== false) {
        return [
            'icon' => 'fab fa-x-twitter',
            'name' => 'X (Twitter)',
            'color' => '#000000'
        ];
    } 
    // LinkedIn
    elseif (strpos($referrerLower, 'linkedin') !== false || strpos($referrerLower, 'lnkd.in') !== false) {
        return [
            'icon' => 'fab fa-linkedin',
            'name' => 'LinkedIn',
            'color' => '#0a66c2'
        ];
    } 
    // Instagram
    elseif (strpos($referrerLower, 'instagram') !== false) {
        return [
            'icon' => 'fab fa-instagram',
            'name' => 'Instagram',
            'color' => '#e4405f'
        ];
    } 
    // YouTube
    elseif (strpos($referrerLower, 'youtube') !== false || strpos($referrerLower, 'youtu.be') !== false) {
        return [
            'icon' => 'fab fa-youtube',
            'name' => 'YouTube',
            'color' => '#ff0000'
        ];
    } 
    // Reddit
    elseif (strpos($referrerLower, 'reddit') !== false) {
        return [
            'icon' => 'fab fa-reddit',
            'name' => 'Reddit',
            'color' => '#ff4500'
        ];
    } 
    // GitHub
    elseif (strpos($referrerLower, 'github') !== false) {
        return [
            'icon' => 'fab fa-github',
            'name' => 'GitHub',
            'color' => '#333333'
        ];
    } 
    // TikTok
    elseif (strpos($referrerLower, 'tiktok') !== false) {
        return [
            'icon' => 'fab fa-tiktok',
            'name' => 'TikTok',
            'color' => '#000000'
        ];
    } 
    // Pinterest
    elseif (strpos($referrerLower, 'pinterest') !== false || strpos($referrerLower, 'pin.it') !== false) {
        return [
            'icon' => 'fab fa-pinterest',
            'name' => 'Pinterest',
            'color' => '#e60023'
        ];
    } 
    // WhatsApp
    elseif (strpos($referrerLower, 'whatsapp') !== false || strpos($referrerLower, 'wa.me') !== false) {
        return [
            'icon' => 'fab fa-whatsapp',
            'name' => 'WhatsApp',
            'color' => '#25d366'
        ];
    } 
    // Telegram
    elseif (strpos($referrerLower, 'telegram') !== false || strpos($referrerLower, 't.me') !== false) {
        return [
            'icon' => 'fab fa-telegram',
            'name' => 'Telegram',
            'color' => '#0088cc'
        ];
    } 
    // Bing
    elseif (strpos($referrerLower, 'bing') !== false) {
        return [
            'icon' => 'fa-magnifying-glass',
            'name' => 'Bing',
            'color' => '#008373'
        ];
    } 
    // Yahoo
    elseif (strpos($referrerLower, 'yahoo') !== false) {
        return [
            'icon' => 'fab fa-yahoo',
            'name' => 'Yahoo',
            'color' => '#720e9e'
        ];
    }
    // DuckDuckGo
    elseif (strpos($referrerLower, 'duckduckgo') !== false) {
        return [
            'icon' => 'fa-magnifying-glass',
            'name' => 'DuckDuckGo',
            'color' => '#de5833'
        ];
    }
    // Snapchat
    elseif (strpos($referrerLower, 'snapchat') !== false) {
        return [
            'icon' => 'fab fa-snapchat',
            'name' => 'Snapchat',
            'color' => '#fffc00'
        ];
    }
    // Discord
    elseif (strpos($referrerLower, 'discord') !== false) {
        return [
            'icon' => 'fab fa-discord',
            'name' => 'Discord',
            'color' => '#5865f2'
        ];
    }
    // Slack
    elseif (strpos($referrerLower, 'slack') !== false) {
        return [
            'icon' => 'fab fa-slack',
            'name' => 'Slack',
            'color' => '#4a154b'
        ];
    }
    // Twitch
    elseif (strpos($referrerLower, 'twitch') !== false) {
        return [
            'icon' => 'fab fa-twitch',
            'name' => 'Twitch',
            'color' => '#9146ff'
        ];
    }
    // Medium
    elseif (strpos($referrerLower, 'medium') !== false) {
        return [
            'icon' => 'fab fa-medium',
            'name' => 'Medium',
            'color' => '#000000'
        ];
    }
    // Quora
    elseif (strpos($referrerLower, 'quora') !== false) {
        return [
            'icon' => 'fab fa-quora',
            'name' => 'Quora',
            'color' => '#a82400'
        ];
    }
    // Stack Overflow
    elseif (strpos($referrerLower, 'stackoverflow') !== false || strpos($referrerLower, 'stackexchange') !== false) {
        return [
            'icon' => 'fab fa-stack-overflow',
            'name' => 'Stack Overflow',
            'color' => '#f48024'
        ];
    }
    // Tumblr
    elseif (strpos($referrerLower, 'tumblr') !== false) {
        return [
            'icon' => 'fab fa-tumblr',
            'name' => 'Tumblr',
            'color' => '#35465c'
        ];
    }
    // Spotify
    elseif (strpos($referrerLower, 'spotify') !== false) {
        return [
            'icon' => 'fab fa-spotify',
            'name' => 'Spotify',
            'color' => '#1db954'
        ];
    }
    // Vimeo
    elseif (strpos($referrerLower, 'vimeo') !== false) {
        return [
            'icon' => 'fab fa-vimeo',
            'name' => 'Vimeo',
            'color' => '#1ab7ea'
        ];
    }
    // Dribbble
    elseif (strpos($referrerLower, 'dribbble') !== false) {
        return [
            'icon' => 'fab fa-dribbble',
            'name' => 'Dribbble',
            'color' => '#ea4c89'
        ];
    }
    // Behance
    elseif (strpos($referrerLower, 'behance') !== false) {
        return [
            'icon' => 'fab fa-behance',
            'name' => 'Behance',
            'color' => '#1769ff'
        ];
    }
    // GitLab
    elseif (strpos($referrerLower, 'gitlab') !== false) {
        return [
            'icon' => 'fab fa-gitlab',
            'name' => 'GitLab',
            'color' => '#fc6d26'
        ];
    }
    // Bitbucket
    elseif (strpos($referrerLower, 'bitbucket') !== false) {
        return [
            'icon' => 'fab fa-bitbucket',
            'name' => 'Bitbucket',
            'color' => '#0052cc'
        ];
    }
    // Dev.to
    elseif (strpos($referrerLower, 'dev.to') !== false) {
        return [
            'icon' => 'fab fa-dev',
            'name' => 'Dev.to',
            'color' => '#0a0a0a'
        ];
    }
    // Hacker News
    elseif (strpos($referrerLower, 'news.ycombinator') !== false) {
        return [
            'icon' => 'fab fa-hacker-news',
            'name' => 'Hacker News',
            'color' => '#ff6600'
        ];
    }
    // Product Hunt
    elseif (strpos($referrerLower, 'producthunt') !== false) {
        return [
            'icon' => 'fab fa-product-hunt',
            'name' => 'Product Hunt',
            'color' => '#da552f'
        ];
    }
    // Stripe
    elseif (strpos($referrerLower, 'stripe') !== false) {
        return [
            'icon' => 'fab fa-stripe',
            'name' => 'Stripe',
            'color' => '#635bff'
        ];
    }
    // Shopify
    elseif (strpos($referrerLower, 'shopify') !== false) {
        return [
            'icon' => 'fab fa-shopify',
            'name' => 'Shopify',
            'color' => '#96bf48'
        ];
    }
    // WordPress
    elseif (strpos($referrerLower, 'wordpress') !== false) {
        return [
            'icon' => 'fab fa-wordpress',
            'name' => 'WordPress',
            'color' => '#21759b'
        ];
    }
    // Wix
    elseif (strpos($referrerLower, 'wix') !== false) {
        return [
            'icon' => 'fab fa-wix',
            'name' => 'Wix',
            'color' => '#0c6ebd'
        ];
    }
    // Weebly
    elseif (strpos($referrerLower, 'weebly') !== false) {
        return [
            'icon' => 'fab fa-weebly',
            'name' => 'Weebly',
            'color' => '#3fbaff'
        ];
    }
    // Squarespace
    elseif (strpos($referrerLower, 'squarespace') !== false) {
        return [
            'icon' => 'fab fa-squarespace',
            'name' => 'Squarespace',
            'color' => '#000000'
        ];
    }
    // Apple (Safari, Mail, etc.)
    elseif (strpos($referrerLower, 'apple') !== false || strpos($referrerLower, 'icloud') !== false) {
        return [
            'icon' => 'fab fa-apple',
            'name' => 'Apple',
            'color' => '#000000'
        ];
    }
    // Microsoft (Outlook, Edge, etc.)
    elseif (strpos($referrerLower, 'microsoft') !== false || strpos($referrerLower, 'outlook') !== false || strpos($referrerLower, 'live.com') !== false) {
        return [
            'icon' => 'fab fa-microsoft',
            'name' => 'Microsoft',
            'color' => '#00a4ef'
        ];
    }
    // Amazon
    elseif (strpos($referrerLower, 'amazon') !== false) {
        return [
            'icon' => 'fab fa-amazon',
            'name' => 'Amazon',
            'color' => '#ff9900'
        ];
    }
    // eBay
    elseif (strpos($referrerLower, 'ebay') !== false) {
        return [
            'icon' => 'fab fa-ebay',
            'name' => 'eBay',
            'color' => '#e53238'
        ];
    }
    // Etsy
    elseif (strpos($referrerLower, 'etsy') !== false) {
        return [
            'icon' => 'fab fa-etsy',
            'name' => 'Etsy',
            'color' => '#f1641e'
        ];
    }
    // Airbnb
    elseif (strpos($referrerLower, 'airbnb') !== false) {
        return [
            'icon' => 'fab fa-airbnb',
            'name' => 'Airbnb',
            'color' => '#ff5a5f'
        ];
    }
    // Yelp
    elseif (strpos($referrerLower, 'yelp') !== false) {
        return [
            'icon' => 'fab fa-yelp',
            'name' => 'Yelp',
            'color' => '#d32323'
        ];
    }
    // Foursquare
    elseif (strpos($referrerLower, 'foursquare') !== false) {
        return [
            'icon' => 'fab fa-foursquare',
            'name' => 'Foursquare',
            'color' => '#f94877'
        ];
    }
    // Blogger
    elseif (strpos($referrerLower, 'blogger') !== false) {
        return [
            'icon' => 'fab fa-blogger',
            'name' => 'Blogger',
            'color' => '#ff5722'
        ];
    }
    // Flickr
    elseif (strpos($referrerLower, 'flickr') !== false) {
        return [
            'icon' => 'fab fa-flickr',
            'name' => 'Flickr',
            'color' => '#0063dc'
        ];
    }
    // SoundCloud
    elseif (strpos($referrerLower, 'soundcloud') !== false) {
        return [
            'icon' => 'fab fa-soundcloud',
            'name' => 'SoundCloud',
            'color' => '#ff3300'
        ];
    }
    // Kickstarter
    elseif (strpos($referrerLower, 'kickstarter') !== false) {
        return [
            'icon' => 'fab fa-kickstarter',
            'name' => 'Kickstarter',
            'color' => '#05ce78'
        ];
    }
    // Patreon
    elseif (strpos($referrerLower, 'patreon') !== false) {
        return [
            'icon' => 'fab fa-patreon',
            'name' => 'Patreon',
            'color' => '#ff424d'
        ];
    }
    // Substack
    elseif (strpos($referrerLower, 'substack') !== false) {
        return [
            'icon' => 'fa-newspaper',
            'name' => 'Substack',
            'color' => '#ff6719'
        ];
    }
    // Email clients
    elseif (strpos($referrerLower, 'mail') !== false || strpos($referrerLower, 'email') !== false) {
        return [
            'icon' => 'fa-envelope',
            'name' => 'Email',
            'color' => '#6b7280'
        ];
    }
    else {
        // Generic referral
        $domain = parse_url($referrer, PHP_URL_HOST) ?? $referrer;
        return [
            'icon' => 'fa-globe',
            'name' => $domain,
            'color' => '#6b7280'
        ];
    }
}

function getPageData($type, $view, $website_id) {
    $limit = ($view === 'all') ? 100 : 5;
    
    switch($type) {
        case 'pages':
            // Get ALL page data first, then normalize in PHP
            $rawPages = fetchAll(
                "SELECT 
                    page_url,
                    timespent
                FROM analytics 
                WHERE website_id = ?",
                [$website_id]
            );
            
            // Group by normalized path
            $grouped = [];
            foreach ($rawPages as $row) {
                $normalizedPath = normalizeUrl($row['page_url']);
                
                if (!isset($grouped[$normalizedPath])) {
                    $grouped[$normalizedPath] = [
                        'count' => 0,
                        'total_time' => 0,
                        'bounces' => 0
                    ];
                }
                
                $grouped[$normalizedPath]['count']++;
                $timeSpent = (int)$row['timespent'];
                $grouped[$normalizedPath]['total_time'] += $timeSpent;
                
                if ($timeSpent < 5) {
                    $grouped[$normalizedPath]['bounces']++;
                }
            }
            
            // Calculate stats and format
            $result = [];
            foreach ($grouped as $path => $stats) {
                $avgSeconds = $stats['count'] > 0 ? round($stats['total_time'] / $stats['count']) : 0;
                $bounceRate = $stats['count'] > 0 ? round(($stats['bounces'] / $stats['count']) * 100, 1) : 0;
                $minutes = floor($avgSeconds / 60);
                $seconds = $avgSeconds % 60;
                
                $result[] = [
                    'path' => $path,
                    'views' => $stats['count'],
                    'bounce' => $bounceRate,
                    'avg_time' => "{$minutes}m {$seconds}s",
                    'sort_value' => $stats['count'] // For sorting
                ];
            }
            
            // Sort by view count descending
            usort($result, function($a, $b) {
                return $b['sort_value'] - $a['sort_value'];
            });
            
            // Limit results
            $result = array_slice($result, 0, $limit);
            
            // Format for output
            foreach ($result as &$item) {
                $item['views'] = number_format($item['views']);
                $item['bounce'] = $item['bounce'] . '%';
                unset($item['sort_value']);
            }
            
            return $result;
            
        case 'referrers':
            // Get ALL referrer data first, then normalize in PHP
            $rawReferrers = fetchAll(
                "SELECT 
                    referrer,
                    timespent
                FROM analytics 
                WHERE website_id = ?",
                [$website_id]
            );
            
            // Group by normalized referrer
            $grouped = [];
            foreach ($rawReferrers as $row) {
                $normalizedReferrer = normalizeReferrer($row['referrer']);
                
                if (!isset($grouped[$normalizedReferrer])) {
                    $grouped[$normalizedReferrer] = [
                        'count' => 0,
                        'total_time' => 0,
                        'bounces' => 0
                    ];
                }
                
                $grouped[$normalizedReferrer]['count']++;
                $timeSpent = (int)$row['timespent'];
                $grouped[$normalizedReferrer]['total_time'] += $timeSpent;
                
                if ($timeSpent < 5) {
                    $grouped[$normalizedReferrer]['bounces']++;
                }
            }
            
            // Calculate stats and format
            $result = [];
            foreach ($grouped as $referrer => $stats) {
                $avgSeconds = $stats['count'] > 0 ? round($stats['total_time'] / $stats['count']) : 0;
                $bounceRate = $stats['count'] > 0 ? round(($stats['bounces'] / $stats['count']) * 100, 1) : 0;
                $minutes = floor($avgSeconds / 60);
                $seconds = $avgSeconds % 60;
                
                $referrerInfo = getReferrerInfo($referrer);
                
                $result[] = [
                    'path' => $referrer,
                    'display_name' => $referrerInfo['name'],
                    'icon' => $referrerInfo['icon'],
                    'color' => $referrerInfo['color'],
                    'views' => $stats['count'],
                    'bounce' => $bounceRate,
                    'avg_time' => "{$minutes}m {$seconds}s",
                    'sort_value' => $stats['count']
                ];
            }
            
            // Sort by view count descending
            usort($result, function($a, $b) {
                return $b['sort_value'] - $a['sort_value'];
            });
            
            // Limit results
            $result = array_slice($result, 0, $limit);
            
            // Format for output
            foreach ($result as &$item) {
                $item['views'] = number_format($item['views']);
                $item['bounce'] = $item['bounce'] . '%';
                unset($item['sort_value']);
            }
            
            return $result;
            
        case 'countries':
            // Countries should already be normalized (2-letter codes)
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
            if ($isDetailed) {
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