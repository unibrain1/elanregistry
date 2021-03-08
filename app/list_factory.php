<?php
require_once '../users/init.php';
require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php';
?>

<div id="page-wrapper">
  <div class='container-fluid'>
    <div class="well">
      <div class="row">
        <div class="col-12">
          <div class="card card-default">
            <div class='card-header text-white bg-primary'>
              <h2><strong>Elan Factory Information</strong></h2><br>
              <h5><strong>WARNING</strong> - I've lost track of where this data originated and it may be incomplete, inaccurate, false, or just plain made up.</h5>
            </div>
            <div class="card-body">
              <table id="cartable" style="width: 100%" class="table table-striped table-bordered table-sm" aria-describedby="card-header">
                <thead>
                  <tr>
                    <th scope=column>Record #</th>
                    <th scope=column>Year</th>
                    <th scope=column>Month</th>
                    <th scope=column>Batch</th>
                    <th scope=column>Type</th>
                    <th scope=column>Serial</th>
                    <th scope=column>Suffix</th>
                    <th scope=column>Engine Letter</th>
                    <th scope=column>Engine Number</th>
                    <th scope=column>Gearbox</th>
                    <th scope=column>Color</th>
                    <th scope=column>Built / Invoiced / 1ST Registered </th>
                    <th scope=column>Note</th>
                  </tr>
                </thead>
              </table>
            </div> <!-- card-body -->
          </div> <!-- car -->
        </div> <!-- row -->
      </div><!-- row -->
    </div> <!-- well -->
  </div> <!-- /.container -->
</div><!-- .page-wrapper -->
<!-- End of main content section -->

<!-- footers -->
<?php
require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php'; //custom template footer

// Table Sorting and Such
echo html_entity_decode($settings->elan_datatables_js_cdn);
echo html_entity_decode($settings->elan_datatables_css_cdn);
?>

<script>
  const img_root = '<? $us_url_root . $settings->elan_image_dir ?>';
  const csrf = '<?= Token::generate(); ?>';
  const us_url_root = '<?= $us_url_root ?>';

  var table = $('#cartable').DataTable({
    fixedHeader: true,
    responsive: true,
    pageLength: 25,
    scrollX: true,
    "aLengthMenu": [
      [25, 50, 100, -1],
      [25, 50, 100, "All"]
    ],
    caseInsensitive: true,
    "aaSorting": [
      [0, "asc"]
    ],
    "language": {
      "emptyTable": "No Data"
    },
    'processing': true,
    'serverSide': true,
    'serverMethod': 'post',

    "ajax": {
      "url": "action/getList.php",
      "dataSrc": "data",
      data: function(d) {
        d.csrf = csrf;
        d.table = 'factory';
      }
    },
    'columns': [{
        data: "id",
        'searchable': false,
        'orderable': false,
      },
      {
        data: "year",
      },
      {
        data: "month"
      },
      {
        data: "batch"
      },
      {
        data: "type"
      },
      {
        data: "serial"
      },
      {
        data: "suffix"
      },
      {
        data: "engineletter"
      },
      {
        data: "enginenumber"
      },
      {
        data: "gearbox"
      },
      {
        data: "color"
      },
      {
        data: "builddate",
      }, {
        data: "note",
      }
    ]
  });
</script>
