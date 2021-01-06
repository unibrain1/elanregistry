<?php
require_once 'users/init.php';
require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php';

// Grab a random car with an image!
$car = $db->query("SELECT * FROM cars WHERE image <> '' ORDER BY RAND() LIMIT 1")->results()[0];
?>
<div id='page-wrapper'>
	<!-- Page Content -->
	<div class='container'>

		<!-- Heading Row -->
		<div class='row'>
			<div class='col-lg-5'>
				<div class='card card-default'>
					<div class='card-header'>
						<h1><?php echo $settings->site_name; ?></h1>
						<p class='text-muted'>A place to document Lotus Elan and Lotus Elan Plus 2</p>
					</div>
					<div class='card-body'>


						<?php if ($user->isLoggedIn()) {
							$uid = $user->data()->id; ?>
							<a class='btn btn-default' href='users/account.php' role='button'>User Account &raquo;</a>
						<?php
						} else { ?>
							<a class='btn btn-warning' href='users/login.php' role='button'>Log In &raquo;</a>
							<a class='btn btn-info' href='users/join.php' role='button'>Sign Up &raquo;</a>
						<?php } ?>
						<br><br>
						<p>This is the Registry for the 1963 thru 1973 Lotus
							Elan and the 1967 thru 1974 Lotus Elan Plus 2. The purpose of the registry is to keep a
							history of the cars, trace the evolution of the
							Lotus Elan and to facilitate owner communication.
						</p>
						<p>The Lotus Elan Registry started in January 2003. A thread on LotusElan.net asked the question,
							<a href='http://www.lotuselan.net/forums/elan-f14/lotus-elan-register-t349.html'>
								Does anybody know if there is a Lotus Elan register?</a>
							I bashed together a registry and a few years later
							we have over 300 cars accounted for with more added every month.
						</p>
					</div> <!-- card-body -->
				</div> <!-- card -->
			</div>
			<!-- /.col-lg-8 -->
			<div class='col-lg-7'>
				<div class='card card-default'>
					<div class='card-header'>
						<h2>One of the Cars</h2>
					</div>
					<div class='card-body'>
						<?php include($abs_us_root . $us_url_root . 'app/views/_display_image.php'); ?>
						<table id='cartable' class='table table-striped table-bordered table-sm' aria-describedby='Car ID <?= $car->id ?>'>
							<tr>
								<th scope='col'><strong>Year :</strong></th>
								<th scope='col'><?= $car->year ?></th>
							</tr>
							<tr>
								<td><strong>Series :</strong></td>
								<td><?= $car->series ?></td>
							</tr>
							<tr>
								<td><strong>Variant:</strong></td>
								<td><?= $car->variant ?></td>
							</tr>
							<tr>
								<td><strong>Type:</strong></td>
								<td><?= $car->type ?></td>
							</tr>
							<tr>
								<td colspan='2'><a class='btn btn-success btn-sm' href='<?= $us_url_root ?>app/car_details.php?car_id=<?= $car->id ?>'>Details</a></td>
							</tr>
						</table>
					</div> <!-- card-body -->
				</div> <!-- card -->
			</div> <!-- /.col-md-4 -->
		</div> <!-- /.row -->

		<!-- Content Row -->
		<div class='row'>
			<div class='col-md-5'>
				<div class='card card-default'>
					<div class='card-header'>
						<H2>Thanks</H2>
					</div>
					<div class='card-body'>
						<p class='card-text'>Thank you to the many people on the Elan mailing list and the Elan forums who have helped with the registry.
							The group has helped with testing, providing pictures, provided feedback on what should be included, and kept me motivated to improve the site.
							This is their work. I am just the one who assembled the pieces.</p>

						<p class='card-text'>Special thanks to Ross, Tim, Gary, Ed, Terry, Peter, Jeff, Nicholas, Alan, Christian, Michael, Stan,
							Jason and everyone else who has contributed and will continue to make the registry what it is, a place
							for us to obsess over little British cars.</p>
					</div><!-- /.card-body -->
				</div><!-- /.card -->
			</div><!-- /.col -->
			<!-- /.col-md-4 -->
			<div class='col-md-7'>
				<div class='card h-100'>
					<div class='card-header'>
						<h2>Important Resources</h2>
					</div>
					<div class='card-body'>
						<div class='list-group'>
							<a href='http://www.lotuselansprint.com/' class='list-group-item list-group-item-action flex-column align-items-start'>
								<div class='d-flex w-100 justify-content-between'>
									<h5 class='mb-1'>The Lotus Elan Sprint</h5>
								</div>
								<p class='mb-1 pl-3'><small>This site is dedicated to the Lotus Elan Sprint, the final iteration of the Lotus Elan</small></p>
							</a>
							<a href='http://www.lotuselan.net/' class='list-group-item list-group-item-action flex-column align-items-start'>
								<div class='d-flex w-100 justify-content-between'>
									<h5 class='mb-1'>LotusElan.Net</h5>
								</div>
								<p class='mb-1 pl-3'><small>A great online community for the Lotus Elan.</small></p>
							</a>
							<a href='<?= $us_url_root ?>stories/type26registry/index.html' class='list-group-item list-group-item-action flex-column align-items-start'>
								<div class='d-flex w-100 justify-content-between'>
									<h5 class='mb-1'>Type 26 Registry</h5>
								</div>
								<p class='mb-1 pl-3'><small>The 26 registry is no longer online. I've copied what I can and saved it here</small></p>
							</a>
							<a href='https://github.com/unibrain1/elanregistry' class='list-group-item list-group-item-action flex-column align-items-start'>
								<div class='d-flex w-100 justify-content-between'>
									<h5 class='mb-1'>Elan Registry project on GitHub</h5>
								</div>
								<p class='mb-1 pl-3'><small>If you want to help out with the coding or just want to see how the sausage is made. I'm not a proffesional coder, I just play one in the garage.</small></p>
							</a>
						</div><!-- /.list-group -->
					</div><!-- /.card-body -->
				</div> <!-- /.card -->
			</div> <!-- /.col-md-4 -->
		</div> <!-- /.row -->
	</div> <!-- /.container -->
</div><!-- .page-wrapper -->
<!-- footers -->
<?php
require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php'; //custom template footer
?>
