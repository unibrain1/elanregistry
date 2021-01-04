<?php

// From https://developers.google.com/maps/documentation/javascript/mysql-to-maps#domfunctions

require_once '../users/init.php';

if (!securePage($_SERVER['PHP_SELF'])) {
    die();
}

// Get the cars data
$db = DB::getInstance();
$carData = $db->findAll("cars")->results();

// Start XML file, create parent node
$doc = new DOMDocument('1.0', 'utf-8');
$parnode = $doc->appendChild($doc->createElement('markers'));

header("Content-type: text/xml");

// Iterate through the rows, adding XML nodes for each
foreach ($carData as $car) {
    $carImages = explode(',', $car->image);
    // Add to XML document node
    $node = $doc->createElement("marker");
    $newnode = $parnode->appendChild($node);

    $newnode->setAttribute("id", $car->id);
    $newnode->setAttribute("series", $car->series);
    $newnode->setAttribute("year", $car->year);
    $newnode->setAttribute("variant", $car->variant);
    $count = count($carImages);
    if ($count != 0) {
        $newnode->setAttribute("image", $carImages[0]);
    } else {
        $newnode->setAttribute("image", "");
    }
    $newnode->setAttribute("url", $car->website);
    $newnode->setAttribute("type", $car->type);
    $newnode->setAttribute("lat", random($car->lat));
    $newnode->setAttribute("lng", random($car->lon));
}

echo $doc->saveXML();

// Randomize the lat/lon info so pins don't stack on top of each other
function random($num)
{
    return $num + (rand(-1000, 1000) / 10000);
}
