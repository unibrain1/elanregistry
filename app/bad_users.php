<?php
 // Remove users with no reason to be here!

require_once '../users/init.php';
require_once $abs_us_root.$us_url_root.'users/includes/template/prep.php';

if (!securePage($_SERVER['PHP_SELF'])) {
    die();
}



$usersQ = $db->query("SELECT  id as userid FROM  users WHERE  users.id NOT IN (SELECT userid FROM car_user)");

if ($usersQ->count() > 0) {
    $users = $usersQ->results();
}


foreach ($users as $u) {
	echo "<p>Remove userID ". $u->userid."</p>";
	deleteUsers(array($u->userid));

}

?>