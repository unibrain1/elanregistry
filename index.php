<?php

require_once 'users/init.php';
require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php';

if (!securePage($_SERVER['PHP_SELF'])) {
	die();
}

// Grab a random car with an image!
$randomCarId = $db->query("SELECT id FROM cars WHERE image <> '' ORDER BY RAND() LIMIT 1")->results()[0]->id;
$car = new Car($randomCarId);

// Grab count of cars by Series
// There should be a more efficient way to do this
$count['s1']     = $db->query("select count(*) as count from cars where series like 's1%'")->results()[0]->count;
$count['s2']     = $db->query("select count(*) as count from cars where series like 's2%'")->results()[0]->count;
$count['s3']     = $db->query("select count(*) as count from cars where series like 's3%'")->results()[0]->count;
$count['s4']     = $db->query("select count(*) as count from cars where series like 's4%'")->results()[0]->count;
$count['sprint'] = $db->query("select count(*) as count from cars where series like 'sprint%'")->results()[0]->count;
$count['+2']     = $db->query("select count(*) as count from cars where series like '+2%'")->results()[0]->count;

// Number of cars produced
$notes['s1']     = "900";
$notes['s2']     = "1250";
$notes['s3']     = "2650";
$notes['s4']     = "2976";
$notes['sprint'] = "900";
$notes['+2']     = "4526";

?>
<div id='page-wrapper'>
	<!-- Page Content -->
	<div class='container'>
		<!-- Heading Row -->
		<div class='row'>
			<div class='col-lg-5'>
				<div class='card-block'>
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

				<div class='card-block'>
					<div class="card-header">
						<h2>How are we doing?</h2>
					</div>
					<div class="card-body">
						<table id="seriestable" class="table table-striped table-bordered table-sm" aria-describedby="card-header">
							<thead>
								<tr>
									<th scope=columnd>Series</th>
									<th scope=columnd>Registered</th>
									<th scope=columnd>Number produced *</th>
									<th scope=columnd>Percent registered</th>
								</tr>
							</thead>
							<tbody>
								<?php
								$total = 0;
								$totalN = 0;
								foreach ($count as $key => $value) {
									echo "<tr><td>" . ucfirst($key) . "</td><td>" . $value . "</td>";
									echo "<td>" . $notes[$key] . "</td>";
									echo "<td>" . round(($value * 100) / $notes[$key], 0) . " %</td></tr>";

									$total += $value;
									$totalN += $notes[$key];
								}
								echo "<tr><td><strong>Total</strong></td><td><strong>" . $total . "</strong></td><td>" .
									$totalN . "</td><td>" . round(($total * 100) / $totalN) . " %</td></tr>";
								?>
							</tbody>
						</table>
						<p><small>* - Number produced is from
								<a href="https://www.amazon.com/Authentic-Lotus-1962-1974-Marques-Models/dp/0947981950">
									Authentic Lotus Elan & Plus 2 1962 - 1974 by Robinshaw and Ross</a>, page 22 and page 138.
								In cases where there is a range of values, I took the lower.</small></p>
					</div> <!-- body -->
				</div><!-- card block -->

			</div>
			<!-- /.col-lg-8 -->
			<div class='col-lg-7'>
				<div class='card-block'>
					<div class='card-header'>
						<h2>One of the Cars</h2>
					</div>
					<div class='card-body'>

						<?php echo displayCarousel($car); ?>
						<table id='cartable' class='table table-striped table-bordered table-sm' aria-describedby='Car ID <?= $car->data()->id ?>'>
							<tr>
								<th scope='col'><strong>Year :</strong></th>
								<th scope='col'><?= $car->data()->year ?></th>
							</tr>
							<tr>
								<td><strong>Series :</strong></td>
								<td><?= $car->data()->series ?></td>
							</tr>
							<tr>
								<td><strong>Variant:</strong></td>
								<td><?= $car->data()->variant ?></td>
							</tr>
							<tr>
								<td><strong>Type:</strong></td>
								<td><?= $car->data()->type ?></td>
							</tr>
							<tr>
								<td colspan='2'><a class='btn btn-success btn-sm' href='<?= $us_url_root ?>
									app/cars/details.php?car_id=<?= $car->data()->id ?>'>Details</a></td>
							</tr>
						</table>
					</div> <!-- card-body -->
				</div> <!-- card -->
			</div> <!-- /.col-md-4 -->
		</div> <!-- /.row -->

		<!-- Content Row -->
		<div class='row'>
			<div class='col-md-5'>
				<div class='card-block'>
					<div class='card-header'>
						<H2>Thanks</H2>
					</div>
					<div class='card-body'>
						<p class='card-text'>Thank you to the many people on the Elan mailing list and the
							Elan forums who have helped with the registry.
							The group has helped with testing, providing pictures, provided feedback on what
							should be included, and kept me motivated to improve the site.
							This is their work. I am just the one who assembled the pieces.</p>

						<p class='card-text'>Special thanks to Ross, Tim, Gary, Ed, Terry, Peter, Jeff, Nicholas,
							Alan, Christian, Michael, Stan,
							Jason and everyone else who has contributed and will continue to make the registry
							what it is, a place
							for us to obsess over little British cars.</p>
					</div><!-- /.card-body -->
				</div><!-- /.card -->
			</div><!-- /.col -->
			<!-- /.col-md-4 -->
			<div class='col-md-7'>
				<div class='card-block'>
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