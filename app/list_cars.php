<?php
/*

*/

require_once '../users/init.php';
require_once $abs_us_root.$us_url_root.'users/includes/header.php';
require_once $abs_us_root.$us_url_root.'users/includes/navigation.php';
if (!securePage($_SERVER['PHP_SELF'])) {
    die();
}

//PHP Goes Here!
// https://datatables.net/download/


$carQ = $db->findAll("users_carsview");
$carData = $carQ->results();

?>

    <div id="page-wrapper">
      <div class="container">
        <!-- Page Heading -->
        <div class="row">
          <div class="col-md-12">
            <h1>LIST CARS</h1>
            </hr>  
          </div>
        </div> 
      <div class="row">
        <div class="col-xs-12">
          <table id="cartable" width="100%" class='display cell-border table table-hover table-list-search compact order-column'>
            <thead>
              <tr>
                <th></th>
                <th>Year</th>
                <th>Type</th>
                <th>Chassis</th>
                <th>Series</th>
                <th>Variant</th>
                <th>Color</th>
                <th>Image</th>
                <th>First Name</th>
                <th>City</th>
                <th>State</th>
                <th>Country</th>
                <th>Date Added</th>
              </tr>
              <tr id="filterrow">
                <th>NOSEARCH</th>
                <th>Year</th>
                <th>Type</th>
                <th>Chassis</th>
                <th>Series</th>
                <th>Variant</th>
                <th>Color</th>
                <th>NOSEARCH</th>
                <th>First Name</th>
                <th>City</th>
                <th>State</th>
                <th>Country</th>
                <th>Date Added</th>

              </tr>
            </thead>
            <tbody>
              <?php
              //Cycle through users
              foreach ($carData as $v1) {
                  ?>
                <tr>
                  <td>
                  <?php  echo '<a class="btn btn-success btn-sm" href='.$us_url_root.'app/car_details.php?car_id='.$v1->id.">Details</a>" ?>
                  </td>
                  <td><?=$v1->year?></td>
                  <td><?=$v1->type?></td>
                  <td><?=$v1->chassis?></td>
                  <td><?=$v1->series?></td>
                  <td><?=$v1->variant?></td>
                  <td><?=$v1->color?></td>
                  <td> <?php
                  if ($v1->image AND file_exists($abs_us_root.$us_url_root."app/userimages/".$v1->image)) {
                        echo '<img  src='.$us_url_root.'app/userimages/thumbs/'.$v1->image.">";
                    } ?>  </td>
                  <td><?=$v1->fname?></td>
                  <td><?=$v1->city?></td>
                  <td><?=$v1->state?></td>
                  <td><?=$v1->country?></td>
                  <td><?=date('Y-m-d', strtotime($v1->ctime));?></td>                 
                </tr>
              <?php
              } ?>
            </tbody>
          </table>
      </div>
    </div>

        <!-- End of main content section -->

        <?php require_once $abs_us_root.$us_url_root.'users/includes/page_footer.php'; // the final html footer copyright row + the external js calls?>

        <!-- Place any per-page javascript here -->

        <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/v/dt/dt-1.10.18/b-1.5.4/b-colvis-1.5.4/cr-1.5.0/fc-3.2.5/fh-3.1.4/r-2.2.2/sl-1.2.6/datatables.min.css"/>
 
        <script type="text/javascript" src="https://cdn.datatables.net/v/dt/dt-1.10.18/b-1.5.4/b-colvis-1.5.4/cr-1.5.0/fc-3.2.5/fh-3.1.4/r-2.2.2/sl-1.2.6/datatables.min.js"></script>

        <link rel="stylesheet" type="text/css" href="/Registry/usersc/css/responsive.dataTables.css"/> 

        <script>
        $(document).ready(function()  {
          // Filter each column - http://jsfiddle.net/9bc6upok/ 

          // Setup - add a text input to each footer cell
            $('#cartable thead tr#filterrow th').each( function () {
                var title = $('#cartable thead tr#filterrow th').eq( $(this).index() ).text();
                // $(this).html( '<input type="text" size="6" onclick="stopPropagation(event);" placeholder="Search '+title+'" />' );
                if( title != "NOSEARCH" )
                {
                  $(this).html( '<input type="text" size="5" onclick="stopPropagation(event);" placeholder="Search" />' );
                }else{
                  $(this).html( '' );
                }
            } );
         
            // DataTable
            var table =  $('#cartable').DataTable(
            {
              "pageLength": 25,
              "aLengthMenu": [[25, 50, 100, -1], [25, 50, 100, "All"]],
              "aaSorting": [[ 1, "asc" ],[ 2, "asc" ], [3, "asc" ]],
              fixedHeader:  { headerOffset: 68 },
              "columnDefs": [ {
                "targets": 0,
                "orderable": false
                } ]
            });
             
            // Apply the filter
            $("#cartable thead input").on( 'keyup change', function () {
                table
                    .column( $(this).parent().index()+':visible' )
                    .search( this.value )
                    .draw();
            } );

          function stopPropagation(evt) {
            if (evt.stopPropagation !== undefined) {
              evt.stopPropagation();
            } else {
              evt.cancelBubble = true;
            }
          }
        } );
        </script>


      <?php require_once $abs_us_root.$us_url_root.'users/includes/html_footer.php'; // currently just the closing /body and /html?>
