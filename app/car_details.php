<?php
require_once '../users/init.php';
require_once $abs_us_root.$us_url_root.'users/includes/template/prep.php';


if (!securePage($_SERVER['PHP_SELF'])) {
    die();
}

// Get some interesting user information to display later

$user_id = $user->data()->id;

if (!empty($_GET)) {
    $id = $_GET['car_id'];
    $car_id = Input::sanitize($id);

    $carQ = $db->findById($car_id, "users_carsview");
    $carData = $carQ->results();

    $carQ = $db->query('SELECT * FROM cars_hist WHERE car_id=? ORDER BY timestamp DESC, operation ASC', [$car_id]);
    $carHist = $carQ->results();

    $raw = date_parse($carData[0]->join_date);
    $signupdate = $raw['year']."-".$raw['month']."-".$raw['day'];
} else {
    // Shouldn't be here unless someone is mangling the url
    Redirect::to($us_url_root."/app/list_cars.php");
}
?>
<!-- Now that that is all out of the way, let's display everything -->

<div id="page-wrapper">
  <div class="container-fluid">
    <div class="well">
    <br>

    <div class="row">
      <div class="col-sm-6"> <!-- Image -->
        <div class="card card-default">
        <div class="card-header"><h2><strong>The Car</strong></h2></div>
          <div class="card-body">
            <?php
                    if ($carData[0]->image and file_exists($abs_us_root.$us_url_root."app/userimages/".$carData[0]->image)) {
                        ?>
                      <img class="card-img-top" src=<?=$us_url_root?>app/userimages/<?=$carData[0]->image?> >
                    <?php
                    } ?>
          </div> <!-- card-body -->
        </div> <!-- card -->
      </div> <!-- col-xs-12 col-md-6 -->
      <div class="col-sm-6"> <!-- Car Info -->
        <div class="card card-default">
          <div class="card-header"><h2><strong>Car Information</strong></h2></div>
          <div class="card-body">
            <table id="cartable" class="table table-striped table-bordered table-sm" cellspacing="0" width="100%">	
            <tr ><td ><strong>Car ID:</strong></td><td ><?=$carData[0]->id?></td></tr>
            <tr ><td ><strong>Series:</strong></td><td ><?=$carData[0]->series?></td></tr>
            <tr ><td ><strong>Variant:</strong></td><td ><?=$carData[0]->variant?></td></tr>
            <tr ><td ><strong>Model:</strong></td><td ><?=$carData[0]->model?></td></tr>
            <tr ><td ><strong>Year:</strong></td><td ><?=$carData[0]->year?></td></tr>
            <tr ><td ><strong>Type:</strong></td><td ><?=$carData[0]->type?></td></tr>
            <tr ><td ><strong>Chassis :</strong></td><td ><?=$carData[0]->chassis?></td></tr>
            <tr ><td ><strong>Color:</strong></td><td ><?=$carData[0]->color?></td></tr>
            <tr ><td ><strong>Engine :</strong></td><td ><?=$carData[0]->engine?></td></tr>
            <tr ><td ><strong>Purchase Date:</strong></td><td ><?=$carData[0]->purchasedate?></td></tr>
            <tr ><td ><strong>Sold Date :</strong></td><td ><?=$carData[0]->solddate?></td></tr>
            <tr ><td ><strong>Comments:</strong></td><td ><?=$carData[0]->comments?></td></tr>
            <tr ><td ><strong>Owner ID:</strong></td><td ><?=$carData[0]->user_id?></td></tr>
            <tr ><td ><strong>First name:</strong></td><td ><?=ucfirst($carData[0]->fname)?></td></tr>
            <tr ><td ><strong>City</strong></td><td ><?=html_entity_decode($carData[0]->city);?></td></tr>
            <tr ><td ><strong>State:</strong></td><td ><?=html_entity_decode($carData[0]->state);?></td></tr>
            <tr ><td ><strong>Country:</strong></td><td ><?=html_entity_decode($carData[0]->country);?></td></tr>
            <tr ><td ><strong>Member Since:</strong></td><td ><?=$signupdate?></td></tr>
            <tr ><td ><strong>Record Created:</strong></td><td ><?=$carData[0]->ctime?></td></tr>
            <tr ><td ><strong>Record Modified:</strong><t/d><td ><?=$carData[0]->mtime?></td></tr>
            <?php
            if (!empty($carData[0]->website)) {
                ?>
                <tr ><td ><strong>Website:</strong></td><td> <a target="_blank" href="<?=$carData[0]->website?>">Website</a></td></tr>
            <?php
            }
            ?>
            </table>
          </div>
        </div>
      </div> <!-- col-xs-12 col-md-6 -->

    </div> <!-- row -->
    <br>
    <div class="card border-success">
      <div class="card-header"><h2><strong>Record Update History</strong></h2></div>
          <div class="card-body">
            <?php include($abs_us_root.$us_url_root.'app/views/_car_history.php'); ?>
          </div> <!-- card-body -->
        </div> <!-- card -->
    </div> <!-- well -->
  </div> <!-- container -->
</div> <!-- #page-wrapper -->

<!-- footers -->
<?php require_once $abs_us_root.$us_url_root.'users/includes/page_footer.php'; // the final html footer copyright row + the external js calls?>

<!-- Place any per-page javascript here -->
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.18/css/jquery.dataTables.min.css"/>
<script type="text/javascript" src="https://cdn.datatables.net/1.10.18/js/jquery.dataTables.min.js"></script>

<script type="text/javascript">
$(document).ready(function()  {
  var table =  $('#cartable').DataTable();
} );
</script>

<script type="text/javascript">
$(document).ready(function()  {
  var table =  $('#historytable').DataTable(
    {
      "ordering": false,
      "scrollX": true
    });
} );
</script>

<!-- footers -->
<?php require_once $abs_us_root . $us_url_root . 'usersc/templates/' . $settings->template . '/footer.php'; //custom template footer?>
