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

try {
    // Base fields from delivery table
    $stmt = $conn->prepare("SELECT id, full_name, phone, created_at FROM delivery WHERE id = ?");
    $stmt->bind_param('i', $driver_id);
    $stmt->execute();
    $base = $stmt->get_result()->fetch_assoc();
    if (!$base) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Driver not found']);
        exit;
    }

    $profile = [
        'id' => (int)$base['id'],
        'full_name' => $base['full_name'],
        'phone' => $base['phone'],
        'created_at' => $base['created_at'],
    ];

    // Optional columns: address, vehicle_type, vehicle_make, vehicle_model, license_plate, email
    $optionalCols = ['address','vehicle_type','vehicle_make','vehicle_model','license_plate','email'];
    foreach ($optionalCols as $col) {
        $res = $conn->query("SHOW COLUMNS FROM delivery LIKE '".$conn->real_escape_string($col)."'");
        if ($res && $res->num_rows > 0) {
            $q = $conn->prepare("SELECT `{$col}` AS c FROM delivery WHERE id = ?");
            $q->bind_param('i', $driver_id);
            $q->execute();
            $v = $q->get_result()->fetch_assoc();
            $profile[$col] = $v ? $v['c'] : null;
        }
    }

    echo json_encode(['success' => true, 'profile' => $profile]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error', 'error_detail' => $e->getMessage()]);
}
