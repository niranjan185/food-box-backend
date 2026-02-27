<?php
session_start();
header('Content-Type: application/json');
require_once '../../db_connect.php';

// Resolve driver id from various session keys
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
    $today = date('Y-m-d');

    // New orders assigned to this driver (active states). You can adjust statuses as needed.
    $stmt = $conn->prepare("SELECT COUNT(*) AS cnt
                            FROM orders o
                            WHERE o.delivery_partner_id = ?
                              AND o.status IN ('pending','confirmed','preparing','ready','out_for_delivery')");
    $stmt->bind_param('i', $driver_id);
    $stmt->execute();
    $new_orders = (int)$stmt->get_result()->fetch_assoc()['cnt'];

    // Deliveries completed today by this driver
    $stmt = $conn->prepare("SELECT COUNT(*) AS cnt
                            FROM orders o
                            WHERE o.delivery_partner_id = ?
                              AND o.status = 'delivered'
                              AND DATE(o.created_at) = ?");
    $stmt->bind_param('is', $driver_id, $today);
    $stmt->execute();
    $deliveries_today = (int)$stmt->get_result()->fetch_assoc()['cnt'];

    // Earnings today for this driver (sum driver_earning)
    $earnCol = 'driver_earning';
    // Detect column existence for robustness
    $colRes = $conn->query("SHOW COLUMNS FROM orders LIKE 'driver_earning'");
    if (!$colRes || $colRes->num_rows === 0) { $earnCol = null; }

    $earnings_today = 0.0;
    if ($earnCol) {
        $stmt = $conn->prepare("SELECT COALESCE(SUM($earnCol),0) AS total
                                FROM orders o
                                WHERE o.delivery_partner_id = ?
                                  AND o.status = 'delivered'
                                  AND DATE(o.created_at) = ?");
        $stmt->bind_param('is', $driver_id, $today);
        $stmt->execute();
        $earnings_today = (float)$stmt->get_result()->fetch_assoc()['total'];
    }

    echo json_encode([
        'success' => true,
        'new_orders' => $new_orders,
        'deliveries_today' => $deliveries_today,
        'earnings_today' => $earnings_today,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error', 'error_detail' => $e->getMessage()]);
}
