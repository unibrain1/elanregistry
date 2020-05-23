<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);


require_once '../users/init.php';
require_once $abs_us_root.$us_url_root.'users/includes/template/prep.php';


if (!securePage($_SERVER['PHP_SELF'])) {
    die();
}

// Some useful queries

$badusers="SELECT users.id FROM users  LEFT JOIN car_user ON (users.id = car_user.userid) WHERE ( users.email_verified = 0 AND users.last_login = 0 AND car_user.carid IS NULL AND vericode_expiry < CURRENT_DATE ) GROUP BY users.id ";
$unusedprofiles=" SELECT t1.user_id FROM profiles t1 LEFT JOIN users t2 ON t1.user_id = t2.id WHERE t2.id IS NULL ";
$orphanedcars="SELECT t1.userid FROM car_user t1 LEFT JOIN users t2 ON t1.userid = t2.id  WHERE t2.id IS NULL ";

$errors                     = [];
$successes                  = [];

//Form is posted now process it
if (!empty($_POST)) {
    $token = $_POST['csrf'];
    if (!Token::check($token)) {
        include($abs_us_root . $us_url_root . 'usersc/scripts/token_error.php');
    } else {
        // Do something!
        if (!empty($_POST)) {
            if (!empty($_POST['command'])) {
                switch ($_POST['command']) {
                    case "reassign":
                        
                        $user_id = $_POST['user_id'];
                        $car_id  = $_POST['car_id'];

                        // Get the new user details
                        $userQ                    = $db->findById($user_id, "usersview");
                        $userData                 = $userQ->results();

                        $fields['user_id']   = $userData[0]->id;
                        $fields['email']     = $userData[0]->email;
                        $fields['username']  = $userData[0]->username;
                        $fields['fname']     = $userData[0]->fname;
                        $fields['lname']     = $userData[0]->lname;
                        $fields['join_date'] = $userData[0]->join_date;
                        $fields['city']      = $userData[0]->city;
                        $fields['state']     = $userData[0]->state;
                        $fields['country']   = $userData[0]->country;
                        $fields['lat']       = $userData[0]->lat;
                        $fields['lon']       = $userData[0]->lon;
                        $fields['website']   = $userData[0]->website;

                        // Update the car details with the new owner
                        $db->update('cars', $car_id, $fields);

                        // Update the cross reference table
                        $db->query("UPDATE car_user SET userid = ? WHERE carid = ?", [$user_id, $car_id]);

                        // Add a record to the history with some information on the assignment
                        $fields['comments'] = "Car was reassigned to new user $user_id.";
                        $fields['car_id'] = $car_id;
                        $fields['operation'] = "REASSIGN";
                        $db->insert("cars_hist", $fields);

                        $successes[] = 'Admin '.($user->data()->id).' Assigned CAR '.$car_id.' to USER '.$user_id;
                        logger($user->data()->id, "User", "Assigned CAR $car_id to USER $user_id.");

                        break;

                    case "merge":
                        $new_car_id = $_POST['new_car_id'];
                        $old_car_id  = $_POST['old_car_id'];

                        // Merge the history
                        $db->query("UPDATE cars_hist SET car_id = ? WHERE car_id = ?", [$new_car_id, $old_car_id]);
                        if ($db->error()) {
                            $errors[] = $db->errorString();
                            logger($user->data()->id, "User", "FAILED: Merged CAR $old_car_id to CAR $new_car_id.");
                        } else {
                            // Unassign from the previous owner
                            $db->query("DELETE FROM car_user WHERE carid = ?", [$old_car_id]);

                            // Remove old car
                            $db->query("DELETE FROM cars WHERE id = ?", [$old_car_id]);

                            // Add a record to the history with some information on the assignment
                            $fields['comments'] = "Car $old_car_id was merged with $new_car_id.  Car $old_car_id has been deleted.  This was most likely because of a duplicate entry.";
                            $fields['car_id'] = $new_car_id;
                            $fields['operation'] = "MERGE";
                            $db->insert("cars_hist", $fields);

                            $successes[] = 'Admin '.($user->data()->id).' Merged CAR '.$old_car_id.' to CAR '.$new_car_id;
                            logger($user->data()->id, "User", "Merged CAR $old_car_id to CAR $new_car_id.");
                        }
                        break;
                    case "cake":
                        echo "i is cake";
                        break;
                }
            }
        }
    }
}

?>
<div id="page-wrapper">
	<div class="container">
<br>
	<div class="row">
		<div class="col-4" align="center">
		<div class="card card-default">
		<div class="card-header"><h2><strong>DB Cleanup</strong></h2></div>
			<div class="card-body">
			<button type="button" class="btn btn-primary btn-lg btn-block" data-toggle="modal" data-target="#badUser">Remove <?= $db->query($badusers)->count() ?> Bad Users</button>
			<button type="button" class="btn btn-primary btn-lg btn-block" data-toggle="modal" data-target="#cleanProfile">Clean <?= $db->query($unusedprofiles)->count() ?> Unused Profiles</button>
			<button type="button" class="btn btn-primary btn-lg btn-block" data-toggle="modal" data-target="#orphanCars">Assign  <?= $db->query($orphanedcars)->count() ?> Orphan Cars</button>
			</div> <!-- card-body -->
		</div> <!-- card -->
		</div> <!-- col -->


		<div class="col-4" align="center">
		<div class="card card-default">
		<div class="card-header"><h2><strong>Reassign Car</strong></h2></div>
			<div class="card-body">
			<form name="assignCar" action="manage_cars.php" method="POST" enctype="multipart/form-data">
				<label for="car_id">Car ID:</label><br>
				<input type="text" id="car_id" name="car_id"><br>
				<label for="user_id">User ID:</label><br>
				<input type="text" id="user_id" name="user_id">
				<br><br>
				<input type="hidden" name="csrf" value="<?= Token::generate(); ?>" />
				<input type="hidden" name="command" value="reassign" />
				<input class="btn btn-primary btn-lg btn-block" type='submit' value='Assign' class='submit' />
			</form>
			</div> <!-- card-body -->
		</div> <!-- card -->
		</div> <!-- col -->


		<div class="col-4" align="center">
		<div class="card card-default">
		<div class="card-header"><h2><strong>Merge Car</strong></h2></div>
			<div class="card-body">
			<form name="mergeCar" action="manage_cars.php" method="POST" enctype="multipart/form-data">
				<label for="car_id">Older Car ID:</label><br>
				<input type="text" id="old_car_id" name="old_car_id"><br>
				<label for="user_id">New Car ID:</label><br>
				<input type="text" id="new_car_id" name="new_car_id">
				<br><br>
				<input type="hidden" name="csrf" value="<?= Token::generate(); ?>" />
				<input type="hidden" name="command" value="merge" />
				<input class="btn btn-primary btn-lg btn-block" type='submit' value='Merge' class='submit' />
			</form>
			</div> <!-- card-body -->
		</div> <!-- card -->
		</div> <!-- col -->


		<div class="col" align="center">

		<div class="card card-default">
		<div class="card-header"><h2><strong>Messages</strong></h2></div>
			<div class="card-body">
                            <?php if (!$errors=='') {
    ?><div class="alert alert-danger"><?=display_errors($errors); ?></div><?php
} ?>
                            <?php if (!$successes=='') {
        ?><div class="alert alert-success"><?=display_successes($successes); ?></div><?php
    } ?>
			</div> <!-- card-body -->
		</div> <!-- card -->  
	</div>

	<!-- The Modal for Bad Users-->
	<div class="modal fade" id="badUser">
	<div class="modal-dialog">
		<div class="modal-content">
		<!-- Modal Header -->
		<div class="modal-header">
			<h4 class="modal-title">Remove Bad Users</h4>
			<button type="button" class="close" data-dismiss="modal">&times;</button>
		</div>

		<!-- Modal body -->
		<div class="modal-body">
			<?php
            $usersQ = $db->query($badusers);
            echo "Delete ". $usersQ->count() ." SPAM users</br>";
            $users = $usersQ->results();
            foreach ($users as $u) {
                echo "- user_id ". $u->id ."</br>" ;
                deleteUsers(array($u->id));
                $db->query("DELETE FROM profiles WHERE user_id = ?", array($u->id));
            }
            ?>              
		</div>

		<!-- Modal footer -->
		<div class="modal-footer">
			<button type="button" class="btn btn-danger" data-dismiss="modal">Close</button>
		</div>
		</div>
	</div>
	</div>

	<!-- The Modal for Clean Profiles-->
	<div class="modal fade" id="cleanProfile">
	<div class="modal-dialog">
		<div class="modal-content">
		<!-- Modal Header -->
		<div class="modal-header">
			<h4 class="modal-title">Clean Unused Profiles</h4>
			<button type="button" class="close" data-dismiss="modal">&times;</button>
		</div>

		<!-- Modal body -->
		<div class="modal-body">
			<?php
            $profileQ = $db->query($unusedprofiles);
            echo "Delete ". $profileQ->count() ." profiles</br>";
            $profile = $profileQ->results();
            foreach ($profile as $p) {
                echo "- user_id ". $p->user_id ."</br>" ;
                $db->query("DELETE FROM profiles WHERE user_id = ?", array($p->user_id));
            }
            ?>              
		</div>  

		<!-- Modal footer -->
		<div class="modal-footer">
			<button type="button" class="btn btn-danger" data-dismiss="modal">Close</button>
		</div>
		</div>
	</div>
	</div>

	<!-- The Modal for Orphan Cars-->
	<div class="modal fade" id="orphanCars" role="dialog">
	<div class="modal-dialog">
		<div class="modal-content">

		<!-- Modal Header -->
		<div class="modal-header">
			<h4 class="modal-title">Assign Orphan Cars</h4>
			<button type="button" class="close" data-dismiss="modal">&times;</button>
		</div>

		<!-- Modal body -->
		<div class="modal-body">
			<?php
            $qResult = $db->query($orphanedcars);
            echo "There are ". $qResult->count() ." car_user rows without corresponding owner</br>";

            $profile = $qResult->results();
            foreach ($profile as $p) {
                echo "- userid ". $p->userid ."</br>" ;
                $db->query("DELETE FROM car_user WHERE userid = ?", array($p->userid));
            }

            $q="
			SELECT t1.id
			FROM cars t1
			LEFT JOIN car_user t2 ON t1.id = t2.carid
			WHERE t2.carid IS NULL";

            $qResult = $db->query($q);

            echo "There are ". $qResult->count() ." cars  without corresponding car_owner entry</br>";

            $car = $qResult->results();
            foreach ($car as $c) {
                echo "- carid ". $c->id ."</br>" ;
                $db->query("INSERT INTO car_user (userid, carid ) VALUES (83, ?)", array($c->id));
            }
            ?>
		</div>

		<!-- Modal footer -->
		<div class="modal-footer">
			<button type="button" class="btn btn-danger" data-dismiss="modal">Close</button>
		</div>
		</div>
	</div>
</div>

<!-- footers -->
<?php require_once $abs_us_root . $us_url_root . 'usersc/templates/' . $settings->template . '/footer.php'; //custom template footer?>
