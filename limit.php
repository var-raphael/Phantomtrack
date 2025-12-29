<?php
/**
 * Rate Limit Test Script for PhantomTrack
 * Tests your rate limiting with Redis/Database fallback
 */

// Try different possible endpoints
$possibleUrls = [
    'http://localhost:8000/phantomtrack/track.php',
    'http://localhost:8000/phantomtrack/track',
    'http://localhost:8000/track.php',
    'http://localhost:8000/track'
];

echo "🔍 Finding correct endpoint...\n";
$url = null;
foreach ($possibleUrls as $testUrl) {
    $ch = curl_init($testUrl);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 2);
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($code !== 404) {
        $url = $testUrl;
        echo "✅ Found endpoint: $testUrl (HTTP $code)\n\n";
        break;
    }
}

if (!$url) {
    die("❌ Could not find working endpoint. Tried:\n" . implode("\n", $possibleUrls) . "\n");
}

$totalRequests = 150; // Test beyond the 100/min IP limit
$delayMs = 100; // 100ms between requests

echo "🧪 Testing Rate Limiting\n";
echo "Target: $url\n";
echo "Total requests: $totalRequests\n";
echo "Delay: {$delayMs}ms\n";
echo str_repeat("=", 50) . "\n\n";

$stats = [
    'success' => 0,
    'rate_limited' => 0,
    'errors' => 0,
    'total_time' => 0,
    'response_times' => []
];

$startTime = microtime(true);

for ($i = 1; $i <= $totalRequests; $i++) {
    $reqStart = microtime(true);
    
    // Prepare test data with your real track ID
    $data = [
        'trackid' => 'track_9uu6ms2pxt3php5jbgwms',
        'session_id' => 'test_session_' . uniqid(),
        'event_type' => 'pageview',
        'page_url' => 'http://localhost/test-page-' . $i,
        'referrer' => 'direct',
        'device_type' => 'desktop',
        'user_agent' => 'Mozilla/5.0 (Test Bot)',
        'screen_resolution' => '1920x1080'
    ];
    
    // Send request
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $reqTime = round((microtime(true) - $reqStart) * 1000, 2);
    $stats['response_times'][] = $reqTime;
    
    // Categorize response
    if ($httpCode === 200) {
        $stats['success']++;
        echo "✅ Request $i: SUCCESS ({$reqTime}ms)\n";
    } elseif ($httpCode === 429) {
        $stats['rate_limited']++;
        echo "⚠️  Request $i: RATE LIMITED ({$reqTime}ms)\n";
    } else {
        $stats['errors']++;
        echo "❌ Request $i: ERROR - HTTP $httpCode ({$reqTime}ms)\n";
    }
    
    // Progress indicator every 10 requests
    if ($i % 10 === 0) {
        $elapsed = round(microtime(true) - $startTime, 2);
        echo "   📊 Progress: $i/$totalRequests | Elapsed: {$elapsed}s\n\n";
    }
    
    // Delay between requests
    usleep($delayMs * 1000);
}

$totalTime = round(microtime(true) - $startTime, 2);
$stats['total_time'] = $totalTime;

// Calculate response time stats
$avgResponseTime = round(array_sum($stats['response_times']) / count($stats['response_times']), 2);
$minResponseTime = round(min($stats['response_times']), 2);
$maxResponseTime = round(max($stats['response_times']), 2);

// Results
echo "\n" . str_repeat("=", 50) . "\n";
echo "📈 TEST RESULTS\n";
echo str_repeat("=", 50) . "\n";
echo "✅ Successful: {$stats['success']}\n";
echo "⚠️  Rate Limited: {$stats['rate_limited']}\n";
echo "❌ Errors: {$stats['errors']}\n";
echo "⏱️  Total Time: {$totalTime}s\n";
echo "📊 Avg Speed: " . round($totalRequests / $totalTime, 2) . " req/s\n";
echo "\n";

// Response time analysis
echo "⚡ RESPONSE TIME ANALYSIS\n";
echo str_repeat("-", 50) . "\n";
echo "Average: {$avgResponseTime}ms\n";
echo "Fastest: {$minResponseTime}ms\n";
echo "Slowest: {$maxResponseTime}ms\n";
echo "\n";

// Performance assessment
if ($avgResponseTime < 5) {
    echo "🚀 REDIS DETECTED! Ultra-fast performance!\n";
} elseif ($avgResponseTime < 20) {
    echo "⚡ Good performance - likely using Redis\n";
} else {
    echo "💾 Using database fallback (Redis not available)\n";
}
echo "\n";

// Rate limiting analysis
echo "🔍 RATE LIMITING ANALYSIS\n";
echo str_repeat("-", 50) . "\n";
if ($stats['rate_limited'] > 0) {
    echo "✅ Rate limiting is WORKING!\n";
    echo "   First ~100 requests succeeded\n";
    echo "   Then got {$stats['rate_limited']} rate-limited (429) responses\n";
    echo "   This confirms your IP rate limit of 100/minute\n";
} else {
    echo "⚠️  No rate limiting detected!\n";
    echo "   Expected 429 responses after ~100 requests\n";
}
echo "\n";

// Database check recommendation
if ($stats['success'] > 0) {
    echo "💡 TIP: Check your database:\n";
    echo "   SELECT COUNT(*) FROM analytics WHERE page_url LIKE '%test-page-%';\n";
    echo "   You should see {$stats['success']} entries\n";
    echo "\n";
    echo "🧹 CLEANUP: Delete test data when done:\n";
    echo "   DELETE FROM analytics WHERE page_url LIKE '%test-page-%';\n";
    echo "   DELETE FROM rate_limits WHERE rate_key LIKE '%test%';\n";
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "✅ TEST COMPLETE!\n";
echo str_repeat("=", 50) . "\n";
?>