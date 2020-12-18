<div class='card' id='historyCard'>
    <div class='card-header'>
        <h2><strong>Car Update History</strong></h2>
    </div>
    <div class="card-body">
        <table id="historytable" style="width: 100%" class="table table-striped table-bordered table-sm" aria-describedby="card-header">
            <thead>
                <tr>
                    <th scope=column>Operation</th>
                    <th scope=column>Date Modified</th>
                    <th scope=column>Year</th>
                    <th scope=column>Type</th>
                    <th scope=column>Chassis</th>
                    <th scope=column>Series</th>
                    <th scope=column>Variant</th>
                    <th scope=column>Color</th>
                    <th scope=column>Engine</th>
                    <th scope=column>Purchase Date</th>
                    <th scope=column>Sold Date</th>
                    <th scope=column>Comments</th>
                    <th scope=column>Image</th>
                    <th scope=column>Owner</th>
                    <th scope=column>City</th>
                    <th scope=column>State</th>
                    <th scope=column>Country</th>
                </tr>
            </thead>
        </table>
    </div> <!-- card-body -->
</div> <!-- card -->
<?php
require_once $abs_us_root . $us_url_root . 'usersc/templates/' . $settings->template . '/datatables.php';
?>
<script>
    const img_root = <?= $us_url_root ?> + 'app/userimages/';
    // Format history table
    // Get history from AJAX call TBD
    const id = $('#car_id').val();
    var table = $('#historytable').DataTable({
        "scrollX": true,
        "order": [
            [1, "desc"]
        ],
        "language": {
            "emptyTable": "No history"
        },
        "ajax": {
            "url": "action/carGetHistory.php",
            "dataSrc": "history",
            "type": "POST",
            "data": function(d) {
                d.car_id = $('#car_id').val();
            }
        },
        "columns": [{
                "data": "operation"
            },
            {
                "data": "mtime"
            },
            {
                "data": "year"
            },
            {
                "data": "type"
            },
            {
                "data": "chassis"
            },
            {
                "data": "series"
            },
            {
                "data": "variant"
            },
            {
                "data": "color"
            },
            {
                "data": "engine"
            },
            {
                "data": "purchasedate"
            },
            {
                "data": "solddate"
            },
            {
                "data": "comments"
            },
            {
                "data": "image",
                "render": function(data, type, row, meta) {
                    if (data) {
                        return '<img src="' + img_root + 'thumbs/' + data + '">';
                    } else {
                        return "";
                    }
                }
            },
            {
                "data": "fname"
            },
            {
                "data": "city"
            },
            {
                "data": "state"
            },
            {
                "data": "country"
            }
        ]
    });
</script>