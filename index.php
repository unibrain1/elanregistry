<?php

require_once 'users/init.php';
require_once $abs_us_root.$us_url_root.'users/includes/template/prep.php';
if (isset($user) && $user->isLoggedIn()) {
}

// Grab a random car

$carQ = $db->query("SELECT * FROM users_carsview WHERE image <> '' ORDER BY RAND() LIMIT 1");
if ($carQ->count() > 0) {
    $thatCar = $carQ->results();
}

?>
<div id="page-wrapper">
	<div class="container">
		</br>
		<div class="jumbotron">
				<h1>Welcome to <?php echo $settings->site_name;?></h1>
				<p class="text-muted">A place to document Lotus Elan and Lotus Elan Plus 2</p>
				<?php if ($user->isLoggedIn()) {
    $uid = $user->data()->id; ?>
					<a class="btn btn-default" href="users/account.php" role="button">User Account &raquo;</a>
					<?php
} else {?>
					<a class="btn btn-warning" href="users/login.php" role="button">Log In &raquo;</a>
					<a class="btn btn-info" href="users/join.php" role="button">Sign Up &raquo;</a>
				<?php } ?>
		</div>
		<!-- My other stuff -->


		<div class="row">
			<div class="col-sm-4">

			<div class="card bg-secondary mb-3" style="max-width: 20rem;">
				<div class="card-header"><H2>About the Registry</H2></div>
				<div class="card-body">
					<p class="card-text">This is the Registry for the 1963 thru 1973 Lotus
						Elan and the 1967 thru 1974 Lotus Elan Plus 2.  The purpose of the registry is to keep a
						history of the cars, trace the evolution of the
						Lotus Elan and to facilitate owner communication.
						</p>
						<p class="card-text">The Lotus Elan Registry started in January 2003.  A thread on LotusElan.net asked the question, 
						<a href="http://www.lotuselan.net/forums/elan-f14/lotus-elan-register-t349.html">
						Does anybody know if there is a Lotus Elan register?</a>
						I bashed together a registry and a few years later
						we have over 300 cars accounted for with more added every month.</p>
						
				</div>
			</div>
			<div class="card bg-secondary mb-3" style="max-width: 20rem;">
				<div class="card-header"><H2>Thanks</H2></div>
				<div class="card-body">
					<p class="card-text">Thank you to the many people on the Elan mailing list and the Elan forums who have helped with the registry. 
                            The group has helped with testing, providing pictures, provided feedback on what should be included, and kept me motivated to improve the site. 
							This is their work. I am just the one who assembled the pieces.</p>

					<p class="card-text">Special thanks to Ross, Tim, Gary, Ed, Terry, Peter, Jeff, Nicholas, Alan, Christian, Michael, Stan, 
					Jason and everyone else who has contributed and will continue to make the registry what it is, a place 
					for us to obsess over little British cars.</p>
						
				</div>
			</div>

			<div class="card bg-secondary mb-3" style="max-width: 20rem;">
				<div class="card-header"><h2>Important Resources</h2></div>
				<div class="card-body">
					<div class="list-group">
						<a href="http://www.lotuselansprint.com/" class="list-group-item list-group-item-action flex-column align-items-start">
							<div class="d-flex w-100 justify-content-between">
							<h5 class="mb-1">The Lotus Elan Sprint</h5>
							</div>
							<p class="mb-1 pl-3"><small>This site is dedicated to the Lotus Elan Sprint, the final iteration of the Lotus Elan</small></p>
						</a>
						<a href="http://www.lotuselan.net/" class="list-group-item list-group-item-action flex-column align-items-start">
							<div class="d-flex w-100 justify-content-between">
							<h5 class="mb-1">LotusElan.Net</h5>
							</div>
							<p class="mb-1 pl-3"><small>A great online community for the Lotus Elan.</small></p>
						</a>
						<a href="<?=$us_url_root?>stories/type26registry/index.html" class="list-group-item list-group-item-action flex-column align-items-start">
							<div class="d-flex w-100 justify-content-between">
							<h5 class="mb-1">Type 26 Registry</h5>
							</div>
							<p class="mb-1 pl-3"><small>The 26 registry is no longer online.  I've copied what I can and saved it here<<small></p>
						</a>
						<a href="https://github.com/unibrain1/elanregistry" class="list-group-item list-group-item-action flex-column align-items-start">
							<div class="d-flex w-100 justify-content-between">
							<h5 class="mb-1">Elan Registry project on GitHub</h5>
							</div>
							<p class="mb-1 pl-3"><small>If you want to help out with the coding or just want to see how the sausage is made.  I'm not a proffesional coder, I just play one in the garage.<small></p>
						</a>
					</div>

				</div>
			</div>
				</div><!-- /.col -->

				<div class="col">
				<div class="card card-default">
					<div class="card-header"><h2>One of the Cars</h2></div>
						<div class="card-body">
							<?php
                                if ($thatCar[0]->image and file_exists($abs_us_root.$us_url_root."app/userimages/".$thatCar[0]->image)) {
                                    ?>
								<img class="card-img-top" src=<?=$us_url_root?>app/userimages/<?=$thatCar[0]->image?> >
								<?php
                                } ?>

							<table id="cartable" class="table table-striped table-bordered table-sm" cellspacing="0" width="100%">	
								<tr ><td ><strong>Year :</strong></td><td ><?=$thatCar[0]->year?></td></tr>
								<tr ><td ><strong>Series :</strong></td><td ><?=$thatCar[0]->series?></td></tr>
								<tr ><td ><strong>Variant:</strong></td><td ><?=$thatCar[0]->variant?></td></tr>
								<tr ><td ><strong>Type:</strong></td><td ><?=$thatCar[0]->type?></td></tr>	
								<tr><td><a colspan="2" class="btn btn-success btn-sm" href=<?=$us_url_root?>app/car_details.php?car_id=<?=$thatCar[0]->id?>">Details</a></td><td></td></tr>		
							</table>
						</div> <!-- card-body -->
        </div> <!-- card -->
			</div><!-- /.col -->
		</div><!-- /.row -->

		
	</div><!-- /.container -->
</div><!-- .page-wrapper -->
<!-- footers -->

<!-- Place any per-page javascript here -->
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.18/css/jquery.dataTables.min.css"/>
<script type="text/javascript" src="https://cdn.datatables.net/1.10.18/js/jquery.dataTables.min.js"></script>

<script type="text/javascript">
$(document).ready(function()  {
  var table =  $('#cartable').DataTable();
} );
</script>
<!-- footers -->
<?php require_once $abs_us_root . $us_url_root . 'usersc/templates/' . $settings->template . '/footer.php'; //custom template footer?>
