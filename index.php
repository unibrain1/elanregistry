<?php

require_once 'users/init.php';
require_once $abs_us_root.$us_url_root.'users/includes/template/prep.php';

// Grab a random car 

$carQ = $db->query("SELECT * FROM users_carsview WHERE image <> '' ORDER BY RAND() LIMIT 1");
if ($carQ->count() > 0) {
	$thatCar = $carQ->results();
}

?>
<div class="container">
	<div class="jumbotron">
			<h1>Welcome to <?php echo $settings->site_name;?></h1>
			<p class="text-muted">A place to document Lotus Elan and Lotus Elan Plus 2</p>
			<?php if($user->isLoggedIn()){$uid = $user->data()->id;?>
			<a class="btn btn-default" href="users/account.php" role="button">User Account &raquo;</a>
			<?php }else{?>
				<a class="btn btn-warning" href="users/login.php" role="button">Log In &raquo;</a>
				<a class="btn btn-info" href="users/join.php" role="button">Sign Up &raquo;</a>
			<?php } ?>
	</div>


	<div class="row">
		<div class="col-md-4">
				<H2>About the Registry</H2>
					<p>This is the Registry for the 1963 thru 1973 Lotus
						Elan and the 1967 thru 1974 Lotus Elan Plus 2.  The purpose of the registry is to keep a
						history of the cars, trace the evolution of the
						Lotus Elan and to facilitate owner communication.
					</p>

					<p>The Lotus Elan Registry started in January
						2003.  A thread on LotusElan.net asked the
						question, <a
						href="http://www.lotuselan.net/forums/elan-f14/lotus-elan-register-t349.html">Does
					anybody know if there is a Lotus Elan register?</a>
					I bashed together a registry and a few years later
					we have over 300 cars accounted for with more added every month.</p>

						<h3>Important Resources</h3>
						<ul>
							<li><a href="http://www.lotuselansprint.com/index.asp">The Lotus Elan Sprint</a></li>
							<li><a href="http://www.type26register.com/">The Lotus Elan Type 26 Registry</a></li>
							<li><a href="http://www.lotuselan.net/">LotusElan.Net</a></li>
						</ul>

			</div><!-- /.col -->
			<div class="col-md-8">
					<H2>One of the cars</H2>
						<table id="cartable" width="100%" class='display'>	
							<?php
							if ($thatCar[0]->image) {
								?>
								<tr><td colspan="2">
									<img class="img-responsive" src=<?=$us_url_root?>app/userimages/<?=$thatCar[0]->image?> >
								</td></tr>
								<?php
							} ?>
							<tr ><td ><strong>Year :</strong></td><td ><?=$thatCar[0]->year?></td></tr>
							<tr ><td ><strong>Series :</strong></td><td ><?=$thatCar[0]->series?></td></tr>
							<tr ><td ><strong>Variant:</strong></td><td ><?=$thatCar[0]->variant?></td></tr>
							<tr ><td ><strong>Type:</strong></td><td ><?=$thatCar[0]->type?></td></tr>	
							<tr><td><a colspan="2" class="btn btn-success btn-sm" href=<?=$us_url_root?>app/car_details.php?car_id=<?=$thatCar[0]->id?>">Details</a></td><td></td></tr>		
						</table>
					<br />
		</div><!-- /.col -->
	</div><!-- /.row -->
</div><!-- /.container -->
<!-- footers -->
<?php require_once $abs_us_root.$us_url_root.'users/includes/page_footer.php'; // the final html footer copyright row + the external js calls ?>

<!-- Place any per-page javascript here -->
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.18/css/jquery.dataTables.min.css"/>
<script type="text/javascript" src="https://cdn.datatables.net/1.10.18/js/jquery.dataTables.min.js"></script>

<script type="text/javascript">
	$(document).ready(function()  {
		var table =  $('#cartable').DataTable();
	} );
</script>
<!-- footers -->
<?php require_once $abs_us_root . $us_url_root . 'usersc/templates/' . $settings->template . '/footer.php'; //custom template footer ?>
