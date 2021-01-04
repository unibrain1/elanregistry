<?php

// Get the car history
require_once '../../users/init.php';

//Forms posted now process it
if (!empty($_POST)) {
    $token = Input::get('csrf');
    if (!Token::check($token)) {
        include($abs_us_root . $us_url_root . 'usersc/scripts/token_error.php');
    } else {
        // Get the DB
        $db = DB::getInstance();

        $draw = Input::get('draw');
        $car_id = Input::get('car_id');

        $carQ    = $db->query('SELECT * FROM cars_hist WHERE car_id = ? ORDER BY cars_hist.timestamp DESC', [$car_id]);
        $carHist = $carQ->results();
        $count   = $carQ->count();
        $error   = ""; // Place holder for error messages.  If there is text in here it issues a pop-up.  Do not include if there is no error.

        echo json_encode(array('draw' => $draw, 'recordsTotal' => $count, 'recordsFiltered' => $count, 'history' => $carHist));
    }
}