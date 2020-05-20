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

// It's a car edit
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
// A place to put my cardetails
$cardetails                     = [];

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

        /*  Add the User/profile information to the record */
        // Get the combined user+profile
        $userQ = $db->findById($user_id, "usersview");
        $userData = $userQ->results();

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

        //Update id
        $id = ($_POST['car_id']);
        $cardetails['id'] = Input::sanitize($id);

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
            $cardetails['model'] = Input::sanitize($model);  // Still save it for later so we can remember what the user entered
            list($series, $variant, $type) = explode('|', $model);
            /* MST value is from form, so I shouldn't have to do this but to be safe ... */
            $cardetails['series'] = filter_var($series, FILTER_SANITIZE_STRING);
            $cardetails['variant'] = filter_var($variant, FILTER_SANITIZE_STRING);
            $cardetails['type']   = filter_var($type, FILTER_SANITIZE_STRING);

            $successes[] = 'Model Updated -' . $cardetails['model'];
        } else {
            $errors[] = "Please select Model";
        }

        // Update 'chassis'
        $chassis = ($_POST['chassis']);
        $chassis = Input::sanitize($chassis);
        $len = strlen($chassis);
        $cardetails['chassis'] = filter_var($chassis, FILTER_SANITIZE_STRING);
        if (strcmp($cardetails['variant'], 'Race') == 0) { /* For the 26R let them do what they want */
            $successes[] = 'Chassis Updated -' . $cardetails['chassis'];
        } elseif ($year < 1970) {
            if ($len != 4) { // Chassis number for years < 1970 are 4 digits
                $errors[] = "Enter Chassis Number. Four Digits,6490 not 36/6490";
            }
            // } elseif ($len != 11) { 	// Chassis number for years >= 1970 are 11 digits
            //     $errors[] = "Enter Chassis Number. 70xxyy0001z";
        } else {
            $successes[] = 'Chassis Updated -' . $cardetails['chassis'];
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
        $successes[] = 'Engine Updated -' . $cardetails['engine'];

        // Update 'purchasedate'
        $purchasedate = ($_POST['purchasedate']);
        if (!empty($purchasedate)) {
            $purchasedate = Input::sanitize($purchasedate);
            // Convert to SQL date format
            if ($purchasedate = date("Y-m-d", strtotime($purchasedate))) {
                $cardetails['purchasedate'] = filter_var($purchasedate, FILTER_SANITIZE_STRING);
                $successes[] = 'Purchased  Updated';
            } else {
                $errors[] = "Purchase Date conversion error";
            }
        }

        // Update 'solddate'
        $solddate = ($_POST['solddate']);
        if (!empty($solddate)) {
            $solddate = Input::sanitize($solddate);
            if ($solddate = date("Y-m-d", strtotime($solddate))) {
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
        $successes[] = 'Comment Updated to ('.$comments.')';

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
            $cardetails['mtime'] = date('Y-m-d G:i:s');

            // Make sure user owns the car before update
            if ($db->get('car_user', ['AND',  ['userid', '=', $user_id], ['carid', '=', $cardetails['id']]])) {
                // Owner
                $db->update('cars', $cardetails['id'], $cardetails);
                if ($db->error()) {
                    $errors[] = 'DB ERROR' . $db->errorString();
                    logger($user->data()->id, "User", "edit_car error car " . $db->errorString());
                } else {
                    // Grab the id of the last insert
                    $successes[] = 'Update Car ID: ' . $cardetails['id'];
                    $successes[] = 'Update BY ID: ' . $cardetails['user_id'];

                    // then log it
                    logger($user->data()->id, "User", "Updated car ID " . $cardetails['id']);

                    // then redirect to User Account Page
                    Redirect::to($us_url_root . 'users/account.php');
                }
            } else {
                $errors[] = 'DB Abort' . print_r($errors);
            }
        }
    } // End Post with data
} // End Post

if (!empty($_GET)) {
    // Did someone pass me a car?
    $car_id = $_GET['car_id'];

    if (!empty($car_id)) {

        //  Let's check to see if this user owns this car`
        $userQ = $db->get('car_user', ['AND',  ['userid', '=', $user_id], ['carid', '=', $car_id]]);

        if ($userQ->count() > 0) {
            // Owner so get the car
            $carQ =  $db->get("cars", ["id",  "=", $car_id]);
            $theCar = $carQ->results();

            // Get the details from the current car
            $cardetails['id']          = $theCar[0]->id;
            $cardetails['year']        = $theCar[0]->year;
            $cardetails['model']       = $theCar[0]->model;
            $cardetails['chassis']     = $theCar[0]->chassis;
            $cardetails['color']       = $theCar[0]->color;
            $cardetails['engine']      = $theCar[0]->engine;
            $cardetails['purchasedate']= $theCar[0]->purchasedate;
            $cardetails['solddate']    = $theCar[0]->solddate;
            $cardetails['comments']    = $theCar[0]->comments;
            $cardetails['image']       = $theCar[0]->image;

            

            // Get the car history for display
            $carQ = $db->query('SELECT * FROM cars_hist WHERE car_id=? ORDER BY cars_hist.timestamp DESC', [$car_id]);
            $carHist = $carQ->results();
        } else {
            // This should never happen unless the user is trying to do something bad.  Log it and then log them out
            logger($user->data()->id, "User", "Not owner of car! USER " . $user_id . " CAR " . $car_id);
            $user->logout();
            Redirect::to($us_url_root . 'index.php');

            exit();
        }
    } else { /* Empty Car */
        logger($user->data()->id, "User", "Empty car_id field in GET");
        Redirect::to($us_url_root . 'users/account.php');
    } // empty $car_id
} // $_GET
?>

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="well">
        <br>
        <div class="row">
            <div class="col-sm"> <!-- Car Info -->
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
                        <form name="addCar" action="edit_car.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="car_id" value="<?= $cardetails['id'] ?>" />

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
                            <small id="chassisHelp" class="form-text text-muted"><?= $carprompt['chassis'] ?></small>
                        </div>

                        <div class="form-group">
                            <label>Color</label>
                            <input class='form-control' type='text' name='color' placeholder='<?= $carprompt['color'] ?>' value='<?= $cardetails['color'] ?>' />
                            <small id="colorHelp" class="form-text text-muted"><?= $carprompt['color'] ?></small>

                        </div>

                        <div class="form-group">
                            <label>Engine Number</label>
                            <input class='form-control' type='text' name='engine' placeholder='<?= $carprompt['engine'] ?>' value='<?= $cardetails['engine'] ?>' />
                            <small id="engineHelp" class="form-text text-muted"><?= $carprompt['engine'] ?></small>

                        </div>

                        <div class="form-group">
                            <label class="control-label" for="purchasedate">
                            Purchase Date 
                            </label>
                                <div class="input-group">
                                    <input class="form-control" id="purchasedate" name="purchasedate" placeholder='<?= $cardetails['purchasedate'] ?>' type="text"/>
                                </div>
                        </div>                       

                        <div class="form-group">
                            <label class="control-label" for="solddate">
                            Sold Date
                            </label>
                                <div class="input-group">
                                    <input class="form-control" id="solddate" name="solddate" placeholder='<?= $cardetails['solddate'] ?>' type="text"/>
                                </div>
                        </div> 

                        <!-- Form for the comment -->
                        <div class="form-group">
                            <label>Comments</br></label>
                        </div>
                        <textarea class="form-control" name='comments' rows='10' wrap='virtual' placeholder='<?= $carprompt['comments'] ?>'><?= htmlspecialchars($cardetails['comments']); ?></textarea>
                    </div>
                </div>   
            </div>
            <div class="col-sm"> <!-- Image Info -->
                <div class="card card-default">
                <div class="card-header"><h2><strong>Upload/Replace Picture</strong></h2></div>
                    <div class="card-body">            
                        <!-- Form for the 'image' -->
                        <div>
                            <input type="file" name="uploadedFile" />
                            <small id="engineHelp" class="form-text text-muted">Valid file types:  JPEG</small>
                        </div>                        
                        <!-- Add some space -->
                        </br>
                        <?php
                        if ($cardetails['image']) { ?>
                            <img class="card-img-top" src=<?= $us_url_root ?>app/userimages/<?= $cardetails['image'] ?> width='390'> <?php
                        }
                        ?>
                        </br>
                        </br>
                        </br>
                    </div>
                </div> <!-- card -->
            </div> <!--col -->
        </div> <!-- /.row -->
        <div class="row">
            <div class="col-sm-4">
            </div>
            <div class="col-sm-4">
                <input type="hidden" name="csrf" value="<?= Token::generate(); ?>" />
                <input class="btn btn-primary btn-lg btn-block" type='submit' value='Update' class='submit' />
                <a class="btn btn-info btn-lg btn-block" href=<?= $us_url_root ?>users/account.php>Cancel </a></form>
            </div>
            <div class="col-sm-4">
            </div>
        </div> <!-- /.row -->
        </div> <!-- well -->              

        <!-- Car History -->
        <div class="row">
        <div class="col-sm">
        <div class="card card-info">
            <div class="card-header"><h2><strong>Record Update History</strong></h2></div>
            <div class="card-body">
                <table id="historytable" class="table table-striped table-bordered table-sm" cellspacing="0" width="100%">
                    <thead>
                    <tr>
                        <th>Operation</th>
                        <th>Date Modified</th>
                        <th>Year</th>
                        <th>Type</th>
                        <th>Chassis</th>
                        <th>Series</th>
                        <th>Variant</th>
                        <th>Color</th>

                        <th>Engine</th>
                        <th>Purchase Date</th>
                        <th>Sold Date</th>
                        <th>Comments</th>

                        <th>Image</th>
                        <th>Owner</th>
                        <th>City</th>
                        <th>State</th>
                        <th>Country</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php
                    //Cycle through users
                    foreach ($carHist as $v1) {
                        ?>
                        <tr>
                        <td><?=$v1->operation?></td>
                        <td><?=$v1->timestamp?></td> 
                        <td><?=$v1->year?></td>
                        <td><?=$v1->type?></td>
                        <td><?=$v1->chassis?></td>
                        <td><?=$v1->series?></td>
                        <td><?=$v1->variant?></td>
                        <td><?=$v1->color?></td>

                        <td><?=$v1->engine?></td>                        
                        <td><?=$v1->purchasedate?></td>
                        <td><?=$v1->solddate?></td>
                        <td><?=$v1->comments?></td>

                        <td> <?php
                        if ($v1->image and file_exists($abs_us_root.$us_url_root."app/userimages/".$v1->image)) {
                            echo '<img src='.$us_url_root.'app/userimages/thumbs/'.$v1->image.">";
                        } ?>  </td>
                        <td><?=$v1->lname?></td>
                        <td><?=$v1->city?></td>
                        <td><?=$v1->state?></td>
                        <td><?=$v1->country?></td> 
                        </tr>
                    <?php
                    } ?>
                    </tbody>
                </table>
            </div> <!-- card-body -->
        </div> <!-- card -->   
        </div> <! - .col -->  
        </div> <!-- /.row -->          
        <!-- End of main content section -->
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

<!-- Car History Table -->
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.18/css/jquery.dataTables.min.css"/>
<script type="text/javascript" src="https://cdn.datatables.net/1.10.18/js/jquery.dataTables.min.js"></script>

<script type="text/javascript">
$(document).ready(function()  {
    var table =  $('#historytable').DataTable(
    {
        "ordering": false,
        "scrollX"  : true
    });
} );
</script>

<!-- Add car validation JS -->
<script language="JavaScript" src=<?= $us_url_root . 'app/js/cardefinition.js' ?> type="text/javascript"></script>

<?php
// And close the template

require_once $abs_us_root . $us_url_root . 'usersc/templates/' . $settings->template . '/footer.php';
?>