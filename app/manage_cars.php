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
$duplicates="SELECT  a.* FROM cars a JOIN(  SELECT  type,chassis,  COUNT(*)  FROM  users_carsview  WHERE chassis <> '' GROUP BY type,chassis HAVING COUNT(*) > 1) b ON a.chassis = b.chassis ORDER BY a.chassis, a.type";

// Get list of suspected duplicates
$duplicatesQ = $db->query($duplicates);
$duplicateCars = $duplicatesQ->results();



$errors                     = [];
$successes                  = [];

//Form is posted now process it
if (!empty($_POST)) {
    $token = $_POST['csrf'];
    if (!Token::check($token)) {
        include($abs_us_root . $us_url_root . 'usersc/scripts/token_error.php');
    } else {
        // Do something!
        $_POST  = filter_input_array(INPUT_POST, FILTER_SANITIZE_STRING);

        if (!empty($_POST)) {
            if (!empty($_POST['command'])) {
                switch ($_POST['command']) {

                    // Assign car t a new owner
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
                        $fields['operation'] = "NEWOWNER";

                        $fields['car_id'] = $car_id;
                        $db->insert("cars_hist", $fields);

                        $successes[] = 'Admin '.($user->data()->id).' '.$fields['comments'];
                        logger($user->data()->id, "ElanRegistry", $fields['comments']);

                        break;

                    // Merge two cars because a car is a) a duplicate or b)the car was sold to a new owner and the new owner created a record.
                    case "merge":
                            // Validate input
                            if (!isset($_POST['cars'])  or !isset($_POST['reason'])) {
                                $errors[] = 'Select 2 cars to merge and a reason';
                                break;
                            }
                            $cars    = $_POST['cars'];
                            $reason  = $_POST['reason'];

                            if (count($cars) <> 2) {
                                $errors[] = 'Select 2 cars to merge';
                                break;
                            } else {
                                // Determine the newest car
                                if ($cars[0] > $cars[1]) {
                                    $new_car_id = $cars[0];
                                    $old_car_id = $cars[1];
                                } else {
                                    $new_car_id = $cars[1];
                                    $old_car_id = $cars[0];
                                }
                            }
                            if (count($reason) <> 1) {
                                $errors[] = 'Select 1 reason code';
                                break;
                            } else {
                                // Build the reason string
                                switch ($reason[0]) {
                                    case "duplicate":
                                        $fields['comments'] = "Car $old_car_id a duplicate of $new_car_id.  The history of $old_car_id has been merged with $new_car_id and $old_car_id deleted.";
                                        $fields['operation'] = "DUPLICATE";
                                    break;

                                    case "newowner":
                                        $fields['comments'] = "Car $old_car_id was sold to a new owner and the new owner created a record for the same car as $new_car_id. The history of $old_car_id has been merged with $new_car_id and $old_car_id deleted.";
                                        $fields['operation'] = "NEWOWNER";
                                    break;

                                    default:

                                    // This should never happen (Yeah right)
                                        $fields['comments'] = "Car $old_car_id was merged with $new_car_id.  Car $old_car_id has been deleted.";
                                        $fields['operation'] = "DEFAULT";
                                    break;
                                }
                            }

                            // Merge the history
                            $db->query("UPDATE cars_hist SET car_id = ? WHERE car_id = ?", [$new_car_id, $old_car_id]);
                            if ($db->error()) {
                                $errors[] = $db->errorString();
                                logger($user->data()->id, "ElanRegistry", "FAILED: Merged CAR $old_car_id to CAR $new_car_id.");
                            } else {
                                // Unassign from the previous owner
                                $db->query("DELETE FROM car_user WHERE carid = ?", [$old_car_id]);

                                // Remove old car
                                $db->query("DELETE FROM cars WHERE id = ?", [$old_car_id]);

                                // Add a record to the history with some information on the assignment
                                $fields['car_id'] = $new_car_id;
<<<<<<< HEAD
=======
                                print_r($fields);
>>>>>>> 7db57e9da077c13feb80a49d479382f73b9afc1a

                                $db->insert("cars_hist", $fields);

                                $successes[] = 'Admin '.($user->data()->id).' '.$fields['comments'];
                                logger($user->data()->id, "ElanRegistry", $fields['comments']);
                            }
                        // Now update suspected duplicates
                        $duplicatesQ = $db->query($duplicates);
                        $duplicateCars = $duplicatesQ->results();

                        break;

                    // This will never happen (Yeah right)
                    case "cake":
                        echo "The cake is a lie";
                        break;
                }
            }
        }
    }
}

?>
<div id="page-wrapper">
	<div class="container-fluid">
		<div class="well">
		<div class="row">

            <div class="col-4" align="center">
				<div class="card card-default">
				<div class="card-header"><h2><strong>Messages</strong></h2></div>
					<div class="card-body">
						<?php if (!$errors=='') {
    ?>
						<div class="alert alert-danger"><?=display_errors($errors); ?></div><?php
} ?>
						<?php if (!$successes=='') {
        ?>
						<div class="alert alert-success"><?=display_successes($successes); ?></div><?php
    } ?>
					</div> <!-- card-body -->
				</div> <!-- card -->  
			</div> <!-- col -->


			<div class="col-4" align="center">
				<div class="card card-default">
				<div class="card-header"><h2><strong>DB Cleanup</strong></h2></div>
					<div class="card-body">
					<button type="button" class="btn btn-success btn-lg btn-block" data-toggle="modal" data-target="#badUser">Remove <?= $db->query($badusers)->count() ?> Bad Users</button>
					<button type="button" class="btn btn-success btn-lg btn-block" data-toggle="modal" data-target="#cleanProfile">Clean <?= $db->query($unusedprofiles)->count() ?> Unused Profiles</button>
					<button type="button" class="btn btn-success btn-lg btn-block" data-toggle="modal" data-target="#orphanCars">Assign  <?= $db->query($orphanedcars)->count() ?> Orphan Cars</button>
					</div> <!-- card-body -->
				</div> <!-- card -->
			</div> <!-- col -->

			<div class="col-4" align="center">
				<div class="card card-default">
				<div class="card-header"><h2><strong>Reassign Car</strong></h2></div>
					<div class="card-body">
					<form name="assignCar" action="manage_car.php" method="POST" enctype="multipart/form-data">
						<label for="car_id">Car ID:</label><br>
						<input type="text" id="car_id" name="car_id"><br>
						<label for="user_id">User ID:</label><br>
						<input type="text" id="user_id" name="user_id">
						<br><br>
						<input type="hidden" name="csrf" value="<?= Token::generate(); ?>" />
						<input type="hidden" name="command" value="reassign" />
						<input class="btn btn-success btn-lg btn-block" type='submit' value='Assign' class='submit' />
					</form>
					</div> <!-- card-body -->
				</div> <!-- card -->
			</div> <!-- col -->

		</div> <!-- row -->
		<div class="row">
			<div class="col">
				<div class="card card-default">
				<div class="card-header"><h2><strong>Duplicates</strong></h2></div>
					<div class="card-body">
                        <form action="manage_cars.php" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="command" value="merge" />
                            <input type="hidden" name="csrf" value="<?= Token::generate(); ?>" />
                            <input type="submit" class="btn btn-success "  name="formSubmit" value="Merge" />
                            <div class="form-group">
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" id="customCheck-duplicate" name="reason[]" value="duplicate" />
                                    <label class="custom-control-label" for="customCheck-duplicate">Duplicate car</label>
                                </div>
                            </div>
                            <div class="form-group">
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" id="customCheck-newowner" name="reason[]" value="newowner" />
                                    <label class="custom-control-label" for="customCheck-newowner">New car is new owner</label>
                                </div>
                            </div>

                            <table id="duptable" class="table table-striped table-bordered table-sm" cellspacing="0" width="100%">
                                <thead>
                                <tr>
                                    <th>Merge</th>
                                    <th>CarID</th>
                                    <th>Username</th>
                                    <th>Create</th>
                                    <th>Modified</th>
                                    <th>Year</th>
                                    <th>Type</th>
                                    <th>Chassis</th>
                                    <th>Series</th>
                                    <th>Variant</th>
                                    <th>Color</th>
                                    <th>Engine</th>
                                    <th>Purchase Date</th>
                                    <th>Sold Date</th>
                                    <th>Comments</th>
                                    <th>Image</th>
                                    <th>Fname</th>
                                    <th>Lname</th>
                                    <th>email</th>
                                    <th>City</th>
                                    <th>State</th>
                                    <th>Country</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php
                                //Cycle through users
                                foreach ($duplicateCars as $v1) {
                                    ?>
                                    <tr>
                                    <td class="center">
                                        <div class="form-group">
                                            <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" id="customCheck-<?=$v1->id?>" name="cars[]" value="<?=$v1->id?>" />
                                            <label class="custom-control-label" for="customCheck-<?=$v1->id?>"></label>
                                            </div>
                                        </div>
                                    </td>
                                    <td><a class="btn btn-success btn-sm" target="_blank" href='<?=$us_url_root?>app/car_details.php?car_id=<?=$v1->id?>'><?=$v1->id?></a></td>
                                    <td><?=$v1->username?></td>
                                    <td><?=$v1->ctime?></td> 
                                    <td><?=$v1->mtime?></td> 
                                    <td><?=$v1->year?></td>
                                    <td><?=$v1->type?></td>
                                    <td><?=$v1->chassis?></td>
                                    <td><?=$v1->series?></td>
                                    <td><?=$v1->variant?></td>
                                    <td><?=$v1->color?></td>
                                    <td><?=$v1->engine?></td>                        
                                    <td><?=$v1->purchasedate?></td>
                                    <td><?=$v1->solddate?></td>
                                    <td><?=$v1->comments?></td>
                                    <td> <?php
                                    if ($v1->image and file_exists($abs_us_root.$us_url_root."app/userimages/".$v1->image)) {
                                        echo '<img src='.$us_url_root.'app/userimages/thumbs/'.$v1->image.">";
                                    } ?>  </td>
                                    <td><?=$v1->fname?></td>
                                    <td><?=$v1->lname?></td>
                                    <td><?=$v1->email?></td>
                                    <td><?=$v1->city?></td>
                                    <td><?=$v1->state?></td>
                                    <td><?=$v1->country?></td> 
                                    </tr>
                                <?php
                                } ?>
                                </tbody>
                            </table>
                        </form>
					</div> <!-- card-body -->
				</div> <!-- card -->  
			</div> <!-- col -->
		</div> <!-- row -->
		</div> <!-- well -->
	</div><!-- Container -->
</div><!-- page -->


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


<!-- Javascript -->

<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.18/css/jquery.dataTables.min.css"/>
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/fixedheader/3.1.2/css/fixedHeader.dataTables.min.css">
<script type="text/javascript" src="https://cdn.datatables.net/1.10.18/js/jquery.dataTables.min.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/rowgroup/1.1.2/js/dataTables.rowGroup.min.js"></script>
<script src="https://cdn.datatables.net/fixedheader/3.1.2/js/dataTables.fixedHeader.min.js" type="text/javascript"></script>


<script type="text/javascript">
$(document).ready(function()  {
$('#duptable').DataTable(
	{
    fixedHeader: true,
	rowGroup: {
            dataSrc: [6,7],    
            startClassName: 'table-info',
    },
    "ordering": false,
    "scrollX": true,
    "pageLength": 50
	});
} );
</script>

<!-- footers -->
<?php require_once $abs_us_root . $us_url_root . 'usersc/templates/' . $settings->template . '/footer.php'; //custom template footer?>
