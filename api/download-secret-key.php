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

// Get dashboard URL from environment
$base_url = rtrim(env('PHANTOMTRACK_URL'), '/');
$dashboard_url = $base_url . '/dashboard?secretkey=' . urlencode($secret_key);

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
ACCOUNT EMAIL
───────────────────────────────────────────────────────────
{$user['email']}

───────────────────────────────────────────────────────────
SECURITY NOTES
───────────────────────────────────────────────────────────
• Never share this key with anyone
• Store this file in a secure location
• If compromised, contact support immediately

───────────────────────────────────────────────────────────
SUPPORT
───────────────────────────────────────────────────────────
Email:         phantomdev17@gmail.com
Documentation: https://phantomtrack-docs.vercel.app/docs

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