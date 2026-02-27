<?php
session_start();
header('Content-Type: application/json');
require_once '../../db_connect.php';
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT); }

// Resolve driver id from session
$session_keys = ['delivery_id','driver_id','delivery_boy_id','rider_id'];
$driver_id = null;
foreach ($session_keys as $k) { if (isset($_SESSION[$k])) { $driver_id = (int)$_SESSION[$k]; break; } }
if (!$driver_id && isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'delivery' && isset($_SESSION['user_id'])) {
    $driver_id = (int)$_SESSION['user_id'];
}
if (!$driver_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];

try {
    // Determine which columns are available to update safely
    $updatable = [
        'phone' => null,
        'vehicle_type' => null,
        'address' => null,
        'vehicle_make' => null,
        'vehicle_model' => null,
        'license_plate' => null,
    ];
    $available = [];
    foreach (array_keys($updatable) as $col) {
        $res = $conn->query("SHOW COLUMNS FROM delivery LIKE '".$conn->real_escape_string($col)."'");
        if ($res && $res->num_rows > 0) { $available[$col] = true; }
    }

    $sets = [];
    $params = [];
    $types = '';

    // Phone is always updatable per schema
    if (isset($input['phone']) && $input['phone'] !== '') {
        $sets[] = 'phone = ?';
        $params[] = substr(trim($input['phone']), 0, 20);
        $types .= 's';
    }

    if (isset($input['address']) && isset($available['address'])) {
        $sets[] = 'address = ?';
        $params[] = substr(trim($input['address']), 0, 255);
        $types .= 's';
    }
    if (isset($input['vehicle_type']) && isset($available['vehicle_type'])) {
        $sets[] = 'vehicle_type = ?';
        $params[] = substr(trim($input['vehicle_type']), 0, 32);
        $types .= 's';
    }
    if (isset($input['vehicle_make']) && isset($available['vehicle_make'])) {
        $sets[] = 'vehicle_make = ?';
        $params[] = substr(trim($input['vehicle_make']), 0, 100);
        $types .= 's';
    }
    if (isset($input['vehicle_model']) && isset($available['vehicle_model'])) {
        $sets[] = 'vehicle_model = ?';
        $params[] = substr(trim($input['vehicle_model']), 0, 100);
        $types .= 's';
    }
    if (isset($input['license_plate']) && isset($available['license_plate'])) {
        $sets[] = 'license_plate = ?';
        $params[] = substr(trim($input['license_plate']), 0, 32);
        $types .= 's';
    }

    if (empty($sets)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'No updatable fields provided']);
        exit;
    }

    $sql = 'UPDATE delivery SET '.implode(', ', $sets).' WHERE id = ?';
    $stmt = $conn->prepare($sql);
    $types .= 'i';
    $params[] = $driver_id;
    $stmt->bind_param($types, ...$params);
    $stmt->execute();

    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error', 'error_detail' => $e->getMessage()]);
}
