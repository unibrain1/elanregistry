<?php
require_once '../users/init.php';
require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php';

if (!securePage($_SERVER['PHP_SELF'])) {
  die();
}
$carQ = $db->findAll("elan_factory_info");
$carData = $carQ->results();
?>


<div id="page-wrapper">
  <div class="container-fluid">
    <br>
    <div class="jumbotron text-white bg-primary">
      <H2>WARNING</h2>
      <p>I've lost track of where this data originated and it may be incomplete, inaccurate, false, or just plain made up.</p>
    </div>
    <div class="col-12">
      <div class="card card-default">
        <div class="card-body">
          <table id="cartable" style="width: 100%" class="table-sm display compact table-bordered table-list-search" aria-describedby="card-header">
            <thead>
              <tr>
                <th scope=column>Record #</th>
                <th scope=column>Year</th>
                <th scope=column>Month</th>
                <th scope=column>Batch</th>
                <th scope=column>Type</th>
                <th scope=column>Serial</th>
                <th scope=column>Suffix</th>
                <th scope=column>Chassis/Unit</th>
                <th scope=column>Engine Letter</th>
                <th scope=column>Engine Number</th>
                <th scope=column>Gearbox</th>
                <th scope=column>Color</th>
                <th scope=column>Built / Invoiced / 1ST Registered </th>
                <th scope=column>Note</th>
              </tr>
              <tr id="filterrow">
                <th scope=column>Record</th>
                <th scope=column>Year</th>
                <th scope=column>Month</th>
                <th scope=column>Batch</th>
                <th scope=column>Type</th>
                <th scope=column>Serial</th>
                <th scope=column>Suffix</th>
                <th scope=column>Chassis/Unit</th>
                <th scope=column>Engine Letter</th>
                <th scope=column>Engine Number</th>
                <th scope=column>Gearbox</th>
                <th scope=column>Color</th>
                <th scope=column>Built</th>
                <th scope=column>Note</th>
              </tr>
            </thead>
            <tbody>
              <?php
              //Cycle through users
              foreach ($carData as $v1) {
              ?>
                <tr>
                  <td><?= $v1->id ?></td>
                  <td><?= $v1->year ?></td>
                  <td><?= $v1->month ?></td>
                  <td><?= $v1->batch ?></td>
                  <td><?= $v1->type ?></td>
                  <td><?= $v1->serial ?></td>
                  <td><?= $v1->suffix ?></td>
                  <td class="table-active">

                    <?php
                    if ($v1->suffix != "") {
                      if ($v1->year   != "") {
                        $year   = $v1->year;
                      } else {
                        $year = "yy";
                      }
                      if ($v1->month  != "") {
                        $month  = $v1->month;
                      } else {
                        $month = "mm";
                      }
                      if ($v1->batch  != "") {
                        $batch  = $v1->batch;
                      } else {
                        $batch = "bb";
                      }
                      if ($v1->serial != "") {
                        $serial = $v1->serial;
                      } else {
                        $serial = "ssss";
                      }
                      echo $year . $month . $batch . $serial;
                    } elseif ($v1->type != "") {
                      echo $v1->type . "/" . $v1->serial;
                    } ?>
                  </td>


                  <td><?= $v1->engineletter ?></td>
                  <td><?= $v1->enginenumber ?></td>
                  <td><?= $v1->gearbox ?></td>
                  <td><?= $v1->color ?></td>
                  <td><?php
                      if ($v1->builddate != "1000-01-01") {
                        echo date('Y-m-d', strtotime($v1->builddate));
                      } ?>
                  </td>
                  <td><?= $v1->note ?></td>
                </tr>
              <?php
              } ?>
            </tbody>
          </table>
        </div> <!-- card-body -->
      </div> <!-- car -->
    </div> <!-- row -->
  </div> <!-- /.container -->
</div><!-- .page-wrapper -->
<!-- End of main content section -->

<!-- Table Sorting and Such -->
<?php require_once $abs_us_root . $us_url_root . 'usersc/templates/' . $settings->template . '/datatables.php'; ?>

<script>
  $(document).ready(function() {
    // Filter each column - http://jsfiddle.net/9bc6upok/ 

    // Setup - add a text input to each footer cell
    $('#cartable thead tr#filterrow th').each(function(i) {
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
      responsive: true,
      pageLength: 25,
      scrollX: true,
      "aLengthMenu": [
        [35, 70, -1],
        [35, 70, "All"]
      ],
      "aaSorting": [
        [0, "asc"]
      ],
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