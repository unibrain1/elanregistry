<?php
require_once '../users/init.php';
require_once $abs_us_root.$us_url_root.'users/includes/template/prep.php';


if (!securePage($_SERVER['PHP_SELF'])) {
    die();
}

// Always handy to know who I am
$user_id                    = $user->data()->id;

// Get the combined user+profile
$userQ = $db->findById($user_id, "usersview");
$userData = $userQ->results();


// A place to store default values and what the user entered
$select_str                 = 'Please Select';

// It's a car add
$cardetails['id']           = null;
$cardetails['year']         = $select_str;
$cardetails['model']        = $select_str;
$cardetails['chassis']      = null;
$cardetails['color']        = null;
$cardetails['engine']       = null;
$cardetails['purchasedate'] = null;
$cardetails['solddate']     = null;
$cardetails['comments']     = null;

// Holding place before processing
$fields                 = [];

$fields['year']         = null;
$fields['model']        = null;
$fields['series']       = null;
$fields['variant']      = null;
$fields['type']         = null;
$fields['chassis']      = null;
$fields['color']        = null;
$fields['engine']       = null;
$fields['purchasedate'] = null;
$fields['solddate']     = null;
$fields['comments']     = null;
$fields['image']        = null;
/*  Add the User/profile information to the record */
$fields['user_id']      = $userData[0]->id;
$fields['email']        = $userData[0]->email;
$fields['fname']        = $userData[0]->fname;
$fields['lname']        = $userData[0]->lname;
$fields['join_date']    = $userData[0]->join_date;
$fields['city']         = $userData[0]->city;
$fields['state']        = $userData[0]->state;
$fields['country']      = $userData[0]->country;
$fields['lat']          = $userData[0]->lat;
$fields['lon']          = $userData[0]->lon;
$fields['website']      = $userData[0]->website;

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

// And a Validation function
$validation                 = new Validate();

//Temporary Success Message
$holdover = Input::get('success');
if ($holdover == 'true') {
    bold("Account Updated");
}
//Forms posted now process it
//
// TODO - Sanatize the data!

if (!empty($_POST)) {
    $token = $_POST['csrf'];
    if (!Token::check($token)) {
        include($abs_us_root . $us_url_root . 'usersc/scripts/token_error.php');
    } else {




        //Update Year
        $year = ($_POST['year']);
        $year = Input::sanitize($year);
        if (strcmp($year, $select_str)) {
            $fields['year'] = $year;
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
            $fields['model'] = $model;  // Still save it for later so we can remember what the user entered
            list($series, $variant, $type) = explode('|', $model);
            /* MST value is from form, so I shouldn't have to do this but to be safe ... */
            $fields['series'] = filter_var($series, FILTER_SANITIZE_STRING);
            $fields['variant'] = filter_var($variant, FILTER_SANITIZE_STRING);
            $fields['type'] = filter_var($type, FILTER_SANITIZE_STRING);

            $successes[] = 'Model Updated';
        } else {
            $errors[] = "Please select Model";
        }

        // Update 'chassis'
        $chassis = ($_POST['chassis']);
        $chassis = Input::sanitize($chassis);
        $len = strlen($chassis);
        $fields['chassis'] = filter_var($chassis, FILTER_SANITIZE_STRING);
        if (strcmp($fields['variant'], 'Race') == 0) { /* For the 26R let them do what they want */
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
        $fields['color'] = filter_var($color, FILTER_SANITIZE_STRING);
        $successes[] = 'Color Updated';

        // Update 'engine'
        $engine = ($_POST['engine']);
        $engine = Input::sanitize($engine);
        $fields['engine'] = filter_var(str_replace(" ", "", strtoupper(trim($engine))), FILTER_SANITIZE_STRING);
        $successes[] = 'Engine Updated';

        // Update 'purchasedate'
        $purchasedate = ($_POST['purchasedate']);
        if (!empty($purchasedate)) {
            $purchasedate = Input::sanitize($purchasedate);
            // Convert to SQL date format
            if ($purchasedate = date("Y-m-d H:i:s", strtotime($purchasedate))) {
                $fields['purchasedate'] = filter_var($purchasedate, FILTER_SANITIZE_STRING);
                $successes[] = 'Purchased  Updated';
            } else {
                $errors[] = "Purchase Date conversion error";
            }
        } else {
            $fields['purchasedate'] = ''; // NULL
        }

        // Update 'solddate'
        if (!empty($solddate)) {
            $solddate = ($_POST['solddate']);
            $solddate = Input::sanitize($solddate);
            if ($solddate = date("Y-m-d H:i:s", strtotime($solddate))) {
                $fields['solddate'] = filter_var($solddate, FILTER_SANITIZE_STRING);
                $successes[] = 'Sold Date Updated';
            } else {
                $errors[] = "Sold Date conversion error";
            }
        } else {
            $fields['solddate'] = ''; // NULL
        }

        // Update 'comments'
        $comments = ($_POST['comments']);
        $comments = Input::sanitize($comments);
        $fields['comments'] = filter_var($comments, FILTER_SANITIZE_STRING);
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

                    $fields['image'] = $newFileName;
                } else {
                    $errors[] = 'There was some error moving the file to upload directory. Please make sure the upload directory is writable by web server.';
                }
            } else {
                $errors[] = 'Upload failed. Allowed file types: ' . implode(',', $allowedfileExtensions);
            }
        }

        // If there are no errors then INSERT the $fields into the DB,
        if (empty($errors)) {
            // Add a create time
            $fields['ctime'] = date('Y-m-d G:i:s');
            $db->insert('cars', $fields);

            if ($db->error()) {
                $errors[] = 'DB ERROR' . $db->errorString();
                logger($user->data()->id, "User", "add_car error car " . $db->errorString());
            } else {
                // Grab the id of the last insert
                $car_id = $db->lastId();
                $successes[] = 'Car ID: ' . $car_id;
                $successes[] = 'User ID: ' . $user_id;
                // then log it
                logger($user->data()->id, "User", "Added car " . $car_id);
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







































// mod to allow edited values to be shown in form after update
if (isset($fields['id'])) {
    $cardetails['id']  = $fields['id'];
}
if (isset($fields['year'])) {
    $cardetails['year']  = $fields['year'];
}
if (isset($fields['model'])) {
    $cardetails['model'] = $fields['model'];
}
if (isset($fields['chassis'])) {
    $cardetails['chassis'] = $fields['chassis'];
}
if (isset($fields['color'])) {
    $cardetails['color'] = $fields['color'];
}
if (isset($fields['engine'])) {
    $cardetails['engine'] = $fields['engine'];
}
if (isset($fields['purchasedate'])) {
    $cardetails['purchasedate'] = $fields['purchasedate'];
}
if (isset($fields['solddate'])) {
    $cardetails['solddate'] =  $fields['solddate'];
}
if (isset($fields['comments'])) {
    $cardetails['comments'] = $fields['comments'];
}
if (isset($fields['image'])) {
    $cardetails['image'] = $fields['image'];
}
?>

<div id="page-wrapper">
    <div class="container">
        <div class="well">
    <br>
        <div class="row">
            <div class="col-xs-12 col-md-6">
                <div class="card card-default">
                <div class="card-header"><h2><strong>Update Car - <?php echo $cardetails['id'] ?></strong></h2></div>
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




                        <div class="form-group">
                            <label>Year</label>

                            <select class="custom-select" name="year" onchange="populateSub(this, this.form.model);">
                                <option selected><?= $cardetails['year'] ?></option>
                                <option>1963</option>
                                <option>1964</option>
                                <option>1965</option>
                                <option>1966</option>
                                <option>1967</option>
                                <option>1968</option>
                                <option>1969</option>
                                <option>1970</option>
                                <option>1971</option>
                                <option>1972</option>
                                <option>1973</option>
                                <option>1974</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Model</label>

                            <select class="custom-select" name="model">
                                <option selected><?= $cardetails['model'] ?></option>
                                <option value="">--Please Choose--</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Chassis</label>
                            <input class='form-control' type='text' name='chassis' placeholder='<?= $carprompt['chassis'] ?>' value='<?= $cardetails['chassis'] ?>' />
                        </div>

                        <div class="form-group">
                            <label>Color</label>
                            <input class='form-control' type='text' name='color' placeholder='<?= $carprompt['color'] ?>' value='<?= $cardetails['color'] ?>' />
                        </div>

                        <div class="form-group">
                            <label>Engine Number</label>
                            <input class='form-control' type='text' name='engine' placeholder='<?= $carprompt['engine'] ?>' value='<?= $cardetails['engine'] ?>' />
                        </div>

                        <div class="form-group">
                            <label class="control-label" for="purchasedate">
                            Purchase Date 
                            </label>
                                <div class="input-group">
                                    <input class="form-control" id="purchasedate" name="purchasedate" placeholder='<?= $carprompt['purchasedate'] ?>' type="text"/>
                                </div>
                        </div>                       

                        <div class="form-group">
                            <label class="control-label" for="solddate">
                            Sold Date
                            </label>
                                <div class="input-group">
                                    <input class="form-control" id="solddate" name="solddate" placeholder='<?= $carprompt['solddate'] ?>' type="text"/>
                                </div>
                        </div> 

                        <!-- Form for the comment -->
                        <div class="form-group">
                            <label>Comments</br></label>
                        </div>
                        <textarea name='comments' rows='10' cols='60' wrap='virtual' placeholder='<?= $carprompt['comments'] ?>'><?= htmlspecialchars($cardetails['comments']); ?></textarea>
                        

                        <!-- Form for the 'image' -->
                        </br>
                        <div class="form-group">
                            <label>Upload a Picture:</br></label> </br>
                        </div>
                        <div>
                            <input type="file" name="uploadedFile" />
                        </div>








                        <!-- Add some space -->
                        </br></br>

                        <!-- And last some buttons -->
                        <input type="hidden" name="csrf" value="<?= Token::generate(); ?>" />

                        <p><input class='btn btn-primary' type='submit' value='Add' class='submit' />
                        <a class="btn btn-info" href=<?= $us_url_root ?>users/account.php>Cancel </a> </form> 

                        <!-- End of main content section -->
                </div> <!-- /.col -->
            </div> <!-- /.row -->
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
</script>
<script>
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
</script>


<!-- Add car validation JS -->
    <?php
    if (file_exists($abs_us_root . $us_url_root . 'app/js/cardefinition.js')) {
        ?> <script language="JavaScript" src=<?= $us_url_root . 'app/js/cardefinition.js' ?> type="text/javascript"></script> <?php
    }


    require_once $abs_us_root . $us_url_root . 'usersc/templates/' . $settings->template . '/footer.php';
    ?>