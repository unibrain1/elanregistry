<?php

/*
 Delete SPAM users.  A SPAM users is one where the email is not verified, as never logged in, has no CAR and the join_date is over 30 days old.


 */

 require_once '../users/init.php';


$q="
SELECT  users.id
FROM users
LEFT JOIN car_user
ON (users.id = car_user.userid)
where ( users.email_verified = 0 AND users.last_login = 0 AND car_user.carid is NULL AND users.join_date  < CURRENT_DATE - INTERVAL 30 DAY)
GROUP BY users.id 
";


// The DB for SPICE should already be open!
$db = DB::getInstance();


// This should update all the users to make sure they have a profile
$usersQ = $db->query( $q );

echo "Delete ". $usersQ->count() ." SPAM users</br>";

$users = $usersQ->results();
foreach($users as $u)
{
	echo "- user_id ". $u->id ."</br>" ;
	deleteUsers(array($u->id));
	$db->query("DELETE FROM profiles WHERE user_id = ?",array($u->id));
}


?>