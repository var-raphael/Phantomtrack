<?php
session_start();
require_once "../includes/functions.php";

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    jsonResponse(false, "Unauthorized");
}

$email = trim($_POST['email'] ?? '');

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(false, "Invalid email address");
}

// Check if email already exists
$existing = fetchOne("SELECT user_id FROM users WHERE email = ? AND user_id != ?", [$email, $_SESSION['user_id']]);

if ($existing) {
    jsonResponse(false, "This email is already registered");
}

// Update email
$updated = execQuery(
    "UPDATE users SET email = ? WHERE user_id = ?",
    [$email, $_SESSION['user_id']]
);

if ($updated) {
    jsonResponse(true, "Email updated successfully");
} else {
    jsonResponse(false, "Failed to update email");
}