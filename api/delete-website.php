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
    
    // Validate input
    if (empty($website_id)) {
        echo json_encode(['success' => false, 'error' => 'Missing website ID']);
        exit;
    }
    
    // Check if website belongs to user
    $website = fetchOne(
        "SELECT website_id, website_name, track_id FROM website WHERE website_id = ? AND user_id = ?",
        [$website_id, $user_id]
    );
    
    if (!$website) {
        echo json_encode(['success' => false, 'error' => 'Website not found or unauthorized']);
        exit;
    }
    
    try {
        // Start transaction
        db()->beginTransaction();
        
        // Get the track_id for cascade deletion
        $track_id = $website['track_id'];
        
        // 1. Delete from analytics table
        execQuery(
            "DELETE FROM analytics WHERE website_id = ?",
            [$website_id]
        );
        
        // 2. Delete from custom_events table
        execQuery(
            "DELETE FROM custom_events WHERE website_id = ?",
            [$website_id]
        );
        
        // 3. Delete from live_users table
        execQuery(
            "DELETE FROM live_users WHERE website_id = ?",
            [$website_id]
        );
        
        // 4. Delete from live_user_sessions table
        execQuery(
            "DELETE FROM live_user_sessions WHERE website_id = ?",
            [$website_id]
        );
        
        // 5. Delete from monthly_usage table
        execQuery(
            "DELETE FROM monthly_usage WHERE website_id = ? OR tracking_id = ?",
            [$website_id, $track_id]
        );
        
        // 6. Finally, delete the website itself
        $result = execQuery(
            "DELETE FROM website WHERE website_id = ? AND user_id = ?",
            [$website_id, $user_id]
        );
        
        if ($result) {
            // Commit all deletions
            db()->commit();
            
            // UNSET website_id session if the deleted website was the active one
            if (isset($_SESSION['website_id']) && $_SESSION['website_id'] == $website_id) {
                unset($_SESSION['website_id']);
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Website and all related data deleted successfully'
            ]);
        } else {
            // Rollback if website deletion failed
            db()->rollBack();
            echo json_encode(['success' => false, 'error' => 'Failed to delete website']);
        }
        
    } catch (PDOException $e) {
        // Rollback on any error
        db()->rollBack();
        error_log("Database error during website deletion: " . $e->getMessage());
        echo json_encode([
            'success' => false, 
            'error' => 'Database error occurred: ' . $e->getMessage()
        ]);
    }
    
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
}
?>
