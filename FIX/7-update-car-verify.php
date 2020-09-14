<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../users/init.php';

// Get the DB
$db = DB::getInstance();

echo "Updating car verification information<br>";

// For every car that has been verified, remove the vericode

$cars = $db->query("SELECT id from cars where last_verified != '' ")->results();

foreach ($cars as $car) {
    echo "Fixing car_id ". $car->id ;

    $db->query("SET @disable_triggers = 1;UPDATE cars SET vericode = '', last_verified = NULL WHERE id = ?", [$car->id]);

    echo "</br>" ;
}
