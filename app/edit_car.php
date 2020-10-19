<?php
require_once '../users/init.php';
require_once $abs_us_root.$us_url_root.'users/includes/template/prep.php';
require_once 'validate.php';

if (!securePage($_SERVER['PHP_SELF'])) {
    die();
}

// A place to store default values and what the user entered
$select_str                 = 'Please Select';

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
$cardetails['website']      = $userData[0]->website;

$cardetails['id']           = null;
$cardetails['year']         = $select_str;
$cardetails['model']        = $select_str;
$cardetails['series']       = null;
$cardetails['variant']      = null;
$cardetails['type']         = null;
$cardetails['chassis']      = null;
$cardetails['color']        = null;
$cardetails['engine']       = null;
$cardetails['purchasedate'] = null;
$cardetails['solddate']     = null;
$cardetails['comments']     = null;
$cardetails['image']        = null;

// 'placeholder' to prompt for response.  Background text in input boxes
$carprompt['chassis']       = 'Enter Chassis Number - Pre 1970 - xxxx, 1970 and on 70xxyy0001z';
$carprompt['color']         = 'Enter the current color of the car';
$carprompt['engine']        = 'Enter Engine number - LPAxxxxx';
$carprompt['purchasedate']  = 'YYYY-MM-DD';
$carprompt['solddate']      = 'YYYY-MM-DD';
$carprompt['comments']      = 'Please give a brief history of your car and anything special';

// A place to put some messages
$errors                     = [];
$successes                  = [];

// A place for the car History if it exists
$carHist                    = null;

// Default page title
$title = 'Add Car';
$action = null; // No one has asked me to do anything yet

function var_error_log($object=null)
{
    ob_start();                    // start buffer capture
    var_dump($object);           // dump the values
    $contents = ob_get_contents(); // put the buffer into a variable
    ob_end_clean();                // end capture
    error_log($contents);        // log contents of the result of var_dump( $object )
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


//Forms posted now process it
if (!empty($_POST)) {
    $token = $_POST['csrf'];
    if (!Token::check($token)) {
        include($abs_us_root . $us_url_root . 'usersc/scripts/token_error.php');
    } else {
        $action = ($_POST['action']);
        $action = Input::sanitize($action);

        switch ($action) {
            case "add_car":
                $title = 'Add Car';
                add_car($_POST);
                break;
            case "update_car":
                $title = 'Update Car';
                update_car($_POST);
               // add_car( $_POST, $cardetails);
                break;
            default:
                $errors[] = "No valid action";
        }
    } // End Post with data
} // End Post


function add_car($post)
{
    // Configuration
    // directory in which the uploaded file will be moved
    $uploadFileDir = './userimages/';
    $thumbnailDir = $uploadFileDir . 'thumbs/';

    global $cardetails;
    global $user;
    global $db;
    global $us_url_root;
    global $errors;
    global $successes;

    if (isset($_POST['car_id'])) {
        $cardetails['id'] = Input::sanitize($_POST['car_id']);
    }

    // This is the name of the last image
    $image = ($_POST['image']);
    $cardetails['image'] = Input::sanitize($image);

    //Update Year
    $year = ($_POST['year']);
    $year = Input::sanitize($year);
    if ($year == 0) {
        $errors[] = "Please select Year";
    } else {
        $cardetails['year'] = $year;
        $successes[] = 'Year Updated ('.$year.')';
    }

    // Update 'model'
    //
    $model = ($_POST['model']);
    $model = Input::sanitize($model);
    if (strcmp($model, "") == 0) {
        $errors[] = "Please select Model ".$model." strcmp" . strcmp($model, "");
    } else {
        // Model isn't really a thing.
        //      We need to explode it into the proper columns
        $cardetails['model'] = $model;  // Still save it for later so we can remember what the user entered
        list($series, $variant, $type) = explode('|', $model);
        /* MST value is from form, so I shouldn't have to do this but to be safe ... */
        $cardetails['series'] = filter_var($series, FILTER_SANITIZE_STRING);
        $cardetails['variant'] = filter_var($variant, FILTER_SANITIZE_STRING);
        $cardetails['type'] = filter_var($type, FILTER_SANITIZE_STRING);

        $successes[] = 'Model Updated ('.$model.')';
    }

    // Update 'chassis'
    $chassis = ($_POST['chassis']);
    $chassis = Input::sanitize($chassis);
    $len = strlen($chassis);
    $cardetails['chassis'] = filter_var($chassis, FILTER_SANITIZE_STRING);
    // Validate
    if (strcmp($cardetails['variant'], 'Race') == 0) { /* For the 26R let them do what they want */
        $successes[] = 'Chassis Updated';
    } elseif ($year < 1970) {
        if ($len != 4) { // Chassis number for years < 1970 are 4 digits
            $errors[] = "Enter Chassis Number. Four Digits,6490 not 36/6490";
        }
        // } elseif ($len != 11) { 	// Chassis number for years >= 1970 are 11 digits
            //     $errors[] = "Enter Chassis Number. 70xxyy0001z";
    } else {
        $successes[] = 'Chassis Updated';
    }


    // Update 'color'
    $color = ($_POST['color']);
    $color = Input::sanitize($color);
    if (strcmp($color, "") != 0) {
        $successes[] = 'Color Updated ('.$color.')';
        $cardetails['color'] = filter_var($color, FILTER_SANITIZE_STRING);
    }

    // Update 'engine'
    $engine = ($_POST['engine']);
    $engine = Input::sanitize($engine);
    if (strcmp($engine, "") != 0) {
        $cardetails['engine'] = filter_var(str_replace(" ", "", strtoupper(trim($engine))), FILTER_SANITIZE_STRING);
        $successes[] = 'Engine Updated ('.$engine.')';
    }

    // Update 'purchasedate'
    $purchasedate = ($_POST['purchasedate']);
    if (!empty($purchasedate)) {
        $purchasedate = Input::sanitize($purchasedate);
        // Convert to SQL date format
        if ($purchasedate = date("Y-m-d H:i:s", strtotime($purchasedate))) {
            $cardetails['purchasedate'] = filter_var($purchasedate, FILTER_SANITIZE_STRING);
            $successes[] = 'Purchased Updated ('.$purchasedate.')';
        } else {
            $errors[] = "Purchase Date conversion error";
        }
    }


    // Update 'solddate'
    $solddate = ($_POST['solddate']);
    if (!empty($solddate)) {
        $solddate = Input::sanitize($solddate);
        // Convert to SQL date format
        if ($solddate = date("Y-m-d H:i:s", strtotime($solddate))) {
            $cardetails['solddate'] = filter_var($solddate, FILTER_SANITIZE_STRING);
            $successes[] = 'Sold Date Updated ('.$solddate.')';
        } else {
            $errors[] = "Sold Date conversion error";
        }
    }

    // Update 'comments'
    $comments = ($_POST['comments']);
    $comments = Input::sanitize($comments);
    if (strcmp($comments, "") != 0) {
        $cardetails['comments'] = filter_var($comments, FILTER_SANITIZE_STRING);
        $successes[] = 'Comment Updated';
    }

    // Update 'image'
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        // check if file has one of the allowed mime types
        $mime_type = get_mime_type($_FILES['file']['tmp_name']);

        $allowed_file_types = ['image/png', 'image/jpeg'];
        if (in_array($mime_type, $allowed_file_types)) {
            // get extenstion of the uploaded file
            $fileNameCmps = explode(".", $_FILES['file']['error']);
            $fileExtension = strtolower(end($fileNameCmps));

            //  give the file a random name
            $newFileName = uniqid('img_', 'true') . '.' . $fileExtension;
            
            $dest_path = $uploadFileDir . $newFileName;
            $thumb_dest_path = $thumbnailDir . $newFileName;

            if (move_uploaded_file($_FILES['file']['tmp_name'], $dest_path)) {
                $successes[] = 'Image is successfully uploaded.' . $newFileName;

                // Resize the image
                list($width, $height) = getimagesize($dest_path);
                $modwidth = 600;  // Only 600 wide
                $diff = $width / $modwidth;
                $modheight = $height / $diff;
                $tn = imagecreatetruecolor($modwidth, $modheight);
                $image = imagecreatefromjpeg($dest_path);
                imagecopyresampled($tn, $image, 0, 0, 0, 0, $modwidth, $modheight, $width, $height);
                imagejpeg($tn, $dest_path, 100);
                $successes[] = 'Image Resized';

                // Create a thumbnail
                list($width, $height) = getimagesize($dest_path);
                $modwidth = 80;
                $diff = $width / $modwidth;
                $modheight = $height / $diff;
                $tn = imagecreatetruecolor($modwidth, $modheight);
                $image = imagecreatefromjpeg($dest_path);
                imagecopyresampled($tn, $image, 0, 0, 0, 0, $modwidth, $modheight, $width, $height);
                imagejpeg($tn, $thumb_dest_path, 80);
                $successes[] = 'Image Thumbnail';

                $cardetails['image'] = $newFileName;
            } else {
                $errors[] = 'There was some error moving the file to upload directory. Please make sure the upload directory is writable by web server.';
            }
        } else {
            $errors[] = "Upload failed. You tries to upload <?=$mime_type?> Allowed file types: " . implode(',', $allowed_file_types);
        }
    }

    // If there are no errors then INSERT the $cardetails into the DB,
    if (empty($errors)) {

        // Is this an update or an insert?
        if (isset($cardetails['id'])) {
            // Update
            $db->update('cars', $cardetails['id'], $cardetails);
            if ($db->error()) {
                $errors[] = 'DB ERROR' . $db->errorString();
                logger($user->data()->id, "ElanRegistry - Update", "edit_car error car " . $db->errorString());
            } else {
                // Grab the id of the last insert
                $successes[] = 'Update Car ID: ' . $cardetails['id'];
                $successes[] = 'Update BY ID: ' . $cardetails['user_id'];

                // then log it
                logger($user->data()->id, "ElanRegistry", "Updated car ID " . $cardetails['id']);

                // then redirect to User Account Page
                Redirect::to($us_url_root . 'users/account.php');
            }
        } else {
            // Insert
            // Add a create time
            $cardetails['ctime'] = date('Y-m-d G:i:s');

            $db->insert('cars', $cardetails);
            
            if ($db->error()) {
                $errors[] = 'DB ERROR' . $db->errorString();
                logger($user->data()->id, "ElanRegistry - Insert", "edit_car error car " . $db->errorString());
            } else {
                // Grab the id of the last insert
                $car_id = $db->lastId();
                $successes[] = 'Add Car ID: ' . $car_id;
                $successes[] = 'Update User ID: ' . $user->data()->id;
                // then log it
                logger($user->data()->id, "ElanRegistry", "Added car " . $car_id);
                // then udate the cross reference table (user_car) with the car_id and user_id,
                $db->insert('car_user', array('userid' => $user->data()->id, 'carid' => $car_id));
                // then redirect to User Account Page
                Redirect::to($us_url_root . 'users/account.php');
            }
        }
    } else {
        $errors[] = 'Cannot add record';
    }
}

/*
 * Fill out the form with the existing values
 */
function update_car($post)
{
    global $cardetails;
    global $user;
    global $db;
    global $us_url_root;
    global $errors;
    global $successes;
    global $carHist;

    $car_id = $_POST['car_id'];

    if (!empty($car_id)) {
        //  Let's check to see if this user owns this car`
        $userQ = $db->get('car_user', ['AND',  ['userid', '=', $user->data()->id], ['carid', '=', $car_id]]);

        if ($userQ->count() > 0) {
            // Owner so get the car
            $carQ =  $db->get("cars", ["id",  "=", $car_id]);
            $theCar = $carQ->results();
            // Get the details from the current car
            $cardetails['id']           = $theCar[0]->id;
            $cardetails['year']         = $theCar[0]->year;
            $cardetails['model']        = $theCar[0]->model;
            $cardetails['series']       = $theCar[0]->series;
            $cardetails['variant']      = $theCar[0]->variant;
            $cardetails['type']         = $theCar[0]->type;
            $cardetails['chassis']      = $theCar[0]->chassis;
            $cardetails['color']        = $theCar[0]->color;
            $cardetails['engine']       = $theCar[0]->engine;
            $cardetails['purchasedate'] = $theCar[0]->purchasedate;
            $cardetails['solddate']     = $theCar[0]->solddate;
            $cardetails['comments']     = $theCar[0]->comments;
            $cardetails['image']        = $theCar[0]->image;

            // Get the car history for display
            $carQ = $db->query('SELECT * FROM cars_hist WHERE car_id=? ORDER BY cars_hist.timestamp DESC', [$car_id]);
            $carHist = $carQ->results();
        } else {
            // This should never happen unless the user is trying to do something bad.  Log it and then log them out
            logger($user->data()->id, "ElanRegistry", "Not owner of car! USER " . $user->data()->id . " CAR " . $car_id);
            $user->logout();
            Redirect::to($us_url_root . 'index.php');

            exit();
        }
    } else { /* Empty Car */
        logger($user->data()->id, "ElanRegistry", "Empty car_id field in GET");
        Redirect::to($us_url_root . 'users/account.php');
    } // empty $car_id
}
?>

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="well">
        <br> 
        <form method="POST" name="addCar" action="edit_car.php" enctype="multipart/form-data">
            <div class="row">
                <div class="col-md-6">  <!-- Car Info -->
                    <div class="card card-default">
                        <div class="card-header"><h2><strong><?=$title?></strong></h2></div>
                        <div class="card-body">
                            <!-- Here is a place to display errors -->
                            <?php
                            if (!$errors == '') {
                                ?><div class="alert alert-danger"><?= display_errors($errors); ?></div><?php
                            }
                            ?>
                            <?php if (!$successes == '') {
                                ?><div class="alert alert-success"><?= display_successes($successes); ?></div><?php
                            }
                            ?>
                            <!-- Here is the FORM -->
                            <?php include($abs_us_root.$us_url_root.'app/views/_car_table.php'); ?>
                        </div> <!-- card-body -->
                    </div>   
                </div>
                <div class="col-md-6"> <!-- Image Info -->
                    <div class="card card-default">
                    <div class="card-header"><h2><strong>Photos</strong></h2></div>
                        <div class="card-body">    
                                <?php include($abs_us_root.$us_url_root.'app/views/_image_table.php'); ?>
                        </div>
                    </div> <!-- card -->
                </div> <!--col -->
            </div> <!-- /.row -->
            <div class="row">
                <div class="col-sm-4">
                </div>
                <div class="col-sm-4">
                    <input type="hidden" name="csrf" id="csrf" value="<?= Token::generate(); ?>" />
                    <input type="hidden" name="action" id="action" value="add_car" />
                    <?php
                        if (isset($cardetails['id'])) { ?>
                            <input type="hidden" name="car_id" id="action" value="<?=$cardetails['id']?>" />
                        <?php } ?>
                    <input type="hidden" name="image" value="<?= $cardetails['image'] ?>" />
                    <input class='bbtn btn-success btn-lg btn-block' type='submit' id='submit' class='submit' />
                    <a class="btn btn-info btn-lg btn-block" href=<?= $us_url_root ?>users/account.php>Cancel </a> 
                </div>
                <div class="col-sm-4">
                </div>
            </div> <!-- /.row -->
        </form> 

        </div> <!-- well -->   

            <!-- Car History -->
            <?php
            if ($action == 'update_car') { ?>

            
            <div class="row">
                <div class="col-sm">
                    <div class="card card-info">
                        <div class="card-header"><h2><strong>Record Update History</strong></h2></div>
                        <div class="card-body">
                            <?php include($abs_us_root.$us_url_root.'app/views/_car_history.php'); ?>
                        </div> <!-- card-body -->
                    </div> <!-- card -->   
                </div> <! - .col -->  
            </div> <!-- /.row -->          
            <?php } ?>
        </div> <!-- well -->     
    </div> <!-- /.container -->
</div> <!-- /.wrapper -->

<!-- Include Date Range Picker -->
<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.4.1/js/bootstrap-datepicker.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.4.1/css/bootstrap-datepicker3.css"/>

<script>
    $(document).ready(function(){
        var date_input=$('input[id="purchasedate"]'); //our date input has the name "date"
        var container=$('.page-wrapper form').length>0 ? $('.page-wrapper form').parent() : "body";
        date_input.datepicker({
            format: 'yyyy-mm-dd',
            container: container,
            todayHighlight: false,
            autoclose: true,
        })
    })

    $(document).ready(function(){
        var date_input=$('input[id="solddate"]'); //our date input has the name "date"
        var container=$('.page-wrapper form').length>0 ? $('.page-wrapper form').parent() : "body";
        date_input.datepicker({
            format: 'yyyy-mm-dd',
            container: container,
            todayHighlight: false,
            autoclose: true,
        })
    })

    $(document).ready(function(){
        var _action = '<?= $action ?>';
        
        if( _action == 'update_car' ){
            $('#year option[value=<?= $cardetails['year'] ?>]').prop('selected', true);
            $('#year').trigger("change");
            // Need to escape all the special characters in the MODEL field in order for this to work
            $('#model option[value=<?php  $str = array("|", " ", "/","+"); $escStr   = array("\\\|", "\\\ ", "\\\/","\\\+");  echo str_replace($str, $escStr, $cardetails['model']);?> ]').prop('selected', true);
        }

    });


</script>



<!-- Add car validation JS -->
<script language="JavaScript" src="<?= $us_url_root ?>assets/js/cardefinition.js" type="text/javascript"></script> 

<?php
require_once $abs_us_root . $us_url_root . 'usersc/templates/' . $settings->template . '/footer.php';
?>
