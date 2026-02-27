<?php
session_start();
header('Content-Type: application/json');
require_once '../../db_connect.php';

$session_keys = ['delivery_id','driver_id','delivery_boy_id','rider_id'];
$driver_id = null;
foreach ($session_keys as $k) {
    if (isset($_SESSION[$k])) { $driver_id = (int)$_SESSION[$k]; break; }
}
if (!$driver_id && isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'delivery' && isset($_SESSION['user_id'])) {
    $driver_id = (int)$_SESSION['user_id'];
}
if (!$driver_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$order_id = isset($input['order_id']) ? (int)$input['order_id'] : 0;
if ($order_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid order id']);
    exit;
}

try {
    // Only accept if not already claimed
    $stmt = $conn->prepare("UPDATE orders SET delivery_partner_id = ?, status = CASE WHEN status IN ('preparing','ready') THEN status ELSE status END WHERE id = ? AND delivery_partner_id IS NULL");
    $stmt->bind_param('ii', $driver_id, $order_id);
    $stmt->execute();

    if ($stmt->affected_rows === 0) {
        echo json_encode(['success' => false, 'error' => 'Order already claimed or not available']);
        exit;
    }

    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
