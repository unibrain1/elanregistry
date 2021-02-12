<?php

/* Sets variables
$fields['lat']
$fields['lon']
*/

$fields = array();

$address = $city . "," . $state . "," . $country;
// url encode the address
$address = urlencode($address);

// get latitude, longitude
$data_arr = geocode($address);
if ($data_arr !== false) {
    $fields['lat'] = round($data_arr[0], 4);
    $fields['lon'] = round($data_arr[1], 4);
}

function geocode($address)
{
    global $settings;
    // url encode the address
    $address = urlencode($address);

    // google map geocode api url
    $url = "https://maps.googleapis.com/maps/api/geocode/json?address={$address}&key={$settings->elan_google_geo_key}";

    // get the json response
    $resp_json = file_get_contents($url);

    // decode the json
    $resp = json_decode($resp_json, true);

    // response status will be 'OK', if able to geocode given address
    if ($resp['status'] == 'OK') {
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
        return false;
    }
}
