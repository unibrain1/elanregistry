<?php
require_once '../users/init.php';
require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php';

if (!securePage($_SERVER['PHP_SELF'])) {
    die();
}

// Get some interesting user information to display later
if (!empty($_GET)) {
    $carID = Input::get('car_id');

    // Get the car information
    $car = new Car($carID);

    $raw = date_parse($car->data()->join_date);
    $signupdate = $raw['year'] . '-' . $raw['month'] . '-' . $raw['day'];
} else {
    // Shouldn't be here unless someone is mangling the url
    Redirect::to($us_url_root . '/app/list_cars.php');
}
?>

<!-- Now that that is all out of the way, let's display everything -->
<div id='page-wrapper'>
    <div class='well'>
        <br>
        <div class='row'>
            <div class='col-sm-6'>
                <!-- Car Info -->
                <div class='card card-default'>
                    <div class='card-header'>
                        <div class='form-group row'>
                            <div class='col-md-7'>
                                <h2><strong>Car Information</strong></h2>
                            </div>
                            <div class='col-md-5 text-right'>
                                <?php
                                if (isset($user) && $user->isLoggedIn()) {
                                    if ($user->data()->id === $car->data()->user_id) { ?>
                                        <form method='POST' action=<?= $us_url_root . 'app/edit_car.php' ?>>
                                            <input type='hidden' name='csrf' value="<?= Token::generate(); ?>" />
                                            <input type='hidden' name='action' value='updateCar' />
                                            <input type='hidden' name='carid' id='carid' value="<?= $car->data()->id ?>" />
                                            <button class='btn btn-block btn-success' type='submit'>Update</button>
                                        </form>
                                    <?php
                                    } else {
                                    ?>
                                        <form method='POST' action=<?= $us_url_root . 'app/contact_owner.php' ?>>
                                            <input type='hidden' name='csrf' value="<?= Token::generate(); ?>" />
                                            <input type='hidden' name='action' value='contact_owner' />
                                            <input type='hidden' name='carid' id='carid' value="<?= $car->data()->id ?>" />
                                            <button class='btn btn-block btn-success' type='submit'>Contact Owner</button>
                                        </form>
                                <?php
                                    }
                                } else {
                                    echo "<input type='hidden' name='carid' id='carid' value='" . $car->data()->id . "' />";
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                    <div class='card-body'>
                        <table id='cartable' class='table table-striped table-bordered table-sm' aria-describedby='card-header'>
                            <tr class='table-success'>
                                <th scope=column><strong>Car ID:</strong></th>
                                <th scope=column><?= $car->data()->id ?></th>
                            </tr>
                            <tr>
                                <td><strong>Series:</strong></td>
                                <td><?= $car->data()->series ?></td>
                            </tr>
                            <tr>
                                <td><strong>Variant:</strong></td>
                                <td><?= $car->data()->variant ?></td>
                            </tr>
                            <tr>
                                <td><strong>Model:</strong></td>
                                <td><?= $car->data()->model ?></td>
                            </tr>
                            <tr>
                                <td><strong>Year:</strong></td>
                                <td><?= $car->data()->year ?></td>
                            </tr>
                            <tr>
                                <td><strong>Type:</strong></td>
                                <td><?= $car->data()->type ?></td>
                            </tr>
                            <tr>
                                <td><strong>Chassis :</strong></td>
                                <td><?= $car->data()->chassis ?></td>
                            </tr>
                            <tr>
                                <td><strong>Color:</strong></td>
                                <td><?= $car->data()->color ?></td>
                            </tr>
                            <tr>
                                <td><strong>Engine :</strong></td>
                                <td><?= $car->data()->engine ?></td>
                            </tr>
                            <tr>
                                <td><strong>Purchase Date:</strong></td>
                                <td><?= $car->data()->purchasedate ?></td>
                            </tr>
                            <tr>
                                <td><strong>Sold Date :</strong></td>
                                <td><?= $car->data()->solddate ?></td>
                            </tr>
                            <tr>
                                <td><strong>Comments:</strong></td>
                                <td><?= $car->data()->comments ?></td>
                            </tr>
                            <tr class='table-success'>
                                <td><strong>Owner ID:</strong></td>
                                <td><?= $car->data()->user_id ?></td>
                            </tr>
                            <tr>
                                <td><strong>First name:</strong></td>
                                <td><?= ucfirst($car->data()->fname) ?></td>
                            </tr>
                            <tr>
                                <td><strong>City</strong></td>
                                <td><?= html_entity_decode($car->data()->city); ?></td>
                            </tr>
                            <tr>
                                <td><strong>State:</strong></td>
                                <td><?= html_entity_decode($car->data()->state); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Country:</strong></td>
                                <td><?= html_entity_decode($car->data()->country); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Member Since:</strong></td>
                                <td><?= $signupdate ?></td>
                            </tr>
                            <tr>
                                <td><strong>Record Created:</strong></td>
                                <td><?= $car->data()->ctime ?></td>
                            </tr>
                            <tr>
                                <td><strong>Record Modified:</strong></td>
                                <td><?= $car->data()->mtime ?></td>
                            </tr>
                            <?php
                            if (!empty($car->data()->website)) {
                            ?>
                                <tr>
                                    <td><strong>Website:</strong></td>
                                    <td> <a target='_blank' href="<?= $car->data()->website ?>">Website</a></td>
                                </tr>
                            <?php
                            }
                            ?>
                            <?php
                            if (!is_null($car->factory())) { ?>

                                <tr class='table-info'>
                                    <td colspan=2><strong>Factory Data - <small>I've lost track of where this data originated and it may be incomplete, inaccurate, false, or just plain made up.</small> </strong> </td>
                                </tr>
                                <tr>
                                    <td><strong>Year:</strong></td>
                                    <td><?= $car->factory()->year ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Month:</strong></td>
                                    <td><?= $car->factory()->month ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Production Batch:</strong></td>
                                    <td><?= $car->factory()->batch ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Type:</strong></td>
                                    <td><?= $car->factory()->type ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Chassis:</strong></td>
                                    <td><?= $car->factory()->serial ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Suffix:</strong></td>
                                    <td><?= $car->factory()->suffix ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Engine:</strong></td>
                                    <td><?= $car->factory()->engineletter ?><?= $car->factory()->enginenumber ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Gearbox:</strong></td>
                                    <td><?= $car->factory()->gearbox ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Color:</strong></td>
                                    <td><?= $car->factory()->color ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Build Date:</strong></td>
                                    <td><?= $car->factory()->builddate ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Notes:</strong></td>
                                    <td><?= $car->factory()->note ?></td>
                                </tr>
                            <?php } ?>

                        </table>
                    </div>
                </div>
            </div> <!-- col-xs-12 col-md-6 -->
            <div class='col-sm-6'>
                <!-- Image -->
                <div class='card card-default'>
                    <div class='card-header'>
                        <h2><strong>The Car</strong></h2>
                    </div>
                    <div class='card-body'>
                        <?php echo display_carousel($car->data()->image); ?>
                    </div> <!-- card-body -->
                </div> <!-- card -->
            </div> <!-- col-xs-12 col-md-6 -->
        </div> <!-- row -->
        <br>
        <div class='row'>
            <div class='col-sm-12'>
                <div class='card' id='historyCard'>
                    <div class='card-header'>
                        <h2><strong>Car Update History</strong></h2>
                    </div>
                    <div class='card-body'>
                        <table id='historytable' style='width: 100%' class='table table-striped table-bordered table-sm' aria-describedby='card-header'>
                            <thead>
                                <tr>
                                    <th scope=column>Operation</th>
                                    <th scope=column>Date Modified</th>
                                    <th scope=column>Year</th>
                                    <th scope=column>Type</th>
                                    <th scope=column>Chassis</th>
                                    <th scope=column>Series</th>
                                    <th scope=column>Variant</th>
                                    <th scope=column>Color</th>
                                    <th scope=column>Engine</th>
                                    <th scope=column>Purchase Date</th>
                                    <th scope=column>Sold Date</th>
                                    <th scope=column>Comments</th>
                                    <th scope=column>Image</th>
                                    <th scope=column>Owner</th>
                                    <th scope=column>City</th>
                                    <th scope=column>State</th>
                                    <th scope=column>Country</th>
                                </tr>
                            </thead>
                        </table>
                    </div> <!-- card-body -->
                </div> <!-- card -->
            </div> <!-- col -->
        </div> <!-- row -->
    </div> <!-- well -->
</div>

<!-- footers -->
<?php
require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php'; //custom template footer

// Table Sorting and Such
echo html_entity_decode($settings->elan_datatables_js_cdn);
echo html_entity_decode($settings->elan_datatables_css_cdn);
?>

<!-- Constants needed by the scripts -->
<script>
    const csrf = '<?= Token::generate(); ?>';
    const us_url_root = '<?= $us_url_root ?>';
    const img_root = '<?= $us_url_root . $settings->elan_image_dir ?>';
</script>

<script src='<?= $us_url_root ?>app/assets/js/imagedisplay.js'></script>

<script>
    // Format history table
    // Get history from AJAX call TBD
    const id = $('#carid').val();

    var table = $('#historytable').DataTable({
        scrollX: true,
        responsive: true,
        order: [
            [1, 'desc']
        ],
        language: {
            'emptyTable': 'No history'
        },
        ajax: {
            url: 'action/carGetHistory.php',
            dataSrc: 'history',
            type: 'POST',
            data: function(d) {
                d.csrf = csrf;
                d.car_id = id;
            }
        },
        columns: [{
                data: "operation"
            },
            {
                data: "mtime"
            },
            {
                data: "year"
            },
            {
                data: "type"
            },
            {
                data: "chassis"
            },
            {
                data: "series"
            },
            {
                data: "variant"
            },
            {
                data: "color"
            },
            {
                data: "engine"
            },
            {
                data: "purchasedate"
            },
            {
                data: "solddate"
            },
            {
                data: "comments"
            },
            {
                data: "image",
                searchable: false,
                render: function(data) {
                    if (data) {
                        return carousel(data);
                    } else {
                        return "";
                    }
                }
            },
            {
                data: "fname"
            }, {
                data: "city"
            }, {
                data: "state"
            }, {
                data: 'country'
            }
        ]
    });
</script>