<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../users/init.php';

// Get the users data
$db = DB::getInstance();

$messages[] = "";

?>
<h2>Update geocoded addresses and lat/Llin<br></h2>
<?php
echo date("h:i:sa");
?>
<p>
	<u>Progress</u>
</p>



<?php

updateTable('profiles');
updateTable('cars');

dump($messages);


function updateTable($table)
{
	global $db;
	global $messages;
	$profileData = $db->findAll($table)->results();

	$total = count($profileData);

	foreach ($profileData as $key => $profile) {
		outputMessage($key, $table . " - " . $key . " of " . $total);

		$address = $profile->city . "," . $profile->state . "," . $profile->country;

		// get latitude, longitude and formatted address
		$data_arr = geocode($address);
		if ($data_arr) {
			$fields['lat'] = round($data_arr[0], 2);
			$fields['lon'] = round($data_arr[1], 2);

			// if (round($profile->lat, 2) != $fields['lat'] || round($profile->lon, 2) != $fields['lon']) {
			if (round($profile->lat, 2) != $fields['lat'] || round($profile->lon, 2) != $fields['lon']) {
				$messeges[] = $key . " of " . $total . " - Update " . $table . " ID " . $profile->id . " user_id " . $profile->user_id . " LAT " . $profile->lat . " " . $fields['lat'] . " LON " . $profile->lon . " " . $fields['lon'];
				$db->update($table, $profile->id, $fields);
				if ($db->error()) {
					outputMessage($key, $db->errorString());
				}
			}
		} else {
			$messages[] = $key . " of " . $total . " - Error " . $table . " ID " . $profile->id . " Cannot geocode address: " . $address;
		}
	}
}

function geocode($address)
{
	// url encode the address
	$address = urlencode($address);

	// google map geocode api url
	$url = "https://maps.googleapis.com/maps/api/geocode/json?address={$address}&key=AIzaSyDe6iL2X8LI5jQY_7NLOPReQmuEEBVc0Oc";

	// get the json response
	$resp_json = file_get_contents($url);

	// decode the json
	$resp = json_decode($resp_json, true);

	// response status will be 'OK', if able to geocode given address 
	if ($resp['status'] == 'OK') {

		// get the important data
		$lati = isset($resp['results'][0]['geometry']['location']['lat']) ? $resp['results'][0]['geometry']['location']['lat'] : "";
		$longi = isset($resp['results'][0]['geometry']['location']['lng']) ? $resp['results'][0]['geometry']['location']['lng'] : "";
		$formatted_address = isset($resp['results'][0]['formatted_address']) ? $resp['results'][0]['formatted_address'] : "";

		// verify if data is complete
		if ($lati && $longi && $formatted_address) {

			// put the data in the array
			$data_arr = array();

			array_push(
				$data_arr,
				$lati,
				$longi,
				$formatted_address
			);

			return $data_arr;
		} else {
			return false;
		}
	} else {

		return false;
	}
}


function outputMessage($current, $message)
{
	$pad = str_pad($message, 80);
	echo "<span style='position: absolute;z-index:$current;background:#FFF;'>" . " " . date('h:i:sa') . " - " . $pad . "<br></span>";
	myFlush();
}

/**
 * Flush output buffer
 */
function myFlush()
{
	echo str_repeat(' ', 256);
	if (@ob_get_contents()) {
		@ob_end_flush();
	}
	flush();
}
