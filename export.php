<?php
session_start();
require_once 'includes/functions.php';

if (!isset($_SESSION["user_id"]) || !isset($_SESSION["website_id"])) {
    header("Location: index");
    exit;
}

$user_id = $_SESSION["user_id"];
$website_id = $_SESSION["website_id"];

// Get website info
$website = fetchOne("SELECT website_name, track_id FROM website WHERE website_id = ? AND user_id = ?", [$website_id, $user_id]);
if (!$website) {
    die("Website not found");
}

// Get date range from query params
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$end_date = $_GET['end_date'] ?? date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Export Analytics - Phantom Track</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/export.css">
    <link rel="stylesheet" href="assets/font-awesome/icons/css/all.min.css">
</head>
<body data-theme="dark">

    <!-- Header -->
    <div class="header">
        <div class="header-content">
            <h1><i class="fas fa-download"></i> Export Analytics</h1>
            <button class="theme-toggle" onclick="toggleTheme()" aria-label="Toggle theme">
                <span id="themeIcon"></span>
            </button>
        </div>
    </div>

    <!-- Content -->
    <div class="content">
        <div class="export-container">

            <!-- Website Info -->
            <div class="export-section">
                <div class="card-solid">
                    <h3><i class="fas fa-globe"></i> Export Data For</h3>
                    <div style="margin-top: 16px;">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <div style="width: 48px; height: 48px; background: linear-gradient(135deg, var(--accent1), var(--accent2)); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-chart-line" style="font-size: 24px; color: white;"></i>
                            </div>
                            <div>
                                <div style="font-size: 18px; font-weight: 600; color: var(--text);"><?php echo htmlspecialchars($website['website_name']); ?></div>
                                <div style="font-size: 13px; opacity: 0.7; color: var(--text); margin-top: 4px;">
                                    <i class="fas fa-fingerprint"></i> <?php echo htmlspecialchars($website['track_id']); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Export Form -->
            <div class="export-section">
                <div class="card-solid">
                    <h3><i class="fas fa-file-export"></i> Export Configuration</h3>
                    
                    <form id="exportForm" action="api/export_data" method="POST">
                        
                        <!-- Data Type Selection -->
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-database"></i> Select Data Type
                            </label>
                            <div class="data-type-grid">
                                <label class="data-type-card">
                                    <input type="radio" name="data_type" value="analytics" checked>
                                    <div class="data-type-content">
                                        <i class="fas fa-chart-bar"></i>
                                        <span>Page Analytics</span>
                                        <small>Pageviews, visitors, time spent</small>
                                    </div>
                                </label>
                                
                                <label class="data-type-card">
                                    <input type="radio" name="data_type" value="custom_events">
                                    <div class="data-type-content">
                                        <i class="fas fa-mouse-pointer"></i>
                                        <span>Custom Events</span>
                                        <small>Button clicks, form submissions</small>
                                    </div>
                                </label>
                                
                                <label class="data-type-card">
                                    <input type="radio" name="data_type" value="all">
                                    <div class="data-type-content">
                                        <i class="fas fa-layer-group"></i>
                                        <span>All Data</span>
                                        <small>Complete analytics export</small>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- Date Range -->
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-calendar-alt"></i> Date Range
                            </label>
                            <div class="date-range-inputs">
                                <div class="input-wrapper">
                                    <label for="start_date">From</label>
                                    <input type="date" 
                                           id="start_date" 
                                           name="start_date" 
                                           value="<?php echo $start_date; ?>" 
                                           class="date-input"
                                           max="<?php echo date('Y-m-d'); ?>"
                                           required>
                                </div>
                                <div class="date-separator">
                                    <i class="fas fa-arrow-right"></i>
                                </div>
                                <div class="input-wrapper">
                                    <label for="end_date">To</label>
                                    <input type="date" 
                                           id="end_date" 
                                           name="end_date" 
                                           value="<?php echo $end_date; ?>" 
                                           class="date-input"
                                           max="<?php echo date('Y-m-d'); ?>"
                                           required>
                                </div>
                            </div>
                            
                            <!-- Quick Date Presets -->
                            <div class="date-presets">
                                <button type="button" class="btn-preset" onclick="setDateRange(7)">Last 7 days</button>
                                <button type="button" class="btn-preset" onclick="setDateRange(30)">Last 30 days</button>
                                <button type="button" class="btn-preset" onclick="setDateRange(90)">Last 90 days</button>
                                <button type="button" class="btn-preset" onclick="setDateRange('all')">All time</button>
                            </div>
                        </div>

                        <!-- Format Selection -->
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-file-code"></i> Export Format
                            </label>
                            <div class="format-grid">
                                <label class="format-card">
                                    <input type="radio" name="format" value="csv" checked>
                                    <div class="format-content">
                                        <i class="fas fa-file-csv"></i>
                                        <span>CSV</span>
                                        <small>Excel, Google Sheets</small>
                                    </div>
                                </label>
                                
                                <label class="format-card">
                                    <input type="radio" name="format" value="json">
                                    <div class="format-content">
                                        <i class="fas fa-file-code"></i>
                                        <span>JSON</span>
                                        <small>Web applications, APIs</small>
                                    </div>
                                </label>
                                
                                <label class="format-card">
                                    <input type="radio" name="format" value="txt">
                                    <div class="format-content">
                                        <i class="fas fa-file-alt"></i>
                                        <span>TXT</span>
                                        <small>Plain text, readable</small>
                                    </div>
                                </label>
                                
                                <label class="format-card">
                                    <input type="radio" name="format" value="php">
                                    <div class="format-content">
                                        <i class="fab fa-php"></i>
                                        <span>PHP Array</span>
                                        <small>PHP applications</small>
                                    </div>
                                </label>
                                
                                <label class="format-card">
                                    <input type="radio" name="format" value="xml">
                                    <div class="format-content">
                                        <i class="fas fa-code"></i>
                                        <span>XML</span>
                                        <small>Enterprise systems</small>
                                    </div>
                                </label>
                                
                                <label class="format-card">
                                    <input type="radio" name="format" value="html">
                                    <div class="format-content">
                                        <i class="fab fa-html5"></i>
                                        <span>HTML</span>
                                        <small>Report, printable</small>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- Export Options -->
                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-cog"></i> Options
                            </label>
                            <div class="options-list">
                                <label class="option-item">
                                    <input type="checkbox" name="include_headers" value="1" checked>
                                    <span>Include column headers</span>
                                </label>
                                <label class="option-item">
                                    <input type="checkbox" name="pretty_format" value="1" checked>
                                    <span>Pretty format (readable)</span>
                                </label>
                                <label class="option-item">
                                    <input type="checkbox" name="compress" value="1">
                                    <span>Compress as ZIP</span>
                                </label>
                            </div>
                        </div>

                        <!-- Preview Info -->
                        <div id="previewInfo" class="preview-info" style="display: none;">
                            <div class="info-card">
                                <i class="fas fa-info-circle"></i>
                                <div>
                                    <strong>Preview:</strong>
                                    <span id="previewText">Loading...</span>
                                </div>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <div class="form-actions">
                            <button type="button" class="btn-secondary" onclick="previewExport()">
                                <i class="fas fa-eye"></i> Preview
                            </button>
                            <button type="submit" class="btn btn-large">
                                <i class="fas fa-download"></i> Export Data
                            </button>
                        </div>

                    </form>
                </div>
            </div>

            <!-- Export History 
            <div class="export-section">
                <div class="card-solid">
                    <h3><i class="fas fa-history"></i> Recent Exports</h3>
                    <div id="exportHistory">
                        <p style="text-align: center; opacity: 0.7; padding: 40px 0;">
                            <i class="fas fa-inbox" style="font-size: 48px; opacity: 0.3; display: block; margin-bottom: 16px;"></i>
                            No exports yet
                        </p>
                    </div>
                </div>
            </div> -->

        </div>
    </div>

    <!-- Loading Modal -->
    <div id="loadingModal" class="modal">
        <div class="modal-content" style="text-align: center;">
            <i class="fas fa-spinner fa-spin" style="font-size: 48px; color: var(--accent1); margin-bottom: 20px;"></i>
            <h3>Preparing Your Export...</h3>
            <p style="opacity: 0.7; margin-top: 8px;">This may take a few moments</p>
        </div>
    </div>

    <script src="assets/js/toogle.js"></script>
    <script>
        // Set date range presets
        function setDateRange(days) {
            const endDate = new Date();
            const startDate = new Date();
            
            if (days === 'all') {
                // Set to a very old date for "all time"
                startDate.setFullYear(startDate.getFullYear() - 10);
            } else {
                startDate.setDate(startDate.getDate() - days);
            }
            
            document.getElementById('start_date').value = startDate.toISOString().split('T')[0];
            document.getElementById('end_date').value = endDate.toISOString().split('T')[0];
        }

        // Preview export
        function previewExport() {
            const form = document.getElementById('exportForm');
            const formData = new FormData(form);
            formData.append('preview', '1');
            
            const previewInfo = document.getElementById('previewInfo');
            const previewText = document.getElementById('previewText');
            
            previewInfo.style.display = 'block';
            previewText.textContent = 'Calculating...';
            
            fetch('api/export_data', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    previewText.innerHTML = `
                        <strong>${data.data.total_records}</strong> records found 
                        (${data.data.date_range}) • 
                        Estimated size: <strong>${data.data.estimated_size}</strong>
                    `;
                } else {
                    previewText.textContent = data.message || 'Error loading preview';
                }
            })
            .catch(error => {
                previewText.textContent = 'Error loading preview';
                console.error('Preview error:', error);
            });
        }

        // Handle form submission
        document.getElementById('exportForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const loadingModal = document.getElementById('loadingModal');
            loadingModal.classList.add('active');
            
            const formData = new FormData(this);
            
            fetch('api/export_data', {
                method: 'POST',
                body: formData
            })
            .then(response => response.blob())
            .then(blob => {
                loadingModal.classList.remove('active');
                
                // Get filename from Content-Disposition header or generate one
                const format = formData.get('format');
                const timestamp = new Date().toISOString().split('T')[0];
                const filename = `phantomtrack_export_${timestamp}.${format}`;
                
                // Create download link
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = filename;
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                document.body.removeChild(a);
                
                showNotification('Export downloaded successfully!', 'success');
            })
            .catch(error => {
                loadingModal.classList.remove('active');
                showNotification('Export failed. Please try again.', 'error');
                console.error('Export error:', error);
            });
        });

        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: ${type === 'success' ? 'var(--accent1)' : type === 'error' ? '#ef4444' : 'var(--accent2)'};
                color: white;
                padding: 16px 24px;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.2);
                z-index: 10000;
                animation: slideIn 0.3s ease;
            `;
            notification.textContent = message;
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        }

        // Animation styles
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideIn {
                from { transform: translateX(400px); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            @keyframes slideOut {
                from { transform: translateX(0); opacity: 1; }
                to { transform: translateX(400px); opacity: 0; }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>