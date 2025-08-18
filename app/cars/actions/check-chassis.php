<?php

// Check to see if the chassis number is taken
require_once '../../../users/init.php';
// Get the DB
$db = DB::getInstance();

//Forms posted now process it
if (Input::exists('post')) {
    $token = Input::get('csrf');
    if (!Token::check($token)) {
        include($abs_us_root . $us_url_root . 'usersc/scripts/token_error.php');
    } else {
        $command = Input::get('command');
        if ($command) {
            if ($command === 'chassis_check') {
                $year = Input::get('year');
                list($series, $variant, $type) = explode('|', Input::get('model'));
                $chassis = Input::get('chassis');
                $carQ = $db->query('SELECT * FROM cars WHERE year=? AND type = ? AND chassis=?', [$year, $type, $chassis]);
                // Did it return anything?
                if ($carQ->count() !== 0) {
                    // Yes it did
                    echo "taken";
                } else {
                    echo 'not_taken';
                }
            } else {
                echo "Called with invalid CMD";
            }
        }
    }
}
