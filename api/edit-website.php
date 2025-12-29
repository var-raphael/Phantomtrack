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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $website_id = $_POST['website_id'] ?? '';
    $new_name = $_POST['new_name'] ?? '';
    
    // Validate inputs
    if (empty($website_id) || empty($new_name)) {
        echo json_encode(['success' => false, 'error' => 'Missing website ID or new name']);
        exit;
    }
    
    // Validate URL
    if (!filter_var($new_name, FILTER_VALIDATE_URL)) {
        echo json_encode(['success' => false, 'error' => 'Invalid URL format']);
        exit;
    }
    
    if (!str_starts_with($new_name, 'https://')) {
        echo json_encode(['success' => false, 'error' => 'URL must start with https://']);
        exit;
    }
    
    // Check if website belongs to user
    $website = fetchOne(
        "SELECT website_id FROM website WHERE website_id = ? AND user_id = ?",
        [$website_id, $user_id]
    );
    
    if (!$website) {
        echo json_encode(['success' => false, 'error' => 'Website not found or unauthorized']);
        exit;
    }
    
    // Check if new name already exists for this user (excluding current website)
    $existing = fetchOne(
        "SELECT website_id FROM website WHERE website_name = ? AND user_id = ? AND website_id != ?",
        [$new_name, $user_id, $website_id]
    );
    
    if ($existing) {
        echo json_encode(['success' => false, 'error' => 'A website with this URL already exists']);
        exit;
    }
    
    try {
        // Update website name
        $result = execQuery(
            "UPDATE website SET website_name = ? WHERE website_id = ? AND user_id = ?",
            [$new_name, $website_id, $user_id]
        );
        
        if ($result) {
            echo json_encode([
                'success' => true,
                'message' => 'Website updated successfully',
                'new_name' => $new_name
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to update website']);
        }
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Database error occurred']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
}
?>