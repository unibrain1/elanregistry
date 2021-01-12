<?php
require_once '../users/init.php';
require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php';
require_once 'validate.php'; // TBD do I need this?


if (!securePage($_SERVER['PHP_SELF'])) {
  die();
}

// Get some interesting user information to display later
if (!empty($_GET)) {


  $id = $_GET['car_id'];
  $id = Input::sanitize($id);

  // Get the car information
  $car = $db->findById($id, 'cars')->results()[0];

  // Search in the elan_factory_info for details on the car.
  // The car.chassis can either match exactly (car.chassis = elan_factory_info.serial )
  //    or
  // The right most 5 digits of the car.chassis (post 1970 and some 1969) will =  elan_factory_info.serial

  $search = array($car->chassis, substr($car->chassis, -5));

  $carFactory = FALSE;
  foreach ($search as $s) {
    $carQ = $db->query('SELECT * FROM elan_factory_info WHERE serial = ? ', [$s]);
    // Did it return anything?
    if ($carQ->count() !== 0) {
      // Yes it did
      $carFactory = $carQ->results();

      if ($carFactory[0]->suffix != "") {
        $carFactory[0]->suffix = $carFactory[0]->suffix . " (" . suffixtotext($carFactory[0]->suffix) . ")";
      }
      break;
    }
  }

  $raw = date_parse($car->join_date);
  $signupdate = $raw['year'] . "-" . $raw['month'] . "-" . $raw['day'];
} else {
  // Shouldn't be here unless someone is mangling the url
  Redirect::to($us_url_root . "/app/list_cars.php");
}
?>

<!-- Now that that is all out of the way, let's display everything -->
<div id="page-wrapper">
  <div class="well">
    <br>
    <div class="row">
      <div class="col-sm-6">
        <!-- Car Info -->
        <div class="card card-default">
          <div class="card-header">
            <div class="form-group row">
              <div class='col-md-7'>
                <h2><strong>Car Information</strong></h2>
              </div>
              <div class='col-md-5 text-right'>
                <?php if (isset($user) && $user->isLoggedIn() && $user->data()->id === $car->user_id) {
                ?>
                  <form method='POST' action=<?= $us_url_root . 'app/edit_car.php' ?>>
                    <input type="hidden" name="csrf" value="<?= Token::generate(); ?>" />
                    <input type="hidden" name="action" value="update_car" />
                    <input type="hidden" name="carid" id='carid' value="<?= $car->id ?>" />
                    <button class='btn btn-block btn-success' type="submit">Update</button>
                  </form>
                <?php
                } else {
                  echo "<input type='hidden' name='carid' id='carid' value='" . $car->id . "'/>";
                }
                ?>
              </div>
            </div>
          </div>
          <div class="card-body">
            <table id="cartable" class="table table-striped table-bordered table-sm" aria-describedby="card-header">
              <tr class="table-success">
                <th scope=column><strong>Car ID:</strong></th>
                <th scope=column><?= $car->id ?></th>
              </tr>
              <tr>
                <td><strong>Series:</strong></td>
                <td><?= $car->series ?></td>
              </tr>
              <tr>
                <td><strong>Variant:</strong></td>
                <td><?= $car->variant ?></td>
              </tr>
              <tr>
                <td><strong>Model:</strong></td>
                <td><?= $car->model ?></td>
              </tr>
              <tr>
                <td><strong>Year:</strong></td>
                <td><?= $car->year ?></td>
              </tr>
              <tr>
                <td><strong>Type:</strong></td>
                <td><?= $car->type ?></td>
              </tr>
              <tr>
                <td><strong>Chassis :</strong></td>
                <td><?= $car->chassis ?></td>
              </tr>
              <tr>
                <td><strong>Color:</strong></td>
                <td><?= $car->color ?></td>
              </tr>
              <tr>
                <td><strong>Engine :</strong></td>
                <td><?= $car->engine ?></td>
              </tr>
              <tr>
                <td><strong>Purchase Date:</strong></td>
                <td><?= $car->purchasedate ?></td>
              </tr>
              <tr>
                <td><strong>Sold Date :</strong></td>
                <td><?= $car->solddate ?></td>
              </tr>
              <tr>
                <td><strong>Comments:</strong></td>
                <td><?= $car->comments ?></td>
              </tr>
              <tr class="table-success">
                <td><strong>Owner ID:</strong></td>
                <td><?= $car->user_id ?></td>
              </tr>
              <tr>
                <td><strong>First name:</strong></td>
                <td><?= ucfirst($car->fname) ?></td>
              </tr>
              <tr>
                <td><strong>City</strong></td>
                <td><?= html_entity_decode($car->city); ?></td>
              </tr>
              <tr>
                <td><strong>State:</strong></td>
                <td><?= html_entity_decode($car->state); ?></td>
              </tr>
              <tr>
                <td><strong>Country:</strong></td>
                <td><?= html_entity_decode($car->country); ?></td>
              </tr>
              <tr>
                <td><strong>Member Since:</strong></td>
                <td><?= $signupdate ?></td>
              </tr>
              <tr>
                <td><strong>Record Created:</strong></td>
                <td><?= $car->ctime ?></td>
              </tr>
              <tr>
                <td><strong>Record Modified:</strong></td>
                <td><?= $car->mtime ?></td>
              </tr>
              <?php
              if (!empty($car->website)) {
              ?>
                <tr>
                  <td><strong>Website:</strong></td>
                  <td> <a target="_blank" href="<?= $car->website ?>">Website</a></td>
                </tr>
              <?php
              }
              ?>
              <?php
              if ($carFactory !== FALSE) { ?>

                <tr class="table-info">
                  <td colspan=2><strong>Factory Data - <small>I' ve lost track of where this data originated and it may be incomplete, inaccurate, false, or just plain made up.</small> </strong> </td>
                </tr>
                <tr>
                  <td><strong>Year:</strong></td>
                  <td><?= $carFactory[0]->year ?></td>
                </tr>
                <tr>
                  <td><strong>Month:</strong></td>
                  <td><?= $carFactory[0]->month ?></td>
                </tr>
                <tr>
                  <td><strong>Production Batch:</strong></td>
                  <td><?= $carFactory[0]->batch ?></td>
                </tr>
                <tr>
                  <td><strong>Type:</strong></td>
                  <td><?= $carFactory[0]->type ?></td>
                </tr>
                <tr>
                  <td><strong>Chassis:</strong></td>
                  <td><?= $carFactory[0]->serial ?></td>
                </tr>
                <tr>
                  <td><strong>Suffix:</strong></td>
                  <td><?= $carFactory[0]->suffix ?></td>
                </tr>
                <tr>
                  <td><strong>Engine:</strong></td>
                  <td><?= $carFactory[0]->engineletter ?><?= $carFactory[0]->enginenumber ?></td>
                </tr>
                <tr>
                  <td><strong>Gearbox:</strong></td>
                  <td><?= $carFactory[0]->gearbox ?></td>
                </tr>
                <tr>
                  <td><strong>Color:</strong></td>
                  <td><?= $carFactory[0]->color ?></td>
                </tr>
                <tr>
                  <td><strong>Build Date:</strong></td>
                  <td><?= $carFactory[0]->builddate ?></td>
                </tr>
                <tr>
                  <td><strong>Notes:</strong></td>
                  <td><?= $carFactory[0]->note ?></td>
                </tr>
              <?php } ?>

            </table>
          </div>
        </div>
      </div> <!-- col-xs-12 col-md-6 -->
      <div class="col-sm-6">
        <!-- Image -->
        <div class="card card-default">
          <div class="card-header">
            <h2><strong>The Car</strong></h2>
          </div>
          <div class="card-body">
            <?php include($abs_us_root . $us_url_root . 'app/views/_display_image.php'); ?>
          </div> <!-- card-body -->
        </div> <!-- card -->
      </div> <!-- col-xs-12 col-md-6 -->
    </div> <!-- row -->
    <br>
    <div class="row">
      <div class='col-sm-12'>

        <?php include($abs_us_root . $us_url_root . 'app/views/_car_history.php'); ?>

      </div> <!-- col -->
    </div> <!-- row -->
  </div> <!-- well -->
</div>

<!-- footers -->
<?php
require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php'; //custom template footer
?>