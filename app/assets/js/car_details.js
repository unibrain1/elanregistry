/**
 * car_details.js
 * JavaScript functionality for the car details page
 * Handles DataTables initialization for car history and Google Maps display
 * 
 * @author Elan Registry Admin
 * @copyright 2025
 */

// Global variables (will be injected by PHP)
let carDetailsConfig = {};

// Initialize DataTables for car history
function initializeHistoryTable() {
    // Get car ID from the hidden input field
    const carId = $('#carid').val();
    
    if (!carId) {
        console.error('Car ID not provided');
        return;
    }

    const table = $('#historytable').DataTable({
        scrollX: true,
        responsive: true,
        order: [
            [1, 'desc']
        ],
        language: {
            'emptyTable': 'No history'
        },
        ajax: {
            url: 'action/carGetHistory.php',
            dataSrc: 'history',
            type: 'POST',
            data: function(d) {
                d.csrf = carDetailsConfig.csrf;
                d.car_id = carId;
            }
        },
        columns: [{
                data: "operation"
            },
            {
                data: "mtime"
            },
            {
                data: "year"
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
                data: "engine"
            },
            {
                data: "purchasedate"
            },
            {
                data: "solddate"
            },
            {
                data: "comments"
            },
            {
                data: "image",
                searchable: false,
                'render': function(data, type, row) {
                    if (data) {
                        return carousel(row, row.car_id);
                    } else {
                        return '';
                    }
                }
            },
            {
                data: "fname"
            }, {
                data: "city"
            }, {
                data: "state"
            }, {
                data: 'country'
            }
        ]
    });
}

// Initialize Google Maps
function initMap() {
    if (!carDetailsConfig.hasLocation) {
        return; // No location data available
    }

    // Car location coordinates
    const carLocation = {
        lat: carDetailsConfig.latitude,
        lng: carDetailsConfig.longitude
    };

    const mapElement = document.getElementById("map");

    if (mapElement) {
        // The map, centered at car location
        const map = new google.maps.Map(mapElement, {
            zoom: 8,
            center: carLocation,
            streetViewControl: false
        });

        // Use classic marker (reliable and widely supported)
        const marker = new google.maps.Marker({
            position: carLocation,
            map: map,
            title: "Car Location"
        });
    }
}

// Initialize when DOM is ready
$(document).ready(function() {
    initializeHistoryTable();
});