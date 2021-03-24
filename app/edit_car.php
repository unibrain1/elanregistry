<?php

require_once '../users/init.php';
require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php';

if (!securePage($_SERVER['PHP_SELF'])) {
    die();
}

$maximages = $settings->elan_image_max;

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

$action = 'addCar'; // No one has asked me to do anything yet

if (!empty($_POST)) {
    $token = Input::get('csrf');
    if (!Token::check($token)) {
        include($abs_us_root . $us_url_root . 'usersc/scripts/token_error.php');
    } else {

        $action = Input::get('action');
        $cardetails['id']  = Input::get('carid');

        if ($action === 'updateCar') {
            updateCarDetails($cardetails);
        } else {
            $errors[] = 'No valid action';
        }
    } // End Post with data
}

/* 
Called to update a car.  Get the car information and fill in the defaults.
*/
function updateCarDetails(&$car)
{
    global $user;

    if (empty($car['id'])) {
        logger($user->data()->id, 'ElanRegistry', 'Empty carid field in GET');
        return;
    }

    $carQ = new Car($car['id']);

    // Let's check to see if this user owns this car`
    if ($user->data()->id != $carQ->data()->user_id) {
        // This should never happen unless the user is trying to do something bad. Log it and then log them out
        logger($user->data()->id, 'ElanRegistry', 'Not owner of car! USER ' . $user->data()->id . ' CAR ' . $car['id']);
        $user->logout();
        exit();
    }

    foreach ($carQ->data() as $key => $value) {
        // Copy data into the $car
        $car[$key] = $value;
    }
}
?>
<link rel="stylesheet" href="<?= $us_url_root ?>app/assets/css/edit_car.css">

<div id='page-wrapper'>
    <div class='container-fluid'>
        <div class='row justify-content-center'>
            <div class='col-9 text-center '>
                <h2 id='heading'>Enter a New Car</h2>
                <p>Fill all form field to go to next step</p>
                <form id='editCar' name='editCar' method='post' enctype='multipart/form-data' novalidate>
                    <!-- progressbar -->
                    <ul id='progressbar'>
                        <li class='active' id='cardetails'><strong>Car Details</strong></li>
                        <li id='addInfo'><strong>Additional Information</strong></li>
                        <li id='image'><strong>Images</strong></li>
                        <li id='confirm'><strong>Results</strong></li>
                    </ul>
                    <div class='progress'>
                        <div class='progress-bar bg-success' role='progressbar' aria-valuemin='0' aria-valuemax='100'></div>
                    </div>

                    <hr>

                    <br>
                    <div id='message' style='display: none;'></div>
                    <fieldset>
                        <!-- fieldsets page 1 -->
                        <div class="card card-default">
                            <div class="card-header">
                                <div class="row">
                                    <div class='col-md-7 text-left'>
                                        <legend class='fs-title'>Car Details:</legend>
                                    </div>
                                    <div class='col-5'>
                                        <h2 class='steps'>Step 1 - 4</h2>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php include($abs_us_root . $us_url_root . 'app/views/_edit_car_1.php'); ?>
                            </div>
                        </div>
                        <input type='button' name='next' class='next btn btn-info' value='Next' />
                    </fieldset>

                    <fieldset>
                        <!-- fieldsets page 2 -->
                        <div class="card card-default">
                            <div class="card-header">
                                <div class="row">
                                    <div class='col-md-7'>
                                        <legend class='fs-title'>Additional Information:</legend>
                                    </div>
                                    <div class='col-5'>
                                        <h2 class='steps'>Step 2 - 4</h2>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php include($abs_us_root . $us_url_root . 'app/views/_edit_car_2.php'); ?>
                            </div>
                        </div>
                        <input type='button' name='next' class='next btn btn-info' value='Next' />
                        <input type='button' name='previous' class='previous btn btn-danger' value='Previous' />
                    </fieldset>


                    <fieldset>
                        <!-- fieldsets page 3 -->
                        <div class="card card-default">
                            <div class="card-header">
                                <div class="row">
                                    <div class='col-md-7'>
                                        <legend class='fs-title'>Image Upload:</legend>
                                    </div>
                                    <div class='col-5'>
                                        <h2 class='steps'>Step 3 - 4</h2>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php include($abs_us_root . $us_url_root . 'app/views/_edit_car_3.php'); ?>
                            </div>
                        </div>
                        <!-- End Image panel -->
                        <input type='hidden' name='csrf' id='csrf' value='<?= Token::generate(); ?>' />
                        <input type='hidden' name='action' id='action' value='<?= $action ?>' />
                        <input type='hidden' name='carid' id='carid' value='<?= $cardetails['id'] ?>' />
                        <input type='submit' name='submit' id='submit' class=' btn btn-success' value='Add Car' />
                        <input type='button' name='previous' class='previous btn btn-danger' value='Previous' />
                    </fieldset>

                    <fieldset>
                        <div class="card card-default">
                            <div class="card-header">
                                <div class="row">
                                    <div class='col-6 d-flex text-left'>
                                        <legend class='fs-title'>Results</legend>
                                    </div>
                                    <div class='col-6'>
                                        <h2 class='steps'>Step 4 - 4</h2>
                                    </div>
                                </div>
                            </div>
                            <div class='card-body' id='results'>
                            </div>
                        </div>
                    </fieldset>
                </form>
            </div>
        </div>
    </div>
</div><!-- .page-wrapper -->
<!--footers-->
<?php
require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php'; //custom template footer
?>

<!-- Dropzone  jqueryui required for sortable dropzone -->
<?php echo html_entity_decode($settings->elan_jquery_ui_cdn); ?>
<?php echo html_entity_decode($settings->elan_dropzone_js_cdn); ?>
<?php echo html_entity_decode($settings->elan_dropzone_css_cdn); ?>

<!-- Include datapicker -->
<?php echo html_entity_decode($settings->elan_datepicker_js_cdn); ?>
<?php echo html_entity_decode($settings->elan_datepicker_css_cdn); ?>

<!-- Year/Model definitions -->
<script src='<?= $us_url_root ?>app/assets/js/cardefinition.js'></script>

<script>
    Dropzone.autoDiscover = false;
    const csrf = $('#csrf').val();
    const carid = $('#carid').val();

    $(document).ready(function() {
        // BEGIN DROPZONE

        $(function() {
            $("#myDrop").sortable({
                items: '.dz-preview',
                cursor: 'move',
                opacity: 0.5,
                containment: '#myDrop',
                distance: 20,
                tolerance: 'pointer',
            });

            $("#myDrop").disableSelection();
        });

        var myDropzone = new Dropzone("div#myDrop", {
            url: "action/editCar.php",
            autoProcessQueue: false,
            clickable: true,

            uploadMultiple: true,
            maxFiles: <?= $maximages ?>,
            maxFilesize: 2, // MB
            parallelUploads: 10,

            acceptedFiles: 'image/*',
            addRemoveLinks: true,

            resizeWidth: 2048,
            resizeMimeType: 'image/jpeg',

            dictRemoveFile: 'Remove photo',
            dictDefaultMessage: "Drop photos here to upload",
            dictMaxFilesExceeded: "Only {{maxFiles}} photos are allowed",
            dictFileTooBig: "Photo is to big ({{filesize}}mb). Max allowed photo size is {{maxFilesize}}mb",
            dictInvalidFileType: "Invalid File Type - Only images are allowed",

            paramName: "file", // The name that will be used to transfer the file

            init: function() {
                thisDropzone = this;
                // Load any existing images
                $.post('action/editCar.php', {
                        'carID': carid,
                        'csrf': csrf,
                        'action': 'fetchImages'
                    },
                    function(response) {
                        let data = JSON.parse(response);
                        if (data == null || data.status != 'success') {
                            return;
                        }
                        $.each(data.image, function(key, value) {
                            var mockFile = {
                                name: value.name,
                                accepted: true,
                                status: 'success',
                            };
                            thisDropzone.emit("addedfile", mockFile);
                            thisDropzone.emit("thumbnail", mockFile, '<?= $us_url_root . $settings->elan_image_dir ?>' + value.name);
                            $('[data-dz-thumbnail]').css('height', '120');
                            $('[data-dz-thumbnail]').css('width', '120');
                            $('[data-dz-thumbnail]').css('object-fit', 'cover');

                            // Make sure that there is no progress bar, etc...

                            // thisDropzone.emit("success", mockFile);
                            thisDropzone.emit("complete", mockFile);

                            thisDropzone.files.push(mockFile);
                        });

                    });

                // Grab the submit button.  Make sure it's error free and process the queue  
                document.getElementById("submit").addEventListener("click", function(e) {
                    current_fs = $(this).parent();
                    next_fs = $(this).parent().next();

                    // Check to see if any of the form fields are invalid
                    var form_data = $('#addCar').serializeArray();
                    var error_free = true;

                    for (var input in form_data) {
                        var element = $('#' + form_data[input]['name'] + '_icon');
                        var invalid = element.hasClass('fa-thumbs-down');
                        if (invalid) {
                            error_free = false;
                        }
                    }

                    // check to see if there are any errors on the images
                    //  See if data-dz-errormessage is empty for all images.  
                    $('.dropzone .dz-error-message span').each(function() {
                        if ($(this).text()) {
                            error_free = false;
                        }
                    });

                    if (!error_free) {
                        $('#message').show().append('<div class="alert alert-primary">Error: There are one or more errors on the page.<br>Please update and submit</div>');

                        e.preventDefault();
                    } else {
                        // Now process the queue
                        if (thisDropzone.getQueuedFiles().length > 0) {
                            e.stopPropagation();
                            e.preventDefault();
                            thisDropzone.processQueue();
                        } else {
                            e.stopPropagation();
                            e.preventDefault();

                            // https://stackoverflow.com/questions/20910571/dropzonejs-submit-form-without-files
                            var blob = new Blob();
                            blob.upload = {};
                            thisDropzone.uploadFile(blob);
                        }
                    }
                });

                thisDropzone.on("addedfile", function(file) {
                    $("#message").hide();
                });

            }
        });

        //send all the form data along with the files:
        myDropzone.on("sendingmultiple", function(data, xhr, formData) {
            var filenames = [];
            $('.dz-preview .dz-filename').each(function() {
                filenames.push($(this).find('span').text());
            });

            formData.append('action', $('#action').val());
            formData.append('csrf', $('#csrf').val());
            formData.append('filenames', filenames);
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

        myDropzone.on("successmultiple", function(file, message) {
            const data = JSON.parse(message);

            if (data.status === 'success') {
                window.location = '<?= $us_url_root ?>app/car_details.php?car_id=' + data.cardetails.id;
            } else {

                // Advance the page progress indicator
                $('#message').hide();
                $('#progressbar li').eq($('fieldset').index(next_fs)).addClass('active');

                //show the next fieldset
                next_fs.show();
                //hide the current fieldset with style
                current_fs.animate({
                    opacity: 0
                }, {
                    step: function(now) {
                        // for making fielset appear animation
                        opacity = 1 - now;

                        current_fs.css({
                            'display': 'none',
                            'position': 'relative'
                        });
                        next_fs.css({
                            'opacity': opacity
                        });
                    },
                    duration: 500
                });
                setProgressBar(++current);
                var html = "<table id='resultstable' class='table table-striped table-bordered table-sm text-wrap'>";
                html += '<tr><td>Status</td><td>' + data.status + '</td></tr>';
                html += '<tr><td>Info</td><td><ul>';
                data.info.forEach(function(element, index, names) {
                    html += '<li>' + element + '</li>';
                });
                html += '<ul></td></tr>';
                html += '</table>'

                $("#results").html(html);
            }
        });

        myDropzone.on("error", function(data, msg, xhr) {
            $("#message").show().html('<div class="alert alert-primary">' + msg + '</div>');
        });

        // END DROPZONE

        // Tabbed interface 

        var current_fs, next_fs, previous_fs; //fieldsets
        var opacity;
        var current = 1;
        var steps = $('fieldset').length;

        setProgressBar(current);

        $('.next').click(function() {
            current_fs = $(this).parent();
            next_fs = $(this).parent().next();

            // Check to see if the page is error free
            var form_data = current_fs.serializeArray();
            var error_free = true;

            for (var input in form_data) {
                var element = $('#' + form_data[input]['name'] + '_icon');
                var invalid = element.hasClass('fa-thumbs-down');
                if (invalid) {
                    error_free = false;
                }
            }

            if (!error_free) {
                $('#message').show().html('<div class="alert alert-primary">Error: There are one or more errors on the page.<br>Please update and submit.<div>');
            } else {
                $('#message').hide();

                //Add Class Active
                $('#progressbar li').eq($('fieldset').index(next_fs)).addClass('active');

                //show the next fieldset
                next_fs.show();
                //hide the current fieldset with style
                current_fs.animate({
                    opacity: 0
                }, {
                    step: function(now) {
                        // for making fielset appear animation
                        opacity = 1 - now;

                        current_fs.css({
                            'display': 'none',
                            'position': 'relative'
                        });
                        next_fs.css({
                            'opacity': opacity
                        });
                    },
                    duration: 500
                });
                setProgressBar(++current);
            }
        });

        $(".previous").click(function() {
            current_fs = $(this).parent();
            previous_fs = $(this).parent().prev();

            //Remove class active
            $("#progressbar li").eq($("fieldset").index(current_fs)).removeClass("active");

            //show the previous fieldset
            previous_fs.show();

            //hide the current fieldset with style
            current_fs.animate({
                opacity: 0
            }, {
                step: function(now) {
                    // for making fielset appear animation
                    opacity = 1 - now;

                    current_fs.css({
                        'display': 'none',
                        'position': 'relative'
                    });
                    previous_fs.css({
                        'opacity': opacity
                    });
                },
                duration: 500
            });
            setProgressBar(--current);
        });

        function setProgressBar(curStep) {
            var percent = parseFloat(100 / steps) * curStep;
            percent = percent.toFixed();
            $(".progress-bar")
                .css("width", percent + "%")
        }
    });

    // End Tabbed Form

    // Car Validation
    var validYear = '';
    var validModel = '';
    var validChassis = '';

    $(document).ready(function() {
        $('#message').hide();

        // // Pop-up Calendar for date fields
        // Avoid conflict with jquery datepicker - https://stackoverflow.com/questions/18507908/bootstrap-datepicker-noconflict#18512888
        $(function() {
            var datepicker = $.fn.datepicker.noConflict();
            $.fn.bootstrapDP = datepicker;
            $('#purchasedate').bootstrapDP({
                format: 'yyyy-mm-dd',
                todayHighlight: false,
                autoclose: true,
            });
            $('#solddate').bootstrapDP({
                format: 'yyyy-mm-dd',
                todayHighlight: false,
                autoclose: true,
            });
        });

        // Pre-populate dropdown menus if we are updating a car
        if ($('#action').val() === 'updateCar') {
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
            $('#submit').attr('value', 'Update Car');
            $('#carid').html($('#carid').val());
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
        if ($('#action').val() === 'addCar') {
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

        if ($('#action').val() === 'addCar' && (validChassis)) {
            // addCar
            if (validChassis) {
                // Now see if the chassis is taken
                // const csrf = $('#csrf').val();
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
                        }
                    },
                    error: function(response) {},
                });
            }
        }
    });
    // End Car Validation
</script>