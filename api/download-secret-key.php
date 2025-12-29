<?php
session_start();
require_once '../includes/functions.php';

if (!isset($_SESSION["user_id"]) || !isset($_SESSION["website_id"])) {
    die('Unauthorized access');
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    die('Invalid request method');
}

$user_id = $_SESSION["user_id"];
$website_id = $_SESSION["website_id"];
$secret_key = clean($_GET['key'] ?? '');

// Verify the secret key belongs to this user
$user = fetchOne("SELECT secret_key, email FROM users WHERE user_id = ? AND secret_key = ?", [$user_id, $secret_key]);

if (!$user) {
    die('Invalid secret key');
}

// Get website info
$website = fetchOne("SELECT website_name, track_id FROM website WHERE website_id = ? AND user_id = ?", [$website_id, $user_id]);

// Create the file content
$base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];
$dashboard_url = $base_url . dirname($_SERVER['PHP_SELF']) . "/../dashboard.php?secretkey=" . urlencode($secret_key);

$file_content = "═══════════════════════════════════════════════════════════
  PHANTOMTRACK SECRET KEY - CONFIDENTIAL
═══════════════════════════════════════════════════════════

⚠️  IMPORTANT: Keep this file secure and private!
This key grants access to your PhantomTrack dashboard.

───────────────────────────────────────────────────────────
SECRET KEY
───────────────────────────────────────────────────────────
{$secret_key}

───────────────────────────────────────────────────────────
DASHBOARD ACCESS
───────────────────────────────────────────────────────────
{$dashboard_url}

───────────────────────────────────────────────────────────
ACCOUNT INFORMATION
───────────────────────────────────────────────────────────
User ID:       {$user_id}
Email:         {$user['email']}
Website:       {$website['website_name']}
Website ID:    {$website_id}
Tracking ID:   {$website['track_id']}

───────────────────────────────────────────────────────────
GENERATED
───────────────────────────────────────────────────────────
Date:          " . date('F d, Y') . "
Time:          " . date('H:i:s T') . "
Timestamp:     " . date('Y-m-d H:i:s') . "

───────────────────────────────────────────────────────────
SECURITY NOTES
───────────────────────────────────────────────────────────
• Never share this key with anyone
• Do not commit this file to version control (Git, SVN, etc.)
• Store this file in a secure location
• Delete this file after saving the key in a password manager
• If compromised, regenerate immediately from settings page

───────────────────────────────────────────────────────────
SUPPORT
───────────────────────────────────────────────────────────
Email:         support@phantomtrack.com
Documentation: https://docs.phantomtrack.com

═══════════════════════════════════════════════════════════
  © " . date('Y') . " PhantomTrack - All Rights Reserved
═══════════════════════════════════════════════════════════
";

// Generate filename with timestamp
$filename = "phantomtrack-secret-key-" . date('Ymd-His') . ".txt";

// Set headers for file download
header('Content-Type: text/plain');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($file_content));
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Output the file content
echo $file_content;
exit;
?>