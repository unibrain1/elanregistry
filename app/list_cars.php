<?php
require_once '../users/init.php';
require_once $abs_us_root.$us_url_root.'users/includes/template/prep.php';

if (!securePage($_SERVER['PHP_SELF'])) {
    die();
}

// https://datatables.net/download/

$carQ = $db->findAll("users_carsview");
$carData = $carQ->results();
?>

<div id="page-wrapper">
  <div class="container-fluid">
    <div class="well">
      <div class="row">
        <div class="col-12" align="center">
          <div class="card card-default">
            <div class="card-header"><h2><strong>List Cars</strong></h2></div>
              <div class="card-body">
                <table id="cartable" width="100%" class="table-sm display compact table-bordered table-list-search ">
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
                      <th>Website</th>
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
                      <th>NOSEARCH</th>
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
                        if ($v1->image and file_exists($abs_us_root.$us_url_root."app/userimages/".$v1->image)) {
                            echo '<img src='.$us_url_root.'app/userimages/thumbs/'.$v1->image.">";
                        } ?>  </td>
                        <td><?=$v1->fname?></td>
                        <td><?=$v1->city?></td>
                        <td><?=$v1->state?></td>
                        <td><?=$v1->country?></td>
                        <?php
                          if (!empty($v1->website)) {
                              ?>
                          <td> <a target="_blank"  href="<?=$v1->website?>">Website</a></td>
                          <?php
                          } else {
                              echo "<td></td>";
                          } ?>
                        <td>
                        <?=date('Y-m-d', strtotime($v1->ctime)); ?>                

                        <?php
                          if (strtotime($v1->ctime) > strtotime('-30 days')) {
                              echo '<img style="-webkit-user-select:none; display:block; margin:auto;" src="'.$us_url_root.'app/images/new.png">';
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
  </div> <!--container -->
</div> <!-- page -->
<!-- End of main content section -->

<!-- Place any per-page javascript here -->

<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.18/css/jquery.dataTables.min.css"/>
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/fixedheader/3.1.2/css/fixedHeader.dataTables.min.css">
<script type="text/javascript" src="https://cdn.datatables.net/1.10.18/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/fixedheader/3.1.2/js/dataTables.fixedHeader.min.js" type="text/javascript"></script>


<script>
$(document).ready(function()  {
  // Filter each column - http://jsfiddle.net/9bc6upok/ 

  // Setup - add a text input to each footer cell
    $('#cartable thead tr#filterrow th').each( function ( i ) {
        var title = $('#cartable thead tr#filterrow th').eq( $(this).index() ).text();
        if( title != "NOSEARCH" )
        {
          $(this).html( '<input type="text" size="5" placeholder=" '+title+'" data-index="'+i+'" />');
        }else{
          $(this).html( '' );
        }
    } );

    // DataTable
    var table =  $('#cartable').DataTable(
    {
      fixedHeader  : true,
      pageLength   : 25,
      scrollX      : true,
      "aLengthMenu": [[25, 50, 100, -1], [25, 50, 100, "All"]],
      "aaSorting"  : [[ 1, "asc" ],[ 2, "asc" ],[ 3, "asc" ]],
      "columnDefs" : [ {
        "targets"  : 0,
        "orderable": false
        } ]
    });
    // Filter event handler
    $( table.table().container() ).on( 'keyup', 'thead input', function () {
        table
            .column( $(this).data('index') )
            .search( this.value )
            .draw();
    } );


} );
</script>

<!-- footers -->
<?php
require_once $abs_us_root . $us_url_root . 'usersc/templates/' . $settings->template . '/footer.php'; //custom template footer
?>