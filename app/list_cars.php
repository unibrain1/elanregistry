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
            <div class="card-header">
              <h2><strong>List Cars</strong></h2>
            </div>
            <div class="card-body">
              <table id="cartable" style="width: 100%" class="table table-striped table-bordered table-sm" aria-describedby="card-header">
                <thead>
                  <tr>
                    <th scope=column>ID</th>
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
                    <th scope=column>Date Added</th>
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
  const csrf = '<?= Token::generate(); ?>';
  const us_url_root = '<?= $us_url_root ?>';
  const img_root = '<?= $us_url_root . $settings->elan_image_dir ?>';


  var table = $('#cartable').DataTable({
    fixedHeader: true,
    responsive: true,
    pageLength: 10,
    scrollX: true,
    "aLengthMenu": [
      [10, 25, 50, 100, -1],
      [10, 25, 50, 100, "All"]
    ],
    caseInsensitive: true,
    "aaSorting": [
      [1, "asc"],
      [2, "asc"],
      [3, "asc"]
    ],
    "language": {
      "emptyTable": "No Cars"
    },
    'processing': true,
    'serverSide': true,
    'serverMethod': 'post',

    "ajax": {
      "url": "action/getList.php",
      "dataSrc": "data",
      data: function(d) {
        d.csrf = csrf;
        d.table = 'cars';
      }
    },
    'columns': [{
        data: "id",
        'searchable': false,
        'orderable': false,
        'render': function(data, type, row, meta) {
          response = '<a class = "btn btn-success btn-sm" href = "' + us_url_root + 'app/car_details.php?car_id=' + data + '">Details';
          return response;
        }
      },
      {
        data: "year",
      },
      {
        data: "type"
      },
      {
        data: "chassis"
      },
      {
        data: "series"
      },
      {
        data: "variant"
      },
      {
        data: "color"
      },
      {
        data: "image",
        'searchable': false,
        'render': function(data) {
          if (data) {
            return carousel(data);
          } else {
            return "";
          }
        }
      }, {
        data: "fname"
      }, {
        data: "city"
      }, {
        data: "state"
      }, {
        data: "country"
      },
      {
        data: "ctime",
        'searchable': true,
      }
    ]
  });

  function carousel(data) {
    var images = data.split(',');
    if (images.length == 1) {
      // 1 Image
      return '<img class="card-img-top" loading="lazy" src="' + img_root + images[0] + '">';
    }
    var i;
    var response = '<div id="slider"> <div id = "myCarousel" class = "carousel slide shadow"> <div class = "carousel-inner"> <div class = "carousel-inner" > ';
    var active = 'carousel-item active';
    for (i = 0; i < images.length; i++) {
      response += "<div class='" + active + "' data-slide-number='" + i + "'>";
      response += '<img class="img-fluid card-img-top" loading="lazy" src="' + img_root + images[i] + '">';
      response += '</div>';
      active = 'carousel-item';
    }
    response += '</div><a class="carousel-control-prev" href="#myCarousel" role="button" data-slide="prev">';
    response += '<span class = "carousel-control-prev-icon" aria-hidden = "true" > </span>';
    response += '<span class = "sr-only" > Previous </span> </a> <a class = "carousel-control-next" href = "#myCarousel" role="button" data-slide = "next">';
    response += '<span class = "carousel-control-next-icon" aria-hidden = "true" > </span> <span class = "sr-only"> Next </span> </a>';
    response += '</div>';


    response += '<ul class="carousel-indicators list-inline mx-auto border px-0">';
    for (i = 0; i < images.length; i++) {
      response += '<li class="list-inline-item active">';
      response += '<a id="carousel-selector-' + i + '" class="selected" data-slide-to="' + i + '" data-target="#myCarousel">';
      response += '<img loading="lazy" src="' + img_root + images[i] + '" class="img-fluid ">';
      response += '</a> </li>';
    }
    response += '</ul> </div><div>';

    return response;

  };
</script>