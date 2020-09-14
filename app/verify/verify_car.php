<?php
require_once '../../users/init.php';
require_once $abs_us_root.$us_url_root.'users/includes/template/prep.php';

if (!securePage($_SERVER['PHP_SELF'])) {
    die();
}

$query = $db->query("SELECT * FROM email");
$base_url = $query->first()->verify_url;


if (isset($_GET['code']) and (isset($_GET['code']))) {
    $code = $_GET['code'];
    $action = $_GET['action'];

    // Is there a car with that code?
    $carQ = $db->query('SELECT * FROM cars WHERE vericode = ?', [$code]);
    if ($db->count() != 1) {
        echo "<h2>Verification code not found</h2><br>";
        header('refresh:5;url='.$base_url.$us_url_root);
        exit;
    }
    $car = $carQ->first();

    switch ($action) {
        case 'verify':
            // Is there a car with that code?
            $message =  "<h2>Thank you for verifying your car</h2><p>Taking you to the details...</p>";

            // Update last_verified time
            $db->update("cars", $car->id, ["last_verified"=>  date('Y-m-d G:i:s') ]);
            // Update the History record to show verified
            // Find the history record
            $hist_id = $db->query('SELECT car_id, id, MAX(timestamp) AS max FROM cars_hist where car_id = ? GROUP BY id, car_id ORDER BY `max` DESC LIMIT 1', [$car->id])->first()->id;
            $db->update("cars_hist", $hist_id, ["operation"=> "VERIFIED"]);

            // Redirect to the car detail page
            $redirect = $base_url.$us_url_root.'app/car_details.php?car_id='.$car->id;

        break;

        case 'edit':
            // Is there a car with that code?
            $message = "<h3>Thank you for updating your car.  Taking you to the Login Screen where you can edit yor information...</h3>";
            $redirect = $base_url.$us_url_root.'usersc/account.php?';
        
        break;

        case 'sold':
            // Is there a car with that code?
            $message =  "<h2>Thank you for letting me know you sold the car.  I'll update the records.</h2><p>Taking you to the details...</p>";

            $db->update("cars", $car->id, ["last_verified"=>  date('Y-m-d G:i:s') ]);
            // Update the History record to show verified
            // Find the history record
            $hist_id = $db->query('SELECT car_id, id, MAX(timestamp) AS max FROM cars_hist where car_id = ? GROUP BY id, car_id ORDER BY `max` DESC LIMIT 1', [$car->id])->first()->id;
            $db->update("cars_hist", $hist_id, ["operation"=> "VERIFIED SOLD","comments"=>"Owner reported car sold"]);



            $redirect = $base_url.$us_url_root.'app/car_details.php?car_id='.$car->id;
        break;
    }
}
?>


<div id="page-wrapper">
    <div class="container-fluid">
        <div class="well">
            <div class="row">
                <div class="col-12" align="center">
                    <div class="card card-default">
                        <div class="card-header">
                            <h2><strong>Car Verification</strong></h2>
                        </div>
                        <div class="card-body">
                            <?php
                            echo $message;
                            header('refresh:5;url='.$redirect);
                            ?>
                        </div> <!-- card-body -->
                    </div> <!-- car -->
                </div> <!-- row -->
            </div><!-- row -->
        </div> <!-- well -->
    </div>
    <!--container -->
</div> <!-- page -->
<!-- End of main content section -->

<!-- footers -->
<?php
require_once $abs_us_root . $us_url_root . 'usersc/templates/' . $settings->template .'/footer.php'; //custom template footer
