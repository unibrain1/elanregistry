<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


require_once '../users/init.php';
// The DB for SPICE should already be open!
$db = DB::getInstance();


echo "Deleting old administrative update records";

$q = "
    DELETE FROM `cars_hist` WHERE timestamp LIKE '2020-05-06 13:2%';
    DELETE FROM `cars_hist` WHERE timestamp LIKE '2020-05-22 10:5%';

    DELETE FROM `cars_hist` WHERE timestamp LIKE '2020-05-06 15:2%';
    DELETE FROM `cars_hist` WHERE timestamp LIKE '2020-05-22 12:5%';

    DELETE FROM `cars_hist` WHERE timestamp LIKE '2020-05-06 15:2%';
    DELETE FROM `cars_hist` WHERE timestamp LIKE '2020-05-22 12:5%';

    DELETE FROM `cars_hist` WHERE timestamp LIKE '2020-05-05 14%';
    DELETE FROM `cars_hist` WHERE timestamp LIKE '2020-05-05 12%';
    DELETE FROM `cars_hist` WHERE timestamp LIKE '2019-02-09 17%';
    DELETE FROM `cars_hist` WHERE timestamp LIKE '2019-02-02 06%';
    ";


$db->query($q);

if ($db->error()) {
    echo " ERROR ";
    echo $db->errorString();
} else {
    echo " SUCCESS";
}
 echo "</br>";
