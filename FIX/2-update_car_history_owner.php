

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


// Update Car History with owners
$records = $db->query("SELECT id,car_id FROM cars_hist")->results();
foreach ($records as $record) {
    echo "Fixing record ".$record->id." Car_ID ".$record->car_id;

    //Find the owner of the car
    $user = $db->query("SELECT * FROM car_user WHERE carid =?", [$record->car_id])->results();
    if ($db->count() == 0) {
        echo " Car no longer exists";
    } else {
        echo " Owner ".$user[0]->userid;

        // Now get the users information

        $userQ = $db->query("SELECT * FROM usersview WHERE id = ?", [$user[0]->userid])->results();
    
        $fields['user_id']   = $userQ[0]->id;
        $fields['email']     = $userQ[0]->email;
        $fields['fname']     = $userQ[0]->fname;
        $fields['lname']     = $userQ[0]->lname;
        $fields['join_date'] = $userQ[0]->join_date;
        $fields['city']      = $userQ[0]->city;
        $fields['state']     = $userQ[0]->state;
        $fields['country']   = $userQ[0]->country;
        $fields['lat']       = $userQ[0]->lat;
        $fields['lon']       = $userQ[0]->lon;
        $fields['website']   = $userQ[0]->website;


        $db->update('cars_hist', $record->id, $fields);
    }

    echo "</br>" ;
}
