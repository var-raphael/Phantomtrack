<?php
session_start();
include "../includes/functions.php";

$action = $_GET["action"] ?? "load";
$website_id = $_SESSION["website_id"] ?? null;

if (!$website_id) {
    echo "<div class='ai-review-item'><div class='review-message' style='color: #EF4444;'>No website selected</div></div>";
    exit;
}

// Check rate limit (1 review per hour per website)
$existing = fetchOne(
    "SELECT * FROM ai_review WHERE website_id = ? AND date > DATE_SUB(NOW(), INTERVAL 1 HOUR) ORDER BY date DESC LIMIT 1",
    [$website_id]
);

if ($existing && $action === "generate") {
    $timeLeft = 60 - floor((time() - strtotime($existing['date'])) / 60);
    echo <<<HTML
    <div class="ai-review-item">
      <div class="review-title">
        <span class="review-dot" style="background-color: #F59E0B;"></span>
        Rate Limited
      </div>
      <div class="review-message">AI review already generated. Please wait {$timeLeft} minutes.</div>
    </div>
    HTML;
    exit;
}

// If requesting new generation
if ($action === "generate") {
    generateAndSaveAIReview($website_id);
    exit;
}

// Load existing or generate if none exists
$savedReview = fetchOne(
    "SELECT review, date FROM ai_review WHERE website_id = ? ORDER BY date DESC LIMIT 1",
    [$website_id]
);

if ($savedReview) {
    $reviews = json_decode($savedReview['review'], true);
    $generatedDate = date('M j, Y g:i A', strtotime($savedReview['date']));
} else {
    // Generate first review if none exists
    generateAndSaveAIReview($website_id);
    exit;
}

function generateAndSaveAIReview($website_id) {
    // Fetch analytics data for the website (last 7 days)
    $sevenDaysAgo = date('Y-m-d H:i:s', strtotime('-7 days'));
    
    $analytics = fetchAll(
        "SELECT 
            COUNT(*) as total_visits,
            COUNT(DISTINCT ip_hash) as unique_visitors,
            AVG(CAST(timespent AS UNSIGNED)) as avg_time,
            device_type,
            browser_type,
            country,
            country_tier,
            page_url,
            referrer
         FROM analytics 
         WHERE website_id = ? 
         AND created_at > ?
         GROUP BY device_type, browser_type, country, country_tier, page_url, referrer",
        [$website_id, $sevenDaysAgo]
    );

    // Summary stats
    $stats = fetchOne(
        "SELECT 
            COUNT(*) as total_visits,
            COUNT(DISTINCT ip_hash) as unique_visitors,
            COUNT(DISTINCT page_url) as pages_visited,
            AVG(CAST(timespent AS UNSIGNED)) as avg_time,
            SUM(CASE WHEN device_type = 'mobile' THEN 1 ELSE 0 END) * 100.0 / COUNT(*) as mobile_percent
         FROM analytics 
         WHERE website_id = ? 
         AND created_at > ?",
        [$website_id, $sevenDaysAgo]
    );

    // Custom events
    $events = fetchAll(
        "SELECT event_name, COUNT(*) as count 
         FROM custom_events 
         WHERE website_id = ? 
         AND created_at > ?
         GROUP BY event_name 
         ORDER BY count DESC 
         LIMIT 5",
        [$website_id, $sevenDaysAgo]
    );

    // Prepare data for AI
    $analyticsData = json_encode([
        'stats' => $stats,
        'analytics' => $analytics,
        'events' => $events
    ], JSON_PRETTY_PRINT);


    $apiKey = env('GROQ_API_KEY');
    
    // Validate API key exists
    if (!$apiKey) {
        error_log("❌ GROQ_API_KEY not found in .env file");
        echo <<<HTML
        <div class="ai-review-item">
          <div class="review-title">
            <span class="review-dot" style="background-color: #EF4444;"></span>
            Configuration Error
          </div>
          <div class="review-message">API key not configured. Please check .env file.</div>
        </div>
        HTML;
        exit;
    }
    
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
        error_log("Groq API Error: " . $response);
        echo <<<HTML
        <div class="ai-review-item">
          <div class="review-title">
            <span class="review-dot" style="background-color: #EF4444;"></span>
            API Error
          </div>
          <div class="review-message">Failed to generate AI review. Please try again later.</div>
        </div>
        HTML;
        exit;
    }

    $result = json_decode($response, true);
    $aiResponse = $result['choices'][0]['message']['content'] ?? '';

    // Clean the response (remove markdown if present)
    $aiResponse = preg_replace('/```json\s*|\s*```/', '', $aiResponse);
    $aiResponse = trim($aiResponse);

    // Validate JSON
    $reviews = json_decode($aiResponse, true);
    if (!$reviews || !is_array($reviews)) {
        error_log("Invalid AI response: " . $aiResponse);
        echo <<<HTML
        <div class="ai-review-item">
          <div class="review-title">
            <span class="review-dot" style="background-color: #EF4444;"></span>
            Format Error
          </div>
          <div class="review-message">Invalid AI response format. Please try again.</div>
        </div>
        HTML;
        exit;
    }

    // Save to database
    $inserted = quickInsert('ai_review', [
        'website_id' => $website_id,
        'review' => $aiResponse,
        'rate_limit' => 1
    ]);

    if (!$inserted) {
        echo <<<HTML
        <div class="ai-review-item">
          <div class="review-title">
            <span class="review-dot" style="background-color: #EF4444;"></span>
            Save Error
          </div>
          <div class="review-message">Failed to save review to database.</div>
        </div>
        HTML;
        exit;
    }

    // Return the reviews
    foreach ($reviews as $review) {
        $dotColor = match($review['type']) {
            'positive' => '#10B981',
            'warning' => '#F59E0B',
            default => '#06B6D4'
        };
        
        echo <<<HTML
        <div class="ai-review-item">
          <div class="review-title">
            <span class="review-dot" style="background-color: {$dotColor};"></span>
            {$review['title']}
          </div>
          <div class="review-message">{$review['message']}</div>
        </div>
        HTML;
    }
    
    exit;
}

// Display saved reviews
foreach ($reviews as $review) {
    $dotColor = match($review['type']) {
        'positive' => '#10B981',
        'warning' => '#F59E0B',
        default => '#06B6D4'
    };
    
    echo <<<HTML
    <div class="ai-review-item">
      <div class="review-title">
        <span class="review-dot" style="background-color: {$dotColor};"></span>
        {$review['title']}
      </div>
      <div class="review-message">{$review['message']}</div>
    </div>
    HTML;
}
?>