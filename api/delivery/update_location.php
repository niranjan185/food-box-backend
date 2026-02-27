<?php
session_start();
header('Content-Type: application/json');
require_once '../../db_connect.php';

// Accept driver sessions under various keys
$session_keys = ['delivery_id','driver_id','delivery_boy_id','rider_id'];
$driver_id = null;
foreach ($session_keys as $k) { if (isset($_SESSION[$k])) { $driver_id = (int)$_SESSION[$k]; break; } }
if (!$driver_id && isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'delivery' && isset($_SESSION['user_id'])) {
    $driver_id = (int)$_SESSION['user_id'];
}
if (!$driver_id) {
    http_response_code(401);
    echo json_encode(['success'=>false,'error'=>'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$lat = isset($input['lat']) ? (float)$input['lat'] : null;
$lng = isset($input['lng']) ? (float)$input['lng'] : null;
$accuracy = isset($input['accuracy']) ? (float)$input['accuracy'] : null;
$heading = isset($input['heading']) ? (float)$input['heading'] : null;
$speed = isset($input['speed']) ? (float)$input['speed'] : null;
$order_id = isset($input['order_id']) ? (int)$input['order_id'] : null;

if ($lat === null || $lng === null) {
    http_response_code(400);
    echo json_encode(['success'=>false,'error'=>'Missing lat/lng']);
    exit;
}

try {
    // Ensure table exists (best-effort)
    $conn->query("CREATE TABLE IF NOT EXISTS driver_locations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        driver_id INT NOT NULL,
        order_id INT NULL,
        lat DECIMAL(10,7) NOT NULL,
        lng DECIMAL(10,7) NOT NULL,
        accuracy FLOAT NULL,
        heading FLOAT NULL,
        speed FLOAT NULL,
        updated_at DATETIME NOT NULL,
        INDEX idx_driver (driver_id),
        INDEX idx_order (order_id),
        INDEX idx_updated (updated_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $stmt = $conn->prepare('INSERT INTO driver_locations (driver_id, order_id, lat, lng, accuracy, heading, speed, updated_at) VALUES (?,?,?,?,?,?,?, NOW())');
    if (!$stmt) { throw new Exception('Prepare failed: '.$conn->error); }
    // types: i i d d d d d
    if ($order_id) {
        $stmt->bind_param('iiddddd', $driver_id, $order_id, $lat, $lng, $accuracy, $heading, $speed);
    } else {
        $null = null; // will be ignored by bind types but we keep order
        $stmt->bind_param('iiddddd', $driver_id, $null, $lat, $lng, $accuracy, $heading, $speed);
    }
    $stmt->execute();

    echo json_encode(['success'=>true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>'Server error','error_detail'=>$e->getMessage()]);
}
