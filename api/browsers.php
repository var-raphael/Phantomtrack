<?php
session_start();
include "../includes/functions.php";

$type = $_GET["type"] ?? "browsers";
$limit = $_GET["limit"] ?? "10"; // Default to 10, can be "all"
$website_id = $_SESSION["website_id"] ?? 1;

// Comprehensive browser icon and color mapping
$browserConfig = [
    // Major Desktop Browsers
    'Chrome' => [
        'icon' => 'fa-brands fa-chrome',
        'color' => '#4285F4',
        'progress_class' => 'progress-blue'
    ],
    'Firefox' => [
        'icon' => 'fa-brands fa-firefox-browser',
        'color' => '#FF7139',
        'progress_class' => 'progress-orange'
    ],
    'Safari' => [
        'icon' => 'fa-brands fa-safari',
        'color' => '#006CFF',
        'progress_class' => 'progress-sky'
    ],
    'Edge' => [
        'icon' => 'fa-brands fa-edge',
        'color' => '#0078D7',
        'progress_class' => 'progress-ocean'
    ],
    'Opera' => [
        'icon' => 'fa-brands fa-opera',
        'color' => '#FF1B2D',
        'progress_class' => 'progress-red'
    ],
    'Brave' => [
        'icon' => 'fa-brands fa-brave',
        'color' => '#FB542B',
        'progress_class' => 'progress-sunset'
    ],
    'Internet Explorer' => [
        'icon' => 'fa-brands fa-internet-explorer',
        'color' => '#0076D6',
        'progress_class' => 'progress-navy'
    ],
    
    // Mobile Browsers
    'Chrome Mobile' => [
        'icon' => 'fa-brands fa-chrome',
        'color' => '#4285F4',
        'progress_class' => 'progress-blue',
        'show_badge' => true
    ],
    'Safari Mobile' => [
        'icon' => 'fa-brands fa-safari',
        'color' => '#006CFF',
        'progress_class' => 'progress-sky',
        'show_badge' => true
    ],
    'Samsung Browser' => [
        'icon' => 'fa-solid fa-mobile-screen-button',
        'color' => '#1428A0',
        'progress_class' => 'progress-indigo',
        'text_badge' => 'S'
    ],
    'UC Browser' => [
        'icon' => 'fa-solid fa-mobile-screen-button',
        'color' => '#FF6600',
        'progress_class' => 'progress-amber',
        'text_badge' => 'UC'
    ],
    'Firefox Mobile' => [
        'icon' => 'fa-brands fa-firefox-browser',
        'color' => '#FF7139',
        'progress_class' => 'progress-coral',
        'show_badge' => true
    ],
    'Opera Mini' => [
        'icon' => 'fa-brands fa-opera',
        'color' => '#FF1B2D',
        'progress_class' => 'progress-crimson',
        'text_badge' => 'Mini'
    ],
    'Opera Mobile' => [
        'icon' => 'fa-brands fa-opera',
        'color' => '#FF1B2D',
        'progress_class' => 'progress-red',
        'show_badge' => true
    ],
    
    // Chromium-based
    'Vivaldi' => [
        'icon' => 'fa-solid fa-compass',
        'color' => '#EF3939',
        'progress_class' => 'progress-rose'
    ],
    'Yandex' => [
        'icon' => 'fa-brands fa-yandex',
        'color' => '#FF0000',
        'progress_class' => 'progress-red'
    ],
    'MIUI Browser' => [
        'icon' => 'fa-solid fa-mobile-screen-button',
        'color' => '#FF6900',
        'progress_class' => 'progress-sunset',
        'text_badge' => 'Mi'
    ],
    
    // Regional Browsers
    'QQ Browser' => [
        'icon' => 'fa-solid fa-message',
        'color' => '#12B7F5',
        'progress_class' => 'progress-aqua',
        'text_badge' => 'QQ'
    ],
    'Sogou' => [
        'icon' => 'fa-solid fa-globe',
        'color' => '#FF6A00',
        'progress_class' => 'progress-amber',
        'text_badge' => 'S'
    ],
    
    // Privacy Browsers
    'Tor Browser' => [
        'icon' => 'fa-solid fa-user-secret',
        'color' => '#7D4698',
        'progress_class' => 'progress-violet'
    ],
    'DuckDuckGo' => [
        'icon' => 'fa-solid fa-shield-halved',
        'color' => '#DE5833',
        'progress_class' => 'progress-orange',
        'text_badge' => 'DDG'
    ],
    
    // Developer/Specialty
    'Arc' => [
        'icon' => 'fa-solid fa-wand-magic-sparkles',
        'color' => '#FF6B6B',
        'progress_class' => 'progress-pink'
    ],
    'Electron' => [
        'icon' => 'fa-solid fa-atom',
        'color' => '#47848F',
        'progress_class' => 'progress-teal'
    ],
    
    // Bots/Crawlers
    'Googlebot' => [
        'icon' => 'fa-solid fa-robot',
        'color' => '#4285F4',
        'progress_class' => 'progress-blue',
        'text_badge' => 'Bot'
    ],
    'Bingbot' => [
        'icon' => 'fa-solid fa-robot',
        'color' => '#008373',
        'progress_class' => 'progress-emerald',
        'text_badge' => 'Bot'
    ],
    
    // Fallback
    'Other' => [
        'icon' => 'fa-solid fa-globe',
        'color' => '#6B7280',
        'progress_class' => 'progress-gray'
    ],
    'Unknown' => [
        'icon' => 'fa-solid fa-question-circle',
        'color' => '#9CA3AF',
        'progress_class' => 'progress-slate'
    ]
];

function getBrowserData($type, $website_id, $browserConfig, $limit) {
    // Calculate date 90 days ago
    $date90DaysAgo = date('Y-m-d H:i:s', strtotime('-90 days'));
    
    // Get total visits from last 90 days only
    $totalResult = fetchOne(
        "SELECT COUNT(*) as total 
        FROM analytics 
        WHERE website_id = ? 
        AND created_at >= ?",
        [$website_id, $date90DaysAgo]
    );
    $totalVisits = $totalResult['total'] ?? 1;
    
    // Determine LIMIT clause
    $limitClause = ($limit === "all") ? "" : "LIMIT " . intval($limit);
    
    // Get browser data from last 90 days only
    $query = "SELECT 
            browser_type,
            COUNT(*) as visit_count
        FROM analytics
        WHERE website_id = ?
        AND created_at >= ?
        GROUP BY browser_type
        ORDER BY visit_count DESC
        {$limitClause}";
    
    $browsers = fetchAll($query, [$website_id, $date90DaysAgo]);
    
    $result = [];
    foreach ($browsers as $browser) {
        $browserName = $browser['browser_type'];
        
        // Try exact match first
        $config = $browserConfig[$browserName] ?? null;
        
        // If no exact match, try partial match
        if (!$config) {
            foreach ($browserConfig as $key => $value) {
                if (stripos($browserName, $key) !== false) {
                    $config = $value;
                    break;
                }
            }
        }
        
        // Fallback to 'Other' if still no match
        if (!$config) {
            $config = $browserConfig['Other'];
        }
        
        $percent = round(($browser['visit_count'] / $totalVisits) * 100, 1);
        
        $result[] = [
            'name' => $browserName,
            'icon' => $config['icon'],
            'color' => $config['color'],
            'progress_class' => $config['progress_class'],
            'percent' => $percent . '%',
            'width' => $percent,
            'text_badge' => $config['text_badge'] ?? null,
            'show_badge' => $config['show_badge'] ?? false
        ];
    }
    
    return $result;
}

$data = getBrowserData($type, $website_id, $browserConfig, $limit);

if (empty($data)) {
    echo '<div class="browser-item"><div class="browser-info">No data available</div></div>';
} else {
    foreach ($data as $browser) {
        $iconHtml = '';
        
        if (isset($browser['text_badge']) && $browser['text_badge']) {
            // Custom text badge (UC, QQ, Mi, etc.)
            $iconHtml = '<div class="browser-icon-text" style="background-color: ' . $browser['color'] . ';">' 
                      . htmlspecialchars($browser['text_badge']) 
                      . '</div>';
        } else {
            // Font Awesome icon
            $iconHtml = '<i class="' . $browser['icon'] . ' browser-icon" style="color: ' . $browser['color'] . ';"></i>';
            
            // Add "M" badge for mobile versions
            if ($browser['show_badge']) {
                $iconHtml = '<div class="icon-with-badge">' 
                          . $iconHtml 
                          . '<span class="mobile-badge">M</span>'
                          . '</div>';
            }
        }
        
        echo <<<HTML
        <div class="browser-item">
          <div class="browser-icon-wrapper">
            {$iconHtml}
          </div>
          <div class="browser-info">
            <div class="browser-name">{$browser['name']}</div>
            <div class="progress-bar">
              <div class="progress-fill {$browser['progress_class']}" style="width: {$browser['width']}%;"></div>
            </div>
          </div>
          <span class="browser-percent">{$browser['percent']}</span>
        </div>
        
        HTML;
    }
}
?>