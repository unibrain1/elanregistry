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

$sql = "SELECT * FROM users";

$userQ = $reg_conn->query($sql);

if ($userQ->num_rows > 0) {
    // output data of each row
    while($user = $userQ->fetch_assoc()) {

        // Write the new records

        // User Record

    	$users_fields=array();
    	$users_fields['email'] = $user['email'];
    	$users_fields['username'] = $user['username'];
    	$users_fields['fname'] = $user['FirstName'];
    	$users_fields['lname'] = $user['LastName'];
    	$join_date=date('Y/m/d H:i:s',$user['timestamp']);

    	$users_fields['join_date'] = $join_date;

		// Some default values
		$users_fields['permissions'] = 1;
    	$users_fields['email_verified'] = 0;
    	$users_fields['active'] = 1;
    	$users_fields['email_new'] = NULL;


    	$db->insert('users', $users_fields);

        if ($db->error()) {
            echo "Add User Error".$db->errorString()."<br>";
        } else 
        {
            // Grab the id of the last insert
            $user_id = $db->lastId();
          
            echo "Add User - ".$user['username']. " ID - ".$user_id." Join: - ".$join_date." - ";

			// User Profile
			$profiles_fields=array();
			$profiles_fields['user_id'] = $user_id;
			$profiles_fields['city'] = $user['City'];
			$profiles_fields['state'] = $user['State'];
			$profiles_fields['country'] = $user['Country'];
			$profiles_fields['lat'] = $user['lat'];
			$profiles_fields['lon'] = $user['lon'];
			$profiles_fields['website'] = $user['WebSite'];

			$db->insert('profiles', $profiles_fields);
            echo "Profile - ";

			// Now get the users car
			// $username = $user['username'];
			$sql = "SELECT * FROM cars WHERE username = '".$user['username']."'";
			$carQ = $reg_conn->query($sql);

			if ($carQ->num_rows > 0) {
		 	  	 // output data of each row
		  	 	 while($car = $carQ->fetch_assoc()) {

					// Car Record
		            $old_carid = $car['id']; // Save this for the history table query
					$cars_fields=array();	
					$cars_fields['id'] = $car['id'];
					$cars_fields['username'] = $car['username'];
					$cars_fields['ctime'] = $car['ctime'];
					$cars_fields['mtime'] = $car['mtime'];
					$cars_fields['ModifiedBy'] = $car['ModifiedBy'];
					$cars_fields['series'] = $car['series'];
					$cars_fields['variant'] = $car['variant'];
					$cars_fields['year'] = $car['year'];
					$cars_fields['type'] = $car['type'];
					$cars_fields['chassis'] = $car['chassis'];
					$cars_fields['color'] = $car['color'];
					$cars_fields['engine'] = $car['engine'];
					$cars_fields['purchasedate'] = $car['purchasedate'];
					$cars_fields['solddate'] = $car['solddate'];
					$cars_fields['comments'] = $car['comments'];
					$cars_fields['image'] = $car['image'];

					$db->insert('cars', $cars_fields);

		            if ($db->error()) {
           			 echo "Car Error".$db->errorString()."<br>";
           			}else
           			{
           				$car_id = $db->lastId();
		           		echo "Car ".$car_id." - ";
		        	}


					// Relationship Record
					// then udate the cross reference table (user_car) with the car_id and user_id,
		            $db->insert(
		                'car_user',
		                array('userid'=>$user_id,'carid'=>$car_id)
		                    );
		            echo "Cross Reference - ";

					// Car History
		            // Get the Car History

					// $history = $reg_conn->query("SELECT * FROM users_cars WHERE id = ?", $old_car );
					$sql = "SELECT * FROM cars_hist WHERE id = '".$old_carid."'";
					$result = $reg_conn->query($sql);

					if ($result->num_rows > 0) {
					    // output data of each row
					    echo "History .";

					    while($row = $result->fetch_assoc()) {
					    	$hist_fields=array();
					    	$hist_fields['operation'] = $row['operation'];
							$hist_fields['id'] = $car_id;  // New Car ID!
							$hist_fields['username'] = $row['username'];
							$hist_fields['model'] = NULL;
							$hist_fields['series'] = $row['series'];
							$hist_fields['variant'] = $row['variant'];
							$hist_fields['year'] = $row['year'];
							$hist_fields['type'] = $row['type'];
							$hist_fields['chassis'] = $row['chassis'];
							$hist_fields['color'] = $row['color'];
							$hist_fields['engine'] = $row['engine'];
							$hist_fields['purchasedate'] = $row['purchasedate'];
							$hist_fields['solddate'] = $row['solddate'];
							$hist_fields['comments'] = $row['comments'];
							$hist_fields['image'] = $row['image'];
							$hist_fields['modifiedby'] = $row['modifiedby'];
							$hist_fields['timestamp'] = $row['timestamp'];

							$db->insert('cars_hist', $hist_fields);

							echo ".";

					    }
		            }
		        }
		        echo "No Car - ";
	   		}	
            echo " Done<br>";
        }
    }

} else {
    echo "0 results";
}


?>

