<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

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
<?php
require_once '../users/init.php';
require_once $abs_us_root.$us_url_root.'users/includes/template/prep.php';
?>

<?php if (!securePage($_SERVER['PHP_SELF'])) {
    die();
} ?>

<?php
//PHP Goes Here!

?>
<div id="page-wrapper">
	<div class="container">
	<br>
		<div class="row">
			<div class="col-md-12 col-md-offset-8">
				<form name="contactform" method="post" action="send_form_email.php">
					<fieldset>
						<legend>Feedback</legend>
						<div class="form-group row">
							<label for="id" class="col-sm-2 col-form-label">ID</label>
							<div class="col-sm-10">
								<input type="text" readonly class="form-control-plaintext" id="id" name="id" value=<?= $user->data()->id ?>>
							</div>
						</div>		
						<div class="form-group row">
							<label for="name" class="col-sm-2 col-form-label">Name</label>
							<div class="col-sm-10">
								<input type="text" readonly class="form-control-plaintext" id="name" name="name"" value="<?php echo $user->data()->fname . ' ' . $user->data()->lname;?>">
							</div>
						</div>
						<div class="form-group row">
							<label for="email" class="col-sm-2 col-form-label">Email</label>
							<div class="col-sm-10">
								<input type="text" readonly class="form-control-plaintext" id="email" name="email" value=<?= $user->data()->email ?>>
							</div>
						</div>
						<div class="form-group row">
							<label for="comments" class="col-sm-2 col-form-label">Comments</label>
							<div class="col-sm-10">
								<textarea required class="form-control" name="comments" maxlength="1000" cols="60" rows="10"></textarea>
							</div>
						</div>
						
					</fieldset>
					<input class='btn btn-primary' type='submit' value='submit' class='Submit' /></p>
				</form>
			</div> <!-- /.col -->
		</div> <!-- /.row -->
	</div> <!-- /.container -->
</div> <!-- /.wrapper -->


<!-- footers -->
<?php require_once $abs_us_root . $us_url_root . 'usersc/templates/' . $settings->template . '/footer.php'; //custom template footer?>
