<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/vendor/autoload.php';
include "includes/functions.php";

use WhichBrowser\Parser;

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// ===========================================
// DDOS PROTECTION & RATE LIMITING
// ===========================================

$clientIp = getIP();
$ipHash = hash('sha256', $clientIp . 'phantom_salt_2025');

// 1. CHECK IP RATE LIMIT (100 requests per minute per IP)
if (!checkIPRateLimit($ipHash, 100, 60)) {
    http_response_code(429);
    echo json_encode(['error' => 'Rate limit exceeded. Try again later.']);
    exit;
}

// 2. GET AND VALIDATE INPUT
$rawData = file_get_contents('php://input');
$data = json_decode($rawData, true);

if (!$data || !is_array($data)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

// 3. VALIDATE PAYLOAD SIZE (prevent large payloads)
if (strlen($rawData) > 50000) { // 50KB max
    http_response_code(413);
    echo json_encode(['error' => 'Payload too large']);
    exit;
}

// 4. DETECT SUSPICIOUS PATTERNS
if (isSuspiciousRequest($data)) {
    http_response_code(403);
    echo json_encode(['error' => 'Suspicious activity detected']);
    exit;
}

// Validate required fields
if (!isset($data['trackid']) || !isset($data['event_type'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit;
}

// Sanitize and validate trackid format
$trackId = clean($data['trackid']);
if (!preg_match('/^track_[a-z0-9]{20,30}$/i', $trackId)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid tracking ID format']);
    exit;
}

$eventType = clean($data['event_type']);

// Validate event type against whitelist
$validEventTypes = ['pageview', 'heartbeat', 'pageview_end', 'leave', 'custom_event'];
if (!in_array($eventType, $validEventTypes)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid event type']);
    exit;
}

// Get website info from trackid
$websiteInfo = getWebsiteByTrackId($trackId);

if (!$websiteInfo) {
    http_response_code(404);
    echo json_encode(['error' => 'Invalid tracking ID']);
    exit;
}

$userId = (int)$websiteInfo['user_id'];
$websiteId = (int)$websiteInfo['website_id'];
$websiteName = $websiteInfo['website_name'];
$planType = $websiteInfo['plan_type'] ?? 'free';

// CRITICAL: Verify trackid matches the website
if ($trackId !== $websiteInfo['track_id']) {
    http_response_code(403);
    echo json_encode(['error' => 'Tracking ID mismatch']);
    error_log("Security: Tracking ID mismatch for website_id={$websiteId}");
    exit;
}

// ===========================================
// ORIGIN VERIFICATION - PREVENT UNAUTHORIZED USAGE
// ===========================================

// Get the origin/referer from the request
$requestOrigin = '';
$requestReferer = '';

// Try to get origin from headers (most reliable)
if (isset($_SERVER['HTTP_ORIGIN'])) {
    $requestOrigin = $_SERVER['HTTP_ORIGIN'];
} elseif (isset($_SERVER['HTTP_REFERER'])) {
    $requestReferer = $_SERVER['HTTP_REFERER'];
    // Extract origin from referer
    $parsed = parse_url($requestReferer);
    if ($parsed && isset($parsed['scheme']) && isset($parsed['host'])) {
        $requestOrigin = $parsed['scheme'] . '://' . $parsed['host'];
        if (isset($parsed['port']) && !in_array($parsed['port'], [80, 443])) {
            $requestOrigin .= ':' . $parsed['port'];
        }
    }
}

// If no origin/referer, check if it's from page_url in the payload
if (empty($requestOrigin) && isset($data['page_url'])) {
    $parsed = parse_url($data['page_url']);
    if ($parsed && isset($parsed['scheme']) && isset($parsed['host'])) {
        $requestOrigin = $parsed['scheme'] . '://' . $parsed['host'];
        if (isset($parsed['port']) && !in_array($parsed['port'], [80, 443])) {
            $requestOrigin .= ':' . $parsed['port'];
        }
    }
}

// Normalize the authorized website URL
$authorizedWebsite = normalizeWebsiteUrl($websiteName);

// Verify the origin matches the authorized website
if (!empty($requestOrigin) && !empty($authorizedWebsite)) {
    $normalizedRequestOrigin = normalizeWebsiteUrl($requestOrigin);
    
    if ($normalizedRequestOrigin !== $authorizedWebsite) {
        http_response_code(403);
        echo json_encode([
            'error' => 'Unauthorized origin',
            'message' => 'This tracking ID can only be used on ' . $authorizedWebsite,
            'detected_origin' => $normalizedRequestOrigin
        ]);
        error_log("Security: Unauthorized origin detected. Expected: {$authorizedWebsite}, Got: {$normalizedRequestOrigin}, Website ID: {$websiteId}");
        exit;
    }
}

// Additional check: If we have page_url, verify its domain matches
if (isset($data['page_url']) && !empty($data['page_url'])) {
    $pageUrlDomain = extractDomain($data['page_url']);
    $authorizedDomain = extractDomain($authorizedWebsite);
    
    if (!empty($pageUrlDomain) && !empty($authorizedDomain) && $pageUrlDomain !== $authorizedDomain) {
        http_response_code(403);
        echo json_encode([
            'error' => 'Domain mismatch',
            'message' => 'Page URL domain does not match authorized website',
            'authorized' => $authorizedDomain,
            'detected' => $pageUrlDomain
        ]);
        error_log("Security: Domain mismatch. Expected: {$authorizedDomain}, Got: {$pageUrlDomain}, Website ID: {$websiteId}");
        exit;
    }
}

// ============================================
// AUTOMATIC MONTH ROLLOVER & USAGE CHECK
// ============================================

// Ensure current month usage record exists and get it
$usageData = ensureCurrentMonthUsage($websiteId, $planType);

$currentUsage = (int)$usageData['event_count'];
$usageLimit = (int)$usageData['req_limit'];

// Determine if this is "unlimited" (lifetime plan with 300k limit)
$isLifetimePlan = ($planType === 'lifetime' && $usageLimit >= 300000);

// Check if limit reached
if ($currentUsage >= $usageLimit) {
    http_response_code(429);
    echo json_encode([
        'error' => 'Monthly request limit exceeded',
        'limit' => $usageLimit,
        'used' => $currentUsage,
        'plan' => $planType,
        'message' => $isLifetimePlan 
            ? 'Lifetime plan monthly limit of 300k requests reached. Resets on ' . date('F 1, Y', strtotime('first day of next month'))
            : 'Monthly request limit exceeded. Please upgrade your plan to continue tracking.',
        'reset_date' => date('Y-m-01', strtotime('first day of next month'))
    ]);
    error_log("Usage limit exceeded for website_id={$websiteId}, plan={$planType}: {$currentUsage}/{$usageLimit}");
    exit;
}

// 5. CHECK SESSION RATE LIMIT (30 requests per minute per session)
$sessionId = isset($data['session_id']) ? clean($data['session_id']) : null;
if ($sessionId) {
    // Validate session ID format
    if (!preg_match('/^sess_[a-z0-9_]{10,50}$/i', $sessionId)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid session ID format']);
        exit;
    }
    
    if (!checkSessionRateLimit($sessionId, 30, 60)) {
        http_response_code(429);
        echo json_encode(['error' => 'Too many requests from this session']);
        exit;
    }
}

// 6. CHECK WEBSITE-SPECIFIC RATE LIMIT (1000 events per hour per website)
if (!checkWebsiteRateLimit($websiteId, 1000, 3600)) {
    http_response_code(429);
    echo json_encode(['error' => 'Website event quota exceeded']);
    exit;
}

// Sanitize URL fields
$pageUrl = isset($data['page_url']) ? filter_var($data['page_url'], FILTER_SANITIZE_URL) : '';
$referrer = isset($data['referrer']) ? filter_var($data['referrer'], FILTER_SANITIZE_URL) : 'direct';

// Validate URLs are not too long
if (strlen($pageUrl) > 2048 || strlen($referrer) > 2048) {
    http_response_code(400);
    echo json_encode(['error' => 'URL too long']);
    exit;
}

// Get user agent
$userAgent = isset($data['user_agent']) ? substr(clean($data['user_agent']), 0, 500) : 
             (isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 500) : '');

// ADVANCED BROWSER DETECTION using WhichBrowser
$parser = new Parser($userAgent);

$browserType = $parser->browser->name ?? 'Unknown';
$browserVersion = $parser->browser->version->value ?? '';
$engineType = $parser->engine->name ?? 'Unknown';
$osName = $parser->os->name ?? 'Unknown';

// Device type from library
$deviceType = 'desktop';
if ($parser->isType('mobile')) {
    $deviceType = 'mobile';
} elseif ($parser->isType('tablet')) {
    $deviceType = 'tablet';
} elseif ($parser->isType('desktop')) {
    $deviceType = 'desktop';
} elseif ($parser->isType('television')) {
    $deviceType = 'tv';
} elseif ($parser->isType('gaming')) {
    $deviceType = 'gaming';
}

// Override device type if it was sent from JS (validate against whitelist)
if (isset($data['device_type'])) {
    $allowedDevices = ['mobile', 'tablet', 'desktop', 'tv', 'gaming', 'unknown'];
    if (in_array($data['device_type'], $allowedDevices)) {
        $deviceType = $data['device_type'];
    }
}

$isActiveFlag = isset($data['is_active']) ? (bool)$data['is_active'] : true;
$timeSpent = isset($data['timespent']) ? max(0, min((int)$data['timespent'], 86400)) : 0; // Max 24 hours

// Get GeoIP data
$geoData = getGeoIPData($clientIp);
$country = $geoData['country'];
$countryTier = $geoData['country_tier'];

// Process based on event type
try {
    switch ($eventType) {
        case 'pageview':
            handlePageView($websiteId, $pageUrl, $sessionId);
            break;
        
        case 'heartbeat':
            handleHeartbeat($websiteId, $pageUrl, $sessionId, $isActiveFlag);
            break;
        
        case 'pageview_end':
            handlePageViewEnd($userId, $websiteId, $pageUrl, $referrer, $country, $countryTier, $deviceType, $browserType, $ipHash, $timeSpent);
            break;
        
        case 'leave':
            handleLeave($websiteId, $sessionId);
            break;
            
        case 'custom_event':
            handleCustomEvent($userId, $websiteId, $pageUrl, $country, $deviceType, $browserType, $sessionId, $data);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid event type']);
            exit;
    }
} catch (Exception $e) {
    error_log("Event processing error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
    exit;
}

// Calculate remaining requests
$remainingRequests = max(0, $usageLimit - $currentUsage - 1);
$usagePercentage = $usageLimit > 0 ? round((($currentUsage + 1) / $usageLimit) * 100, 1) : 0;

echo json_encode([
    'success' => true, 
    'event' => $eventType,
    'usage' => [
        'current' => $currentUsage + 1,
        'limit' => $usageLimit,
        'remaining' => $remainingRequests,
        'percentage' => $usagePercentage,
        'plan' => $planType,
        'is_lifetime' => $isLifetimePlan,
        'reset_date' => date('Y-m-01', strtotime('first day of next month'))
    ],
    'detected' => [
        'browser' => $browserType,
        'browser_version' => $browserVersion,
        'engine' => $engineType,
        'os' => $osName,
        'device' => $deviceType
    ]
]);

// Periodic cleanup: 5% chance on each request to prevent table bloat
if (rand(1, 20) === 1) {
    cleanupRateLimits();
}

exit;

// ============= ORIGIN VERIFICATION HELPER FUNCTIONS =============

/**
 * Normalize website URL for comparison
 * Converts various formats to a standard scheme://domain format
 */
function normalizeWebsiteUrl($url) {
    if (empty($url)) {
        return '';
    }
    
    // Remove whitespace
    $url = trim($url);
    
    // If no scheme, add https://
    if (!preg_match('#^https?://#i', $url)) {
        $url = 'https://' . $url;
    }
    
    // Parse the URL
    $parsed = parse_url($url);
    
    if (!$parsed || !isset($parsed['host'])) {
        return '';
    }
    
    // Remove www. for consistency
    $host = preg_replace('/^www\./i', '', $parsed['host']);
    
    // Build normalized URL (scheme + host, no port for 80/443)
    $scheme = isset($parsed['scheme']) ? strtolower($parsed['scheme']) : 'https';
    $normalized = $scheme . '://' . strtolower($host);
    
    // Add port if non-standard
    if (isset($parsed['port'])) {
        if (($scheme === 'http' && $parsed['port'] != 80) || 
            ($scheme === 'https' && $parsed['port'] != 443)) {
            $normalized .= ':' . $parsed['port'];
        }
    }
    
    return $normalized;
}

/**
 * Extract just the domain from a URL
 */
function extractDomain($url) {
    if (empty($url)) {
        return '';
    }
    
    $normalized = normalizeWebsiteUrl($url);
    $parsed = parse_url($normalized);
    
    if (!$parsed || !isset($parsed['host'])) {
        return '';
    }
    
    // Remove www. for consistency
    return preg_replace('/^www\./i', '', strtolower($parsed['host']));
}

// ============= AUTOMATIC MONTH ROLLOVER =============

/**
 * Ensures current month usage record exists with correct limits
 * Handles automatic month rollover, old record cleanup, and plan changes
 */
function ensureCurrentMonthUsage($websiteId, $planType) {
    $currentMonth = date('Y-m');
    
    // Plan limits
    $planLimits = [
        'free' => 10000,
        'pro' => 30000,
        'premium' => 60000,
        'enterprise' => 100000,
        'lifetime' => 300000
    ];
    $correctLimit = $planLimits[$planType] ?? 10000;
    
    // Get track_id
    $websiteData = fetchOne(
        "SELECT track_id FROM website WHERE website_id = ?",
        [$websiteId]
    );
    $trackId = $websiteData['track_id'] ?? '';
    
    // Check if current month exists
    $currentRecord = fetchOne(
        "SELECT usage_id, event_count, req_limit FROM monthly_usage 
         WHERE website_id = ? AND month = ?",
        [$websiteId, $currentMonth]
    );
    
    if (!$currentRecord) {
        // Delete old months (keep DB clean)
        execQuery(
            "DELETE FROM monthly_usage WHERE website_id = ? AND month < ?",
            [$websiteId, $currentMonth]
        );
        
        // Create new month record with correct limit
        quickInsert('monthly_usage', [
            'website_id' => $websiteId,
            'tracking_id' => $trackId,
            'month' => $currentMonth,
            'event_count' => 0,
            'req_limit' => $correctLimit
        ]);
        
        error_log("✓ New month created: website_id={$websiteId}, month={$currentMonth}, plan={$planType}, limit={$correctLimit}");
        
        return ['event_count' => 0, 'req_limit' => $correctLimit];
    } else {
        // Month exists - check if limit matches current plan (handles upgrades/downgrades)
        if ($currentRecord['req_limit'] != $correctLimit) {
            execQuery(
                "UPDATE monthly_usage SET req_limit = ? WHERE usage_id = ?",
                [$correctLimit, $currentRecord['usage_id']]
            );
            error_log("✓ Limit updated: website_id={$websiteId}, old={$currentRecord['req_limit']}, new={$correctLimit}");
            $currentRecord['req_limit'] = $correctLimit;
        }
        
        return $currentRecord;
    }
}

// ============= RATE LIMITING FUNCTIONS WITH AUTO-CLEANUP =============

/**
 * Clean up old rate limit entries (run periodically)
 * This prevents the table from growing indefinitely
 */
function cleanupRateLimits() {
    try {
        // Delete all entries older than 1 hour (max window we use)
        execQuery(
            "DELETE FROM rate_limits WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)"
        );
    } catch (Exception $e) {
        error_log("Cleanup error: " . $e->getMessage());
    }
}

/**
 * Check IP-based rate limit
 */
function checkIPRateLimit($ipHash, $maxRequests, $windowSeconds) {
    $key = 'ratelimit_ip_' . substr($ipHash, 0, 64); // Limit key length
    $cutoffTime = date('Y-m-d H:i:s', time() - $windowSeconds);
    
    try {
        // Clean old entries for this specific key
        execQuery(
            "DELETE FROM rate_limits WHERE rate_key = ? AND created_at < ?",
            [$key, $cutoffTime]
        );
        
        // Count recent requests
        $count = fetchOne(
            "SELECT COUNT(*) as total FROM rate_limits WHERE rate_key = ? AND created_at >= ?",
            [$key, $cutoffTime]
        );
        
        if ($count && $count['total'] >= $maxRequests) {
            return false;
        }
        
        // Log this request
        quickInsert('rate_limits', [
            'rate_key' => $key,
            'rate_type' => 'ip'
        ]);
        
        return true;
    } catch (Exception $e) {
        error_log("Rate limit check error: " . $e->getMessage());
        return true; // Allow on error to prevent blocking legitimate traffic
    }
}

/**
 * Check session-based rate limit
 */
function checkSessionRateLimit($sessionId, $maxRequests, $windowSeconds) {
    $key = 'ratelimit_session_' . substr($sessionId, 0, 64);
    $cutoffTime = date('Y-m-d H:i:s', time() - $windowSeconds);
    
    try {
        // Clean old entries for this specific key
        execQuery(
            "DELETE FROM rate_limits WHERE rate_key = ? AND created_at < ?",
            [$key, $cutoffTime]
        );
        
        // Count recent requests
        $count = fetchOne(
            "SELECT COUNT(*) as total FROM rate_limits WHERE rate_key = ? AND created_at >= ?",
            [$key, $cutoffTime]
        );
        
        if ($count && $count['total'] >= $maxRequests) {
            return false;
        }
        
        // Log this request
        quickInsert('rate_limits', [
            'rate_key' => $key,
            'rate_type' => 'session'
        ]);
        
        return true;
    } catch (Exception $e) {
        error_log("Session rate limit error: " . $e->getMessage());
        return true;
    }
}

/**
 * Check website-specific rate limit
 */
function checkWebsiteRateLimit($websiteId, $maxRequests, $windowSeconds) {
    $key = 'ratelimit_website_' . (int)$websiteId;
    $cutoffTime = date('Y-m-d H:i:s', time() - $windowSeconds);
    
    try {
        // Clean old entries for this specific key
        execQuery(
            "DELETE FROM rate_limits WHERE rate_key = ? AND created_at < ?",
            [$key, $cutoffTime]
        );
        
        // Count recent requests
        $count = fetchOne(
            "SELECT COUNT(*) as total FROM rate_limits WHERE rate_key = ? AND created_at >= ?",
            [$key, $cutoffTime]
        );
        
        if ($count && $count['total'] >= $maxRequests) {
            return false;
        }
        
        // Log this request
        quickInsert('rate_limits', [
            'rate_key' => $key,
            'rate_type' => 'website'
        ]);
        
        return true;
    } catch (Exception $e) {
        error_log("Website rate limit error: " . $e->getMessage());
        return true;
    }
}

/**
 * Detect suspicious patterns
 */
function isSuspiciousRequest($data) {
    // 1. Check for SQL injection attempts in string fields
    $suspiciousPatterns = [
        '/(\bUNION\b|\bSELECT\b|\bDROP\b|\bINSERT\b|\bUPDATE\b|\bDELETE\b|\bEXEC\b|\bEXECUTE\b)/i',
        '/(--|\;|\*|\/\*|\*\/|xp_|sp_)/i',
        '/(<script|javascript:|onerror=|onload=|onclick=|<iframe|<object|<embed)/i',
        '/(\.\.\/|\.\.\\\\|\/etc\/passwd|\/windows\/system)/i'
    ];
    
    $fieldsToCheck = ['page_url', 'referrer', 'event_name'];
    
    foreach ($fieldsToCheck as $field) {
        if (isset($data[$field]) && is_string($data[$field])) {
            foreach ($suspiciousPatterns as $pattern) {
                if (preg_match($pattern, $data[$field])) {
                    error_log("Suspicious pattern detected in {$field}: " . substr($data[$field], 0, 100));
                    return true;
                }
            }
        }
    }
    
    // 2. Check for extremely long session IDs (potential attack)
    if (isset($data['session_id']) && strlen($data['session_id']) > 100) {
        error_log("Suspicious: session_id too long");
        return true;
    }
    
    // 3. Check for null bytes (potential attack)
    foreach ($data as $key => $value) {
        if (is_string($value) && strpos($value, "\0") !== false) {
            error_log("Suspicious: null byte detected in {$key}");
            return true;
        }
    }
    
    // 4. Check for excessive nesting in event_properties
    if (isset($data['event_properties'])) {
        $jsonDepth = 0;
        $str = is_string($data['event_properties']) ? $data['event_properties'] : json_encode($data['event_properties']);
        for ($i = 0; $i < strlen($str); $i++) {
            if ($str[$i] === '{' || $str[$i] === '[') $jsonDepth++;
            if ($jsonDepth > 10) return true; // Max 10 levels deep
        }
    }
    
    return false;
}

// ============= HELPER FUNCTIONS =============

function getWebsiteByTrackId($trackId) {
    return fetchOne(
        "SELECT user_id, website_id, website_name, track_id, plan_type FROM website WHERE track_id = ? LIMIT 1",
        [$trackId]
    );
}

function handleCustomEvent($userId, $websiteId, $pageUrl, $country, $deviceType, $browserType, $sessionId, $data) {
    $eventName = isset($data['event_name']) ? substr(clean($data['event_name']), 0, 100) : 'unknown';
    
    // Sanitize event properties
    if (isset($data['event_properties'])) {
        if (is_string($data['event_properties'])) {
            $eventProperties = $data['event_properties'];
        } else {
            $eventProperties = json_encode($data['event_properties']);
        }
        
        // Validate JSON and limit size
        $decoded = json_decode($eventProperties, true);
        if (!$decoded || strlen($eventProperties) > 10000) {
            $eventProperties = '{}';
        }
    } else {
        $eventProperties = '{}';
    }
    
    quickInsert('custom_events', [
        'user_id' => $userId,
        'website_id' => $websiteId,
        'event_name' => $eventName,
        'event_properties' => $eventProperties,
        'page_url' => substr($pageUrl, 0, 2048),
        'country' => substr($country, 0, 20),
        'device_type' => substr($deviceType, 0, 20),
        'browser_type' => substr($browserType, 0, 20),
        'session_id' => substr($sessionId, 0, 100)
    ]);
}

function isUniqueVisitor($websiteId, $ipHash) {
    $result = fetchOne(
        "SELECT COUNT(*) as count FROM analytics 
         WHERE website_id = ? AND ip_hash = ? AND DATE(created_at) = CURDATE()",
        [$websiteId, $ipHash]
    );
    
    return ($result && $result['count'] == 0) ? 'yes' : 'no';
}

function handlePageView($websiteId, $pageUrl, $sessionId) {
    error_log("PageView: website_id=$websiteId, page=" . substr($pageUrl, 0, 100) . ", session=$sessionId");
}

function handlePageViewEnd($userId, $websiteId, $pageUrl, $referrer, $country, $countryTier, $deviceType, $browserType, $ipHash, $timeSpent) {
    $isUnique = isUniqueVisitor($websiteId, $ipHash);
    
    // Insert analytics record
    quickInsert('analytics', [
        'user_id' => $userId,
        'website_id' => $websiteId,
        'page_url' => substr($pageUrl, 0, 255),
        'referrer' => substr($referrer, 0, 100),
        'country' => substr($country, 0, 20),
        'country_tier' => $countryTier,
        'device_type' => substr($deviceType, 0, 20),
        'browser_type' => substr($browserType, 0, 20),
        'ip_hash' => $ipHash,
        'is_unique' => $isUnique,
        'timespent' => $timeSpent
    ]);
    
    // Simply increment the counter - record is guaranteed to exist from ensureCurrentMonthUsage()
    $currentMonth = date('Y-m');
    execQuery(
        "UPDATE monthly_usage 
         SET event_count = event_count + 1 
         WHERE website_id = ? AND month = ?",
        [$websiteId, $currentMonth]
    );
}

function handleHeartbeat($websiteId, $pageUrl, $sessionId, $isActiveFlag) {
    $existing = fetchOne(
        "SELECT session_id FROM live_user_sessions WHERE session_id = ?",
        [$sessionId]
    );
    
    if ($existing) {
        execQuery(
            "UPDATE live_user_sessions 
             SET page = ?, is_active = ?, last_ping = NOW() 
             WHERE session_id = ?",
            [substr($pageUrl, 0, 255), $isActiveFlag ? 1 : 0, $sessionId]
        );
    } else {
        quickInsert('live_user_sessions', [
            'session_id' => $sessionId,
            'website_id' => $websiteId,
            'page' => substr($pageUrl, 0, 255),
            'is_active' => $isActiveFlag ? 1 : 0
        ]);
    }
    
    // Cleanup old sessions
    execQuery(
        "DELETE FROM live_user_sessions 
         WHERE last_ping < DATE_SUB(NOW(), INTERVAL 2 MINUTE)"
    );
    
    // Update live user count
    $liveCount = fetchOne(
        "SELECT COUNT(DISTINCT session_id) as total 
         FROM live_user_sessions 
         WHERE website_id = ? AND page = ? AND last_ping >= DATE_SUB(NOW(), INTERVAL 2 MINUTE)",
        [$websiteId, substr($pageUrl, 0, 255)]
    );
    
    $totalOnline = $liveCount['total'] ?? 0;
    
    $existingLive = fetchOne(
        "SELECT live_user_id FROM live_users WHERE website_id = ? AND page = ?",
        [$websiteId, substr($pageUrl, 0, 255)]
    );
    
    if ($existingLive) {
        execQuery(
            "UPDATE live_users 
             SET total_users_online = ?, last_updated = NOW() 
             WHERE live_user_id = ?",
            [$totalOnline, $existingLive['live_user_id']]
        );
    } else {
        quickInsert('live_users', [
            'website_id' => $websiteId,
            'total_users_online' => $totalOnline,
            'page' => substr($pageUrl, 0, 255)
        ]);
    }
}

function handleLeave($websiteId, $sessionId) {
    execQuery(
        "DELETE FROM live_user_sessions WHERE session_id = ?",
        [$sessionId]
    );
}
?>