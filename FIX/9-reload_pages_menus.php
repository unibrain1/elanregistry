<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../users/init.php';

// Get the DB
$db = DB::getInstance();


echo "Reload Pages and Menus<br>";

// Load pages first because it sets security
$pages='../SQL/pages.sql';
$menu='../SQL/menus.sql';

loadSQL($pages);
loadSQL($menu);


function loadSQL($filename)
{
    global $db;
    echo "Loading information: " . $filename . "<br>";

    // Temporary variable, used to store current query
    $templine = '';
    // Read in entire file
    $lines = file($filename);
    // Loop through each line
    foreach ($lines as $line) {
        // Skip it if it's a comment
        if (substr($line, 0, 2) == '--' || $line == '') {
            continue;
        }

        // Add this line to the current segment
        $templine .= $line;
        // If it has a semicolon at the end, it's the end of the query
        if (substr(trim($line), -1, 1) == ';') {
            // Perform the query
            $db->query($templine) or print('Error performing query \'<strong>' . $templine . '\': ' . mysql_error() . '<br /><br />');
            // Reset temp variable to empty
            $templine = '';
        }
    }


    echo "$filename imported successfully<br>";
}
