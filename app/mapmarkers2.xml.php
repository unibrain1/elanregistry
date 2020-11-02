<?php

// From https://developers.google.com/maps/documentation/javascript/mysql-to-maps#domfunctions

require_once '../users/init.php';

if (!securePage($_SERVER['PHP_SELF'])) {
    die();
}

// Get the cars data
$db = DB::getInstance();
$carData = $db->findAll("users_carsview")->results();

// Start XML file, create parent node
$doc = new DOMDocument('1.0', 'utf-8');
$parnode = $doc->appendChild($doc->createElement('markers'));

header("Content-type: text/xml");

// Iterate through the rows, adding XML nodes for each
foreach ($carData as $v1) {
    // Add to XML document node
    $node = $doc->createElement("marker");
    $newnode = $parnode->appendChild($node);

    $newnode->setAttribute("id", $v1->id);
    $newnode->setAttribute("series", $v1->series);
    $newnode->setAttribute("year", $v1->year);
    $newnode->setAttribute("variant", $v1->variant);
    $newnode->setAttribute("image", $v1->image);
    $newnode->setAttribute("url", $v1->website);
    $newnode->setAttribute("type", $v1->type);
    $newnode->setAttribute("lat", random($v1->lat));
    $newnode->setAttribute("lng", random($v1->lon));
}

echo $doc->saveXML();

// Randomize the lat/lon info so pins don't stack on top of each other
function random($num)
{
    return $num + (rand(-1000, 1000) / 10000);
}
