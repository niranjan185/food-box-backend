<?php
// Server-side proxy for Nominatim reverse geocoding to avoid browser CORS
// GET params: lat, lon, email (optional)
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$lat = isset($_GET['lat']) ? (float)$_GET['lat'] : null;
$lon = isset($_GET['lon']) ? (float)$_GET['lon'] : null;
$email = isset($_GET['email']) ? trim((string)$_GET['email']) : '';

if ($lat === null || $lon === null) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing lat/lon']);
    exit;
}

$params = [
    'format' => 'json',
    'lat' => $lat,
    'lon' => $lon,
    'addressdetails' => '1'
];
if ($email !== '') { $params['email'] = $email; }

$url = 'https://nominatim.openstreetmap.org/reverse?' . http_build_query($params);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => 8,
    CURLOPT_HTTPHEADER => [
        'Accept: application/json'
    ],
    CURLOPT_USERAGENT => 'FoodBox-Geocoder/1.0 (+https://localhost)'
]);
$body = curl_exec($ch);
$err = curl_error($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($body === false || !$code || $code >= 400) {
    http_response_code(502);
    echo json_encode(['error' => 'Upstream error', 'detail' => $err ?: ('HTTP '.$code)]);
    exit;
}

echo $body;
