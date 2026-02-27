<?php
session_start();
header('Content-Type: application/json');
require_once '../../db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'customer') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
if ($order_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing order_id']);
    exit;
}

try {
    // Fetch order belonging to this customer
    $sql = "SELECT o.id, o.customer_id, o.status, o.created_at, o.total_amount, o.delivery_partner_id
            FROM orders o
            WHERE o.id = ? AND o.customer_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $order_id, $_SESSION['user_id']);
    $stmt->execute();
    $res = $stmt->get_result();
    $order = $res->fetch_assoc();

    if (!$order) {
        http_response_code(404);
        echo json_encode(['error' => 'Order not found']);
        exit;
    }

    // Basic ETA logic: estimate minutes based on status
    $status = str_replace('_', '-', $order['status']);
    $eta_minutes = 30; // default
    switch ($status) {
        case 'pending':
        case 'placed':
            $eta_minutes = 35;
            break;
        case 'preparing':
            $eta_minutes = 25;
            break;
        case 'out-for-delivery':
            $eta_minutes = 10;
            break;
        case 'delivered':
            $eta_minutes = 0;
            break;
        default:
            $eta_minutes = 30;
    }

    echo json_encode([
        'success' => true,
        'order_id' => (int)$order['id'],
        'status' => $status,
        'eta_minutes' => $eta_minutes,
        'created_at' => $order['created_at'],
        'total_amount' => (float)$order['total_amount'],
        'driver_assigned' => !empty($order['delivery_partner_id'])
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
