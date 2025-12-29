<?php
session_start();
include "../includes/functions.php";

$website_id = $_SESSION["website_id"] ?? 1;

$result = fetchOne(
    "SELECT 
        COUNT(*) as total_events,
        COUNT(DISTINCT event_name) as unique_events
    FROM custom_events
    WHERE website_id = ?
    AND DATE(created_at) = CURDATE()",
    [$website_id]
);

$totalEvents = $result['total_events'] ?? 0;
$uniqueEvents = $result['unique_events'] ?? 0;

$previous = fetchOne(
    "SELECT COUNT(*) as total 
    FROM custom_events
    WHERE website_id = ?
    AND DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)",
    [$website_id]
);

$previousTotal = $previous['total'] ?? 0;
$change = $previousTotal > 0 ? round((($totalEvents - $previousTotal) / $previousTotal) * 100, 1) : 0;
$changeText = $change >= 0 ? "+{$change}%" : "{$change}%";

echo <<<HTML
<div 
  hx-get="api/custom-events-modal" 
  hx-target="#modal-content"
  hx-trigger="click"
  data-modal-title="Custom Events">
  <div class="stat-header">
    <span class="stat-label">Custom Events</span>
    <div class="stat-icon icon-purple">
      <i class="fas fa-bolt"></i>
    </div>
  </div>
  <div class="stat-value">{$totalEvents}</div>
  <div class="stat-subtext">{$changeText} from yesterday • {$uniqueEvents} unique events</div>
</div>
HTML;
?>