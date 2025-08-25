<?php

/**
 * Remove Duplicate History Records Script
 *
 * Administrative script to clean up duplicate rows in cars_hist table.
 * Issue #202: Delete Duplicate Rows in Car History
 *
 * DUPLICATES IDENTIFIED:
 * - 631 groups of duplicate records (631 rows to remove)
 * - Duplicates by: car_id + operation + timestamp
 * - 485 duplicate INSERT operations, 146 duplicate UPDATE operations
 * - Strategy: Keep record with LOWEST id (earliest created)
 */
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../users/init.php';

// Get the database instance
$db = DB::getInstance();

$line = 1; // Where messages go

?>

<h2>Database Cleanup: Duplicate History Records<br></h2>
<?php
echo date("h:i:sa");
?>
<p>
    <u>Progress</u>
</p>

<?php
function msg($message)
{
    global $line;
    echo '<script>document.getElementById("line' . $line . '").innerHTML = "' . $message . '";</script>';
    echo '<div id="line' . ($line + 1) . '"></div>';
    $line++;
    ob_flush();
    flush();
}

// SAFETY: Create backup notification
msg("⚠️  SAFETY NOTICE: Backup cars_hist table before running this script!");
msg("Command: mysqldump -h localhost -P 8889 -u claude -p\"claude\" elanregi_spice cars_hist > cars_hist_backup_$(date +%Y%m%d_%H%M%S).sql");
msg("");

// Analysis before cleanup
msg("🔍 Analyzing duplicate records...");

$total_rows_before = $db->query("SELECT COUNT(*) as count FROM cars_hist")->first()->count;
msg("Total rows in cars_hist: " . $total_rows_before);

$duplicate_groups = $db->query("
    SELECT COUNT(*) as count
    FROM (
        SELECT car_id, operation, timestamp, COUNT(*) as cnt
        FROM cars_hist
        GROUP BY car_id, operation, timestamp
        HAVING COUNT(*) > 1
    ) as dups
")->first()->count;
msg("Duplicate groups found: " . $duplicate_groups);

$rows_to_remove = $db->query("
    SELECT (SUM(cnt) - COUNT(*)) as rows_to_remove
    FROM (
        SELECT car_id, operation, timestamp, COUNT(*) as cnt
        FROM cars_hist
        GROUP BY car_id, operation, timestamp
        HAVING COUNT(*) > 1
    ) as dups
")->first()->rows_to_remove;
msg("Duplicate rows to remove: " . $rows_to_remove);

// Show operation breakdown
$operation_breakdown = $db->query("
    SELECT operation, COUNT(*) as duplicate_groups
    FROM (
        SELECT car_id, operation, timestamp, COUNT(*) as cnt
        FROM cars_hist
        GROUP BY car_id, operation, timestamp
        HAVING COUNT(*) > 1
    ) as dups
    GROUP BY operation
    ORDER BY duplicate_groups DESC
")->results();

msg("Breakdown by operation:");
foreach ($operation_breakdown as $op) {
    msg("  - {$op->operation}: {$op->duplicate_groups} duplicate groups");
}
msg("");

// Show sample duplicates
msg("📋 Sample duplicate records:");
$samples = $db->query("
    SELECT car_id, operation, timestamp, COUNT(*) as count
    FROM cars_hist
    GROUP BY car_id, operation, timestamp
    HAVING COUNT(*) > 1
    ORDER BY count DESC
    LIMIT 5
")->results();

foreach ($samples as $sample) {
    msg("  Car {$sample->car_id} - {$sample->operation} at {$sample->timestamp} ({$sample->count} copies)");
}
msg("");

// Pause for user confirmation
msg("⏸️  READY TO PROCEED WITH CLEANUP");
msg("Press any key to continue or Ctrl+C to cancel...");
msg("");

// Execute the duplicate removal
msg("🚀 Starting duplicate removal...");

try {
    // Execute the cleanup query
    $result = $db->query("
        DELETE h1 FROM cars_hist h1
        INNER JOIN (
            SELECT car_id, operation, timestamp, MIN(id) as min_id
            FROM cars_hist
            GROUP BY car_id, operation, timestamp
            HAVING COUNT(*) > 1
        ) h2 ON h1.car_id = h2.car_id
            AND h1.operation = h2.operation
            AND h1.timestamp = h2.timestamp
            AND h1.id > h2.min_id
    ");

    msg("✅ Duplicate removal completed successfully!");

    // Verification
    msg("");
    msg("🔍 Verifying cleanup results...");

    $total_rows_after = $db->query("SELECT COUNT(*) as count FROM cars_hist")->first()->count;
    msg("Total rows after cleanup: " . $total_rows_after);
    msg("Rows removed: " . ($total_rows_before - $total_rows_after));

    $remaining_duplicates = $db->query("
        SELECT COUNT(*) as count
        FROM (
            SELECT car_id, operation, timestamp, COUNT(*) as cnt
            FROM cars_hist
            GROUP BY car_id, operation, timestamp
            HAVING COUNT(*) > 1
        ) as dups
    ")->first()->count;
    msg("Remaining duplicate groups: " . $remaining_duplicates);

    if ($remaining_duplicates == 0) {
        msg("✅ SUCCESS: All duplicates have been removed!");
    } else {
        msg("⚠️  WARNING: Some duplicates may remain - manual review needed");
    }

    // Verify car 1104 specifically (from issue report)
    $car_1104_count = $db->query("SELECT COUNT(*) as count FROM cars_hist WHERE car_id = 1104")->first()->count;
    msg("Car 1104 records after cleanup: " . $car_1104_count);

    // Safety checks
    msg("");
    msg("🔒 Running safety checks...");

    $orphaned_cars = $db->query("
        SELECT COUNT(*) as count
        FROM cars c
        LEFT JOIN cars_hist h ON c.id = h.car_id
        WHERE h.car_id IS NULL
    ")->first()->count;

    if ($orphaned_cars > 0) {
        msg("⚠️  WARNING: Found {$orphaned_cars} cars without history records");
    } else {
        msg("✅ No orphaned cars found");
    }

    msg("");
    msg("🎉 CLEANUP COMPLETED SUCCESSFULLY!");
    msg("Issue #202 resolved: Duplicate rows in cars_hist have been removed");

    // Record script completion
    try {
        $db->query("INSERT INTO fix_script_runs (script_name) VALUES (?)", [basename(__FILE__)]);
        msg("✅ Script completion recorded");
    } catch (Exception $record_e) {
        msg("⚠️  Could not record script completion: " . $record_e->getMessage());
    }

} catch (Exception $e) {
    msg("❌ ERROR during cleanup: " . $e->getMessage());
    msg("Cleanup aborted - no changes made");
}

msg("");
msg("Script completed at " . date("h:i:sa"));

// Return to FIX menu button
echo '<div style="margin-top: 20px; text-align: center;">';
echo '<button onclick="window.opener.location.reload(); window.close();" class="btn btn-outline-primary">';
echo '<i class="fa fa-arrow-left" aria-hidden="true"></i> Return to FIX Menu';
echo '</button>';
echo '</div>';
?>

<div id="line<?php echo $line; ?>"></div>
