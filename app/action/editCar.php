<?php

// Check to see if the chassis number is taken
require_once '../../users/init.php';

$settings = getSettings();  // Get global settings from plugin

// A place to put some messages
$errors     = [];
$successes  = [];
$cardetails = [];
$carId      = null;


$targetFilePath = $abs_us_root . $us_url_root . $settings->elan_image_dir;
$targetURL = $us_url_root . $settings->elan_image_dir;

//Forms posted now process it
if (!empty($_POST)) {
    $token = Input::get('csrf');
    if (!Token::check($token)) {
        include($abs_us_root . $us_url_root . 'usersc/scripts/token_error.php');
    } else {
        $db = DB::getInstance();

        $action = Input::get('action');
        switch ($action) {
            case "addCar":
                buildCarDetails($cardetails);
                buildImageDetails($cardetails);
                if (empty($errors)) {
                    addCar($cardetails);
                } else {
                    $errors[] = 'Add_Car: Cannot add record';
                }
                break;
            case "updateCar":
                buildCarDetails($cardetails, Input::get('carid'));
                buildImageDetails($cardetails);

                if (empty($errors)) {
                    updateCar($cardetails);
                } else {
                    $errors[] = 'Update_Car: Cannot add record';
                }
                break;
            case "fetchImages":
                $carid = Input::get('carID');
                fetchImages($carid);
                break;
            case "removeImages":
                $carid = Input::get('carID');
                $file = Input::get('file');
                removeImage($carid, $file);
                break;
            default:
                $errors[] = "No valid action";
        }

        $response = array(
            'status'     => (!empty($errors)) ? 'error' : 'success',
            'action'     => $action,
            'info'       => array_merge($successes,  $errors),
            'cardetails' => $cardetails
        );
        logger($user->data()->id, "ElanRegistry: ", "Action: " . $response['action'] . " Status: " . $response['status'] . "  carID: " . $cardetails['id'] . "Messages: " . json_encode($response['info']) . " Data: " . json_encode($response['cardetails']));

        // Blanks instead of NULL for display
        foreach ($response['cardetails'] as $key => $value) {
            if (is_null($value)) {
                $response['cardetails'][$key] = "";
            }
        }
        echo json_encode($response);
    } // End Post with data
}


function updateCar(&$cardetails)
{
    global $errors;
    global $successes;

    $car = new Car();

    // Update 
    if ($car->update($cardetails)) {
        $successes[] = 'Update Car ID: ' . $car->data()->id;
        $successes[] = 'Update BY ID: ' . $car->data()->user_id;
    } else {
        $errors[] = 'Update Car ERROR';
    }
}
function addCar(&$cardetails)
{
    global $errors;
    global $successes;
    $car = new Car();

    if ($car->create($cardetails)) {
        $successes[] = 'Add Car ID: ' . $car->data()->id;
        $successes[] = 'Added by User ID: ' . $car->data()->user_id;
        $cardetails['id'] = $car->data()->id;
    } else {
        $errors[] = 'Car Create ERROR';
    }
}

function buildCarDetails(&$cardetails, $carId = null)
{
    global $user;
    global $errors;
    global $successes;
    global $db;

    // Get the combined user+profile
    if ($carId) {
        $car = new Car($carId);
        foreach ($car->data() as $key => $value) {
            $cardetails[$key] = $value;
        }
    } else {
        $userQ = $db->findById($user->data()->id, "usersview")->results()[0];

        /*  Add the User/profile information to the record */
        $cardetails['user_id']      = $userQ->id;
        $cardetails['email']        = $userQ->email;
        $cardetails['fname']        = $userQ->fname;
        $cardetails['lname']        = $userQ->lname;
        $cardetails['join_date']    = $userQ->join_date;
        $cardetails['city']         = $userQ->city;
        $cardetails['state']        = $userQ->state;
        $cardetails['country']      = $userQ->country;
        $cardetails['lat']          = $userQ->lat;
        $cardetails['lon']          = $userQ->lon;

        $cardetails['id']           = null;
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
    }
    updateYear($cardetails);
    updateModel($cardetails);
    updateChassis($cardetails);
    updateColor($cardetails);
    updateEngine($cardetails);
    updatePurchasedate($cardetails);
    updateSolddate($cardetails);
    updateWebsite($cardetails);
    updateComments($cardetails);
}

function updateYear(&$cardetails)
{
    //Update Year
    if (!empty($_POST['year'])) {
        $cardetails['year'] =  Input::get('year');
        $successes[] = 'Year Updated (' . $cardetails['year'] . ')';
    } else {
        $errors[] = "Please select Year";
    }
}

function updateModel(&$cardetails)
{
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
}

function updateChassis(&$cardetails)
{
    // Update 'chassis'
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
    } else {
        $errors[] = "Please enter chassis number";
    }
}

function updateColor(&$cardetails)
{
    // Update 'color' 
    if (!empty($_POST['color'])) {
        $cardetails['color'] = Input::get('color');
        $successes[] = 'Color Updated (' . $cardetails['color'] . ')';
    } else {
        $cardetails['color'] = null;
    }
}

function updateEngine(&$cardetails)
{
    // Update 'engine' 
    if (!empty($_POST['engine'])) {
        $cardetails['engine'] = Input::get('engine');
        $cardetails['engine'] = str_replace(" ", "", strtoupper(trim($cardetails['engine'])));
        $successes[] = 'Engine Updated (' . $cardetails['engine'] . ')';
    } else {
        $cardetails['engine'] = null;
    }
}

function updatePurchasedate(&$cardetails)
{
    // Update 'purchasedate'
    if (!empty($_POST['purchasedate'])) {
        $cardetails['purchasedate'] = Input::get('purchasedate');
        $cardetails['purchasedate'] = date("Y-m-d", strtotime($cardetails['purchasedate']));
        $successes[] = 'Purchase Date Updated (' . $cardetails['purchasedate'] . ')';
    } else {
        $cardetails['purchasedate'] = null;
    }
}

function updateSolddate(&$cardetails)
{
    // Update 'solddate' 
    if (!empty($_POST['solddate'])) {
        $cardetails['solddate'] = Input::get('solddate');
        $cardetails['solddate'] = date("Y-m-d", strtotime($cardetails['solddate']));
        $successes[] = 'Sold Date Updated (' . $cardetails['solddate'] . ')';
    } else {
        $cardetails['solddate'] = null;
    }
}

function updateWebsite(&$cardetails)
{
    // Update 'website' 
    if (!empty($_POST['website'])) {
        $cardetails['website'] = Input::get('website');
        $successes[] = 'Website Updated (' . $cardetails['website'] . ')';
    } else {
        $cardetails['website'] = null;
    }
}

function updateComments(&$cardetails)
{
    // Update 'comments' 
    if (!empty($_POST['comments'])) {
        $cardetails['comments'] = Input::get('comments');
        $successes[] = 'Comments Updated (' . $cardetails['comments'] . ')';
    } else {
        $cardetails['comments'] = null;
    }
}

function buildImageDetails(&$cardetails)
{
    global $targetFilePath;
    global $errors;
    global $successes;
    global $db;
    global $user;

    $sizes = [100, 300, 600, 1024, 2048];  // Should get from config

    // Order of all images in the dropzone
    $requestedOrder = array_filter(explode(',', $_POST['filenames']));
    $cardetails['image'] = implode(',', $requestedOrder);


    // Do I have any new files?
    if ($_FILES['file']['name'][0] == 'blob') {
        $successes[] = 'No image';
        return;
    }
    //  $_FILES['file']['tmp_name'] is an array so have to use loop
    foreach ($_FILES['file']['tmp_name'] as $key => $value) {
        $name  = $_FILES['file']['name'][$key];
        $tempFile = $_FILES['file']['tmp_name'][$key];

        if ($tempFile !== '') { //  deal with empty file name
            // Create a filename for the new file and give the file a random name
            $newFileName = uniqid('img_', 'true') . '.' . getExtension(get_mime_type($tempFile));

            if (move_uploaded_file($tempFile, $targetFilePath . $newFileName)) {
                $successes[] = "Photo has been uploaded " . $name . " as " . $newFileName;

                //  Create resized images
                $fileinfo = pathinfo($targetFilePath . $newFileName);
                $filename = $fileinfo['filename'];
                $extension = $fileinfo['extension'];

                foreach ($sizes as $size) {
                    $thumbname = $targetFilePath . $filename . "-resized-" . $size . "." . $extension;

                    $resizeObj = new Resize($targetFilePath . $newFileName);
                    $resizeObj->resizeImage($size, $size, 'auto');
                    $resizeObj->saveImage($thumbname, 80);
                }
                array_replace_value($requestedOrder, $name, $newFileName);
            } else {
                $errors[] = "Photo failed to uploaded " . $name . " as " . $newFileName;
                logger($user->data()->id, "ElanRegistry", "ERROR: buildImageDetails carId: " . Input::get('carid') . " Photo failed to uploaded " . $name . " as " . $newFileName);
            }
        }
    }
    $cardetails['image'] = implode(',', $requestedOrder);
}

function fetchImages($carid)
{
    global $db;

    $car = new Car($carid);

    $car->images();

    $listQ = $db->query("SELECT image FROM cars WHERE id = ?", [$carid]);

    if ($listQ->count() > 0) {
        $list = $listQ->results()[0]->image;

        if ($list === "") {
            $response = array(
                'status' => 'No images'
            );
        } else {
            $images = explode(',', $list);

            $response = array('status' => 'success');
            for ($i = 0; $i < count($images); $i++) {
                $response['image'][$i]['name'] =  $images[$i];
            }
        }
    } else {
        $response = array(
            'status' => 'No images'
        );
    }

    echo json_encode($response);
    exit;
}

function removeImage($carID, $file)
{
    global $db;
    global $user;

    $car = $db->findById($carID, 'cars')->results()[0];
    $carImages = explode(',', $car->image);
    $idx = array_search($file, $carImages);

    if ($idx !== false) {
        unset($carImages[$idx]);
        // Write the new array to the DB
        $images = implode(',', $carImages);
        $db->update("cars", $carID, ["image" => $images]);

        $response = array(
            'status' => 'success',
            'count'   => count($carImages),
            'images' => $carImages
        );
    } else {
        logger($user->data()->id, "ElanRegistry", "ERROR: removeImage carId: " . $carID . "Image not found: " . $file);
        $response = array(
            'status' => 'error',
            'info'   => "image not found"
        );
    }
    echo json_encode($response);
    exit;
}

function array_replace_value(&$ar, $value, $replacement)
{
    if (($key = array_search($value, $ar)) !== FALSE) {
        $ar[$key] = $replacement;
    }
}

function getExtension($mime_type)
{
    $extensions = array(
        'image/jpeg' => 'jpg'
    );
    return $extensions[$mime_type];
}

function get_mime_type($file)
{
    $mtype = false;
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mtype = finfo_file($finfo, $file);
        finfo_close($finfo);
    } elseif (function_exists('mime_content_type')) {
        $mtype = mime_content_type($file);
    }
    return $mtype;
}
