<?php
session_start();
header('Content-Type: application/json');
require_once '../../../db_connect.php';
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT); }

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'customer') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}
$customer_id = (int)$_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true) ?: [];

$label = trim($input['label'] ?? '');
$street = trim($input['street'] ?? '');
$apartment = trim($input['apartment'] ?? '');
$city = trim($input['city'] ?? '');
$state = trim($input['state'] ?? '');
$zip_code = trim($input['zip_code'] ?? '');
$country = trim($input['country'] ?? '');
$is_default = (int)!!($input['is_default'] ?? false);
// Optional precise location
$lat = isset($input['lat']) && $input['lat'] !== null ? (float)$input['lat'] : null;
$lng = isset($input['lng']) && $input['lng'] !== null ? (float)$input['lng'] : null;

if ($label === '' || $street === '' || $city === '' || $state === '' || $zip_code === '' || $country === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

try {
    $conn->begin_transaction();
    // Ensure table exists
    $conn->query("SELECT 1 FROM customer_addresses LIMIT 0");

    if ($is_default) {
        $clear = $conn->prepare('UPDATE customer_addresses SET is_default = 0 WHERE customer_id = ?');
        $clear->bind_param('i', $customer_id);
        $clear->execute();
    }

    // Detect if lat/lng columns exist
    $hasLat = false; $hasLng = false;
    try {
        $r = $conn->query("SHOW COLUMNS FROM customer_addresses LIKE 'lat'");
        if ($r && $r->num_rows > 0) $hasLat = true;
    } catch (Throwable $ignored) {}
    try {
        $r = $conn->query("SHOW COLUMNS FROM customer_addresses LIKE 'lng'");
        if ($r && $r->num_rows > 0) $hasLng = true;
    } catch (Throwable $ignored) {}

    if ($hasLat && $hasLng && $lat !== null && $lng !== null) {
        $stmt = $conn->prepare('INSERT INTO customer_addresses (customer_id, label, street, apartment, city, state, zip_code, country, lat, lng, is_default, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?, NOW())');
        // Types: i (customer_id), 7x s (label..country), d (lat), d (lng), i (is_default)
        $stmt->bind_param('isssssssddi', $customer_id, $label, $street, $apartment, $city, $state, $zip_code, $country, $lat, $lng, $is_default);
    } else {
        $stmt = $conn->prepare('INSERT INTO customer_addresses (customer_id, label, street, apartment, city, state, zip_code, country, is_default, created_at) VALUES (?,?,?,?,?,?,?,?,?, NOW())');
        $stmt->bind_param('isssssssi', $customer_id, $label, $street, $apartment, $city, $state, $zip_code, $country, $is_default);
    }
    $stmt->execute();
    $id = $stmt->insert_id;

    $conn->commit();
    echo json_encode(['success' => true, 'id' => $id]);
} catch (Throwable $e) {
    try { $conn->rollback(); } catch (Throwable $ignored) {}
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error', 'error_detail' => $e->getMessage()]);
}
