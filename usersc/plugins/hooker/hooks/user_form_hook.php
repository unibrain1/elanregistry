<?php if (count(get_included_files()) == 1) die(); //Direct Access Not Permitted Leave this line in place
?>

<?php
/*
This will display the users Profile information

*/
global $userId;

$user_id = $userId;

$userQ = $db->query("SELECT * FROM profiles WHERE id = ?", array($user_id));
if ($userQ->count() > 0) {
	$thatUser = $userQ->results();
}

$carQ = $db->query("SELECT * FROM users_carsview WHERE user_id = ?", array($user_id));
if ($carQ->count() > 0) {
	$thatCar = $carQ->results();
}

?>
<table id="accounttable" width="100%" class="table table-striped table-bordered table-sm" cellspacing="0" width="100%">
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
		<td><strong>LAT : </strong></td>
		<td><?= html_entity_decode($thatUser[0]->lat); ?></td>
	</tr>
	<tr>
		<td><strong>LON : </strong></td>
		<td><?= html_entity_decode($thatUser[0]->lon); ?></td>
	</tr>
	<tr>
		<td><strong>CAR ID : </strong></td>
		<td><?= html_entity_decode($thatUser[0]->id); ?></td>
	</tr>
	<tr>
		<td><strong>YEAR : </strong></td>
		<td><?= html_entity_decode($thatCar[0]->year); ?></td>
	</tr>
	<tr>
		<td><strong>TYPE : </strong></td>
		<td><?= html_entity_decode($thatCar[0]->type); ?></td>
	</tr>
	<tr>
		<td><strong>CHASSIS : </strong></td>
		<td><?= html_entity_decode($thatCar[0]->chassis); ?></td>
	</tr>
</table>