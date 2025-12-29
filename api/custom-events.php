<?php
session_start();
include "../includes/functions.php";

$type = $_GET["type"] ?? "overview";
$website_id = $_SESSION["website_id"] ?? 1;
$limit = (int)($_GET["limit"] ?? 10);
$event_name = $_GET["event_name"] ?? null;

// Helper function for time ago formatting
function time_ago($timestamp) {
    $time = strtotime($timestamp);
    $diff = time() - $time;
    
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . ' min ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
    if ($diff < 604800) return floor($diff / 86400) . ' days ago';
    
    return date('M d, Y', $time);
}

function getCustomEventsData($type, $website_id, $limit, $event_name) {
    switch($type) {
        case 'overview':
            $events = fetchAll(
                "SELECT 
                    event_name,
                    COUNT(*) as event_count,
                    COUNT(DISTINCT session_id) as unique_users,
                    MAX(created_at) as last_triggered
                FROM custom_events
                WHERE website_id = ?
                GROUP BY event_name
                ORDER BY event_count DESC
                LIMIT " . $limit,
                [$website_id]
            );
            
            return ['type' => 'overview', 'data' => $events];
            
        case 'timeline':
            $timeline = fetchAll(
                "SELECT 
                    DATE(created_at) as event_date,
                    event_name,
                    COUNT(*) as event_count
                FROM custom_events
                WHERE website_id = ?
                AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                GROUP BY DATE(created_at), event_name
                ORDER BY event_date DESC, event_count DESC",
                [$website_id]
            );
            
            return ['type' => 'timeline', 'data' => $timeline];
            
        case 'details':
            if (!$event_name) {
                return ['type' => 'details', 'data' => []];
            }
            
            $details = fetchAll(
                "SELECT 
                    event_name,
                    event_properties,
                    page_url,
                    country,
                    device_type,
                    browser_type,
                    created_at
                FROM custom_events
                WHERE website_id = ? AND event_name = ?
                ORDER BY created_at DESC
                LIMIT " . $limit,
                [$website_id, $event_name]
            );
            
            return ['type' => 'details', 'event_name' => $event_name, 'data' => $details];
            
        default:
            return ['type' => 'unknown', 'data' => []];
    }
}

$result = getCustomEventsData($type, $website_id, $limit, $event_name);

// ============================================================================
// RENDER VIEWS
// ============================================================================

if ($result['type'] === 'overview') {
    if (empty($result['data'])) {
        echo <<<HTML
        <div class="empty-state">
            <i class="fas fa-bolt"></i>
            <p>No custom events tracked yet</p>
            <small>Start tracking events by adding phantom.track() to your website</small>
        </div>
        HTML;
    } else {
        foreach ($result['data'] as $event) {
            $timeAgo = time_ago($event['last_triggered']);
            echo <<<HTML
            <div class="event-item" 
                 hx-get="api/custom-events?type=details&event_name={$event['event_name']}" 
                 hx-target="#event-details-modal"
                 hx-trigger="click">
                <div class="event-icon">
                    <i class="fas fa-bolt"></i>
                </div>
                <div class="event-info">
                    <div class="event-name">{$event['event_name']}</div>
                    <div class="event-time">{$timeAgo}</div>
                </div>
                <div class="event-stats">
                    <div class="stat-item">
                        <span class="">{$event['event_count']}</span>
                        <span class="stat-label">Total</span>
                    </div>
                    <div class="stat-item">
                        <span class="">{$event['unique_users']}</span>
                        <span class="stat-label">Users</span>
                    </div>
                </div>
            </div>
            HTML;
        }
    }
} 

elseif ($result['type'] === 'timeline') {
    if (empty($result['data'])) {
        echo '<div class="empty-state">No timeline data available</div>';
    } else {
        $groupedByDate = [];
        foreach ($result['data'] as $item) {
            $groupedByDate[$item['event_date']][] = $item;
        }
        
        foreach ($groupedByDate as $date => $events) {
            echo "<div class='timeline-date'>" . date('M d, Y', strtotime($date)) . "</div>";
            foreach ($events as $event) {
                echo <<<HTML
                <div class="timeline-item">
                    <span class="timeline-name">{$event['event_name']}</span>
                    <span class="timeline-count">{$event['event_count']}×</span>
                </div>
                HTML;
            }
        }
    }
}

elseif ($result['type'] === 'details') {
    echo <<<HTML
    <div class='details-header'>
        <h3>{$result['event_name']}</h3>
        <button onclick='document.getElementById("event-details-modal").innerHTML=""' class="close-btn">×</button>
    </div>
    HTML;
    
    if (empty($result['data'])) {
        echo '<div class="empty-state">No details available</div>';
    } else {
        echo "<div class='details-list'>";
        foreach ($result['data'] as $detail) {
            $properties = json_decode($detail['event_properties'], true);
            $propertiesHtml = '';
            if ($properties && is_array($properties)) {
                $propertiesHtml = '<div class="properties-row">';
                foreach ($properties as $key => $value) {
                    $propertiesHtml .= "<span class='property-tag'>{$key}: {$value}</span>";
                }
                $propertiesHtml .= '</div>';
            }
            
            $timeAgo = time_ago($detail['created_at']);
            
            // Extract just the path from the URL
            $parsed = parse_url($detail['page_url']);
            $shortUrl = ($parsed['path'] ?? '/') . (isset($parsed['query']) ? '?' . $parsed['query'] : '');
            // Remove leading slash if present to match your desired format
            $shortUrl = ltrim($shortUrl, '/');
            
            echo <<<HTML
            <div class="detail-card">
                <div class="detail-row">
                    <span class="detail-label">Time:</span>
                    <span class="detail-value">{$timeAgo}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Page:</span>
                    <span class="detail-value">{$shortUrl}</span>
                </div>
                {$propertiesHtml}
                <div class="detail-meta">
                    <span class="meta-badge">{$detail['country']}</span>
                    <span class="meta-badge">{$detail['device_type']}</span>
                    <span class="meta-badge">{$detail['browser_type']}</span>
                </div>
            </div>
            HTML;
        }
        echo "</div>";
    }
}
?>