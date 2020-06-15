<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);


require_once '../../users/init.php';
require_once $abs_us_root.$us_url_root.'users/includes/template/prep.php';


if (!securePage($_SERVER['PHP_SELF'])) {
    die();
}

// Get the counts of cars that have a vericode and count of cars with a verified date

$results = $db->query("SELECT  COUNT(*) as requested,  count(last_verified) as verified  FROM cars  WHERE vericode <> '' ")->results()[0];

?>

<div id="page-wrapper">
	<div class="container-fluid">
		<div class="well">
		<div class="row">
		<div class="col-8" align="center">
				<div class="card card-default">
				<div class="card-header"><h2><strong>Report of Verification Status</strong></h2></div>
					<div class="card-body">
						Requested <?=$results->requested?></br>
						Verified <?=$results->verified?></br>
					</div> <!-- card-body -->
				</div> <!-- card -->
			</div> <!-- col -->
			<div class="col-4" align="center">
				<div class="card card-default">
				<div class="card-header"><h2><strong>Verify Cars</strong></h2></div>
					<div class="card-body">
                        <button class="btn btn-primary btn-lg btn-block" onclick=" window.open('send_email.php','_blank')">Send Verify Request Email</button>
					</div> <!-- card-body -->
				</div> <!-- card -->
			</div> <!-- col -->

		</div> <!-- row -->
	
		</div> <!-- well -->
	</div><!-- Container -->
</div><!-- page -->


<!-- Javascript -->



<!-- footers -->
<?php require_once $abs_us_root . $us_url_root . 'usersc/templates/' . $settings->template . '/footer.php'; //custom template footer?>
