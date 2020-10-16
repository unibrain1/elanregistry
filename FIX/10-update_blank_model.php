
<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "Updating blank model information</br>";


require_once '../users/init.php';

$fields = [];

// The DB for SPICE should already be open!
$db = DB::getInstance();

// This should update all the users to make sure they have a profile
$cars = $db->query("SELECT * FROM cars	 WHERE model = '' ")->results();
foreach ($cars as $car) {
    echo "Fixing car_id ". $car->id ;

    $fields['model']     = $car->series  . "|" . $car->variant . "|" . $car->type;
    $fields['id']        = $car->id;

    echo " - ". $fields['model'] ." - ";

    // Turn off triggers, do the UPDATE, turn on triggers.  Cannot use the builtin db->update so do it the hard way
    //$db->update('cars', $car->id, $fields);

    $db->query("SET @disable_triggers = 1;  UPDATE cars SET model = ?  WHERE id = ?;  SET @disable_triggers = NULL;", $fields);

    
    if ($db->error()) {
        echo " ERROR ";
        echo $db->errorString();
    } else {
        echo " SUCCESS";
    }
    echo "</br>";
}
