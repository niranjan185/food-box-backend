<?php
session_start();
header('Content-Type: application/json');
require_once '../../db_connect.php';

if (!isset($_SESSION['restaurant_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$restaurant_id = (int)$_SESSION['restaurant_id'];
$status = isset($_GET['status']) && $_GET['status'] !== '' ? $_GET['status'] : null;
if ($status === 'all') { $status = null; }

try {
    $params = [$restaurant_id];
    $types = 'i';
    $whereStatus = '';
    if ($status) {
        $whereStatus = " AND o.status = ?";
        $params[] = $status;
        $types .= 's';
    }

    $sql = "SELECT o.id, o.total_amount, o.status, o.created_at AS order_date,
                   c.full_name AS customer_name, c.phone AS customer_phone,
                   '' AS delivery_address, 'COD' AS payment_method
            FROM orders o
            JOIN customer c ON c.id = o.customer_id
            WHERE o.restaurant_id = ? $whereStatus
            ORDER BY o.created_at DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Attach items for each order
    $itemStmt = $conn->prepare(
        "SELECT oi.order_id, oi.quantity, oi.price_at_time AS price_at_purchase, mi.name
         FROM order_items oi
         JOIN menu_items mi ON mi.id = oi.menu_item_id
         WHERE oi.order_id = ?"
    );

    foreach ($orders as &$order) {
        $oid = (int)$order['id'];
        $itemStmt->bind_param('i', $oid);
        $itemStmt->execute();
        $order['items'] = $itemStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    echo json_encode(['orders' => $orders]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error', 'error_detail' => $e->getMessage()]);
}
