<?php

/**
 * FIX Directory Index
 *
 * Lists available administrative cleanup scripts in the FIX directory.
 * Requires authentication and displays each script as a button for easy access.
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../users/init.php';
require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php';

if (!securePage($_SERVER['PHP_SELF'])) {
	die();
}

// Get list of files in the FIX directory
$directory    = $abs_us_root . $us_url_root . 'FIX/';
$scanned_directory = array_diff(scandir($directory), array('..', '.', '.htaccess', 'index.php'));

?>
<div id="page-wrapper">
	<div class="container-fluid">
		<div class="well">
			<div class="row">
				<div class="col-4">
					<div class="card card-default">
						<div class="card-header">
							<h2><strong>Administrative Cleanup</strong></h2>
						</div>
						<div class="card-body">
							<?php
							foreach ($scanned_directory as $file) {
							?>

								<button class="btn btn-primary btn-lg btn-block" onclick=" window.open('<?= $file ?>','_blank')"> <?= $file ?></button>

							<?php
							}
							?>
						</div> <!-- card-body -->
					</div> <!-- card -->
				</div> <!-- col -->

			</div> <!-- row -->

		</div> <!-- well -->
	</div><!-- Container -->
</div><!-- page -->


<!-- Javascript -->



<!-- footers -->
<?php require_once $abs_us_root . $us_url_root . 'usersc/templates/' . $settings->template . '/footer.php'; //custom template footer
?>
