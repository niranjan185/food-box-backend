<?php
session_start();
header('Content-Type: application/json');
require_once '../../db_connect.php';

if (!isset($_SESSION['restaurant_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$order_id = isset($input['order_id']) ? (int)$input['order_id'] : 0;
$status = isset($input['status']) ? trim($input['status']) : '';
$allowed = ['pending','confirmed','preparing','ready','out_for_delivery','delivered','cancelled'];

if ($order_id <= 0 || !in_array($status, $allowed, true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid input']);
    exit;
}

$restaurant_id = (int)$_SESSION['restaurant_id'];

try {
    // Verify order belongs to this restaurant
    $stmt = $conn->prepare('SELECT id FROM orders WHERE id = ? AND restaurant_id = ?');
    $stmt->bind_param('ii', $order_id, $restaurant_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }

    $upd = $conn->prepare('UPDATE orders SET status = ? WHERE id = ?');
    $upd->bind_param('si', $status, $order_id);
    $upd->execute();

    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}
