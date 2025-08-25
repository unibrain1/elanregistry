<?php

/**
 * Cleanup Orphaned Profiles Script
 *
 * Administrative script to clean up orphaned records in the database.
 * Removes profiles without corresponding users and reassigns orphaned cars.
 * Displays progress and uses error reporting for debugging.
 */
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../users/init.php';

// Get the database instance
$db = DB::getInstance();

$line = 1; // Where messages go

?>

<h2>Database Cleanup: Orphaned Records<br></h2>
<?php
echo date("h:i:sa");
?>
<p>
    <u>Progress</u>
</p>

<?php

# Display initial statistics
displayStatistics();

# Clean up orphaned profiles
cleanupOrphanedProfiles();

# Clean up orphaned car_user records
cleanupOrphanedCarUser();

# Reassign orphaned cars to noowner
reassignOrphanedCars();

# Display final statistics
displayFinalStatistics();

echo "<br><br>Done<br>";

/**
 * Display current database statistics
 */
function displayStatistics()
{
    global $db, $line;
    
    outputMessage($line++, "=== INITIAL STATISTICS ===");
    
    // Count users and profiles
    $userCount = $db->query('SELECT COUNT(*) as count FROM users')->first()->count;
    $profileCount = $db->query('SELECT COUNT(*) as count FROM profiles')->first()->count;
    $orphanedProfiles = $db->query('SELECT COUNT(*) as count FROM profiles p LEFT JOIN users u ON p.user_id = u.id WHERE u.id IS NULL')->first()->count;
    
    outputMessage($line++, "Users: $userCount");
    outputMessage($line++, "Profiles: $profileCount");
    outputMessage($line++, "Orphaned Profiles: $orphanedProfiles");
    
    // Count car relationships
    $carCount = $db->query('SELECT COUNT(*) as count FROM cars WHERE user_id > 0')->first()->count;
    $carUserCount = $db->query('SELECT COUNT(*) as count FROM car_user')->first()->count;
    $orphanedCarUser = $db->query('SELECT COUNT(*) as count FROM car_user cu LEFT JOIN users u ON cu.userid = u.id WHERE u.id IS NULL')->first()->count;
    $orphanedCars = $db->query('SELECT COUNT(*) as count FROM cars c LEFT JOIN users u ON c.user_id = u.id WHERE u.id IS NULL AND c.user_id IS NOT NULL AND c.user_id > 0')->first()->count;
    
    outputMessage($line++, "Cars with owners: $carCount");
    outputMessage($line++, "Car-User relationships: $carUserCount");
    outputMessage($line++, "Orphaned car_user records: $orphanedCarUser");
    outputMessage($line++, "Orphaned cars: $orphanedCars");
    
    outputMessage($line++, "");
}

/**
 * Clean up orphaned profile records
 */
function cleanupOrphanedProfiles()
{
    global $db, $line;
    
    outputMessage($line++, "=== CLEANING UP ORPHANED PROFILES ===");
    
    // Get orphaned profiles before deletion
    $orphanedQuery = $db->query('SELECT p.id, p.user_id FROM profiles p LEFT JOIN users u ON p.user_id = u.id WHERE u.id IS NULL');
    $orphaned = $orphanedQuery->results();
    $count = count($orphaned);
    
    if ($count > 0) {
        outputMessage($line++, "Found $count orphaned profile records");
        
        // Delete orphaned profiles
        $deleted = $db->query('DELETE p FROM profiles p LEFT JOIN users u ON p.user_id = u.id WHERE u.id IS NULL');
        
        outputMessage($line++, "Deleted $count orphaned profile records");
        
        // Log each deletion
        foreach ($orphaned as $profile) {
            outputMessage($line++, "  - Deleted profile ID {$profile->id} (user_id: {$profile->user_id})");
        }
    } else {
        outputMessage($line++, "No orphaned profiles found - database is clean");
    }
    
    outputMessage($line++, "");
}

/**
 * Clean up orphaned car_user records
 */
function cleanupOrphanedCarUser()
{
    global $db, $line;
    
    outputMessage($line++, "=== CLEANING UP ORPHANED CAR_USER RECORDS ===");
    
    // Get orphaned car_user records before deletion
    $orphanedQuery = $db->query('SELECT cu.id, cu.userid, cu.carid FROM car_user cu LEFT JOIN users u ON cu.userid = u.id WHERE u.id IS NULL');
    $orphaned = $orphanedQuery->results();
    $count = count($orphaned);
    
    if ($count > 0) {
        outputMessage($line++, "Found $count orphaned car_user records");
        
        // Delete orphaned car_user records
        $deleted = $db->query('DELETE cu FROM car_user cu LEFT JOIN users u ON cu.userid = u.id WHERE u.id IS NULL');
        
        outputMessage($line++, "Deleted $count orphaned car_user records");
        
        // Log each deletion
        foreach ($orphaned as $record) {
            outputMessage($line++, "  - Deleted car_user ID {$record->id} (user_id: {$record->userid}, car_id: {$record->carid})");
        }
    } else {
        outputMessage($line++, "No orphaned car_user records found - database is clean");
    }
    
    outputMessage($line++, "");
}

/**
 * Reassign orphaned cars to noowner user
 */
function reassignOrphanedCars()
{
    global $db, $line;
    
    outputMessage($line++, "=== REASSIGNING ORPHANED CARS ===");
    
    // Find noowner user
    $noOwnerQuery = $db->query('SELECT id FROM users WHERE username = ?', ['noowner']);
    if ($noOwnerQuery->count() == 0) {
        outputMessage($line++, "ERROR: noowner user not found - cannot reassign cars");
        return;
    }
    
    $noOwnerUserId = $noOwnerQuery->first()->id;
    outputMessage($line++, "Found noowner user (ID: $noOwnerUserId)");
    
    // Get orphaned cars
    $orphanedQuery = $db->query('SELECT c.id, c.user_id, c.chassis, c.year, c.model FROM cars c LEFT JOIN users u ON c.user_id = u.id WHERE u.id IS NULL AND c.user_id IS NOT NULL AND c.user_id > 0');
    $orphaned = $orphanedQuery->results();
    $count = count($orphaned);
    
    if ($count > 0) {
        outputMessage($line++, "Found $count orphaned cars to reassign");
        
        // Reassign cars to noowner
        foreach ($orphaned as $car) {
            // Update car ownership (triggers cars_hist automatically)
            $db->query('UPDATE cars SET user_id = ? WHERE id = ?', [$noOwnerUserId, $car->id]);
            
            // Add to car_user table
            $db->query('INSERT INTO car_user (userid, carid) VALUES (?, ?)', [$noOwnerUserId, $car->id]);
            
            outputMessage($line++, "  - Reassigned car ID {$car->id} ({$car->year} {$car->model}, chassis: {$car->chassis})");
        }
        
        outputMessage($line++, "Successfully reassigned $count cars to noowner user");
    } else {
        outputMessage($line++, "No orphaned cars found - database is clean");
    }
    
    outputMessage($line++, "");
}

/**
 * Display final statistics after cleanup
 */
function displayFinalStatistics()
{
    global $db, $line;
    
    outputMessage($line++, "=== FINAL STATISTICS ===");
    
    // Count users and profiles
    $userCount = $db->query('SELECT COUNT(*) as count FROM users')->first()->count;
    $profileCount = $db->query('SELECT COUNT(*) as count FROM profiles')->first()->count;
    $orphanedProfiles = $db->query('SELECT COUNT(*) as count FROM profiles p LEFT JOIN users u ON p.user_id = u.id WHERE u.id IS NULL')->first()->count;
    
    outputMessage($line++, "Users: $userCount");
    outputMessage($line++, "Profiles: $profileCount");
    outputMessage($line++, "Orphaned Profiles: $orphanedProfiles");
    
    // Count car relationships
    $carCount = $db->query('SELECT COUNT(*) as count FROM cars WHERE user_id > 0')->first()->count;
    $carUserCount = $db->query('SELECT COUNT(*) as count FROM car_user')->first()->count;
    $orphanedCarUser = $db->query('SELECT COUNT(*) as count FROM car_user cu LEFT JOIN users u ON cu.userid = u.id WHERE u.id IS NULL')->first()->count;
    $orphanedCars = $db->query('SELECT COUNT(*) as count FROM cars c LEFT JOIN users u ON c.user_id = u.id WHERE u.id IS NULL AND c.user_id IS NOT NULL AND c.user_id > 0')->first()->count;
    
    outputMessage($line++, "Cars with owners: $carCount");
    outputMessage($line++, "Car-User relationships: $carUserCount");
    outputMessage($line++, "Orphaned car_user records: $orphanedCarUser");
    outputMessage($line++, "Orphaned cars: $orphanedCars");
    
    if ($orphanedProfiles == 0 && $orphanedCarUser == 0 && $orphanedCars == 0) {
        outputMessage($line++, "✓ Database is now clean - no orphaned records found");
    }
    
    // Record script completion
    try {
        global $db;
        $db->query("INSERT INTO fix_script_runs (script_name) VALUES (?)", [basename(__FILE__)]);
        outputMessage($line++, "✓ Script completion recorded");
    } catch (Exception $e) {
        // Create table if it doesn't exist
        try {
            $db->query("CREATE TABLE IF NOT EXISTS fix_script_runs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                script_name VARCHAR(255) NOT NULL,
                completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_script_name (script_name)
            )");
            $db->query("INSERT INTO fix_script_runs (script_name) VALUES (?)", [basename(__FILE__)]);
            outputMessage($line++, "✓ Script completion recorded");
        } catch (Exception $create_e) {
            outputMessage($line++, "⚠ Could not record script completion");
        }
    }
    
    // Return to FIX menu button
    outputMessage($line++, "");
    echo '<div style="margin-top: 20px; text-align: center;">';
    echo '<button onclick="window.opener.location.reload(); window.close();" class="btn btn-outline-primary">';
    echo '<i class="fa fa-arrow-left" aria-hidden="true"></i> Return to FIX Menu';
    echo '</button>';
    echo '</div>';
}

/**
 * Output progress message with timestamp
 */
function outputMessage($current, $message)
{
    $pad = str_pad($message, 100, '.', STR_PAD_RIGHT);
    echo "<span style='position: absolute;z-index:$current;background:#FFF;'>"
        . " " . date('h:i:sa') . " - " . $pad . "<br></span>";
    myFlush();
}

/**
 * Flush output buffer for real-time display
 */
function myFlush()
{
    echo str_repeat(' ', 256);
    if (@ob_get_contents()) {
        @ob_end_flush();
    }
    flush();
}