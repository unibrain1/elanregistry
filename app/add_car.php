<?php
require_once '../users/init.php';
require_once $abs_us_root.$us_url_root.'users/includes/template/prep.php';

if (!securePage($_SERVER['PHP_SELF'])) {
    die();
}

// Always handy to know who I am
$user_id                    = $user->data()->id;

// A place to store default values and what the user entered
$select_str                 = 'Please Select';

// Get the combined user+profile
$userQ = $db->findById($user_id, "usersview");
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

//Forms posted now process it
if (!empty($_POST)) {
    $token = $_POST['csrf'];
    if (!Token::check($token)) {
        include($abs_us_root . $us_url_root . 'usersc/scripts/token_error.php');
    } else {








        //Update Year
        $year = ($_POST['year']);
        $year = Input::sanitize($year);
        if (strcmp($year, $select_str)) {
            $cardetails['year'] = $year;
            $successes[] = 'Year Updated';
        } else {
            $errors[] = "Please select Year";
        }

        // Update 'model'
        //
        $model = ($_POST['model']);
        $model = Input::sanitize($model);
        if (strcmp($model, $select_str)) {
            // Model isn't really a thing.
            //      We need to explode it into the proper columns
            $cardetails['model'] = $model;  // Still save it for later so we can remember what the user entered
            list($series, $variant, $type) = explode('|', $model);
            /* MST value is from form, so I shouldn't have to do this but to be safe ... */
            $cardetails['series'] = filter_var($series, FILTER_SANITIZE_STRING);
            $cardetails['variant'] = filter_var($variant, FILTER_SANITIZE_STRING);
            $cardetails['type'] = filter_var($type, FILTER_SANITIZE_STRING);

            $successes[] = 'Model Updated';
        } else {
            $errors[] = "Please select Model";
        }

        // Update 'chassis'
        $chassis = ($_POST['chassis']);
        $chassis = Input::sanitize($chassis);
        $len = strlen($chassis);
        $cardetails['chassis'] = filter_var($chassis, FILTER_SANITIZE_STRING);
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
        $cardetails['color'] = filter_var($color, FILTER_SANITIZE_STRING);
        $successes[] = 'Color Updated';

        // Update 'engine'
        $engine = ($_POST['engine']);
        $engine = Input::sanitize($engine);
        $cardetails['engine'] = filter_var(str_replace(" ", "", strtoupper(trim($engine))), FILTER_SANITIZE_STRING);
        $successes[] = 'Engine Updated';

        // Update 'purchasedate'
        $purchasedate = ($_POST['purchasedate']);
        if (!empty($purchasedate)) {
            $purchasedate = Input::sanitize($purchasedate);
            // Convert to SQL date format
            if ($purchasedate = date("Y-m-d H:i:s", strtotime($purchasedate))) {
                $cardetails['purchasedate'] = filter_var($purchasedate, FILTER_SANITIZE_STRING);
                $successes[] = 'Purchased  Updated';
            } else {
                $errors[] = "Purchase Date conversion error";
            }
        }

        // Update 'solddate'
        if (!empty($solddate)) {
            $solddate = ($_POST['solddate']);
            $solddate = Input::sanitize($solddate);
            if ($solddate = date("Y-m-d H:i:s", strtotime($solddate))) {
                $cardetails['solddate'] = filter_var($solddate, FILTER_SANITIZE_STRING);
                $successes[] = 'Sold Date Updated';
            } else {
                $errors[] = "Sold Date conversion error";
            }
        }

        // Update 'comments'
        $comments = ($_POST['comments']);
        $comments = Input::sanitize($comments);
        $cardetails['comments'] = filter_var($comments, FILTER_SANITIZE_STRING);
        $successes[] = 'Comment Updated';

        // Update 'image'

        if (isset($_FILES['uploadedFile']) && $_FILES['uploadedFile']['error'] === UPLOAD_ERR_OK) {
            $successes[] = 'in Image Upload';

            // get details of the uploaded file
            $fileTmpPath = $_FILES['uploadedFile']['tmp_name'];
            $fileName = $_FILES['uploadedFile']['name'];
            $fileSize = $_FILES['uploadedFile']['size'];
            $fileType = $_FILES['uploadedFile']['type'];
            $fileNameCmps = explode(".", $fileName);
            $fileExtension = strtolower(end($fileNameCmps));

            // sanitize file-name and give it a random name
            $newFileName = md5(time() . $fileName) . '.' . $fileExtension;

            // check if file has one of the following extensions
            $allowedfileExtensions = array('jpg', 'jpeg', 'gif', 'png');

            if (in_array($fileExtension, $allowedfileExtensions)) {
                // directory in which the uploaded file will be moved
                $uploadFileDir = './userimages/';
                $thumbnailDir = $uploadFileDir . 'thumbs/';
                $dest_path = $uploadFileDir . $newFileName;
                $thumb_dest_path = $thumbnailDir . $newFileName;


                if (move_uploaded_file($fileTmpPath, $dest_path)) {
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
                $errors[] = 'Upload failed. Allowed file types: ' . implode(',', $allowedfileExtensions);
            }
        }

        // If there are no errors then INSERT the $cardetails into the DB,
        if (empty($errors)) {
            // Add a create time
            $cardetails['ctime'] = date('Y-m-d G:i:s');
            $db->insert('cars', $cardetails);

            if ($db->error()) {
                $errors[] = 'DB ERROR' . $db->errorString();
                logger($user->data()->id, "ElanRegistry", "add_car error car " . $db->errorString());
            } else {
                // Grab the id of the last insert
                $car_id = $db->lastId();
                $successes[] = 'Car ID: ' . $car_id;
                $successes[] = 'User ID: ' . $user_id;
                // then log it
                logger($user->data()->id, "ElanRegistry", "Added car " . $car_id);
                // then udate the cross reference table (user_car) with the car_id and user_id,
                $db->insert(
                    'car_user',
                    array('userid' => $user_id, 'carid' => $car_id)
                );
                // then redirect to User Account Page
                Redirect::to($us_url_root . 'users/account.php');
            }
        } else {
            $errors[] = 'Cannot add record';
        }
    } // End Post with data
} // End Post













































?>

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="well">
        <br>
        <div class="row">
            <div class="col-sm">  <!-- Car Info -->
                <div class="card card-default">
                    <div class="card-header"><h2><strong>Add Car</strong></h2></div>
                    <div class="card-body">
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
                        <form name="addCar" action="add_car.php" method="POST" enctype="multipart/form-data">


                        <?php include($abs_us_root.$us_url_root.'app/views/_car_table.php'); ?>

                    </div> <!-- card-body -->
                </div>   
            </div>
            <div class="col-sm"> <!-- Image Info -->
                <div class="card card-default">
                <div class="card-header"><h2><strong>Upload/Replace Picture</strong></h2></div>
                    <div class="card-body">    
                        <div class="custom-file">
                            <?php include($abs_us_root.$us_url_root.'app/views/_image_table.php'); ?>
                        </div>
                    </div>
                </div> <!-- card -->
            </div> <!--col -->
        </div> <!-- /.row -->

        <div class="row">
            <div class="col-sm-4">
            </div>
            <div class="col-sm-4">
                <input type="hidden" name="csrf" value="<?= Token::generate(); ?>" />
                <input class='bbtn btn-success btn-lg btn-block' type='submit' value='Add' class='submit' />
                <a class="btn btn-info btn-lg btn-block" href=<?= $us_url_root ?>users/account.php>Cancel </a> 
            </div>
            <div class="col-sm-4">
            </div>
            </form> 
        </div> <!-- /.row -->
        </div> <!-- well -->     













    </div> <!-- /.container -->
</div> <!-- /.wrapper -->

<!-- Include Date Range Picker -->
<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.4.1/js/bootstrap-datepicker.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.4.1/css/bootstrap-datepicker3.css"/>

<script>
$(document).ready(function(){
    var date_input=$('input[name="purchasedate"]'); //our date input has the name "date"
    var container=$('.page-wrapper form').length>0 ? $('.page-wrapper form').parent() : "body";
    date_input.datepicker({
        format: 'yyyy-mm-dd',
        container: container,
        todayHighlight: false,
        autoclose: true,
    })
})

$(document).ready(function(){
    var date_input=$('input[name="solddate"]'); //our date input has the name "date"
    var container=$('.page-wrapper form').length>0 ? $('.page-wrapper form').parent() : "body";
    date_input.datepicker({
        format: 'yyyy-mm-dd',
        container: container,
        todayHighlight: false,
        autoclose: true,
    })
})

$('#uploadedFile').on('change',function(){
    //get the file name
    var fileName = $(this).val().replace('C:\\fakepath\\', " ");
    //replace the "Choose a file" label
    $(this).next('.custom-file-label').html(fileName);
})
</script>





<!-- Add car validation JS -->
 <script language="JavaScript" src=<?= $us_url_root . 'assets/js/cardefinition.js' ?> type="text/javascript"></script> 

<?php
require_once $abs_us_root . $us_url_root . 'usersc/templates/' . $settings->template . '/footer.php';
?>
