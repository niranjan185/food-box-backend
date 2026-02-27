<?php
session_start();
header('Content-Type: application/json');
require_once '../../db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'customer') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$customer_id = (int)$_SESSION['user_id'];

try {
    $sql = "SELECT o.id AS order_id, o.total_amount, o.status, o.created_at, r.restaurant_name
            FROM orders o
            JOIN restaurant r ON o.restaurant_id = r.id
            WHERE o.customer_id = ?
            ORDER BY o.created_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $customer_id);
    $stmt->execute();
    $orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Fetch items for each order (join menu_items for names; use price_at_time)
    $sqlItems = $conn->prepare("SELECT oi.menu_item_id, mi.name AS name, oi.quantity, oi.price_at_time AS price
                                FROM order_items oi
                                JOIN menu_items mi ON mi.id = oi.menu_item_id
                                WHERE oi.order_id = ?");

    foreach ($orders as &$order) {
        $oid = (int)$order['order_id'];
        $sqlItems->bind_param('i', $oid);
        $sqlItems->execute();
        $order['items'] = $sqlItems->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    echo json_encode(['success' => true, 'orders' => $orders]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error', 'error_detail' => $e->getMessage()]);
}
