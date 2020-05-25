
<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


require_once '../users/init.php';
// require_once $abs_us_root.$us_url_root.'users/includes/header.php';
// require_once $abs_us_root.$us_url_root.'users/includes/navigation.php';

$fields = [];

// The DB for SPICE should already be open!
$db = DB::getInstance();
echo "Updating all cars with owner information</br>";

// This should update all the users to make sure they have a profile
$cars = $db->query("SELECT id FROM cars")->results();
foreach ($cars as $car) {
    echo "Fixing car_id ". $car->id ;

    // Find the user
    $ctime = $db->query("SELECT ctime FROM cars WHERE id = ?", [$car->id])->results()[0]->ctime;
    echo "  ctime ". $ctime;
    
    $db->query('UPDATE cars_hist SET timestamp = ? WHERE  operation = "INSERT" AND car_id = ?', [$ctime, $car->id]);
    
    echo "</br>" ;
}
