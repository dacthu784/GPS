<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Distance Calculator</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
    <style>
        body,
        html {
            height: 100%;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
        }

        #controls {
            padding: 10px;
            background-color: rgba(255, 255, 255, 0.8);
            z-index: 1000;
        }

        #map-container {
            position: relative;
            flex-grow: 1;
            width: 100%;
        }

        #map {
            height: 100%;
            width: 100%;
        }

        #result {
            margin-top: 10px;
            font-weight: bold;
        }
    </style>
</head>

<body>
    <div id="controls">
        <button onclick="setStartPoint()">Set Start Point</button>
        <button onclick="setEndPoint()">Set End Point</button>
        <button onclick="calculateDistance('air')">Calculate Air Distance</button>
        <button onclick="calculateDistance('road')">Calculate Road Distance</button>
        <button onclick="useCurrentLocation()">Use Current Location</button>
        <div id="result"></div>
    </div>
    <div id="map-container">
        <div id="map"></div>
    </div>

    <script>
        let map = L.map('map').setView([0, 0], 2);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);

        let markers = L.layerGroup().addTo(map);
        let routeLine;
        let startPoint, endPoint;
        let isSettingStart = false;
        let isSettingEnd = false;

        map.on('click', function(e) {
            if (isSettingStart) {
                setPoint('start', e.latlng);
                isSettingStart = false;
            } else if (isSettingEnd) {
                setPoint('end', e.latlng);
                isSettingEnd = false;
            }
        });

        function setStartPoint() {
            isSettingStart = true;
            isSettingEnd = false;
            alert('Click on the map to set the start point');
        }

        function setEndPoint() {
            isSettingEnd = true;
            isSettingStart = false;
            alert('Click on the map to set the end point');
        }

        function setPoint(type, latlng) {
            if (type === 'start') {
                startPoint = latlng;
            } else {
                endPoint = latlng;
            }
            updateMap();
        }

        function updateMap() {
            markers.clearLayers();
            if (routeLine) {
                map.removeLayer(routeLine);
            }
            if (startPoint) {
                L.marker(startPoint).addTo(markers).bindPopup('Start Point').openPopup();
            }
            if (endPoint) {
                L.marker(endPoint).addTo(markers).bindPopup('End Point').openPopup();
            }
            if (startPoint && endPoint) {
                map.fitBounds(L.latLngBounds([startPoint, endPoint]));
            }
        }

        function decodePolyline(str, precision) {
            var index = 0,
                lat = 0,
                lng = 0,
                coordinates = [],
                shift = 0,
                result = 0,
                byte = null,
                latitude_change,
                longitude_change,
                factor = Math.pow(10, precision || 5);

            while (index < str.length) {
                byte = null;
                shift = 0;
                result = 0;

                do {
                    byte = str.charCodeAt(index++) - 63;
                    result |= (byte & 0x1f) << shift;
                    shift += 5;
                } while (byte >= 0x20);

                latitude_change = ((result & 1) ? ~(result >> 1) : (result >> 1));
                shift = result = 0;

                do {
                    byte = str.charCodeAt(index++) - 63;
                    result |= (byte & 0x1f) << shift;
                    shift += 5;
                } while (byte >= 0x20);

                longitude_change = ((result & 1) ? ~(result >> 1) : (result >> 1));

                lat += latitude_change;
                lng += longitude_change;

                coordinates.push([lat / factor, lng / factor]);
            }

            return coordinates;
        }

        function drawRoadRoute(routeGeometry) {
            if (routeLine) {
                map.removeLayer(routeLine);
            }
            // Decode the route geometry
            let decodedRoute = decodePolyline(routeGeometry);
            routeLine = L.polyline(decodedRoute, {
                color: 'blue'
            }).addTo(map);
            // Fit the map to the route bounds
            map.fitBounds(routeLine.getBounds());
        }

        function calculateDistance(mode) {
            if (!startPoint || !endPoint) {
                alert('Please set both start and end points');
                return;
            }

            fetch('/api/calculate-distance', { // Fixed typo in URL
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        start_lat: startPoint.lat,
                        start_lon: startPoint.lng,
                        end_lat: endPoint.lat,
                        end_lon: endPoint.lng,
                        mode: mode
                    })
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    let resultHTML = `Air Distance: ${data.air_distance} ${data.unit}<br>
                          Bearing: ${data.bearing}°<br>
                          Midpoint: ${data.midpoint.lat}, ${data.midpoint.lon}`;

                    if (data.road_distance) {
                        resultHTML += `<br>Road Distance: ${data.road_distance} ${data.unit}`;
                    }

                    document.getElementById('result').innerHTML = resultHTML;

                    // Draw the midpoint
                    L.marker([data.midpoint.lat, data.midpoint.lon])
                        .addTo(markers)
                        .bindPopup('Midpoint')
                        .openPopup();

                    // If it's a road calculation, draw the route
                    if (mode === 'road' && data.route_geometry) {
                        drawRoadRoute(data.route_geometry);
                    } else {
                        // For air distance, draw a straight line
                        if (routeLine) {
                            map.removeLayer(routeLine);
                        }
                        routeLine = L.polyline([startPoint, endPoint], {
                            color: 'red'
                        }).addTo(map);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('result').innerHTML = 'Error calculating distance: ' + error.message;
                });
        }

        function useCurrentLocation() {
            if ("geolocation" in navigator) {
                navigator.geolocation.getCurrentPosition(function(position) {
                    startPoint = L.latLng(position.coords.latitude, position.coords.longitude);
                    updateMap();
                    map.setView(startPoint, 13);
                }, function(error) {
                    alert("Error getting location: " + error.message);
                });
            } else {
                alert("Geolocation is not supported by your browser");
            }
        }
    </script>
</body>

</html>
