<?php
require_once '../users/init.php';
?>

<?php if (!securePage($_SERVER['PHP_SELF'])) {
    die();
} ?>

<?php
//PHP Goes Here!


$countryData = $db->query("SELECT country, COUNT(country) as count FROM users_carsview GROUP BY country ORDER BY count DESC")->results();
$typeData = $db->query("SELECT type, COUNT(type) as count FROM users_carsview GROUP BY type ORDER BY count DESC")->results();
$seriesData = $db->query("SELECT series, COUNT(series) as count FROM users_carsview GROUP BY series ORDER BY count DESC")->results();
$variantData = $db->query("SELECT variant, COUNT(variant) as count FROM users_carsview GROUP BY variant ORDER BY count DESC")->results();

$ageData = $db->query("
SELECT t.age as age,  count(*) as count
FROM (
  SELECT CASE
  WHEN ctime BETWEEN DATE_SUB( CURDATE(), INTERVAL 15 DAY ) AND CURDATE() THEN '15'
  WHEN ctime BETWEEN DATE_SUB( CURDATE(), INTERVAL 30 DAY ) AND CURDATE() THEN '30'
  WHEN ctime BETWEEN DATE_SUB( CURDATE(), INTERVAL 60 DAY ) AND CURDATE() THEN '60'
  WHEN ctime BETWEEN DATE_SUB( CURDATE(), INTERVAL 90 DAY ) AND CURDATE() THEN '90'
  WHEN ctime BETWEEN DATE_SUB( CURDATE(), INTERVAL 180 DAY ) AND CURDATE() THEN '180'
  WHEN ctime BETWEEN DATE_SUB( CURDATE(), INTERVAL 365 DAY ) AND CURDATE() THEN '365'
  END AS age
  FROM users_carsview  WHERE ctime > DATE_SUB( CURDATE(), INTERVAL 365 DAY )) t
  group by t.age ORDER BY CAST(t.age as unsigned)
")->results();


?>


<div id="page-wrapper">
<div class="container-fluid">
<div class="well">
	<h1>Statistics</h1></br>
	<div class="row">

		<div class="col-xs-12" align="center">
			<div class="panel-body">
        <div id="map" style="height: 400px; width: 80%; margin: 10px; padding: 40px;"></div>
        26 <img src="http://labs.google.com/ridefinder/images/mm_20_yellow.png"/> |
        36 <img src="http://labs.google.com/ridefinder/images/mm_20_white.png"/> |
        45 <img src="http://labs.google.com/ridefinder/images/mm_20_red.png"/> |
        50 <img src="http://labs.google.com/ridefinder/images/mm_20_blue.png"/> |
        26R <img src="http://labs.google.com/ridefinder/images/mm_20_purple.png"/> 
		</div>
	</div>

	<div class="row">
		<div class="col-xs-12 col-md-6">
			<!-- Column 1 -->
		
			<div class="panel-heading"></div>
			<div class="panel-body">
		    <!--Div that will hold the pie chart-->
		    	<div id="chart_country"></div>
	    	</div> <!-- .panel-body -->

			<div class="panel-heading"></div>
			<div class="panel-body">
		    <!--Div that will hold the pie chart-->
		    	<div id="chart_type"></div>
	    	</div> <!-- .panel-body -->

		</div> <!-- /.col -->

		<div class="col-xs-12 col-md-6">
			<!-- Column 2 -->
			<div class="panel-heading"></div>
			<div class="panel-body">
		    	<div id="chart_series"></div>
			</div> <!-- .panel-body -->
			<div class="panel-heading"></div>
			<div class="panel-body">
		    	<div id="chart_variant"></div>
			</div> <!-- .panel-body -->
			<div class="panel-heading"></div>
			<div class="panel-body">
		    	<div id="chart_age"></div>
			</div> <!-- .panel-body -->
		</div> <!-- /.col -->

	</div> <!-- /.row -->
</div> <!-- /.well -->
</div> <!-- /.container -->
</div> <!-- /.wrapper -->


<!-- footers -->
<?php require_once $abs_us_root.$us_url_root.'users/includes/page_footer.php'; // the final html footer copyright row + the external js calls?>

<!-- Google Chart https://developers.google.com/chart/interactive/docs/  -->
<!--Load the AJAX API-->
<script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
<script type="text/javascript">

	// Load the Visualization API and the corechart package.
	google.charts.load('current', {'packages':['corechart']});

	// Set a callback to run when the Google Visualization API is loaded.
	google.charts.setOnLoadCallback(drawChart1);
	google.charts.setOnLoadCallback(drawChart2);
	google.charts.setOnLoadCallback(drawChart3);
	google.charts.setOnLoadCallback(drawChart4);
	google.charts.setOnLoadCallback(drawChart5);

   function drawChart1() {
      // Create the data table.
	 var data = google.visualization.arrayToDataTable([
	 [ 	{ label: 'Country', type: 'string'},
	 	{ label: 'Count', type: 'number'}
	 ],		 
		 <?php
      foreach ($countryData as $record) {
          echo "['".$record->country."',".$record->count."],";
      }
           ?> 
	 ]);

      // Set chart options
      var options = {'title':'Cars by Country',
                     'height':400,
                      pieHole: 0.4
                 };

      // Instantiate and draw our chart, passing in some options.
      var chart = new google.visualization.PieChart(document.getElementById('chart_country'));
      chart.draw(data, options);
   }
   function drawChart2() {
      // Create the data table.
	 var data = google.visualization.arrayToDataTable([
	 [ 	{ label: 'Type', type: 'string'},
	 	{ label: 'Count', type: 'number'}
	 ],		 
		 <?php
      foreach ($typeData as $record) {
          echo "['".$record->type."',".$record->count."],";
      }
           ?> 
	 ]);

      // Set chart options
      var options = {'title':'Cars by Type',
                     'height':400,
                      pieHole: 0.4
                 };

      // Instantiate and draw our chart, passing in some options.
      var chart = new google.visualization.PieChart(document.getElementById('chart_type'));
      chart.draw(data, options);
   }     
   function drawChart3() {
      // Create the data table.
	 var data = google.visualization.arrayToDataTable([
	 [ 	{ label: 'Series', type: 'string'},
	 	{ label: 'Count', type: 'number'}
	 ],		 
		 <?php
      foreach ($seriesData as $record) {
          echo "['".$record->series."',".$record->count."],";
      }
           ?> 
	 ]);

      // Set chart options
      var options = {'title':'Cars by Series',
                     'height':400,
                      pieHole: 0.4
                 };
      // Instantiate and draw our chart, passing in some options.
      var chart = new google.visualization.PieChart(document.getElementById('chart_series'));
      chart.draw(data, options);
   }     
   function drawChart4() {
      // Create the data table.
	 var data = google.visualization.arrayToDataTable([
	 [ 	{ label: 'Variant', type: 'string'},
	 	{ label: 'Count', type: 'number'}
	 ],		 
		 <?php
              foreach ($variantData as $record) {
                  echo "['".$record->variant."',".$record->count."],";
              }
           ?> 
	 ]);

      // Set chart options
      var options = {'title':'Cars by Variant',
                     'height':400,
                      pieHole: 0.4
                 };
      // Instantiate and draw our chart, passing in some options.
      var chart = new google.visualization.PieChart(document.getElementById('chart_variant'));
      chart.draw(data, options);
   }
    function drawChart5() {
      // Create the data table.
	 var data = google.visualization.arrayToDataTable([
	 [ 	{ label: 'Age', type: 'string'},
	 	{ label: 'Count', type: 'number'}
	 ],		 
		 <?php
              foreach ($ageData as $record) {
                  echo "['".$record->age."',".$record->count."],";
              }
           ?> 
	 ]);

      // Set chart options
      var options = {'title':'Cars added in the last period',
                     'height':400,
                 };

      // Instantiate and draw our chart, passing in some options.
      var chart = new google.visualization.BarChart(document.getElementById('chart_age'));
      chart.draw(data, options);
   }

  //  The Map 

  var customIcons = {
    '26':  {url: 'http://labs.google.com/ridefinder/images/mm_20_yellow.png'},
    '36':  {url: 'http://labs.google.com/ridefinder/images/mm_20_white.png'},
    '45':  {url: 'http://labs.google.com/ridefinder/images/mm_20_red.png'},
    '50':  {url: 'http://labs.google.com/ridefinder/images/mm_20_blue.png'},
    '26R': {url: 'http://labs.google.com/ridefinder/images/mm_20_purple.png'}
  };
  function initMap() {
    var map = new google.maps.Map(document.getElementById('map'), {
      center: new google.maps.LatLng(18, 0),
      zoom: 2,
      streetViewControl: false
    });
    var infoWindow = new google.maps.InfoWindow;

    // Change this depending on the name of your PHP or XML file
    downloadUrl('mapmarkers2.xml.php/', function(data) {
      var xml = data.responseXML;

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
   
        if( image != "" ){
          var img = document.createElement('img');
          img.src = "/app/userimages/thumbs/".concat(image);
          infowincontent.appendChild(img);
          infowincontent.appendChild(document.createElement('br'));
        }

        var a = document.createElement('a');
        var linkText = document.createTextNode("Car Details");
        a.appendChild(linkText);
        a.title = "Car Details";
        a.href = "/app/car_details.php?car_id=".concat(id);
        infowincontent.appendChild(a);

        var icon = customIcons[type] || {};

        var marker = new google.maps.Marker({
          map: map,
          position: point,
          icon: icon.url

        }); // google.maps.Marker
        marker.addListener('click', function() {
          infoWindow.setContent(infowincontent);
          infoWindow.open(map, marker);
        }); // addListener
      });  // markerElem
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
<script async defer
src="https://maps.googleapis.com/maps/api/js?key=AIzaSyBXQRDsHxF-xqZc-QaH7HK_3C1srIluRLU&callback=initMap">
</script>
<style>
  /* Always set the map height explicitly to define the size of the div
   * element that contains the map. */
  #map {
    height: 100%;
  }
  #map-container img {
  max-width: none;
  }
</style>
  <!-- Place any per-page javascript here -->
<?php require_once $abs_us_root.$us_url_root.'users/includes/html_footer.php'; // currently just the closing /body and /html?>
