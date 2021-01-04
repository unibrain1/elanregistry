<?php // Allowed file types. This should also be reflected in getExtension

// Check to see if the chassis number is taken
require_once '../../users/init.php';
// Get the DB
$db = DB::getInstance();
//Forms posted now process it
if (!empty($_POST)) {
    $token = Input::get('csrf');
    if (!Token::check($token)) {
        include($abs_us_root . $us_url_root . 'usersc/scripts/token_error.php');
    } else {
        if (isset($_POST['command'])) {
            $command = $_POST['command'];
        } else {
            $response = array(
                'status' => 'error',
                'info'   => 'Invalid command'
            );
            echo json_encode($response);
            exit;
        }

        switch ($command) {
            case 'delete':
                delete(Input::get('target_file'), Input::get('carID'));
                break;
            case 'fetch':
                fetch(Input::get('carID'));
                break;

            default:
                $response = array(
                    'status' => 'error',
                    'info'   => 'Invalid command'
                );
                echo json_encode($response);
                exit;
        }
    }
}
// Remove the image entry
// TODO make sure there are values passed
function delete($image = null, $carID = null)
{
    global $db;
    global $user;
    // Remove file reference
    // Get the car images
    $car = $db->findById($carID, 'cars')->results()[0];
    $carImages = explode(',', $car->image);

    // Search for thr image in the array and remove it

    $pos = array_search($image, $carImages);
    if ($pos !== false) {
        unset($carImages[$pos]);
        // Write the new array to the DB
        $images = implode(',', $carImages);
        $db->update("cars", $carID, ["image" => $images]);

        $response = array(
            'status' => 'success',
            'count'   => count($carImages),
            'images' => $carImages
        );
    } else {

        $response = array(
            'status' => 'error',
            'info'   => "image not found"
        );
    }

    // Return the response
    echo json_encode($response);
    exit;
}

// Get the list of images for the car
function fetch($carID)
{
    global $db;
    global $user;

    // Get the car information
    $car = $db->findById($carID, 'cars')->results()[0];

    $carImages = explode(',', $car->image);

    $j = count($carImages);
    if ($j === 0 || $carImages[0] == '') {
        $response = array(
            'status' => 'success',
            'count'   => 0
        );
    } else {


        $response = array(
            'status' => 'success',
            'count'   => $j,
            'images' => $carImages
        );
    }

    // Return the response
    echo json_encode($response);
    exit;
}
