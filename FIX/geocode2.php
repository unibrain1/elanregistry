<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


require_once '../users/init.php';

if (!securePage($_SERVER['PHP_SELF'])) {
    die();
}

// Get the cars data
$db = DB::getInstance();

$profileData = $db->findAll("profiles")->results();
// $profileData = $db->query("SELECT * FROM profiles WHERE lat IS NULL OR lon is NULL")->results();

foreach ($profileData as $v1) {
    $fields = array();

    $address = $v1->city.",".$v1->state.",".$v1->country;
    // url encode the address
    $address = urlencode($address);

    echo "<hr>Geocode address ".$address."</br>";

    // get latitude, longitude
    $data_arr = geocode($address);
    if ($data_arr == false) {
        echo "    No results </br>";
    } else {
        $fields['lat'] = round($data_arr[0], 4);
        $fields['lon'] = round($data_arr[1], 4);

        if ($v1->lat != $fields['lat'] or $v1->lon != $fields['lon']) {
            echo "  Update Profie ID ".$v1->id." user_id ".$v1->user_id." OLD LAT ".$v1->lat." with new LAT ".$fields['lat']." OLD LON ".$v1->lon." with new LON".$fields['lon']."</br>";
            $db->update("profiles", $v1->id, $fields);
            if ($db->error()) {
                echo $db->errorString();
                echo "</br>" ;
            }
        } else {
            echo "  No Update Profie ID ".$v1->id."</br>";
        }
    }
    ob_flush();
    flush();
    sleep(2);  // Don't overload the encoding service
}
echo "DONE</br>";

function geocode($address)
{
    // KEY is set in the initialization routine
    global $GEO_ENCODE_KEY;

    // url encode the address
    $address = urlencode($address);

    // google map geocode api url
    $url = "https://maps.googleapis.com/maps/api/geocode/json?address={$address}&key={$GEO_ENCODE_KEY}";

    // get the json response
    $resp_json = file_get_contents($url);

    // decode the json
    $resp = json_decode($resp_json, true);

    // response status will be 'OK', if able to geocode given address
    if ($resp['status']=='OK') {
        // get the important data
        $lati = isset($resp['results'][0]['geometry']['location']['lat']) ? $resp['results'][0]['geometry']['location']['lat'] : "";
        $longi = isset($resp['results'][0]['geometry']['location']['lng']) ? $resp['results'][0]['geometry']['location']['lng'] : "";

        // verify if data is complete
        if ($lati && $longi) {

            // put the data in the array
            $data_arr = array();

            array_push(
                $data_arr,
                $lati,
                $longi
            );

            return $data_arr;
        } else {
            return false;
        }
    } else {
        print_r($resp);
        echo "</br>$url</br>" ;

        return false;
    }
}
