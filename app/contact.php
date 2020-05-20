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
	<div class="col-xs-12">

		<div class="jumbotron">
					<!-- Content Goes Here. Class width can be adjusted -->

<form name="contactform" method="post" action="send_form_email.php">
	<table>
	<tr> <td width="25%"> <label> First Name </label> </td>  <td> <?php echo $user->data()->fname;?> </td> </tr>
	<tr> <td width="25%"> <label> Last Name </label> </td>  <td> <?php echo $user->data()->lname;?> </td> </tr>
	<tr> <td width="25%"> <label> Email </label> </td>  <td> <?php echo $user->data()->email;?> </td> </tr>
	<tr> <td width="25%"> <label> Account ID </label> </td>  <td> <?php echo $user->data()->id;?> </td> </tr>
	

	 <td valign="top">
	  <label for="comments">Comments</label>
	 </td>
	 <td valign="top">
	  <textarea  name="comments" maxlength="1000" cols="60" rows="10"></textarea>
	 </td>
	</tr>
	<tr>
	 <td colspan="2" style="text-align:center">
	 <input type="hidden" name="fname" value="<?php echo $user->data()->fname;?>" />
	 <input type="hidden" name="lname" value="<?php echo $user->data()->lname;?>" />
	 <input type="hidden" name="email" value="<?php echo $user->data()->email;?>" />
	 <input type="hidden" name="id" value="<?php echo $user->data()->id;?>" />

	 <input class='btn btn-primary' type='submit' value='submit' class='submit' /></p>
	 </td>
	</tr>
</table>
</form>



				</div>	<!-- End of jumbotron content section -->
			</div> <!-- /.col -->
		</div> <!-- /.row -->
	</div> <!-- /.container -->
</div> <!-- /.wrapper -->


<!-- footers -->
<?php require_once $abs_us_root . $us_url_root . 'usersc/templates/' . $settings->template . '/footer.php'; //custom template footer?>
