<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../users/init.php';


// Make sure all users have a valid join date and every car has a last update date.

// If the join date is the Epoch, make it match the car creation date
// If the car last update date is the Epoch, make it the car creation date
// Get the DB
$db = DB::getInstance();
echo "Updating join_date<br>";

$users = $db->query("SELECT * FROM users WHERE join_date LIKE '1969-12-31%' ")->results();
foreach ($users as $user) {
    echo "Fixing user_id ". $user->id ;

    // Find the users car create time
    $userQ = $db->query("SELECT * FROM users_carsview WHERE user_id = ?", [$user->id]);
    if ($db->count() == 0) {
        echo " - No car found";
    } else {
        $ctime = $userQ->results()[0]->ctime;
        echo "  ctime ". $ctime;
        $db->query('UPDATE users SET join_date = ? WHERE  id = ?', [$ctime, $user->id]);
    }
    
    echo "</br>" ;
}

echo "Updating Last Update<br>";

$cars = $db->query("SELECT * FROM cars WHERE mtime LIKE '1969-12-31%'")->results();
foreach ($cars as $car) {
    echo "Fixing car ". $car->id ;

    $ctime = $car->ctime;
    echo "  ctime ". $ctime;
    $db->query('UPDATE cars SET mtime = ? WHERE  id = ?', [$ctime, $car->id]);

    // Now delete the history record
    $id = $db->query('SELECT car_id, id, MAX(timestamp) AS max FROM cars_hist where car_id = ? GROUP BY id, car_id ORDER BY `max` DESC LIMIT 1', [$car->id])->first()->id;
    $db->deleteById("cars_hist", $id);

    echo " Last history record is ".$id;
    
    echo "</br>" ;
}
