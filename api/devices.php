<?php
session_start();
include "../includes/functions.php";

$type = $_GET["type"] ?? "devices";
$website_id = $_SESSION["website_id"] ?? 1;

// Device icon and color mapping
$deviceConfig = [
    'mobile' => [
        'icon' => 'fa-mobile-alt',
        'color' => '#6366F1',
        'progress_class' => 'progress-purple'
    ],
    'desktop' => [
        'icon' => 'fa-desktop',
        'color' => '#06B6D4',
        'progress_class' => 'progress-cyan'
    ],
    'tablet' => [
        'icon' => 'fa-tablet-alt',
        'color' => '#10B981',
        'progress_class' => 'progress-green'
    ],
    'unknown' => [
        'icon' => 'fa-question',
        'color' => '#9CA3AF',
        'progress_class' => 'progress-gray'
    ]
];

function getDeviceData($type, $website_id, $deviceConfig) {
    // Get total count for percentage calculation
    $totalResult = fetchOne(
        "SELECT COUNT(*) as total FROM analytics WHERE website_id = ?",
        [$website_id]
    );
    $totalVisits = $totalResult['total'] ?? 1;
    
    // Get device statistics
    $devices = fetchAll(
        "SELECT 
            device_type,
            COUNT(*) as visit_count
        FROM analytics
        WHERE website_id = ?
        GROUP BY device_type
        ORDER BY visit_count DESC",
        [$website_id]
    );
    
    $result = [];
    foreach ($devices as $device) {
        $deviceType = strtolower($device['device_type']);
        $config = $deviceConfig[$deviceType] ?? $deviceConfig['unknown'];
        
        $percent = round(($device['visit_count'] / $totalVisits) * 100, 1);
        
        $result[] = [
            'name' => ucfirst($device['device_type']),
            'icon' => $config['icon'],
            'color' => $config['color'],
            'progress_class' => $config['progress_class'],
            'percent' => $percent . '%',
            'width' => $percent
        ];
    }
    
    return $result;
}

$data = getDeviceData($type, $website_id, $deviceConfig);

// Generate device items
if (empty($data)) {
    echo '<div class="device-item"><div class="device-info">No data available</div></div>';
} else {
    foreach ($data as $device) {
        echo <<<HTML
        <div class="device-item">
          <i class="fas {$device['icon']} device-icon" style="color: {$device['color']};"></i>
          <div class="device-info">
            <div class="device-name">{$device['name']}</div>
            <div class="progress-bar">
              <div class="progress-fill {$device['progress_class']}" style="width: {$device['width']}%;"></div>
            </div>
          </div>
          <span class="device-percent">{$device['percent']}</span>
        </div>
        
        HTML;
    }
}
?>