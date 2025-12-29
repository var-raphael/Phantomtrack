<?php
/**
 * CRON JOB - Multiple Tasks with Parameters
 * 
 * Setup on cron-job.org:
 * 
 * Job 1 (Daily Subscriptions): 
 *   URL: https://yourdomain.com/cron/cron-job.php?secret=YOUR_SECRET&task=subscriptions
 *   Schedule: Every day at 00:00
 * 
 * Job 2 (Weekly AI Reviews):
 *   URL: https://yourdomain.com/cron/cron-job.php?secret=YOUR_SECRET&task=ai_reviews
 *   Schedule: Every Sunday at 02:00
 * 
 * Job 3 (Daily Cleanup):
 *   URL: https://yourdomain.com/cron/cron-job.php?secret=YOUR_SECRET&task=cleanup
 *   Schedule: Every day at 03:00
 */

// Security: Require secret key
if (php_sapi_name() !== 'cli') {
    $secretKey = $_GET['secret'] ?? '';
    $expectedKey = env('CRON_JOB_KEY'); // CHANGE THIS!
    
    if ($secretKey !== $expectedKey) {
        http_response_code(403);
        die('Forbidden');
    }
}

require_once '../includes/functions.php';

$paystackSecretKey = env('PAYSTACK_SECRET_KEY');

// Get task from URL parameter
$task = $_GET['task'] ?? 'subscriptions';

echo "=== PhantomTrack Cron Job: {$task} ===\n";
echo "Started at " . date('Y-m-d H:i:s') . "\n\n";

// Route to appropriate task
switch ($task) {
    case 'subscriptions':
        runSubscriptionCheck();
        break;
    
    case 'ai_reviews':
        runAIReviewGeneration();
        break;
    
    case 'cleanup':
        runCleanupTasks();
        break;
    
    case 'all':
        // Run everything (useful for testing)
        runSubscriptionCheck();
        echo "\n" . str_repeat("=", 60) . "\n\n";
        runAIReviewGeneration();
        echo "\n" . str_repeat("=", 60) . "\n\n";
        runCleanupTasks();
        break;
    
    default:
        echo "ERROR: Unknown task '{$task}'\n";
        echo "Valid tasks: subscriptions, ai_reviews, cleanup, all\n";
        exit(1);
}

echo "\n=== Completed at " . date('Y-m-d H:i:s') . " ===\n";
exit(0);

// ============================================
// TASK: SUBSCRIPTION VERIFICATION
// ============================================
function runSubscriptionCheck() {
    global $paystackSecretKey;
    
    echo "TASK: Subscription Verification\n";
    echo str_repeat("-", 60) . "\n";
    
    $activeSubscriptions = fetchAll(
        "SELECT website_id, paystack_sub_code, plan_type, subscription_status 
         FROM website 
         WHERE tier = 'paid' 
         AND subscription_status = 'active' 
         AND paystack_sub_code IS NOT NULL 
         AND paystack_sub_code NOT LIKE 'lifetime_%'"
    );
    
    if (empty($activeSubscriptions)) {
        echo "No active subscriptions to check.\n";
        return;
    }
    
    $verifiedCount = 0;
    $downgradedCount = 0;
    $errorCount = 0;
    
    foreach ($activeSubscriptions as $site) {
        $websiteId = $site['website_id'];
        $subCode = $site['paystack_sub_code'];
        $planType = $site['plan_type'];
        
        echo "Checking website_id={$websiteId}, sub={$subCode}... ";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.paystack.co/subscription/{$subCode}");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer {$paystackSecretKey}"
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            echo "ERROR (HTTP {$httpCode})\n";
            $errorCount++;
            continue;
        }
        
        $result = json_decode($response, true);
        
        if (!$result['status']) {
            echo "ERROR (API failed)\n";
            $errorCount++;
            continue;
        }
        
        $paystackStatus = $result['data']['status'] ?? 'unknown';
        
        if ($paystackStatus === 'active') {
            echo "OK\n";
            $verifiedCount++;
        } else {
            echo "DOWNGRADING (status: {$paystackStatus})\n";
            
            execQuery(
                "UPDATE website 
                 SET subscription_status = 'cancelled',
                     plan_type = 'free',
                     tier = 'free'
                 WHERE website_id = ?",
                [$websiteId]
            );
            
            $currentMonth = date('Y-m');
            execQuery(
                "UPDATE monthly_usage 
                 SET req_limit = 10000
                 WHERE website_id = ? AND month = ?",
                [$websiteId, $currentMonth]
            );
            
            $downgradedCount++;
        }
        
        usleep(200000); // 0.2s delay
    }
    
    echo "\nResults:\n";
    echo "  ✓ Verified: {$verifiedCount}\n";
    echo "  ⚠ Downgraded: {$downgradedCount}\n";
    echo "  ✗ Errors: {$errorCount}\n";
    
    // Check expired subscriptions
    echo "\nChecking expired subscriptions...\n";
    
    $expiredSubs = fetchAll(
        "SELECT website_id, plan_type, subscription_ends 
         FROM website 
         WHERE tier = 'paid' 
         AND subscription_status = 'active' 
         AND subscription_ends IS NOT NULL 
         AND subscription_ends < CURDATE()"
    );
    
    $expiredCount = 0;
    foreach ($expiredSubs as $site) {
        $websiteId = $site['website_id'];
        
        execQuery(
            "UPDATE website 
             SET subscription_status = 'expired',
                 plan_type = 'free',
                 tier = 'free'
             WHERE website_id = ?",
            [$websiteId]
        );
        
        $currentMonth = date('Y-m');
        execQuery(
            "UPDATE monthly_usage 
             SET req_limit = 10000
             WHERE website_id = ? AND month = ?",
            [$websiteId, $currentMonth]
        );
        
        $expiredCount++;
    }
    
    echo "  ⚠ Expired: {$expiredCount}\n";
}

// ============================================
// TASK: AI REVIEW GENERATION
// ============================================
function runAIReviewGeneration() {
    echo "TASK: AI Review Generation (Weekly)\n";
    echo str_repeat("-", 60) . "\n";
    
    // Get all paid websites that need review
    $websites = fetchAll(
        "SELECT website_id, website_name 
         FROM website 
         WHERE tier = 'paid' 
         AND subscription_status = 'active'"
    );
    
    if (empty($websites)) {
        echo "No paid websites to generate reviews for.\n";
        return;
    }
    
    $successCount = 0;
    $failCount = 0;
    
    foreach ($websites as $site) {
        $websiteId = $site['website_id'];
        $websiteName = $site['website_name'];
        
        echo "Generating review for website_id={$websiteId} ({$websiteName})... ";
        
        // Check if review was generated in last 6 days (prevent spam)
        $recentReview = fetchOne(
            "SELECT review_id FROM ai_review 
             WHERE website_id = ? 
             AND date > DATE_SUB(NOW(), INTERVAL 6 DAY)
             LIMIT 1",
            [$websiteId]
        );
        
        if ($recentReview) {
            echo "SKIPPED (recent review exists)\n";
            continue;
        }
        
        // Generate review
        $result = generateAIReviewForWebsite($websiteId);
        
        if ($result['success']) {
            echo "SUCCESS\n";
            $successCount++;
        } else {
            echo "FAILED ({$result['error']})\n";
            $failCount++;
        }
        
        // Delay between API calls
        sleep(2);
    }
    
    echo "\nResults:\n";
    echo "  ✓ Generated: {$successCount}\n";
    echo "  ✗ Failed: {$failCount}\n";
}

function generateAIReviewForWebsite($website_id) {
    // Fetch analytics data (last 7 days)
    $sevenDaysAgo = date('Y-m-d H:i:s', strtotime('-7 days'));
    
    $stats = fetchOne(
        "SELECT 
            COUNT(*) as total_visits,
            COUNT(DISTINCT ip_hash) as unique_visitors,
            COUNT(DISTINCT page_url) as pages_visited,
            AVG(CAST(timespent AS UNSIGNED)) as avg_time,
            SUM(CASE WHEN device_type = 'mobile' THEN 1 ELSE 0 END) * 100.0 / NULLIF(COUNT(*), 0) as mobile_percent
         FROM analytics 
         WHERE website_id = ? 
         AND created_at > ?",
        [$website_id, $sevenDaysAgo]
    );
    
    $topCountries = fetchAll(
        "SELECT country, COUNT(*) as visits 
         FROM analytics 
         WHERE website_id = ? AND created_at > ?
         GROUP BY country 
         ORDER BY visits DESC 
         LIMIT 3",
        [$website_id, $sevenDaysAgo]
    );
    
    $events = fetchAll(
        "SELECT event_name, COUNT(*) as count 
         FROM custom_events 
         WHERE website_id = ? AND created_at > ?
         GROUP BY event_name 
         ORDER BY count DESC 
         LIMIT 5",
        [$website_id, $sevenDaysAgo]
    );
    
    $analyticsData = json_encode([
        'stats' => $stats,
        'top_countries' => $topCountries,
        'events' => $events
    ], JSON_PRETTY_PRINT);
    
    // Call Groq API
    $apiKey = env('GROQ_API_KEY');
    
    $prompt = "You are an expert web analytics consultant. Analyze this website's data from the last 7 days and provide 4 concise insights. Each insight should have a title (max 3 words) and a message (max 15 words). Focus on: traffic trends, geographic performance, user engagement, and key recommendations.

Analytics Data:
$analyticsData

Return ONLY a JSON array with this exact structure (no markdown, no explanation):
[
  {\"title\": \"Traffic Growth\", \"message\": \"Your insight here\", \"type\": \"positive\"},
  {\"title\": \"Geographic Data\", \"message\": \"Your insight here\", \"type\": \"info\"},
  {\"title\": \"User Engagement\", \"message\": \"Your insight here\", \"type\": \"warning\"},
  {\"title\": \"Key Action\", \"message\": \"Your insight here\", \"type\": \"info\"}
]

Types: positive (green), warning (yellow), info (blue). Use 'positive' for good metrics, 'warning' for areas needing attention.";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.groq.com/openai/v1/chat/completions");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer " . $apiKey,
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        "model" => "llama-3.3-70b-versatile",
        "messages" => [
            ["role" => "user", "content" => $prompt]
        ],
        "temperature" => 0.7,
        "max_tokens" => 500
    ]));
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        return ['success' => false, 'error' => 'API error'];
    }
    
    $result = json_decode($response, true);
    $aiResponse = $result['choices'][0]['message']['content'] ?? '';
    
    // Clean response
    $aiResponse = preg_replace('/```json\s*|\s*```/', '', $aiResponse);
    $aiResponse = trim($aiResponse);
    
    // Validate JSON
    $reviews = json_decode($aiResponse, true);
    if (!$reviews || !is_array($reviews)) {
        return ['success' => false, 'error' => 'Invalid JSON'];
    }
    
    // Save to database
    $inserted = quickInsert('ai_review', [
        'website_id' => $website_id,
        'review' => $aiResponse,
        'rate_limit' => 1
    ]);
    
    if (!$inserted) {
        return ['success' => false, 'error' => 'DB insert failed'];
    }
    
    return ['success' => true];
}

// ============================================
// TASK: CLEANUP
// ============================================
function runCleanupTasks() {
    echo "TASK: Data Cleanup\n";
    echo str_repeat("-", 60) . "\n";
    
    // Delete old rate_limits (older than 24 hours)
    try {
        execQuery("DELETE FROM rate_limits WHERE created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
        echo "✓ Cleaned old rate_limits\n";
    } catch (Exception $e) {
        echo "✗ Failed to clean rate_limits\n";
    }
    
    // Delete stale live sessions (older than 5 minutes)
    try {
        execQuery("DELETE FROM live_user_sessions WHERE last_ping < DATE_SUB(NOW(), INTERVAL 5 MINUTE)");
        echo "✓ Cleaned stale live sessions\n";
    } catch (Exception $e) {
        echo "✗ Failed to clean live sessions\n";
    }
    
    // Update live_users counts
    try {
        execQuery("DELETE FROM live_users WHERE last_updated < DATE_SUB(NOW(), INTERVAL 10 MINUTE)");
        echo "✓ Cleaned stale live_users entries\n";
    } catch (Exception $e) {
        echo "✗ Failed to clean live_users\n";
    }
    
    // Optional: Archive old analytics (uncomment if needed)
    /*
    try {
        execQuery("DELETE FROM analytics WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 YEAR)");
        echo "✓ Archived analytics older than 1 year\n";
    } catch (Exception $e) {
        echo "✗ Failed to archive analytics\n";
    }
    */
    
    echo "\nCleanup complete.\n";
}
?>