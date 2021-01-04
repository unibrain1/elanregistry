<?php
require_once '../users/init.php';
require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php';

if (!securePage($_SERVER['PHP_SELF'])) {
  die();
}

// https://datatables.net/download/

$carQ = $db->findAll("cars");
$carData = $carQ->results();
?>

<div id="page-wrapper">
  <div class="container-fluid">
    <div class="well">
      <div class="row">
        <div class="col-12">
          <div class="card card-default">
            <div class="card-header">
              <h2><strong>List Cars</strong></h2>
            </div>
            <div class="card-body">
              <table id="cartable" style="width: 100%" class="table-sm display compact table-bordered table-list-search" aria-describedby="card-header">
                <thead>
                  <tr>
                    <th scope=column></th>
                    <th scope=column>Year</th>
                    <th scope=column>Type</th>
                    <th scope=column>Chassis</th>
                    <th scope=column>Series</th>
                    <th scope=column>Variant</th>
                    <th scope=column>Color</th>
                    <th scope=column>Image</th>
                    <th scope=column>First Name</th>
                    <th scope=column>City</th>
                    <th scope=column>State</th>
                    <th scope=column>Country</th>
                    <th scope=column>Website</th>
                    <th scope=column>Date Added</th>
                  </tr>
                  <tr id="filterrow">
                    <th scope=column>NOSEARCH</th>
                    <th scope=column>Year</th>
                    <th scope=column>Type</th>
                    <th scope=column>Chassis</th>
                    <th scope=column>Series</th>
                    <th scope=column>Variant</th>
                    <th scope=column>Color</th>
                    <th scope=column>NOSEARCH</th>
                    <th scope=column>First Name</th>
                    <th scope=column>City</th>
                    <th scope=column>State</th>
                    <th scope=column>Country</th>
                    <th scope=column>NOSEARCH</th>
                    <th scope=column>Date Added</th>
                  </tr>
                </thead>
                <tbody>
                  <?php
                  //Cycle through users
                  foreach ($carData as $car) {
                  ?>
                    <tr>
                      <td> <a class="btn btn-success btn-sm" href="<?= $us_url_root ?>app/car_details.php?car_id=<?= $car->id ?>">Details</a> </td>
                      <td><?= $car->year ?></td>
                      <td><?= $car->type ?></td>
                      <td><?= $car->chassis ?></td>
                      <td><?= $car->series ?></td>
                      <td><?= $car->variant ?></td>
                      <td><?= $car->color ?></td>
                      <td>
                        <?php
                        include($abs_us_root . $us_url_root . 'app/views/_display_image.php');
                        ?>
                      <td><?= $car->fname ?></td>
                      <td><?= $car->city ?></td>
                      <td><?= $car->state ?></td>
                      <td><?= $car->country ?></td>
                      <?php
                      if (!empty($car->website)) {
                      ?>
                        <td> <a target="_blank" href="<?= $car->website ?>">Website</a></td>
                      <?php
                      } else {
                        echo "<td></td>";
                      } ?>
                      <td>
                        <?= date('Y-m-d', strtotime($car->ctime)); ?>

                        <?php
                        if (strtotime($car->ctime) > strtotime('-30 days')) {
                          echo '<img style="-webkit-user-select:none; display:block; margin:auto;" alt="new" src="' . $us_url_root . 'app/images/new.png">';
                        } ?>
                      </td>
                    </tr>
                  <?php
                  } ?>
                </tbody>
              </table>
            </div> <!-- card-body -->
          </div> <!-- car -->
        </div> <!-- row -->
      </div><!-- row -->
    </div> <!-- well -->
  </div> <!-- /.container -->
</div><!-- .page-wrapper -->
<!-- End of main content section -->


<!-- Table Sorting and Such -->
<?php require_once $abs_us_root . $us_url_root . 'usersc/templates/' . $settings->template . '/datatables.php'; ?>

<script>
  $(document).ready(function() {
    // Filter each column - http://jsfiddle.net/9bc6upok/ 

    // Setup - add a text input to each footer cell
    $(' #cartable thead tr#filterrow th').each(function(i) {
      var title = $('#cartable thead tr#filterrow th').eq($(this).index()).text();
      if (title != "NOSEARCH") {
        $(this).html('<input type="text" size="5" placeholder=" ' + title + '" data-index="' + i + '" />');
      } else {
        $(this).html('');
      }
    });

    // DataTable
    var table = $('#cartable').DataTable({
      fixedHeader: true,
      pageLength: 25,
      scrollX: true,
      "aLengthMenu": [
        [25, 50, 100, -1],
        [25, 50, 100, "All"]
      ],
      "aaSorting": [
        [1, "asc"],
        [2, "asc"],
        [3, "asc"]
      ],
      "columnDefs": [{
        "targets": 0,
        "orderable": false
      }]
    });
    // Filter event handler
    $(table.table().container()).on('keyup', 'thead input', function() {
      table
        .column($(this).data('index'))
        .search(this.value)
        .draw();
    });


  });
</script>

<!-- footers -->
<?php
require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php'; //custom template footer
?>