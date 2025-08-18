<?php
/**
 * statistics.php
 * Displays comprehensive statistics and analytics for the car registry.
 *
 * Shows various charts and visualizations including car counts by country, series, 
 * variant, registration timeline, and an interactive world map of car locations.
 * Uses Google Charts and Google Maps APIs for data visualization.
 *
 * @author Elan Registry Admin
 * @copyright 2025
 */
require_once '../users/init.php';
require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php';
?>

<?php if (!securePage($_SERVER['PHP_SELF'])) {
  die();
} ?>

<?php
$countryData     = $db->query("SELECT country, COUNT(country) as count FROM cars GROUP BY country ORDER BY count DESC")->results();
$typeData        = $db->query("SELECT type, COUNT(type) as count FROM cars GROUP BY type ORDER BY count DESC")->results();
$seriesData      = $db->query("SELECT series, COUNT(series) as count FROM cars GROUP BY series ORDER BY count DESC")->results();
$variantData     = $db->query("SELECT variant, COUNT(variant) as count FROM cars GROUP BY variant ORDER BY count DESC")->results();
$timeData        = $db->query("SELECT ctime FROM cars WHERE 1 ORDER BY `cars`.`ctime` ASC")->results();

// There should be a more efficient way to do this
$count['s1']     = $db->query("select count(*) as count from cars where series like 's1%'")->results()[0]->count;
$count['s2']     = $db->query("select count(*) as count from cars where series like 's2%'")->results()[0]->count;
$count['s3']     = $db->query("select count(*) as count from cars where series like 's3%'")->results()[0]->count;
$count['s4']     = $db->query("select count(*) as count from cars where series like 's4%'")->results()[0]->count;
$count['sprint'] = $db->query("select count(*) as count from cars where series like 'sprint%'")->results()[0]->count;
$count['+2']     = $db->query("select count(*) as count from cars where series like '+2%'")->results()[0]->count;

// Number of cars produced
$notes['s1']     = "900";
$notes['s2']     = "1250";
$notes['s3']     = "2650";
$notes['s4']     = "2976";
$notes['sprint'] = "900";
$notes['+2']     = "4526";


$ageData = $db->query("
SELECT t.age as age,  count(*) as count
FROM (
  SELECT CASE
  WHEN ctime BETWEEN DATE_SUB( CURDATE(), INTERVAL 15 DAY ) AND CURDATE() THEN '15 days'
  WHEN ctime BETWEEN DATE_SUB( CURDATE(), INTERVAL 30 DAY ) AND CURDATE() THEN '30 days'
  WHEN ctime BETWEEN DATE_SUB( CURDATE(), INTERVAL 60 DAY ) AND CURDATE() THEN '60 days'
  WHEN ctime BETWEEN DATE_SUB( CURDATE(), INTERVAL 90 DAY ) AND CURDATE() THEN '90 days'
  WHEN ctime BETWEEN DATE_SUB( CURDATE(), INTERVAL 180 DAY ) AND CURDATE() THEN '180 days'
  WHEN ctime BETWEEN DATE_SUB( CURDATE(), INTERVAL 365 DAY ) AND CURDATE() THEN '365 days'
  END AS age
  FROM cars  WHERE ctime > DATE_SUB( CURDATE(), INTERVAL 365 DAY )) t
  group by t.age ORDER BY CAST(t.age as unsigned) 
")->results();
?>

<div class="page-wrapper">
  <div class="container-fluid">
    <div class="page-container">
      <!-- Map Section -->
      <div class="row">
        <div class="col-12">
          <div class="card registry-card">
            <div class="card-header">
              <h2 class="mb-0">Where are the cars around the world</h2>
            </div>
            <div class="card-body text-center">
              <div class="map-container">
                <div id="map"></div>
              </div>
              26 <img alt="yellow pin" src="https://maps.gstatic.com/mapfiles/ridefinder-images/mm_20_yellow.png" /> |
              36 <img alt="white pin" src="https://maps.gstatic.com/mapfiles/ridefinder-images/mm_20_white.png" /> |
              45 <img alt="red pin" src="https://maps.gstatic.com/mapfiles/ridefinder-images/mm_20_red.png" /> |
              50 <img alt="blue pin" src="https://maps.gstatic.com/mapfiles/ridefinder-images//mm_20_blue.png" /> |
              26R <img alt="purple pin" src="https://maps.gstatic.com/mapfiles/ridefinder-images/mm_20_purple.png" />
            </div>
          </div>
        </div>
      </div>

      <!-- Statistics Grid -->
      <div class="row">
        <div class="col-lg-6 mb-4">
          <div class="card registry-card h-100">
            <div class="card-header">
              <h2 class="mb-0">Count of Cars by Series</h2>
            </div>
            <div class="card-body">
              <table id="seriestable" class="table table-striped table-bordered table-sm" aria-describedby="card-header">
                <thead>
                  <tr>
                    <th scope=columnd>Series</th>
                    <th scope=columnd>Count</th>
                    <th scope=columnd>Number produced *</th>
                    <th scope=columnd>Percent recorded</th>
                  </tr>
                </thead>
                <tbody>
                  <?php
                  $total = 0;
                  $totalN = 0;
                  foreach ($count as $key => $value) {
                    echo "<tr><td>" . ucfirst($key) . "</td><td>" . $value . "</td>";
                    echo "<td>" . $notes[$key] . "</td>";
                    echo "<td>" . round(($value * 100) / $notes[$key], 0) . " %</td></tr>";

                    $total += $value;
                    $totalN += $notes[$key];
                  }
                  echo "<tr><td><strong>Total</strong></td><td><strong>" . $total . "</strong></td><td>" . $totalN . "</td><td>" . round(($total * 100) / $totalN) . " %</td></tr>";
                  ?>
                </tbody>
              </table>
              <p><small>* - Number produced is from
                  <a href="https://www.amazon.com/Authentic-Lotus-1962-1974-Marques-Models/dp/0947981950">
                    Authentic Lotus Elan & Plus 2 1962 - 1974 by Robinshaw and Ross</a>, page 22 and page 138.
                  In cases where there is a range of values, I took the lower.</small></p>
            </div> <!-- body -->
          </div><!-- card -->
        </div> <!-- col -->
        <div class="col-lg-6 mb-4">
          <div class="card registry-card h-100">
            <div class="card-header">
              <h2 class="mb-0">Cars by Country</h2>
            </div>
            <div class="card-body">
              <div id="chart_country"></div>
            </div> <!-- body -->
          </div><!-- card -->
        </div> <!-- col -->
      </div> <!-- row -->

      <div class="row">
        <div class="col-lg-6 mb-4">
          <div class="card registry-card h-100">
            <div class="card-header">
              <h2 class="mb-0">Cars by Type</h2>
            </div>
            <div class="card-body">
              <div id="chart_type"></div>
            </div>
          </div>
        </div>
        <div class="col-lg-6 mb-4">
          <div class="card registry-card h-100">
            <div class="card-header">
              <h2 class="mb-0">Cars by Series</h2>
            </div>
            <div class="card-body">
              <div id="chart_series"></div>
            </div>
          </div>
        </div>
      
        <div class="col-lg-6 mb-4">
          <div class="card registry-card h-100">
            <div class="card-header">
              <h2 class="mb-0">Cars by Variant</h2>
            </div>
            <div class="card-body">
              <div id="chart_variant"></div>
            </div>
          </div>
        </div>
        
        <div class="col-lg-6 mb-4">
          <div class="card registry-card h-100">
            <div class="card-header">
              <h2 class="mb-0">Cars added in the last period</h2>
            </div>
            <div class="card-body">
              <div id="chart_age"></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Timeline Chart -->
      <div class="row">
        <div class="col-12">
          <div class="card registry-card">
            <div class="card-header">
              <h2 class="mb-0">Cars added over Time</h2>
            </div>
            <div class="card-body">
              <div id="car_chart"></div>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>

<!-- footers -->
<?php
require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php'; //custom template footer
?>

<!-- Google Chart https://developers.google.com/chart/interactive/docs/  -->
<!--Load the AJAX API-->
<script src="https://www.gstatic.com/charts/loader.js"></script>
<script>
  // Load the Visualization API and the corechart package.
  google.charts.load('current', {
    'packages': ['corechart', 'line'],
    'packages': ['annotationchart']
  });

  // Set a callback to run when the Google Visualization API is loaded.
  google.charts.setOnLoadCallback(drawChart_carsbycountry);
  google.charts.setOnLoadCallback(drawChart_carsbytype);
  google.charts.setOnLoadCallback(drawChart_carsbyseries);
  google.charts.setOnLoadCallback(drawChart_carsbyvariant);
  google.charts.setOnLoadCallback(drawChart_carsbyage);
  google.charts.setOnLoadCallback(drawChart_carbytime);


  function drawChart_carsbycountry() {
    // Create the data table.
    var data = google.visualization.arrayToDataTable([
      [{
          label: 'Country',
          type: 'string'
        },
        {
          label: 'Count',
          type: 'number'
        }
      ],
      <?php
      foreach ($countryData as $record) {
        echo "['" . $record->country . "'," . $record->count . "],";
      }
      ?>
    ]);

    // Set chart options
    var options = {
      'height': 400,
      pieHole: 0.4
    };

    // Instantiate and draw our chart, passing in some options.
    var chart = new google.visualization.PieChart(document.getElementById('chart_country'));
    chart.draw(data, options);
  }

  function drawChart_carsbytype() {
    // Create the data table.
    var data = google.visualization.arrayToDataTable([
      [{
          label: 'Type',
          type: 'string'
        },
        {
          label: 'Count',
          type: 'number'
        }
      ],
      <?php
      foreach ($typeData as $record) {
        echo "['" . $record->type . "'," . $record->count . "],";
      }
      ?>
    ]);

    // Set chart options
    var options = {
      'height': 400,
      pieHole: 0.4
    };

    // Instantiate and draw our chart, passing in some options.
    var chart = new google.visualization.PieChart(document.getElementById('chart_type'));
    chart.draw(data, options);
  }

  function drawChart_carsbyseries() {
    // Create the data table.
    var data = google.visualization.arrayToDataTable([
      [{
          label: 'Series',
          type: 'string'
        },
        {
          label: 'Count',
          type: 'number'
        }
      ],
      <?php
      foreach ($seriesData as $record) {
        echo "['" . $record->series . "'," . $record->count . "],";
      }
      ?>
    ]);

    // Set chart options
    var options = {
      'height': 400,
      pieHole: 0.4
    };
    // Instantiate and draw our chart, passing in some options.
    var chart = new google.visualization.PieChart(document.getElementById('chart_series'));
    chart.draw(data, options);
  }

  function drawChart_carsbyvariant() {
    // Create the data table.
    var data = google.visualization.arrayToDataTable([
      [{
          label: 'Variant',
          type: 'string'
        },
        {
          label: 'Count',
          type: 'number'
        }
      ],
      <?php
      foreach ($variantData as $record) {
        echo "['" . $record->variant . "'," . $record->count . "],";
      }
      ?>
    ]);

    // Set chart options
    var options = {
      'height': 400,
      pieHole: 0.4
    };
    // Instantiate and draw our chart, passing in some options.
    var chart = new google.visualization.PieChart(document.getElementById('chart_variant'));
    chart.draw(data, options);
  }

  function drawChart_carsbyage() {
    // Create the data table.
    var data = google.visualization.arrayToDataTable([
      [{
          label: 'Age',
          type: 'string'
        },
        {
          label: 'Count',
          type: 'number'
        }
      ],
      <?php
      $count = 0;
      foreach ($ageData as $record) {
        $count += $record->count;  // Count returned is the number of cars in the bucket.  Make cumulative
        echo "['" . $record->age . "'," . $count . "],";
      }
      ?>
    ]);

    // Set chart options
    var options = {
      'height': 400,
      legend: {
        position: "none"
      },

    };

    // Instantiate and draw our chart, passing in some options.
    var chart = new google.visualization.BarChart(document.getElementById('chart_age'));
    chart.draw(data, options);
  }

  function drawChart_carbytime() {
    var data = new google.visualization.DataTable();
    data.addColumn('date', 'Date');
    data.addColumn('number', 'Count of Cars');

    data.addRows([
      <?php
      $count = 0;
      foreach ($timeData as $car) {
        $count++;
        echo "[ new Date(" . date('Y, m, d, G, i, s', strtotime($car->ctime)) . "), " . $count . "],";
      }
      ?>
    ]);

    var chart = new google.visualization.AnnotationChart(document.getElementById('car_chart'));

    var options = {
      displayAnnotations: false,
      'height': 400,
      width: 900
    };

    chart.draw(data, options);
  }

  //  The Map 

  // From https://developers.google.com/maps/documentation/javascript/mysql-to-maps

  var customIcons = {
    '26': {
      url: 'https://maps.gstatic.com/mapfiles/ridefinder-images/mm_20_yellow.png'
    },
    '36': {
      url: 'https://maps.gstatic.com/mapfiles/ridefinder-images/mm_20_white.png'
    },
    '45': {
      url: 'https://maps.gstatic.com/mapfiles/ridefinder-images/mm_20_red.png'
    },
    '50': {
      url: 'https://maps.gstatic.com/mapfiles/ridefinder-images/mm_20_blue.png'
    },
    '26R': {
      url: 'https://maps.gstatic.com/mapfiles/ridefinder-images/mm_20_purple.png'
    }
  };

  function initMap() {
    var map = new google.maps.Map(document.getElementById('map'), {
      center: new google.maps.LatLng(18, 0),
      zoom: 2,
      streetViewControl: false
    });
    var infoWindow = new google.maps.InfoWindow;

    // Change this depending on the name of your PHP or XML file
    downloadUrl('mapmarkers2.xml.php', function(data) {
      var xml = data.responseXML;
      
      // Check if XML is valid before processing
      if (!xml || !xml.documentElement) {
        console.error('Invalid XML response from mapmarkers2.xml.php');
        return;
      }

      var markers = xml.documentElement.getElementsByTagName('marker');
      Array.prototype.forEach.call(markers, function(markerElem) {
        var id = markerElem.getAttribute('id');
        var series = markerElem.getAttribute('series');
        var year = markerElem.getAttribute('year');
        var type = markerElem.getAttribute('type');
        var image = markerElem.getAttribute('image');
        var url = markerElem.getAttribute('url');
        var point = new google.maps.LatLng(
          parseFloat(markerElem.getAttribute('lat')),
          parseFloat(markerElem.getAttribute('lng')));

        var infowincontent = document.createElement('div');
        var strong = document.createElement('strong');
        strong.textContent = "Series : ".concat(series)
        infowincontent.appendChild(strong);
        infowincontent.appendChild(document.createElement('br'));

        var text = document.createElement('text');
        text.textContent = "Year : ".concat(year)
        infowincontent.appendChild(text);
        infowincontent.appendChild(document.createElement('br'));

        var text = document.createElement('text');
        text.textContent = "Type : ".concat(type)
        infowincontent.appendChild(text);
        infowincontent.appendChild(document.createElement('br'));


        var a = document.createElement('a');
        var linkText = document.createTextNode("Car Details");
        a.appendChild(linkText);
        a.title = "Details";
        a.className = "btn btn-success btn-sm";
        a.href = "/app/car_details.php?car_id=".concat(id);
        infowincontent.appendChild(a);

        var icon = customIcons[type] || {};

        var marker = new google.maps.Marker({
          map: map,
          position: point,
          icon: icon.url

        }); // google.maps.Marker
        marker.addListener('click', function() {
          if (image != "") {
            var img = document.createElement('img');
            img.src = "<?= $us_url_root . $settings->elan_image_dir ?>".concat(image);
            infowincontent.appendChild(img);
            infowincontent.appendChild(document.createElement('br'));
          }
          infoWindow.setContent(infowincontent);
          infoWindow.open(map, marker);
        }); // addListener
      }); // markerElem
    });
  }

  function downloadUrl(url, callback) {
    var request = window.ActiveXObject ?
      new ActiveXObject('Microsoft.XMLHTTP') :
      new XMLHttpRequest;

    request.onreadystatechange = function() {
      if (request.readyState == 4) {
        request.onreadystatechange = doNothing;
        callback(request, request.status);
      }
    };

    request.open('GET', url, true);
    request.send(null);
  }

  function doNothing() {}
</script>
<script async defer src="https://maps.googleapis.com/maps/api/js?&key=<?= $settings->elan_google_maps_key ?>&callback=initMap"> </script>