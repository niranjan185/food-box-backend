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
$period = $_GET['period'] ?? '7'; // expect '7','30','90','all'

$rangeSql = '';
// Support both legacy numeric periods and descriptive UI periods
switch ($period) {
    // Legacy numeric periods
    case '7':
        $rangeSql = "AND o.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
        break;
    case '30':
        $rangeSql = "AND o.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
        break;
    case '90':
        $rangeSql = "AND o.created_at >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)";
        break;

    // Descriptive UI periods
    case 'today':
        $rangeSql = "AND DATE(o.created_at) = CURDATE()";
        break;
    case 'last_7_days':
        $rangeSql = "AND o.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
        break;
    case 'this_month':
        $rangeSql = "AND YEAR(o.created_at) = YEAR(CURDATE()) AND MONTH(o.created_at) = MONTH(CURDATE())";
        break;
    case 'last_30_days':
        $rangeSql = "AND o.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
        break;
    case 'last_year':
        $rangeSql = "AND YEAR(o.created_at) = YEAR(CURDATE()) - 1";
        break;
    case 'all_time':
    case 'all':
    default:
        $rangeSql = '';
}

try {
    // Summary
    $sql = "SELECT 
                COALESCE(SUM(o.total_amount),0) AS total_revenue,
                SUM(CASE WHEN o.status = 'delivered' THEN 1 ELSE 0 END) AS total_completed_orders,
                COALESCE(AVG(CASE WHEN o.status IN ('confirmed','preparing','ready','out_for_delivery','delivered') THEN o.total_amount END),0) AS average_order_value
            FROM orders o
            WHERE o.restaurant_id = ? $rangeSql";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $restaurant_id);
    $stmt->execute();
    $summary = $stmt->get_result()->fetch_assoc();

    // Detailed orders
    $sql = "SELECT o.id, o.created_at AS order_date, o.total_amount, o.status
            FROM orders o
            WHERE o.restaurant_id = ? $rangeSql
            ORDER BY o.created_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $restaurant_id);
    $stmt->execute();
    $detailed_orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    echo json_encode([
        'summary' => $summary,
        'detailed_orders' => $detailed_orders,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}
