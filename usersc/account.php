<?php
require_once '../users/init.php';
require_once $abs_us_root.$us_url_root.'users/includes/template/prep.php';
require_once '../app/validate.php';


if (!securePage($_SERVER['PHP_SELF'])) {
    die();
}


if (!empty($_POST['uncloak'])) {
    logger($user->data()->id, "Cloaking", "Attempting Uncloak");
    if (isset($_SESSION['cloak_to'])) {
        $to = $_SESSION['cloak_to'];
        $from = $_SESSION['cloak_from'];
        unset($_SESSION['cloak_to']);
        $_SESSION['user'] = $_SESSION['cloak_from'];
        unset($_SESSION['cloak_from']);
        logger($from, "Cloaking", "uncloaked from ".$to);
        Redirect::to($us_url_root.'users/admin.php?view=users&err=You+are+now+you!');
    } else {
        Redirect::to($us_url_root.'users/logout.php?err=Something+went+wrong.+Please+login+again');
    }
}


//dealing with if the user is logged in
if ($user->isLoggedIn() || !$user->isLoggedIn() && !checkMenu(2, $user->data()->id)) {
    if (($settings->site_offline==1) && (!in_array($user->data()->id, $master_account)) && ($currentPage != 'login.php') && ($currentPage != 'maintenance.php')) {
        $user->logout();
        Redirect::to($us_url_root.'users/maintenance.php');
    }
}
// Get some interesting user information to display later

$user_id = $user->data()->id;

// USER ID is in $user_id .  Use the USER ID to get the users Profile information
$userQ = $db->query("SELECT * FROM usersview WHERE id = ?", array($user_id));
if ($userQ->count() > 0) {
    $thatUser = $userQ->results();
}
$carQ = $db->query("SELECT * FROM users_carsview WHERE user_id = ?", array($user_id));
if ($carQ->count() > 0) {
    $thatCar = $carQ->results();
}

$signupdate = new DateTime($thatUser[0]->join_date);
$lastlogin = new DateTime($thatUser[0]->last_login);

?>

<!-- Now that that is all out of the way, let's display everything -->

<div id="page-wrapper">
<div class="container-fluid">
<div class="well">
<br>

<div class="row">
	<div class="col-4">
		<div class="card card-default">
			<div class="card-header"><h2><strong>Account Information</strong></h2></div>
			<div class="card-body">
				<table id="accounttable" class="table table-striped table-bordered table-sm" >	
					<tr ><td ><strong>First name      : </strong></td><td ><?= ucfirst($thatUser[0]->fname)?></td></tr>
					<tr ><td ><strong>Last name       : </strong></td><td ><?= ucfirst($thatUser[0]->lname)?></td></tr>
					<tr ><td ><strong>Email           : </strong></td><td ><?= $thatUser[0]->email?></td></tr>
					<tr ><td ><strong>City            : </strong></td><td ><?= html_entity_decode($thatUser[0]->city);?></td></tr>
					<tr ><td ><strong>State           : </strong></td><td ><?= html_entity_decode($thatUser[0]->state);?></td></tr>
					<tr ><td ><strong>Country         : </strong></td><td ><?= html_entity_decode($thatUser[0]->country);?></td></tr>
					<tr ><td ><strong>Website         : </strong></td>
					<?php
                    if (!empty($thatUser[0]->website)) {
                        echo '<td> <a target="_blank"  href='.$thatUser[0]->website.'>Website</a></td>';
                    } else {
                        echo "<td></td></tr>";
                    }
                    ?>
					<tr ><td ><strong>Member Since    : </strong></td><td ><?= $signupdate->format("Y-m-d")?></td></tr>
					<tr ><td ><strong>Member Since    : </strong></td><td ><?= $lastlogin->format("Y-m-d")?></td></tr>
					<tr ><td ><strong>Number of Logins: </strong></td><td ><?= $thatUser[0]->logins?></td></tr>
					
					<tr ><td ><a class="btn btn-success" href=<?=$us_url_root."users/user_settings.php"?> >Update Account Info</a><td></tr>
				</table>
			
			</div>
		</div>
	</div> 
	<div class="col">

		<div class="card border-default">
			<div class="card-header"><h2><strong>Your Car Information</strong></h2></div>
			<div class="card-body">
			<?php
            
            // If there is car information then display it
            
            if (empty($thatCar)) {
                // 	If the user does not have a car then display the add car form</li>
                    ?>
					<a class="btn btn-success" href=<?=$us_url_root."app/edit_car.php"?> role="button">Add Car</a>
					<?php
            } else {
                // Else there is car information then display it
                foreach ($thatCar as $car) {
                    // output data of each row.  View has both cars and users?>

				<table id="cartable-<?=$car->id?>" class="table table-striped table-bordered table-sm" >	

					<tr class="table-success"><th><strong>Car ID :</strong></th><th><?=$car->id?></th></tr>
					<tr ><td ><strong>Model :</strong></td><td ><?=$car->model?></td></tr>
					<tr ><td ><strong>Series :</strong></td><td ><?=$car->series?></td></tr>
					<tr ><td ><strong>Variant:</strong></td><td ><?=$car->variant?></td></tr>
					<tr ><td ><strong>Year :</strong></td><td ><?=$car->year?></td></tr>
					<tr ><td ><strong>Type:</strong></td><td ><?=$car->type?></td></tr>
					<tr ><td ><strong>Chassis :</strong></td><td ><?=$car->chassis?></td></tr>
					<tr ><td ><strong>Color:</strong></td><td ><?=$car->color?></td></tr>
					<tr ><td ><strong>Engine :</strong></td><td ><?=$car->engine?></td></tr>
					<tr ><td ><strong>Purchase Date:</strong></td><td ><?=$car->purchasedate?></td></tr>
					<tr ><td ><strong>Sold Date :</strong></td><td ><?=$car->solddate?></td></tr>
					<tr ><td ><strong>Comments:</strong></td><td ><?=$car->comments?></td></tr>
					<tr ><td ><strong>Created:</strong></td><td ><?=$car->ctime?></td></tr>
					<tr ><td ><strong>Last Modified:</strong></td><td ><?=$car->mtime?></td></tr>
					<?php
                    if ($car->image) {
                        ?>
						<tr ><td ><strong>Image:</strong></td>
						<td ><img alt="my car" class="card-img-top" src=<?=$us_url_root?>app/userimages/<?=$car->image?> ></td></tr>
					<?php
                    } ?>
					<?php
                    // Search in the elan_factory_info for details on the car.
                    // The car.chassis can either match exactly (car.chassis = elan_factory_info.serial )
                    //    or
                    // The right most 5 digits of the car.chassis (post 1970 and some 1969) will =  elan_factory_info.serial

                    $search = array($car->chassis, substr($car->chassis, -5));

                    $carFactory = [];
                    foreach ($search as $s) {
                        $factoryQ = $db->query('SELECT * FROM elan_factory_info WHERE serial = ? ', [$s]);
                        // Did it return anything?
                        if ($factoryQ->count() != 0) {
                            // Yes it did
                            $carFactory = $carQ->results();
                            if ($carFactory[0]->suffix != "") {
                                $carFactory[0]->suffix = $carFactory[0]->suffix . " (" . suffixtotext($carFactory[0]->suffix) .")";
                            } ?>
								<tr class="table-info"><td colspan=2 ><strong>Factory Data - <small>I've lost track of where this data originated and it may be incomplete, inaccurate, false, or just plain made up.</small></strong></td></tr>

								<tr ><td ><strong>Year:</strong></td><td ><?=$carFactory[0]->year?></td></tr>
								<tr ><td ><strong>Month:</strong></td><td ><?=$carFactory[0]->month?></td></tr>
								<tr ><td ><strong>Production Batch:</strong></td><td ><?=$carFactory[0]->batch?></td></tr>
								<tr ><td ><strong>Type:</strong></td><td ><?=$carFactory[0]->type?></td></tr>
								<tr ><td ><strong>Chassis:</strong></td><td ><?=$carFactory[0]->serial?></td></tr>
								<tr ><td ><strong>Suffix:</strong></td><td ><?=$carFactory[0]->suffix?></td></tr>
								<tr ><td ><strong>Engine:</strong></td><td ><?=$carFactory[0]->engineletter?><?=$carFactory[0]->enginenumber?></td></tr>
								<tr ><td ><strong>Gearbox:</strong></td><td ><?=$carFactory[0]->gearbox?></td></tr>
								<tr ><td ><strong>Color:</strong></td><td ><?=$carFactory[0]->color?></td></tr>
								<tr ><td ><strong>Build Date:</strong></td><td ><?=$carFactory[0]->builddate?></td></tr>
								<tr ><td ><strong>Notes:</strong></td><td ><?=$carFactory[0]->note?></td></tr>
							<?php
                            break;
                        }
                    } ?>
					</table>
					<br>
					<div class="col">
						<div class="form-group row">
							<form method = 'POST' action=<?=$us_url_root.'app/edit_car.php'?> >
								<input type="hidden" name="csrf" value="<?= Token::generate(); ?>" />
								<input type="hidden" name="action" value="update_car" />
								<input type="hidden" name="car_id" value="<?=$car->id?>" />
								<button class="btn btn-success" type="submit">Update Car</button>
							</form>
							<a class="btn btn-info" role="button" href="<?=$us_url_root?>app/car_details.php?car_id=<?=$car->id?>">Details</a>
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

<!-- footers -->
<?php require_once $abs_us_root.$us_url_root.'users/includes/page_footer.php'; // the final html footer copyright row + the external js calls?>

<!-- Place any per-page javascript here -->
<script type="text/javascript">
$(document).ready(function(){
  $('[data-toggle="tooltip"]').tooltip();
});
</script>




<!-- footers -->
<?php require_once $abs_us_root . $us_url_root . 'usersc/templates/' . $settings->template . '/footer.php'; //custom template footer?>
