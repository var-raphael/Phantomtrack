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

// Validate email is not empty
if (empty($email)) {
    echo json_encode([
        'success' => false,
        'message' => 'Email is required'
    ]);
    exit;
}

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid email format'
    ]);
    exit;
}

// Check for duplicate email
$existingUser = fetchOne(
    "SELECT user_id FROM users WHERE email = ? LIMIT 1",
    [$email]
);

if ($existingUser) {
    echo json_encode([
        'success' => false,
        'message' => 'This email is already registered. Please use a different email or contact support.'
    ]);
    exit;
}

// Set secure cookie (1 year expiration)
$cookieSet = setcookie(
    COOKIE_NAME, 
    $secretKey, 
    time() + COOKIE_EXPIRY, 
    '/',
    '',
    isset($_SERVER['HTTPS']), 
    true     
);

// Insert user into database
try {
    quickInsert('users', [
        'secret_key' => $secretKey,
        'email'  => $email
    ]);
    
    $user_id = db()->lastInsertId();
    $_SESSION['user_id'] = $user_id;
    
} catch (Exception $e) {
    error_log("Error inserting user: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error saving user data. Please try again.'
    ]);
    exit;
}

// Generate dashboard link
$baseUrl = rtrim(env('PHANTOMTRACK_URL'), '/');
$dashboardLink = $baseUrl . '/dashboard?secretkey=' . $secretKey;

// Prepare email HTML
$emailHtml = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .container {
            background: #ffffff;
            border-radius: 12px;
            padding: 40px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .header h1 {
            color: #6366f1;
            margin: 0;
            font-size: 28px;
        }
        .content {
            margin: 20px 0;
        }
        .secret-key-box {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 15px;
            margin: 20px 0;
            font-family: monospace;
            font-size: 14px;
            word-break: break-all;
        }
        .button {
            display: inline-block;
            background: #6366f1;
            color: white;
            text-decoration: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 500;
            margin: 20px 0;
        }
        .button:hover {
            background: #4f46e5;
        }
        .warning {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
            font-size: 14px;
            color: #6c757d;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎉 Welcome to PhantomTrack!</h1>
        </div>
        
        <div class="content">
            <p>Hi there,</p>
            <p>Thank you for signing up! Your PhantomTrack account has been created successfully.</p>
            
            <h3>Your Secret Key:</h3>
            <div class="secret-key-box">
                ' . htmlspecialchars($secretKey) . '
            </div>
            
            <div class="warning">
                <strong>⚠️ Important:</strong> Keep this secret key safe! You will need it to access your dashboard. Do not share it with anyone.
            </div>
            
            <p>Click the button below to access your dashboard:</p>
            
            <div style="text-align: center;">
                <a href="' . htmlspecialchars($dashboardLink) . '" class="button">Access Dashboard</a>
            </div>
            
            <p>Or copy and paste this link into your browser:</p>
            <div class="secret-key-box" style="font-size: 12px;">
                ' . htmlspecialchars($dashboardLink) . '
            </div>
            
            <p>If you have any questions or need assistance, feel free to reach out to our support team.</p>
            
            <p>Happy tracking! 🚀</p>
        </div>
        
        <div class="footer">
            <p>PhantomTrack - Analytics Made Simple</p>
            <p>For any questions or issues reach out to the support email phantomdev17@gmail.com.</p>
        </div>
    </div>
</body>
</html>
';

// Send email via Brevo
$emailSent = sendBrevoEmail(
    $email,
    $email, // Using email as name since we don't collect names
    'Welcome to PhantomTrack - Your Secret Key',
    $emailHtml
);

if (!$emailSent) {
    error_log("Failed to send welcome email to: " . $email);
    // Don't fail the request if email fails, just log it
}

// Return success response
echo json_encode([
    'success' => true,
    'message' => 'Account created successfully! Check your email for your secret key.',
    'cookie_set' => $cookieSet,
    'secret_key' => $secretKey,
    'email' => $email,
    'email_sent' => $emailSent ? true : false
]);
?>