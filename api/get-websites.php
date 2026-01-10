<?php
session_start();
include "../includes/functions.php";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo '<li style="padding: 8px 0; color: #ef4444;">Please log in to view websites</li>';
    exit;
}

$user_id = $_SESSION['user_id'];
$current_website_id = $_SESSION['website_id'] ?? null;

// Fetch all websites for this user
$websites = fetchAll(
    "SELECT website_id, website_name, track_id, tier, plan_type, created_at 
     FROM website 
     WHERE user_id = ? 
     ORDER BY created_at DESC",
    [$user_id]
);

if (empty($websites)) {
    echo '<li style="padding: 8px 0; color: var(--text-secondary);">
            <i class="fas fa-inbox"></i> No websites added yet
          </li>';
    exit;
}

// Output the website list
foreach ($websites as $website) {
    $domain = parse_url($website['website_name'], PHP_URL_HOST);
    $displayName = $domain ?: $website['website_name'];
    
    // Truncate long names
    if (strlen($displayName) > 20) {
        $displayName = substr($displayName, 0, 20) . '...';
    }
    
    // Check if this is the active website
    $isActive = ($current_website_id && $current_website_id == $website['website_id']);
    
    // Add active class and styling
    $itemStyle = 'padding: 8px 0;';
    if ($isActive) {
        $itemStyle .= ' background: rgba(99, 102, 241, 0.1); border-left: 3px solid var(--accent1); padding-left: 8px; margin-left: -8px; border-radius: 4px;';
    }
    
    echo '<li style="' . $itemStyle . '">
            <div style="display: flex; align-items: center; justify-content: space-between; gap: 8px;">
              <a href="dashboard?website_id=' . htmlspecialchars($website['website_id']) . '" 
                 style="color: var(--text); text-decoration: none; flex: 1; display: flex; align-items: center; gap: 8px;">
                <i class="fas fa-globe" style="font-size: 0.9em; color: ' . ($isActive ? 'var(--accent1)' : 'inherit') . ';"></i>
                <span title="' . htmlspecialchars($website['website_name']) . '" style="font-weight: ' . ($isActive ? '600' : '400') . ';">' 
                  . htmlspecialchars($displayName) . 
                '</span>';
    
    // Show active indicator
    if ($isActive) {
        echo '<span style="font-size: 0.7em; padding: 2px 6px; border-radius: 4px; background: var(--accent1); color: white;">
                Active
              </span>';
    }
    
    /* Uncomment to show plan type badge
    if ($website['tier'] === 'paid') {
        echo '<span style="font-size: 0.7em; padding: 2px 6px; border-radius: 4px; background: var(--accent2); color: white;">
                ' . htmlspecialchars($website['plan_type']) . '
              </span>';
    }
    */
    
    echo '      </a>
              <div style="display: flex; gap: 8px;">
                <button onclick="editWebsite(' . $website['website_id'] . ')" 
                        title="Edit website"
                        style="background: none; border: none; color: var(--accent1); cursor: pointer; padding: 4px 8px;">
                  <i class="fas fa-edit"></i>
                </button>
                <button onclick="deleteWebsite(' . $website['website_id'] . ')" 
                        title="Delete website"
                        style="background: none; border: none; color: #ef4444; cursor: pointer; padding: 4px 8px;">
                  <i class="fas fa-trash"></i>
                </button>
              </div>
            </div>
          </li>';
}
?>