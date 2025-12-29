<?php
/**
 * Monthly Usage Cleanup Script
 * Run this once to fix duplicate records and reset for the new month
 */

require 'includes/functions.php';

echo "Starting monthly usage cleanup...\n\n";

// Get current month
$currentMonth = date('Y-m');
$previousMonth = date('Y-m', strtotime('-1 month'));

echo "Current month: {$currentMonth}\n";
echo "Previous month: {$previousMonth}\n\n";

// Step 1: Find and fix duplicate month records
echo "Step 1: Checking for duplicate month records...\n";
$duplicates = fetchAll(
    "SELECT website_id, month, COUNT(*) as count 
     FROM monthly_usage 
     GROUP BY website_id, month 
     HAVING count > 1"
);

if (!empty($duplicates)) {
    echo "Found " . count($duplicates) . " duplicate records:\n";
    
    foreach ($duplicates as $dup) {
        echo "  - Website ID: {$dup['website_id']}, Month: {$dup['month']}, Count: {$dup['count']}\n";
        
        // Get all records for this website/month
        $records = fetchAll(
            "SELECT * FROM monthly_usage 
             WHERE website_id = ? AND month = ? 
             ORDER BY usage_id ASC",
            [$dup['website_id'], $dup['month']]
        );
        
        // Keep the first record, sum up event_counts, delete the rest
        $firstRecord = $records[0];
        $totalEvents = 0;
        $recordsToDelete = [];
        
        foreach ($records as $record) {
            $totalEvents += $record['event_count'];
            if ($record['usage_id'] != $firstRecord['usage_id']) {
                $recordsToDelete[] = $record['usage_id'];
            }
        }
        
        // Update the first record with total events
        execQuery(
            "UPDATE monthly_usage SET event_count = ? WHERE usage_id = ?",
            [$totalEvents, $firstRecord['usage_id']]
        );
        
        // Delete duplicates
        if (!empty($recordsToDelete)) {
            $placeholders = implode(',', array_fill(0, count($recordsToDelete), '?'));
            execQuery(
                "DELETE FROM monthly_usage WHERE usage_id IN ({$placeholders})",
                $recordsToDelete
            );
            echo "    ✓ Merged {$dup['count']} records into one (total events: {$totalEvents})\n";
        }
    }
} else {
    echo "  ✓ No duplicate records found\n";
}

echo "\n";

// Step 2: Check and create current month records if missing
echo "Step 2: Ensuring current month records exist...\n";

$websites = fetchAll("SELECT website_id, track_id, plan_type FROM website");

$planLimits = [
    'free' => 10000,
    'pro' => 30000,
    'premium' => 60000,
    'enterprise' => 100000,
    'lifetime' => 300000
];

foreach ($websites as $site) {
    $websiteId = $site['website_id'];
    $trackId = $site['track_id'];
    $planType = $site['plan_type'] ?? 'free';
    
    // Check if current month record exists
    $currentMonthRecord = fetchOne(
        "SELECT * FROM monthly_usage WHERE website_id = ? AND month = ?",
        [$websiteId, $currentMonth]
    );
    
    $correctLimit = $planLimits[$planType] ?? 10000;
    
    if (!$currentMonthRecord) {
        // Create new month record
        quickInsert('monthly_usage', [
            'website_id' => $websiteId,
            'tracking_id' => $trackId,
            'month' => $currentMonth,
            'event_count' => 0,
            'req_limit' => $correctLimit
        ]);
        echo "  ✓ Created {$currentMonth} record for Website ID {$websiteId} (Plan: {$planType}, Limit: {$correctLimit})\n";
    } else {
        // Check if limit matches plan (update if changed)
        if ($currentMonthRecord['req_limit'] != $correctLimit) {
            execQuery(
                "UPDATE monthly_usage SET req_limit = ? WHERE usage_id = ?",
                [$correctLimit, $currentMonthRecord['usage_id']]
            );
            echo "  ✓ Updated limit for Website ID {$websiteId} from {$currentMonthRecord['req_limit']} to {$correctLimit}\n";
        } else {
            echo "  ✓ Website ID {$websiteId} already has correct {$currentMonth} record\n";
        }
    }
}

echo "\n";

// Step 3: Add unique constraint to prevent future duplicates
echo "Step 3: Adding unique constraint to prevent duplicates...\n";
try {
    // Check if constraint already exists
    $constraintExists = fetchOne(
        "SELECT COUNT(*) as count FROM information_schema.TABLE_CONSTRAINTS 
         WHERE CONSTRAINT_SCHEMA = 'phantomtrack' 
         AND TABLE_NAME = 'monthly_usage' 
         AND CONSTRAINT_NAME = 'unique_website_month'"
    );
    
    if ($constraintExists && $constraintExists['count'] == 0) {
        execQuery(
            "ALTER TABLE monthly_usage 
             ADD CONSTRAINT unique_website_month UNIQUE (website_id, month)"
        );
        echo "  ✓ Added unique constraint on (website_id, month)\n";
    } else {
        echo "  ✓ Unique constraint already exists\n";
    }
} catch (Exception $e) {
    echo "  ⚠ Could not add constraint (may already exist): " . $e->getMessage() . "\n";
}

echo "\n";

// Step 4: Summary report
echo "Step 4: Current status summary...\n";
$allUsage = fetchAll(
    "SELECT mu.*, w.website_name, w.plan_type 
     FROM monthly_usage mu
     JOIN website w ON mu.website_id = w.website_id
     WHERE mu.month = ?
     ORDER BY mu.website_id ASC",
    [$currentMonth]
);

echo "\nCurrent Month ({$currentMonth}) Usage:\n";
echo str_repeat("-", 100) . "\n";
printf("%-12s %-30s %-12s %-12s %-12s %-10s\n", 
    "Website ID", "Website Name", "Plan", "Events", "Limit", "Usage %");
echo str_repeat("-", 100) . "\n";

foreach ($allUsage as $usage) {
    $percentUsed = $usage['req_limit'] > 0 
        ? round(($usage['event_count'] / $usage['req_limit']) * 100, 1) 
        : 0;
    
    printf("%-12s %-30s %-12s %-12s %-12s %-10s\n",
        $usage['website_id'],
        substr($usage['website_name'], 0, 28),
        $usage['plan_type'],
        number_format($usage['event_count']),
        number_format($usage['req_limit']),
        $percentUsed . '%'
    );
}

echo str_repeat("-", 100) . "\n";
echo "\n✅ Cleanup completed successfully!\n";
echo "\nNext steps:\n";
echo "1. Update your track.php with the fixed usage check code\n";
echo "2. Update your statistics.php with the fixed usage query\n";
echo "3. Monitor the logs for any issues\n";
?>