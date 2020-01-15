
<?php

require_once '../users/init.php';
// require_once $abs_us_root.$us_url_root.'users/includes/header.php';
// require_once $abs_us_root.$us_url_root.'users/includes/navigation.php';


// The DB for SPICE should already be open!
$db = DB::getInstance();
echo "Patch for bug #106</br>";

// This should update all the users to make sure they have a profile
$users = $db->query("SELECT id FROM users")->results();
foreach($users as $u)
{
	$check = $db->query("SELECT id FROM profiles WHERE user_id = ?",[$u->id])->count();
	if($check < 1)
	{
		echo "Fixing user_id ". $u->id ."</br>" ;
  		$db->insert('profiles',['user_id'=>$u->id,'bio'=>"Fixed bug #106 1-15-2020"]);
	}
}
