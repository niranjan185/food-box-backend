<?php
session_start();
header('Content-Type: application/json');
require_once '../../db_connect.php';
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT); }

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'customer') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$customer_id = (int)$_SESSION['user_id'];
$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
if ($order_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing order_id']);
    exit;
}

try {
    // Base order with restaurant
    $stmt = $conn->prepare("SELECT o.id AS order_id, o.total_amount, o.status, o.created_at, o.restaurant_id, r.restaurant_name
                             FROM orders o
                             JOIN restaurant r ON r.id = o.restaurant_id
                             WHERE o.id = ? AND o.customer_id = ?");
    $stmt->bind_param('ii', $order_id, $customer_id);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    if (!$order) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Order not found']);
        exit;
    }

    // Delivery address text if present
    $delivery_address_text = '';
    try {
        // Prefer delivery_address_id if column exists and set
        $addrId = null;
        $col = $conn->prepare("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='orders' AND COLUMN_NAME='delivery_address_id'");
        $col->execute();
        $hasAddrId = $col->get_result()->num_rows > 0;
        if ($hasAddrId) {
            $aidStmt = $conn->prepare("SELECT delivery_address_id FROM orders WHERE id = ?");
            $aidStmt->bind_param('i', $order_id);
            $aidStmt->execute();
            $aidRes = $aidStmt->get_result()->fetch_assoc();
            if ($aidRes && !empty($aidRes['delivery_address_id'])) {
                $addrId = (int)$aidRes['delivery_address_id'];
            }
        }
        if ($addrId) {
            $a = $conn->prepare("SELECT CONCAT_WS(', ', street, NULLIF(apartment,''), city, state, zip_code, country) AS full_addr FROM customer_addresses WHERE id = ? AND customer_id = ?");
            $a->bind_param('ii', $addrId, $customer_id);
            $a->execute();
            $ar = $a->get_result()->fetch_assoc();
            if ($ar && !empty($ar['full_addr'])) {
                $delivery_address_text = $ar['full_addr'];
            }
        }
    } catch (Throwable $ignored) {}

    // Fallback: if orders table has a direct delivery_address column (legacy)
    if ($delivery_address_text === '') {
        try {
            $col2 = $conn->prepare("SHOW COLUMNS FROM orders LIKE 'delivery_address'");
            $col2->execute();
            $hasLegacy = $col2->get_result()->num_rows > 0;
            if ($hasLegacy) {
                $ad = $conn->prepare("SELECT delivery_address FROM orders WHERE id = ?");
                $ad->bind_param('i', $order_id);
                $ad->execute();
                $row = $ad->get_result()->fetch_assoc();
                $delivery_address_text = trim((string)($row['delivery_address'] ?? ''));
            }
        } catch (Throwable $ignored) {}
    }

    // Items
    $it = $conn->prepare("SELECT oi.menu_item_id, oi.quantity, oi.price_at_time, m.name
                           FROM order_items oi
                           JOIN menu_items m ON m.id = oi.menu_item_id
                           WHERE oi.order_id = ?");
    $it->bind_param('i', $order_id);
    $it->execute();
    $items = $it->get_result()->fetch_all(MYSQLI_ASSOC);

    echo json_encode([
        'success' => true,
        'order' => [
            'order_id' => (int)$order['order_id'],
            'restaurant_id' => (int)$order['restaurant_id'],
            'restaurant_name' => $order['restaurant_name'],
            'delivery_address' => $delivery_address_text,
            'status' => $order['status'],
            'created_at' => $order['created_at'],
            'total_amount' => (float)$order['total_amount'],
            'items' => array_map(function($x){
                return [
                    'menu_item_id' => (int)$x['menu_item_id'],
                    'name' => $x['name'],
                    'quantity' => (int)$x['quantity'],
                    'price' => (float)$x['price_at_time']
                ];
            }, $items)
        ]
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error', 'error_detail' => $e->getMessage()]);
}
