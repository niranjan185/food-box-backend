<?php
session_start();
header('Content-Type: application/json');
require_once '../../db_connect.php';
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT); }

try {
    $counts = [
        'totalCustomers' => 0,
        'totalRestaurants' => 0,
        'totalDelivery' => 0,
        'totalOrders' => 0,
    ];

    // Helper to run a COUNT(*) safely
    $tables = [
        'totalCustomers' => 'customer',
        'totalRestaurants' => 'restaurant',
        'totalDelivery' => 'delivery',
        'totalOrders' => 'orders',
    ];

    foreach ($tables as $key => $table) {
        $res = $conn->query("SELECT COUNT(*) AS c FROM `{$table}`");
        $row = $res->fetch_assoc();
        $counts[$key] = isset($row['c']) ? (int)$row['c'] : 0;
    }

    echo json_encode(['success' => true, 'counts' => $counts]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error', 'error_detail' => $e->getMessage()]);
}
