<?php
header('Content-Type: application/json');
session_start();
include "includes/functions.php";

// Cookie configuration
define('COOKIE_NAME', 'user_secret_key');
define('COOKIE_EXPIRY', 365 * 24 * 60 * 60); // 1 year in seconds

// Get POST data
$secretKey = isset($_POST['secretKey']) ? trim($_POST['secretKey']) : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';

// Validate secret key
if (empty($secretKey) || !preg_match('/^sk_[a-z0-9]+$/', $secretKey)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid secret key format'
    ]);
    exit;
}

// Validate email if provided
if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid email format'
    ]);
    exit;
}

// Set secure cookie (1 year expiration)
// Parameters: name, value, expire, path, domain, secure (HTTPS only), httponly (not accessible via JS)
$cookieSet = setcookie(
    COOKIE_NAME, 
    $secretKey, 
    time() + COOKIE_EXPIRY, 
    '/',           // path
    '',            // domain (empty = current domain)
    isset($_SERVER['HTTPS']), 
    true     
);

quickInsert('users', [
    'secret_key' => $secretKey,
    'email'  => $email
]);

$user_id = db()->lastInsertId();
$_SESSION['user_id'] = $user_id;


// Return success response
echo json_encode([
    'success' => true,
    'message' => 'Secret key saved successfully!',
    'cookie_set' => $cookieSet,
    'secret_key' => $secretKey,
    'email' => $email
]);
?>