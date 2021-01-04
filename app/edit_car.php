<?php

require_once '../users/init.php';
require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php';

if (!securePage($_SERVER['PHP_SELF'])) {
    die();
}

// Get the combined user+profile
$userQ = $db->findById($user->data()->id, 'usersview');
$userData = $userQ->results();

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
$cardetails['image']        = null;

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

        if ($action === 'update_car') {
            update_car($cardetails);
        } else {
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

    $car['id'] = Input::get('carid');

    if (!empty($car['id'])) {
        // Let's check to see if this user owns this car`
        $userQ = $db->get('car_user', ['AND', ['userid', '=', $user->data()->id], ['carid', '=', $car['id']]]);

        if ($userQ->count() > 0) {
            // Owner so get the car
            $carQ = $db->get('cars', ['id', '=', $car['id']])->results()[0];

            foreach ($carQ as $key => $value) {
                // Copy data into the $car
                $car[$key] = $value;
            }
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
<style>

</style>
<div id='page-wrapper'>
    <div class='container-fluid'>
        <div class='well'>
            <br>
            <form method='POST' id='addCar' name='addCar' action='' enctype='multipart/form-data'>
                <div class='row'>
                    <div class='alert alert-primary col-md-12 text-center' id='errorMsg' role='alert' style='display: none'>
                        This is for error messages
                    </div>
                </div>
                <div class='row'>
                    <div class='col-md-12 text-center'>
                        <input type='hidden' name='csrf' id='csrf' value="<?= Token::generate(); ?>" />
                        <input type='hidden' name='action' id='action' value="<?= $action ?>" />
                        <input type='hidden' name='carid' id='carid' value="<?= $cardetails['id'] ?>" />
                        <button class='btn btn-success btn-lg' type='button' id='submit'>Add</button>
                        <a href="<?= $us_url_root ?>app/car_details.php?car_id=<?= $cardetails['id'] ?>" class='btn btn-info btn-lg'>Cancel</a>
                    </div>
                </div>
                <br>
                <div class=' row'>
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
            </form>
            <!-- Car History -->
            <div class='row'>
                <div class='col-sm-12'>
                    <?php include($abs_us_root . $us_url_root . 'app/views/_car_history.php'); ?>
                </div> <!-- .col -->
            </div> <!-- /.row -->
        </div> <!-- well -->
    </div> <!-- /.container -->
</div><!-- .page-wrapper -->
</div>
<div id="overlay" style="display:none;">
    <div class="spinner"></div>
    <br />
    Saving...
</div>

<?php
// Include Date Range Picker
require_once $abs_us_root . $us_url_root . 'usersc/templates/' . $settings->template . '/datapicker.php';
?>
<!-- Year/Model definitions -->
<script src="<?= $us_url_root ?>app/assets/js/cardefinition.js"></script>

<!-- Dropzone Init -->
<script src="<?= $us_url_root ?>usersc/vendor/enyo/dropzone/dist/min/dropzone.min.js"></script>
<link rel="stylesheet" href="<?= $us_url_root ?>usersc/vendor/enyo/dropzone/dist/min/dropzone.min.css">

<script>
    var validYear = '';
    var validModel = '';
    var validChassis = '';

    $(document).ready(function() {
        $('#errorMsg').hide();
        // Load any existing car images
        loadImages();

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

        // Pre-populate dropdown menus if we are updating a car
        if ($('#action').val() === 'update_car') {
            $('#year option[value=<?= $cardetails['year'] ?>]').prop('selected', true);
            $('#year').trigger('change'); // Trigger the change event to populate and validate
            // Need to escape all the special characters in the MODEL field in order for this to work
            var model = "<?= $cardetails['model'] ?>";

            // Escape the special characters in the model string
            var model = model.replace(/\|/g, "\\\|");
            var model = model.replace(/ /g, "\\\ ");
            var model = model.replace(/\//g, "\\\/");
            var model = model.replace(/\+/g, "\\\+");

            $('#model option[value=' + model + ']').prop('selected', true);

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
        }
    });


    /* *
     *  Validate car form during data entry
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
                const csrf = $('#csrf').val();
                $.ajax({
                    url: 'action/checkChassis.php',
                    type: 'post',
                    data: {
                        'command': 'chassis_check',
                        'year': validYear,
                        'model': validModel,
                        'chassis': validChassis,
                        'csrf': csrf,
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

    // Image remove button

    // https://stackoverflow.com/questions/203198/event-binding-on-dynamically-created-elements
    $("#images").on('click', '[id^=remove]', function(e) {
        e.preventDefault();
        let image = this.getAttribute("value");
        let carID = $('#carid').val();
        const csrf = $('#csrf').val();

        if (confirm("This will remove the photo from the car record.  Are you sure?")) {
            $.ajax({
                url: 'action/imageUpdate.php',
                data: {
                    'command': 'delete',
                    'target_file': image,
                    'carID': id,
                    'csrf': csrf,
                },
                type: "post",
                success: function(response) {
                    // Update the image display
                    loadImages();
                }
            });
        }
    });

    // Dropzone configuration
    Dropzone.autoDiscover = false;

    var myDropzone = new Dropzone("div.dropzone", {
        url: 'action/carUpdate.php',
        autoProcessQueue: false,
        clickable: true, // Define the element that should be used as click trigger to select files.

        uploadMultiple: true,
        maxFiles: 5,
        maxFilesize: 20, // MB
        parallelUploads: 20,

        acceptedFiles: 'image/*',
        addRemoveLinks: true,

        resizeWidth: 2048,
        resizeMimeType: 'image/jpeg',

        init: function() {
            myDropzone = this; // Makes sure that 'this' is understood inside the functions below.

            // for Dropzone to process the queue instead of default form behavior:
            document.getElementById("submit").addEventListener("click", function(e) {
                // Check to see if any of the fields are invalid
                var form_data = $('#addCar').serializeArray();
                var error_free = true;

                for (var input in form_data) {
                    var element = $('#' + form_data[input]['name'] + '_icon');
                    var invalid = element.hasClass('fa-thumbs-down');
                    if (invalid) {
                        error_free = false;
                    }
                }

                if (!error_free) {
                    $('#errorMsg').show().html('Error: There are one or more errors on the page.<br>Please update and submit ');
                    e.preventDefault();
                } else {
                    $('#overlay').show();

                    if (myDropzone.getQueuedFiles().length > 0) {
                        e.stopPropagation();
                        myDropzone.processQueue();
                    } else {
                        // https://stackoverflow.com/questions/20910571/dropzonejs-submit-form-without-files
                        var blob = new Blob();
                        blob.upload = {
                            'chunked': myDropzone.defaultOptions.chunking
                        };
                        myDropzone.uploadFile(blob);
                    }
                }
            });

            //send all the form data along with the files:
            this.on("sendingmultiple", function(data, xhr, formData) {
                formData.append('action', $('#action').val());
                formData.append('csrf', $('#csrf').val());
                formData.append('carid', $('#carid').val());
                formData.append('year', $('#year').val());
                formData.append('model', $('#model').val());
                formData.append('series', $('#series').val());
                formData.append('variant', $('#variant').val());
                formData.append('type', $('#type').val());
                formData.append('chassis', $('#chassis').val());
                formData.append('color', $('#color').val());
                formData.append('engine', $('#engine').val());
                formData.append('purchasedate', $('#purchasedate').val());
                formData.append('solddate', $('#solddate').val());
                formData.append('website', $('#website').val());
                formData.append('comments', $('#comments').val());
            });

            this.on("successmultiple", function(file, response) {
                let parsedResponse = JSON.parse(response);
                window.location.href = '<?= $us_url_root ?>' + 'app/car_details.php?car_id=' + parsedResponse.carId;
            });
        }
    });

    /*
     * Load existing images for the car and place a delete button near each image. 
     * This makes it easier to redraw the area when someone deleted an image
     */
    function loadImages() {
        let id = $('#carid').val();
        const csrf = $('#csrf').val();

        $.ajax({
            url: 'action/imageUpdate.php',
            data: {
                'command': 'fetch',
                'carID': id,
                'csrf': csrf,
            },
            type: "post",
            success: function(response) {
                let r = JSON.parse(response);
                if (r.status === 'success' && r.count != 0) {
                    $('#images').empty();
                    for (i = 0; i < r.images.length; i++) {
                        $('#images').append('<div class="form-group row"><div class="col-md-9">' +
                            '<img class = "card-img-top" src = "' + '<?= $us_url_root ?>' +
                            'app/userimages/' + r.images[i] + '" >' +
                            '</div><div class="col-md-3"> <button class="btn btn-primary btn-lg btn-block" type="button" id="remove-' + r.images[i] + '" value="' + r.images[i] + '"><i class="far fa-trash-alt"></i></i> Remove</button></div></div > ');
                    }
                } else {
                    $('#existing').hide();
                }
            }
        });
    }
</script>


<!--footers-->
<?php
require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php'; //custom template footer
?>