<?php

// Check to see if the chassis number is taken
require_once '../../users/init.php';
// Get the DB
$db = DB::getInstance();

//Forms posted now process it
if (!empty($_POST)) {
    $token = Input::get('csrf');
    if (!Token::check($token)) {
        include($abs_us_root . $us_url_root . 'usersc/scripts/token_error.php');
    } else {
        if (isset($_POST['command'])) {
            if ($_POST['command'] === 'chassis_check') {
                $year = $_POST['year'];
                list($series, $variant, $type) = explode('|', $_POST['model']);
                $chassis = $_POST['chassis'];
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
