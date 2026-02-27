<?php
session_start();
header('Content-Type: application/json');
require_once '../../db_connect.php';
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT); }

try {
    $q = isset($_GET['q']) ? trim($_GET['q']) : '';
    $status = isset($_GET['status']) ? trim($_GET['status']) : 'approved'; // approved|all

    $sql = "SELECT r.id, r.restaurant_name, r.owner_name, r.email, r.phone, r.cuisine_type, r.address, r.opening_time, r.closing_time, r.logo_url, r.status, r.is_verified
            FROM restaurant r";

    $where = [];
    $types = '';
    $params = [];

    // Only show approved restaurants to customers by default
    if ($status !== 'all') {
        // require is_verified = 1
        $where[] = 'r.is_verified = 1';
    }
    // If status column exists, avoid closed restaurants
    $hasStatus = false;
    $res = $conn->query("SHOW COLUMNS FROM restaurant LIKE 'status'");
    if ($res && $res->num_rows > 0) { $hasStatus = true; }
    if ($hasStatus) { $where[] = "(r.status IS NULL OR r.status <> 'closed')"; }

    if ($q !== '') {
        $where[] = '(r.restaurant_name LIKE CONCAT(\'%\', ?, \'%\') OR r.cuisine_type LIKE CONCAT(\'%\', ?, \'%\') OR r.address LIKE CONCAT(\'%\', ?, \'%\'))';
        $types .= 'sss';
        $params[] = $q; $params[] = $q; $params[] = $q;
    }

    if (!empty($where)) { $sql .= ' WHERE ' . implode(' AND ', $where); }
    $sql .= ' ORDER BY r.id DESC';

    $stmt = $conn->prepare($sql);
    if (!empty($types)) { $stmt->bind_param($types, ...$params); }
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    echo json_encode(['success' => true, 'restaurants' => $rows]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error', 'error_detail' => $e->getMessage()]);
}
