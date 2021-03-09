<?php
require_once '../users/init.php';
require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php';
require_once '../app/validate.php';


if (!securePage($_SERVER['PHP_SELF'])) {
	die();
}


if (!empty($_POST['uncloak'])) {
	logger($user->data()->id, 'Cloaking', 'Attempting Uncloak');
	if (isset($_SESSION['cloak_to'])) {
		$to = $_SESSION['cloak_to'];
		$from = $_SESSION['cloak_from'];
		unset($_SESSION['cloak_to']);
		$_SESSION['user'] = $_SESSION['cloak_from'];
		unset($_SESSION['cloak_from']);
		logger($from, 'Cloaking', 'uncloaked from ' . $to);
		Redirect::to($us_url_root . 'users/admin.php?view=users&err=You+are+now+you!');
	} else {
		Redirect::to($us_url_root . 'users/logout.php?err=Something+went+wrong.+Please+login+again');
	}
}


//dealing with if the user is logged in
if ($user->isLoggedIn() || !$user->isLoggedIn() && !checkMenu(2, $user->data()->id)) {
	if (($settings->site_offline == 1) && (!in_array($user->data()->id, $master_account)) && ($currentPage != 'login.php') && ($currentPage != 'maintenance.php')) {
		$user->logout();
		logger($user->data()->id, 'Errors', 'Sending to Maint');
		Redirect::to($us_url_root . 'users/maintenance.php');
	}
}
// Get some interesting user information to display later

$user_id = $user->data()->id;

// USER ID is in $user_id .  Use the USER ID to get the users Profile information
$userQ = $db->query("SELECT * FROM usersview WHERE id = ?", array($user_id));
if ($userQ->count() > 0) {
	$thatUser = $userQ->results();
}
$carQ = $db->query("SELECT * FROM cars WHERE user_id = ?", array($user_id));



$cars = findByOwner($user_id);

$signupdate = new DateTime($thatUser[0]->join_date);
$lastlogin = new DateTime($thatUser[0]->last_login);

?>

<!-- Now that that is all out of the way, let's display everything -->

<div id="page-wrapper">
	<div class="container-fluid">
		<div class="well">
			<br>

			<div class="row">
				<div class="col-md-4 col-xs-12">
					<div class="card card-default">
						<div class="card-header">
							<h2><strong>Account Information</strong></h2>
						</div>
						<div class="card-body">
							<table id="accounttable" class="table table-striped table-bordered table-sm" aria-describedby="card-header">
								<tr>
									<th scope=column><strong>First name : </strong></th>
									<th scope=column><?= ucfirst($thatUser[0]->fname) ?></th>
								</tr>
								<tr>
									<td><strong>Last name : </strong></td>
									<td><?= ucfirst($thatUser[0]->lname) ?></td>
								</tr>
								<tr>
									<td><strong>Email : </strong></td>
									<td><?= $thatUser[0]->email ?></td>
								</tr>
								<tr>
									<td><strong>City : </strong></td>
									<td><?= html_entity_decode($thatUser[0]->city); ?></td>
								</tr>
								<tr>
									<td><strong>State : </strong></td>
									<td><?= html_entity_decode($thatUser[0]->state); ?></td>
								</tr>
								<tr>
									<td><strong>Country : </strong></td>
									<td><?= html_entity_decode($thatUser[0]->country); ?></td>
								</tr>
								<tr>
									<td><strong>Member Since : </strong></td>
									<td><?= $signupdate->format("Y-m-d") ?></td>
								</tr>
								<tr>
									<td><strong>Last Login : </strong></td>
									<td><?= $lastlogin->format("Y-m-d") ?></td>
								</tr>
								<tr>
									<td><strong>Number of Logins: </strong></td>
									<td><?= $thatUser[0]->logins ?></td>
								</tr>

								<tr>
									<td><a class="btn btn-success" href=<?= $us_url_root . "users/user_settings.php" ?>>Update Account Info</a>
									<td>
								</tr>
							</table>

						</div>
					</div>
				</div>
				<div class="col-md-8 col-xs-12">
					<div class="card border-default">
						<div class="card-header">
							<h2><strong>Your Car Information</strong></h2>
						</div>
						<div class="card-body">
							<?php

							// If there is car information then display it

							if (empty($cars)) {
								// 	If the user does not have a car then display the add car form</li>
							?>
								<a class="btn btn-success" href=<?= $us_url_root . "app/edit_car.php" ?> role="button">Add Car</a>
								<?php
							} else {
								// Else there is car information then display it
								foreach ($cars as $car) {
									// output data of each row.  View has both cars and users
								?>
									<table style='padding: 0;' id="cartable-<?= $car->data()->id ?>" class="table table-striped table-bordered table-sm" aria-describedby="card-header">
										<tr class="table-success">
											<th scope=column><strong>Car ID :</strong></th>
											<th scope=column><?= $car->data()->id ?></th>
										</tr>
										<tr>
											<td><strong>Model :</strong></td>
											<td><?= $car->data()->model ?></td>
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
											<td><strong>Year :</strong></td>
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
											<td><strong>Website : </strong></td>
											<?php
											if (!empty($car->data()->website)) {
												echo '<td> <a target="_blank"  href=' . $car->data()->website . '>' . $car->data()->website . '</a></td>';
											} else {
												echo "<td></td></tr>";
											}
											?>
										<tr>
											<td><strong>Comments:</strong></td>
											<td><?= $car->data()->comments ?></td>
										</tr>
										<tr>
											<td><strong>Created:</strong></td>
											<td><?= $car->data()->ctime ?></td>
										</tr>
										<tr>
											<td><strong>Last Modified:</strong></td>
											<td><?= $car->data()->mtime ?></td>
										</tr>
										<tr>
											<td><strong>Images:</strong></td>
											<td>
												<?php echo display_carousel($car->data()->image); ?>
											</td>
										</tr>
										<?php
										if (!is_null($car->factory())) {
										?>
											<tr class="table-info">
												<td colspan=2><strong>Factory Data - <small>I' ve lost track of where this data originated and it may be incomplete, inaccurate, false, or just plain made up.</small></strong></td>
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
										<?php }	?>
									</table>
									<div class="col">
										<div class="form-group row">
											<form method='POST' action=<?= $us_url_root . 'app/edit_car.php' ?>>
												<input type="hidden" name="csrf" value="<?= Token::generate(); ?>" />
												<input type="hidden" name="action" value="updateCar" />
												<input type="hidden" name="carid" value="<?= $car->data()->id ?>" />
												<button class="btn btn-success" type="submit">Update Car</button>
											</form>
											<a class="btn btn-info" role="button" href="<?= $us_url_root ?>app/car_details.php?car_id=<?= $car->data()->id ?>">Details</a>
										</div>
									</div>
									<br>
							<?php
								}
							} ?>

						</div> <!-- card-body -->
					</div> <!-- card -->
				</div> <!-- col-xs-12 col-md-6 -->
			</div> <!-- row -->

		</div> <!-- well -->

	</div> <!-- /container -->

</div> <!-- /#page-wrapper -->

<?php
require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php'; //custom template footer
?>