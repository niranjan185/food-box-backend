<?php
session_start();
header('Content-Type: application/json');
require_once '../../db_connect.php';
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT); }

try {
    // Detect an order date column
    $ordersCols = [];
    $colRes = $conn->query("SHOW COLUMNS FROM orders");
    while ($col = $colRes->fetch_assoc()) { $ordersCols[$col['Field']] = true; }
    $dateCol = null;
    foreach (['created_at','order_date','placed_at'] as $c) {
        if (isset($ordersCols[$c])) { $dateCol = $c; break; }
    }

    $labels = [];
    $values = [];
    if ($dateCol) {
        $sql = "SELECT DATE_FORMAT(`$dateCol`, '%b %Y') AS m, COUNT(id) AS c
                FROM orders
                WHERE `$dateCol` >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 5 MONTH)
                GROUP BY DATE_FORMAT(`$dateCol`, '%Y-%m')
                ORDER BY MIN(`$dateCol`) ASC";
        $res = $conn->query($sql);
        while ($r = $res->fetch_assoc()) {
            $labels[] = $r['m'];
            $values[] = (int)$r['c'];
        }
    }

    echo json_encode(['success' => true, 'labels' => $labels, 'values' => $values]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error', 'error_detail' => $e->getMessage()]);
}
