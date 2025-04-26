<?php
/**
 * Nearby Hospitals Finder
 * 
 * This script helps users find nearby hospitals using OpenStreetMap's Nominatim API
 * and displays them on an interactive map with routing functionality.
 */

// Function to calculate distance between two coordinates using Haversine formula
function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $R = 6371; // Earth radius in km
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat/2) * sin($dLat/2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * 
         sin($dLon/2) * sin($dLon/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a)); 
    return round($R * $c, 2); // Distance in km
}

/**
 * Fetch hospitals from Nominatim API
 * 
 * @param float $lat Latitude of search center
 * @param float $lng Longitude of search center
 * @return array|null Array of hospitals or null on failure
 */
function fetchHospitals($lat, $lng) {
    $radius = 0.1; // Degrees (~11km at equator)
    $url = "https://nominatim.openstreetmap.org/search?format=json&q=hospital&bounded=1" .
           "&viewbox=" . ($lng-$radius) . "," . ($lat-$radius) . "," . 
           ($lng+$radius) . "," . ($lat+$radius) . "&limit=10";
    $options = [
        'http' => [
            'header' => "User-Agent: MediRapid Hospital Finder/1.0\r\n",
            'timeout' => 10 // Timeout in seconds
        ]
    ];
    
    try {
        $context = stream_context_create($options);
        $response = file_get_contents($url, false, $context);
        
        if ($response === false) {
            error_log("Failed to fetch hospitals from Nominatim API");
            return null;
        }
        
        return json_decode($response, true);
    } catch (Exception $e) {
        error_log("Error fetching hospitals: " . $e->getMessage());
        return null;
    }
}

// Get user location (from URL parameters)
$lat = isset($_GET['lat']) ? floatval($_GET['lat']) : null;
$lng = isset($_GET['lng']) ? floatval($_GET['lng']) : null;

// Try to get precise location from browser if no coordinates provided
if ($lat === null || $lng === null) {
    // This will be handled by JavaScript geolocation
    $locationText = "Detecting your precise location...";
} else {
    $locationText = "Showing results for your location: " . number_format($lat, 4) . ", " . number_format($lng, 4);
}

// Get hospitals data if we have coordinates
$hospitals = ($lat !== null && $lng !== null) ? fetchHospitals($lat, $lng) : null;

// Prepare hospitals data for JavaScript
$hospitalsJs = [];
if ($hospitals) {
    foreach ($hospitals as $hospital) {
        $distance = calculateDistance(
            $lat, $lng, 
            floatval($hospital['lat']), 
            floatval($hospital['lon'])
        );
        $hospitalName = explode(',', $hospital['display_name'])[0] ?: 'Hospital';
        $address = implode(',', array_slice(explode(',', $hospital['display_name']), 0, 3)) ?: 'Address not available';
        
        $hospitalsJs[] = [
            'lat' => $hospital['lat'],
            'lng' => $hospital['lon'],
            'name' => $hospitalName,
            'address' => $address,
            'distance' => $distance,
            'osm_id' => $hospital['osm_id']
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Find nearby hospitals with MediRapid's hospital locator">
    <title>Nearby Hospitals - MediRapid</title>
    
    <!-- CSS Libraries -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.css" />
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <style>
        #map { 
            height: 500px; 
            width: 100%; 
            border-radius: 8px; 
        }
        .hospital-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .hospital-card:hover {
            transform: scale(1.02);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
        }
        .loading-spinner {
            display: inline-block;
            width: 1rem;
            height: 1rem;
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        .route-modal {
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }
        .hospital-popup .leaflet-popup-content {
            margin: 8px 12px;
            line-height: 1.4;
        }
        .hospital-popup .leaflet-popup-content h3 {
            font-weight: bold;
            margin-bottom: 5px;
            color: #2563eb;
        }
        .hospital-popup .leaflet-popup-content p {
            margin-bottom: 3px;
        }
        .hospital-popup .leaflet-popup-content a {
            color: #2563eb;
            text-decoration: underline;
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-12">
        <div class="bg-white shadow-lg rounded-lg p-8">
           <!-- Header Section -->
<header class="mb-6">
    <div class="flex justify-between items-start">
        <h1 class="text-3xl font-bold text-green-700">
            <i class="fas fa-hospital mr-3"></i>Nearby Hospitals
        </h1>
        <a href="index.html" class="inline-flex items-center bg-green-700 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition duration-200 ease-in-out transform hover:-translate-y-0.5 shadow-md">
            <i class="fas fa-arrow-left mr-2"></i>Back to Home
        </a>
    </div>
</header>

            <!-- Location and Map Section -->
            <section id="locationSection" class="mb-6">
                <div id="map" class="w-full rounded-lg shadow-md"></div>
                <div class="mt-4 text-center">
                    <p id="currentLocationText" class="text-gray-600">
                        <i class="fas fa-map-marker-alt mr-2"></i><?php echo htmlspecialchars($locationText); ?>
                    </p>
                </div>
            </section>

            <!-- Hospitals Results Section -->
            <section id="hospitalsResults">
                <div id="hospitalsContainer" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php if ($hospitals && count($hospitals) > 0): ?>
                        <?php foreach ($hospitals as $hospital): 
                            $distance = calculateDistance(
                                $lat, $lng, 
                                floatval($hospital['lat']), 
                                floatval($hospital['lon'])
                            );
                            $hospitalName = explode(',', $hospital['display_name'])[0] ?: 'Hospital';
                            $address = implode(',', array_slice(explode(',', $hospital['display_name']), 0, 3)) ?: 'Address not available';
                        ?>
                            <article class="hospital-card bg-white rounded-lg shadow-md p-6">
                                <h2 class="text-xl font-bold mb-2 text-blue-600">
                                    <i class="fas fa-hospital mr-2"></i><?php echo htmlspecialchars($hospitalName); ?>
                                </h2>
                                <div class="text-gray-600 mb-4">
                                    <p class="mb-2"><i class="fas fa-map-marker-alt mr-2"></i><?php echo htmlspecialchars($address); ?></p>
                                    <p class="mb-2"><i class="fas fa-ruler mr-2"></i><?php echo $distance; ?> km away</p>
                                </div>
                                <div class="flex space-x-2">
                                    <button class="route-btn bg-green-500 text-white px-3 py-2 rounded hover:bg-green-600 transition-colors"
                                            data-lat="<?php echo $hospital['lat']; ?>" 
                                            data-lon="<?php echo $hospital['lon']; ?>"
                                            data-name="<?php echo htmlspecialchars($hospitalName); ?>">
                                        <i class="fas fa-directions mr-2"></i>Get Route
                                    </button>
                                    <a href="https://www.openstreetmap.org/?mlat=<?php echo $hospital['lat']; ?>&mlon=<?php echo $hospital['lon']; ?>#map=19/<?php echo $hospital['lat']; ?>/<?php echo $hospital['lon']; ?>" 
                                       target="_blank" rel="noopener noreferrer"
                                       class="bg-blue-500 text-white px-3 py-2 rounded hover:bg-blue-600 transition-colors">
                                        <i class="fas fa-map-marked-alt mr-2"></i>View on Map
                                    </a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php elseif ($lat !== null && $lng !== null): ?>
                        <div class="col-span-full text-center py-8">
                            <i class="fas fa-hospital text-4xl text-gray-300 mb-4"></i>
                            <p class="text-gray-500">No hospitals found in this area. Try adjusting your location.</p>
                        </div>
                    <?php else: ?>
                        <div class="col-span-full text-center py-8">
                            <i class="fas fa-spinner loading-spinner text-4xl text-gray-300 mb-4"></i>
                            <p class="text-gray-500">Detecting your location to show nearby hospitals...</p>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    </div>

    <!-- Route Details Modal -->
    <div id="routeModal" class="route-modal fixed inset-0 bg-black bg-opacity-50 z-50 hidden items-center justify-center">
        <div class="bg-white p-6 rounded-lg max-w-md w-full mx-4">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-bold text-blue-600">Route Details</h2>
                <button id="closeRouteModal" class="text-red-500 hover:text-red-700" aria-label="Close modal">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
            <div id="routeDetails" class="space-y-2">
                <!-- Route information will be added here -->
            </div>
        </div>
    </div>

    <!-- JavaScript Libraries -->
    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.js"></script>
    
    <script>
        // Constants
        const OSRM_API = 'https://router.project-osrm.org/route/v1/driving/';
        
        // Initialize map (will be set after getting location)
        let map;
        let userMarker;
        let hospitalMarkers = [];
        let routingControl = null;
        const routeModal = document.getElementById('routeModal');
        const routeDetails = document.getElementById('routeDetails');
        const closeRouteModal = document.getElementById('closeRouteModal');
        const currentLocationText = document.getElementById('currentLocationText');
        const hospitalsContainer = document.getElementById('hospitalsContainer');

        // Hospitals data from PHP
        const hospitalsData = <?php echo json_encode($hospitalsJs); ?>;

        /**
         * Initialize the map with given coordinates
         * @param {number} lat - Latitude
         * @param {number} lng - Longitude
         */
        function initMap(lat, lng) {
            // Create map if it doesn't exist
            if (!map) {
                map = L.map('map').setView([lat, lng], 13);
                
                // Add OpenStreetMap tiles
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '© OpenStreetMap contributors',
                    maxZoom: 18
                }).addTo(map);
            } else {
                map.setView([lat, lng], 13);
            }

            // Add or update user marker
            if (userMarker) {
                userMarker.setLatLng([lat, lng]);
            } else {
                userMarker = L.marker([lat, lng], {
                    icon: L.icon({
                        iconUrl: 'https://cdn.rawgit.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-blue.png',
                        shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
                        iconSize: [25, 41],
                        iconAnchor: [12, 41]
                    })
                }).addTo(map).bindPopup('Your Location').openPopup();
            }

            // Add hospital markers if we have data
            if (hospitalsData && hospitalsData.length > 0) {
                addHospitalMarkers(lat, lng);
            }

            // Update location text
            currentLocationText.innerHTML = `<i class="fas fa-map-marker-alt mr-2"></i>Showing results for your location: ${lat.toFixed(4)}, ${lng.toFixed(4)}`;
        }

        /**
         * Add hospital markers to the map
         * @param {number} userLat - User's latitude
         * @param {number} userLng - User's longitude
         */
        function addHospitalMarkers(userLat, userLng) {
            // Clear existing markers
            hospitalMarkers.forEach(marker => map.removeLayer(marker));
            hospitalMarkers = [];

            // Create custom hospital icon
            const hospitalIcon = L.icon({
                iconUrl: 'https://cdn.rawgit.com/pointhi/leaflet-color-markers/master/img/marker-icon-red.png',
                shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
                iconSize: [25, 41],
                iconAnchor: [12, 41],
                popupAnchor: [1, -34]
            });

            // Add markers for each hospital
            hospitalsData.forEach(hospital => {
                const marker = L.marker([hospital.lat, hospital.lng], {
                    icon: hospitalIcon
                }).addTo(map);
                
                // Create popup content
                const popupContent = `
                    <div class="hospital-popup">
                        <h3>${hospital.name}</h3>
                        <p><i class="fas fa-map-marker-alt"></i> ${hospital.address}</p>
                        <p><i class="fas fa-ruler"></i> ${hospital.distance} km away</p>
                        <div class="mt-2">
                            <button onclick="showRouteFromPopup(${userLat}, ${userLng}, ${hospital.lat}, ${hospital.lng}, '${hospital.name.replace(/'/g, "\\'")}')" 
                                class="bg-green-500 text-white px-2 py-1 rounded text-sm hover:bg-green-600">
                                <i class="fas fa-directions"></i> Get Directions
                            </button>
                            <a href="https://www.openstreetmap.org/?mlat=${hospital.lat}&mlon=${hospital.lng}#map=19/${hospital.lat}/${hospital.lng}" 
                               target="_blank" rel="noopener noreferrer"
                               class="bg-blue-500 text-white px-2 py-1 rounded text-sm hover:bg-blue-600 ml-1">
                                <i class="fas fa-map-marked-alt"></i> View on Map
                            </a>
                        </div>
                    </div>
                `;
                
                marker.bindPopup(popupContent);
                hospitalMarkers.push(marker);
            });
        }

        /**
         * Show route from popup click
         */
        window.showRouteFromPopup = function(userLat, userLng, hospitalLat, hospitalLng, hospitalName) {
            showRoute([userLat, userLng], [hospitalLat, hospitalLng], hospitalName);
        };

        /**
         * Load hospitals for the given location
         * @param {number} lat - Latitude
         * @param {number} lng - Longitude
         */
        function loadHospitals(lat, lng) {
            // Show loading state
            hospitalsContainer.innerHTML = `
                <div class="col-span-full text-center py-8">
                    <i class="fas fa-spinner loading-spinner text-4xl text-gray-300 mb-4"></i>
                    <p class="text-gray-500">Loading nearby hospitals...</p>
                </div>
            `;

            // Fetch hospitals via AJAX
            fetch(`?lat=${lat}&lng=${lng}&ajax=1`)
                .then(response => response.text())
                .then(html => {
                    // Replace the hospitals container with new content
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const newContainer = doc.getElementById('hospitalsContainer');
                    
                    if (newContainer) {
                        hospitalsContainer.innerHTML = newContainer.innerHTML;
                        
                        // Reattach event listeners to route buttons
                        document.querySelectorAll('.route-btn').forEach(button => {
                            button.addEventListener('click', function() {
                                const hospitalLat = parseFloat(this.dataset.lat);
                                const hospitalLng = parseFloat(this.dataset.lon);
                                const hospitalName = this.dataset.name;
                                
                                showRoute([lat, lng], [hospitalLat, hospitalLng], hospitalName);
                            });
                        });
                    }
                })
                .catch(error => {
                    console.error('Error loading hospitals:', error);
                    hospitalsContainer.innerHTML = `
                        <div class="col-span-full text-center py-8">
                            <i class="fas fa-exclamation-triangle text-4xl text-yellow-500 mb-4"></i>
                            <p class="text-gray-500">Error loading hospitals. Please try again.</p>
                        </div>
                    `;
                });
        }

        /**
         * Show route between two points
         * @param {Array} start - [latitude, longitude] of start point
         * @param {Array} end - [latitude, longitude] of end point
         * @param {string} hospitalName - Name of destination hospital
         */
        function showRoute(start, end, hospitalName) {
            // Remove existing routing
            if (routingControl) {
                map.removeControl(routingControl);
            }

            // Create new routing
            routingControl = L.Routing.control({
                waypoints: [
                    L.latLng(start[0], start[1]),
                    L.latLng(end[0], end[1])
                ],
                routeWhileDragging: true,
                show: true,
                addWaypoints: false,
                fitSelectedRoutes: true,
                showAlternatives: true,
                collapsible: true
            }).addTo(map);

            // Fetch route details
            fetch(`${OSRM_API}${start[1]},${start[0]};${end[1]},${end[0]}?overview=full&geometries=geojson`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.routes && data.routes.length > 0) {
                        const route = data.routes[0];
                        const distance = (route.distance / 1000).toFixed(2); // Convert to kilometers
                        const duration = (route.duration / 60).toFixed(0); // Convert to minutes

                        // Update route details modal
                        routeDetails.innerHTML = `
                            <p><strong>Destination:</strong> ${hospitalName}</p>
                            <p><strong>Distance:</strong> ${distance} km</p>
                            <p><strong>Estimated Travel Time:</strong> ${duration} minutes</p>
                        `;
                        showModal();
                    }
                })
                .catch(error => {
                    console.error('Route details error:', error);
                    routeDetails.innerHTML = '<p class="text-red-500">Error loading route details. Please try again.</p>';
                    showModal();
                });
        }

        /**
         * Show the route modal
         */
        function showModal() {
            routeModal.classList.remove('hidden');
            routeModal.classList.add('flex');
            document.body.style.overflow = 'hidden';
        }

        /**
         * Hide the route modal
         */
        function hideModal() {
            routeModal.classList.add('hidden');
            routeModal.classList.remove('flex');
            document.body.style.overflow = '';
        }

        // Close Route Modal
        closeRouteModal.addEventListener('click', hideModal);

        // Close modal when clicking outside
        routeModal.addEventListener('click', (e) => {
            if (e.target === routeModal) {
                hideModal();
            }
        });

        // Get precise location on page load
        document.addEventListener('DOMContentLoaded', () => {
            // Check if we already have coordinates from PHP
            const urlParams = new URLSearchParams(window.location.search);
            const latParam = urlParams.get('lat');
            const lngParam = urlParams.get('lng');
            
            if (latParam && lngParam) {
                const lat = parseFloat(latParam);
                const lng = parseFloat(lngParam);
                initMap(lat, lng);
            } else {
                // Show loading state
                currentLocationText.innerHTML = '<i class="fas fa-spinner loading-spinner mr-2"></i>Detecting your precise location...';
                
                if (navigator.geolocation) {
                    navigator.geolocation.getCurrentPosition(
                        (position) => {
                            const lat = position.coords.latitude;
                            const lng = position.coords.longitude;
                            
                            // Initialize map and load hospitals
                            initMap(lat, lng);
                            loadHospitals(lat, lng);
                            
                            // Update URL without reloading the page
                            window.history.replaceState(null, null, `?lat=${lat}&lng=${lng}`);
                        },
                        (error) => {
                            let errorMessage = 'Unable to detect your location';
                            switch(error.code) {
                                case error.PERMISSION_DENIED:
                                    errorMessage = 'Location access was denied. Please enable location services.';
                                    break;
                                case error.POSITION_UNAVAILABLE:
                                    errorMessage = 'Location information is unavailable';
                                    break;
                                case error.TIMEOUT:
                                    errorMessage = 'Location request timed out';
                                    break;
                            }
                            
                            currentLocationText.innerHTML = 
                                `<i class="fas fa-exclamation-triangle mr-2 text-yellow-500"></i>
                                 ${errorMessage}`;
                                 
                            // Show error in hospitals container
                            hospitalsContainer.innerHTML = `
                                <div class="col-span-full text-center py-8">
                                    <i class="fas fa-exclamation-triangle text-4xl text-yellow-500 mb-4"></i>
                                    <p class="text-gray-500">${errorMessage}</p>
                                    <p class="text-sm mt-2">We need your location to show nearby hospitals.</p>
                                </div>
                            `;
                        },
                        { 
                            enableHighAccuracy: true, 
                            timeout: 10000,
                            maximumAge: 0
                        }
                    );
                } else {
                    currentLocationText.innerHTML = 
                        '<i class="fas fa-exclamation-triangle mr-2 text-yellow-500"></i>Geolocation is not supported by your browser.';
                    
                    hospitalsContainer.innerHTML = `
                        <div class="col-span-full text-center py-8">
                            <i class="fas fa-exclamation-triangle text-4xl text-yellow-500 mb-4"></i>
                            <p class="text-gray-500">Geolocation is not supported by your browser.</p>
                        </div>
                    `;
                }
            }
        });
    </script>
</body>
</html>
