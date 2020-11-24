<?php
require_once '../users/init.php';
require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php';

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
$cardetails['image']        = null;

// 'placeholder' to prompt for response.  Background text in input boxes
$carprompt['chassis']       = 'Enter Chassis Number';
$carprompt['color']         = 'Enter the current color of the car';
$carprompt['engine']        = 'Enter Engine number - LPAxxxxx';
$carprompt['purchasedate']  = 'YYYY-MM-DD';
$carprompt['solddate']      = 'YYYY-MM-DD';
$carprompt['comments']      = 'Please give a brief history of your car and anything special';
$carprompt['website']       = 'Website URL';

// A place to put some messages
$errors                     = [];
$successes                  = [];

// A place for the car History if it exists
$carHist                    = null;

// Default page title
$title = 'Add Car';
$action = null; // No one has asked me to do anything yet

$accountPage = $us_url_root . 'users/account.php';

// Allowed file types.  This should also be reflected in getExtension
$allowed_file_types = ['image/jpeg'];

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

//Forms posted now process it
if (!empty($_POST)) {
    $token = Input::get('csrf');
    if (!Token::check($token)) {
        include($abs_us_root . $us_url_root . 'usersc/scripts/token_error.php');
    } else {
        $action = Input::get('action');

        switch ($action) {
            case "add_car":
                $title = 'Add Car';
                add_car();
                break;
            case "update_car":
                $title = 'Update Car';
                update_car();
                break;
            default:
                $errors[] = "No valid action";
        }
    } // End Post with data
} // End Post


function add_car()
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
    global $accountPage;
    global $allowed_file_types;

    if (!empty($_POST['car_id'])) {
        $cardetails['id'] = Input::get('car_id');
    }

    // This is the name of the last image
    $cardetails['image'] = Input::get('image');

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
        //      We need to explode it into the proper columns
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
    if (!empty($_POST['chassis'])) {
        $cardetails['chassis'] = Input::get('chassis');
        $len = strlen($cardetails['chassis']);
        // Validate
        if (strcmp($cardetails['variant'], 'Race') == 0) { /* For the 26R let them do what they want */
            $successes[] = 'Chassis Updated';
        } elseif ($cardetails['year'] < 1970) {
            if ($len != 4) { // Chassis number for years < 1970 are 4 digits
                $errors[] = "Enter Chassis Number. Four Digits,6490 not 36/6490";
            }
        } else {
            $successes[] = 'Chassis Updated';
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
        $cardetails['purchasedate'] = date("Y-m-d H:i:s", strtotime($cardetails['purchasedate']));
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

    // Update 'image'
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        // check if file has one of the allowed mime types
        $mime_type = get_mime_type($_FILES['file']['tmp_name']);

        if (in_array($mime_type, $allowed_file_types)) {
            // get extenstion of the uploaded file
            $fileExtension = getExtension($mime_type);

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
            $errors[] = "Upload failed. You tried to upload an invalid file type. Allowed file types: " . implode(',', $allowed_file_types);
        }
    }

    // If there are no errors then INSERT the $cardetails into the DB,
    if (empty($errors)) {
        // Is this an update or an insert?
        if (!empty($cardetails['id'])) {
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
                Redirect::to($accountPage);
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
                Redirect::to($accountPage);
            }
        }
    } else {
        $errors[] = 'Cannot add record';
    }
}



/*
 * Fill out the form with the existing values
 */
function update_car()
{
    global $cardetails;
    global $user;
    global $db;
    global $us_url_root;
    global $errors;
    global $successes;
    global $carHist;
    global $accountPage;

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
            $cardetails['website']      = $theCar[0]->website;
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
        Redirect::to($accountPage);
    } // empty $car_id
}
?>

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="well">
            <br>
            <form method='POST' id='addCar' name='addCar' action='edit_car.php' enctype='multipart/form-data' class='needs-validation' novalidate>
                <div class="row">
                    <div class="col-md-6">
                        <!-- Car Info -->
                        <?php include($abs_us_root . $us_url_root . 'app/views/_car_edit.php'); ?>
                    </div>
                    <div class="col-md-6">
                        <!-- Image Info -->
                        <?php include($abs_us_root . $us_url_root . 'app/views/_image_upload.php'); ?>
                    </div>
                    <!--col -->
                </div> <!-- /.row -->
                <div class="row">
                    <div class="col-sm-4">
                    </div>
                    <div class="col-sm-2">
                        <input type="hidden" name="csrf" id="csrf" value="<?= Token::generate(); ?>" />
                        <input type="hidden" name="action" id="action" value="add_car" />
                        <?php
                        if (!empty($cardetails['id'])) { ?>
                            <input type="hidden" name="car_id" id="car_id" value="<?= $cardetails['id'] ?>" />
                        <?php } ?>
                        <input type="hidden" name="image" value="<?= $cardetails['image'] ?>" />
                        <button class='btn btn-success btn-lg btn-block' type='submit' id='submit'>Submit</button>
                    </div>
                    <div class="col-sm-2">
                        <button class="btn btn-info btn-lg btn-block" href="<?= $accountPage ?>">Cancel </button>
                    </div>
                    <div class="col-sm-4">
                    </div>
                </div> <!-- /.row -->
            </form>

            <!-- Car History -->
            <?php
            if ($action == 'update_car') { ?>

                <div class="row">
                    <div class="col-sm">
                        <div class="card card-info">
                            <div class="card-header">
                                <h2><strong>Record Update History</strong></h2>
                            </div>
                            <div class="card-body">
                                <?php include($abs_us_root . $us_url_root . 'app/views/_car_history.php'); ?>
                            </div> <!-- card-body -->
                        </div> <!-- card -->
                    </div>
                    <!-- .col -->
                </div> <!-- /.row -->
            <?php } ?>
        </div> <!-- well -->
    </div> <!-- /.container -->
</div><!-- .page-wrapper -->

<?php
if ($action == 'update_car') {
    //    Table Sorting and Such
    require_once $abs_us_root . $us_url_root . 'usersc/templates/' . $settings->template . '/datatables.php';
}
// Include Date Range Picker
require_once $abs_us_root . $us_url_root . 'usersc/templates/' . $settings->template . '/datapicker.php';
?>
<!-- Year/Model definitions -->
<script src="<?= $us_url_root ?>app/assets/js/cardefinition.js"></script>

<script>
    var validYear = '';
    var validModel = '';
    var validChassis = '';
    var _action = '<?= $action ?>';

    /*
     * On form submission check to see if any field is marked is-invalid
     */
    $('#submit').click(function(event) {
        var form_data = $("#addCar").serializeArray();
        var error_free = true;
        for (var input in form_data) {
            var element = $("#" + form_data[input]['name'] + "_icon");
            var invalid = element.hasClass("fa-thumbs-down");
            if (invalid) {
                error_free = false;
            }
        }
        if (!error_free) {
            alert('Error: There are one or more errors on the form.  Please update and submit');
            event.preventDefault();
        }
    });


    $(document).ready(function() {
        // Pre-populate dropdown menus if we are updating a car
        if (_action === 'update_car') {
            $('#year option[value=<?= $cardetails['year'] ?>]').prop('selected', true);
            $('#year').trigger("change"); // Trigger the change event to populate and validate
            // Need to escape all the special characters in the MODEL field in order for this to work
            $('#model option[value=<?php $str = array("|",    " ",    "/",    "+");
                                    $escStr   = array("\\\|", "\\\ ", "\\\/", "\\\+");
                                    $newStr   = str_replace($str, $escStr, $cardetails['model']);
                                    echo $newStr ?>]').prop('selected', true);

            $('#model').trigger("change"); // Trigger the change event to populate and validate
            $('#chassis').trigger("blur"); // Trigger the change event to populate and validate

            // Show all fields
            $('#color').prop("disabled", false)
            $('#engine').prop("disabled", false)
            $('#purchasedate').prop("disabled", false)
            $('#solddate').prop("disabled", false)
            $('#website').prop("disabled", false)
            $('#comments').prop("disabled", false)

            // Format history table
            var table = $('#historytable').DataTable({
                "ordering": false,
                "scrollX": true
            });
        }

        // Pop-up Calendar for date fields
        var date_input = $('input[id="purchasedate"]'); //our date input has the name "date"
        var container = $('.page-wrapper form').length > 0 ? $('.page-wrapper form').parent() : "body";
        date_input.datepicker({
            format: 'yyyy-mm-dd',
            container: container,
            todayHighlight: false,
            autoclose: true,
        });

        var date_input = $('input[id="solddate"]'); //our date input has the name "date"
        var container = $('.page-wrapper form').length > 0 ? $('.page-wrapper form').parent() : "body";
        date_input.datepicker({
            format: 'yyyy-mm-dd',
            container: container,
            todayHighlight: false,
            autoclose: true,
        });
    });


    /* *
     *  Validate car form before data entry
     *
     * Set fields that are valid as green and invalid as red
     */


    /*
     * When year changes, update the model list and show the appropriate chassis help text
     */
    $('#year').change(function() {
        validYear = $('#year option:selected').val();

        $('#year_icon').toggleClass('fa-thumbs-up', Boolean(validYear)).toggleClass('fa-thumbs-down', !Boolean(validYear)).toggleClass('is-valid', Boolean(validYear)).toggleClass('is-invalid', !Boolean(validYear));
        $('#year').toggleClass('is-valid', Boolean(validYear)).toggleClass('is-invalid', !Boolean(validYear));

        // Year changed so reset model and chassis
        if (_action !== 'update_car') {
            validModel = "";
            validChassis = "";
            $('#model_icon').toggleClass('fa-thumbs-up', Boolean(validModel)).toggleClass('fa-thumbs-down', !Boolean(validModel)).toggleClass('is-valid', false).toggleClass('is-invalid', false);
            $('#model').toggleClass('is-valid', false).toggleClass('is-invalid', false);
            $('#model').prop("disabled", false).val("");

            $('#chassis_icon').toggleClass('fa-thumbs-up', Boolean(validChassis)).toggleClass('fa-thumbs-down', !Boolean(validChassis)).toggleClass('is-valid', false).toggleClass('is-invalid', false);
            $('#chassis').toggleClass('is-valid', false).toggleClass('is-invalid', false);

            $('#chassis').val("");
        } else {
            $('#model').prop("disabled", false);
        }

        if (validYear) {

            //Display appropriate chassis text
            if (validYear < 1970) {
                $('#chassis_pre1970').show();
                $('#chassis_1970').hide();
                $('#chassis_post1970').hide();
                $('#chassis_taken').hide();
            } else if (validYear === '1970') {
                $('#chassis_pre1970').hide();
                $('#chassis_1970').show();
                $('#chassis_post1970').hide();
                $('#chassis_taken').hide();
            } else {
                $('#chassis_pre1970').hide();
                $('#chassis_1970').hide();
                $('#chassis_post1970').show();
                $('#chassis_taken').hide();
            }
            populateSub($('#year').get(0), $('#model').get(0));
        }
    });
    // Validate Model
    $('#model').change(function() {
        validModel = $('#model option:selected').val();

        $('#model_icon').toggleClass('fa-thumbs-up', Boolean(validModel)).toggleClass('fa-thumbs-down', !Boolean(validModel)).toggleClass('is-valid', Boolean(validModel)).toggleClass('is-invalid', !Boolean(validModel));
        $('#model').toggleClass('is-valid', Boolean(validModel)).toggleClass('is-invalid', !Boolean(validModel));
        $('#chassis').prop("disabled", false);
    });

    // Validate Chassis
    $('#chassis').blur(function() {
        // validChassis = validateChassis();

        const _valid_suffix = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'J', 'K', 'L', 'M', 'N'];
        const _chassis = $('#chassis').val();
        var _base;
        var _suffix;

        // If this is a Race model let the chassis be anything
        if (validModel.indexOf("Race") >= 0) {
            validChassis = _chassis;
        } else if (validYear !== '') {
            // Now validate the chassis number
            if (validYear < 1970) {
                validChassis = ($.isNumeric(_chassis) && (_chassis.length === 4)) ? _chassis : "";
            } else if (validYear === "1970") {
                if (_chassis.length === 5) {
                    _base = _chassis.slice(0, 4);
                    _suffix = _chassis.slice(4, 5).toUpperCase();
                } else if (_chassis.length === 11) {
                    _base = _chassis.slice(0, 10);
                    _suffix = _chassis.slice(10, 11).toUpperCase();
                }
                validChassis = ($.isNumeric(_base) && ($.inArray(_suffix, _valid_suffix) !== -1)) ? _chassis : "";
            } else {
                if (_chassis.length === 11) {
                    _base = _chassis.slice(0, 10);
                    _suffix = _chassis.slice(10, 11).toUpperCase();
                }
                validChassis = ($.isNumeric(_base) && ($.inArray(_suffix, _valid_suffix) !== -1)) ? _chassis : "";
            }
        }

        $('#chassis_icon').toggleClass('fa-thumbs-up', Boolean(validChassis)).toggleClass('fa-thumbs-down', !Boolean(validChassis)).toggleClass('is-valid', Boolean(validChassis)).toggleClass('is-invalid', !Boolean(validChassis));
        $('#chassis').toggleClass('is-valid', Boolean(validChassis)).toggleClass('is-invalid', !Boolean(validChassis));

        if (_action !== 'update_car' && (validChassis)) {
            // add_car
            if (validChassis) {
                // Now see if the chassis is taken
                $.ajax({
                    url: 'checkChassis.php',
                    type: 'post',
                    data: {
                        'command': 'chassis_check',
                        'year': validYear,
                        'model': validModel,
                        'chassis': validChassis,
                    },
                    success: function(response) {
                        if (response === 'taken') {
                            validChassis = "";
                            $('#chassis_icon').toggleClass('fa-thumbs-up', Boolean(validChassis)).toggleClass('fa-thumbs-down', !Boolean(validChassis)).toggleClass('is-valid', Boolean(validChassis)).toggleClass('is-invalid', !Boolean(validChassis));
                            $('#chassis').toggleClass('is-valid', Boolean(validChassis)).toggleClass('is-invalid', !Boolean(validChassis));
                            $('#chassis_taken').show();
                        } else if (response === 'not_taken') {
                            $('#chassis_taken').hide();
                            $('#color').prop("disabled", false)
                            $('#engine').prop("disabled", false)
                            $('#purchasedate').prop("disabled", false)
                            $('#solddate').prop("disabled", false)
                            $('#website').prop("disabled", false)
                            $('#comments').prop("disabled", false)
                        }
                    },
                    error: function(response) {},
                });
            }
        }
    });
</script>

<!-- footers -->
<?php
require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php'; //custom template footer
?>