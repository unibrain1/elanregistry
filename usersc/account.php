<?php
/*
UserSpice 4
An Open Source PHP User Management System
by the UserSpice Team at http://UserSpice.com

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/
?>
<?php require_once '../users/init.php'; ?>
<?php require_once $abs_us_root.$us_url_root.'users/includes/header.php'; ?>
<?php require_once $abs_us_root.$us_url_root.'users/includes/navigation.php'; ?>

<?php if (!securePage($_SERVER['PHP_SELF'])) {
    die();
}?>
<?php
if (!empty($_POST['uncloak'])) {
    if (isset($_SESSION['cloak_to'])) {
        $to = $_SESSION['cloak_to'];
        $from = $_SESSION['cloak_from'];
        unset($_SESSION['cloak_to']);
        $_SESSION['user'] = $_SESSION['cloak_from'];
        unset($_SESSION['cloak_from']);
        logger($from, "Cloaking", "uncloaked from ".$to);
        Redirect::to($us_url_root.'users/admin_users.php?err=You+are+now+you!');
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
<div class="container">
<div class="well">
<h1>Account Information</h1></br>

<div class="row">
	<div class="col-xs-12 col-md-6">
		<div class="panel panel-default">
			<div class="panel-heading"><strong>Account Information</strong></div>
			<div class="panel-body">

				<table id="datatable" width="100%" class='display'>	

				<tr ><td >
						<a align="left"   class="btn btn-success" href=<?=$us_url_root."users/user_settings.php"?> >Account Info</a>
					<td>
				</tr>
				<tr ><td ><strong>Username    :</strong></td><td ><?=echousername($thatUser[0]->id)?></td></tr>
				<tr ><td ><strong>First name  :</strong></td><td ><?=ucfirst($thatUser[0]->fname)?></td></tr>
				<tr ><td ><strong>Last name   :</strong></td><td ><?=ucfirst($thatUser[0]->lname)?></td></tr>
				<tr ><td ><strong>Email       :</strong></td><td ><?=$thatUser[0]->email?></td></tr>
				<tr ><td ><strong>City        :</strong></td><td ><?=html_entity_decode($thatUser[0]->city);?></td></tr>
				<tr ><td ><strong>State       :</strong></td><td ><?=html_entity_decode($thatUser[0]->state);?></td></tr>
				<tr ><td ><strong>Country     :</strong></td><td ><?=html_entity_decode($thatUser[0]->country);?></td></tr>
				<tr ><td ><strong>Member Since:</strong></td><td ><?=$signupdate?></td></tr>
				<tr ><td ><strong>Last Login  :</strong></td><td ><?=$lastlogin?></td></tr>
				<tr ><td ><strong>Number of Logins:</strong></td><td > <?=$thatUser[0]->logins?></td></tr>
				<tr ><td ><strong>Website:</strong></td>
				<?php
				if(!empty($thatUser[0]->website)){
					echo '<td> <a target="_blank"  href='.$thatUser[0]->website.'>Website</a></td>';
                 } else {
                    echo "<td></td></tr>";
                 }
                 ?>
				</table>
			
			</div>
		</div>
	</div> <!-- col-xs-12 col-md-6 -->
	<div class="col-xs-12 col-md-6">

		<div class="panel panel-default">
			<div class="panel-heading"><strong>Your Car Information</strong></div>
			<div class="panel-body">
			<?php
            
            // If there is car information then display it
            // $cars = $thatCar;
            
            if (empty($thatCar)) {
                // 	If the user does not have a car then display the add car form</li>
                    ?>
					<a align="center" class="btn btn-success" href=<?=$us_url_root."app/add_car.php"?> role="button">Add Car</a>
					<?php
            } else {
                // Else there is car information then display it
                foreach ($thatCar as $car) {
                    // output data of each row.  View has both cars and users ?>

				<table id="datatable1" width="100%" class='display'>	
					<tr ><td>
						 <form  method = 'get' action = <?=$us_url_root.'app/edit_car.php'?> >
              					<input class='btn btn-success' type = 'submit' value = 'Update Car' >
								<input type='hidden' name='car_id' value='<?=$car->id?>'>
						</form>
						<td></td>
					</tr>
					<tr><td><strong>Car ID :</strong></td><td><?=$car->id?></td></tr>
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
						<td ><img class="img-responsive" src=<?=$us_url_root?>app/userimages/<?=$car->image?> >
</td></tr>
					<?php
                    } ?>
				</table>
				<?php
                }
            } ?>

			</div> <!-- panel-body -->
		</div> <!-- panel -->
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
 var table =  $('#datatable').DataTable();
} );
</script>
<script type="text/javascript">
$(document).ready(function()  {
 var table =  $('#datatable1').DataTable();
} );
</script>



<?php require_once $abs_us_root.$us_url_root.'users/includes/html_footer.php'; // currently just the closing /body and /html?>
