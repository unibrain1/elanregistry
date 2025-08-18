/**
 * statistics.js
 * JavaScript functionality for the statistics page including Google Charts and Maps
 * Extracted from statistics.php for better organization and maintainability
 * 
 * @author Elan Registry Admin
 * @copyright 2025
 */

// Load Google Charts
google.charts.load('current', {
  'packages': ['corechart', 'annotationchart']
});

// Set callbacks for when Google Charts API is loaded
google.charts.setOnLoadCallback(drawChart_carsbycountry);
google.charts.setOnLoadCallback(drawChart_carsbytype);
google.charts.setOnLoadCallback(drawChart_carsbyseries);
google.charts.setOnLoadCallback(drawChart_carsbyvariant);
google.charts.setOnLoadCallback(drawChart_carsbyage);
google.charts.setOnLoadCallback(drawChart_carbytime);

// Chart drawing functions
function drawChart_carsbycountry() {
  // Create data table from raw PHP data
  var data = google.visualization.arrayToDataTable(window.statisticsRawData.countryData);
  
  var options = {
    'height': 400,
    pieHole: 0.4
  };

  var chart = new google.visualization.PieChart(document.getElementById('chart_country'));
  chart.draw(data, options);
}

function drawChart_carsbytype() {
  var data = google.visualization.arrayToDataTable(window.statisticsRawData.typeData);
  
  var options = {
    'height': 400,
    pieHole: 0.4
  };

  var chart = new google.visualization.PieChart(document.getElementById('chart_type'));
  chart.draw(data, options);
}

function drawChart_carsbyseries() {
  var data = google.visualization.arrayToDataTable(window.statisticsRawData.seriesData);
  
  var options = {
    'height': 400,
    pieHole: 0.4
  };

  var chart = new google.visualization.PieChart(document.getElementById('chart_series'));
  chart.draw(data, options);
}

function drawChart_carsbyvariant() {
  var data = google.visualization.arrayToDataTable(window.statisticsRawData.variantData);
  
  var options = {
    'height': 400,
    pieHole: 0.4
  };

  var chart = new google.visualization.PieChart(document.getElementById('chart_variant'));
  chart.draw(data, options);
}

function drawChart_carsbyage() {
  var data = google.visualization.arrayToDataTable(window.statisticsRawData.ageData);
  
  var options = {
    'height': 400,
    legend: {
      position: "none"
    }
  };

  var chart = new google.visualization.BarChart(document.getElementById('chart_age'));
  chart.draw(data, options);
}

function drawChart_carbytime() {
  var data = new google.visualization.DataTable();
  data.addColumn('date', 'Date');
  data.addColumn('number', 'Count of Cars');
  data.addRows(window.statisticsRawData.timeDataRows);

  var chart = new google.visualization.AnnotationChart(document.getElementById('car_chart'));

  var options = {
    displayAnnotations: false,
    'height': 400,
    width: 900
  };

  chart.draw(data, options);
}

// Google Maps functionality
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
  downloadUrl('../cars/mapmarkers.xml.php', function(data) {
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
          img.src = window.statisticsRawData.imageDir.concat(image);
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