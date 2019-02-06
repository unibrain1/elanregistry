
<?php

require_once '../users/init.php';
// require_once $abs_us_root.$us_url_root.'users/includes/header.php';
// require_once $abs_us_root.$us_url_root.'users/includes/navigation.php';

?>
<?php

//  Open the ORIGINAL DB

$host         = 'localhost';
$username     = 'elanregi_reg';
$password     = 'PASSWORD';
$db           = 'elanregi_reg';

$reg_conn = new mysqli($host, $username, $password,$db) or die("Connect failed: %s\n". $reg_conn -> error);

// Check connection
if (!$reg_conn) {
    die("Connection failed: " . mysqli_connect_error());
}
echo "Connected successfully<br>";

// The DB for SPICE should already be open!
$user_id = $user->data()->id;
$db = DB::getInstance();


echo "I am user_id ". $user_id ."<br>";


for($i = 11388; $i<=12501; $i++) {
		// Some default values
    	$users_fields['user_id'] = $i;
    	$users_fields['permission_id'] = 1;
		echo "user_id: ".$i."</br>";
    	$db->insert('user_permission_matches', $users_fields);
}


?>

