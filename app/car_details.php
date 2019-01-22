<?php
/*

*/
?>
<?php require_once '../users/init.php'; ?>
<?php require_once $abs_us_root.$us_url_root.'users/includes/header.php'; ?>
<?php require_once $abs_us_root.$us_url_root.'users/includes/navigation.php'; ?>

<?php if (!securePage($_SERVER['PHP_SELF'])) {
    die();
}?>
<?php

// Get some interesting user information to display later

$user_id = $user->data()->id;
    
 if (!empty($_GET)) {
     $id = $_GET['car_id'];
     $car_id = Input::sanitize($id);

     $carQ = $db->findById($car_id, "users_carsview");
     $carData = $carQ->results();

     // $carQ = $db->query('SELECT * FROM cars_hist	 WHERE id=?', [$car_id] );
     // $carHist = $carQ->results();

     $raw = date_parse($carData[0]->join_date);
     $signupdate = $raw['year']."-".$raw['month']."-".$raw['day'];
 } else {
     // Shouldn't be here unless someone is mangling the url
     Redirect::to($us_url_root."/app/list_cars.php");
 }
?>
<!-- Now that that is all out of the way, let's display everything -->

<div id="page-wrapper">
<div class="container">
<div class="well">
<h1>Car Information</h1></br>

<div class="row">
	<div class="col-xs-12 col-md-6">
		<div class="panel panel-default">
			<div class="panel-body">
				<table id="cartable" width="100%" class='display'>	

				<tr ><td ><strong>Series:</strong><td><td ><?=$carData[0]->series?></td></tr>
				<tr ><td ><strong>Variant:</strong><td><td ><?=$carData[0]->variant?></td></tr>
				<tr ><td ><strong>Year:</strong><td><td ><?=$carData[0]->year?></td></tr>
				<tr ><td ><strong>Type:</strong><td><td ><?=$carData[0]->type?></td></tr>
				<tr ><td ><strong>Chassis :</strong><td><td ><?=$carData[0]->chassis?></td></tr>
				<tr ><td ><strong>Color:</strong><td><td ><?=$carData[0]->color?></td></tr>
				<tr ><td ><strong>Engine :</strong><td><td ><?=$carData[0]->engine?></td></tr>
				<tr ><td ><strong>Purchase Date:</strong><td><td ><?=$carData[0]->purchasedate?></td></tr>
				<tr ><td ><strong>Sold Date :</strong><td><td ><?=$carData[0]->solddate?></td></tr>
				<tr ><td ><strong>Comments:</strong><td><td ><?=$carData[0]->comments?></td></tr>
				
				<tr ><td ><strong>First name:</strong><td><td ><?=ucfirst($carData[0]->fname)?></td></tr>
				<tr ><td ><strong>City</strong><td><td ><?=html_entity_decode($carData[0]->city);?></td></tr>
				<tr ><td ><strong>State:</strong><td><td ><?=html_entity_decode($carData[0]->state);?></td></tr>
				<tr ><td ><strong>Country:</strong><td><td ><?=html_entity_decode($carData[0]->country);?></td></tr>
				<tr ><td ><strong>Member Since:</strong><td><td ><?=$signupdate?></td></tr>
				<tr ><td ><strong>Record Created:</strong><td><td ><?=$carData[0]->ctime?></td></tr>
				<tr ><td ><strong>Record Modified:</strong><td><td ><?=$carData[0]->mtime?></td></tr>
		
				</table>

			</div>
		</div>
	</div> <!-- col-xs-12 col-md-6 -->
	<div class="col-xs-12 col-md-6">

		<div class="panel panel-default">
			<div class="panel-body">
				<?php
                if ($carData[0]->image) {
                    ?>
					<img src=<?=$us_url_root?>app/userimages/<?=$carData[0]->image?> width='390'>
				<?php
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
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.18/css/jquery.dataTables.min.css"/>
<script type="text/javascript" src="https://cdn.datatables.net/1.10.18/js/jquery.dataTables.min.js"></script>

<script type="text/javascript">
$(document).ready(function()  {
 var table =  $('#cartable').DataTable();
} );
</script>

<?php require_once $abs_us_root.$us_url_root.'users/includes/html_footer.php'; // currently just the closing /body and /html?>
