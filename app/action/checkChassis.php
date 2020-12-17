<?php

// Check to see if the chassis number is taken
require_once '../../users/init.php';
// Get the DB
$db = DB::getInstance();

if (isset($_POST['command'])) {
    $command = $_POST['command'];

    switch ($command) {
        case 'chassis_check':
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
            break;

        default:
            echo "Called with invalid CMD";
            break;
    }
}
exit();
