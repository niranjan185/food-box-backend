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

$start = isset($_GET['start']) && $_GET['start'] !== '' ? $_GET['start'] : null; // YYYY-MM-DD
$end   = isset($_GET['end']) && $_GET['end'] !== '' ? $_GET['end']   : null; // YYYY-MM-DD

try {
    // Detect earning column; fallback to zero if absent
    $earnCol = null;
    $colRes = $conn->query("SHOW COLUMNS FROM orders LIKE 'driver_earning'");
    if ($colRes && $colRes->num_rows > 0) { $earnCol = 'driver_earning'; }

    $today = date('Y-m-d');

    $today_earnings = 0.0;
    $week_earnings = 0.0;
    $month_earnings = 0.0;
    if ($earnCol) {
        // Today
        $stmt = $conn->prepare("SELECT COALESCE(SUM($earnCol),0) AS total
                                FROM orders o
                                WHERE o.delivery_partner_id = ?
                                  AND o.status = 'delivered'
                                  AND DATE(o.created_at) = ?");
        $stmt->bind_param('is', $driver_id, $today);
        $stmt->execute();
        $today_earnings = (float)$stmt->get_result()->fetch_assoc()['total'];

        // This week (ISO week of current date)
        $stmt = $conn->prepare("SELECT COALESCE(SUM($earnCol),0) AS total
                                FROM orders o
                                WHERE o.delivery_partner_id = ?
                                  AND o.status = 'delivered'
                                  AND YEARWEEK(o.created_at, 1) = YEARWEEK(CURDATE(), 1)");
        $stmt->bind_param('i', $driver_id);
        $stmt->execute();
        $week_earnings = (float)$stmt->get_result()->fetch_assoc()['total'];

        // This month
        $stmt = $conn->prepare("SELECT COALESCE(SUM($earnCol),0) AS total
                                FROM orders o
                                WHERE o.delivery_partner_id = ?
                                  AND o.status = 'delivered'
                                  AND YEAR(o.created_at) = YEAR(CURDATE())
                                  AND MONTH(o.created_at) = MONTH(CURDATE())");
        $stmt->bind_param('i', $driver_id);
        $stmt->execute();
        $month_earnings = (float)$stmt->get_result()->fetch_assoc()['total'];
    }

    // Breakdown (by date) within optional range
    $where = " WHERE o.delivery_partner_id = ? AND o.status = 'delivered' ";
    $params = [$driver_id];
    $types = 'i';
    if ($start) { $where .= " AND DATE(o.created_at) >= ? "; $params[] = $start; $types .= 's'; }
    if ($end)   { $where .= " AND DATE(o.created_at) <= ? "; $params[] = $end;   $types .= 's'; }

    // We don't have tips/bonus columns; expose zeros and use driver_earning as base
    $earnSelect = $earnCol ? "COALESCE(SUM($earnCol),0)" : "0";

    $sql = "SELECT DATE(o.created_at) AS day,
                   COUNT(*) AS deliveries,
                   $earnSelect AS base_pay,
                   0 AS tips,
                   0 AS bonus,
                   $earnSelect AS total
            FROM orders o
            $where
            GROUP BY DATE(o.created_at)
            ORDER BY day DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    echo json_encode([
        'success' => true,
        'cards' => [
            'today' => $today_earnings,
            'week'  => $week_earnings,
            'month' => $month_earnings
        ],
        'breakdown' => $rows,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error', 'error_detail' => $e->getMessage()]);
}
