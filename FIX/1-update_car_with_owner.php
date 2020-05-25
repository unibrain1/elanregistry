
<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "Updating all cars with owner information</br>";


require_once '../users/init.php';

$fields = [];

// The DB for SPICE should already be open!
$db = DB::getInstance();

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
    $fields['id']        = $car->id;

    // Turn off triggers, do the UPDATE, turn on triggers.  Cannot use the builtin db->update so do it the hard way
    //$db->update('cars', $car->id, $fields);

    $db->query("SET @disable_triggers = 1;
    UPDATE cars SET user_id = ?, email = ?, fname = ?, lname = ?, join_date = ?, city = ?, state = ?, country = ?, lat = ?, lon = ?, website = ? WHERE id = ?;
    SET @disable_triggers = NULL;", $fields);

    
    if ($db->error()) {
        echo " ERROR ";
        echo $db->errorString();
    } else {
        echo " SUCCESS";
    }
    echo "</br>";
}
