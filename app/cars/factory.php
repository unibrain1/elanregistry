<?php
/**
 * list_factory.php
 * Displays factory information for Lotus Elan cars.
 * 
 * Shows a searchable, sortable table of factory records with warnings about data verification.
 * Uses DataTables for client-side features and AJAX for server-side data loading.
 * 
 * @author Elan Registry Team
 * @copyright 2025
 */
require_once '../../users/init.php';
require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php';

if (!securePage($_SERVER['PHP_SELF'])) {
  die();
}
?>

<div class="page-wrapper">
  <div class="container-fluid">
    <div class="page-container">
      <div class="row">
        <div class="col-12">
          <div class="card registry-card">
            <div class="card-header">
              <h2 class="mb-0">Elan Factory Information</h2>
              <div class="mt-2">
                <h5><strong>WARNING</strong> - This information has not been verified against the Lotus archives.</h5>
              </div>
            </div>
            <div class="card-body">
              <table id="cartable" class="table table-striped table-bordered table-sm w-100 registry-table" aria-describedby="card-header">
                <thead>
                  <tr>
                    <th scope="column">Record #</th>
                    <th scope="column">Year</th>
                    <th scope="column">Month</th>
                    <th scope="column">Batch</th>
                    <th scope="column">Type</th>
                    <th scope="column">Serial</th>
                    <th scope="column">Suffix</th>
                    <th scope="column">Engine Letter</th>
                    <th scope="column">Engine Number</th>
                    <th scope="column">Gearbox</th>
                    <th scope="column">Color</th>
                    <th scope="column">Built / Invoiced / 1ST Registered </th>
                    <th scope="column">Note</th>
                  </tr>
                </thead>
              </table>
            </div> <!-- card-body -->
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<!-- End of main content section -->

<!-- footers -->
<?php
require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php'; //custom template footer

// Table Sorting and Such
echo html_entity_decode($settings->elan_datatables_js_cdn);
echo html_entity_decode($settings->elan_datatables_css_cdn);
?>

<script>
  const img_root = '<?= $us_url_root . $settings->elan_image_dir ?>';
  const csrf = '<?= Token::generate(); ?>';
  const us_url_root = '<?= $us_url_root ?>';

  const table = $('#cartable').DataTable({
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
      "url": "../action/getDataTables.php",
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