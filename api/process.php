<?php
header('Content-Type: application/json');
session_start();
include "../includes/functions.php";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'User not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Prevent any HTML/text output before JSON
ob_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $url = $_POST['url'] ?? '';
    $trackid = $_POST['trackid'] ?? '';
    
    // Validate inputs
    if (empty($url) || empty($trackid)) {
        ob_end_clean();
        echo json_encode(['success' => false, 'error' => 'Missing URL or Track ID']);
        exit;
    }
    
    // Validate URL
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        ob_end_clean();
        echo json_encode(['success' => false, 'error' => 'Invalid URL format']);
        exit;
    }
    
    if (!str_starts_with($url, 'https://')) {
        ob_end_clean();
        echo json_encode(['success' => false, 'error' => 'URL must start with https://']);
        exit;
    }
    
    if (strpos($url, 'localhost') !== false || strpos($url, '127.0.0.1') !== false) {
        ob_end_clean();
        echo json_encode(['success' => false, 'error' => 'Localhost URLs are not allowed']);
        exit;
    }
    
    // Check if track_id already exists
    $existing = fetchOne("SELECT website_id FROM website WHERE track_id = ?", [$trackid]);
    if ($existing) {
        ob_end_clean();
        echo json_encode(['success' => false, 'error' => 'Track ID already exists']);
        exit;
    }
    
    // Check if URL already exists for this user
    $existingUrl = fetchOne("SELECT website_id FROM website WHERE user_id = ? AND website_name = ?", [$user_id, $url]);
    if ($existingUrl) {
        ob_end_clean();
        echo json_encode(['success' => false, 'error' => 'Website already added']);
        exit;
    }
    
    // Only insert columns that exist in your table
   $data = [
    'user_id' => $user_id,
    'website_name' => $url,
    'track_id' => $trackid,
    'tier' => 'free',
    'paystack_sub_code' => '',  // Use empty string instead of null
    'paystack_customer_code' => ''  // Use empty string instead of null
];
    
    try {
        $result = quickInsert('website', $data);
        
        if ($result) {
            ob_end_clean();
            echo json_encode([
                'success' => true,
                'url' => $url,
                'trackid' => $trackid
            ]);
        } else {
            ob_end_clean();
            echo json_encode(['success' => false, 'error' => 'Failed to save website']);
        }
    } catch (PDOException $e) {
        ob_end_clean();
        // Log the actual error for debugging
        error_log("Database error: " . $e->getMessage());
        
        // Return user-friendly error
        if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
            echo json_encode(['success' => false, 'error' => 'Website or Track ID already exists']);
        } else if (strpos($e->getMessage(), 'foreign key constraint') !== false) {
            echo json_encode(['success' => false, 'error' => 'Invalid user account']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Database error occurred: ' . $e->getMessage()]);
        }
    }
    
} else {
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
}
?>
