<?php
session_start();
header('Content-Type: application/json');
require_once '../../db_connect.php';

$session_keys = ['delivery_id','driver_id','delivery_boy_id','rider_id'];
$driver_id = null;
foreach ($session_keys as $k) {
    if (isset($_SESSION[$k])) { $driver_id = (int)$_SESSION[$k]; break; }
}
// Fallback: app uses generic user_id + user_type ('customer' | 'delivery')
if (!$driver_id && isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'delivery' && isset($_SESSION['user_id'])) {
    $driver_id = (int)$_SESSION['user_id'];
}
if (!$driver_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}
$status = isset($_GET['status']) && $_GET['status'] !== '' ? $_GET['status'] : null; // optional filter

try {
    $params = [$driver_id];
    $types = 'i';
    $where = ' WHERE o.delivery_partner_id = ? ';

    if ($status && $status !== 'all') {
        $where .= ' AND o.status = ? ';
        $params[] = $status;
        $types .= 's';
    } else {
        // Default: show active assigned orders including early states
        $where .= " AND o.status IN ('pending','confirmed','preparing','ready','out_for_delivery') ";
    }

    // Detect optional columns for coordinates
    $hasAddrId = false;
    $hasOrderLat = false; $hasOrderLng = false;
    $hasRestaurantLat = false; $hasRestaurantLng = false;
    $hasCaLat = false; $hasCaLng = false;
    try {
        $colRes = $conn->query("SHOW COLUMNS FROM orders LIKE 'delivery_address_id'");
        $hasAddrId = $colRes && $colRes->num_rows > 0;
    } catch (Throwable $ignored) {}
    try { $r=$conn->query("SHOW COLUMNS FROM orders LIKE 'delivery_lat'"); if ($r&&$r->num_rows>0) $hasOrderLat=true; } catch(Throwable $ignored){}
    try { $r=$conn->query("SHOW COLUMNS FROM orders LIKE 'delivery_lng'"); if ($r&&$r->num_rows>0) $hasOrderLng=true; } catch(Throwable $ignored){}
    try { $r=$conn->query("SHOW COLUMNS FROM restaurant LIKE 'lat'"); if ($r&&$r->num_rows>0) $hasRestaurantLat=true; } catch(Throwable $ignored){}
    try { $r=$conn->query("SHOW COLUMNS FROM restaurant LIKE 'lng'"); if ($r&&$r->num_rows>0) $hasRestaurantLng=true; } catch(Throwable $ignored){}
    try { $r=$conn->query("SHOW COLUMNS FROM customer_addresses LIKE 'lat'"); if ($r&&$r->num_rows>0) $hasCaLat=true; } catch(Throwable $ignored){}
    try { $r=$conn->query("SHOW COLUMNS FROM customer_addresses LIKE 'lng'"); if ($r&&$r->num_rows>0) $hasCaLng=true; } catch(Throwable $ignored){}

    // Build SELECT with conditional coordinate fields
    $pickupLatExpr = $hasRestaurantLat ? 'r.lat' : 'NULL';
    $pickupLngExpr = $hasRestaurantLng ? 'r.lng' : 'NULL';
    $dropLatExpr = $hasOrderLat ? 'o.delivery_lat' : ($hasAddrId && $hasCaLat ? 'ca.lat' : 'NULL');
    $dropLngExpr = $hasOrderLng ? 'o.delivery_lng' : ($hasAddrId && $hasCaLng ? 'ca.lng' : 'NULL');

    if ($hasAddrId) {
        $sql = "SELECT o.id, o.status, o.total_amount, o.created_at AS order_date,
                       r.restaurant_name AS restaurant_name, r.address AS pickup_address,
                       c.full_name AS customer_name,
                       COALESCE(CONCAT_WS(', ', ca.street, NULLIF(ca.apartment,''), ca.city, ca.state, ca.zip_code, ca.country), o.delivery_address) AS delivery_address,
                       'COD' AS payment_method,
                       $pickupLatExpr AS pickup_lat,
                       $pickupLngExpr AS pickup_lng,
                       $dropLatExpr AS delivery_lat,
                       $dropLngExpr AS delivery_lng
                FROM orders o
                JOIN restaurant r ON r.id = o.restaurant_id
                JOIN customer c ON c.id = o.customer_id
                LEFT JOIN customer_addresses ca ON ca.id = o.delivery_address_id
                $where
                ORDER BY o.created_at DESC";
    } else {
        $sql = "SELECT o.id, o.status, o.total_amount, o.created_at AS order_date,
                       r.restaurant_name AS restaurant_name, r.address AS pickup_address,
                       c.full_name AS customer_name, o.delivery_address,
                       'COD' AS payment_method,
                       $pickupLatExpr AS pickup_lat,
                       $pickupLngExpr AS pickup_lng,
                       $dropLatExpr AS delivery_lat,
                       $dropLngExpr AS delivery_lng
                FROM orders o
                JOIN restaurant r ON r.id = o.restaurant_id
                JOIN customer c ON c.id = o.customer_id
                $where
                ORDER BY o.created_at DESC";
    }

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Items per order
    $itemStmt = $conn->prepare(
        "SELECT oi.order_id, oi.quantity, oi.price_at_time AS price_at_purchase, mi.name
         FROM order_items oi
         JOIN menu_items mi ON mi.id = oi.menu_item_id
         WHERE oi.order_id = ?"
    );

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
