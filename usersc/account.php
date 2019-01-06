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

<?php if (!securePage($_SERVER['PHP_SELF'])){die();}?>
<?php
if(!empty($_POST['uncloak'])){
	if(isset($_SESSION['cloak_to'])){
		$to = $_SESSION['cloak_to'];
		$from = $_SESSION['cloak_from'];
		unset($_SESSION['cloak_to']);
		$_SESSION['user'] = $_SESSION['cloak_from'];
		unset($_SESSION['cloak_from']);
		logger($from,"Cloaking","uncloaked from ".$to);
		Redirect::to($us_url_root.'users/admin_users.php?err=You+are+now+you!');
		}else{
			Redirect::to($us_url_root.'users/logout.php?err=Something+went+wrong.+Please+login+again');
		}
}

//dealing with if the user is logged in
if($user->isLoggedIn() || !$user->isLoggedIn() && !checkMenu(2,$user->data()->id)){
	if (($settings->site_offline==1) && (!in_array($user->data()->id, $master_account)) && ($currentPage != 'login.php') && ($currentPage != 'maintenance.php')){
		$user->logout();
		Redirect::to($us_url_root.'users/maintenance.php');
	}
}
// Get some interesting user information to display later

$user_id = $user->data()->id;

// USER ID is in $user_id .  Use the USER ID to get the users Profile information
$userQ = $db->query("SELECT * FROM users_carsView WHERE user_id = ?",array($user_id));
if ($userQ->count() > 0) {
	$thatUser = $userQ->results();
}
else {
	echo 'something is wrong with the user profile </br>';
}
?>
<?php
$raw = date_parse($thatUser[0]->join_date);
$signupdate = $raw['month']."/".$raw['day']."/".$raw['year'];
$raw = date_parse($thatUser[0]->last_login);
$lastlogin = $raw['month']."/".$raw['day']."/".$raw['year'];
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
				<p><a align="left" class="btn btn-success" href=<?=$us_url_root."/users/user_settings.php"?>>Edit Account Info</a></p>

				<table class="pme-main">
				<tr ><td class="pme-cell-0"><strong>Username    :</strong><td><td class="pme-cell-0"><?=echousername($thatUser[0]->id)?></td></tr>
				<tr ><td class="pme-cell-1"><strong>First name  :</strong><td><td class="pme-cell-1"><?=ucfirst($thatUser[0]->fname)?></td></tr>
				<tr ><td class="pme-cell-0"><strong>Last name   :</strong><td><td class="pme-cell-0"><?=ucfirst($thatUser[0]->lname)?></td></tr>
				<tr ><td class="pme-cell-1"><strong>Email       :</strong><td><td class="pme-cell-1"><?=$thatUser[0]->email?></td></tr>
				<tr ><td class="pme-cell-1"><strong>City        :</strong><td><td class="pme-cell-1"><?=html_entity_decode($thatUser[0]->city);?></td></tr>
				<tr ><td class="pme-cell-0"><strong>State       :</strong><td><td class="pme-cell-0"><?=html_entity_decode($thatUser[0]->state);?></td></tr>
				<tr ><td class="pme-cell-1"><strong>Country     :</strong><td><td class="pme-cell-1"><?=html_entity_decode($thatUser[0]->country);?></td></tr>
				<tr ><td class="pme-cell-0"><strong>Member Since:</strong><td><td class="pme-cell-0"><?=$signupdate?></td></tr>
				<tr ><td class="pme-cell-0"><strong>Last Login  :</strong><td><td class="pme-cell-0"><?=$lastlogin?></td></tr>
				<tr ><td class="pme-cell-1"><strong>Number of Logins:</strong><td><td class="pme-cell-1"> <?=$thatUser[0]->logins?></td></tr>
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
			$cars = $thatUser;
			
			if( empty($cars) ) {
					// 	If the user does not have a car then display the add car form</li>
					?>
					<a align="center" class="btn btn-success" href=<?=$us_url_root."app/add_car.php"?> role="button">Add Car</a>
					<?php
			} else {
				// Else there is car information then display it 
				foreach($cars as $car){
   					// output data of each row.  View has both cars and users
					?>
					<p> <a align="left" class="btn btn-success" href=<?=$us_url_root."app/edit_car.php"?> role="button">Update Car</a> </p>

					<table class="pme-main">
						<tr ><td class="pme-cell-0"><strong>Series :</strong><td><td class="pme-cell-0"><?=$car->series?></td></tr>
						<tr ><td class="pme-cell-1"><strong>Variant:</strong><td><td class="pme-cell-1"><?=$car->variant?></td></tr>
						<tr ><td class="pme-cell-0"><strong>Year :</strong><td><td class="pme-cell-0"><?=$car->year?></td></tr>
						<tr ><td class="pme-cell-1"><strong>Type:</strong><td><td class="pme-cell-1"><?=$car->type?></td></tr>
						<tr ><td class="pme-cell-0"><strong>Chassis :</strong><td><td class="pme-cell-0"><?=$car->chassis?></td></tr>
						<tr ><td class="pme-cell-1"><strong>Color:</strong><td><td class="pme-cell-1"><?=$car->color?></td></tr>
						<tr ><td class="pme-cell-0"><strong>Engine :</strong><td><td class="pme-cell-0"><?=$car->engine?></td></tr>
						<tr ><td class="pme-cell-1"><strong>Purchase Date:</strong><td><td class="pme-cell-1"><?=$car->purchasedate?></td></tr>
						<tr ><td class="pme-cell-0"><strong>Sold Date :</strong><td><td class="pme-cell-0"><?=$car->solddate?></td></tr>
						<tr ><td class="pme-cell-1"><strong>Comments:</strong><td><td class="pme-cell-1"><?=$car->comments?></td></tr>
						<tr ><td class="pme-cell-1"><strong>Created:</strong><td><td class="pme-cell-1"><?=$car->ctime?></td></tr>
						<tr ><td class="pme-cell-1"><strong>Last Modified:</strong><td><td class="pme-cell-1"><?=$car->mtime?></td></tr>
						<?php
						if($car->image) {
						?>
							<tr ><td class="pme-cell-1"><strong>Image:</strong><td>
							<td class="pme-cell-1"><img src=<?=$us_url_root?>app/userimages/<?=$car->image?> width='430'></td></tr>
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
<?php require_once $abs_us_root.$us_url_root.'users/includes/page_footer.php'; // the final html footer copyright row + the external js calls ?>

<!-- Place any per-page javascript here -->
<script type="text/javascript">
$(document).ready(function(){
  $('[data-toggle="tooltip"]').tooltip();
});
</script>

<?php require_once $abs_us_root.$us_url_root.'users/includes/html_footer.php'; // currently just the closing /body and /html ?>
