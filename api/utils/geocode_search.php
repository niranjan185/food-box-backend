<?php
// Simple server-side proxy for Nominatim search to avoid browser CORS
// GET params: q, limit (optional), addressdetails (optional), countrycodes (optional), email (optional)
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
if ($q === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Missing q']);
    exit;
}
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 3;
$addressdetails = isset($_GET['addressdetails']) ? (int)$_GET['addressdetails'] : 1;
$countrycodes = isset($_GET['countrycodes']) ? preg_replace('/[^a-zA-Z,]/', '', (string)$_GET['countrycodes']) : '';
$email = isset($_GET['email']) ? trim((string)$_GET['email']) : '';

$params = [
    'format' => 'json',
    'q' => $q,
    'limit' => max(1, min($limit, 10)),
    'addressdetails' => $addressdetails ? '1' : '0',
];
if ($countrycodes !== '') { $params['countrycodes'] = strtolower($countrycodes); }
if ($email !== '') { $params['email'] = $email; }

$url = 'https://nominatim.openstreetmap.org/search?' . http_build_query($params);

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
