<?php

function var_error_log($object = null)
{
    // ob_start(); // start buffer capture
    // var_dump($object); // dump the values
    // $contents = ob_get_contents(); // put the buffer into a variable
    // ob_end_clean(); // end capture
    // error_log($contents); // log contents of the result of var_dump( $object )
}

// Check to see if the chassis number is taken
require_once '../../users/init.php';

// A place to put some messages
$errors     = [];
$successes  = [];
$cardetails = [];
$carID      = null;

//Forms posted now process it
if (!empty($_POST)) {
    $token = Input::get('csrf');
    if (!Token::check($token)) {
        include($abs_us_root . $us_url_root . 'usersc/scripts/token_error.php');
    } else {
        $action = Input::get('action');
        switch ($action) {
            case "add_car":
                buildCarDetails($cardetails);
                if (empty($errors)) {
                    addCar($cardetails);
                } else {
                    $errors[] = 'Add_Car: Cannot add record';
                }
                break;
            case "update_car":
                buildCarDetails($cardetails, Input::get('car_id'));
                if (empty($errors)) {
                    updateCar($cardetails);
                } else {
                    $errors[] = 'Update_Car: Cannot add record';
                }
                break;
            default:
                $errors[] = "No valid action";
        }

        $response = array(
            'action' => $action,
            'carID'  => $cardetails['id'],
            'status' => (!empty($errors)) ? 'error' : 'success',
            'info'   => array_merge($successes,  $errors),
        );
        logger($user->data()->id, "ElanRegistry", "carUpdate carID: " . $response['carID'] . " Status: " . $response['status']);

        echo json_encode($response);
    } // End Post with data
}

// Update global cardetails
function buildCarDetails(&$cardetails, $carId = null)
{
    global $user;
    global $errors;
    global $successes;

    // Get the DB
    $db = DB::getInstance();

    // Get the combined user+profile
    $userQ = $db->findById($user->data()->id, "usersview");
    $userData = $userQ->results();

    /*  Add the User/profile information to the record */
    $cardetails['user_id']      = $userData[0]->id;
    $cardetails['email']        = $userData[0]->email;
    $cardetails['fname']        = $userData[0]->fname;
    $cardetails['lname']        = $userData[0]->lname;
    $cardetails['join_date']    = $userData[0]->join_date;
    $cardetails['city']         = $userData[0]->city;
    $cardetails['state']        = $userData[0]->state;
    $cardetails['country']      = $userData[0]->country;
    $cardetails['lat']          = $userData[0]->lat;
    $cardetails['lon']          = $userData[0]->lon;

    $cardetails['id']           = $carId;
    $cardetails['year']         = null;
    $cardetails['model']        = null;
    $cardetails['series']       = null;
    $cardetails['variant']      = null;
    $cardetails['type']         = null;
    $cardetails['chassis']      = null;
    $cardetails['color']        = null;
    $cardetails['engine']       = null;
    $cardetails['purchasedate'] = null;
    $cardetails['solddate']     = null;
    $cardetails['website']      = null;
    $cardetails['comments']     = null;

    //Update Year
    if (!empty($_POST['year'])) {

        $cardetails['year'] = Input::get('year');
        $successes[] = 'Year Updated (' . $cardetails['year'] . ')';
    } else {
        $errors[] = "Please select Year";
    }

    // Update 'model'
    if (!empty($_POST['model'])) {
        $cardetails['model'] = Input::get('model');
        // Model isn't really a thing.
        // We need to explode it into the proper columns
        list($series, $variant, $type) = explode('|', $cardetails['model']);
        /* MST value is from form, so I shouldn't have to do this but to be safe ... */
        $cardetails['series'] = filter_var($series, FILTER_SANITIZE_STRING);
        $cardetails['variant'] = filter_var($variant, FILTER_SANITIZE_STRING);
        $cardetails['type'] = filter_var($type, FILTER_SANITIZE_STRING);

        $successes[] = 'Model Updated (' . $cardetails['model'] . ')';
    } else {
        $errors[] = "Please select Model";
    }

    // Update 'chassis'
    // TODO - Complete chassis validation to match JS
    if (!empty($_POST['chassis'])) {
        $cardetails['chassis'] = Input::get('chassis');
        $len = strlen($cardetails['chassis']);
        // Validate
        if (strcmp($cardetails['variant'], 'Race') == 0) { /* For the 26R let them do what they want */
            $successes[] =  'Chassis Updated (' . $cardetails['chassis'] . ')';
        } elseif ($cardetails['year'] < 1970) {
            if ($len != 4) { // Chassis number for years < 1970 are 4 digits 
                $errors[] = "Enter Chassis Number. Four Digits,6490 not 36/6490";
            }
        } else {
            $successes[] =  'Chassis Updated (' . $cardetails['chassis'] . ')';
        }
    }
    // Update 'color' 
    if (!empty($_POST['color'])) {
        $cardetails['color'] = Input::get('color');
        $successes[] = 'Color Updated (' . $cardetails['color'] . ')';
    }
    // Update 'engine' 
    if (!empty($_POST['engine'])) {
        $cardetails['engine'] = Input::get('engine');
        $cardetails['engine'] = str_replace(" ", "", strtoupper(trim($cardetails['engine'])));
        $successes[] = 'Engine Updated (' . $cardetails['engine'] . ')';
    }

    // Update 'purchasedate'
    if (!empty($_POST['purchasedate'])) {
        $cardetails['purchasedate'] = Input::get('purchasedate');
        $cardetails['purchasedate'] = date(" Y-m-d H:i:s", strtotime($cardetails['purchasedate']));
        $successes[] = 'Purchase Date Updated (' . $cardetails['purchasedate'] . ')';
    }

    // Update 'solddate' 
    if (!empty($_POST['solddate'])) {
        $cardetails['solddate'] = Input::get('solddate');
        $cardetails['solddate'] = date("Y-m-d H:i:s", strtotime($cardetails['solddate']));
        $successes[] = 'Sold Date Updated (' . $cardetails['solddate'] . ')';
    }
    // Update 'website' 
    if (!empty($_POST['website'])) {
        $cardetails['website'] = Input::get('website');
        $successes[] = 'Website Updated (' . $cardetails['website'] . ')';
    }
    // Update 'comments' 
    if (!empty($_POST['comments'])) {
        $cardetails['comments'] = Input::get('comments');
        $successes[] = 'Comments Updated (' . $cardetails['comments'] . ')';
    }
}


function updateCar(&$cardetails)
{
    global $user;
    global $errors;
    global $successes;

    // Get the DB
    $db = DB::getInstance();

    // Update 
    $db->update('cars', $cardetails['id'], $cardetails);
    if ($db->error()) {
        $errors[] = 'DB ERROR' . $db->errorString();
        logger($user->data()->id, "ElanRegistry - Update", "edit_car error car " . $db->errorString());
    } else {
        // Grab the id of the last insert
        $successes[] = 'Update Car ID: ' . $cardetails['id'];
        $successes[] = 'Update BY ID: ' . $cardetails['user_id'];
    }
}
function addCar(&$cardetails)
{
    global $user;
    global $errors;
    global $successes;

    // Get the DB
    $db = DB::getInstance();

    // Insert
    // Add a create time
    $cardetails['ctime'] = date('Y-m-d G:i:s');

    $db->insert('cars', $cardetails);
    if ($db->error()) {
        $errors[] = 'DB ERROR' . $db->errorString();
        logger($user->data()->id, "ElanRegistry - Insert", "edit_car error car " . $db->errorString());
    } else {
        // Grab the id of the last insert
        $cardetails['id'] = $db->lastId();
        $successes[] = 'Add Car ID: ' . $cardetails['id'];
        $successes[] = 'Update User ID: ' . $user->data()->id;
        // then udate the cross reference table (user_car) with the car_id and user_id,
        $db->insert('car_user', array('userid' => $user->data()->id, 'carid' => $cardetails['id']));
    }
}
