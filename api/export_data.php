<?php
session_start();
require_once '../includes/functions.php';

if (!isset($_SESSION["user_id"]) || !isset($_SESSION["website_id"])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$user_id = $_SESSION["user_id"];
$website_id = $_SESSION["website_id"];

// Get parameters
$data_type = clean($_POST['data_type'] ?? 'analytics');
$format = clean($_POST['format'] ?? 'csv');
$start_date = clean($_POST['start_date'] ?? date('Y-m-d', strtotime('-30 days')));
$end_date = clean($_POST['end_date'] ?? date('Y-m-d'));
$include_headers = isset($_POST['include_headers']);
$pretty_format = isset($_POST['pretty_format']);
$compress = isset($_POST['compress']);
$is_preview = isset($_POST['preview']);

// Validate dates
if (!strtotime($start_date) || !strtotime($end_date)) {
    echo json_encode(['success' => false, 'message' => 'Invalid date format']);
    exit;
}

// Validate format
$valid_formats = ['csv', 'json', 'txt', 'php', 'xml', 'html'];
if (!in_array($format, $valid_formats)) {
    echo json_encode(['success' => false, 'message' => 'Invalid export format']);
    exit;
}

// Get data based on type
$data = [];
$total_records = 0;

switch ($data_type) {
    case 'analytics':
        $data['analytics'] = fetchAll(
            "SELECT * FROM analytics 
             WHERE user_id = ? AND website_id = ? 
             AND DATE(created_at) BETWEEN ? AND ?
             ORDER BY created_at DESC",
            [$user_id, $website_id, $start_date, $end_date]
        );
        $total_records = count($data['analytics']);
        break;
        
    case 'custom_events':
        $data['custom_events'] = fetchAll(
            "SELECT * FROM custom_events 
             WHERE user_id = ? AND website_id = ? 
             AND DATE(created_at) BETWEEN ? AND ?
             ORDER BY created_at DESC",
            [$user_id, $website_id, $start_date, $end_date]
        );
        $total_records = count($data['custom_events']);
        break;
        
    case 'all':
        $data['analytics'] = fetchAll(
            "SELECT * FROM analytics 
             WHERE user_id = ? AND website_id = ? 
             AND DATE(created_at) BETWEEN ? AND ?
             ORDER BY created_at DESC",
            [$user_id, $website_id, $start_date, $end_date]
        );
        $data['custom_events'] = fetchAll(
            "SELECT * FROM custom_events 
             WHERE user_id = ? AND website_id = ? 
             AND DATE(created_at) BETWEEN ? AND ?
             ORDER BY created_at DESC",
            [$user_id, $website_id, $start_date, $end_date]
        );
        $total_records = count($data['analytics']) + count($data['custom_events']);
        break;
}

// Preview mode
if ($is_preview) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'data' => [
            'total_records' => $total_records,
            'date_range' => date('M d, Y', strtotime($start_date)) . ' - ' . date('M d, Y', strtotime($end_date)),
            'estimated_size' => formatBytes(estimateSize($data, $format))
        ]
    ]);
    exit;
}

// Generate export based on format
$timestamp = date('Y-m-d_His');
$filename = "phantomtrack_export_{$timestamp}";

switch ($format) {
    case 'csv':
        exportCSV($data, $filename, $include_headers);
        break;
        
    case 'json':
        exportJSON($data, $filename, $pretty_format);
        break;
        
    case 'txt':
        exportTXT($data, $filename);
        break;
        
    case 'php':
        exportPHP($data, $filename, $pretty_format);
        break;
        
    case 'xml':
        exportXML($data, $filename);
        break;
        
    case 'html':
        exportHTML($data, $filename, $start_date, $end_date);
        break;
}

exit;

// ========== EXPORT FUNCTIONS ==========

function exportCSV($data, $filename, $include_headers) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    foreach ($data as $table_name => $rows) {
        if (empty($rows)) continue;
        
        // Add table separator
        if (count($data) > 1) {
            fputcsv($output, ["=== {$table_name} ==="]);
            fputcsv($output, []);
        }
        
        // Add headers
        if ($include_headers && !empty($rows)) {
            fputcsv($output, array_keys($rows[0]));
        }
        
        // Add data rows
        foreach ($rows as $row) {
            fputcsv($output, $row);
        }
        
        // Add spacing between tables
        if (count($data) > 1) {
            fputcsv($output, []);
        }
    }
    
    fclose($output);
}

function exportJSON($data, $filename, $pretty_format) {
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="' . $filename . '.json"');
    
    $options = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
    if ($pretty_format) {
        $options |= JSON_PRETTY_PRINT;
    }
    
    echo json_encode($data, $options);
}

function exportTXT($data, $filename) {
    header('Content-Type: text/plain');
    header('Content-Disposition: attachment; filename="' . $filename . '.txt"');
    
    echo "PhantomTrack Analytics Export\n";
    echo "Generated: " . date('Y-m-d H:i:s') . "\n";
    echo str_repeat("=", 80) . "\n\n";
    
    foreach ($data as $table_name => $rows) {
        if (empty($rows)) continue;
        
        echo strtoupper($table_name) . "\n";
        echo str_repeat("-", 80) . "\n\n";
        
        foreach ($rows as $index => $row) {
            echo "Record #" . ($index + 1) . "\n";
            foreach ($row as $key => $value) {
                echo sprintf("  %-20s: %s\n", $key, $value);
            }
            echo "\n";
        }
        
        echo "\n";
    }
    
    echo str_repeat("=", 80) . "\n";
    echo "Total Records: " . array_sum(array_map('count', $data)) . "\n";
}

function exportPHP($data, $filename, $pretty_format) {
    header('Content-Type: text/plain');
    header('Content-Disposition: attachment; filename="' . $filename . '.php"');
    
    echo "<?php\n";
    echo "/**\n";
    echo " * PhantomTrack Analytics Export\n";
    echo " * Generated: " . date('Y-m-d H:i:s') . "\n";
    echo " */\n\n";
    
    if ($pretty_format) {
        echo "\$analytics_data = " . var_export($data, true) . ";\n\n";
    } else {
        echo "\$analytics_data = " . var_export($data, true) . ";\n";
    }
    
    echo "// Total records: " . array_sum(array_map('count', $data)) . "\n";
    echo "// Usage: include this file and access data via \$analytics_data\n";
}

function exportXML($data, $filename) {
    header('Content-Type: application/xml');
    header('Content-Disposition: attachment; filename="' . $filename . '.xml"');
    
    $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><phantomtrack_export/>');
    $xml->addAttribute('generated', date('c'));
    
    foreach ($data as $table_name => $rows) {
        if (empty($rows)) continue;
        
        $table_node = $xml->addChild($table_name);
        
        foreach ($rows as $row) {
            $record_node = $table_node->addChild('record');
            foreach ($row as $key => $value) {
                $record_node->addChild($key, htmlspecialchars($value ?? ''));
            }
        }
    }
    
    // Pretty print
    $dom = new DOMDocument('1.0');
    $dom->preserveWhiteSpace = false;
    $dom->formatOutput = true;
    $dom->loadXML($xml->asXML());
    
    echo $dom->saveXML();
}

function exportHTML($data, $filename, $start_date, $end_date) {
    header('Content-Type: text/html');
    header('Content-Disposition: attachment; filename="' . $filename . '.html"');
    
    $website = fetchOne("SELECT website_name FROM website WHERE website_id = ?", [$_SESSION['website_id']]);
    $website_name = $website['website_name'] ?? 'Unknown';
    
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>PhantomTrack Analytics Export</title>
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
                line-height: 1.6;
                color: #333;
                background: #f5f5f5;
                padding: 40px 20px;
            }
            
            .container {
                max-width: 1200px;
                margin: 0 auto;
                background: white;
                padding: 40px;
                border-radius: 12px;
                box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            }
            
            .header {
                border-bottom: 3px solid #6366f1;
                padding-bottom: 20px;
                margin-bottom: 40px;
            }
            
            .header h1 {
                color: #6366f1;
                font-size: 32px;
                margin-bottom: 8px;
            }
            
            .header .meta {
                color: #666;
                font-size: 14px;
            }
            
            .summary {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 20px;
                margin-bottom: 40px;
            }
            
            .summary-card {
                background: linear-gradient(135deg, #6366f1, #06b6d4);
                color: white;
                padding: 20px;
                border-radius: 8px;
                text-align: center;
            }
            
            .summary-card .value {
                font-size: 32px;
                font-weight: bold;
                margin-bottom: 4px;
            }
            
            .summary-card .label {
                font-size: 14px;
                opacity: 0.9;
            }
            
            .section {
                margin-bottom: 40px;
            }
            
            .section h2 {
                color: #333;
                font-size: 24px;
                margin-bottom: 20px;
                padding-bottom: 10px;
                border-bottom: 2px solid #e5e7eb;
            }
            
            table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 20px;
                font-size: 14px;
            }
            
            table thead {
                background: #f9fafb;
            }
            
            table th {
                padding: 12px;
                text-align: left;
                font-weight: 600;
                color: #374151;
                border-bottom: 2px solid #e5e7eb;
            }
            
            table td {
                padding: 12px;
                border-bottom: 1px solid #f3f4f6;
            }
            
            table tbody tr:hover {
                background: #f9fafb;
            }
            
            .footer {
                margin-top: 40px;
                padding-top: 20px;
                border-top: 2px solid #e5e7eb;
                text-align: center;
                color: #666;
                font-size: 14px;
            }
            
            @media print {
                body {
                    background: white;
                    padding: 0;
                }
                
                .container {
                    box-shadow: none;
                    padding: 20px;
                }
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>📊 PhantomTrack Analytics Report</h1>
                <div class="meta">
                    <strong>Website:</strong> <?php echo htmlspecialchars($website_name); ?><br>
                    <strong>Period:</strong> <?php echo date('M d, Y', strtotime($start_date)); ?> - <?php echo date('M d, Y', strtotime($end_date)); ?><br>
                    <strong>Generated:</strong> <?php echo date('F d, Y \a\t H:i:s'); ?>
                </div>
            </div>
            
            <div class="summary">
                <?php
                $total_analytics = count($data['analytics'] ?? []);
                $total_events = count($data['custom_events'] ?? []);
                $total_records = $total_analytics + $total_events;
                
                // Calculate unique visitors
                $unique_visitors = 0;
                if (!empty($data['analytics'])) {
                    $unique_ips = array_unique(array_column($data['analytics'], 'ip_hash'));
                    $unique_visitors = count($unique_ips);
                }
                
                // Calculate total time spent
                $total_time = 0;
                if (!empty($data['analytics'])) {
                    foreach ($data['analytics'] as $row) {
                        $total_time += (int)($row['timespent'] ?? 0);
                    }
                }
                ?>
                
                <div class="summary-card">
                    <div class="value"><?php echo number_format($total_analytics); ?></div>
                    <div class="label">Page Views</div>
                </div>
                
                <div class="summary-card">
                    <div class="value"><?php echo number_format($unique_visitors); ?></div>
                    <div class="label">Unique Visitors</div>
                </div>
                
                <div class="summary-card">
                    <div class="value"><?php echo number_format($total_events); ?></div>
                    <div class="label">Custom Events</div>
                </div>
                
                <div class="summary-card">
                    <div class="value"><?php echo gmdate('H:i:s', $total_time); ?></div>
                    <div class="label">Total Time Spent</div>
                </div>
            </div>
            
            <?php if (!empty($data['analytics'])): ?>
            <div class="section">
                <h2>📈 Page Analytics</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Page URL</th>
                            <th>Referrer</th>
                            <th>Country</th>
                            <th>Device</th>
                            <th>Browser</th>
                            <th>Time Spent</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data['analytics'] as $row): ?>
                        <tr>
                            <td><?php echo date('M d, H:i', strtotime($row['created_at'])); ?></td>
                            <td><?php echo htmlspecialchars(substr($row['page_url'], 0, 50)); ?></td>
                            <td><?php echo htmlspecialchars($row['referrer']); ?></td>
                            <td><?php echo htmlspecialchars($row['country']); ?></td>
                            <td><?php echo htmlspecialchars($row['device_type']); ?></td>
                            <td><?php echo htmlspecialchars($row['browser_type']); ?></td>
                            <td><?php echo $row['timespent']; ?>s</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($data['custom_events'])): ?>
            <div class="section">
                <h2>🎯 Custom Events</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Event Name</th>
                            <th>Properties</th>
                            <th>Page URL</th>
                            <th>Country</th>
                            <th>Device</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data['custom_events'] as $row): ?>
                        <tr>
                            <td><?php echo date('M d, H:i', strtotime($row['created_at'])); ?></td>
                            <td><strong><?php echo htmlspecialchars($row['event_name']); ?></strong></td>
                            <td><?php echo htmlspecialchars(substr($row['event_properties'], 0, 50)); ?></td>
                            <td><?php echo htmlspecialchars(substr($row['page_url'], 0, 40)); ?></td>
                            <td><?php echo htmlspecialchars($row['country']); ?></td>
                            <td><?php echo htmlspecialchars($row['device_type']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
            
            <div class="footer">
                <p>Generated by <strong>PhantomTrack</strong> • <?php echo date('Y'); ?></p>
                <p style="margin-top: 8px; font-size: 12px; opacity: 0.7;">
                    This report contains <?php echo number_format($total_records); ?> total records
                </p>
            </div>
        </div>
    </body>
    </html>
    <?php
}

// Helper functions

function estimateSize($data, $format) {
    $json_size = strlen(json_encode($data));
    
    switch ($format) {
        case 'csv':
            return $json_size * 0.7; // CSV is typically smaller
        case 'json':
            return $json_size;
        case 'txt':
            return $json_size * 1.5; // TXT has more formatting
        case 'php':
            return $json_size * 1.3;
        case 'xml':
            return $json_size * 2; // XML has lots of tags
        case 'html':
            return $json_size * 3; // HTML has styling
        default:
            return $json_size;
    }
}

function formatBytes($bytes) {
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1048576) return round($bytes / 1024, 2) . ' KB';
    return round($bytes / 1048576, 2) . ' MB';
}
?>
