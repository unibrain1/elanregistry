<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


$q="
DROP TRIGGER IF EXISTS `cars_update`;

DELIMITER $$
CREATE TRIGGER `cars_update` AFTER UPDATE ON `cars` FOR EACH ROW IF @disable_triggers IS NULL THEN
INSERT INTO cars_hist(
    operation,
    car_id,
    username,
    ctime,
    mtime,
    ModifiedBy,
    model,
    series,
    variant,
    YEAR,
    TYPE,
    chassis,
    color,
    ENGINE,
    purchasedate,
    solddate,
    comments,
    image,
    user_id,
    email,
    fname,
    lname,
    join_date,
    city,
    state,
    country,
    lat,
    lon,
    website
)
VALUES(
    'UPDATE',
    OLD.id,
    OLD.username,
    OLD.ctime,
    OLD.mtime,
    OLD.ModifiedBy,
    OLD.model,
    OLD.series,
    OLD.variant,
    OLD.year,
    OLD.type,
    OLD.chassis,
    OLD.color,
    OLD.engine,
    OLD.purchasedate,
    OLD.solddate,
    OLD.comments,
    OLD.image,
    OLD.user_id,
    OLD.email,
    OLD.fname,
    OLD.lname,
    OLD.join_date,
    OLD.city,
    OLD.state,
    OLD.country,
    OLD.lat,
    OLD.lon,
    OLD.website
);
END IF
$$
";


echo "Updating cars_hist UPDATE trigger</br></br>";


require_once '../users/init.php';
// The DB for SPICE should already be open!
$db = DB::getInstance();

echo "Run this SQL</br>";

echo $q."</br>";


echo "</br>";
