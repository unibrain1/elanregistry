<?php
require_once '../users/init.php';
require_once $abs_us_root.$us_url_root.'users/includes/template/prep.php';


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

?>
<?php
$raw = date_parse($thatUser[0]->join_date);
$signupdate = $raw['year']."-".$raw['month']."-".$raw['day'];
$raw = date_parse($thatUser[0]->last_login);
$lastlogin = $raw['year']."-".$raw['month']."-".$raw['day'];
?>


<!-- Now that that is all out of the way, let's display everything -->

<div id="page-wrapper">
<div class="container-fluid">
<div class="well">
</br>

<div class="row">
	<div class="col-4">
		<div class="card card-default">
			<div class="card-header"><strong><h2>Account Information</h2></strong></div>
			<div class="card-body">
				<table id="accounttable" width="100%" class="table table-striped table-bordered table-sm" cellspacing="0" width="100%">	

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
					<tr ><td ><strong>Member Since    : </strong></td><td ><?= $signupdate?></td></tr>
					<tr ><td ><strong>Last Login      : </strong></td><td ><?= $lastlogin?></td></tr>
					<tr ><td ><strong>Number of Logins: </strong></td><td ><?= $thatUser[0]->logins?></td></tr>
					
					<tr ><td ><a align="left"   class="btn btn-success" href=<?=$us_url_root."users/user_settings.php"?> >Update Account Info</a><td></tr>
				</table>
			
			</div>
		</div>
	</div> 
	<div class="col">

		<div class="card border-default">
			<div class="card-header"><strong><h2>Your Car Information</h2></strong></div>
			<div class="card-body">
			<?php
            
            // If there is car information then display it
            
            if (empty($thatCar)) {
                // 	If the user does not have a car then display the add car form</li>
                    ?>
					<a align="center" class="btn btn-success" href=<?=$us_url_root."app/add_car.php"?> role="button">Add Car</a>
					<?php
            } else {
                // Else there is car information then display it
                foreach ($thatCar as $car) {
                    // output data of each row.  View has both cars and users?>

				<table id="cartable" width="100%" class="table table-striped table-bordered table-sm" cellspacing="0" width="100%">	

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
						<td ><img class="card-img-top" src=<?=$us_url_root?>app/userimages/<?=$car->image?> ></td></tr>
					<?php
                    } ?>
					<tr ><td>
					<form  method = 'get' action = <?=$us_url_root.'app/edit_car.php'?> >
							<input class='btn btn-success' type = 'submit' value = 'Update Car' >
							<input type='hidden' name='car_id' value='<?=$car->id?>'>
					</form>
					<td></td>
					</tr>
				</table>

				</br>
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

<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.18/css/jquery.dataTables.min.css"/>
<script type="text/javascript" src="https://cdn.datatables.net/1.10.18/js/jquery.dataTables.min.js"></script>

<script type="text/javascript">
$(document).ready(function()  {
 var table =  $('#accounttable').DataTable();
} );
</script>

<script type="text/javascript">
$(document).ready(function()  {
  var table =  $('#cartable').DataTable(
    {
      "scrollX": true
    });
} );
</script>


<!-- footers -->
<?php require_once $abs_us_root . $us_url_root . 'usersc/templates/' . $settings->template . '/footer.php'; //custom template footer?>
