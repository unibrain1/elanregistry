
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
    $userid = $db->query("SELECT userid FROM car_user WHERE carid = ?", [$car->id])->results()[0]->userid;
    echo "  user_id ". $userid;

    // Now get the users information
    
    $user = $db->query("SELECT * FROM usersview WHERE id = ?", [$userid])->results()[0];
    $fields['user_id']   = $userid;
    $fields['email']     = $user->email;
    $fields['fname']     = $user->fname;
    $fields['lname']     = $user->lname;
    $fields['join_date'] = $user->join_date;
    $fields['city']      = $user->city;
    $fields['state']     = $user->state;
    $fields['country']   = $user->country;
    $fields['lat']       = $user->lat;
    $fields['lon']       = $user->lon;
    $fields['website']   = $user->website;

    // $db->update('cars', $c->id, ['user_id'=>$userid], 'city'->$user->city);
    $db->update('cars', $car->id, $fields);

    echo "</br>" ;
}
