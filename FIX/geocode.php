<?php

// define("KEY", "AIzaSyBXQRDsHxF-xqZc-QaH7HK_3C1srIluRLU");


require_once '../users/init.php';


if (!securePage($_SERVER['PHP_SELF'])) {
    die();
}

// Get the cars data
$db = DB::getInstance();

$profileData = $db->findAll("profiles")->results();

foreach ($profileData as $v1) {
	$fields = array();

  	$address = $v1->city.",".$v1->state.",".$v1->country;

	// get latitude, longitude and formatted address
	$data_arr = geocode($address);
	if($data_arr){
		$fields['lat'] = $data_arr[0];
		$fields['lon'] = $data_arr[1];
		// $formatted_address = $data_arr[2];
	}

	if( $v1->lat != $fields['lat'] or $v1->lon != $fields['lon'])
	{
		echo "Update Profie ID ".$v1->id." user_id ".$v1->user_id." LAT ".$v1->lat." ".$fields['lat']." LON ".$v1->lon." ".$fields['lon']."</br>";
		$db->update("profiles", $v1->id, $fields);
		if($db->error()){ 
			echo $db->errorString();
		}
		flush();
	}
}

 // if able to geocode the address


function geocode($address){
 
    // url encode the address
    $address = urlencode($address);
     
    // google map geocode api url
    $url = "https://maps.googleapis.com/maps/api/geocode/json?address={$address}&key=AIzaSyBXQRDsHxF-xqZc-QaH7HK_3C1srIluRLU";

 
    // get the json response
    $resp_json = file_get_contents($url);
     
    // decode the json
    $resp = json_decode($resp_json, true);
 
    // response status will be 'OK', if able to geocode given address 
    if($resp['status']=='OK'){
 
        // get the important data
        $lati = isset($resp['results'][0]['geometry']['location']['lat']) ? $resp['results'][0]['geometry']['location']['lat'] : "";
        $longi = isset($resp['results'][0]['geometry']['location']['lng']) ? $resp['results'][0]['geometry']['location']['lng'] : "";
        $formatted_address = isset($resp['results'][0]['formatted_address']) ? $resp['results'][0]['formatted_address'] : "";
         
        // verify if data is complete
        if($lati && $longi && $formatted_address){
         
            // put the data in the array
            $data_arr = array();            
             
            array_push(
                $data_arr, 
                    $lati, 
                    $longi, 
                    $formatted_address
                );
             
            return $data_arr;
             
        }else{
            return false;
        }
         
    }
 
    else{
        echo "<strong>ERROR: {$resp['status']}</strong>";
        return false;
    }
}

?>
