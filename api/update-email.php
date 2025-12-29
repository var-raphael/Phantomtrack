<?php
session_start();
require_once '../includes/functions.php';

header('Content-Type: text/html');

if (!isset($_SESSION["user_id"])) {
    echo '<div style="color: #ef4444; padding: 8px; background: rgba(239, 68, 68, 0.1); border-radius: 4px;">Unauthorized access</div>';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo '<div style="color: #ef4444; padding: 8px; background: rgba(239, 68, 68, 0.1); border-radius: 4px;">Invalid request method</div>';
    exit;
}

$user_id = $_SESSION["user_id"];
$email = clean($_POST['email'] ?? '');

// Validate email
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo '<div style="color: #ef4444; padding: 8px; background: rgba(239, 68, 68, 0.1); border-radius: 4px;">Please enter a valid email address</div>';
    exit;
}

try {
    // Check if email is already used by another user
    $existing = fetchOne("SELECT user_id FROM users WHERE email = ? AND user_id != ?", [$email, $user_id]);
    
    if ($existing) {
        echo '<div style="color: #ef4444; padding: 8px; background: rgba(239, 68, 68, 0.1); border-radius: 4px;">This email is already in use by another account</div>';
        exit;
    }
    
    // Update email in users table
    execQuery(
        "UPDATE users SET email = ? WHERE user_id = ?",
        [$email, $user_id]
    );
    
    echo '<div style="color: #10b981; padding: 8px; background: rgba(16, 185, 129, 0.1); border-radius: 4px;">
        <i class="fas fa-check-circle"></i> Email updated successfully to: ' . htmlspecialchars($email) . '
    </div>
    <script>showNotification("Email updated successfully!", "success");</script>';
    
} catch (Exception $e) {
    error_log("Update email error: " . $e->getMessage());
    echo '<div style="color: #ef4444; padding: 8px; background: rgba(239, 68, 68, 0.1); border-radius: 4px;">Failed to update email. Please try again.</div>';
}
?>