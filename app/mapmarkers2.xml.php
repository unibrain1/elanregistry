<?php

require_once '../users/init.php';
// require_once $abs_us_root.$us_url_root.'users/includes/header.php';
// require_once $abs_us_root.$us_url_root.'users/includes/navigation.php';

if (!securePage($_SERVER['PHP_SELF'])) {
    die();
}

// Get the cars data
$db = DB::getInstance();

$carData = $db->findAll("users_carsview")->results();

// require("db.php");


// Start XML file, create parent node
// $doc = domxml_new_doc("1.0");
$doc = new DOMDocument('1.0', 'utf-8');
$parnode = $doc->appendChild($doc->createElement('markers'));

// Opens a connection to a MySQL server
// $db_selected=mysqli_connect ('localhost', $username, $password,$database);
// if (!$db_selected) {
//   die('Not connected : ' . mysql_error());
// }

// // Select all the rows in the markers table
// $query = "SELECT * FROM markers WHERE 1";
// $result = mysqli_query($db_selected,$query);
// if (!$result) {
//   die('Invalid query: ' . mysqli_error());
// }

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

// $xmlfile = $doc->dump_mem();
// echo $xmlfile;
echo $doc->saveXML();

// Randomize the lat/lon info so pins don't stack on top of each other
function random($num)
{
    return $num + (rand(-1000, 1000) / 10000);
}
