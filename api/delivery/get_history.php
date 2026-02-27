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
$start = isset($_GET['start']) && $_GET['start'] !== '' ? $_GET['start'] : null; // YYYY-MM-DD
$end = isset($_GET['end']) && $_GET['end'] !== '' ? $_GET['end'] : null;   // YYYY-MM-DD

try {
    $where = " WHERE o.delivery_partner_id = ? AND o.status IN ('delivered','cancelled') ";
    $params = [$driver_id];
    $types = 'i';

    if ($start) {
        $where .= " AND DATE(o.created_at) >= ? ";
        $params[] = $start;
        $types .= 's';
    }
    if ($end) {
        $where .= " AND DATE(o.created_at) <= ? ";
        $params[] = $end;
        $types .= 's';
    }

    $sql = "SELECT o.id, o.status, o.total_amount, o.created_at,
                   r.restaurant_name AS restaurant_name,
                   c.full_name AS customer_name,
                   o.delivery_address,
                   COALESCE(o.driver_earning, 0) AS earning
            FROM orders o
            JOIN restaurant r ON r.id = o.restaurant_id
            JOIN customer c ON c.id = o.customer_id
            $where
            ORDER BY o.created_at DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    echo json_encode(['success' => true, 'history' => $rows]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error', 'error_detail' => $e->getMessage()]);
}
