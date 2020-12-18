<?php

// Get the car history
// TODO - Error traps and returns
require_once '../../users/init.php';

// Get the DB
$db = DB::getInstance();

$draw = Input::get('draw');
$car_id = Input::get('car_id');

$carQ    = $db->query('SELECT * FROM cars_hist WHERE car_id = ? ORDER BY cars_hist.timestamp DESC', [$car_id]);
$carHist = $carQ->results();
$count   = $carQ->count();
$error   = ""; // Place holder for error messages.  If there is text in here it issues a pop-up.  Do not include if there is no error.

echo json_encode(array('draw' => $draw, 'recordsTotal' => $count, 'recordsFiltered' => $count, 'history' => $carHist));
