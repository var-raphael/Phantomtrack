<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once "../includes/functions.php";

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-KEY, Authorization');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ==================== SECURITY & RATE LIMITING ====================

// DDoS Protection: Check request frequency from IP
function checkDDoSProtection($ip_hash) {
    $oneMinuteAgo = date('Y-m-d H:i:s', strtotime('-1 minute'));
    
    // Count requests from this IP in last minute
    $recentRequests = fetchOne(
        "SELECT COUNT(*) as count FROM rate_limits 
         WHERE rate_key = ? AND rate_type = 'ip' AND created_at >= ?",
        ["ratelimit_ip_{$ip_hash}", $oneMinuteAgo]
    )['count'] ?? 0;
    
    // Max 60 requests per minute per IP (1 per second average)
    if ($recentRequests > 60) {
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'error' => 'Too Many Requests',
            'message' => 'Rate limit exceeded. Maximum 60 requests per minute per IP.',
            'retry_after' => 60
        ]);
        exit;
    }
    
    // Log this request
    quickInsert('rate_limits', [
        'rate_key' => "ratelimit_ip_{$ip_hash}",
        'rate_type' => 'ip'
    ]);
}

// Plan-based rate limiting
function checkPlanRateLimit($website_id, $plan_type, $tier) {
    $currentMonth = date('Y-m');
    
    // Get current usage
    $usage = fetchOne(
        "SELECT event_count, req_limit FROM monthly_usage 
         WHERE website_id = ? AND month = ?",
        [$website_id, $currentMonth]
    );
    
    if (!$usage) {
        // Initialize usage for this month if not exists
        $limits = [
            'free' => 10000,      // 10k requests
            'pro' => 30000,       // 30k requests @ $3
            'premium' => 60000,   // 60k requests @ $5
            'enterprise' => 100000, // 100k requests @ $8
            'lifetime' => 999999999 // Unlimited @ $20
        ];
        
        $limit = $limits[$plan_type] ?? 10000;
        
        quickInsert('monthly_usage', [
            'website_id' => $website_id,
            'tracking_id' => 'api_access',
            'month' => $currentMonth,
            'event_count' => 1,
            'req_limit' => $limit
        ]);
        
        return [
            'allowed' => true,
            'current' => 1,
            'limit' => $limit
        ];
    }
    
    $current = (int)$usage['event_count'];
    $limit = (int)$usage['req_limit'];
    
    // Check if unlimited (lifetime plan)
    if ($limit >= 999999999) {
        // Increment counter but always allow
        execQuery(
            "UPDATE monthly_usage SET event_count = event_count + 1 
             WHERE website_id = ? AND month = ?",
            [$website_id, $currentMonth]
        );
        
        return [
            'allowed' => true,
            'current' => $current + 1,
            'limit' => 'unlimited'
        ];
    }
    
    // Check if limit exceeded
    if ($current >= $limit) {
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'error' => 'API Limit Reached',
            'message' => "Monthly API limit of {$limit} requests reached for {$plan_type} plan.",
            'current_usage' => $current,
            'limit' => $limit,
            'plan' => $plan_type,
            'upgrade_url' => 'https://phantomtrack.com/pricing'
        ]);
        exit;
    }
    
    // Increment usage counter
    execQuery(
        "UPDATE monthly_usage SET event_count = event_count + 1 
         WHERE website_id = ? AND month = ?",
        [$website_id, $currentMonth]
    );
    
    return [
        'allowed' => true,
        'current' => $current + 1,
        'limit' => $limit
    ];
}

// Request burst protection: Max 10 requests per second per website
function checkBurstProtection($website_id) {
    $oneSecondAgo = date('Y-m-d H:i:s', strtotime('-1 second'));
    
    $recentRequests = fetchOne(
        "SELECT COUNT(*) as count FROM rate_limits 
         WHERE rate_key = ? AND rate_type = 'website' AND created_at >= ?",
        ["ratelimit_api_web_{$website_id}", $oneSecondAgo]
    )['count'] ?? 0;
    
    if ($recentRequests > 10) {
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'error' => 'Request Burst Limit',
            'message' => 'Too many requests in short time. Maximum 10 requests per second.',
            'retry_after' => 1
        ]);
        exit;
    }
    
    quickInsert('rate_limits', [
        'rate_key' => "ratelimit_api_web_{$website_id}",
        'rate_type' => 'website'
    ]);
}

// Clean old rate limit records (older than 5 minutes)
function cleanupRateLimits() {
    $fiveMinutesAgo = date('Y-m-d H:i:s', strtotime('-5 minutes'));
    execQuery(
        "DELETE FROM rate_limits WHERE created_at < ?",
        [$fiveMinutesAgo]
    );
}

// Run cleanup occasionally (1% chance per request)
if (rand(1, 100) === 1) {
    cleanupRateLimits();
}

// ==================== API KEY VALIDATION ====================

function validateApiKey($apiKey) {
    $isTestKey = strpos($apiKey, 'sk_test_') === 0;
    $isProdKey = strpos($apiKey, 'sk_live_') === 0;
    
    if (!$isTestKey && !$isProdKey) {
        return ['valid' => false, 'message' => 'Invalid API key format'];
    }
    
    $keyData = fetchOne(
        "SELECT ak.*, w.website_id, w.tier, w.plan_type, w.website_name
         FROM api_key ak
         JOIN website w ON ak.website_id = w.website_id
         WHERE " . ($isTestKey ? "ak.test_key = ?" : "ak.production_key = ?"),
        [$apiKey]
    );
    
    if (!$keyData) {
        return ['valid' => false, 'message' => 'API key not found'];
    }
    
    return [
        'valid' => true,
        'website_id' => $keyData['website_id'],
        'user_id' => $keyData['user_id'],
        'tier' => $keyData['tier'],
        'plan_type' => $keyData['plan_type'],
        'website_name' => $keyData['website_name'],
        'is_test' => $isTestKey
    ];
}

// ==================== DUMMY DATA GENERATOR ====================

function getDummyData($endpoint, $type, $limit) {
    $dummyData = [
        'stats' => [
            'unique_visitors' => [
                'label' => 'Unique Visitors',
                'value' => 1247,
                'formatted' => '1,247'
            ],
            'pageviews' => [
                'label' => 'Page Views',
                'value' => 3891,
                'formatted' => '3,891'
            ],
            'live_visitors' => [
                'label' => 'Live Visitors',
                'value' => 12,
                'formatted' => '12'
            ],
            'bounce_rate' => [
                'label' => 'Bounce Rate',
                'value' => 42.3,
                'formatted' => '42.3%'
            ]
        ],
        'pages' => [
            ['path' => '/home', 'views' => 523, 'unique_visitors' => 412, 'avg_time_seconds' => 145, 'avg_time_formatted' => '2m 25s'],
            ['path' => '/products', 'views' => 389, 'unique_visitors' => 301, 'avg_time_seconds' => 210, 'avg_time_formatted' => '3m 30s'],
            ['path' => '/about', 'views' => 267, 'unique_visitors' => 198, 'avg_time_seconds' => 89, 'avg_time_formatted' => '1m 29s'],
            ['path' => '/contact', 'views' => 156, 'unique_visitors' => 134, 'avg_time_seconds' => 65, 'avg_time_formatted' => '1m 5s'],
            ['path' => '/blog', 'views' => 145, 'unique_visitors' => 112, 'avg_time_seconds' => 320, 'avg_time_formatted' => '5m 20s']
        ],
        'referrers' => [
            ['source' => 'google.com', 'visits' => 845, 'unique_visitors' => 678],
            ['source' => 'direct', 'visits' => 523, 'unique_visitors' => 445],
            ['source' => 'facebook.com', 'visits' => 267, 'unique_visitors' => 201],
            ['source' => 'twitter.com', 'visits' => 134, 'unique_visitors' => 98],
            ['source' => 'linkedin.com', 'visits' => 89, 'unique_visitors' => 67]
        ],
        'countries' => [
            ['country_code' => 'US', 'tier' => 'tier1', 'visits' => 1245, 'unique_visitors' => 892],
            ['country_code' => 'GB', 'tier' => 'tier1', 'visits' => 456, 'unique_visitors' => 334],
            ['country_code' => 'CA', 'tier' => 'tier1', 'visits' => 334, 'unique_visitors' => 267],
            ['country_code' => 'DE', 'tier' => 'tier1', 'visits' => 223, 'unique_visitors' => 178],
            ['country_code' => 'FR', 'tier' => 'tier1', 'visits' => 167, 'unique_visitors' => 134]
        ],
        'devices' => [
            ['device' => 'mobile', 'count' => 1567, 'percentage' => 52.3],
            ['device' => 'desktop', 'count' => 1234, 'percentage' => 41.2],
            ['device' => 'tablet', 'count' => 195, 'percentage' => 6.5]
        ],
        'browsers' => [
            ['browser' => 'Chrome', 'count' => 1678, 'percentage' => 56.1],
            ['browser' => 'Safari', 'count' => 723, 'percentage' => 24.2],
            ['browser' => 'Firefox', 'count' => 345, 'percentage' => 11.5],
            ['browser' => 'Edge', 'count' => 178, 'percentage' => 5.9],
            ['browser' => 'Other', 'count' => 72, 'percentage' => 2.3]
        ],
        'events' => [
            ['event_name' => 'button_click', 'count' => 567],
            ['event_name' => 'add_to_cart', 'count' => 234],
            ['event_name' => 'form_submit', 'count' => 189],
            ['event_name' => 'video_play', 'count' => 156],
            ['event_name' => 'download', 'count' => 89]
        ],
        'usage' => [
            'current_usage' => 2347,
            'limit' => 10000,
            'remaining' => 7653,
            'percentage_used' => 23.5,
            'is_unlimited' => false
        ]
    ];
    
    switch($endpoint) {
        case 'stats':
            return $type ? ($dummyData['stats'][$type] ?? []) : $dummyData['stats'];
        case 'pages':
            return array_slice($dummyData['pages'], 0, $limit);
        case 'referrers':
            return array_slice($dummyData['referrers'], 0, $limit);
        case 'countries':
            return array_slice($dummyData['countries'], 0, $limit);
        case 'devices':
            return $dummyData['devices'];
        case 'browsers':
            return $dummyData['browsers'];
        case 'events':
            return array_slice($dummyData['events'], 0, $limit);
        case 'usage':
            return $dummyData['usage'];
        case 'all':
            return [
                'statistics' => $dummyData['stats'],
                'top_pages' => array_slice($dummyData['pages'], 0, 10),
                'top_referrers' => array_slice($dummyData['referrers'], 0, 10),
                'top_countries' => array_slice($dummyData['countries'], 0, 10),
                'devices' => $dummyData['devices'],
                'browsers' => $dummyData['browsers'],
                'recent_events' => array_slice($dummyData['events'], 0, 10),
                'usage' => $dummyData['usage']
            ];
        default:
            return [];
    }
}

// ==================== DATA FUNCTIONS (Real Data) ====================

function getStatistics($website_id, $type, $dateRange) {
    $start = $dateRange['start'];
    $end = $dateRange['end'];
    
    switch($type) {
        case 'unique':
            $current = fetchOne(
                "SELECT COUNT(DISTINCT ip_hash) as count FROM analytics 
                 WHERE website_id = ? AND created_at BETWEEN ? AND ?",
                [$website_id, $start, $end]
            )['count'] ?? 0;
            
            return [
                'label' => 'Unique Visitors',
                'value' => (int)$current,
                'formatted' => number_format($current)
            ];
            
        case 'pageviews':
            $current = fetchOne(
                "SELECT COUNT(*) as count FROM analytics 
                 WHERE website_id = ? AND created_at BETWEEN ? AND ?",
                [$website_id, $start, $end]
            )['count'] ?? 0;
            
            return [
                'label' => 'Page Views',
                'value' => (int)$current,
                'formatted' => number_format($current)
            ];
            
        case 'live':
            $current = fetchOne(
                "SELECT COUNT(DISTINCT session_id) as count 
                 FROM live_user_sessions 
                 WHERE website_id = ? AND last_ping >= DATE_SUB(NOW(), INTERVAL 2 MINUTE)",
                [$website_id]
            )['count'] ?? 0;
            
            return [
                'label' => 'Live Visitors',
                'value' => (int)$current,
                'formatted' => number_format($current)
            ];
            
        case 'bounce':
            $total = fetchOne(
                "SELECT COUNT(DISTINCT ip_hash) as count FROM analytics 
                 WHERE website_id = ? AND created_at BETWEEN ? AND ?",
                [$website_id, $start, $end]
            )['count'] ?? 1;
            
            $bounced = fetchOne(
                "SELECT COUNT(*) as count FROM (
                    SELECT ip_hash, COUNT(*) as page_count
                    FROM analytics 
                    WHERE website_id = ? AND created_at BETWEEN ? AND ?
                    GROUP BY ip_hash
                    HAVING page_count = 1
                ) as bounces",
                [$website_id, $start, $end]
            )['count'] ?? 0;
            
            $rate = $total > 0 ? round(($bounced / $total) * 100, 1) : 0;
            
            return [
                'label' => 'Bounce Rate',
                'value' => $rate,
                'formatted' => $rate . '%'
            ];
            
        default:
            return [
                'unique_visitors' => getStatistics($website_id, 'unique', $dateRange),
                'pageviews' => getStatistics($website_id, 'pageviews', $dateRange),
                'live_visitors' => getStatistics($website_id, 'live', $dateRange),
                'bounce_rate' => getStatistics($website_id, 'bounce', $dateRange)
            ];
    }
}

function getPageData($website_id, $view, $limit, $dateRange) {
    $start = $dateRange['start'];
    $end = $dateRange['end'];
    
    $pages = fetchAll(
        "SELECT page_url, 
                COUNT(*) as views,
                COUNT(DISTINCT ip_hash) as unique_visitors,
                AVG(CAST(timespent AS UNSIGNED)) as avg_time
         FROM analytics 
         WHERE website_id = ? AND created_at BETWEEN ? AND ?
         GROUP BY page_url 
         ORDER BY views DESC 
         LIMIT {$limit}",
        [$website_id, $start, $end]
    );
    
    $formatted = [];
    foreach ($pages as $page) {
        $avgTime = round($page['avg_time'] ?? 0);
        $minutes = floor($avgTime / 60);
        $seconds = $avgTime % 60;
        
        $formatted[] = [
            'path' => $page['page_url'],
            'views' => (int)$page['views'],
            'unique_visitors' => (int)$page['unique_visitors'],
            'avg_time_seconds' => $avgTime,
            'avg_time_formatted' => "{$minutes}m {$seconds}s"
        ];
    }
    
    return $formatted;
}

function getReferrerData($website_id, $view, $limit, $dateRange) {
    $start = $dateRange['start'];
    $end = $dateRange['end'];
    
    $referrers = fetchAll(
        "SELECT referrer, 
                COUNT(*) as visits,
                COUNT(DISTINCT ip_hash) as unique_visitors
         FROM analytics 
         WHERE website_id = ? AND created_at BETWEEN ? AND ?
         GROUP BY referrer 
         ORDER BY visits DESC 
         LIMIT {$limit}",
        [$website_id, $start, $end]
    );
    
    $formatted = [];
    foreach ($referrers as $ref) {
        $formatted[] = [
            'source' => $ref['referrer'],
            'visits' => (int)$ref['visits'],
            'unique_visitors' => (int)$ref['unique_visitors']
        ];
    }
    
    return $formatted;
}

function getCountryData($website_id, $view, $limit, $dateRange) {
    $start = $dateRange['start'];
    $end = $dateRange['end'];
    
    $countries = fetchAll(
        "SELECT country, country_tier,
                COUNT(*) as visits,
                COUNT(DISTINCT ip_hash) as unique_visitors
         FROM analytics 
         WHERE website_id = ? AND created_at BETWEEN ? AND ?
         GROUP BY country, country_tier 
         ORDER BY visits DESC 
         LIMIT {$limit}",
        [$website_id, $start, $end]
    );
    
    $formatted = [];
    foreach ($countries as $country) {
        $formatted[] = [
            'country_code' => $country['country'],
            'tier' => $country['country_tier'],
            'visits' => (int)$country['visits'],
            'unique_visitors' => (int)$country['unique_visitors']
        ];
    }
    
    return $formatted;
}

function getDeviceData($website_id, $dateRange) {
    $start = $dateRange['start'];
    $end = $dateRange['end'];
    
    $devices = fetchAll(
        "SELECT device_type, COUNT(*) as count
         FROM analytics 
         WHERE website_id = ? AND created_at BETWEEN ? AND ?
         GROUP BY device_type",
        [$website_id, $start, $end]
    );
    
    $total = array_sum(array_column($devices, 'count'));
    
    $formatted = [];
    foreach ($devices as $device) {
        $percentage = $total > 0 ? round(($device['count'] / $total) * 100, 1) : 0;
        $formatted[] = [
            'device' => $device['device_type'],
            'count' => (int)$device['count'],
            'percentage' => $percentage
        ];
    }
    
    return $formatted;
}

function getBrowserData($website_id, $dateRange) {
    $start = $dateRange['start'];
    $end = $dateRange['end'];
    
    $browsers = fetchAll(
        "SELECT browser_type, COUNT(*) as count
         FROM analytics 
         WHERE website_id = ? AND created_at BETWEEN ? AND ?
         GROUP BY browser_type
         ORDER BY count DESC
         LIMIT 10",
        [$website_id, $start, $end]
    );
    
    $total = array_sum(array_column($browsers, 'count'));
    
    $formatted = [];
    foreach ($browsers as $browser) {
        $percentage = $total > 0 ? round(($browser['count'] / $total) * 100, 1) : 0;
        $formatted[] = [
            'browser' => $browser['browser_type'],
            'count' => (int)$browser['count'],
            'percentage' => $percentage
        ];
    }
    
    return $formatted;
}

function getCustomEvents($website_id, $limit, $dateRange) {
    $start = $dateRange['start'];
    $end = $dateRange['end'];
    
    $events = fetchAll(
        "SELECT event_name, COUNT(*) as count
         FROM custom_events 
         WHERE website_id = ? AND created_at BETWEEN ? AND ?
         GROUP BY event_name
         ORDER BY count DESC
         LIMIT {$limit}",
        [$website_id, $start, $end]
    );
    
    $formatted = [];
    foreach ($events as $event) {
        $formatted[] = [
            'event_name' => $event['event_name'],
            'count' => (int)$event['count']
        ];
    }
    
    return $formatted;
}

function getUsageData($website_id) {
    $currentMonth = date('Y-m');
    
    $usage = fetchOne(
        "SELECT event_count, req_limit FROM monthly_usage 
         WHERE website_id = ? AND month = ?",
        [$website_id, $currentMonth]
    );
    
    $current = $usage['event_count'] ?? 0;
    $limit = $usage['req_limit'] ?? 10000;
    $isUnlimited = $limit >= 999999999;
    
    $remaining = $isUnlimited ? 'unlimited' : max(0, $limit - $current);
    $percentage = $isUnlimited ? 0 : ($limit > 0 ? round(($current / $limit) * 100, 1) : 0);
    
    return [
        'current_usage' => (int)$current,
        'limit' => $isUnlimited ? 'unlimited' : (int)$limit,
        'remaining' => $remaining,
        'percentage_used' => $percentage,
        'is_unlimited' => $isUnlimited
    ];
}

function getAllData($website_id, $dateRange) {
    return [
        'statistics' => getStatistics($website_id, '', $dateRange),
        'top_pages' => getPageData($website_id, 'default', 10, $dateRange),
        'top_referrers' => getReferrerData($website_id, 'default', 10, $dateRange),
        'top_countries' => getCountryData($website_id, 'default', 10, $dateRange),
        'devices' => getDeviceData($website_id, $dateRange),
        'browsers' => getBrowserData($website_id, $dateRange),
        'recent_events' => getCustomEvents($website_id, 10, $dateRange),
        'usage' => getUsageData($website_id)
    ];
}

// Calculate date range
function getDateRange($period, $startDate, $endDate) {
    if ($startDate && $endDate) {
        return [
            'start' => date('Y-m-d H:i:s', strtotime($startDate)),
            'end' => date('Y-m-d H:i:s', strtotime($endDate))
        ];
    }
    
    $end = date('Y-m-d 23:59:59');
    
    switch($period) {
        case '7d':
            $start = date('Y-m-d 00:00:00', strtotime('-7 days'));
            break;
        case '90d':
            $start = date('Y-m-d 00:00:00', strtotime('-90 days'));
            break;
        case '1y':
            $start = date('Y-m-d 00:00:00', strtotime('-1 year'));
            break;
        case '30d':
        default:
            $start = date('Y-m-d 00:00:00', strtotime('-30 days'));
            break;
    }
    
    return ['start' => $start, 'end' => $end];
}

// ==================== MAIN REQUEST HANDLING ====================

// Get API key from request
$apiKey = null;
if (isset($_SERVER['HTTP_X_API_KEY'])) {
    $apiKey = trim($_SERVER['HTTP_X_API_KEY']);
} elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
    $auth = trim($_SERVER['HTTP_AUTHORIZATION']);
    if (strpos($auth, 'Bearer ') === 0) {
        $apiKey = substr($auth, 7);
    }
} elseif (isset($_GET['api_key'])) {
    $apiKey = trim($_GET['api_key']);
}

if (!$apiKey) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized',
        'message' => 'API key is required. Provide via X-API-KEY header, Authorization: Bearer header, or api_key parameter.'
    ]);
    exit;
}

// Validate API key
$validation = validateApiKey($apiKey);

if (!$validation['valid']) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => 'Forbidden',
        'message' => $validation['message']
    ]);
    exit;
}

// Extract validated data
$website_id = $validation['website_id'];
$user_id = $validation['user_id'];
$tier = $validation['tier'];
$plan_type = $validation['plan_type'];
$is_test = $validation['is_test'];

// Apply security checks (skip for test keys to allow unlimited testing)
if (!$is_test) {
    $ip_hash = ipHash();
    
    // 1. DDoS Protection (60 req/min per IP)
    checkDDoSProtection($ip_hash);
    
    // 2. Burst Protection (10 req/sec per website)
    checkBurstProtection($website_id);
    
    // 3. Plan-based rate limiting
    $rateLimitStatus = checkPlanRateLimit($website_id, $plan_type, $tier);
}

// Get endpoint and parameters
$endpoint = $_GET['endpoint'] ?? 'stats';
$type = $_GET['type'] ?? '';
$view = $_GET['view'] ?? 'default';
$limit = min((int)($_GET['limit'] ?? 10), 100);
$period = $_GET['period'] ?? '30d';
$startDate = $_GET['start_date'] ?? null;
$endDate = $_GET['end_date'] ?? null;

$dateRange = getDateRange($period, $startDate, $endDate);

// Route to appropriate handler
try {
    // Use dummy data for test keys, real data for production keys
    if ($is_test) {
        $response = getDummyData($endpoint, $type, $limit);
        $ai_review = [
            [
                'title' => 'Test Mode Active',
                'message' => 'Using dummy data for development',
                'type' => 'info'
            ],
            [
                'title' => 'Traffic Growth',
                'message' => 'Showing sample analytics data',
                'type' => 'info'
            ],
            [
                'title' => 'Key Action',
                'message' => 'Switch to production key for real data',
                'type' => 'info'
            ]
        ];
    } else {
        // Real data queries
        switch($endpoint) {
            case 'stats':
                $response = getStatistics($website_id, $type, $dateRange);
                break;
            case 'pages':
                $response = getPageData($website_id, $view, $limit, $dateRange);
                break;
            case 'referrers':
                $response = getReferrerData($website_id, $view, $limit, $dateRange);
                break;
            case 'countries':
                $response = getCountryData($website_id, $view, $limit, $dateRange);
                break;
            case 'devices':
                $response = getDeviceData($website_id, $dateRange);
                break;
            case 'browsers':
                $response = getBrowserData($website_id, $dateRange);
                break;
            case 'events':
                $response = getCustomEvents($website_id, $limit, $dateRange);
                break;
            case 'usage':
                $response = getUsageData($website_id);
                break;
            case 'all':
                $response = getAllData($website_id, $dateRange);
                break;
            default:
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'error' => 'Not Found',
                    'message' => 'Invalid endpoint. Available: stats, pages, referrers, countries, devices, browsers, events, usage, all'
                ]);
                exit;
        }
        
        // Get AI review for production data
        $aiReviewData = fetchOne(
            "SELECT review FROM ai_review WHERE website_id = ? ORDER BY date DESC LIMIT 1",
            [$website_id]
        );
        
        $ai_review = [];
        if ($aiReviewData && $aiReviewData['review']) {
            $ai_review = json_decode($aiReviewData['review'], true) ?? [];
        }
    }
    
    // Build response
    $responseData = [
        'success' => true,
        'data' => $response,
        'ai_review' => $ai_review,
        'meta' => [
            'website_id' => $website_id,
            'tier' => $tier,
            'plan' => $plan_type,
            'is_test_mode' => $is_test,
            'period' => $period,
            'date_range' => $dateRange
        ]
    ];
    
    // Add rate limit info for production keys
    if (!$is_test && isset($rateLimitStatus)) {
        $responseData['rate_limit'] = [
            'current_usage' => $rateLimitStatus['current'],
            'limit' => $rateLimitStatus['limit'],
            'remaining' => is_numeric($rateLimitStatus['limit']) 
                ? ($rateLimitStatus['limit'] - $rateLimitStatus['current']) 
                : 'unlimited'
        ];
    }
    
    echo json_encode($responseData, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    error_log("API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal Server Error',
        'message' => 'An error occurred processing your request',
        'debug' => $e->getMessage() // Remove this in production
    ]);
}
?>