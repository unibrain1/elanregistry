<?php
require_once '../users/init.php';
require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php';

if (!securePage($_SERVER['PHP_SELF'])) {
    die();
}

// Get the combined user+profile
$userQ = $db->findById($user->data()->id, 'usersview');
$userData = $userQ->results();

$cancelPage = $us_url_root . 'users/account.php'; // Cancel redirect location

$cardetails = [];
/*  Add the User/profile information to the record */
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

// 'placeholder' to prompt for response.  Background text in input boxes
$carprompt['chassis']       = 'Enter Chassis Number';
$carprompt['color']         = 'Enter the current color of the car';
$carprompt['engine']        = 'Enter Engine number - LPAxxxxx';
$carprompt['purchasedate']  = 'YYYY-MM-DD';
$carprompt['solddate']      = 'YYYY-MM-DD';
$carprompt['comments']      = 'Please give a brief history of your car and anything special';
$carprompt['website']       = 'Website URL';

$action = 'add_car'; // No one has asked me to do anything yet


if (!empty($_POST)) {
    $token = Input::get('csrf');
    if (!Token::check($token)) {
        include($abs_us_root . $us_url_root . 'usersc/scripts/token_error.php');
    } else {

        $action = Input::get('action');

        switch ($action) {
            case 'update_car':
                update_car($cardetails);
                break;
            default:
                $errors[] = 'No valid action';
        }
    } // End Post with data
}

/* 
Called to update a car.  Get the car information and fill in the defaults.
*/
function update_car(&$car)
{
    global $user;
    global $db;
    global $errors;
    global $successes;

    $car['id'] = Input::get('car_id');

    if (!empty($car['id'])) {
        // Let's check to see if this user owns this car`
        $userQ = $db->get('car_user', ['AND', ['userid', '=', $user->data()->id], ['carid', '=', $car['id']]]);

        if ($userQ->count() > 0) {
            // Owner so get the car
            $carQ = $db->get('cars', ['id', '=', $car['id']]);
            $theCar = $carQ->results();
            // Get the details from the current car
            $car['id'] = $theCar[0]->id;
            $car['year'] = $theCar[0]->year;
            $car['model'] = $theCar[0]->model;
            $car['series'] = $theCar[0]->series;
            $car['variant'] = $theCar[0]->variant;
            $car['type'] = $theCar[0]->type;
            $car['chassis'] = $theCar[0]->chassis;
            $car['color'] = $theCar[0]->color;
            $car['engine'] = $theCar[0]->engine;
            $car['purchasedate'] = $theCar[0]->purchasedate;
            $car['solddate'] = $theCar[0]->solddate;
            $car['website'] = $theCar[0]->website;
            $car['comments'] = $theCar[0]->comments;
        } else {
            // This should never happen unless the user is trying to do something bad. Log it and then log them out
            logger($user->data()->id, 'ElanRegistry', 'Not owner of car! USER ' . $user->data()->id . ' CAR ' . $car['id']);
            $user->logout();
            exit();
        }
    } else { /* Empty Car */
        logger($user->data()->id, 'ElanRegistry', 'Empty car_id field in GET');
    } // empty $car['id']
}
?>

<div id='page-wrapper'>
    <div class='container-fluid'>
        <div class='well'>
            <br>
            <div class='row'>
                <div class='col-md-6'>
                    <!-- Car Form -->
                    <?php include($abs_us_root . $us_url_root . 'app/views/_car_edit.php'); ?>
                </div>

                <div class='col-md-6'>
                    <!-- Image Form -->
                    <?php include($abs_us_root . $us_url_root . 'app/views/_image_upload.php'); ?>
                </div>
                <!--col -->
            </div> <!-- /.row -->

            <!-- Car History -->
            <div class='row'>
                <div class='col-sm-12'>
                    <?php include($abs_us_root . $us_url_root . 'app/views/_car_history.php'); ?>
                </div> <!-- .col -->
            </div> <!-- /.row -->
        </div> <!-- well -->
    </div> <!-- /.container -->
</div><!-- .page-wrapper -->

<?php
// Include Date Range Picker
require_once $abs_us_root . $us_url_root . 'usersc/templates/' . $settings->template . '/datapicker.php';
?>
<!-- Year/Model definitions -->
<script src="<?= $us_url_root ?>app/assets/js/cardefinition.js"></script>
<script src="<?= $us_url_root ?>app/assets/js/mydropzone.js"></script>

<script>
    var validYear = '';
    var validModel = '';
    var validChassis = '';

    Dropzone.autoDiscover = false;

    $(document).ready(function() {

        // console('Document ready');

        // console(' - Init calendar');

        // Pop-up Calendar for date fields
        var date_input = $('input[id="purchasedate"]'); //our date input has the name "date"
        var container = $('.page-wrapper form').length > 0 ? $('.page-wrapper form').parent() : 'body';
        date_input.datepicker({
            format: 'yyyy-mm-dd',
            container: container,
            todayHighlight: false,
            autoclose: true,
        });

        var date_input = $('input[id="solddate"]'); //our date input has the name "date"
        var container = $('.page-wrapper form').length > 0 ? $('.page-wrapper form').parent() : 'body';
        date_input.datepicker({
            format: 'yyyy-mm-dd',
            container: container,
            todayHighlight: false,
            autoclose: true,
        });


        $('#addCar').submit(function() {
            // console(' -- In Submit');

            var form_data = $('#addCar').serializeArray();
            var error_free = true;

            // Check to see if any of the fields are invalid
            for (var input in form_data) {
                var element = $('#' + form_data[input]['name'] + '_icon');
                var invalid = element.hasClass('fa-thumbs-down');
                if (invalid) {
                    error_free = false;
                }
            }

            if (!error_free) {
                alert('Error: There are one or more errors on the form.  Please update and submit');
                event.preventDefault();
            } else {
                // console(' -- Error Free - Updating');

                // show that something is loading
                $('#carMessage').toggleClass('alert-success', false).toggleClass('alert-primary', false).html('Updating ...');

                // Call ajax for pass data to other place
                $.ajax({
                        type: 'POST',
                        url: 'action/carUpdate.php',
                        dataType: 'json',
                        data: $(this).serialize() // getting filled value in serialize form
                    })
                    .done(function(data) { // if getting done then call.
                        // show the response

                        if (data.stats === 'error') { // We have errors
                            $('#carMessage').toggleClass('alert-success', false).toggleClass('alert-primary', true).html('<b>Errors</b>');

                            var list = $('<ul />'); // create UL
                            extractResult(data.info); // run function and fill the UL with LI's
                            $('#carMessage').append(list); // append the completed UL to the body

                            function extractResult(result) {
                                jQuery.each(result, function(index, value) {
                                    // create a LI for each iteration and append to the UL
                                    $('<li />', {
                                        text: value
                                    }).appendTo(list);
                                });
                            }
                        } else {
                            var message = ($('#action').val() === 'update_car') ? 'Car Updated' : 'Car Added';
                            $('#carMessage').toggleClass('alert-success', true).toggleClass('alert-primary', false).html(message);

                            // Update the Car Card Title
                            $('#carHeader').html('<h2><strong>Update car</strong><h2>');

                            // Set the form text for Update
                            $('#submit').attr('value', 'update').html('Update');
                            $('#carid').html(data.carID);
                            $('#carHeader').html('<h2><strong>Update car</strong><h2>');

                            // Alter the form so it now is an update
                            $('#car_id').attr('value', data.carID);
                            $('#action').attr('value', 'update_car');

                            // Update the history table
                            $('#historytable').DataTable().ajax.reload();

                            // Show the dropzone
                            $('#dropzoneCard').removeClass('d-none');
                            $('#photoMessage').html('');
                        }
                    })
                    .fail(function() { // if fail then getting message
                        // just in case posting your form failed
                        $('#carMessage').toggleClass('alert-success', false).toggleClass('alert-primary', true).html('<b>Errors</b><br>Car Add/Update failed');
                    });

                // to prevent refreshing the whole page page
                return false;
            }

        });

        // Pre-populate dropdown menus if we are updating a car
        if ($('#action').val() === 'update_car') {
            $('#year option[value=<?= $cardetails['year'] ?>]').prop('selected', true);
            $('#year').trigger('change'); // Trigger the change event to populate and validate
            // Need to escape all the special characters in the MODEL field in order for this to work
            $('#model option[value=<?php $str = array("|",    " ",    "/",    "+");
                                    $escStr   = array("\\\|", "\\\ ", "\\\/", "\\\+");
                                    $newStr   = str_replace($str, $escStr, $cardetails['model']);
                                    echo $newStr ?>]').prop('selected', true);

            $('#model').trigger('change'); // Trigger the change event to populate and validate
            $('#chassis').trigger('blur'); // Trigger the change event to populate and validate

            // Show all fields
            $('#color').prop('disabled', false)
            $('#engine').prop('disabled', false)
            $('#purchasedate').prop('disabled', false)
            $('#solddate').prop('disabled', false)
            $('#website').prop('disabled', false)
            $('#comments').prop('disabled', false)

            // Set the form text for Update
            $('#submit').attr('value', 'update').html('Update');
            $('#carid').html($('#car_id').val());
            $('#carHeader').html('<h2><strong>Update car</strong><h2>');

            // Show the dropzone
            $('#dropzoneCard').removeClass('d-none');
            $('#photoMessage').html('');
        } else {
            // Hide the dropzone
            $('#dropzoneCard').addClass('d-none');
            $('#photoMessage').html('Upload photos after adding a car');
        }
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
        if ($('#action').val() === 'add_car') {
            validModel = '';
            validChassis = '';
            $('#model_icon').toggleClass('fa-thumbs-up', Boolean(validModel)).toggleClass('fa-thumbs-down', !Boolean(validModel)).toggleClass('is-valid', false).toggleClass('is-invalid', false);
            $('#model').toggleClass('is-valid', false).toggleClass('is-invalid', false);
            $('#model').prop('disabled', false).val('');

            $('#chassis_icon').toggleClass('fa-thumbs-up', Boolean(validChassis)).toggleClass('fa-thumbs-down', !Boolean(validChassis)).toggleClass('is-valid', false).toggleClass('is-invalid', false);
            $('#chassis').toggleClass('is-valid', false).toggleClass('is-invalid', false);

            $('#chassis').val('');
        } else {
            $('#model').prop('disabled', false);
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
        $('#chassis').prop('disabled', false);
    });

    // Validate Chassis
    $('#chassis').blur(function() {
        const _valid_suffix = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'J', 'K', 'L', 'M', 'N'];
        const _chassis = $('#chassis').val();
        var _base;
        var _suffix;

        // If this is a Race model let the chassis be anything
        if (validModel.indexOf('Race') >= 0) {
            validChassis = _chassis;
        } else if (validYear !== '') {
            // Now validate the chassis number
            if (validYear < 1970) {
                validChassis = ($.isNumeric(_chassis) && (_chassis.length === 4)) ? _chassis : '';
            } else if (validYear === '1970') {
                if (_chassis.length === 5) {
                    _base = _chassis.slice(0, 4);
                    _suffix = _chassis.slice(4, 5).toUpperCase();
                } else if (_chassis.length === 11) {
                    _base = _chassis.slice(0, 10);
                    _suffix = _chassis.slice(10, 11).toUpperCase();
                }
                validChassis = ($.isNumeric(_base) && ($.inArray(_suffix, _valid_suffix) !== -1)) ? _chassis : '';
            } else {
                if (_chassis.length === 11) {
                    _base = _chassis.slice(0, 10);
                    _suffix = _chassis.slice(10, 11).toUpperCase();
                }
                validChassis = ($.isNumeric(_base) && ($.inArray(_suffix, _valid_suffix) !== -1)) ? _chassis : '';
            }
        }

        $('#chassis_icon').toggleClass('fa-thumbs-up', Boolean(validChassis)).toggleClass('fa-thumbs-down', !Boolean(validChassis)).toggleClass('is-valid', Boolean(validChassis)).toggleClass('is-invalid', !Boolean(validChassis));
        $('#chassis').toggleClass('is-valid', Boolean(validChassis)).toggleClass('is-invalid', !Boolean(validChassis));

        if ($('#action').val() === 'add_car' && (validChassis)) {
            // add_car
            if (validChassis) {
                // Now see if the chassis is taken
                $.ajax({
                    url: 'action/checkChassis.php',
                    type: 'post',
                    data: {
                        'command': 'chassis_check',
                        'year': validYear,
                        'model': validModel,
                        'chassis': validChassis,
                    },
                    success: function(response) {
                        if (response === 'taken') {
                            validChassis = '';
                            $('#chassis_icon').toggleClass('fa-thumbs-up', Boolean(validChassis)).toggleClass('fa-thumbs-down', !Boolean(validChassis)).toggleClass('is-valid', Boolean(validChassis)).toggleClass('is-invalid', !Boolean(validChassis));
                            $('#chassis').toggleClass('is-valid', Boolean(validChassis)).toggleClass('is-invalid', !Boolean(validChassis));
                            $('#chassis_taken').show();
                        } else if (response === 'not_taken') {
                            $('#chassis_taken').hide();
                            $('#color').prop('disabled', false)
                            $('#engine').prop('disabled', false)
                            $('#purchasedate').prop('disabled', false)
                            $('#solddate').prop('disabled', false)
                            $('#website').prop('disabled', false)
                            $('#comments').prop('disabled', false)
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