<?php
session_start();
header('Content-Type: application/json');
require_once '../../db_connect.php';
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT); }

// Auth: accept multiple keys or user_type=delivery
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

try {
    // Available orders: not yet claimed by any driver and ready to be delivered
    $sql = "SELECT o.id, o.status, o.total_amount, o.created_at AS order_date,
                   r.restaurant_name AS restaurant_name, r.address AS pickup_address,
                   c.full_name AS customer_name,
                   o.delivery_address,
                   'COD' AS payment_method
            FROM orders o
            JOIN restaurant r ON r.id = o.restaurant_id
            JOIN customer c ON c.id = o.customer_id
            WHERE o.delivery_partner_id IS NULL
              AND o.status IN ('pending','confirmed','preparing','ready')
            ORDER BY o.created_at ASC";
    $res = $conn->query($sql);
    if (!$res) throw new Exception($conn->error ?: 'Query error');
    $orders = $res->fetch_all(MYSQLI_ASSOC);

    // Attach items with robust column detection for price
    $priceCol = null;
    try {
        $resCol = $conn->query("SHOW COLUMNS FROM order_items LIKE 'price_at_time'");
        if ($resCol && $resCol->num_rows > 0) { $priceCol = 'price_at_time'; }
        if ($priceCol === null) {
            $resCol2 = $conn->query("SHOW COLUMNS FROM order_items LIKE 'price'");
            if ($resCol2 && $resCol2->num_rows > 0) { $priceCol = 'price'; }
        }
    } catch (Throwable $ignored) {}
    if ($priceCol === null) { throw new Exception('order_items missing price column'); }

    $itemSql = "SELECT oi.order_id, oi.quantity, oi.$priceCol AS price_at_purchase, mi.name
                FROM order_items oi
                JOIN menu_items mi ON mi.id = oi.menu_item_id
                WHERE oi.order_id = ?";
    $itemStmt = $conn->prepare($itemSql);
    foreach ($orders as &$o) {
        $oid = (int)$o['id'];
        $itemStmt->bind_param('i', $oid);
        $itemStmt->execute();
        $o['items'] = $itemStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    echo json_encode(['success' => true, 'orders' => $orders]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error', 'error_detail' => $e->getMessage()]);
}
